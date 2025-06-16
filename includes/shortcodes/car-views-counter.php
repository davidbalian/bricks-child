<?php
/**
 * Car Views Counter Shortcode
 * 
 * Displays the view count for car listings via [car_views_counter] shortcode.
 * Can be used in Bricks builder or anywhere shortcodes are supported.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Car Views Counter Shortcode Function
 * 
 * Usage examples:
 * [car_views_counter] - Basic usage, auto-detects car ID
 * [car_views_counter car_id="123"] - Specific car ID
 * [car_views_counter format="detailed"] - Detailed format
 * [car_views_counter show_icon="false"] - Hide the eye icon
 * [car_views_counter class="custom-class"] - Add custom CSS class
 * 
 * @param array $atts Shortcode attributes
 * @return string The formatted view counter HTML
 */
function car_views_counter_shortcode($atts) {
    // Default attributes
    $atts = shortcode_atts(array(
        'car_id'    => 0,           // Car post ID (0 = auto-detect)
        'format'    => 'simple',    // Display format: 'simple', 'detailed', 'number'
        'show_icon' => 'true',      // Show eye icon: 'true' or 'false'
        'class'     => '',          // Additional CSS classes
        'text'      => '',          // Custom text format (use {count} placeholder)
    ), $atts, 'car_views_counter');
    
    // Get car ID - ONLY from car_id parameter or URL parameter
    $car_id = intval($atts['car_id']);
    
    // If no car_id provided in shortcode, try URL parameter ONLY
    if (!$car_id && isset($_GET['car_id'])) {
        $car_id = intval($_GET['car_id']);
    }
    
    // NO OTHER AUTO-DETECTION - Must be explicit
    
    // Validate car ID
    if (!$car_id || get_post_type($car_id) !== 'car') {
        return '<!-- Car Views Counter: Invalid or missing car ID -->';
    }
    
    // Get the views tracker instance
    global $car_views_tracker;
    if (!$car_views_tracker) {
        return '<!-- Car Views Counter: Tracker not initialized -->';
    }
    
    // Get view count
    $view_count = $car_views_tracker->get_view_count($car_id);
    
    // Handle zero views
    if ($view_count === 0) {
        $view_count = 0; // Ensure it's explicitly 0
    }
    
    // Prepare output
    ob_start();
    
    // Build CSS classes
    $css_classes = array('car-views-counter');
    if (!empty($atts['class'])) {
        $css_classes[] = esc_attr($atts['class']);
    }
    $css_classes[] = 'format-' . esc_attr($atts['format']);
    
    // Start output
    echo '<div class="' . implode(' ', $css_classes) . '" data-car-id="' . esc_attr($car_id) . '">';
    
    // Show icon if enabled
    $show_icon = ($atts['show_icon'] === 'true');
    if ($show_icon) {
        echo '<i class="fas fa-eye car-views-icon" aria-hidden="true"></i>';
    }
    
    // Format the display text
    $display_text = '';
    
    if (!empty($atts['text'])) {
        // Custom text format
        $display_text = str_replace('{count}', number_format($view_count), $atts['text']);
    } else {
        // Predefined formats
        switch ($atts['format']) {
            case 'detailed':
                if ($view_count === 0) {
                    $display_text = 'No views yet';
                } elseif ($view_count === 1) {
                    $display_text = 'Viewed by 1 unique visitor';
                } else {
                    $display_text = 'Viewed by ' . number_format($view_count) . ' unique visitors';
                }
                break;
                
            case 'number':
                $display_text = number_format($view_count);
                break;
                
            case 'simple':
            default:
                if ($view_count === 0) {
                    $display_text = '0 views';
                } elseif ($view_count === 1) {
                    $display_text = '1 view';
                } else {
                    $display_text = number_format($view_count) . ' views';
                }
                break;
        }
    }
    
    echo '<span class="car-views-text">' . esc_html($display_text) . '</span>';
    echo '</div>';
    
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('car_views_counter', 'car_views_counter_shortcode');

/**
 * Add some basic CSS for the views counter
 * This will be loaded when the shortcode is used
 */
function car_views_counter_styles() {
    static $styles_loaded = false;
    
    if (!$styles_loaded) {
        echo '<style>
.car-views-counter {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
    color: #666;
    margin: 5px 0;
}

.car-views-counter .car-views-icon {
    font-size: 14px;
    opacity: 0.7;
}

.car-views-counter.format-detailed {
    font-style: italic;
}

.car-views-counter.format-number {
    font-weight: bold;
    font-size: 16px;
}

.car-views-counter.format-number .car-views-icon {
    font-size: 16px;
}

/* Integration with car listing styles */
.car-listing-details .car-views-counter,
.car-listing-header .car-views-counter {
    color: inherit;
    font-size: inherit;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .car-views-counter {
        font-size: 13px;
    }
    
    .car-views-counter .car-views-icon {
        font-size: 13px;
    }
}
</style>';
        $styles_loaded = true;
    }
}

/**
 * Auto-load styles when shortcode is detected in content
 */
function maybe_load_car_views_styles() {
    global $post;
    
    if ($post && (
        has_shortcode($post->post_content, 'car_views_counter') ||
        strpos($post->post_content, 'car_views_counter') !== false
    )) {
        add_action('wp_head', 'car_views_counter_styles');
    }
}
add_action('wp', 'maybe_load_car_views_styles');

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