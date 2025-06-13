<?php
/**
 * Favorite Listings Shortcode - Controller
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Enqueue separated CSS and JS files
function enqueue_favourite_listings_assets() {
    // Only load on specific contexts - be more restrictive to avoid conflicts
    $should_load = false;
    
    // Check if favourite_listings shortcode is being used on current page (for both logged in and logged out users)
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'favourite_listings')) {
        $should_load = true;
    }
    
    // Also load on my-account page for logged-in users
    if (is_user_logged_in() && strpos($_SERVER['REQUEST_URI'], 'my-account') !== false) {
        $should_load = true;
    }
    
    if ($should_load) {
        // Enqueue Favourite Listings CSS
        wp_enqueue_style(
            'favourite-listings-css',
            get_stylesheet_directory_uri() . '/includes/shortcodes/favourite-listings/favourite-listings.css',
            array(),
            '1.0.0'
        );

        // Enqueue Favourite Listings JS
        wp_enqueue_script(
            'favourite-listings-js',
            get_stylesheet_directory_uri() . '/includes/shortcodes/favourite-listings/favourite-listings.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Use a different object name to avoid conflicts with car-listings page
        wp_localize_script('favourite-listings-js', 'favouriteListingsData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('toggle_favorite_car')
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_favourite_listings_assets');

// Register the shortcode
add_shortcode('favourite_listings', 'display_favourite_listings');

function display_favourite_listings($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'per_page' => 12,
        'orderby' => 'date',
        'order' => 'DESC'
    ), $atts);

    // Include the separated display file
    ob_start();
    include get_stylesheet_directory() . '/includes/shortcodes/favourite-listings/favourite-listings-display.php';
    return ob_get_clean();
}
?>