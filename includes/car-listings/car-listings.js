/** Global functions for re-initialization - moved outside DOMContentLoaded */
function initializeCarousels() {
  document.querySelectorAll('.car-listing-image-carousel').forEach(carousel => {
    const images = carousel.querySelectorAll('.car-listing-image');
    const prevBtn = carousel.querySelector('.carousel-nav.prev');
    const nextBtn = carousel.querySelector('.carousel-nav.next');
    const seeAllImagesBtn = carousel.querySelector('.see-all-images');
    let currentIndex = 0;

    // Add image counter element if it doesn't exist
    let counter = carousel.querySelector('.image-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.className = 'image-counter';
        carousel.appendChild(counter);
    }
    counter.textContent = images.length > 0 ? `1/${images.length}` : '0/0';

    const updateImages = () => {
      if (images.length === 0) {
          prevBtn.style.display = 'none';
          nextBtn.style.display = 'none';
          if(seeAllImagesBtn) seeAllImagesBtn.style.display = 'none';
          counter.textContent = '0/0';
          return;
      }
      images.forEach((img, index) => {
        img.classList.toggle('active', index === currentIndex);
      });
      prevBtn.style.display = currentIndex === 0 ? 'none' : 'flex';
      nextBtn.style.display = currentIndex === images.length - 1 ? 'none' : 'flex';
      if (seeAllImagesBtn) {
        seeAllImagesBtn.style.display = currentIndex === images.length - 1 ? 'block' : 'none';
      }
      counter.textContent = `${currentIndex + 1}/${images.length}`;
    };

    updateImages();

    prevBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (currentIndex > 0) {
        currentIndex--;
        updateImages();
      }
    });

    nextBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (currentIndex < images.length - 1) {
        currentIndex++;
        updateImages();
      }
    });
  });
}

function reinitializeCarousels() {
  initializeCarousels();
}

function reinitializeFavoriteButtons() {
  const buttons = document.querySelectorAll(".favorite-btn");
  buttons.forEach((button) => {
    const newButton = button.cloneNode(true);
    button.parentNode.replaceChild(newButton, button);
    newButton.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (typeof carListingsData === 'undefined' || typeof carListingsData.ajaxurl === 'undefined' || typeof carListingsData.nonce === 'undefined') {
        alert('Please log in to add favorites.');
        return;
      }
      const carId = this.getAttribute("data-car-id");
      const isActive = this.classList.contains("active");
      const heartIcon = this.querySelector("i");
      this.classList.toggle("active");
      if (isActive) {
        heartIcon.classList.remove("fas");
        heartIcon.classList.add("far");
      } else {
        heartIcon.classList.remove("far");
        heartIcon.classList.add("fas");
      }
      const formData = new FormData();
      formData.append("action", "toggle_favorite_car");
      formData.append("car_id", carId);
      formData.append("is_favorite", !isActive ? "1" : "0");
      formData.append("nonce", carListingsData.nonce);
      fetch(carListingsData.ajaxurl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error('Network response was not ok.');
          }
          return response.json();
        })
        .then((data) => {
          if (!data.success) {
            this.classList.toggle("active");
            if (isActive) {
              heartIcon.classList.remove("far");
              heartIcon.classList.add("fas");
            } else {
              heartIcon.classList.remove("fas");
              heartIcon.classList.add("far");
            }
            console.error("Favorite toggle failed:", data);
            alert("Failed to update favorites. Please try again.");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          this.classList.toggle("active");
          if (isActive) {
            heartIcon.classList.remove("far");
            heartIcon.classList.add("fas");
          } else {
            heartIcon.classList.remove("fas");
            heartIcon.classList.add("far");
          }
          alert("Failed to update favorites. Please try again.");
        });
    });
  });
}

function updateResultsCounter(totalResults) {
  let resultsCounter = document.querySelector('.results-counter');
  if (!resultsCounter) {
    resultsCounter = document.createElement('div');
    resultsCounter.className = 'results-counter';
    const activeFiltersBar = document.querySelector('.active-filters-bar');
    if (activeFiltersBar) {
      activeFiltersBar.parentNode.insertBefore(resultsCounter, activeFiltersBar.nextSibling);
    } else {
      const listingsGrid = document.querySelector('.car-listings-grid');
      if (listingsGrid) {
        listingsGrid.parentNode.insertBefore(resultsCounter, listingsGrid);
      }
    }
  }
  const carCards = document.querySelectorAll('.car-listing-card');
  const actualCount = carCards.length;
  resultsCounter.innerHTML = `Showing <span class="count">${actualCount}</span> results`;
  if (totalResults !== undefined && actualCount !== totalResults) {
      // This case might happen if pagination from server says X total, but current page shows Y
      // console.warn(`Actual count ${actualCount} differs from server total ${totalResults}`);
      // You might want to display totalResults if it makes more sense for overall filtering context
      // resultsCounter.innerHTML = `Showing <span class="count">${actualCount}</span> of ${totalResults} results`;
  }
}

