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
    $is_listing_form = is_page_template('template-add-listing.php') || is_page_template('template-edit-listing.php');

    if ($is_listing_form) {

        // ✅ Google Maps with Places Library
        $google_maps_url = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode(GOOGLE_MAPS_API_KEY) . '&libraries=places&language=' . rawurlencode(autoagora_current_language());
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
			'language' => function_exists('autoagora_current_language') ? autoagora_current_language() : 'en',
			'i18n' => [
				'findCurrentLocation' => __('Find my current location', 'bricks-child'),
				'geolocationUnsupported' => __('Geolocation is not supported by your browser.', 'bricks-child'),
				'geolocationError' => __('Error getting location: {message}', 'bricks-child'),
				'chooseLocation' => __('Choose Location', 'bricks-child'),
				'continue' => __('Continue', 'bricks-child'),
				'searchLocation' => __('Search for a location in Cyprus...', 'bricks-child'),
				'close' => __('Close', 'bricks-child'),
			],
			'debug' => (strpos($_SERVER['HTTP_HOST'], 'staging') !== false || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false)
        ]);
    }
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_google_maps_assets');
