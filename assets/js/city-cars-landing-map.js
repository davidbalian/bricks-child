/**
 * Google Maps + circle UI for city cars landing (used by city-cars-landing-browse.js).
 */
(function (window) {
    'use strict';

    /**
     * @param {jQuery} $
     * @param {object} cfg autoagoraCityCarsBrowse
     * @param {object} api Surface for map script to call back into browse (setLocationPoint, syncVisuals).
     */
    window.AutoagoraCityCarsBrowseMap = function ($, cfg, api) {
        var locationMap = null;
        var locationCircle = null;
        var locationGeocoder = null;
        var locationAutocomplete = null;
        var reverseGeocodeTimer = null;

        function getZoomForRadius(radiusKm) {
            if (radiusKm <= 1) return 12.8;
            if (radiusKm <= 2) return 12.2;
            if (radiusKm <= 3) return 11.8;
            if (radiusKm <= 5) return 11.4;
            if (radiusKm <= 10) return 10.6;
            if (radiusKm <= 25) return 9.7;
            if (radiusKm <= 50) return 8.8;
            if (radiusKm <= 100) return 7.9;
            return 7.0;
        }

        function getLocationLabelFromComponents(components) {
            var comps = components || [];
            function get(type) {
                var comp = comps.find(function (c) {
                    return c.types && c.types.indexOf(type) !== -1;
                });
                return comp ? comp.long_name : '';
            }
            var districtMap = {
                'Lemesos': 'Limassol',
                'Lefkosia': 'Nicosia',
                'Larnaka': 'Larnaca',
                'Ammochostos': 'Famagusta',
                'Pafos': 'Paphos'
            };
            var locality = get('locality') || get('postal_town') || get('administrative_area_level_2');
            var admin1 = get('administrative_area_level_1');
            var admin1Mapped = districtMap[admin1] || admin1;
            return locality || admin1Mapped || '';
        }

        function reverseGeocodeCenter(locationState) {
            if (!locationMap || !locationGeocoder) {
                return;
            }
            var center = locationMap.getCenter();
            if (!center) {
                return;
            }
            locationGeocoder.geocode(
                { location: center, region: 'CY', language: 'en' },
                function (results, status) {
                    if (status !== 'OK' || !results || !results.length) {
                        return;
                    }
                    var result = results[0];
                    var searchInput = document.getElementById('tcp-location-search');
                    if (searchInput) {
                        searchInput.value = result.formatted_address || '';
                    }
                    locationState.label = getLocationLabelFromComponents(result.address_components || []) || (result.formatted_address || '');
                }
            );
        }

        function initLocationMap(locationState) {
            if (typeof google === 'undefined' || !google.maps) {
                return;
            }
            var mapEl = document.getElementById('tcp-location-map');
            if (!mapEl) {
                return;
            }
            var fallbackLat = parseFloat(cfg.mapFallbackLat) || 35.1856;
            var fallbackLng = parseFloat(cfg.mapFallbackLng) || 33.3823;
            var defaultCenter = { lat: fallbackLat, lng: fallbackLng };

            if (!locationMap) {
                locationMap = new google.maps.Map(mapEl, {
                    center: defaultCenter,
                    zoom: 8,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false
                });
                locationGeocoder = new google.maps.Geocoder();
                locationCircle = new google.maps.Circle({
                    map: locationMap,
                    strokeColor: '#0d86e3',
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: '#0d86e3',
                    fillOpacity: 0.15,
                    radius: locationState.radiusKm * 1000
                });

                locationMap.addListener('click', function (e) {
                    api.setLocationPoint(e.latLng.lat(), e.latLng.lng(), false, locationState);
                });
                locationMap.addListener('center_changed', function () {
                    if (!locationMap) return;
                    var c = locationMap.getCenter();
                    if (!c) return;
                    locationState.lat = c.lat();
                    locationState.lng = c.lng();
                    api.syncLocationVisuals(false, locationState);
                });
                locationMap.addListener('idle', function () {
                    if (reverseGeocodeTimer) {
                        clearTimeout(reverseGeocodeTimer);
                    }
                    reverseGeocodeTimer = setTimeout(function () {
                        reverseGeocodeCenter(locationState);
                    }, 120);
                });

                var searchInput = document.getElementById('tcp-location-search');
                if (searchInput) {
                    locationAutocomplete = new google.maps.places.Autocomplete(searchInput, {
                        componentRestrictions: { country: 'cy' },
                        fields: ['geometry', 'formatted_address', 'address_components'],
                        types: ['geocode']
                    });
                    locationAutocomplete.addListener('place_changed', function () {
                        var place = locationAutocomplete.getPlace();
                        if (!place.geometry || !place.geometry.location) {
                            return;
                        }
                        locationState.label = getLocationLabelFromComponents(place.address_components || []) || (place.formatted_address || searchInput.value || '');
                        if (place.formatted_address) {
                            searchInput.value = place.formatted_address;
                        }
                        api.setLocationPoint(place.geometry.location.lat(), place.geometry.location.lng(), true, locationState);
                    });
                }
            }

            var shouldUseSaved = locationState.lat && locationState.lng;
            if (shouldUseSaved) {
                locationMap.setCenter({ lat: locationState.lat, lng: locationState.lng });
                api.syncLocationVisuals(true, locationState);
            } else {
                var currentCenter = locationMap.getCenter();
                if (currentCenter) {
                    locationState.lat = currentCenter.lat();
                    locationState.lng = currentCenter.lng();
                    api.syncLocationVisuals(true, locationState);
                }
            }
        }

        return {
            getZoomForRadius: getZoomForRadius,
            initLocationMap: initLocationMap,
            getMap: function () { return locationMap; },
            getCircle: function () { return locationCircle; },
            setCircleRadius: function (radiusKm) {
                if (locationCircle) {
                    locationCircle.setRadius(radiusKm * 1000);
                }
            },
            setMapZoom: function (radiusKm) {
                if (locationMap) {
                    locationMap.setZoom(getZoomForRadius(radiusKm));
                }
            }
        };
    };
}(window));
