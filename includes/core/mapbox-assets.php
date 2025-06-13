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
 * Enqueue Mapbox assets
 */
function autoagora_enqueue_mapbox_assets() {
    // Debug: Check if we're on the right page
    error_log('Current page: ' . get_post_type());
    error_log('Is singular: ' . (is_singular() ? 'yes' : 'no'));
    error_log('Is page: ' . (is_page() ? 'yes' : 'no'));

    // Debug: Check Mapbox token
    error_log('Mapbox Token: ' . (defined('MAPBOX_ACCESS_TOKEN') ? 'defined' : 'not defined'));
    if (defined('MAPBOX_ACCESS_TOKEN')) {
        error_log('Token length: ' . strlen(MAPBOX_ACCESS_TOKEN));
    }

    // Only load on single car pages or add-listing page
    if (is_singular('car') || is_page('add-listing') || is_page('edit-listing')) {
        // Enqueue Mapbox GL JS
        wp_enqueue_style(
            'mapbox-gl-css',
            'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css',
            array(),
            '2.15.0'
        );

        wp_enqueue_script(
            'mapbox-gl-js',
            'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js',
            array(),
            '2.15.0',
            true
        );

        // Enqueue Mapbox Geocoder
        wp_enqueue_style(
            'mapbox-geocoder-css',
            'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css',
            array('mapbox-gl-css'),
            '5.0.0'
        );

        wp_enqueue_script(
            'mapbox-geocoder-js',
            'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js',
            array('mapbox-gl-js'),
            '5.0.0',
            true
        );

        // Enqueue location picker assets
        wp_enqueue_style(
            'location-picker-css',
            get_stylesheet_directory_uri() . '/assets/css/location-picker.css',
            array('mapbox-gl-css', 'mapbox-geocoder-css'),
            filemtime(get_stylesheet_directory() . '/assets/css/location-picker.css')
        );

        wp_enqueue_script(
            'location-picker-js',
            get_stylesheet_directory_uri() . '/assets/js/location-picker.js',
            array('mapbox-gl-js', 'mapbox-geocoder-js'),
            filemtime(get_stylesheet_directory() . '/assets/js/location-picker.js'),
            true
        );

        // Localize the script with Mapbox configuration
        wp_localize_script('location-picker-js', 'mapboxConfig', array(
            'accessToken' => MAPBOX_ACCESS_TOKEN,
            'style' => 'mapbox://styles/mapbox/streets-v12',
            'defaultZoom' => 8,
            'center' => [33.3823, 35.1856] // Cyprus center
        ));
    }
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_mapbox_assets'); 