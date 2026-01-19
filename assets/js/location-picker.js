document.addEventListener('DOMContentLoaded', function () {
    let map = null;
    // let marker = null;
    let selectedCoordinates = null;
    let geocoder = null;
    let selectedLocation = {
        city: '',
        district: '',
        address: '',
        latitude: null,
        longitude: null
    };

    // --- Helper: consistent Cyprus location extraction ---
    function extractCyprusLocation(comps) {
        const get = (type) => {
            const c = comps.find(c => c.types.includes(type));
            return c ? c.long_name : '';
        };

        const locality = get('locality'); // Mesa Geitonia, Agios Athanasios
        const sublocality = get('sublocality') || get('sublocality_level_1') || get('neighborhood');
        const admin1 = get('administrative_area_level_1'); // Lemesos, Lefkosia …
        const admin2 = get('administrative_area_level_2'); // Dimos Amathountas …

        const districtMap = {
            'Lemesos': 'Limassol',
            'Lefkosia': 'Nicosia',
            'Larnaka': 'Larnaca',
            'Ammochostos': 'Famagusta',
            'Pafos': 'Paphos'
        };
        const city = districtMap[admin1] || admin1 || '';

        let municipality = '';
        if (locality) municipality = locality;
        else if (!locality && sublocality) municipality = sublocality;
        else if (!locality && !sublocality && admin2) municipality = admin2;

        return { city, district: municipality };
    }


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

    // function updateMarkerPosition(lngLat) {
    //     if (!marker) {
    //         marker = new google.maps.Marker({
    //             position: lngLat,
    //             map: map,
    //             draggable: false
    //         });
    //     } else {
    //         marker.setPosition(lngLat);
    //     }

    //     const continueBtn = document.querySelector(
    //         '.location-picker-modal .choose-location-btn'
    //     );
    //     if (continueBtn) continueBtn.disabled = false;
    // }

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

        // --- Add a fixed center pin overlay (HTML) ---
        const pin = document.createElement('div');
        pin.className = 'center-pin';
        pin.innerHTML = `
        <svg width="40" height="40" viewBox="0 0 24 24" fill="red" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"></path>
            <circle cx="12" cy="9" r="2.5"></circle>
        </svg>
        `;
        pin.style.position = 'absolute';
        pin.style.top = '50%';
        pin.style.left = '50%';
        pin.style.transform = 'translate(-50%, -100%)';
        pin.style.zIndex = '2';            // above the map canvas
        pin.style.pointerEvents = 'none';  // let map interactions pass through
        mapContainer.style.position = 'relative'; // ensure positioning context
        mapContainer.appendChild(pin);


        const locateCtrl = new LocateMeControl();
        const locateNode = locateCtrl.onAdd(map);
        map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(locateNode);

        const mapCenter = map.getCenter();
        // updateMarkerPosition(mapCenter);
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
                types: ['geocode'],
                language: 'en'
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
                // updateMarkerPosition(loc);
                selectedCoordinates = [loc.lng(), loc.lat()];
                input.value = place.formatted_address || '';

                const comps = place.address_components || [];
                const getComp = (type) => {
                    const comp = comps.find(c => c.types.includes(type));
                    return comp ? comp.long_name : '';
                };


                const { city, district } = extractCyprusLocation(comps);



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

        

        let isZooming = false;
        

        // Detect zoom start/end
        map.addListener('zoom_changed', () => {
            isZooming = true;
        });

        // let lastCenter = null;

        // map.addListener('center_changed', () => {
        //     // Prevent marker repositioning while zooming or if center didn't really change
        //     if (isZooming || !marker) return;

        //     const currentCenter = map.getCenter();

        //     // Avoid micro-movements caused by projection changes
        //     if (
        //         lastCenter &&
        //         Math.abs(currentCenter.lat() - lastCenter.lat()) < 0.000001 &&
        //         Math.abs(currentCenter.lng() - lastCenter.lng()) < 0.000001
        //     ) {
        //         return;
        //     }

        //     marker.setPosition(currentCenter);
        //     lastCenter = currentCenter;
        // });

        
        let moveTimeout;
        map.addListener('idle', () => {
            // if just finished zooming, skip reverse geocode to avoid jitter
            if (isZooming) { isZooming = false; return; }

            if (moveTimeout) clearTimeout(moveTimeout);
            moveTimeout = setTimeout(() => {
                const center = map.getCenter();
                selectedCoordinates = [center.lng(), center.lat()];

                const continueBtn = locationModal.querySelector('.choose-location-btn');
                if (continueBtn) continueBtn.disabled = false;

                reverseGeocode(center);
            }, 200);
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
        if (isDevelopment) console.log('Reverse geocoding:', centerLatLng);
        if (typeof google !== 'undefined' && google.maps && geocoder) {
            geocoder.geocode({ location: centerLatLng, region: 'CY', language: 'en' }, (results, status) => {
                if (status === 'OK' && results && results.length) {
                    // Filter out plus code results
                    let result = results.find(r =>
                        r.formatted_address && !r.formatted_address.match(/^\w{4,}\+/)
                    );
    
                    if (!result) {
                        // fallback to first if all are plus codes
                        result = results[0];
                        if (isDevelopment) console.warn('Only Plus Code results found, retrying for place_id:', result.place_id);
    
                        // Try getting more details for this place_id (might have street address)
                        if (result.place_id) {
                            geocoder.geocode({ placeId: result.place_id, language: 'en' }, (res, st) => {
                                if (st === 'OK' && res && res.length) {
                                    const proper = res.find(r =>
                                        r.formatted_address && !r.formatted_address.match(/^\w{4,}\+/)
                                    ) || res[0];
                                    applyReverseGeocodeResult(proper, centerLatLng);
                                } else {
                                    applyReverseGeocodeResult(result, centerLatLng);
                                }
                            });
                            return;
                        }
                    }
    
                    applyReverseGeocodeResult(result, centerLatLng);
                } else if (isDevelopment) {
                    console.warn('Geocoder failed due to:', status);
                }
            });
        }
    }
    
    // Helper function to apply the chosen result
    function applyReverseGeocodeResult(result, centerLatLng) {
        const comps = result.address_components || [];
        const getComp = (type) => {
            const comp = comps.find(c => c.types.includes(type));
            return comp ? comp.long_name : '';
        };

        const { city, district } = extractCyprusLocation(comps);


    
        selectedLocation = {
            city,
            district,
            address: result.formatted_address || '',
            latitude: centerLatLng.lat(),
            longitude: centerLatLng.lng()
        };
    
        const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
        if (geocoderInput) geocoderInput.value = selectedLocation.address;
    
        if (isDevelopment) console.log('Final address:', selectedLocation.address);
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

                const roundCoord = (num) => parseFloat(Number(num).toFixed(6));

                const fields = {
                    car_city: selectedLocation.city,
                    car_district: selectedLocation.district || selectedLocation.city,
                    car_latitude: roundCoord(selectedLocation.latitude),
                    car_longitude: roundCoord(selectedLocation.longitude),
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

            // Update the saved locations dropdown to show the selected address
            if (typeof window.showLocationInDropdown === 'function') {
                window.showLocationInDropdown(finalAddress);
            }
        }

        locationModal.style.display = 'none';
    }

    const chooseLocationBtn = document.querySelector('.choose-location-btn');
    if (chooseLocationBtn)
        chooseLocationBtn.addEventListener('click', showLocationPicker);
});
