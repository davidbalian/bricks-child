<?php
/**
 * Forgot Password AJAX Handlers
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add AJAX handlers for both logged-in and non-logged-in users
add_action('wp_ajax_send_forgot_password_otp', 'handle_send_forgot_password_otp');
add_action('wp_ajax_nopriv_send_forgot_password_otp', 'handle_send_forgot_password_otp');

add_action('wp_ajax_verify_forgot_password_otp', 'handle_verify_forgot_password_otp');
add_action('wp_ajax_nopriv_verify_forgot_password_otp', 'handle_verify_forgot_password_otp');

add_action('wp_ajax_update_forgot_password', 'handle_update_forgot_password');
add_action('wp_ajax_nopriv_update_forgot_password', 'handle_update_forgot_password');

/**
 * Handle sending OTP for forgot password
 */
function handle_send_forgot_password_otp() {
    // Security check: Prevent logged-in users from using forgot password
    if (is_user_logged_in()) {
        wp_send_json_error('Access denied: Already logged in');
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'send_forgot_password_otp_nonce')) {
        wp_send_json_error('Invalid security token');
        return;
    }

    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    
    if (empty($phone)) {
        wp_send_json_error('Phone number is required');
        return;
    }

    // Find user by phone number
    $users = get_users(array(
        'meta_key' => 'phone_number',
        'meta_value' => $phone,
        'number' => 1,
        'count_total' => false,
    ));

    if (empty($users)) {
        wp_send_json_error('No account found with this phone number');
        return;
    }

    $user = $users[0];
    $user_id = $user->ID;

    // Use the existing Twilio configuration
    $twilio_sid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        error_log('Twilio Verify configuration is missing for forgot password.');
        wp_send_json_error('SMS service configuration error. Please contact support.');
        return;
    }

    $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

    try {
        $verification = $twilio->verify->v2->services($twilio_verify_sid)
            ->verifications
            ->create($phone, "sms");

        error_log("Forgot password verification started: SID " . $verification->sid . " for user ID " . $user_id);
        
        // Store the verification session
        set_transient('forgot_password_verification_' . md5($phone), array(
            'phone' => $phone,
            'user_id' => $user_id,
            'verification_sid' => $verification->sid,
            'timestamp' => time()
        ), 300); // 5 minutes expiry

        wp_send_json_success(array(
            'message' => 'Verification code sent to your phone number'
        ));

    } catch (Exception $e) {
        error_log('Twilio Verify error in forgot password: ' . $e->getMessage());
        wp_send_json_error('Failed to send verification code. Please try again later.');
    }
}

/**
 * Handle verifying OTP for forgot password
 */
function handle_verify_forgot_password_otp() {
    // Security check: Prevent logged-in users from using forgot password
    if (is_user_logged_in()) {
        wp_send_json_error('Access denied: Already logged in');
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'verify_forgot_password_otp_nonce')) {
        wp_send_json_error('Invalid security token');
        return;
    }

    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $otp = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';

    if (empty($phone) || empty($otp)) {
        wp_send_json_error('Phone number and verification code are required');
        return;
    }

    if (strlen($otp) !== 6) {
        wp_send_json_error('Verification code must be 6 digits');
        return;
    }

    // Get verification session
    $verification_session = get_transient('forgot_password_verification_' . md5($phone));
    
    if (!$verification_session) {
        wp_send_json_error('Verification session expired. Please start over.');
        return;
    }

    // Use Twilio to verify the code
    $twilio_sid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        wp_send_json_error('SMS service configuration error. Please contact support.');
        return;
    }

    $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

    try {
        $verification_check = $twilio->verify->v2->services($twilio_verify_sid)
            ->verificationChecks
            ->create([
                'to' => $phone,
                'code' => $otp
            ]);

        if ($verification_check->status === 'approved') {
            // Store verified session for password update
            set_transient('forgot_password_verified_' . md5($phone), array(
                'phone' => $phone,
                'user_id' => $verification_session['user_id'],
                'verified' => true,
                'timestamp' => time()
            ), 600); // 10 minutes to complete password reset
            
            // Clean up the verification session
            delete_transient('forgot_password_verification_' . md5($phone));
            
            wp_send_json_success(array(
                'message' => 'Code verified successfully',
                'user_id' => $verification_session['user_id']
            ));
        } else {
            wp_send_json_error('Invalid verification code');
        }
    } catch (Exception $e) {
        error_log('Twilio verification check error in forgot password: ' . $e->getMessage());
        wp_send_json_error('Error verifying code. Please try again.');
    }
}

/**
 * Handle updating password for forgot password
 */
function handle_update_forgot_password() {
    // Security check: Prevent logged-in users from using forgot password
    if (is_user_logged_in()) {
        wp_send_json_error('Access denied: Already logged in');
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_forgot_password_nonce')) {
        wp_send_json_error('Invalid security token');
        return;
    }

    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

    if (empty($phone) || empty($new_password)) {
        wp_send_json_error('Phone number and new password are required');
        return;
    }

    // Check if user has verified session
    $verified_session = get_transient('forgot_password_verified_' . md5($phone));
    
    if (!$verified_session || !$verified_session['verified']) {
        wp_send_json_error('Session expired. Please start over.');
        return;
    }

    // Apply the same strict validation as registration
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
    $user_id = $verified_session['user_id'];
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
    delete_transient('forgot_password_verified_' . md5($phone));
    
    // Log the password change
    error_log("Forgot password completed for user ID: $user_id");

    wp_send_json_success(array(
        'message' => 'Password updated successfully'
    ));
} 