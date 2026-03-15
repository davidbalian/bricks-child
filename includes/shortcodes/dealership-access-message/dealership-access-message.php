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
    
    // Get the user ID from the post author
    $user_id = (int) get_post_field('post_author', $post_id);
    
    // If no author, try to get from current user context
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Validate user ID
    if (!$user_id) {
        return '';
    }
    
    // Get the ACF field value from the USER (not the post)
    // ACF user fields use format: 'user_' . $user_id
    $autoagora_dealer = get_field('autoagora_dealer', 'user_' . $user_id);
    
    // Fallback: try direct user meta
    if ($autoagora_dealer === null || $autoagora_dealer === '' || $autoagora_dealer === false) {
        $autoagora_dealer = get_user_meta($user_id, 'autoagora_dealer', true);
    }
    
    // Get all user meta keys that contain "autoagora" for debugging
    $all_user_meta = get_user_meta($user_id);
    $autoagora_meta = array();
    foreach ($all_user_meta as $key => $value) {
        if (strpos($key, 'autoagora') !== false) {
            $autoagora_meta[$key] = $value;
        }
    }
    
    // Also check for ACF field object to see field settings
    $field_object = get_field_object('autoagora_dealer', 'user_' . $user_id);
    
    // Convert to a comparable value - normalize to integer for comparison
    $dealer_value = null;
    if ($autoagora_dealer !== null && $autoagora_dealer !== '' && $autoagora_dealer !== false) {
        // Convert to integer: 1, '1', true all become 1
        $dealer_value = intval($autoagora_dealer);
    }
    
    // TEMPORARY DEBUG - Remove this block after testing
    // This will show visible debug info on the page
    $debug_output = '<div style="background: #ffeb3b; padding: 10px; margin: 10px 0; border: 2px solid #f57f17;">';
    $debug_output .= '<strong>DEBUG INFO:</strong><br>';
    $debug_output .= 'Post ID: ' . esc_html($post_id) . '<br>';
    $debug_output .= 'User ID: ' . esc_html($user_id) . '<br>';
    $debug_output .= 'ACF get_field(user): ' . var_export($autoagora_dealer, true) . ' (type: ' . gettype($autoagora_dealer) . ')<br>';
    $debug_output .= 'Direct get_user_meta(): ' . var_export(get_user_meta($user_id, 'autoagora_dealer', true), true) . '<br>';
    $debug_output .= 'ACF Field Object exists: ' . ($field_object ? 'YES' : 'NO') . '<br>';
    if ($field_object) {
        $debug_output .= 'Field Object name: ' . esc_html($field_object['name'] ?? 'N/A') . '<br>';
        $debug_output .= 'Field Object key: ' . esc_html($field_object['key'] ?? 'N/A') . '<br>';
        $debug_output .= 'Field Object value: ' . var_export($field_object['value'] ?? 'N/A', true) . '<br>';
    }
    $debug_output .= '<strong>All autoagora user meta keys:</strong><br>';
    if (empty($autoagora_meta)) {
        $debug_output .= '&nbsp;&nbsp;<em>No user meta keys found containing "autoagora"</em><br>';
    } else {
        foreach ($autoagora_meta as $key => $value) {
            $debug_output .= '&nbsp;&nbsp;' . esc_html($key) . ' = ' . var_export($value, true) . '<br>';
        }
    }
    $debug_output .= 'Dealer Value (intval): ' . var_export($dealer_value, true) . '<br>';
    $debug_output .= 'Will show message: ' . ($dealer_value === 1 ? 'NO' : 'YES') . '<br>';
    $debug_output .= '</div>';
    
    // Only show message if value is NOT 1 (i.e., it's 0, null, empty, false, or not set)
    // If dealer_value is 1, the dealer is claimed - don't show message
    if ($dealer_value === 1) {
        return $debug_output; // Show debug when claimed (should not show message)
    }
    
    // Dealer is NOT claimed (value is 0, null, empty, false, or not set), so show the message
    return $debug_output . get_dealership_access_message_html();
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

