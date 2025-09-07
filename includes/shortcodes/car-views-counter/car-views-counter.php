<?php
/**
 * Car Views Counter Shortcode
 * 
 * Provides [car_views_counter] shortcode to display view counts.
 * Shows both total views and unique visitors.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Helper function to extract car ID from URL slug for shortcode
 * Expected format: /car/carname-####/
 * Example: /car/2020-bmw-3-series-10137/ returns 10137
 * 
 * @return int|false Car ID or false if not found
 */
function extract_car_id_from_url_shortcode() {
    // Get the current URL path
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Remove query string if present
    $url_path = strtok($request_uri, '?');
    
    // Remove trailing slash
    $url_path = rtrim($url_path, '/');
    
    // Extract the slug after /car/
    if (preg_match('/\/car\/(.+)$/', $url_path, $matches)) {
        $slug = $matches[1];
        
        // Extract the number after the last dash
        if (preg_match('/-(\d+)$/', $slug, $id_matches)) {
            return intval($id_matches[1]);
        }
    }
    
    return false;
}

/**
 * Car Views Counter Shortcode Handler
 * 
 * Usage: [car_views_counter]
 * Output: "Views: 123 (32 unique visitors)"
 * 
 * @param array $atts Shortcode attributes
 * @return string The formatted view count
 */
function car_views_counter_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'car_id' => get_the_ID(), // Default to current post ID
        'format' => 'full', // 'full', 'total', 'unique'
        'show_zero' => 'yes', // Show even if 0 views
    ), $atts, 'car_views_counter');
    
    // Get the car ID (from URL slug or shortcode attribute)
    $car_id = 0;
    
    // First try extracting from URL slug (primary method)
    $car_id = extract_car_id_from_url_shortcode();
    
    // Fallback to shortcode attribute
    if (!$car_id && $atts['car_id']) {
        $car_id = intval($atts['car_id']);
    }
    
    // Validate car ID
    if (!$car_id || get_post_type($car_id) !== 'car') {
        return '<span class="car-views-error">Invalid car ID</span>';
    }
    
    // Get the views tracker instance
    global $car_views_tracker;
    if (!$car_views_tracker) {
        return '<span class="car-views-error">Views tracker not available</span>';
    }
    
    // Get view counts
    $database = new CarViewsDatabase();
    $counts = $database->get_view_counts($car_id);
    
    $total_views = $counts['total'];
    $unique_views = $counts['unique'];
    
    // Handle zero views display
    if ($total_views == 0 && $unique_views == 0 && $atts['show_zero'] !== 'yes') {
        return '';
    }
    
    // Format output based on format attribute
    switch ($atts['format']) {
        case 'total':
            return '<span class="car-views-counter total-only">' . $total_views . ' view' . ($total_views != 1 ? 's' : '') . '</span>';
            
        case 'unique':
            return '<span class="car-views-counter unique-only">' . $unique_views . ' unique visitor' . ($unique_views != 1 ? 's' : '') . '</span>';
            
        case 'full':
        default:
            $total_text = $total_views . ' view' . ($total_views != 1 ? 's' : '');
            $unique_text = $unique_views . ' unique visitor' . ($unique_views != 1 ? 's' : '');
            
            return '<span class="car-views-counter full-format">' . $total_text . ' (' . $unique_text . ')</span>';
    }
}

// Register the shortcode
add_shortcode('car_views_counter', 'car_views_counter_shortcode');

/**
 * Car Views Counter Single Shortcode Handler
 * 
 * Usage: [car_views_counter_single]
 * Output: "123 views" (shows only total views, no unique visitors)
 * 
 * This is a simple wrapper that calls the main shortcode with format="total"
 * 
 * @param array $atts Shortcode attributes
 * @return string The formatted view count (total views only)
 */
function car_views_counter_single_shortcode($atts) {
    // Add format="total" to the attributes and call the main shortcode
    $atts['format'] = 'total';
    return car_views_counter_shortcode($atts);
}

// Register the single shortcode
add_shortcode('car_views_counter_single', 'car_views_counter_single_shortcode');

/**
 * Enqueue CSS for car views counter
 */
function car_views_counter_enqueue_styles() {
    if (is_singular('car')) {
        // Removed CSS enqueue for car-views-counter.css (file does not exist)
    }
}
add_action('wp_enqueue_scripts', 'car_views_counter_enqueue_styles'); 