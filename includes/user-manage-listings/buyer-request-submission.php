<?php
/**
 * Buyer Request Submission Functionality
 * 
 * Handles form submissions for buyer requests
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Process form submission for adding a new buyer request
 */
function handle_add_buyer_request() {
    // Verify nonce
    if (!isset($_POST['add_buyer_request_nonce']) || !wp_verify_nonce($_POST['add_buyer_request_nonce'], 'add_buyer_request_nonce')) {
        wp_redirect(add_query_arg('error', 'nonce_failed', wp_get_referer()));
        exit;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_redirect(add_query_arg('error', 'not_logged_in', wp_get_referer()));
        exit;
    }
    
    // Required fields (make, year, price are mandatory)
    $required_fields = array(
        'buyer_make' => 'Make',
        'buyer_year' => 'Year',
        'buyer_price' => 'Price',
    );
    
    // Check for required fields
    $missing_fields = array();
    foreach ($required_fields as $field_key => $field_label) {
        if (!isset($_POST[$field_key]) || empty(trim($_POST[$field_key]))) {
            $missing_fields[] = $field_key;
        }
    }
    
    if (!empty($missing_fields)) {
        $redirect_url = add_query_arg(
            array(
                'error' => 'validation',
                'fields' => implode(',', $missing_fields)
            ),
            wp_get_referer()
        );
        wp_redirect($redirect_url);
        exit;
    }
    
    // Sanitize form data
    $make = sanitize_text_field($_POST['buyer_make']);
    $model = isset($_POST['buyer_model']) ? sanitize_text_field($_POST['buyer_model']) : '';
    $year = intval($_POST['buyer_year']);
    $price = intval($_POST['buyer_price']);
    $description = isset($_POST['buyer_description']) ? wp_kses_post($_POST['buyer_description']) : '';
    
    // Validate year range
    $current_year = (int) date('Y');
    if ($year < 1900 || $year > ($current_year + 1)) {
        wp_redirect(add_query_arg('error', 'invalid_year', wp_get_referer()));
        exit;
    }
    
    // Validate price
    if ($price <= 0) {
        wp_redirect(add_query_arg('error', 'invalid_price', wp_get_referer()));
        exit;
    }
    
    // Prepare post title
    $post_title = 'Looking for ' . $year . ' ' . $make;
    if (!empty($model)) {
        $post_title .= ' ' . $model;
    }
    $post_title .= ' - Up to €' . number_format($price, 0, ',', '.');
    
    // Create the post
    $post_data = array(
        'post_title' => $post_title,
        'post_content' => '',
        'post_status' => 'publish', // Buyer requests are published immediately
        'post_type' => 'buyer_request',
        'post_author' => get_current_user_id(),
    );
    
    // Insert the post
    $post_id = wp_insert_post($post_data);
    
    // Check if post creation was successful
    if (is_wp_error($post_id)) {
        wp_redirect(add_query_arg('error', 'post_creation', wp_get_referer()));
        exit;
    }
    
    // Add post meta for all the buyer request details
    update_field('buyer_make', $make, $post_id);
    if (!empty($model)) {
        update_field('buyer_model', $model, $post_id);
    }
    update_field('buyer_year', $year, $post_id);
    update_field('buyer_price', $price, $post_id);
    if (!empty($description)) {
        update_field('buyer_description', $description, $post_id);
    }
    
    // Redirect to success page (buyer requests listing page)
    $buyer_requests_page = get_page_by_path('buyer-requests');
    if ($buyer_requests_page) {
        wp_redirect(add_query_arg('request_submitted', 'success', get_permalink($buyer_requests_page->ID)));
    } else {
        wp_redirect(add_query_arg('request_submitted', 'success', home_url('/buyer-requests')));
    }
    exit;
}

// Add hooks for handling form submissions
add_action('admin_post_add_new_buyer_request', 'handle_add_buyer_request');
add_action('admin_post_nopriv_add_new_buyer_request', 'handle_add_buyer_request');

