jQuery(document).ready(function ($) {
  const mapConfig = carListingsMapFilterData.mapboxConfig;
  const ajaxurl = carListingsMapFilterData.ajaxurl;
  const nonce = carListingsMapFilterData.nonce;
  let initialFilter = carListingsMapFilterData.initialFilter;

  let map = null;
  let marker = null;
  let geocoder = null;
  let selectedCoords = null; // LngLat array [lng, lat]
  let selectedLocationName = initialFilter.text || "All of Cyprus"; // For display
  let currentRadiusKm = initialFilter.radius || 150; // Default radius from server config
  const circleSourceId = "radius-circle-source";
  const circleLayerId = "radius-circle-layer";
  let moveTimeout; // For debouncing map moveend
  let currentListingsRequest = null; // Variable to hold the current AJAX request

  const modal = $("#location-filter-modal");
  const changeLocationBtn = $("#change-location-filter-btn");
  const closeBtn = $("#close-location-filter-modal");
  const applyBtn = $("#apply-location-filter-btn");
  const mapContainer = $("#filter-map-container");
  const geocoderContainer = $("#filter-geocoder");
  const radiusSlider = $("#radius-slider");
  const radiusValueDisplay = $("#radius-value");
  const currentLocationText = $("#current-location-filter-text");

  const specFiltersPopup = $("#spec-filters-popup");
  const openSpecFiltersBtn = $("#open-spec-filters-popup-btn");
  const closeSpecFiltersBtn = $("#close-spec-filters-popup-btn");
  const applySpecFiltersBtn = $("#apply-spec-filters-btn"); // From car-filter-form.php
  const resetSpecFiltersBtn = $("#reset-spec-filters-btn"); // From car-filter-form.php
  const specFiltersContainer = $("#car-spec-filters-container"); // Container of all spec filters

  // Initialize UI elements with defaults
  radiusSlider.val(currentRadiusKm);
  radiusValueDisplay.text(currentRadiusKm);
  currentLocationText.text(selectedLocationName);

  // --- Initialize from URL parameters if present ---
  const urlParams = new URLSearchParams(window.location.search);
  const urlLat = urlParams.get("lat");
  const urlLng = urlParams.get("lng");
  const urlRadius = urlParams.get("radius");
  const urlLocationName = urlParams.get("location_name");

  let loadedFromStorage = false;
  let isUsingCustomLocation = false; // Track if we're using a custom location vs Cyprus default

  if (urlLat && urlLng && urlRadius) {
    // Check if this is the Cyprus default location or a custom location
    const urlLatFloat = parseFloat(urlLat);
    const urlLngFloat = parseFloat(urlLng);
    const urlRadiusInt = parseInt(urlRadius, 10);
    
    if (Math.abs(urlLatFloat - initialFilter.lat) < 0.001 && 
        Math.abs(urlLngFloat - initialFilter.lng) < 0.001 && 
        urlRadiusInt === initialFilter.radius) {
      // This is the Cyprus default location
      isUsingCustomLocation = false;
      selectedLocationName = "All of Cyprus";
    } else {
      // This is a custom location
      isUsingCustomLocation = true;
      selectedLocationName = urlLocationName || "Selected on map";
    }
    
    initialFilter = {
      lat: urlLatFloat,
      lng: urlLngFloat,
      radius: urlRadiusInt,
      text: selectedLocationName,
    };
    selectedCoords = [initialFilter.lng, initialFilter.lat];
    currentRadiusKm = initialFilter.radius;
    console.log("[Init] Loaded filter from URL:", initialFilter);
  } else {
    // --- Try to load from localStorage if not in URL ---
    const savedLocation = localStorage.getItem("autoAgoraUserLocation");
    if (savedLocation) {
      try {
        const parsedLocation = JSON.parse(savedLocation);
        if (parsedLocation.lat && parsedLocation.lng && parsedLocation.radius) {
          // Check if the saved location is Cyprus default or custom
          if (Math.abs(parsedLocation.lat - initialFilter.lat) < 0.001 && 
              Math.abs(parsedLocation.lng - initialFilter.lng) < 0.001 && 
              parsedLocation.radius === initialFilter.radius) {
            // This is Cyprus default
            isUsingCustomLocation = false;
            selectedLocationName = "All of Cyprus";
          } else {
            // This is a custom location
            isUsingCustomLocation = true;
            selectedLocationName = parsedLocation.name || "Saved location";
          }
          
          initialFilter = {
            lat: parseFloat(parsedLocation.lat),
            lng: parseFloat(parsedLocation.lng),
            radius: parseInt(parsedLocation.radius, 10),
            text: selectedLocationName,
          };
          selectedCoords = [initialFilter.lng, initialFilter.lat];
          currentRadiusKm = initialFilter.radius;
          loadedFromStorage = true;
          console.log("[Init] Loaded filter from localStorage:", initialFilter);
        }
      } catch (e) {
        console.error("[Init] Error parsing location from localStorage:", e);
        localStorage.removeItem("autoAgoraUserLocation"); // Clear corrupted data
      }
    }
    
    // If no localStorage data or it was invalid, ensure we have Cyprus defaults set
    if (!loadedFromStorage) {
      selectedCoords = [initialFilter.lng, initialFilter.lat];
      currentRadiusKm = initialFilter.radius;
      selectedLocationName = initialFilter.text;
      isUsingCustomLocation = false;
      console.log("[Init] Using Cyprus default location filter:", initialFilter);
    }
  }

  // Update UI elements with final values
  radiusSlider.val(currentRadiusKm);
  radiusValueDisplay.text(currentRadiusKm);
  currentLocationText.text(selectedLocationName);
  
  // Update radius preset buttons to reflect initial state
  setTimeout(() => {
    updateRadiusPresetButtons(currentRadiusKm);
  }, 100);
  
  if (loadedFromStorage) {
    console.log("[Init] UI updated with localStorage data.");
  } else if (urlLat && urlLng && urlRadius) {
    console.log("[Init] UI updated with URL data.");
  } else {
    console.log("[Init] UI updated with Cyprus defaults.");
  }

  // Update URL if filter was loaded from localStorage and not from URL parameters
  if (loadedFromStorage && !(urlLat && urlLng && urlRadius)) {
    const currentUrl = new URL(window.location.href);
    if (
      initialFilter &&
      initialFilter.lat &&
      initialFilter.lng &&
      initialFilter.radius &&
      initialFilter.text
    ) {
      currentUrl.searchParams.set("lat", initialFilter.lat.toFixed(7));
      currentUrl.searchParams.set("lng", initialFilter.lng.toFixed(7));
      currentUrl.searchParams.set("radius", initialFilter.radius.toString());
      currentUrl.searchParams.set("location_name", initialFilter.text);

      // Manage 'paged' parameter: if not in original URL, ensure it implies page 1 for the new state
      if (!urlParams.has("paged")) {
        currentUrl.searchParams.delete("paged"); // Or set to 1: currentUrl.searchParams.set('paged', '1');
      }
      history.pushState({ path: currentUrl.href }, "", currentUrl.href);
      console.log(
        "[Init] URL updated from localStorage data:",
        currentUrl.href
      );
    } else {
      console.warn(
        "[Init] Tried to update URL from localStorage, but initialFilter data was incomplete."
      );
    }
  }
  // --- End Initialize from URL parameters / localStorage ---

  changeLocationBtn.on("click", function () {
    modal.show();
    if (!map) {
      initializeMap();
    } else {
      map.resize();
      if (selectedCoords) {
        map.setCenter(selectedCoords);
        updateMarkerPosition(selectedCoords);
        updateRadiusCircle(selectedCoords, currentRadiusKm);
      } else {
        // If no coords, center on default and place marker there
        map.setCenter(mapConfig.cyprusCenter);
        const centerArray = map.getCenter().toArray();
        updateMarkerPosition(centerArray);
        updateRadiusCircle(centerArray, currentRadiusKm);
      }
    }
    
    // Ensure radius preset buttons are updated when modal opens
    setTimeout(() => {
      updateRadiusPresetButtons(currentRadiusKm);
    }, 100);
  });

  closeBtn.on("click", function () {
    modal.hide();
  });

  modal.on("click", function (e) {
    if ($(e.target).is(modal)) {
      modal.hide();
    }
  });

  // --- Locate Me Control for Mapbox ---
  class LocateMeControl {
    onAdd(mapInstance) {
      this._map = mapInstance;
      this._container = document.createElement("div");
      this._container.className = "mapboxgl-ctrl mapboxgl-ctrl-group";

      const button = document.createElement("button");
      button.className = "mapboxgl-ctrl-text mapboxgl-ctrl-locate-me";
      button.type = "button";
      button.title = "Find my current location";
      button.setAttribute("aria-label", "Find my current location");
      button.textContent = "Find my current location";

      button.onclick = () => {
        if (!navigator.geolocation) {
          alert("Geolocation is not supported by your browser.");
          return;
        }

        // Add a loading/locating indicator to the button if desired
        button.classList.add("mapboxgl-ctrl-geolocate-active"); // Optional: for styling during location fetch

        navigator.geolocation.getCurrentPosition(
          (position) => {
            const newCoords = [
              position.coords.longitude,
              position.coords.latitude,
            ];
            selectedCoords = newCoords; // Update the shared selectedCoords

            this._map.flyTo({
              center: newCoords,
              zoom: 14, // Zoom in closer for better context
            });
            // The map's 'moveend' event will handle marker update, reverse geocode, and radius update.
            button.classList.remove("mapboxgl-ctrl-geolocate-active"); // Remove loading state
          },
          (error) => {
            alert(`Error getting location: ${error.message}`);
            console.error("Geolocation error:", error);
            button.classList.remove("mapboxgl-ctrl-geolocate-active"); // Remove loading state
          },
          {
            enableHighAccuracy: true,
            timeout: 8000, // Increased timeout
            maximumAge: 0,
          }
        );
      };

      this._container.appendChild(button);
      return this._container;
    }

    onRemove() {
      if (this._container && this._container.parentNode) {
        this._container.parentNode.removeChild(this._container);
      }
      this._map = undefined;
    }
  }
  // --- End Locate Me Control ---

  function initializeMap() {
    if (!mapConfig.accessToken) {
      console.error("Mapbox Access Token is not configured.");
      mapContainer.html(
        '<p style="color:red;text-align:center;margin-top:20px;">Map configuration error.</p>'
      );
      return;
    }
    mapboxgl.accessToken = mapConfig.accessToken;

    let initialMapCenter = mapConfig.cyprusCenter;
    if (initialFilter.lat && initialFilter.lng) {
      initialMapCenter = [initialFilter.lng, initialFilter.lat];
      selectedCoords = initialMapCenter;
    }

    map = new mapboxgl.Map({
      container: "filter-map-container",
      style: mapConfig.style,
      center: initialMapCenter,
      zoom: mapConfig.defaultZoom,
      scrollZoom: { around: "center" },
      transformRequest: (url, resourceType) => {
        if (url.includes("events.mapbox.com")) return { url: "" };
        return { url };
      },
    });

    map.on("load", function () {
      map.addControl(new mapboxgl.NavigationControl());
      map.addControl(new LocateMeControl(), "bottom-right"); // Changed from top-right to bottom-right

      const currentCenterArray = map.getCenter().toArray();
      updateMarkerPosition(currentCenterArray);
      if (selectedCoords) {
        updateRadiusCircle(selectedCoords, currentRadiusKm);
      } else {
        updateRadiusCircle(currentCenterArray, currentRadiusKm);
        selectedCoords = currentCenterArray;
      }
      
      // Initialize radius preset buttons
      updateRadiusPresetButtons(currentRadiusKm);

      geocoder = new MapboxGeocoder({
        accessToken: mapboxgl.accessToken,
        mapboxgl: mapboxgl,
        marker: false,
        placeholder: "Search for a location in Cyprus...",
        countries: "CY",
        language: "en",
      });

      geocoderContainer.empty().append(geocoder.onAdd(map));

      geocoder.on("result", function (ev) {
        const resultCoords = ev.result.center;
        selectedCoords = resultCoords;
        selectedLocationName = ev.result.place_name;
        map.setCenter(resultCoords); // This will trigger move and moveend
        // Marker and circle will update via map move/moveend events
      });

      geocoder.on("clear", function () {
        console.log("Geocoder cleared");
        // Optional: Implement full reset logic here if desired
        // e.g., reset selectedCoords, selectedLocationName, fly to default, update marker/circle
      });
    });

    map.on("move", () => {
      const centerArray = map.getCenter().toArray();
      if (marker) {
        marker.setLngLat(centerArray);
      }
      // Update circle visually during move for smoothness
      updateRadiusCircle(centerArray, currentRadiusKm);
    });

    map.on("moveend", () => {
      if (moveTimeout) {
        clearTimeout(moveTimeout);
      }
      moveTimeout = setTimeout(() => {
        const centerArray = map.getCenter().toArray();
        selectedCoords = centerArray;
        // Marker is already at center due to 'move' event
        // Circle is also updated visually due to 'move' event
        // Final update of circle data source is good practice here too
        updateRadiusCircle(centerArray, currentRadiusKm);

        reverseGeocode(centerArray, (name) => {
          selectedLocationName = name || "Area around selected point";

          // Directly set the input field's value and then blur it
          if (geocoder && geocoder._inputEl) {
            console.log(
              "[Move End] Directly setting geocoder input value to:",
              selectedLocationName
            );
            geocoder._inputEl.value = selectedLocationName; // Directly set the value

            if (typeof geocoder._inputEl.blur === "function") {
              console.log(
                "[Move End] Blurring geocoder input after direct value set."
              );
              geocoder._inputEl.blur();
            }
          } else {
            console.warn(
              "[Move End] Geocoder or its input element (_inputEl) not available for updating input."
            );
          }
        });
      }, 250);
    });

    map.on("click", (e) => {
      const clickedCoords = [e.lngLat.lng, e.lngLat.lat];
      selectedCoords = clickedCoords; // Set selectedCoords immediately on click
      map.flyTo({ center: clickedCoords }); // flyTo will trigger move and moveend
    });
  }

  function updateMarkerPosition(lngLatArray) {
    if (!map) return;
    if (!marker) {
      marker = new mapboxgl.Marker({ draggable: false, color: "#FF0000" })
        .setLngLat(lngLatArray)
        .addTo(map);
    } else {
      marker.setLngLat(lngLatArray);
    }
  }

  function updateRadiusCircle(centerLngLatArray, radiusKm) {
    if (!map || !turf || !centerLngLatArray || centerLngLatArray.length !== 2)
      return;

    const center = turf.point(centerLngLatArray);
    const circlePolygon = turf.circle(center, radiusKm, {
      steps: 64,
      units: "kilometers",
    });

    let source = map.getSource(circleSourceId);
    if (source) {
      source.setData(circlePolygon);
    } else {
      map.addSource(circleSourceId, {
        type: "geojson",
        data: circlePolygon,
      });
      map.addLayer({
        id: circleLayerId,
        type: "fill",
        source: circleSourceId,
        paint: {
          "fill-color": "#007cbf",
          "fill-opacity": 0.3,
        },
      });
    }
  }

  function reverseGeocode(coordsLngLatArray, callback) {
    if (
      !mapConfig.accessToken ||
      !coordsLngLatArray ||
      coordsLngLatArray.length !== 2
    ) {
      callback(null); // Ensure callback is called even on invalid input
      return;
    }
    const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${coordsLngLatArray[0]},${coordsLngLatArray[1]}.json?access_token=${mapConfig.accessToken}&types=place,locality,neighborhood,address&limit=1&country=CY`;

    fetch(url)
      .then((response) => response.json())
      .then((data) => {
        if (data.features && data.features.length > 0) {
          callback(data.features[0].place_name);
        } else {
          callback(null);
        }
      })
      .catch((error) => {
        console.error("Error reverse geocoding:", error);
        callback(null);
      });
  }

  // Event listener for the radius slider
  radiusSlider.on("input", function () {
    currentRadiusKm = parseFloat($(this).val());
    radiusValueDisplay.text(currentRadiusKm);
    console.log("[Radius Slider] New Radius (km):", currentRadiusKm);

    // Update active state of preset buttons
    updateRadiusPresetButtons(currentRadiusKm);

    const centerForCircle =
      selectedCoords ||
      (map ? map.getCenter().toArray() : mapConfig.cyprusCenter);
    console.log("[Radius Slider] Center for circle:", centerForCircle);

    updateRadiusCircle(centerForCircle, currentRadiusKm);

    console.log("[Radius Slider] Map available?", !!map);
    console.log(
      "[Radius Slider] Turf available?",
      typeof turf !== "undefined" ? !!turf : "Turf IS UNDEFINED"
    );

    if (
      map &&
      typeof turf !== "undefined" &&
      turf &&
      centerForCircle &&
      centerForCircle.length === 2
    ) {
      console.log("[Radius Slider] Proceeding with fitBounds logic.");
      try {
        const turfPoint = turf.point(centerForCircle);
        console.log("[Radius Slider] Turf point:", turfPoint);

        const circlePolygon = turf.circle(turfPoint, currentRadiusKm, {
          steps: 64,
          units: "kilometers",
        });
        console.log("[Radius Slider] Circle Polygon:", circlePolygon);

        if (
          circlePolygon &&
          circlePolygon.geometry &&
          circlePolygon.geometry.coordinates &&
          circlePolygon.geometry.coordinates.length > 0
        ) {
          const circleBbox = turf.bbox(circlePolygon);
          console.log("[Radius Slider] Circle BBox:", circleBbox);

          console.log(
            "[Radius Slider] Calling map.fitBounds with BBox:",
            circleBbox
          );
          map.fitBounds(circleBbox, {
            padding: 40,
            duration: 500,
          });
        } else {
          console.warn(
            "[Radius Slider] Could not generate valid circle polygon for fitBounds. Polygon:",
            circlePolygon
          );
        }
      } catch (e) {
        console.error(
          "[Radius Slider] Error calculating or fitting bounds for radius circle:",
          e
        );
      }
    } else {
      console.warn(
        "[Radius Slider] Skipped fitBounds logic. Conditions not met.",
        {
          map: !!map,
          turf: typeof turf !== "undefined" ? !!turf : "Turf IS UNDEFINED",
          centerForCircle,
          centerLength: centerForCircle ? centerForCircle.length : "N/A",
        }
      );
    }
  });

  // Function to update the active state of radius preset buttons
  function updateRadiusPresetButtons(currentRadius) {
    $(".radius-preset-btn").removeClass("active");
    $(`.radius-preset-btn[data-radius="${currentRadius}"]`).addClass("active");
  }

  // Event listener for radius preset buttons
  $(document).on("click", ".radius-preset-btn", function() {
    const newRadius = parseFloat($(this).data("radius"));
    
    // Update the slider value and display
    radiusSlider.val(newRadius);
    radiusValueDisplay.text(newRadius);
    currentRadiusKm = newRadius;
    
    // Update active state of buttons
    updateRadiusPresetButtons(newRadius);
    
    console.log("[Radius Preset] New Radius (km):", newRadius);

    const centerForCircle =
      selectedCoords ||
      (map ? map.getCenter().toArray() : mapConfig.cyprusCenter);
    console.log("[Radius Preset] Center for circle:", centerForCircle);

    updateRadiusCircle(centerForCircle, newRadius);

    // Apply the same zoom logic as the slider
    if (
      map &&
      typeof turf !== "undefined" &&
      turf &&
      centerForCircle &&
      centerForCircle.length === 2
    ) {
      console.log("[Radius Preset] Proceeding with fitBounds logic.");
      try {
        const turfPoint = turf.point(centerForCircle);
        console.log("[Radius Preset] Turf point:", turfPoint);

        const circlePolygon = turf.circle(turfPoint, newRadius, {
          steps: 64,
          units: "kilometers",
        });
        console.log("[Radius Preset] Circle Polygon:", circlePolygon);

        if (
          circlePolygon &&
          circlePolygon.geometry &&
          circlePolygon.geometry.coordinates &&
          circlePolygon.geometry.coordinates.length > 0
        ) {
          const circleBbox = turf.bbox(circlePolygon);
          console.log("[Radius Preset] Circle BBox:", circleBbox);

          console.log(
            "[Radius Preset] Calling map.fitBounds with BBox:",
            circleBbox
          );
          map.fitBounds(circleBbox, {
            padding: 40,
            duration: 500,
          });
        } else {
          console.warn(
            "[Radius Preset] Could not generate valid circle polygon for fitBounds. Polygon:",
            circlePolygon
          );
        }
      } catch (e) {
        console.error(
          "[Radius Preset] Error calculating or fitting bounds for radius circle:",
          e
        );
      }
    } else {
      console.warn(
        "[Radius Preset] Skipped fitBounds logic. Conditions not met.",
        {
          map: !!map,
          turf: typeof turf !== "undefined" ? !!turf : "Turf IS UNDEFINED",
          centerForCircle,
          centerLength: centerForCircle ? centerForCircle.length : "N/A",
        }
      );
    }
  });

  applyBtn.on("click", function () {
    console.log("[ApplyFilterClicked] Current selectedCoords:", JSON.stringify(selectedCoords));
    console.log("[ApplyFilterClicked] Current currentRadiusKm:", currentRadiusKm);

    if (selectedCoords && selectedCoords.length === 2) {
      const lat = selectedCoords[1];
      const lng = selectedCoords[0];
      const radius = currentRadiusKm;
      const locationName = selectedLocationName || "Selected on map";

      // Update UI
      currentLocationText.text(locationName);
      modal.hide();

      // Clear any selected make, model, variant before fetching new listings
      $("#filter-make").val("").addClass("loading-filter");
      $("#filter-model")
        .val("")
        .prop("disabled", true)
        .html('<option value="">Select Make First</option>')
        .addClass("loading-filter");
      $("#filter-variant")
        .val("")
        .prop("disabled", true)
        .html('<option value="">Select Model First</option>')
        .addClass("loading-filter");

      // Fetch listings with new location
      console.log("[ApplyFilterClicked] Calling fetchFilteredListings with LAT:", lat, "LNG:", lng, "RADIUS:", radius);
      fetchFilteredListings(1, lat, lng, radius);

      // Update URL
      const currentUrl = new URL(window.location.href);
      currentUrl.searchParams.set("lat", lat.toFixed(7));
      currentUrl.searchParams.set("lng", lng.toFixed(7));
      currentUrl.searchParams.set("radius", radius.toString());
      currentUrl.searchParams.set("location_name", locationName);
      currentUrl.searchParams.delete("paged");
      currentUrl.searchParams.delete("make"); // Remove make filter from URL
      currentUrl.searchParams.delete("model");
      currentUrl.searchParams.delete("variant");
      history.pushState({ path: currentUrl.href }, "", currentUrl.href);
      console.log("[ApplyFilter] URL updated to:", currentUrl.href);

      // Save to localStorage
      const preferredLocation = {
        lat: lat,
        lng: lng,
        radius: radius,
        name: locationName,
      };
      localStorage.setItem(
        "autoAgoraUserLocation",
        JSON.stringify(preferredLocation)
      );
      console.log(
        "[ApplyFilter] Location saved to localStorage:",
        preferredLocation
      );
    } else {
      // If no specific coords (e.g., user clears map and applies "All of Cyprus")
      console.log("[ApplyFilterClicked] No valid selectedCoords. Calling fetchFilteredListings with NULLs for location.");
      currentLocationText.text("All of Cyprus");
      modal.hide();

      // Clear any selected make, model, variant before fetching new listings
      $("#filter-make").val("").addClass("loading-filter");
      $("#filter-model")
        .val("")
        .prop("disabled", true)
        .html('<option value="">Select Make First</option>')
        .addClass("loading-filter");
      $("#filter-variant")
        .val("")
        .prop("disabled", true)
        .html('<option value="">Select Model First</option>')
        .addClass("loading-filter");

      // Fetch all listings
      fetchFilteredListings(1, null, null, null);

      // Update URL to remove location and make/model/variant parameters
      const currentUrl = new URL(window.location.href);
      currentUrl.searchParams.delete("lat");
      currentUrl.searchParams.delete("lng");
      currentUrl.searchParams.delete("radius");
      currentUrl.searchParams.delete("location_name");
      currentUrl.searchParams.delete("paged");
      currentUrl.searchParams.delete("make"); // Remove make filter from URL
      currentUrl.searchParams.delete("model");
      currentUrl.searchParams.delete("variant");
      history.pushState({ path: currentUrl.href }, "", currentUrl.href);
      console.log(
        '[ApplyFilter] URL updated for "All of Cyprus":',
        currentUrl.href
      );

      // Clear from localStorage
      localStorage.removeItem("autoAgoraUserLocation");
      console.log("[ApplyFilter] Location cleared from localStorage.");
    }
  });

  function updateUrlWithFilters(page, latInput, lngInput, radiusInput) {
    const currentUrl = new URL(window.location.href);

    // Convert to numbers, ensuring null or undefined become null, and empty strings also lead to null after parseFloat (NaN)
    const lat =
      latInput !== null && latInput !== undefined && latInput !== ""
        ? parseFloat(latInput)
        : null;
    const lng =
      lngInput !== null && lngInput !== undefined && lngInput !== ""
        ? parseFloat(lngInput)
        : null;
    const radius =
      radiusInput !== null && radiusInput !== undefined && radiusInput !== ""
        ? parseFloat(radiusInput)
        : null;

    console.log("[DEBUG] updateUrlWithFilters - Processed inputs:", {
      page,
      lat,
      lng,
      radius,
      origLat: latInput,
      origLng: lngInput,
      origRad: radiusInput,
    });

    // Update or remove location parameters
    // Check for valid numbers (not null and not NaN)
    if (
      lat !== null &&
      !isNaN(lat) &&
      lng !== null &&
      !isNaN(lng) &&
      radius !== null &&
      !isNaN(radius)
    ) {
      currentUrl.searchParams.set("lat", lat.toFixed(7));
      currentUrl.searchParams.set("lng", lng.toFixed(7));
      currentUrl.searchParams.set("radius", radius.toString()); // parseFloat then toString is fine
    } else {
      currentUrl.searchParams.delete("lat");
      currentUrl.searchParams.delete("lng");
      currentUrl.searchParams.delete("radius");
    }

    // Update page number
    if (page && page > 1) {
      currentUrl.searchParams.set("paged", page.toString());
    } else {
      currentUrl.searchParams.delete("paged");
    }

    // Get all current filter values
    const filters = {
      make: $("#filter-make").val(),
      model: $("#filter-model").val(),
      variant: $("#filter-variant").val(),
      year_min: $("#filter-year-min").val(),
      year_max: $("#filter-year-max").val(),
      price_min: $("#filter-price-min").val(),
      price_max: $("#filter-price-max").val(),
      mileage_min: $("#filter-mileage-min").val(),
      mileage_max: $("#filter-mileage-max").val(),
    };

    // Update URL with filter values
    Object.entries(filters).forEach(([key, value]) => {
      if (value && value !== "") {
        currentUrl.searchParams.set(key, value);
      } else {
        currentUrl.searchParams.delete(key);
      }
    });

    // Update URL without reloading the page
    history.pushState({ path: currentUrl.href }, "", currentUrl.href);
    console.log("[UpdateURL] Updated URL with filters:", currentUrl.href);
  }

  function fetchFilteredListings(
    page = 1,
    lat = null,
    lng = null,
    radius = null
  ) {
    // Show loading states
    $("#filter-make").addClass("loading-filter");
    $("#filter-model").addClass("loading-filter");
    $("#filter-variant").addClass("loading-filter");
    $(".car-listings-grid").html(
      '<div class="loading-spinner">Loading listings...</div>'
    );

    // Abort any existing request
    if (currentListingsRequest && currentListingsRequest.readyState !== 4) {
      currentListingsRequest.abort();
    }

    const data = {
      action: "filter_listings_by_location",
      nonce: nonce,
      paged: page,
      filter_lat: lat,
      filter_lng: lng,
      filter_radius: radius,
      per_page: carListingsMapFilterData.perPage || 12,
      get_filter_counts: true,
      get_all_makes: true, // Add this to explicitly request all makes
    };

    // Add any selected filters
    const selectedMake = $("#filter-make").val();
    const selectedModel = $("#filter-model").val();
    const selectedVariant = $("#filter-variant").val();

    if (selectedMake) data.make = selectedMake;
    if (selectedModel) data.model = selectedModel;
    if (selectedVariant) data.variant = selectedVariant;

    currentListingsRequest = $.ajax({
      url: ajaxurl,
      type: "POST",
      data: data,
      success: function (response) {
        console.log("[DEBUG] fetchFilteredListings AJAX Response:", response);
        $("#filter-make").removeClass("loading-filter");
        $("#filter-model").removeClass("loading-filter");
        $("#filter-variant").removeClass("loading-filter");

        if (response.success) {
          console.log(
            "[DEBUG] fetchFilteredListings - response.data:",
            response.data
          );
          $(".car-listings-grid").html(response.data.listings_html);
          $(".car-listings-pagination").html(response.data.pagination_html);

          const globalMakes = response.data.all_makes;
          const allModelsByMake = response.data.all_models_by_make;
          const allVariantsByModel = response.data.all_variants_by_model;
          const filterCounts = response.data.filter_counts;

          const currentSelectedMake = $("#filter-make").val();
          const currentSelectedModel = $("#filter-model").val();
          const currentSelectedVariant = $("#filter-variant").val();

          updateMakeFilter(globalMakes, filterCounts, currentSelectedMake);
          updateModelFilter(
            allModelsByMake,
            filterCounts,
            currentSelectedMake,
            currentSelectedModel
          );
          updateVariantFilter(
            allVariantsByModel,
            filterCounts,
            currentSelectedMake,
            currentSelectedModel,
            currentSelectedVariant
          );

          // Update other spec filter counts (excluding make, model, variant as they are handled above)
          if (filterCounts) {
            let otherFilterCounts = { ...filterCounts };
            delete otherFilterCounts.make;
            delete otherFilterCounts.model_by_make;
            delete otherFilterCounts.variant_by_model;
            if (
              window.AutoAgoraSpecFilters &&
              typeof window.AutoAgoraSpecFilters.updateFilterCounts ===
                "function"
            ) {
              window.AutoAgoraSpecFilters.updateFilterCounts(otherFilterCounts);
            } else {
              console.error(
                "[DEBUG] AutoAgoraSpecFilters.updateFilterCounts is not available."
              );
            }
          }

          // Calculate distances if location is set
          if (lat !== null && lng !== null && radius !== null) {
            calculateAndDisplayDistances(lat, lng);
          }

          // Reinitialize features
          if (typeof reinitializeCarousels === "function") {
            reinitializeCarousels();
          }
          if (typeof reinitializeFavoriteButtons === "function") {
            reinitializeFavoriteButtons();
          }
          if (typeof updateResultsCounter === "function") {
            const totalResults = response.data.query_vars?.found_posts;
            updateResultsCounter(totalResults);
          }

          // Update URL
          updateUrlWithFilters(page, lat, lng, radius);
        } else {
          console.error(
            "[DEBUG] fetchFilteredListings AJAX failed:",
            response.data?.message || "No error message"
          );
          $(".car-listings-grid").html(
            "<p>Error loading listings. " +
              (response.data?.message || "") +
              "</p>"
          );
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        if (textStatus !== "abort") {
          $(".car-listings-grid").html(
            "<p>Error loading listings. Please try again.</p>"
          );
          console.error("AJAX Error:", textStatus, errorThrown);
        }
        $("#filter-make").removeClass("loading-filter");
        $("#filter-model").removeClass("loading-filter");
        $("#filter-variant").removeClass("loading-filter");
      },
    });
  }

  function updateMakeFilter(globalMakes, filterCounts, selectedMake = "") {
    console.log(
      "[DEBUG] updateMakeFilter - STEP 1: Received globalMakes:",
      JSON.parse(JSON.stringify(globalMakes))
    );
    console.log(
      "[DEBUG] updateMakeFilter - STEP 2: Received filterCounts:",
      JSON.parse(JSON.stringify(filterCounts))
    );
    console.log(
      "[DEBUG] updateMakeFilter - STEP 3: Received selectedMake:",
      selectedMake
    );

    const $makeSelect = $("#filter-make");
    if (!$makeSelect.length) {
      console.error(
        "[DEBUG] CRITICAL: #filter-make select element NOT FOUND in DOM!"
      );
      return;
    }

    let optionsHtml = '<option value="">All Makes</option>';

    const makesToDisplayWithCounts = {};
    if (Array.isArray(globalMakes)) {
      globalMakes.forEach((makeName) => {
        makesToDisplayWithCounts[makeName] =
          filterCounts?.make?.[makeName] || 0;
      });
    } else {
      console.warn(
        "[DEBUG] updateMakeFilter: globalMakes is not an array. Attempting to use keys from filterCounts.make if available."
      );
      if (
        filterCounts &&
        typeof filterCounts.make === "object" &&
        filterCounts.make !== null
      ) {
        Object.keys(filterCounts.make).forEach((makeName) => {
          makesToDisplayWithCounts[makeName] = filterCounts.make[makeName] || 0;
        });
      }
    }

    const sortedMakeNames = Object.keys(makesToDisplayWithCounts).sort((a, b) =>
      a.localeCompare(b)
    );

    sortedMakeNames.forEach((makeName) => {
      const count = makesToDisplayWithCounts[makeName];
      const isSelectedAttr = makeName === selectedMake ? "selected" : "";

      if (count > 0 || makeName === selectedMake) {
        optionsHtml += `<option value="${makeName}" ${isSelectedAttr}>${makeName} (${count})</option>`;
      }
    });

    $makeSelect.html(optionsHtml).prop("disabled", false);
  }

  function updateModelFilter(
    allModelsByMake,
    filterCounts,
    currentMake,
    selectedModel = ""
  ) {
    const $modelSelect = $("#filter-model");
    if (!$modelSelect.length) {
      console.error(
        "[DEBUG] CRITICAL: #filter-model select element NOT FOUND in DOM!"
      );
      return;
    }

    if (!currentMake) {
      $modelSelect
        .html('<option value="">Select Make First</option>')
        .prop("disabled", true);
      return;
    }

    let optionsHtml = '<option value="">All Models</option>';
    const modelsForCurrentMake = allModelsByMake?.[currentMake] || [];
    const modelCounts = filterCounts?.model_by_make?.[currentMake] || {};

    if (Array.isArray(modelsForCurrentMake)) {
      modelsForCurrentMake.sort((a, b) => a.localeCompare(b));
      modelsForCurrentMake.forEach((modelName) => {
        const count = modelCounts[modelName] || 0;
        const isSelectedAttr = modelName === selectedModel ? "selected" : "";
        if (count > 0 || modelName === selectedModel) {
          optionsHtml += `<option value="${modelName}" ${isSelectedAttr}>${modelName} (${count})</option>`;
        }
      });
    }

    $modelSelect.html(optionsHtml).prop("disabled", false);
  }

  function updateVariantFilter(
    allVariantsByModel,
    filterCounts,
    currentMake,
    currentModel,
    selectedVariant = ""
  ) {
    const $variantSelect = $("#filter-variant");
    if (!$variantSelect.length) {
      console.error(
        "[DEBUG] CRITICAL: #filter-variant select element NOT FOUND in DOM!"
      );
      return;
    }

    if (!currentMake || !currentModel) {
      $variantSelect
        .html('<option value="">Select Model First</option>')
        .prop("disabled", true);
      return;
    }

    let optionsHtml = '<option value="">All Variants</option>';
    const variantsForCurrentModel =
      allVariantsByModel?.[currentMake]?.[currentModel] || [];
    const variantCounts =
      filterCounts?.variant_by_model?.[currentMake]?.[currentModel] || {};

    if (Array.isArray(variantsForCurrentModel)) {
      variantsForCurrentModel.sort((a, b) => a.localeCompare(b));
      variantsForCurrentModel.forEach((variantName) => {
        const count = variantCounts[variantName] || 0;
        const isSelectedAttr =
          variantName === selectedVariant ? "selected" : "";
        if (count > 0 || variantName === selectedVariant) {
          optionsHtml += `<option value="${variantName}" ${isSelectedAttr}>${variantName} (${count})</option>`;
        }
      });
    }
    $variantSelect.html(optionsHtml).prop("disabled", false);
  }

  // Add event listener for make filter changes
  $("#filter-make").on("change", function () {
    const selectedMake = $(this).val();
    console.log("[MakeFilter] Selected make:", selectedMake);

    // Clear model and variant selections
    $("#filter-model").val("").trigger("change.internal");
    $("#filter-variant").val("");

    // Update URL to remove model and variant if make is changed/cleared
    const urlParams = new URLSearchParams(window.location.search);
    const lat = urlParams.get("lat");
    const lng = urlParams.get("lng");
    const radius = urlParams.get("radius");

    // Call fetchFilteredListings, which will handle repopulating all dropdowns in its success callback
    fetchFilteredListings(1, lat, lng, radius);
  });

  // Add event listener for model filter changes
  $("#filter-model").on("change", function (event, isInternalCall) {
    if (isInternalCall) return;

    const selectedModel = $(this).val();
    console.log("[ModelFilter] Selected model:", selectedModel);

    $("#filter-variant").val("");

    const urlParams = new URLSearchParams(window.location.search);
    const lat = urlParams.get("lat");
    const lng = urlParams.get("lng");
    const radius = urlParams.get("radius");

    fetchFilteredListings(1, lat, lng, radius);
  });

  // Add event listener for variant filter changes
  $("#filter-variant").on("change", function () {
    const selectedVariant = $(this).val();
    console.log("[VariantFilter] Selected variant:", selectedVariant);

    const urlParams = new URLSearchParams(window.location.search);
    const lat = urlParams.get("lat");
    const lng = urlParams.get("lng");
    const radius = urlParams.get("radius");

    fetchFilteredListings(1, lat, lng, radius);
  });

  function calculateAndDisplayDistances(centerLat, centerLng) {
    // Ensure centerLat and centerLng are valid numbers
    const cLat = parseFloat(centerLat);
    const cLng = parseFloat(centerLng);
    const locationFilterActive = !isNaN(cLat) && !isNaN(cLng);

    if (locationFilterActive) {
      // console.log('[DEBUG] calculateAndDisplayDistances: Location filter active.', {centerLat, centerLng});
    } else {
      // console.log('[DEBUG] calculateAndDisplayDistances: No location filter active or invalid coords.', {centerLat, centerLng});
    }

    $(".car-listings-grid .car-listing-card").each(function () {
      const $card = $(this);
      const cardCity = $card.data("city");
      const cardDistrict = $card.data("district");
      const cardLatData = $card.data("latitude"); // Already being fetched by PHP
      const cardLngData = $card.data("longitude"); // Already being fetched by PHP

      const $locationEl = $card.find(".car-location");
      const $locationTextSpan = $locationEl.find("span.location-text");

      let baseLocationText = "";
      if (cardCity && cardDistrict) {
        baseLocationText = cardCity + " - " + cardDistrict;
      } else if (cardCity) {
        baseLocationText = cardCity;
      } else if (cardDistrict) {
        baseLocationText = cardDistrict;
      } else {
        baseLocationText = "Location not specified"; // Fallback
      }

      // Ensure cardLatData and cardLngData are not undefined or null before parseFloat
      if (
        cardLatData === undefined ||
        cardLatData === null ||
        cardLngData === undefined ||
        cardLngData === null
      ) {
        // console.warn('[DEBUG] calculateAndDisplayDistances: Card missing latitude or longitude data attribute.', { postId: $card.data('post-id') });
        if ($locationTextSpan.length) {
          $locationTextSpan.text(baseLocationText);
        }
        return; // Skip this card if data attributes are missing for distance calculation
      }

      const cardLat = parseFloat(cardLatData);
      const cardLng = parseFloat(cardLngData);

      if ($locationTextSpan.length) {
        if (locationFilterActive && !isNaN(cardLat) && !isNaN(cardLng)) {
          const pinLocation = turf.point([cLng, cLat]); // Pin from map filter
          const carLocationPoint = turf.point([cardLng, cardLat]); // Car's location
          const distance = turf.distance(pinLocation, carLocationPoint, {
            units: "kilometers",
          });
          const distanceText = ` (${distance.toFixed(1)} km away)`;
          $locationTextSpan.text(baseLocationText + distanceText);
        } else {
          // If no location filter or card coordinates are invalid, just show city - district
          $locationTextSpan.text(baseLocationText);
        }
      } else {
        // console.warn('[DEBUG] calculateAndDisplayDistances: Location text span not found for card.', { postId: $card.data('post-id') });
      }
    });
  }

  // --- Popup Handling for Spec Filters ---
  openSpecFiltersBtn.on("click", function () {
    specFiltersPopup.show();
  });

  closeSpecFiltersBtn.on("click", function () {
    specFiltersPopup.hide();
  });

  specFiltersPopup.on("click", function (e) {
    if ($(e.target).is(specFiltersPopup)) {
      specFiltersPopup.hide();
    }
  });

  // --- Apply Spec Filters Button ---
  applySpecFiltersBtn.on("click", function () {
    console.log("[ApplySpecFilters] Apply button clicked.");
    // Always use the current filter state (either URL params or defaults)
    const urlParams = new URLSearchParams(window.location.search);
    const lat = urlParams.get("lat") || initialFilter.lat;
    const lng = urlParams.get("lng") || initialFilter.lng;
    const radius = urlParams.get("radius") || initialFilter.radius;

    fetchFilteredListings(1, lat, lng, radius); // Page 1, with current location and newly applied spec filters
    specFiltersPopup.hide();
    // updateActiveFiltersDisplay(); // Call function to update the display of active filters
  });

  // --- Reset Spec Filters Button ---
  resetSpecFiltersBtn.on("click", function () {
    console.log("[ResetSpecFilters] Reset button clicked.");
    // Clear all filter inputs within the specFiltersContainer
    specFiltersContainer.find("select").val("");
    specFiltersContainer.find('input[type="text"]').val("");
    specFiltersContainer.find('input[type="checkbox"]').prop("checked", false);

    // Special handling for model dropdown (disable and reset)
    $("#filter-model")
      .prop("disabled", true)
      .html('<option value="">Select Make First</option>');

    // After resetting, fetch listings. Pass current location filters.
    const urlParams = new URLSearchParams(window.location.search);
    const lat = urlParams.get("lat") || initialFilter.lat;
    const lng = urlParams.get("lng") || initialFilter.lng;
    const radius = urlParams.get("radius") || initialFilter.radius;

    fetchFilteredListings(1, lat, lng, radius);
    specFiltersPopup.hide(); // Optionally hide popup after reset
    // updateActiveFiltersDisplay(); // Update display of active filters (should be empty now)
  });

  // Initial fetch on page load, respecting URL and localStorage
  const urlParamsGlobal = new URLSearchParams(window.location.search);
  const pageToFetch = urlParamsGlobal.get("paged") || 1;
  
  // Since we now always have location data (either custom or Cyprus defaults), use it
  let initialLoadLat = initialFilter.lat;
  let initialLoadLng = initialFilter.lng;
  let initialLoadRadius = initialFilter.radius;

  // Initialize makes, models, variants filters
  $("#filter-make").addClass("loading-filter");
  $("#filter-model")
    .addClass("loading-filter")
    .prop("disabled", true)
    .html('<option value="">Select Make First</option>');
  $("#filter-variant")
    .addClass("loading-filter")
    .prop("disabled", true)
    .html('<option value="">Select Model First</option>');

  console.log("[PageLoad] Using location filter:", {
    lat: initialLoadLat,
    lng: initialLoadLng,
    radius: initialLoadRadius,
    text: selectedLocationName
  });

  // Fetch initial makes, models, variants data with location filter
  $.ajax({
    url: ajaxurl,
    type: "POST",
    data: {
      action: "filter_listings_by_location",
      nonce: nonce,
      paged: 1,
      per_page: 1,
      get_all_makes: true,
      get_filter_counts: true,
      make: urlParamsGlobal.get("make") || "",
      model: urlParamsGlobal.get("model") || "",
      variant: urlParamsGlobal.get("variant") || "",
      filter_lat: initialLoadLat,
      filter_lng: initialLoadLng,
      filter_radius: initialLoadRadius,
    },
    success: function (response) {
      if (response.success) {
        console.log("[DEBUG] Initial AJAX - response.data:", response.data);
        const allMakesList = response.data.all_makes;
        const allModelsByMake = response.data.all_models_by_make;
        const allVariantsByModel = response.data.all_variants_by_model;
        const filterCountsInitial = response.data.filter_counts;

        const initialSelectedMake = urlParamsGlobal.get("make") || "";
        const initialSelectedModel = urlParamsGlobal.get("model") || "";
        const initialSelectedVariant = urlParamsGlobal.get("variant") || "";

        updateMakeFilter(
          allMakesList,
          filterCountsInitial,
          initialSelectedMake
        );
        updateModelFilter(
          allModelsByMake,
          filterCountsInitial,
          initialSelectedMake,
          initialSelectedModel
        );
        updateVariantFilter(
          allVariantsByModel,
          filterCountsInitial,
          initialSelectedMake,
          initialSelectedModel,
          initialSelectedVariant
        );

        if (filterCountsInitial) {
          let otherFilterCounts = { ...filterCountsInitial };
          delete otherFilterCounts.make;
          delete otherFilterCounts.model_by_make;
          delete otherFilterCounts.variant_by_model;
          if (
            window.AutoAgoraSpecFilters &&
            typeof window.AutoAgoraSpecFilters.updateFilterCounts === "function"
          ) {
            window.AutoAgoraSpecFilters.updateFilterCounts(otherFilterCounts);
          } else {
            console.error(
              "[DEBUG] AutoAgoraSpecFilters.updateFilterCounts is not available for initial load."
            );
          }
        }
      } else {
        console.error(
          "[DEBUG] Initial AJAX for options failed:",
          response.data?.message || "No error message"
        );
      }
      $("#filter-make").removeClass("loading-filter");
      $("#filter-model").removeClass("loading-filter");
      $("#filter-variant").removeClass("loading-filter");
    },
    error: function (jqXHR, textStatus, errorThrown) {
      console.error(
        "[DEBUG] Initial AJAX for options Error:",
        textStatus,
        errorThrown
      );
      $("#filter-make").removeClass("loading-filter");
      $("#filter-model").removeClass("loading-filter");
      $("#filter-variant").removeClass("loading-filter");
    },
  });

  // Fetch listings with location and any spec filters from URL
  fetchFilteredListings(
    pageToFetch,
    initialLoadLat,
    initialLoadLng,
    initialLoadRadius
  );

  $("body").on(
    "click",
    ".car-listings-pagination a.page-numbers",
    function (e) {
      e.preventDefault();
      const href = $(this).attr("href");
      const urlParams = new URLSearchParams(href.split("?")[1]);
      const page = parseInt(urlParams.get("paged")) || 1;
      
      // Always pass current location for pagination
      const currentLat = urlParams.get("lat") || initialFilter.lat;
      const currentLng = urlParams.get("lng") || initialFilter.lng;
      const currentRadius = urlParams.get("radius") || initialFilter.radius;
      
      fetchFilteredListings(page, currentLat, currentLng, currentRadius);
    }
  );
});
