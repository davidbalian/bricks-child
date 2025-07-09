<?php
/**
 * Seller Verification Shortcode
 * Displays a verification badge for dealership users
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the seller verification shortcode
 */
function register_seller_verification_shortcode() {
    add_shortcode('dealership_verified', 'dealership_verified_shortcode');
}
add_action('init', 'register_seller_verification_shortcode');

/**
 * Shortcode function to display verification badge for dealership users
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output for verification badge or empty string
 */
function dealership_verified_shortcode($atts) {
    // Set default attributes
    $atts = shortcode_atts(array(
        'user_id' => 0,
    ), $atts, 'dealership_verified');
    
    // Validate user ID
    $user_id = intval($atts['user_id']);
    if (!$user_id) {
        return '';
    }
    
    // Get user data
    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }
    
    // Check if user has dealership role
    if (!in_array('dealership', $user->roles)) {
        return '';
    }
    
    // Return verification badge HTML
    return get_verification_badge_html();
}

/**
 * Generate the verification badge HTML
 * 
 * @return string HTML for verification badge
 */
function get_verification_badge_html() {
    ob_start();
    ?>
    <span class="seller-verification-badge">
        <svg class="verification-tick" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="verification-text">Verified Dealership</span>
    </span>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue styles for the verification badge
 */
function enqueue_seller_verification_styles() {
    global $post;
    
    // Only load when the dealership_verified shortcode is present
    if (is_singular() && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dealership_verified')) {
        wp_enqueue_style(
            'seller-verification-styles',
            get_stylesheet_directory_uri() . '/includes/shortcodes/seller-verification/seller-verification.css',
            array(),
            '1.0.0'
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_seller_verification_styles'); 