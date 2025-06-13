<?php
/**
 * Template Name: Edit Car Listing
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// Get the car ID from the URL parameter
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;

if (!$car_id) {
    wp_redirect(home_url('/my-listings/'));
    exit;
}

// Get the car post
$car = get_post($car_id);

// Check if the car exists and belongs to the current user
if (!$car || $car->post_type !== 'car' || $car->post_author != get_current_user_id()) {
    wp_redirect(home_url('/my-listings/'));
    exit;
}

// Get all car details
$make = get_field('make', $car_id);
$model = get_field('model', $car_id);
$variant = get_field('variant', $car_id);

// Get the makes data structure
$add_listing_makes = [];
$add_listing_data = get_field('add_listing_data', 'option');
if ($add_listing_data) {
    foreach ($add_listing_data as $make_name => $models) {
        $add_listing_makes[$make_name] = $models;
    }
    ksort($add_listing_makes);
}

// Get other car details
$year = get_field('year', $car_id);
$price = get_field('price', $car_id);
$mileage = get_field('mileage', $car_id);
$location = get_field('car_address', $car_id);
$engine_capacity = get_field('engine_capacity', $car_id);
$fuel_type = get_field('fuel_type', $car_id);
$transmission = get_field('transmission', $car_id);
$body_type = get_field('body_type', $car_id);
$drive_type = get_field('drive_type', $car_id);
$exterior_color = get_field('exterior_color', $car_id);
$interior_color = get_field('interior_color', $car_id);
$description = get_field('description', $car_id);
$number_of_doors = get_field('number_of_doors', $car_id);
$number_of_seats = get_field('number_of_seats', $car_id);
$hp = get_field('hp', $car_id);
$mot_status = get_field('motuntil', $car_id);
$num_owners = get_field('numowners', $car_id);
$is_antique = get_field('isantique', $car_id);

// Get vehicle history and extras
$vehicle_history = get_field('vehiclehistory', $car_id);
$extras = get_field('extras', $car_id);

// Ensure vehicle_history and extras are arrays and properly formatted
if (!is_array($vehicle_history)) {
    if (!empty($vehicle_history)) {
        $vehicle_history = array($vehicle_history);
    } else {
        $vehicle_history = array();
    }
}

// Convert any serialized data to arrays
if (is_serialized($vehicle_history)) {
    $vehicle_history = maybe_unserialize($vehicle_history);
}

// Ensure we have arrays
if (!is_array($vehicle_history)) {
    $vehicle_history = array();
}
if (!is_array($extras)) {
    $extras = array();
    if (!empty($extras)) {
        $extras = array($extras);
    }
}

// Convert any serialized data to arrays
if (is_serialized($extras)) {
    $extras = maybe_unserialize($extras);
}

// Ensure we have arrays
if (!is_array($extras)) {
    $extras = array();
}

// Get all car images
$featured_image = get_post_thumbnail_id($car_id);
$additional_images = get_field('car_images', $car_id);
$all_images = array();

if ($featured_image) {
    $all_images[] = $featured_image;
}

if (is_array($additional_images)) {
    $all_images = array_merge($all_images, $additional_images);
}

get_header();

// Enqueue assets
wp_enqueue_style('edit-listing-style', get_stylesheet_directory_uri() . '/includes/user-manage-listings/template-edit-listing/edit-listing.css', array(), '1.0.1');
wp_enqueue_script('jquery');

// Enqueue image optimization script (shared with add listing)
wp_enqueue_script(
    'astra-child-image-optimization',
    get_stylesheet_directory_uri() . '/includes/user-manage-listings/image-optimization.js',
    array('jquery'),
    filemtime(get_stylesheet_directory() . '/includes/user-manage-listings/image-optimization.js'),
    true
);

wp_enqueue_script('edit-listing-script', get_stylesheet_directory_uri() . '/includes/user-manage-listings/template-edit-listing/edit-listing.js', array('jquery', 'astra-child-image-optimization'), '1.0.0', true);

// Localize the script with necessary data
wp_localize_script('edit-listing-script', 'editListingData', array(
    'makesData' => $add_listing_makes,
    'selectedMake' => esc_js($make),
    'selectedModel' => esc_js($model),
    'selectedVariant' => esc_js($variant),
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('edit_car_listing_nonce')
));

// Include the separated display file
include get_stylesheet_directory() . '/includes/user-manage-listings/template-edit-listing/edit-listing-display.php';

get_footer(); 