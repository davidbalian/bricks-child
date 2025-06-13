<?php
/**
 * My Account AJAX Handlers
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add AJAX handler for updating user name
add_action('wp_ajax_update_user_name', 'handle_update_user_name');
function handle_update_user_name() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_user_name')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Get and sanitize input
    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

    // Get current user
    $user_id = get_current_user_id();

    // Update user meta - if we got here, the client detected changes
    update_user_meta($user_id, 'first_name', $first_name);
    update_user_meta($user_id, 'last_name', $last_name);

    // Since we've validated the user and nonce, and the client only sends when there are changes,
    // we can assume the update was successful
    wp_send_json_success('Name updated successfully');
}

// Add AJAX handler for initiating password reset
add_action('wp_ajax_initiate_password_reset', 'handle_initiate_password_reset');
function handle_initiate_password_reset() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'password_reset_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Get current user
    $user_id = get_current_user_id();
    $phone_number = get_user_meta($user_id, 'phone_number', true);

    if (empty($phone_number)) {
        wp_send_json_error('No phone number found for your account');
        return;
    }

    // Use the existing Twilio configuration and logic
    $twilio_sid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        error_log('Twilio Verify configuration is missing.');
        wp_send_json_error('SMS configuration error. Please contact admin.');
        return;
    }

    $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

    try {
        $verification = $twilio->verify->v2->services($twilio_verify_sid)
            ->verifications
            ->create($phone_number, "sms");

        error_log("Password reset verification started: SID " . $verification->sid);
        
        // Store the verification session for this user
        set_transient('password_reset_' . $user_id, array(
            'phone' => $phone_number,
            'verification_sid' => $verification->sid,
            'timestamp' => time()
        ), 300); // 5 minutes expiry

        wp_send_json_success('Verification code sent successfully');
    } catch (Exception $e) {
        error_log('Twilio Verify error: ' . $e->getMessage());
        wp_send_json_error('Failed to send verification code. Please try again later.');
    }
}

// Add AJAX handler for verifying password reset code
add_action('wp_ajax_verify_password_reset_code', 'handle_verify_password_reset_code');
function handle_verify_password_reset_code() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'verify_password_reset_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    if (empty($code) || strlen($code) !== 6) {
        wp_send_json_error('Invalid verification code format');
        return;
    }

    // Get current user and verification session
    $user_id = get_current_user_id();
    $reset_session = get_transient('password_reset_' . $user_id);

    if (!$reset_session) {
        wp_send_json_error('Verification session expired. Please start over.');
        return;
    }

    // Use Twilio to verify the code
    $twilio_sid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        wp_send_json_error('SMS configuration error. Please contact admin.');
        return;
    }

    $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

    try {
        $verification_check = $twilio->verify->v2->services($twilio_verify_sid)
            ->verificationChecks
            ->create([
                'to' => $reset_session['phone'],
                'code' => $code
            ]);

        if ($verification_check->status === 'approved') {
            // Store verified session for password update
            set_transient('password_reset_verified_' . $user_id, array(
                'verified' => true,
                'timestamp' => time()
            ), 600); // 10 minutes to complete password reset
            
            // Clean up the verification session
            delete_transient('password_reset_' . $user_id);
            
            wp_send_json_success('Code verified successfully');
        } else {
            wp_send_json_error('Invalid verification code');
        }
    } catch (Exception $e) {
        error_log('Twilio verification check error: ' . $e->getMessage());
        wp_send_json_error('Error verifying code. Please try again.');
    }
}

// Add AJAX handler for updating password
add_action('wp_ajax_update_password_reset', 'handle_update_password_reset');
function handle_update_password_reset() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_password_reset_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Get current user
    $user_id = get_current_user_id();
    
    // Check if user has verified session
    $verified_session = get_transient('password_reset_verified_' . $user_id);
    
    if (!$verified_session || !$verified_session['verified']) {
        wp_send_json_error('Session expired. Please start over.');
        return;
    }

    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    
    // Apply the same strict validation as registration
    if (empty($new_password)) {
        wp_send_json_error('Password is required');
        return;
    }
    
    // Password Length Check (8-16 characters)
    if (strlen($new_password) < 8 || strlen($new_password) > 16) {
        wp_send_json_error('Password must be between 8 and 16 characters long');
        return;
    }

    // Password Complexity Checks
    if (!preg_match('/[a-z]/', $new_password)) {
        wp_send_json_error('Password must contain at least one lowercase letter');
        return;
    }
    
    if (!preg_match('/[A-Z]/', $new_password)) {
        wp_send_json_error('Password must contain at least one uppercase letter');
        return;
    }
    
    if (!preg_match('/[0-9]/', $new_password)) {
        wp_send_json_error('Password must contain at least one number');
        return;
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>\-_=+;\[\]~`]/', $new_password)) {
        wp_send_json_error('Password must contain at least one symbol (e.g., !@#$%^&*)');
        return;
    }

    // Update the user's password
    $user_data = array(
        'ID' => $user_id,
        'user_pass' => $new_password
    );

    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        wp_send_json_error('Failed to update password: ' . $result->get_error_message());
        return;
    }

    // Clean up the verified session
    delete_transient('password_reset_verified_' . $user_id);
    
    // Log the password change
    error_log("Password reset completed for user ID: $user_id");

    wp_send_json_success('Password updated successfully');
}

// Add AJAX handler for sending email verification
add_action('wp_ajax_send_email_verification', 'handle_send_email_verification');
function handle_send_email_verification() {
    // Log the start of the function
    error_log('Email verification AJAX called');
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'email_verification_nonce')) {
        error_log('Email verification: Invalid nonce');
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        error_log('Email verification: User not logged in');
        wp_send_json_error('User not logged in');
        return;
    }

    // Get current user
    $user_id = get_current_user_id();
    error_log('Email verification: User ID: ' . $user_id);

    // Get and validate email
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    error_log('Email verification: Email provided: ' . $email);
    
    if (empty($email) || !is_email($email)) {
        error_log('Email verification: Invalid email format');
        wp_send_json_error('Please enter a valid email address');
        return;
    }

    // Check if email is already in use by another user
    $existing_user = get_user_by('email', $email);
    if ($existing_user && $existing_user->ID !== $user_id) {
        error_log('Email verification: Email already in use by user ID: ' . $existing_user->ID);
        wp_send_json_error('This email address is already in use by another account');
        return;
    }

    // Get current user's email to check if it's a change
    $current_user = get_user_by('ID', $user_id);
    $current_email = $current_user->user_email;
    $is_email_change = ($email !== $current_email);
    
    error_log('Email verification: Current email: ' . $current_email . ', New email: ' . $email . ', Is change: ' . ($is_email_change ? 'YES' : 'NO'));
    
    // If it's an email change, update the email immediately and reset verification status
    if ($is_email_change) {
        // Update user email in database
        $user_update_result = wp_update_user(array(
            'ID' => $user_id,
            'user_email' => $email
        ));
        
        if (is_wp_error($user_update_result)) {
            error_log('Email verification: Failed to update user email: ' . $user_update_result->get_error_message());
            wp_send_json_error('Failed to update email address');
            return;
        }
        
        // Reset email verification status to unverified
        update_user_meta($user_id, 'email_verified', '0');
        error_log('Email verification: Email updated and verification status reset to 0 for user ' . $user_id);
    }

    // Rate limiting - check if user sent an email recently
    $last_email_time = get_transient('email_verification_last_sent_' . $user_id);
    if ($last_email_time && (time() - $last_email_time) < 60) {
        $wait_time = 60 - (time() - $last_email_time);
        error_log('Email verification: Rate limit hit for user ' . $user_id . ', wait time: ' . $wait_time . ' seconds');
        wp_send_json_error('Please wait ' . $wait_time . ' seconds before sending another verification email');
        return;
    }

    // Set rate limit before sending
    set_transient('email_verification_last_sent_' . $user_id, time(), 300);

    // Send verification email
    error_log('Email verification: Attempting to send email to: ' . $email);
    $result = send_verification_email($user_id, $email);
    error_log('Email verification: Send result: ' . ($result ? 'SUCCESS' : 'FAILED'));

    if ($result) {
        wp_send_json_success('Verification email sent successfully! Please check your inbox and click the verification link.');
    } else {
        // Remove rate limit if email failed to send
        delete_transient('email_verification_last_sent_' . $user_id);
        wp_send_json_error('Failed to send verification email. Please try again later.');
    }
} 