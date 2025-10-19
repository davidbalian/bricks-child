<?php
/**
 * Car Single Call Button Shortcode
 * 
 * Provides [car_single_call_button] shortcode to display call button.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Car Single Call Button Shortcode Handler
 * 
 * Usage: [car_single_call_button]
 * 
 * @param array $atts Shortcode attributes
 * @return string The call button HTML
 */
function car_single_call_button_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(), $atts, 'car_single_call_button');
    
    // Start output buffering
    ob_start();
    
    $post_author_id = get_the_author_meta('ID');
    $user_object = get_user_by('ID', $post_author_id);
    $post_id = get_the_ID(); // Get current post ID for tracking

    if ($user_object) {
        $author_username = $user_object->user_login;
        $tel_link_number = preg_replace('/[^0-9+]/', '', $author_username);
        $tel_link_number = preg_replace('/^(.{3})(.+)/', '$1 $2', $tel_link_number);
        $tel_link = 'tel:+' . $tel_link_number;
        $button_display_text = '+' . $tel_link_number;
        ?>
        <a href="<?php echo esc_attr($tel_link); ?>" 
           class="brx-button car-call-button" 
           id="single-car-call-button" 
           data-post-id="<?php echo esc_attr($post_id); ?>"
           data-nonce="<?php echo wp_create_nonce('car_call_button_click'); ?>"
           style="
            padding: .75rem 1.5rem;
            background-color: var(--bricks-color-iztoge);
            border-radius: var(--radius-sm);
            display: flex;
            justify-content: center;
            align-items: center;
            column-gap: .5rem;
            text-decoration: none;
            color: #ffffff;
        ">
            <i class="fas fa-phone" style="color: #ffffff; font-size: 1rem;"></i>
            <span style="color: #ffffff;"><?php echo esc_html($button_display_text); ?></span>
        </a>
        <?php
    }
    
    // Return the buffered content
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('car_single_call_button', 'car_single_call_button_shortcode');

/**
 * AJAX handler for tracking call button clicks
 */
function handle_car_call_button_click() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'car_call_button_click')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Get and validate post ID
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if (!$post_id || get_post_type($post_id) !== 'car') {
        wp_send_json_error('Invalid post ID');
        return;
    }
    
    // Get current click count
    $current_clicks = get_field('call_button_clicks', $post_id);
    $current_clicks = $current_clicks ? intval($current_clicks) : 0;
    
    // Increment the count
    $new_count = $current_clicks + 1;
    
    // Update the ACF field
    $updated = update_field('call_button_clicks', $new_count, $post_id);
    
    if ($updated) {
        wp_send_json_success(array(
            'message' => 'Click tracked successfully',
            'new_count' => $new_count
        ));
    } else {
        wp_send_json_error('Failed to update click count');
    }
}

// Register AJAX handlers
add_action('wp_ajax_car_call_button_click', 'handle_car_call_button_click');
add_action('wp_ajax_nopriv_car_call_button_click', 'handle_car_call_button_click');

/**
 * Enqueue JavaScript for call button click tracking
 */
function car_call_button_enqueue_scripts() {
    if (is_singular('car')) {
        // Enqueue jQuery (if not already enqueued)
        wp_enqueue_script('jquery');
        
        // Add inline script for click tracking
        $script = "
        jQuery(document).ready(function($) {
            $('.car-call-button').on('click', function(e) {
                var button = $(this);
                var postId = button.data('post-id');
                var nonce = button.data('nonce');
                
                // Track the click via AJAX
                $.ajax({
                    url: '" . admin_url('admin-ajax.php') . "',
                    type: 'POST',
                    data: {
                        action: 'car_call_button_click',
                        post_id: postId,
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log('Call button click tracked:', response);
                    },
                    error: function(xhr, status, error) {
                        console.log('Error tracking call button click:', error);
                    }
                });
                
                // Continue with the normal link behavior (making the call)
                // The click tracking happens in the background
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
}
add_action('wp_enqueue_scripts', 'car_call_button_enqueue_scripts');


