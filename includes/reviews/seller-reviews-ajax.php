<?php
/**
 * Seller Reviews AJAX Handlers Registration
 * 
 * Registers AJAX actions for the seller review system.
 * Following the distributed AJAX pattern used in newer features.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the handler functions
require_once get_stylesheet_directory() . '/includes/reviews/seller-reviews-handler.php';

// Register AJAX handlers for seller review submission (logged-in users only)
add_action('wp_ajax_submit_seller_review', 'handle_submit_seller_review');

// Register AJAX handlers for admin review management (logged-in users only)
add_action('wp_ajax_approve_seller_review', 'handle_admin_approve_review');
add_action('wp_ajax_reject_seller_review', 'handle_admin_reject_review'); 