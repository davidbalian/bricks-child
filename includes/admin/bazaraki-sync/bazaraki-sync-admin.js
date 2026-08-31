(function () {
    'use strict';

    const config = window.autoagoraSyncLocation || {};
    const fieldNames = ['car_city', 'car_district', 'car_address', 'car_latitude', 'car_longitude'];
    let modal = null;
    let map = null;
    let geocoder = null;
    let activePicker = null;
    let selectedLocation = null;
    let reverseGeocodeSequence = 0;

    function text(key, fallback) {
        return config[key] || fallback;
    }

    function field(picker, name) {
        return picker.querySelector('[data-location-field="' + name + '"]');
    }

    function storedLocation(picker) {
        const latitude = Number(field(picker, 'car_latitude').value);
        const longitude = Number(field(picker, 'car_longitude').value);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || latitude === 0 || longitude === 0) {
            return null;
        }
        return {
            city: field(picker, 'car_city').value,
            district: field(picker, 'car_district').value,
            address: field(picker, 'car_address').value,
            latitude: latitude,
            longitude: longitude
        };
    }

    function extractLocation(components) {
        const get = function (type) {
            const component = (components || []).find(function (item) {
                return item.types.indexOf(type) !== -1;
            });
            return component ? component.long_name : '';
        };
        const region = get('administrative_area_level_1');
        const cities = {
            Lefkosia: 'Nicosia', Nicosia: 'Nicosia', 'Nicosia District': 'Nicosia',
            Lemesos: 'Limassol', Limassol: 'Limassol', 'Limassol District': 'Limassol',
            Larnaka: 'Larnaca', Larnaca: 'Larnaca', 'Larnaca District': 'Larnaca',
            Pafos: 'Paphos', Paphos: 'Paphos', 'Paphos District': 'Paphos',
            Ammochostos: 'Famagusta', Famagusta: 'Famagusta', 'Famagusta District': 'Famagusta'
        };
        return {
            city: cities[region] || region || '',
            district: get('locality') || get('sublocality_level_1') || get('sublocality') || get('neighborhood') || ''
        };
    }

    function setApplyEnabled(enabled) {
        if (modal) {
            modal.querySelector('[data-apply-location]').disabled = !enabled;
        }
    }

    function applyGeocodeResult(result, position) {
        const parts = extractLocation(result.address_components || []);
        selectedLocation = {
            city: parts.city,
            district: parts.district || parts.city,
            address: result.formatted_address || '',
            latitude: position.lat(),
            longitude: position.lng()
        };
        const search = modal.querySelector('[data-location-search]');
        if (search && document.activeElement !== search) {
            search.value = selectedLocation.address;
        }
        setApplyEnabled(Boolean(selectedLocation.city && selectedLocation.address));
    }

    function reverseGeocode(position) {
        const sequence = ++reverseGeocodeSequence;
        setApplyEnabled(false);
        geocoder.geocode({location: position, region: 'CY', language: 'en'}, function (results, status) {
            if (sequence !== reverseGeocodeSequence || status !== 'OK' || !results || !results.length) {
                return;
            }
            const result = results.find(function (item) {
                return item.formatted_address && !/^\w{4,}\+/.test(item.formatted_address);
            }) || results[0];
            applyGeocodeResult(result, position);
        });
    }

    function createModal() {
        modal = document.createElement('div');
        modal.className = 'location-picker-modal autoagora-sync-location-modal';
        modal.hidden = true;
        modal.innerHTML = [
            '<div class="location-picker-content" role="dialog" aria-modal="true" aria-labelledby="autoagora-sync-location-title">',
            '<div class="location-picker-header">',
            '<h2 id="autoagora-sync-location-title"></h2>',
            '<button class="close-modal" type="button" data-close-location aria-label=""></button>',
            '</div>',
            '<div class="location-picker-body">',
            '<div class="search-container"><div class="mapboxgl-ctrl-geocoder"><input type="text" data-location-search autocomplete="off"></div></div>',
            '<div class="location-map visible" data-location-map></div>',
            '</div>',
            '<div class="location-picker-footer"><button class="button button-primary" type="button" data-apply-location disabled></button></div>',
            '</div>'
        ].join('');
        modal.querySelector('h2').textContent = text('chooseLocation', 'Choose location');
        const close = modal.querySelector('[data-close-location]');
        close.textContent = '\u00d7';
        close.setAttribute('aria-label', text('close', 'Close'));
        const search = modal.querySelector('[data-location-search]');
        search.placeholder = text('searchLocation', 'Search for a location in Cyprus...');
        modal.querySelector('[data-apply-location]').textContent = text('applyLocation', 'Use this location');
        document.body.appendChild(modal);

        const mapElement = modal.querySelector('[data-location-map]');
        map = new google.maps.Map(mapElement, {
            center: {lat: Number(config.defaultLat) || 35.1856, lng: Number(config.defaultLng) || 33.3823},
            zoom: Number(config.defaultZoom) || 8,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
            gestureHandling: 'greedy'
        });
        mapElement.insertAdjacentHTML('beforeend', '<div class="autoagora-sync-center-pin" aria-hidden="true"><span></span></div>');
        geocoder = new google.maps.Geocoder();
        const autocomplete = new google.maps.places.Autocomplete(search, {
            componentRestrictions: {country: 'cy'},
            fields: ['geometry', 'formatted_address', 'address_components'],
            types: ['geocode']
        });
        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();
            if (!place.geometry || !place.geometry.location) {
                return;
            }
            map.panTo(place.geometry.location);
            map.setZoom(16);
            applyGeocodeResult(place, place.geometry.location);
        });
        map.addListener('idle', function () {
            if (!modal.hidden && activePicker) {
                reverseGeocode(map.getCenter());
            }
        });

        close.addEventListener('click', closeModal);
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        modal.querySelector('[data-apply-location]').addEventListener('click', saveLocation);
    }

    function openModal(picker) {
        if (!window.google || !google.maps || !google.maps.places) {
            window.alert(text('mapsUnavailable', 'Google Maps could not be loaded.'));
            return;
        }
        if (!modal) {
            createModal();
        }
        activePicker = picker;
        selectedLocation = storedLocation(picker);
        const center = selectedLocation
            ? {lat: selectedLocation.latitude, lng: selectedLocation.longitude}
            : {lat: Number(config.defaultLat) || 35.1856, lng: Number(config.defaultLng) || 33.3823};
        modal.querySelector('[data-location-search]').value = selectedLocation ? selectedLocation.address : '';
        modal.hidden = false;
        modal.style.display = 'flex';
        map.setCenter(center);
        map.setZoom(selectedLocation ? 16 : (Number(config.defaultZoom) || 8));
        google.maps.event.trigger(map, 'resize');
        setApplyEnabled(Boolean(selectedLocation));
        reverseGeocode(map.getCenter());
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.style.display = 'none';
        activePicker = null;
        selectedLocation = null;
        reverseGeocodeSequence += 1;
    }

    function refreshPicker(picker) {
        const location = storedLocation(picker);
        const summary = picker.querySelector('.autoagora-sync-location-summary');
        const choose = picker.querySelector('[data-choose-location]');
        const clear = picker.querySelector('[data-clear-location]');
        summary.textContent = location ? location.address : text('noLocation', 'No default location selected.');
        summary.classList.toggle('has-location', Boolean(location));
        choose.textContent = location ? text('changeLocation', 'Change location') : text('chooseLocation', 'Choose location');
        clear.hidden = !location;
    }

    function saveLocation() {
        if (!activePicker || !selectedLocation) {
            return;
        }
        const values = {
            car_city: selectedLocation.city,
            car_district: selectedLocation.district || selectedLocation.city,
            car_address: selectedLocation.address,
            car_latitude: Number(selectedLocation.latitude).toFixed(6),
            car_longitude: Number(selectedLocation.longitude).toFixed(6)
        };
        fieldNames.forEach(function (name) {
            field(activePicker, name).value = values[name];
        });
        refreshPicker(activePicker);
        closeModal();
    }

    function clearLocation(picker) {
        fieldNames.forEach(function (name) {
            field(picker, name).value = '';
        });
        refreshPicker(picker);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-location-picker]').forEach(function (picker) {
            picker.querySelector('[data-choose-location]').addEventListener('click', function () {
                openModal(picker);
            });
            picker.querySelector('[data-clear-location]').addEventListener('click', function () {
                clearLocation(picker);
            });
            refreshPicker(picker);
        });
    });
}());
