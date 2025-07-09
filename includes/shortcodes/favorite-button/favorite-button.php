<?php
/**
 * Shared Favorite Button Component
 * Can be used in listings, single car page, or anywhere else
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Register the shortcode
add_shortcode( 'favorite_button', 'favorite_button_shortcode' );

/**
 * Favorite Button Shortcode Function
 */
function favorite_button_shortcode( $atts ) {
    // Parse shortcode attributes
    $atts = shortcode_atts( array(
        'car_id' => null,
        'design' => 'default', // 'default', 'listing', 'single'
        'size' => 'normal', // 'small', 'normal', 'large'
    ), $atts );

    // Get car ID - prioritize attribute, then URL parameter, then current post ID
    if ($atts['car_id']) {
        $car_id = intval($atts['car_id']);
    } elseif (isset($_GET['car_id'])) {
        $car_id = intval($_GET['car_id']);
    } else {
        $car_id = get_the_ID();
    }

    if ( ! $car_id || get_post_type( $car_id ) !== 'car' ) {
        return '<!-- Favorite Button: Not available for this post type -->';
    }

    // Core favorite button logic (EXTRACTED from single-car-buttons.php)
    $user_id = get_current_user_id();
    $favorite_cars = $user_id ? get_user_meta($user_id, 'favorite_cars', true) : array();
    $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
    $is_favorite = $user_id ? in_array($car_id, $favorite_cars) : false;
    
    // Build CSS classes based on design parameter
    $base_class = 'favorite-btn';
    $design_class = 'favorite-btn-' . esc_attr($atts['design']);
    $size_class = 'favorite-btn-' . esc_attr($atts['size']);
    $active_class = $is_favorite ? 'active' : '';
    
    $button_class = trim($base_class . ' ' . $design_class . ' ' . $size_class . ' ' . $active_class);
    $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';

    ob_start();
    ?>
    <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($car_id); ?>" title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
        <i class="<?php echo esc_attr($heart_class); ?>"></i>
    </button>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue the shared favorite button scripts and styles
 */
function enqueue_favorite_button_assets() {
    // Load on ALL pages (matches favourites-button CSS loading pattern)
    
    // Enqueue the JavaScript
    wp_enqueue_script(
        'favorite-button-js',
        get_stylesheet_directory_uri() . '/includes/shortcodes/favorite-button/favorite-button.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    // Note: CSS is loaded in main enqueue file unconditionally
    
    // Localize script with AJAX data
    wp_localize_script('favorite-button-js', 'favoriteButtonData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('toggle_favorite_car'),
        'is_user_logged_in' => is_user_logged_in(),
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_favorite_button_assets'); 