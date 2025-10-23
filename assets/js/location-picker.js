document.addEventListener('DOMContentLoaded', function() {
    let map = null;
    let marker = null;
    let selectedCoordinates = null;
    let geocoder = null;
    let selectedLocation = {
        city: '',
        district: '',
        address: '',
        latitude: null,
        longitude: null
    };

    // Page-level variable to store the last selected location for the session
    let savedLocationForSession = null;
    
    // Store modal reference to reuse it
    let locationModal = null;

    // Debug: Check if mapConfig is available
    // PRODUCTION SAFETY: Only log in development environments
window.isDevelopment = window.isDevelopment || (window.location.hostname === 'localhost' || 
                                               window.location.hostname.includes('staging') ||
                                               window.location.search.includes('debug=true'));
    
    if (isDevelopment) console.log('Map Config:', mapConfig);

    // --- Locate Me Control for Google Maps ---
    class LocateMeControl {
        onAdd(mapInstance) {
            this._map = mapInstance;
            this._container = document.createElement('div');
            this._container.className = 'mapboxgl-ctrl mapboxgl-ctrl-group';

            const button = document.createElement('button');
            button.className = 'mapboxgl-ctrl-text mapboxgl-ctrl-locate-me';
            button.type = 'button';
            button.title = 'Find my current location';
            button.setAttribute('aria-label', 'Find my current location');
            button.textContent = 'Find my current location';

            button.onclick = () => {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by your browser.');
                    return;
                }

                // Add a loading/locating indicator to the button
                button.classList.add('mapboxgl-ctrl-geolocate-active');

                // Force fresh location by using watchPosition briefly then clearing it
                // This bypasses browser location caching more effectively
                let watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        // Immediately clear the watch to stop continuous tracking
                        navigator.geolocation.clearWatch(watchId);
                        
                        const newLatLng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
                        selectedCoordinates = [newLatLng.lng(), newLatLng.lat()];

                        // Check accuracy and zoom level accordingly
                        const accuracy = position.coords.accuracy;
                        const timestamp = new Date(position.timestamp).toLocaleTimeString();
                        let zoomLevel = 18; // Very close zoom for high accuracy
                        
                        if (accuracy > 100) {
                            zoomLevel = 15; // Moderate zoom for lower accuracy
                        } else if (accuracy > 50) {
                            zoomLevel = 16; // Close zoom for medium accuracy
                        } else if (accuracy > 20) {
                            zoomLevel = 17; // Closer zoom for good accuracy
                        }

                        if (this._map && typeof this._map.setZoom === 'function') {
                            this._map.panTo(newLatLng);
                            this._map.setZoom(zoomLevel);
                        }
                        
                        // Log accuracy and timestamp for debugging
                        if (isDevelopment) console.log(`Fresh location found at ${timestamp} with accuracy: ${accuracy.toFixed(1)} meters`);
                        
                        // The map's 'moveend' event will handle marker update and reverse geocode
                        button.classList.remove('mapboxgl-ctrl-geolocate-active');
                    },
                    (error) => {
                        navigator.geolocation.clearWatch(watchId);
                        alert(`Error getting location: ${error.message}`);
                        if (isDevelopment) console.error('Geolocation error:', error);
                        button.classList.remove('mapboxgl-ctrl-geolocate-active');
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000, // Increased timeout for better GPS fix
                        maximumAge: 0, // Always get fresh location
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

    // Function to update marker position
    function updateMarkerPosition(lngLat) {
        if (!marker) {
            marker = new google.maps.Marker({
                position: lngLat,
                map: map,
                draggable: false
            });
        } else {
            marker.setPosition(lngLat);
        }

        // Enable continue button
        const continueBtn = document.querySelector('.location-picker-modal .choose-location-btn');
        if (continueBtn) {
            continueBtn.disabled = false;
            if (isDevelopment) console.log('Continue button enabled');
        } else {
            if (isDevelopment) console.warn('Continue button not found');
        }
    }

    // Function to show location picker
    function showLocationPicker() {
        // Check if modal already exists and is still in the document
        if (locationModal && document.body.contains(locationModal)) {
            if (isDevelopment) console.log('Reusing existing modal');
            locationModal.style.display = 'flex';
            return;
        }
        
        // If modal exists but is not in document, reset it
        if (locationModal) {
            if (isDevelopment) console.log('Modal exists but not in document, resetting');
            locationModal = null;
        }
        
        if (isDevelopment) console.log('Creating new modal for first time');
        
        // Reset global variables
        map = null;
        marker = null;
        selectedCoordinates = null;
        geocoder = null;
        
        // Check if we have a saved location from previous selection
        let initialCenter = mapConfig.center;
        let initialCenterArr = initialCenter; // [lng, lat]
        let initialZoom = mapConfig.defaultZoom;
        
        if (savedLocationForSession) {
            if (isDevelopment) console.log('Using saved location from previous selection:', savedLocationForSession);
            initialCenterArr = [savedLocationForSession.longitude, savedLocationForSession.latitude];
            initialZoom = 15; // Zoom in closer to the saved location
            selectedLocation = { ...savedLocationForSession }; // Copy the saved location
        } else {
            if (isDevelopment) console.log('No saved location found, using default map center');
            selectedLocation = {
                city: '',
                district: '',
                address: '',
                latitude: null,
                longitude: null
            };
        }

        // Store the original location field value
        const locationField = document.getElementById('location');
        const originalLocationValue = locationField ? locationField.value : '';

        // Create modal
        locationModal = document.createElement('div');
        locationModal.className = 'location-picker-modal';
        locationModal.innerHTML = `
            <div class="location-picker-content">
                <div class="location-picker-header">
                    <h2>Choose Location</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="location-picker-body">
                    <div class="location-selection-container">
                        <div class="search-container">
                            <div id="geocoder" class="geocoder"></div>
                        </div>
                    </div>
                    <div class="location-map"></div>
                </div>
                <div class="location-picker-footer">
                    <button class="choose-location-btn" disabled>Continue</button>
                </div>
            </div>
        `;

        // Add modal to body
        document.body.appendChild(locationModal);

        // Initialize map
        const mapContainer = locationModal.querySelector('.location-map');
        mapContainer.classList.add('visible');

        // Initialize Google Maps
        if (typeof google !== 'undefined' && google.maps) {
            try {
                // Prepare initial center and ensure zoom is a number
                const centerArr = Array.isArray(initialCenterArr) ? initialCenterArr : [33.3823, 35.1856];
                const initialLatLng = new google.maps.LatLng(centerArr[1], centerArr[0]);
                const zoom = typeof initialZoom === 'number' ? initialZoom : 8;

                // Initialize Google Map
                map = new google.maps.Map(mapContainer, {
                    center: initialLatLng,
                    zoom: zoom,
                    mapTypeControl: true,
                    streetViewControl: true,
                    fullscreenControl: true,
                    gestureHandling: 'greedy',
                    zoomControl: true,
                });

                // Add Locate Me control (keep existing CSS classes for minimal UI change)
                const locateCtrl = new LocateMeControl();
                const locateNode = locateCtrl.onAdd(map);
                if (map.controls && google.maps.ControlPosition) {
                    map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(locateNode);
                } else {
                    mapContainer.parentNode.appendChild(locateNode);
                }

                // Initial marker at center
                const mapCenter = map.getCenter();
                updateMarkerPosition(mapCenter);
                selectedCoordinates = [mapCenter.lng(), mapCenter.lat()];

                // Geocoder + Places Autocomplete
                geocoder = new google.maps.Geocoder();
                const geocoderContainer = document.getElementById('geocoder');
                if (geocoderContainer) {
                    // Wrap input to keep existing selector '.mapboxgl-ctrl-geocoder input'
                    const wrapper = document.createElement('div');
                    wrapper.className = 'mapboxgl-ctrl-geocoder';
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Search for a location in Cyprus...';
                    input.autocomplete = 'off';
                    wrapper.appendChild(input);
                    geocoderContainer.appendChild(wrapper);

                    const autocomplete = new google.maps.places.Autocomplete(input, {
                        componentRestrictions: { country: 'cy' },
                        fields: ['geometry', 'formatted_address', 'address_components'],
                        types: ['geocode']
                    });

                    google.maps.event.addListener(autocomplete, 'place_changed', function() {
                        const place = autocomplete.getPlace();
                        if (!place.geometry) return;
                        const loc = place.geometry.location;
                        map.panTo(loc);
                        map.setZoom(15);
                    
                        selectedCoordinates = [loc.lng(), loc.lat()];
                        updateMarkerPosition(loc);
                    
                        // Fix: visually indicate found address
                        input.value = place.formatted_address || '';
                    });
                    

                    if (savedLocationForSession && savedLocationForSession.address) {
                        input.value = savedLocationForSession.address;
                        const continueBtn = locationModal.querySelector('.choose-location-btn');
                        if (continueBtn) continueBtn.disabled = false;
                    }

                    autocomplete.addListener('place_changed', () => {
                        const place = autocomplete.getPlace();
                        if (!place || !place.geometry || !place.geometry.location) return;
                        const loc = place.geometry.location;
                        map.panTo(loc);
                        map.setZoom(15);
                        selectedCoordinates = [loc.lng(), loc.lat()];

                        const comps = place.address_components || [];
                        const getComp = (type) => {
                            const comp = comps.find(c => c.types.includes(type));
                            return comp ? comp.long_name : '';
                        };
                        const city = getComp('locality') || getComp('administrative_area_level_3') || getComp('postal_town') || '';
                        const district = getComp('neighborhood') || getComp('sublocality') || getComp('locality') || city || '';
                        selectedLocation = {
                            city: city,
                            district: district,
                            address: place.formatted_address || '',
                            latitude: loc.lat(),
                            longitude: loc.lng()
                        };

                        const continueBtn = locationModal.querySelector('.choose-location-btn');
                        if (continueBtn) continueBtn.disabled = false;
                    });
                }

                // Click to pan
                map.addListener('click', (e) => {
                    const clicked = e.latLng;
                    selectedCoordinates = [clicked.lng(), clicked.lat()];
                    map.panTo(clicked);
                });

                // Keep marker centered during movement
                map.addListener('center_changed', () => {
                    if (marker) {
                        marker.setPosition(map.getCenter());
                    }
                });

                // After movement ends, update + reverse geocode
                let moveTimeout;
                map.addListener('idle', () => {
                    if (moveTimeout) clearTimeout(moveTimeout);
                    moveTimeout = setTimeout(() => {
                        const center = map.getCenter();
                        selectedCoordinates = [center.lng(), center.lat()];
                        const continueBtn = locationModal.querySelector('.choose-location-btn');
                        if (continueBtn) continueBtn.disabled = false;
                        reverseGeocode(center);
                    }, 150);
                });
            } catch (error) {
                if (isDevelopment) console.error('Error initializing Google Maps:', error);
            }
        }

        // Close button functionality
        const closeBtn = locationModal.querySelector('.close-modal');
        closeBtn.addEventListener('click', () => {
            // Only restore original value if no location was ever saved in this session
            if (locationField && !savedLocationForSession) {
                locationField.value = originalLocationValue;
            }
            // Hide modal instead of removing it
            locationModal.style.display = 'none';
        });

        // Close on outside click
        locationModal.addEventListener('click', (e) => {
            if (e.target === locationModal) {
                // Only restore original value if no location was ever saved in this session
                if (locationField && !savedLocationForSession) {
                    locationField.value = originalLocationValue;
                }
                // Hide modal instead of removing it
                locationModal.style.display = 'none';
            }
        });

        // Continue button functionality
        const continueBtn = locationModal.querySelector('.choose-location-btn');
        continueBtn.addEventListener('click', () => {
                    if (isDevelopment) console.log('Continue button clicked');
        if (isDevelopment) console.log('Selected location:', selectedLocation);
            handleContinue(locationModal);
        });
    }

    // Function to clean up map resources
    function cleanupMap() {
        if (marker) {
            marker.setMap(null);
            marker = null;
        }
        if (map) {
            // Google Maps doesn't have a remove() method like Mapbox
            map = null;
        }
        if (geocoder) {
            geocoder = null;
        }
        selectedCoordinates = null;
        selectedLocation = {
            city: '',
            district: '',
            address: '',
            latitude: null,
            longitude: null
        };
    }

    // Function to reverse geocode coordinates
    function reverseGeocode(centerLatLng) {
        if (isDevelopment) console.log('Reverse geocoding:', centerLatLng);
        if (typeof google !== 'undefined' && google.maps && geocoder) {
            geocoder.geocode({ location: centerLatLng, region: 'CY' }, (results, status) => {
                if (status === 'OK' && results && results.length) {
                    const result = results[0];
                    const comps = result.address_components || [];
                    const getComp = (type) => {
                        const comp = comps.find(c => c.types.includes(type));
                        return comp ? comp.long_name : '';
                    };
                    const city = getComp('locality') || getComp('administrative_area_level_3') || getComp('postal_town') || '';
                    const district = getComp('neighborhood') || getComp('sublocality') || getComp('locality') || city || '';
                    selectedLocation = {
                        city: city,
                        district: district,
                        address: result.formatted_address || '',
                        latitude: centerLatLng.lat(),
                        longitude: centerLatLng.lng()
                    };

                    const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
                    if (geocoderInput) geocoderInput.value = selectedLocation.address;
                } else if (isDevelopment) {
                    console.warn('Geocoder failed due to:', status);
                }
            });
        }
    }

    // Add click handler to the button
    const chooseLocationBtn = document.querySelector('.choose-location-btn');
    if (chooseLocationBtn) {
        chooseLocationBtn.addEventListener('click', showLocationPicker);
    }

    function handleContinue(locationModal) {
        if (isDevelopment) console.log('Handling continue...');
        if (selectedLocation.latitude && selectedLocation.longitude) {
            if (isDevelopment) console.log('Location selected:', selectedLocation);
            
            // Get the current search input value
            const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
            const searchValue = geocoderInput ? geocoderInput.value : selectedLocation.address;
            
            // Update the location field with the full address
            const locationField = document.getElementById('location');
            if (locationField) {
                // Use the search value if available, otherwise use the selected location address
                const finalAddress = searchValue || selectedLocation.address;
                locationField.value = finalAddress;
                if (isDevelopment) console.log('Updated location field with:', finalAddress);
                
                // Save the location for the page session
                savedLocationForSession = {
                    city: selectedLocation.city,
                    district: selectedLocation.district || selectedLocation.city,
                    address: finalAddress,
                    latitude: selectedLocation.latitude,
                    longitude: selectedLocation.longitude
                };
                if (isDevelopment) console.log('Saved location for session:', savedLocationForSession);
                
                // Add hidden fields for location components
                const form = locationField.closest('form');
                if (form) {
                    // Remove any existing hidden fields first
                    ['car_city', 'car_district', 'car_latitude', 'car_longitude', 'car_address'].forEach(field => {
                        const existingField = form.querySelector(`input[name="${field}"]`);
                        if (existingField) existingField.remove();
                    });
                    
                    // Add new hidden fields
                    const fields = {
                        'car_city': selectedLocation.city,
                        'car_district': selectedLocation.district || selectedLocation.city, // Fallback to city if no district
                        'car_latitude': selectedLocation.latitude,
                        'car_longitude': selectedLocation.longitude,
                        'car_address': finalAddress
                    };
                    
                    if (isDevelopment) console.log('Adding hidden fields with values:', fields);
                    
                    Object.entries(fields).forEach(([name, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                        if (isDevelopment) console.log(`Added hidden field ${name} with value:`, value);
                    });
                    
                    if (isDevelopment) console.log('Added hidden fields for location components');
                }
            } else {
                if (isDevelopment) console.warn('Location field not found');
            }
            
            // Hide modal instead of removing it
            locationModal.style.display = 'none';
        } else {
            if (isDevelopment) console.log('No location selected');
        }
    }
}); 