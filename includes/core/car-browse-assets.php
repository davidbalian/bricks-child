<?php
/**
 * Lighter assets on /cars/, car_make landings, and cars filter routes (Bricks + custom body).
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Enqueue deferred Google Maps loader for car_make / test template (API loads on first Location open).
 */
function autoagora_enqueue_car_browse_maps_loader() {
	if (!defined('GOOGLE_MAPS_API_KEY') || !GOOGLE_MAPS_API_KEY) {
		return;
	}
	$load = is_tax('car_make') || is_page_template('template-test-cars.php');
	if (!$load) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/js/car-browse-google-maps-loader.js';
	$url  = get_stylesheet_directory_uri() . '/assets/js/car-browse-google-maps-loader.js';
	if (!file_exists($path)) {
		return;
	}

	wp_enqueue_script(
		'autoagora-car-browse-maps-loader',
		$url,
		array(),
		filemtime($path),
		true
	);

	$script_url = add_query_arg(
		array(
			'key'       => GOOGLE_MAPS_API_KEY,
			'libraries' => 'places',
			'language'  => 'en',
			'callback'  => 'autoagoraOnMapsApiLoaded',
		),
		'https://maps.googleapis.com/maps/api/js'
	);

	wp_localize_script(
		'autoagora-car-browse-maps-loader',
		'autoagoraCarBrowseMapsConfig',
		array(
			'scriptUrl' => $script_url,
		)
	);
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_car_browse_maps_loader', 25);

/**
 * Drop plugin CSS not used by the custom listing templates on these routes.
 */
function autoagora_dequeue_heavy_assets_on_cars_browse() {
	if (!function_exists('autoagora_is_cars_browse_light_context') || !autoagora_is_cars_browse_light_context()) {
		return;
	}

	$handles = apply_filters(
		'autoagora_cars_browse_dequeue_styles',
		array(
			'jet-engine-frontend',
		)
	);

	foreach ((array) $handles as $handle) {
		wp_dequeue_style($handle);
	}
}
add_action('wp_enqueue_scripts', 'autoagora_dequeue_heavy_assets_on_cars_browse', 99);