document.addEventListener("DOMContentLoaded", function () {
  console.log('Car Listings JS file loaded successfully from:', window.location.href);

  // --- Access Localized Data ---
  // Ensure carListingsData is available (passed via wp_localize_script)
  if (typeof carListingsData === "undefined") {
    console.error("Car Listings script data not found.");
    return;
  }

  const ajaxurl = carListingsData.ajaxurl;
  const favoriteNonce = carListingsData.nonce;

  // --- Data from PHP (now localized) ---
  const originalCarListings = carListingsData.all_cars || [];
  const exteriorColorCounts = carListingsData.exterior_color_counts || {};
  const interiorColorCounts = carListingsData.interior_color_counts || {};
  const fuelTypeCounts = carListingsData.fuel_type_counts || {};
  const bodyTypeCounts = carListingsData.body_type_counts || {};
  const driveTypeCounts = carListingsData.drive_type_counts || {};
  const modelCounts = carListingsData.model_counts || {};
  const variantCounts = carListingsData.variant_counts || {};
  const yearCounts = carListingsData.year_counts || {};
  const priceCounts = carListingsData.price_counts || {};
  const kmCounts = carListingsData.km_counts || {};
  const engineCounts = carListingsData.engine_counts || {};
  const carData = carListingsData.variants_by_make_model || {};

  // --- Helper Functions ---
  function filterCars(cars, filters) {
    return cars.filter(car => {
      // Check each filter
      for (const [key, value] of Object.entries(filters)) {
        if (!value) continue; // Skip empty filters

        // Handle array values (for checkboxes)
        if (Array.isArray(value)) {
          if (value.length > 0 && !value.includes(car[key])) {
            return false;
          }
        }
        // Handle range filters (min/max)
        else if (key.endsWith('_min') || key.endsWith('_max')) {
          const baseKey = key.replace(/_min$|_max$/, '');
          const carValue = parseFloat(car[baseKey]);
          const filterValue = parseFloat(value);

          if (key.endsWith('_min') && carValue < filterValue) {
            return false;
          }
          if (key.endsWith('_max') && carValue > filterValue) {
            return false;
          }
        }
        // Handle regular filters
        else if (car[key] !== value) {
          return false;
        }
      }
      return true;
    });
  }

  // --- DOM Elements ---
  const filtersButton = document.querySelector(".filters-button");
  const filtersPopup = document.getElementById("filtersPopup");
  const closeFilters = document.querySelector(".close-filters");
  const filterForm = document.getElementById("car-filter-form-listings_page");

  // Check if essential elements exist
  if (!filtersButton || !filtersPopup || !closeFilters || !filterForm) {
    // Don't throw error, just exit if filter elements aren't on the page
    // console.warn('Car Listings filter elements not found.');
    return;
  }

  // --- Popup functionality ---
  filtersButton.addEventListener("click", function () {
    filtersPopup.style.display = "block";
    document.body.style.overflow = "hidden";
  });

  closeFilters.addEventListener("click", function () {
    filtersPopup.style.display = "none";
    document.body.style.overflow = "";
  });

  filtersPopup.addEventListener("click", function (e) {
    if (e.target === filtersPopup) {
      filtersPopup.style.display = "none";
      document.body.style.overflow = "";
    }
  });

  // --- NEW: Core function to sync form state from URL and update dependent counts ---
  function syncFormAndPopulateCountsFromUrl(urlString) {
    if (!filterForm) return;
    const params = new URLSearchParams(urlString);

    // Sync Make, Model, Variant
    const makeSelect = filterForm.querySelector('select[name="make"]');
    const modelSelect = filterForm.querySelector('select[name="model"]');
    const variantSelect = filterForm.querySelector('select[name="variant"]');

    const urlMake = params.get("make");
    const urlModel = params.get("model");
    const urlVariant = params.get("variant");

    if (makeSelect) {
      makeSelect.value = urlMake || "";
      if (typeof populateModels === "function") populateModels();
    }
    if (modelSelect) {
      if (
        urlModel &&
        Array.from(modelSelect.options).some((opt) => opt.value === urlModel)
      ) {
        modelSelect.value = urlModel;
      } else if (!urlMake) {
        modelSelect.value = "";
      }
      if (typeof populateVariants === "function") populateVariants();
    }
    if (variantSelect) {
      if (
        urlVariant &&
        Array.from(variantSelect.options).some(
          (opt) => opt.value === urlVariant
        )
      ) {
        variantSelect.value = urlVariant;
      } else if (!urlModel) {
        variantSelect.value = "";
      }
    }

    // --- Sync Multi-Select Filters (like Transmission, Fuel Type, etc.) ---
    filterForm
      .querySelectorAll("div.multi-select-filter")
      .forEach((msFilter) => {
        const filterKey = msFilter.getAttribute("data-filter-key");
        const hiddenInput = msFilter.querySelector("input.multi-select-value");
        const displayElement = msFilter.querySelector(".multi-select-display");
        let displaySpan = null;
        let defaultDisplayText = "Select Options";

        if (displayElement) {
          displaySpan = displayElement.querySelector("span:first-child");
          defaultDisplayText =
            displayElement.getAttribute("data-default-text") ||
            defaultDisplayText;
        }

        if (filterKey && hiddenInput) {
          const urlQueryValue = params.get(filterKey); // e.g., "Automatic,Manual" or "Petrol"
          const activeValues = urlQueryValue ? urlQueryValue.split(",") : [];

          hiddenInput.value = urlQueryValue || ""; // Update hidden input based on URL

          const selectedLabels = [];
          msFilter.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
            cb.checked = activeValues.includes(cb.value);
            if (cb.checked) {
              const label = cb.getAttribute("data-label") || cb.value;
              selectedLabels.push(label);
            }
          });

          // Update display text for the multi-select dropdown
          if (displaySpan) {
            if (selectedLabels.length === 0) {
              displaySpan.textContent = defaultDisplayText;
            } else if (selectedLabels.length <= 2) {
              displaySpan.textContent = selectedLabels.join(", ");
            } else {
              displaySpan.textContent = selectedLabels.length + " selected";
            }
          }
        }
      });

    // Sync other Selects (Location, Range Filters)
    ["price", "year", "mileage", "engine_capacity", "location"].forEach(
      (field) => {
        const isRange =
          field !== "location" &&
          (field === "price" ||
            field === "year" ||
            field === "mileage" ||
            field === "engine_capacity");

        if (isRange) {
          const minParam = field + "_min";
          const maxParam = field + "_max";
          const minSelect = filterForm.querySelector(
            `select[name="${minParam}"]`
          );
          const maxSelect = filterForm.querySelector(
            `select[name="${maxParam}"]`
          );
          if (minSelect) minSelect.value = params.get(minParam) || "";
          if (maxSelect) maxSelect.value = params.get(maxParam) || "";
        } else {
          // Single select like location
          const select = filterForm.querySelector(`select[name="${field}"]`);
          if (select) select.value = params.get(field) || "";
        }
      }
    );

    // After form is synced from URL, update the counts and enabled/disabled states
    // of options within the filter popup.
    if (typeof updateAllFilterDisplays === "function") {
      updateAllFilterDisplays();
    }
  }

  // --- NEW: Helper function to get current filters from the form ---
  function getFiltersFromForm() {
    const formData = new FormData(filterForm);
    const filters = {};
    for (const [key, value] of formData.entries()) {
      if (value) {
        const cleanKey = key.replace(/\[\]$/, "");
        if (key.endsWith("[]")) {
          if (!filters[cleanKey]) {
            filters[cleanKey] = [];
          }
          if (!filters[cleanKey].includes(value)) {
            filters[cleanKey].push(value);
          }
        } else {
          filters[key] = value;
        }
      }
    }

    // Also include lat, lng, radius, location_name from current URL if present
    const currentUrlParams = new URLSearchParams(window.location.search);
    const locationFilterKeys = ['lat', 'lng', 'radius', 'location_name'];
    locationFilterKeys.forEach(key => {
        if (currentUrlParams.has(key)) {
            filters[key] = currentUrlParams.get(key);
        }
    });

    return filters;
  }

  function updateAllFilterDisplays() {
    const activeFilters = getFiltersFromForm();

    // Update simple selects (Make, Location - others are dependent or range/checkbox)
    updateSelectDisplay("make", activeFilters);
    updateSelectDisplay("location", activeFilters); // Assuming location is simple

    // Update dependent selects (Model, Variant)
    updateModelDisplay(activeFilters);
    updateVariantDisplay(activeFilters);

    // Update checkbox groups
    [
      "fuel_type",
      "body_type",
      "drive_type",
      "exterior_color",
      "interior_color",
    ].forEach((fieldName) => {
      updateCheckboxGroupDisplay(fieldName, activeFilters);
    });

    // Update range selects
    ["price", "year", "mileage", "engine_capacity"].forEach((fieldName) => {
      updateRangeSelectDisplay(fieldName, activeFilters);
    });
  }

  // --- NEW: Update simple select dropdown display ---
  function updateSelectDisplay(fieldName, activeFilters) {
    const selectElement = filterForm.querySelector(
      `select[name="${fieldName}"]`
    );
    if (!selectElement) return;

    const filtersWithoutThis = { ...activeFilters };
    delete filtersWithoutThis[fieldName];
    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutThis
    );

    const valueCounts = {};
    carsMatchingOthers.forEach((car) => {
      const value = car[fieldName];
      if (value) {
        // Ensure car has a value for this field
        valueCounts[value] = (valueCounts[value] || 0) + 1;
      }
    });

    updateOptionsInSelect(selectElement, valueCounts);
  }

  // --- NEW: Update Model select display ---
  function updateModelDisplay(activeFilters) {
    const selectElement = filterForm.querySelector(`select[name="model"]`);
    if (!selectElement) return;
    const currentMake = activeFilters["make"];

    // If no make is selected, clear and disable
    if (!currentMake) {
      selectElement.innerHTML = '<option value="">All Models</option>';
      selectElement.disabled = true;
      return;
    }

    // Filter based on everything EXCEPT model, but INCLUDING make
    const filtersWithoutModel = { ...activeFilters };
    delete filtersWithoutModel["model"];
    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutModel
    );

    const valueCounts = {};
    carsMatchingOthers.forEach((car) => {
      // Count only models of the currently selected make
      if (car.make === currentMake && car.model) {
        valueCounts[car.model] = (valueCounts[car.model] || 0) + 1;
      }
    });

    updateOptionsInSelect(selectElement, valueCounts, "All Models");
    selectElement.disabled = false; // Enable since a make is selected
  }

  // --- NEW: Update Variant select display ---
  function updateVariantDisplay(activeFilters) {
    const selectElement = filterForm.querySelector(`select[name="variant"]`);
    if (!selectElement) return;
    const currentMake = activeFilters["make"];
    const currentModel = activeFilters["model"];

    // If no make or model is selected, clear and disable
    if (!currentMake || !currentModel) {
      selectElement.innerHTML = '<option value="">All Variants</option>';
      selectElement.disabled = true;
      return;
    }

    // Filter based on everything EXCEPT variant, but INCLUDING make and model
    const filtersWithoutVariant = { ...activeFilters };
    delete filtersWithoutVariant["variant"];
    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutVariant
    );

    const valueCounts = {};
    carsMatchingOthers.forEach((car) => {
      // Count only variants of the currently selected make and model
      if (
        car.make === currentMake &&
        car.model === currentModel &&
        car.variant
      ) {
        valueCounts[car.variant] = (valueCounts[car.variant] || 0) + 1;
      }
    });

    updateOptionsInSelect(selectElement, valueCounts, "All Variants");
    selectElement.disabled = false; // Enable since make and model are selected
  }

  // --- NEW: Generic function to update options within any select element ---
  // (Replaces the complex updateSelectWithOptions)
  function updateOptionsInSelect(
    selectElement,
    valueCounts,
    placeholder = "Any"
  ) {
    const currentValue = selectElement.value; // Preserve current selection

    // Get all existing option values from the DOM before clearing (for base text)
    const existingOptions = {};
    selectElement.querySelectorAll("option").forEach((opt) => {
      if (opt.value !== "") {
        existingOptions[opt.value] = opt.textContent.split(" (")[0]; // Store base text
      }
    });

    selectElement.innerHTML = `<option value="">${placeholder}</option>`; // Clear and add placeholder

    // Get all unique values that have a count > 0 OR were originally in the select
    const allPossibleValues = new Set([
      ...Object.keys(valueCounts),
      ...Object.keys(existingOptions),
    ]);

    // Sort values alphabetically (optional)
    const sortedValues = [...allPossibleValues].sort((a, b) =>
      a.localeCompare(b)
    );

    sortedValues.forEach((value) => {
      const count = valueCounts[value] || 0;
      const baseText = existingOptions[value] || value; // Use original text or value itself
      const option = document.createElement("option");
      option.value = value;
      option.textContent = `${baseText} (${count})`;
      option.disabled = count === 0;
      selectElement.appendChild(option);
    });

    // Restore selection if possible
    if (selectElement.querySelector(`option[value="${currentValue}"]`)) {
      selectElement.value = currentValue;
    }

    // Update styles for disabled options (can be done via CSS)
    selectElement.querySelectorAll("option").forEach((opt) => {
      opt.style.opacity = opt.disabled ? "0.5" : "1";
      opt.style.cursor = opt.disabled ? "not-allowed" : "pointer";
    });
  }

  // --- NEW: Update checkbox group display ---
  function updateCheckboxGroupDisplay(fieldName, activeFilters) {
    const checkboxGroup = filterForm.querySelector(
      `div.checkbox-group[data-field="${fieldName}"]`
    );
    if (!checkboxGroup) return;

    const filtersWithoutThis = { ...activeFilters };
    // Handle array key format (e.g., 'fuel_type' corresponds to form name 'fuel_type[]')
    if (filtersWithoutThis[fieldName]) {
      delete filtersWithoutThis[fieldName];
    }

    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutThis
    );

    const valueCounts = {};
    carsMatchingOthers.forEach((car) => {
      const value = car[fieldName];
      if (value) {
        valueCounts[value] = (valueCounts[value] || 0) + 1;
      }
    });

    checkboxGroup.querySelectorAll(".checkbox-label").forEach((label) => {
      const checkbox = label.querySelector('input[type="checkbox"]');
      const value = checkbox.value;
      const count = valueCounts[value] || 0;
      const baseText =
        label.textContent.trim().split(" (")[0].replace(value, "").trim() ||
        value;
      // Find the text node to update safely
      let textNode = null;
      for (const node of label.childNodes) {
        if (
          node.nodeType === Node.TEXT_NODE &&
          node.textContent.trim() !== ""
        ) {
          textNode = node;
          break;
        }
      }
      if (textNode) {
        textNode.textContent = ` ${baseText} (${count})`;
      } else {
        // Fallback if text node wasn't found easily (might need adjustment)
        label.appendChild(document.createTextNode(` ${baseText} (${count})`));
      }

      checkbox.disabled = count === 0;
      label.style.opacity = checkbox.disabled ? "0.5" : "1";
      label.style.cursor = checkbox.disabled ? "not-allowed" : "pointer";
    });

    // Update the dropdown button label
    const button = checkboxGroup
      .closest(".checkbox-dropdown")
      .querySelector(".checkbox-dropdown-button");
    const selectedCount = checkboxGroup.querySelectorAll(
      'input[type="checkbox"]:checked:not(:disabled)'
    ).length;
    let labelText = "Select Options"; // Default
    const labelMap = {
      fuel_type: "Fuel Type",
      body_type: "Body Type",
      drive_type: "Drive Type",
      exterior_color: "Exterior Color",
      interior_color: "Interior Color",
    };
    labelText = labelMap[fieldName] || labelText;
    if (selectedCount > 0) {
      button.textContent = `${labelText}: ${selectedCount} selected`;
    } else {
      button.textContent = `Select ${labelText}`; // Placeholder text
    }
  }

  // --- NEW: Update range select display ---
  function updateRangeSelectDisplay(fieldName, activeFilters) {
    const minSelect = filterForm.querySelector(
      `select[name="${fieldName}_min"]`
    );
    const maxSelect = filterForm.querySelector(
      `select[name="${fieldName}_max"]`
    );
    if (!minSelect || !maxSelect) return;

    const minKey = fieldName + "_min";
    const maxKey = fieldName + "_max";

    const filtersWithoutThis = { ...activeFilters };
    delete filtersWithoutThis[minKey];
    delete filtersWithoutThis[maxKey];
    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutThis
    );

    // Update Min Select
    const minCounts = {};
    carsMatchingOthers.forEach((car) => {
      const carValue = parseFloat(car[fieldName]);
      if (isNaN(carValue)) return;
      // Also check against the current max filter if set
      const currentMax = activeFilters[maxKey]
        ? parseFloat(activeFilters[maxKey])
        : Infinity;
      if (carValue <= currentMax) {
        minSelect.querySelectorAll("option").forEach((opt) => {
          if (opt.value === "") return;
          const optValue = parseFloat(opt.value);
          if (carValue >= optValue) {
            // Car value is >= this min option
            minCounts[opt.value] = (minCounts[opt.value] || 0) + 1;
          }
        });
      }
    });
    updateOptionsInSelect(minSelect, minCounts, "Any");

    // Update Max Select
    const maxCounts = {};
    carsMatchingOthers.forEach((car) => {
      const carValue = parseFloat(car[fieldName]);
      if (isNaN(carValue)) return;
      // Also check against the current min filter if set
      const currentMin = activeFilters[minKey]
        ? parseFloat(activeFilters[minKey])
        : -Infinity;
      if (carValue >= currentMin) {
        maxSelect.querySelectorAll("option").forEach((opt) => {
          if (opt.value === "") return;
          const optValue = parseFloat(opt.value);
          if (carValue <= optValue) {
            // Car value is <= this max option
            maxCounts[opt.value] = (maxCounts[opt.value] || 0) + 1;
          }
        });
      }
    });
    updateOptionsInSelect(maxSelect, maxCounts, "Any");
  }

  // --- Event Listeners and Initialization ---
  document.querySelectorAll(".checkbox-group").forEach((group) => {
    const input = group.querySelector('input[type="checkbox"]');
    if (input && input.name) {
      const fieldName = input.name.replace(/\[\]$/, "");
      group.setAttribute("data-field", fieldName);
    }
  });

  function updateActiveFiltersDisplay() {
    const activeFiltersContainer = document.querySelector(
      ".active-filters-container"
    );
    if (!activeFiltersContainer) return;

    const params = new URLSearchParams(window.location.search); // Always read from current URL
    activeFiltersContainer.innerHTML = "";
    const processedValues = new Set();

    params.forEach((value, key) => {
      if (value && key !== "paged" && key !== "lat" && key !== "lng" && key !== "radius" && key !== "location_name") {
        const baseKey = key.replace(/\\\[\\\]$/, "");
        const label = formatFilterLabel(baseKey);

        if (key.endsWith("[]")) {
          const values = params.getAll(key);
          values.forEach((val) => {
            if (val) {
              const uniqueId = `${key}-${val}`;
              if (!processedValues.has(uniqueId)) {
                processedValues.add(uniqueId);
                const chip = createFilterChip(label, val, key, val);
                activeFiltersContainer.appendChild(chip);
              }
            }
          });
        } else if (!processedValues.has(key)) {
          processedValues.add(key);
          const chip = createFilterChip(label, value, key);
          activeFiltersContainer.appendChild(chip);
        }
      }
    });

    activeFiltersContainer
      .querySelectorAll(".remove-filter")
      .forEach((button) => {
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);

        newButton.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();

          if (!filterForm) {
            console.error(
              "Car Listings: Filter form not found. Cannot clear filter."
            );
            return;
          }

          const filterKeyToRemove = this.dataset.key;
          const valueToRemove = this.dataset.value; // For multi-value (checkboxes)

          if (!filterKeyToRemove) {
            console.warn(
              "Car Listings: Filter key not found on remove button."
            );
            return;
          }

          const currentParams = new URLSearchParams(window.location.search);

          if (filterKeyToRemove.endsWith("[]") && valueToRemove) {
            const existingValues = currentParams.getAll(filterKeyToRemove);
            currentParams.delete(filterKeyToRemove); // Delete all first
            existingValues.forEach((val) => {
              if (val !== valueToRemove) {
                // Add back non-matching values
                currentParams.append(filterKeyToRemove, val);
              }
            });
          } else {
            currentParams.delete(filterKeyToRemove);
          }

          currentParams.delete("paged"); // Reset to page 1

          const newQueryString = currentParams.toString();
          const newUrl =
            window.location.pathname +
            (newQueryString ? "?" + newQueryString : "");

          history.pushState({ path: newUrl, page: 1 }, "", newUrl);

          syncFormAndPopulateCountsFromUrl(window.location.search); // Sync form from new URL
          updateActiveFiltersDisplay(); // Re-render chips from new URL
          submitFiltersWithAjax(1); // Fetch new listings for page 1
        });
      });
  }

  function createFilterChip(label, value, key, dataValue = null) {
    const chip = document.createElement("div");
    chip.className = "active-filter";
    chip.innerHTML = `
             <span class="filter-label">${label}:</span>
             <span class="filter-value">${value}</span>
             <button class="remove-filter" data-key="${key}" ${
      dataValue ? `data-value="${dataValue}"` : ""
    }>×</button>
         `;
    return chip;
  }

  function formatFilterLabel(key) {
    const labelElement = filterForm.querySelector(
      `label[for="${key}"], label[for="${key}_min"]`
    );
    if (labelElement) {
      return labelElement.textContent.replace(":", "").trim();
    }
    const labels = {
      make: "Make",
      model: "Model",
      variant: "Variant",
      location: "Location",
      price_min: "Min Price",
      price_max: "Max Price",
      year_min: "Min Year",
      year_max: "Max Year",
      km_min: "Min KM",
      km_max: "Max KM",
      fuel_type: "Fuel Type",
      body_type: "Body Type",
      drive_type: "Drive Type",
      exterior_color: "Exterior Color",
      interior_color: "Interior Color",
      engine_min: "Min Engine",
      engine_max: "Max Engine",
    };
    return labels[key] || key;
  }

  updateActiveFiltersDisplay();

  filterForm.addEventListener("submit", function (e) {
    e.preventDefault();
    const currentParams = new URLSearchParams(new FormData(filterForm));
    currentParams.delete("paged");

    const newQueryString = currentParams.toString();
    const newUrl =
      window.location.pathname + (newQueryString ? "?" + newQueryString : "");
    history.pushState({ path: newUrl, page: 1 }, "", newUrl);

    // syncFormAndPopulateCountsFromUrl(window.location.search); // Form is source, counts updated by it
    updateAllFilterDisplays(); // Ensure counts are fresh based on form before AJAX
    updateActiveFiltersDisplay(); // Chips from new URL
    submitFiltersWithAjax(1);
  });

  function submitFiltersWithAjax(page = 1) {
    const formData = new FormData(filterForm); // Form should be in sync with URL due to prior calls
    // const filters = {}; // Not strictly needed if using FormData directly for ajaxData
    // const params = new URLSearchParams(); // URL is already updated by the caller

    // Convert FormData to a plain object for easier handling in PHP
    // and build URL params for history.pushState
    // This part is removed as URL is updated by caller.
    // for (const [key, value] of formData.entries()) { ... }

    // Add AJAX action, nonce, and paged info
    const ajaxData = new FormData(filterForm); // Use current form state
    ajaxData.append("action", "filter_car_listings");
    ajaxData.append("nonce", carListingsData.filter_nonce);
    ajaxData.append("paged", page);

    // The FormData directly from filterForm already contains all filter fields.
    // We don't need to manually append `filters[key]` if PHP AJAX handler is robust.
    // Assuming the PHP handler `filter_car_listings` can read from `$_POST` directly
    // or from `$_POST['filters']` if that's how it's structured.
    // Let's assume existing PHP expects filters wrapped in a 'filters' array in POST.
    // To achieve this with FormData, we need to reconstruct it or adjust PHP.
    // For now, let's try to match the previous ajaxData construction logic,
    // but based on the current filterForm state.

    const finalAjaxData = new FormData(); // Create a new FormData for specific AJAX structure
    finalAjaxData.append("action", "filter_car_listings");
    finalAjaxData.append("nonce", carListingsData.filter_nonce);
    finalAjaxData.append("paged", page);

    const currentFormFilters = getFiltersFromForm(); // Get structured filters from form
    for (const key in currentFormFilters) {
      if (Array.isArray(currentFormFilters[key])) {
        currentFormFilters[key].forEach((val) =>
          finalAjaxData.append(`filters[${key}][]`, val)
        );
      } else {
        finalAjaxData.append(`filters[${key}]`, currentFormFilters[key]);
      }
    }

    // --- Visual Feedback Start ---
    const listingsGrid = document.querySelector(".car-listings-grid");
    const paginationContainer = document.querySelector(
      ".car-listings-pagination"
    );
    const container = document.querySelector(".car-listings-container"); // Main container
    if (container) container.classList.add("is-loading"); // Add loading class
    if (listingsGrid) listingsGrid.style.opacity = "0.5";
    if (paginationContainer)
      paginationContainer.innerHTML =
        "<span class='loading-text'>Loading...</span>";
    // --- Visual Feedback End ---

    fetch(carListingsData.ajaxurl, {
      method: "POST",
      body: finalAjaxData, // Use the structured finalAjaxData
      credentials: "same-origin",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        if (container) container.classList.remove("is-loading");
        if (data.success) {
          // Update listings grid
          if (listingsGrid) {
            listingsGrid.innerHTML = data.data.listings_html;
            listingsGrid.style.opacity = "1";
            // --- Re-initialize JS components within the new HTML ---
            reinitializeCarousels();
            reinitializeFavoriteButtons();
            // ---
          }

          // Update pagination
          if (paginationContainer) {
            paginationContainer.innerHTML = data.data.pagination_html;
          }

          // Update URL using History API - This is now done by the CALLER of submitFiltersWithAjax
          // const paramsString = params.toString();
          // const newUrl = paramsString ...
          // history.pushState({ path: newUrl, page: page }, "", newUrl);

          // Update active filter chips display (if applicable)
          if (typeof updateActiveFiltersDisplay === "function") {
            updateActiveFiltersDisplay();
          }

          // Update results counter
          updateResultsCounter(data.data.total_results || 0);

          // Close the filter popup on successful AJAX update
          if (filtersPopup) {
            // filtersPopup should be available from the top scope
            filtersPopup.style.display = "none";
            document.body.style.overflow = ""; // Restore body scroll
          }
        } else {
          console.error("AJAX Error:", data);
          if (listingsGrid) listingsGrid.style.opacity = "1";
          if (paginationContainer)
            paginationContainer.innerHTML =
              "<span class='error-text'>Error loading results.</span>";

          // Update active filter chips display (if applicable)
          if (typeof updateActiveFiltersDisplay === "function") {
            updateActiveFiltersDisplay();
          }
        }
      })
      .catch((error) => {
        console.error("Fetch Error:", error);
        if (container) container.classList.remove("is-loading");
        if (listingsGrid) listingsGrid.style.opacity = "1";
        if (paginationContainer)
          paginationContainer.innerHTML =
            "<span class='error-text'>Error loading results.</span>";
      });
  }

  // --- Handle Pagination Clicks ---
  const paginationContainer = document.querySelector(
    ".car-listings-pagination"
  );
  if (paginationContainer) {
    paginationContainer.addEventListener("click", function (e) {
      const pageLink = e.target.closest("a.page-numbers");
      if (pageLink && !pageLink.classList.contains("current")) {
        e.preventDefault();

        let targetPage = 1;
        const href = pageLink.getAttribute("href");

        // Try to get target page from the link's href first
        try {
          const hrefUrl = new URL(href, window.location.origin);
          const pagedParam = hrefUrl.searchParams.get("paged");
          if (pagedParam) {
            targetPage = parseInt(pagedParam, 10);
            if (isNaN(targetPage) || targetPage < 1) targetPage = 1;
          }
        } catch (error) {
          // Fallback for simple prev/next if URL parsing fails (e.g. if href is just '#')
          const currentUrlParams = new URLSearchParams(window.location.search);
          let currentPage = parseInt(currentUrlParams.get("paged"), 10) || 1;
          if (pageLink.classList.contains("next")) {
            targetPage = currentPage + 1;
          } else if (pageLink.classList.contains("prev")) {
            targetPage = Math.max(1, currentPage - 1);
          }
        }

        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set("paged", targetPage);
        const newQueryString = currentParams.toString();
        const newUrl =
          window.location.pathname +
          (newQueryString ? "?" + newQueryString : "");

        history.pushState({ path: newUrl, page: targetPage }, "", newUrl);

        // No need to call syncForm here as only pagination changed.
        // updateActiveFiltersDisplay will be called by submitFiltersWithAjax on success.
        submitFiltersWithAjax(targetPage);

        const listingsContainer = document.querySelector(
          ".car-listings-container"
        );
        if (listingsContainer) {
          listingsContainer.scrollIntoView({ behavior: "smooth" });
        }
      }
    });
  }

  // --- Handle Browser Back/Forward ---
  window.addEventListener("popstate", function (event) {
    // Sync form from the new URL, update chips, and fetch data.
    syncFormAndPopulateCountsFromUrl(window.location.search);
    updateActiveFiltersDisplay(); // Ensure chips are updated
    const params = new URLSearchParams(window.location.search);
    const page = parseInt(params.get("paged")) || 1;
    submitFiltersWithAjax(page);
  });

  // --- Initialize Carousels ---
  initializeCarousels();

  // Update the reinitializeCarousels function to use the same initialization code
  reinitializeCarousels();

  function populateModels() {
    if (!makeSelect || !modelSelect || !variantSelect) return;
    const make = makeSelect.value;
    const currentModel = new URLSearchParams(window.location.search).get(
      "model"
    );
    modelSelect.innerHTML = '<option value="">All Models</option>';
    variantSelect.innerHTML = '<option value="">All Variants</option>';
    variantSelect.disabled = true;

    if (make && carData[make]) {
      modelSelect.disabled = false;
      Object.keys(carData[make])
        .sort()
        .forEach((model) => {
          const option = document.createElement("option");
          option.value = model;
          const count = modelCounts[make]?.[model] || 0;
          option.textContent = `${model} (${count})`;
          if (model === currentModel) {
            option.selected = true;
          }
          modelSelect.appendChild(option);
        });
      if (currentModel) {
        populateVariants();
      }
    } else {
      modelSelect.disabled = true;
    }
  }

  function populateVariants() {
    if (!makeSelect || !modelSelect || !variantSelect) return;
    const make = makeSelect.value;
    const model = modelSelect.value;
    const currentVariant = new URLSearchParams(window.location.search).get(
      "variant"
    );
    variantSelect.innerHTML = '<option value="">All Variants</option>';

    if (make && model && carData[make]?.[model]) {
      variantSelect.disabled = false;
      carData[make][model].sort().forEach((variant) => {
        const option = document.createElement("option");
        option.value = variant;
        const count = variantCounts[make]?.[model]?.[variant] || 0;
        option.textContent = `${variant} (${count})`;
        if (variant === currentVariant) {
          option.selected = true;
        }
        variantSelect.appendChild(option);
      });
    } else {
      variantSelect.disabled = true;
    }
  }

  if (makeSelect) {
    makeSelect.addEventListener("change", () => {
      // populateModels(); // This is called within syncFormAndPopulateCountsFromUrl if make changes
      // updateAllFilterDisplays(); // This is also called at the end of syncForm

      const currentParams = new URLSearchParams(filterForm); // Get all form data
      currentParams.delete("model"); // Changing make resets model and variant
      currentParams.delete("variant");
      currentParams.delete("paged"); // Reset to page 1

      const newQueryString = currentParams.toString();
      const newUrl =
        window.location.pathname + (newQueryString ? "?" + newQueryString : "");
      history.pushState({ path: newUrl, page: 1 }, "", newUrl);

      // Sync form (which calls populateModels/Variants and updateAllFilterDisplays)
      syncFormAndPopulateCountsFromUrl(window.location.search);
      updateActiveFiltersDisplay(); // Update chips
      submitFiltersWithAjax(1);
    });
  }

  if (modelSelect) {
    modelSelect.addEventListener("change", () => {
      // populateVariants(); // Called by syncForm
      // updateAllFilterDisplays(); // Called by syncForm

      const currentParams = new URLSearchParams(filterForm);
      currentParams.delete("variant"); // Changing model resets variant
      currentParams.delete("paged");

      const newQueryString = currentParams.toString();
      const newUrl =
        window.location.pathname + (newQueryString ? "?" + newQueryString : "");
      history.pushState({ path: newUrl, page: 1 }, "", newUrl);

      syncFormAndPopulateCountsFromUrl(window.location.search);
      updateActiveFiltersDisplay();
      submitFiltersWithAjax(1);
    });
  }

  if (variantSelect) {
    variantSelect.addEventListener("change", () => {
      // updateAllFilterDisplays(); // Called by syncForm

      const currentParams = new URLSearchParams(filterForm);
      currentParams.delete("paged");

      const newQueryString = currentParams.toString();
      const newUrl =
        window.location.pathname + (newQueryString ? "?" + newQueryString : "");
      history.pushState({ path: newUrl, page: 1 }, "", newUrl);

      syncFormAndPopulateCountsFromUrl(window.location.search);
      updateActiveFiltersDisplay();
      submitFiltersWithAjax(1);
    });
  }

  // Generic handler for other filter changes (checkboxes, other selects)
  filterForm
    .querySelectorAll(
      'select:not(#make):not(#model):not(#variant), input[type="checkbox"]'
    )
    .forEach((input) => {
      input.addEventListener("change", () => {
        const currentParams = new URLSearchParams(new FormData(filterForm));
        currentParams.delete("paged"); // Reset to page 1 on any filter change

        const newQueryString = currentParams.toString();
        const newUrl =
          window.location.pathname +
          (newQueryString ? "?" + newQueryString : "");
        history.pushState({ path: newUrl, page: 1 }, "", newUrl);

        // syncForm is not strictly needed here if the form is the source,
        // but updateAllFilterDisplays is important for counts.
        // updateAllFilterDisplays is called by syncForm, so we can call that.
        syncFormAndPopulateCountsFromUrl(window.location.search); // This also calls updateAllFilterDisplays
        updateActiveFiltersDisplay(); // Update chips
        submitFiltersWithAjax(1);
      });
    });

  // --- Initial Page Load ---
  // The old init logic (lines 1095-1139) is now part of syncFormAndPopulateCountsFromUrl

  syncFormAndPopulateCountsFromUrl(window.location.search); // Sync form from URL & update counts in popup
  updateActiveFiltersDisplay(); // Display active filter chips based on URL

  // Extract paged from URL for initial load
  const initialUrlParams = new URLSearchParams(window.location.search);
  const initialPage = parseInt(initialUrlParams.get("paged")) || 1;
  submitFiltersWithAjax(initialPage); // Load initial car listings

  // Initialize carousels and favorite buttons on initial page load
  reinitializeCarousels();
  reinitializeFavoriteButtons();
});

