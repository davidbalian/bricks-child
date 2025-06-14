<?php
/**
 * The template for displaying single Car posts
 *
 * @package Astra Child
 * @since 1.0.0
 */

// Enqueue separated CSS and JS files
function enqueue_single_car_assets() {
    if (is_singular('car')) {
        // Enqueue Single Car Display CSS
        wp_enqueue_style(
            'single-car-display-css',
            get_stylesheet_directory_uri() . '/includes/single-car/single-car-display.css',
            array(),
            '1.0.0'
        );

        // Enqueue Single Car Gallery CSS
        wp_enqueue_style(
            'single-car-gallery-css',
            get_stylesheet_directory_uri() . '/includes/single-car/single-car-gallery.css',
            array(),
            '1.0.0'
        );

        // Enqueue Single Car Display JS
        wp_enqueue_script(
            'single-car-display-js',
            get_stylesheet_directory_uri() . '/includes/single-car/single-car-display.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Enqueue Single Car Gallery JS
        wp_enqueue_script(
            'single-car-gallery-js',
            get_stylesheet_directory_uri() . '/includes/single-car/single-car-gallery.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script for AJAX
        wp_localize_script('single-car-display-js', 'carListingsData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('toggle_favorite_car'),
            'report_nonce' => wp_create_nonce('report_listing_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_single_car_assets');

get_header(); // Ensure Astra's header is loaded

// Include the separated display file
include get_stylesheet_directory() . '/includes/single-car/single-car-display.php';

get_footer(); // Ensure Astra's footer is loaded
?> 