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
    
    // Get the car ID (from URL parameter or shortcode attribute)
    $car_id = 0;
    
    // First try URL parameter (primary method)
    if (isset($_GET['car_id']) && !empty($_GET['car_id'])) {
        $car_id = intval($_GET['car_id']);
    }
    
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
            
            return '<span class="car-views-counter full-format">Views: ' . $total_text . ' (' . $unique_text . ')</span>';
    }
}

// Register the shortcode
add_shortcode('car_views_counter', 'car_views_counter_shortcode');

/**
 * Add basic CSS for the views counter
 */
function car_views_counter_styles() {
    if (is_singular('car')) {
        echo '<style>
            .car-views-counter {
                display: inline-block;
                font-size: 14px;
                color: #666;
                margin: 5px 0;
            }
            
            .car-views-counter.full-format {
                background: #f8f9fa;
                padding: 5px 10px;
                border-radius: 4px;
                border-left: 3px solid #007cba;
            }
            
            .car-views-error {
                color: #d63638;
                font-size: 12px;
                font-style: italic;
            }
            
            @media (max-width: 768px) {
                .car-views-counter {
                    font-size: 12px;
                }
            }
        </style>';
    }
}
add_action('wp_head', 'car_views_counter_styles');

/**
 * Helper function to get formatted view count (for use in PHP)
 * 
 * @param int $car_id The car post ID
 * @param string $format The format type
 * @return string Formatted view count
 */
function get_car_view_count_formatted($car_id, $format = 'simple') {
    return do_shortcode('[car_views_counter car_id="' . intval($car_id) . '" format="' . esc_attr($format) . '" show_icon="false"]');
} 