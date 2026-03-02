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
    // Only allow POST requests
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        wp_die( esc_html__( 'Invalid request method.', 'bricks-child' ), 400 );
    }

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
    
    // Sanitize form data (matching car-submission.php format)
    $make = sanitize_text_field($_POST['buyer_make']);
    $model = isset($_POST['buyer_model']) && !empty(trim($_POST['buyer_model'])) ? sanitize_text_field($_POST['buyer_model']) : '';
    $year = intval($_POST['buyer_year']);
    // Price: handle comma-separated values like "10,000" -> 10000 (same as car submission)
    $price = intval(str_replace(',', '', $_POST['buyer_price']));
    $description = isset($_POST['buyer_description']) ? wp_kses_post($_POST['buyer_description']) : '';
    
    // Validate year range (matching car-submission.php: 1948 to 2025)
    if ($year < 1948 || $year > 2025) {
        $redirect_url = add_query_arg(
            array(
                'error' => 'validation',
                'message' => urlencode(sprintf(__('Year must be between 1948 and 2025. Got: %s', 'bricks-child'), $year))
            ),
            wp_get_referer()
        );
        wp_redirect($redirect_url);
        exit;
    }
    
    // Validate price (must be positive)
    if ($price <= 0) {
        $redirect_url = add_query_arg(
            array(
                'error' => 'validation',
                'message' => urlencode(__('Price must be a positive number.', 'bricks-child'))
            ),
            wp_get_referer()
        );
        wp_redirect($redirect_url);
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
        'post_title'   => $post_title,
        'post_content' => '',
        'post_status'  => 'publish', // Buyer requests are published immediately
        'post_type'    => 'buyer_request',
        'post_author'  => get_current_user_id(),
    );
    
    // Insert the post
    $post_id = wp_insert_post( $post_data );
    
    // Check if post creation was successful
    if ( is_wp_error( $post_id ) ) {
        wp_redirect(add_query_arg('error', 'post_creation', wp_get_referer()));
        exit;
    }

    // Ensure the permalink is unique and stable by appending the post ID to the slug,
    // e.g. /buyer_request/looking-for-2025-acura-cl-up-to-e12-312-1234/
    // This prevents collisions when two buyer requests have the same title.
    $current_post = get_post( $post_id );
    if ( $current_post && ! empty( $current_post->post_name ) ) {
        $slug_with_id = sanitize_title( $current_post->post_name . '-' . $post_id );
        // Update the post_name only (no need to change anything else)
        wp_update_post(
            array(
                'ID'        => $post_id,
                'post_name' => $slug_with_id,
            )
        );
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

/**
 * Handle deletion of an existing buyer request.
 *
 * Security:
 * - Only accepts POST requests.
 * - Requires valid nonce.
 * - Requires logged-in user.
 * - Buyer request must belong to the current user.
 */
function handle_delete_buyer_request() {
    // Only allow POST requests
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        wp_die( esc_html__( 'Invalid request method.', 'bricks-child' ), 400 );
    }

    // Verify nonce
    if (
        ! isset( $_POST['delete_buyer_request_nonce'] )
        || ! wp_verify_nonce( $_POST['delete_buyer_request_nonce'], 'delete_buyer_request_nonce' )
    ) {
        wp_die( esc_html__( 'Security check failed.', 'bricks-child' ), 403 );
    }

    // Require logged-in user
    if ( ! is_user_logged_in() ) {
        wp_die( esc_html__( 'You must be logged in to delete a buyer request.', 'bricks-child' ), 403 );
    }

    // Validate buyer request ID
    $post_id = isset( $_POST['buyer_request_id'] ) ? intval( $_POST['buyer_request_id'] ) : 0;
    if ( ! $post_id || get_post_type( $post_id ) !== 'buyer_request' ) {
        wp_die( esc_html__( 'Invalid buyer request.', 'bricks-child' ), 400 );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_die( esc_html__( 'Buyer request not found.', 'bricks-child' ), 404 );
    }

    $current_user_id = get_current_user_id();
    if ( intval( $post->post_author ) !== $current_user_id ) {
        wp_die( esc_html__( 'You are not allowed to delete this buyer request.', 'bricks-child' ), 403 );
    }

    // Move the post to trash (safer than permanent delete)
    wp_trash_post( $post_id );

    // Redirect to buyer requests index with a success flag
    $buyer_requests_page = get_page_by_path( 'buyer-requests' );
    if ( $buyer_requests_page ) {
        $redirect_url = get_permalink( $buyer_requests_page->ID );
    } else {
        $redirect_url = home_url( '/buyer-requests' );
    }

    $redirect_url = add_query_arg( 'buyer_request_deleted', 'success', $redirect_url );

    wp_safe_redirect( $redirect_url );
    exit;
}

// Add hook for handling form submissions (authenticated users only)
add_action('admin_post_add_new_buyer_request', 'handle_add_buyer_request');
// Add hook for handling deletions (authenticated users only)
add_action('admin_post_delete_buyer_request', 'handle_delete_buyer_request');

