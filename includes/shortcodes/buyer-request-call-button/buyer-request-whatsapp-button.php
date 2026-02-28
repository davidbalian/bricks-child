<?php
/**
 * Buyer Request WhatsApp Button Click Tracking
 * 
 * Tracks WhatsApp button clicks for buyer request posts.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * AJAX handler for tracking WhatsApp button clicks
 */
function handle_buyer_request_whatsapp_button_click() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'buyer_request_whatsapp_button_click')) {
        wp_send_json_error('Security check failed');
        return;
    }

    // Get and validate post ID
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id || get_post_type($post_id) !== 'buyer_request') {
        wp_send_json_error('Invalid post ID');
        return;
    }

    // Get current click count
    $current_clicks = get_field('buyer_whatsapp_number_click', $post_id);
    $current_clicks = $current_clicks ? intval($current_clicks) : 0;

    // Increment the count
    $new_count = $current_clicks + 1;

    // Update the ACF field
    $updated = update_field('buyer_whatsapp_number_click', $new_count, $post_id);

    if ($updated) {
        wp_send_json_success(array(
            'message' => 'WhatsApp click tracked successfully',
            'new_count' => $new_count
        ));
    } else {
        wp_send_json_error('Failed to update WhatsApp click count');
    }
}

// Register AJAX handlers
add_action('wp_ajax_buyer_request_whatsapp_button_click', 'handle_buyer_request_whatsapp_button_click');
add_action('wp_ajax_nopriv_buyer_request_whatsapp_button_click', 'handle_buyer_request_whatsapp_button_click');

/**
 * Enqueue JavaScript for WhatsApp button click tracking
 */
function buyer_request_whatsapp_button_enqueue_scripts() {
    if (is_singular('buyer_request')) {
        // Enqueue jQuery (if not already enqueued)
        wp_enqueue_script('jquery');
        
        // Add inline script for click tracking
        $script = "
        jQuery(document).ready(function($) {

        // --- Define environment flag ---
        window.isDevelopment = window.isDevelopment || (
        window.location.hostname === 'localhost' ||
        window.location.hostname.includes('staging') ||
        window.location.search.includes('debug=true')
        );

            $('.buyer-request-whatsapp-button').on('click', function(e) {
                var button = $(this);
                var postId = button.data('post-id');
                var nonce = button.data('nonce');
                var waLink = button.attr('href'); // get the WhatsApp link
                
                if (!postId || !nonce) {
                    return; // Skip if data attributes are missing
                }
                
                // Track the click via AJAX
                $.ajax({
                    url: '" . admin_url('admin-ajax.php') . "',
                    type: 'POST',
                    data: {
                        action: 'buyer_request_whatsapp_button_click',
                        post_id: postId,
                        nonce: nonce
                    },
                    success: function(response) {
                    if (window.isDevelopment)
                        console.log('Buyer request WhatsApp button click tracked:', response);
                    },
                    error: function(xhr, status, error) {
                    if (window.isDevelopment)
                        console.error('Error tracking buyer request WhatsApp button click:', error);
                    }
                });
                // --- Handle open behavior ---
                var isMobile = /iPhone|iPad|iPod|Android|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

                if (!isMobile) {
                    e.preventDefault(); // prevent default same-tab behavior
                    window.open(waLink, '_blank'); // open in new tab on desktop
                }
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
}
add_action('wp_enqueue_scripts', 'buyer_request_whatsapp_button_enqueue_scripts');

