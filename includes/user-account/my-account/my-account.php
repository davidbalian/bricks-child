<?php
/**
 * My Account Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Include separated files
require_once get_stylesheet_directory() . '/includes/user-account/my-account/my-account-display.php';
require_once get_stylesheet_directory() . '/includes/user-account/my-account/password-reset.php';
require_once get_stylesheet_directory() . '/includes/user-account/my-account/my-account-ajax.php';

// Register the shortcode
add_shortcode('my_account', 'display_my_account');

//this is proof that github is working

function display_my_account($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your account information.</p>';
    }

    // Enqueue separated CSS and JS files
    wp_enqueue_style('my-account-display-css', get_stylesheet_directory_uri() . '/includes/user-account/my-account/my-account-display.css', array(), '1.0.0');
    wp_enqueue_script('my-account-display-js', get_stylesheet_directory_uri() . '/includes/user-account/my-account/my-account-display.js', array(), '1.0.0', true);
    
    // Localize script with AJAX data
    wp_localize_script('my-account-display-js', 'MyAccountAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'update_user_name_nonce' => wp_create_nonce('update_user_name'),
        'password_reset_nonce' => wp_create_nonce('password_reset_nonce'),
        'email_verification_nonce' => wp_create_nonce('email_verification_nonce')
    ));

    // Check if we're in password reset flow
    $password_reset_step = isset($_GET['password_reset_step']) ? sanitize_text_field($_GET['password_reset_step']) : '';
    
    // Enqueue password reset files if in password reset flow
    if ($password_reset_step) {
        wp_enqueue_style('password-reset-css', get_stylesheet_directory_uri() . '/includes/user-account/my-account/password-reset.css', array(), '1.0.0');
        wp_enqueue_script('password-reset-js', get_stylesheet_directory_uri() . '/includes/user-account/my-account/password-reset.js', array(), '1.0.0', true);
        
        // Localize password reset script with AJAX data
        wp_localize_script('password-reset-js', 'PasswordResetAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'password_reset_nonce' => wp_create_nonce('password_reset_nonce'),
            'verify_password_reset_nonce' => wp_create_nonce('verify_password_reset_nonce'),
            'update_password_reset_nonce' => wp_create_nonce('update_password_reset_nonce')
        ));
    }
    
    if ($password_reset_step === 'verify') {
        return display_password_reset_verify();
    } elseif ($password_reset_step === 'new_password') {
        return display_password_reset_form();
    } elseif ($password_reset_step === 'success') {
        return display_password_reset_success();
    }

    // Normal account display - use the separated function
    $current_user = wp_get_current_user();
    return display_my_account_main($current_user);
}