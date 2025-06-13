<?php
/**
 * Email Verification Initialization
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Set default email_verified = '0' for new users during registration
 * Hook into the same user creation process used by registration
 */
function set_default_email_verified_for_new_user($user_id) {
    // Set email_verified to '0' for all new users
    update_user_meta($user_id, 'email_verified', '0');
}
add_action('user_register', 'set_default_email_verified_for_new_user'); 