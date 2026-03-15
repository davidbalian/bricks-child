<?php
/**
 * Homepage Filters Shortcode
 * Now delegates to the [car_filters] shortcode in redirect mode
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the shortcode
 */
add_shortcode('homepage_filters', 'homepage_filters_shortcode');

/**
 * Main shortcode function — renders car_filters in redirect mode
 */
function homepage_filters_shortcode($atts) {
    return do_shortcode('[car_filters filters="make,model,price,mileage" mode="redirect" redirect_url="/cars/" layout="horizontal" show_button="true" button_text="Search Cars" group="homepage"]');
}
