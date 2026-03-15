<?php
/**
 * Dealership Access Message Shortcode
 * Displays a message if the dealership page has autoagora_dealer field set to 0/false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the dealership access message shortcode
 */
function register_dealership_access_message_shortcode() {
    add_shortcode('dealership_access_message', 'dealership_access_message_shortcode');
}
add_action('init', 'register_dealership_access_message_shortcode');

/**
 * Shortcode function to display access message for unclaimed dealerships
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output for access message or empty string
 */
function dealership_access_message_shortcode($atts) {
    // Set default attributes
    $atts = shortcode_atts(array(
        'post_id' => 0,
    ), $atts, 'dealership_access_message');
    
    // Get post ID - use provided post_id or current post
    $post_id = intval($atts['post_id']);
    
    // If no post_id provided, try to get from current context
    if (!$post_id) {
        // First try: get from global post (works on single pages)
        $post_id = get_the_ID();
        
        // Second try: get from query var (works in some loop contexts)
        if (!$post_id) {
            global $wp_query;
            if (isset($wp_query->queried_object_id) && $wp_query->queried_object_id) {
                $post_id = $wp_query->queried_object_id;
            }
        }
        
        // Third try: get from global post object directly
        if (!$post_id) {
            global $post;
            if (isset($post->ID) && $post->ID) {
                $post_id = $post->ID;
            }
        }
    }
    
    // Validate post ID
    if (!$post_id) {
        return '';
    }
    
    // Get the ACF field value
    $autoagora_dealer = get_field('autoagora_dealer', $post_id);
    
    // Check if field is 0 or false
    // ACF returns false for unchecked checkboxes, 0 for number fields, or empty for other types
    if ($autoagora_dealer === 0 || $autoagora_dealer === false || $autoagora_dealer === '0' || empty($autoagora_dealer)) {
        return get_dealership_access_message_html();
    }
    
    // Field is set to true/1, so don't show the message
    return '';
}

/**
 * Generate the dealership access message HTML
 * 
 * @return string HTML for access message
 */
function get_dealership_access_message_html() {
    ob_start();
    ?>
    <div class="dealership-access-message">
        <p class="dealership-access-message-text">
            ℹ️ Does this dealership belong to you?
        </p>
        <p class="dealership-access-message-description">
            Contact us and gain full access to manage your inventory and dealership profile.
        </p>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue styles for the dealership access message
 */
function enqueue_dealership_access_message_styles() {
    global $post;
    
    // Only load when the dealership_access_message shortcode is present
    if (is_singular() && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dealership_access_message')) {
        wp_enqueue_style(
            'dealership-access-message-styles',
            get_stylesheet_directory_uri() . '/includes/shortcodes/dealership-access-message/dealership-access-message.css',
            array(),
            '1.0.0'
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_dealership_access_message_styles');

