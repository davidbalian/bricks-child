<?php
/**
 * Seller Reviews Submission Handler
 * 
 * Handles AJAX form submissions for seller reviews.
 * Validates users, prevents duplicate reviews, and integrates with
 * existing phone verification system.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Check if user can leave reviews
 * Requires both login and verified email
 * 
 * @param int $user_id The user ID to check
 * @return bool Whether user can leave reviews
 */
function can_user_leave_review($user_id) {
    // User must be logged in
    if (!$user_id || !is_user_logged_in()) {
        return false;
    }
    
    // User must have verified email
    $email_verified = get_user_meta($user_id, 'email_verified', true);
    return $email_verified === '1';
}



/**
 * AJAX handler for seller review submission
 * Follows existing AJAX patterns in core/ajax.php
 */
function handle_submit_seller_review() {
    // Verify nonce for security
    if (!isset($_POST['seller_review_nonce']) || !wp_verify_nonce($_POST['seller_review_nonce'], 'submit_seller_review_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in to leave a review'));
        return;
    }
    
    $reviewer_id = get_current_user_id();
    
    // Check if user can leave reviews
    if (!can_user_leave_review($reviewer_id)) {
        wp_send_json_error(array('message' => 'You must be logged in to leave reviews'));
        return;
    }
    
    // Get and validate form data
    $seller_id = isset($_POST['seller_id']) ? intval($_POST['seller_id']) : 0;
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
    $contacted_seller = isset($_POST['contacted_seller']) && $_POST['contacted_seller'] == '1';
    
    // Validate required fields
    if (!$seller_id || !$rating) {
        wp_send_json_error(array('message' => 'Seller and rating are required'));
        return;
    }
    
    // Validate rating range
    if ($rating < 1 || $rating > 5) {
        wp_send_json_error(array('message' => 'Rating must be between 1 and 5 stars'));
        return;
    }
    
    // Validate comment length (140 characters like design spec)
    if (strlen($comment) > 140) {
        wp_send_json_error(array('message' => 'Comment must be 140 characters or less'));
        return;
    }
    
    // Check if seller exists and is not the reviewer
    $seller = get_userdata($seller_id);
    if (!$seller) {
        wp_send_json_error(array('message' => 'Seller not found'));
        return;
    }
    
    if ($seller_id == $reviewer_id) {
        wp_send_json_error(array('message' => 'You cannot review yourself'));
        return;
    }
    
    // Get database instance
    global $seller_reviews_database;
    if (!$seller_reviews_database) {
        // Fallback if global not set
        $seller_reviews_database = new SellerReviewsDatabase();
    }
    
    // Submit the review
    $result = $seller_reviews_database->submit_review(
        $seller_id,
        $reviewer_id,
        $rating,
        $comment,
        $contacted_seller
    );
    
    if ($result['success']) {
        wp_send_json_success(array(
            'message' => $result['message'],
            'data' => array(
                'seller_id' => $seller_id,
                'rating' => $rating,
                'status' => 'pending'
            )
        ));
    } else {
        wp_send_json_error(array('message' => $result['message']));
    }
}



/**
 * AJAX handler for admin review approval
 * For use in admin interface
 */
function handle_admin_approve_review() {
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'admin_review_action_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    
    if (!$review_id) {
        wp_send_json_error(array('message' => 'Review ID required'));
        return;
    }
    
    // Get database instance
    global $seller_reviews_database;
    if (!$seller_reviews_database) {
        $seller_reviews_database = new SellerReviewsDatabase();
    }
    
    $success = $seller_reviews_database->approve_review($review_id);
    
    if ($success) {
        wp_send_json_success(array('message' => 'Review approved successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to approve review'));
    }
}

/**
 * AJAX handler for admin review rejection
 * For use in admin interface
 */
function handle_admin_reject_review() {
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'admin_review_action_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    
    if (!$review_id) {
        wp_send_json_error(array('message' => 'Review ID required'));
        return;
    }
    
    // Get database instance
    global $seller_reviews_database;
    if (!$seller_reviews_database) {
        $seller_reviews_database = new SellerReviewsDatabase();
    }
    
    $success = $seller_reviews_database->reject_review($review_id);
    
    if ($success) {
        wp_send_json_success(array('message' => 'Review rejected successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to reject review'));
    }
}

/**
 * AJAX handler for resetting review to pending status
 * For use in admin interface
 */
function handle_admin_reset_review_to_pending() {
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'admin_review_action_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    
    if (!$review_id) {
        wp_send_json_error(array('message' => 'Review ID required'));
        return;
    }
    
    // Get database instance
    global $seller_reviews_database;
    if (!$seller_reviews_database) {
        $seller_reviews_database = new SellerReviewsDatabase();
    }
    
    $success = $seller_reviews_database->reset_review_to_pending($review_id);
    
    if ($success) {
        wp_send_json_success(array('message' => 'Review reset to pending successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to reset review to pending'));
    }
}

/**
 * AJAX handler for deleting a review
 * For use in admin interface
 */
function handle_admin_delete_review() {
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'admin_review_action_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    
    if (!$review_id) {
        wp_send_json_error(array('message' => 'Review ID required'));
        return;
    }
    
    // Get database instance
    global $seller_reviews_database;
    if (!$seller_reviews_database) {
        $seller_reviews_database = new SellerReviewsDatabase();
    }
    
    $success = $seller_reviews_database->delete_review($review_id);
    
    if ($success) {
        wp_send_json_success(array('message' => 'Review deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete review'));
    }
}

/**
 * Initialize global database instance
 * Follows pattern of other systems in the codebase
 */
function init_seller_reviews_global() {
    global $seller_reviews_database;
    if (!$seller_reviews_database) {
        $seller_reviews_database = new SellerReviewsDatabase();
    }
}

// Initialize the global instance
add_action('init', 'init_seller_reviews_global'); 