/**
 * Mapbox Integration
 * 
 * @package Astra Child
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize map when document is ready
    $(document).ready(function() {
        if (typeof mapboxgl === 'undefined') {
            console.error('Mapbox GL JS is not loaded');
            return;
        }

        // Set the access token
        mapboxgl.accessToken = mapboxConfig.accessToken;

        // Initialize the map
        const map = new mapboxgl.Map({
            container: 'car-location-map',
            style: mapboxConfig.styleUrl,
            center: [mapboxConfig.defaultCenter.lng, mapboxConfig.defaultCenter.lat],
            zoom: mapboxConfig.defaultZoom
        });

        // Add navigation controls
        map.addControl(new mapboxgl.NavigationControl(), 'top-right');

        // Add marker when map loads
        map.on('load', function() {
            // Get car location from ACF fields
            const latitude = $('#acf-field_car_latitude').val();
            const longitude = $('#acf-field_car_longitude').val();

            if (latitude && longitude) {
                // Add marker at car location
                new mapboxgl.Marker()
                    .setLngLat([longitude, latitude])
                    .addTo(map);

                // Center map on car location
                map.flyTo({
                    center: [longitude, latitude],
                    zoom: 15
                });
            }
        });
    });

})(jQuery); 