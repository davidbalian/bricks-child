<?php
/**
 * Email Verification Notification System
 * 
 * Shows notification under header for unverified emails
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show email verification notification under header
 */
function show_email_verification_notification() {
    error_log('show_email_verification_notification function called');
    
    // Only for logged-in users
    if (!is_user_logged_in()) {
        error_log('User not logged in');
        return;
    }
    
    $user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    
    // Check if email is verified
    $email_verified = get_user_meta($user_id, 'email_verified', true);
    error_log('Email verified status: ' . $email_verified);
    
    if ($email_verified === '1') {
        error_log('Email already verified');
        return; // Email already verified, no notification needed
    }
    
    // Check if notification was dismissed for this session
    if (isset($_SESSION['email_notification_dismissed']) && $_SESSION['email_notification_dismissed'] === true) {
        error_log('Notification dismissed in session');
        return;
    }
    
    // Get user email
    $user_email = $current_user->user_email;
    
    // Skip if user has placeholder email (phone_user_)
    if (strpos($user_email, 'phone_user_') === 0) {
        error_log('User has placeholder email');
        return;
    }
    
    error_log('Loading notification template');
    // Load the notification template
    include get_stylesheet_directory() . '/includes/notifications/email-verification-notification-template.php';
}

/**
 * Show email verification notification in footer with fixed positioning
 * (fallback for themes that don't support wp_body_open)
 */
function show_email_verification_notification_footer() {
    // Only show in footer if wp_body_open didn't work (avoid duplicates)
    static $shown = false;
    if ($shown) return;
    
    echo '<div id="email-verification-notification-footer" style="position: fixed; top: 0; left: 0; right: 0; z-index: 9999;">';
    show_email_verification_notification();
    echo '</div>';
    
    $shown = true;
}

/**
 * Handle notification dismissal via AJAX
 */
function handle_dismiss_email_notification() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dismiss_email_notification')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session flag to dismiss notification
    $_SESSION['email_notification_dismissed'] = true;
    
    wp_send_json_success('Notification dismissed');
}

// Hook to show notification before site wrapper
add_action('bricks_before_site_wrapper', 'show_email_verification_notification');

// Add AJAX handler for dismissing notification
add_action('wp_ajax_dismiss_email_notification', 'handle_dismiss_email_notification');

// Enqueue notification scripts and styles
function enqueue_email_notification_assets() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $email_verified = get_user_meta($user_id, 'email_verified', true);
    
    // Only enqueue if email is not verified
    if ($email_verified !== '1') {
        wp_enqueue_style(
            'email-notification-style',
            get_stylesheet_directory_uri() . '/includes/notifications/email-notification.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'email-notification-script',
            get_stylesheet_directory_uri() . '/includes/notifications/email-notification.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('email-notification-script', 'EmailNotificationAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'email_verification_nonce' => wp_create_nonce('email_verification_nonce'),
            'dismiss_notification_nonce' => wp_create_nonce('dismiss_email_notification')
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_email_notification_assets');

// Start session if needed
function start_email_notification_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}
add_action('init', 'start_email_notification_session'); 