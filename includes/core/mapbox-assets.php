<?php
/**
 * Mapbox Assets
 *
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue Google Maps assets for add/edit listing (replacing Mapbox)
 */
function autoagora_enqueue_mapbox_assets() {
    // Debug: Check if we're on the right page
    error_log('Current page: ' . get_post_type());
    error_log('Is singular: ' . (is_singular() ? 'yes' : 'no'));
    error_log('Is page: ' . (is_page() ? 'yes' : 'no'));

    // Debug: Check Google Maps key
    error_log('Google Maps Key: ' . (defined('GOOGLE_MAPS_API_KEY') ? 'defined' : 'not defined'));

    // Only load on add-listing and edit-listing pages
    if (is_page('add-listing') || is_page('edit-listing')) {
        // Enqueue Google Maps JS (Places library)
        $google_maps_url = 'https://maps.googleapis.com/maps/api/js?libraries=places';
        if ( defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY ) {
            $google_maps_url .= '&key=' . urlencode(GOOGLE_MAPS_API_KEY);
        }
        wp_enqueue_script(
            'google-maps-js',
            $google_maps_url,
            array(),
            null,
            true
        );

        // Enqueue location picker assets
        wp_enqueue_style(
            'location-picker-css',
            get_stylesheet_directory_uri() . '/assets/css/location-picker.css',
            array(),
            filemtime(get_stylesheet_directory() . '/assets/css/location-picker.css')
        );

        wp_enqueue_script(
            'location-picker-js',
            get_stylesheet_directory_uri() . '/assets/js/location-picker.js',
            array('google-maps-js'),
            filemtime(get_stylesheet_directory() . '/assets/js/location-picker.js'),
            true
        );

        // Localize the script with map configuration (keep keys for backward compatibility)
        wp_localize_script('location-picker-js', 'mapboxConfig', array(
            'accessToken' => defined('MAPBOX_ACCESS_TOKEN') ? MAPBOX_ACCESS_TOKEN : '',
            'style' => 'mapbox://styles/mapbox/streets-v12',
            'defaultZoom' => 8,
            'center' => [33.3823, 35.1856] // Cyprus center
        ));
    }
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_mapbox_assets'); 