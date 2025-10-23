document.addEventListener('DOMContentLoaded', function () {
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

    let savedLocationForSession = null;
    let locationModal = null;

    // Detect environment
    window.isDevelopment =
        window.isDevelopment ||
        window.location.hostname === 'localhost' ||
        window.location.hostname.includes('staging') ||
        window.location.search.includes('debug=true');

    if (isDevelopment) console.log('Map Config:', mapConfig);

    // --- Locate Me Control ---
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

                button.classList.add('mapboxgl-ctrl-geolocate-active');

                const watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        navigator.geolocation.clearWatch(watchId);
                        const newLatLng = new google.maps.LatLng(
                            position.coords.latitude,
                            position.coords.longitude
                        );
                        selectedCoordinates = [newLatLng.lng(), newLatLng.lat()];

                        const accuracy = position.coords.accuracy;
                        let zoomLevel = 17;
                        if (accuracy > 100) zoomLevel = 15;
                        else if (accuracy > 50) zoomLevel = 16;
                        else if (accuracy > 20) zoomLevel = 17;
                        else zoomLevel = 18;

                        if (this._map) {
                            this._map.panTo(newLatLng);
                            this._map.setZoom(Number(zoomLevel));
                        }

                        if (isDevelopment)
                            console.log(
                                `Located with accuracy ${accuracy.toFixed(1)}m`
                            );

                        button.classList.remove('mapboxgl-ctrl-geolocate-active');
                    },
                    (error) => {
                        navigator.geolocation.clearWatch(watchId);
                        alert(`Error getting location: ${error.message}`);
                        if (isDevelopment)
                            console.error('Geolocation error:', error);
                        button.classList.remove('mapboxgl-ctrl-geolocate-active');
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            };

            this._container.appendChild(button);
            return this._container;
        }

        onRemove() {
            if (this._container?.parentNode) {
                this._container.parentNode.removeChild(this._container);
            }
            this._map = undefined;
        }
    }
    // --- End Locate Me Control ---

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

        const continueBtn = document.querySelector(
            '.location-picker-modal .choose-location-btn'
        );
        if (continueBtn) continueBtn.disabled = false;
    }

    function showLocationPicker() {
        if (locationModal && document.body.contains(locationModal)) {
            locationModal.style.display = 'flex';
            return;
        }

        if (locationModal) locationModal = null;

        map = null;
        marker = null;
        selectedCoordinates = null;
        geocoder = null;

        let initialCenter = mapConfig.center || [33.3823, 35.1856];
        let initialZoom = Number(mapConfig.defaultZoom) || 8;

        if (savedLocationForSession) {
            initialCenter = [
                savedLocationForSession.longitude,
                savedLocationForSession.latitude
            ];
            initialZoom = 15;
            selectedLocation = { ...savedLocationForSession };
        }

        const locationField = document.getElementById('location');
        const originalLocationValue = locationField?.value || '';

        locationModal = document.createElement('div');
        locationModal.className = 'location-picker-modal';
        locationModal.innerHTML = `
            <div class="location-picker-content">
                <div class="location-picker-header">
                    <h2>Choose Location</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="location-picker-body">
                    <div class="search-container" id="geocoder"></div>
                    <div class="location-map"></div>
                </div>
                <div class="location-picker-footer">
                    <button class="choose-location-btn" disabled>Continue</button>
                </div>
            </div>
        `;
        document.body.appendChild(locationModal);

        const mapContainer = locationModal.querySelector('.location-map');
        mapContainer.classList.add('visible');
        const initialLatLng = new google.maps.LatLng(
            initialCenter[1],
            initialCenter[0]
        );

        map = new google.maps.Map(mapContainer, {
            center: initialLatLng,
            zoom: initialZoom,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
            gestureHandling: 'greedy',
            zoomControl: true
        });

        const locateCtrl = new LocateMeControl();
        const locateNode = locateCtrl.onAdd(map);
        map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(locateNode);

        const mapCenter = map.getCenter();
        updateMarkerPosition(mapCenter);
        selectedCoordinates = [mapCenter.lng(), mapCenter.lat()];

        // --- Geocoder + Places Autocomplete ---
        geocoder = new google.maps.Geocoder();
        const geocoderContainer = document.getElementById('geocoder');

        if (geocoderContainer) {
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

            // Ensure dropdown appears correctly
            setTimeout(() => {
                const pacContainer = document.querySelector('.pac-container');
                if (pacContainer) {
                    pacContainer.style.zIndex = '999999';
                    pacContainer.style.position = 'absolute';
                    document.body.appendChild(pacContainer);
                }
            }, 300);

            input.addEventListener('focus', () =>
                google.maps.event.trigger(input, 'focus')
            );
            input.addEventListener('keydown', (e) =>
                google.maps.event.trigger(input, 'keydown', e)
            );

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();
                if (!place.geometry || !place.geometry.location) return;

                const loc = place.geometry.location;
                map.panTo(loc);
                map.setZoom(15);
                updateMarkerPosition(loc);
                selectedCoordinates = [loc.lng(), loc.lat()];
                input.value = place.formatted_address || '';

                const comps = place.address_components || [];
                const getComp = (type) => {
                    const comp = comps.find((c) => c.types.includes(type));
                    return comp ? comp.long_name : '';
                };
                const city =
                    getComp('locality') ||
                    getComp('administrative_area_level_3') ||
                    getComp('postal_town') ||
                    '';
                const district =
                    getComp('neighborhood') ||
                    getComp('sublocality') ||
                    getComp('locality') ||
                    city ||
                    '';
                selectedLocation = {
                    city,
                    district,
                    address: place.formatted_address || '',
                    latitude: loc.lat(),
                    longitude: loc.lng()
                };

                const continueBtn =
                    locationModal.querySelector('.choose-location-btn');
                if (continueBtn) continueBtn.disabled = false;
            });
        }
        // --- End Autocomplete ---

        map.addListener('click', (e) => {
            const clicked = e.latLng;
            selectedCoordinates = [clicked.lng(), clicked.lat()];
            map.panTo(clicked);
        });

        map.addListener('center_changed', () => {
            if (marker) marker.setPosition(map.getCenter());
        });

        let moveTimeout;
        map.addListener('idle', () => {
            if (moveTimeout) clearTimeout(moveTimeout);
            moveTimeout = setTimeout(() => {
                const center = map.getCenter();
                selectedCoordinates = [center.lng(), center.lat()];
                const continueBtn =
                    locationModal.querySelector('.choose-location-btn');
                if (continueBtn) continueBtn.disabled = false;
                reverseGeocode(center);
            }, 150);
        });

        // --- Close modal ---
        const closeBtn = locationModal.querySelector('.close-modal');
        closeBtn.addEventListener('click', () => {
            if (locationField && !savedLocationForSession)
                locationField.value = originalLocationValue;
            locationModal.style.display = 'none';
        });

        locationModal.addEventListener('click', (e) => {
            if (e.target === locationModal) {
                if (locationField && !savedLocationForSession)
                    locationField.value = originalLocationValue;
                locationModal.style.display = 'none';
            }
        });

        const continueBtn = locationModal.querySelector('.choose-location-btn');
        continueBtn.addEventListener('click', () => handleContinue(locationModal));
    }

    function reverseGeocode(centerLatLng) {
        if (typeof google !== 'undefined' && google.maps && geocoder) {
            geocoder.geocode({ location: centerLatLng, region: 'CY' }, (results, status) => {
                if (status === 'OK' && results?.length) {
                    const result = results[0];
                    const comps = result.address_components || [];
                    const getComp = (type) => {
                        const comp = comps.find((c) => c.types.includes(type));
                        return comp ? comp.long_name : '';
                    };
                    const city =
                        getComp('locality') ||
                        getComp('administrative_area_level_3') ||
                        getComp('postal_town') ||
                        '';
                    const district =
                        getComp('neighborhood') ||
                        getComp('sublocality') ||
                        getComp('locality') ||
                        city ||
                        '';
                    selectedLocation = {
                        city,
                        district,
                        address: result.formatted_address || '',
                        latitude: centerLatLng.lat(),
                        longitude: centerLatLng.lng()
                    };

                    const geocoderInput = document.querySelector(
                        '.mapboxgl-ctrl-geocoder input'
                    );
                    if (geocoderInput)
                        geocoderInput.value = selectedLocation.address;
                }
            });
        }
    }

    function handleContinue(locationModal) {
        if (!selectedLocation.latitude || !selectedLocation.longitude) return;

        const locationField = document.getElementById('location');
        const geocoderInput = document.querySelector(
            '.mapboxgl-ctrl-geocoder input'
        );
        const finalAddress =
            (geocoderInput && geocoderInput.value) || selectedLocation.address;

        if (locationField) {
            locationField.value = finalAddress;
            savedLocationForSession = {
                ...selectedLocation,
                address: finalAddress
            };

            const form = locationField.closest('form');
            if (form) {
                ['car_city', 'car_district', 'car_latitude', 'car_longitude', 'car_address'].forEach((field) => {
                    const existing = form.querySelector(`input[name="${field}"]`);
                    if (existing) existing.remove();
                });

                const fields = {
                    car_city: selectedLocation.city,
                    car_district: selectedLocation.district || selectedLocation.city,
                    car_latitude: selectedLocation.latitude,
                    car_longitude: selectedLocation.longitude,
                    car_address: finalAddress
                };

                Object.entries(fields).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });
            }
        }

        locationModal.style.display = 'none';
    }

    const chooseLocationBtn = document.querySelector('.choose-location-btn');
    if (chooseLocationBtn)
        chooseLocationBtn.addEventListener('click', showLocationPicker);
});
