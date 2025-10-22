/**
 * Google Maps Integration
 *
 * @package Astra Child
 * @since 1.0.0
 */

// PRODUCTION SAFETY: Only log in development environments
window.isDevelopment = window.isDevelopment || (
    window.location.hostname === 'localhost' ||
    window.location.hostname.includes('staging') ||
    window.location.search.includes('debug=true')
);

(function($) {
    'use strict';

    $(document).ready(function() {
        // Safety check
        if (typeof google === 'undefined' || !google.maps) {
            if (isDevelopment) console.error('Google Maps JS API not loaded');
            return;
        }

        // Map options (from localized PHP data)
        const mapOptions = {
            center: { lat: parseFloat(mapConfig.defaultLat), lng: parseFloat(mapConfig.defaultLng) },
            zoom: parseInt(mapConfig.zoom),
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true
        };

        // Initialize map
        const map = new google.maps.Map(document.getElementById('car-location-map'), mapOptions);

        // Add marker variable
        let marker = null;

        // Load car location from ACF fields if available
        const latitude = parseFloat($('#acf-field_car_latitude').val());
        const longitude = parseFloat($('#acf-field_car_longitude').val());

        if (!isNaN(latitude) && !isNaN(longitude)) {
            const carPosition = { lat: latitude, lng: longitude };
            marker = new google.maps.Marker({
                position: carPosition,
                map: map
            });

            map.setCenter(carPosition);
            map.setZoom(15);

            if (isDevelopment) console.log('Marker added at', carPosition);
        }

        // Optional: Add map controls (zoom, pan)
        const zoomControlDiv = document.createElement('div');
        map.controls[google.maps.ControlPosition.RIGHT_TOP].push(zoomControlDiv);

        // Optional logging for debugging
        if (isDevelopment) {
            console.log('Google Map initialized');
        }
    });

})(jQuery);
