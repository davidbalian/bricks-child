<?php
/**
 * Google Maps Assets
 *
 * @package AutoAgora Child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue Google Maps API and location picker scripts
 */
function autoagora_enqueue_google_maps_assets() {
    if (is_page('add-listing') || is_page('edit-listing')) {

        // ✅ Google Maps with Places Library
        $google_maps_url = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode(GOOGLE_MAPS_API_KEY) . '&libraries=places';
        wp_enqueue_script('google-maps', $google_maps_url, [], null, true);

        // ✅ Location Picker CSS
        wp_enqueue_style(
            'autoagora-location-picker',
            get_stylesheet_directory_uri() . '/assets/css/location-picker.css',
            [],
            filemtime(get_stylesheet_directory() . '/assets/css/location-picker.css')
        );

        // ✅ Location Picker JS
        wp_enqueue_script(
            'autoagora-location-picker',
            get_stylesheet_directory_uri() . '/assets/js/location-picker.js',
            ['jquery', 'google-maps'],
            filemtime(get_stylesheet_directory() . '/assets/js/location-picker.js'),
            true
        );

        // ✅ Localize Configuration
        wp_localize_script('autoagora-location-picker', 'mapConfig', [
            'defaultLat' => 35.1856,
            'defaultLng' => 33.3823,
            'zoom' => 8,
            'debug' => (strpos($_SERVER['HTTP_HOST'], 'staging') !== false || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false)
        ]);
    }
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_google_maps_assets');
