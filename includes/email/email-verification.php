<?php
/**
 * Email Verification System
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load SendGrid configuration
require_once get_stylesheet_directory() . '/includes/email/sendgrid-config.php';

/**
 * Generate a secure verification token
 */
function generate_email_verification_token($user_id, $email) {
    $data = $user_id . '|' . $email . '|' . time();
    return base64_encode($data . '|' . hash_hmac('sha256', $data, wp_salt()));
}

/**
 * Verify email verification token
 */
function verify_email_verification_token($token) {
    $decoded = base64_decode($token);
    $parts = explode('|', $decoded);
    
    if (count($parts) !== 4) {
        return false;
    }
    
    list($user_id, $email, $timestamp, $hash) = $parts;
    
    // Check if token is expired (72 hours)
    if (time() - $timestamp > 259200) {
        return false;
    }
    
    // Verify hash
    $data = $user_id . '|' . $email . '|' . $timestamp;
    $expected_hash = hash_hmac('sha256', $data, wp_salt());
    
    if (!hash_equals($expected_hash, $hash)) {
        return false;
    }
    
    // SECURITY: Check if this token was created after the latest verification attempt
    // This invalidates all previous tokens when a new verification email is sent
    $latest_verification_timestamp = get_user_meta($user_id, 'latest_email_verification_timestamp', true);
    if ($latest_verification_timestamp && $timestamp < $latest_verification_timestamp) {
        error_log("Email verification: Token rejected - created before latest verification attempt. Token: $timestamp, Latest: $latest_verification_timestamp");
        return false;
    }
    
    return array(
        'user_id' => $user_id,
        'email' => $email,
        'timestamp' => $timestamp
    );
}

/**
 * Send verification email
 */
function send_verification_email($user_id, $email) {
    // Store timestamp of this verification attempt to invalidate previous tokens
    $current_timestamp = time();
    update_user_meta($user_id, 'latest_email_verification_timestamp', $current_timestamp);
    
    // Generate verification token
    $token = generate_email_verification_token($user_id, $email);
    
    // Create verification URL
    $verification_url = home_url('/verify-email/?token=' . urlencode($token));
    
    // Get user info
    $user = get_user_by('ID', $user_id);
    $user_name = trim($user->first_name . ' ' . $user->last_name);
    if (empty($user_name)) {
        $user_name = $user->display_name;
    }
    
    // Email subject
    $subject = 'Verify Your Email Address - AutoAgora';
    
    // Email content (HTML)
    $html_content = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify Your Email</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #f8f9fa; padding: 30px; border-radius: 10px; text-align: center;">
            <h1 style="color: #0073aa; margin-bottom: 20px;">AutoAgora</h1>
            <h2 style="color: #333; margin-bottom: 30px;">Verify Your Email Address</h2>
            
            <p style="font-size: 16px; margin-bottom: 20px;">Hello ' . esc_html($user_name) . ',</p>
            
            <p style="font-size: 16px; margin-bottom: 30px;">
                Please click the button below to verify your email address: <strong>' . esc_html($email) . '</strong>
            </p>
            
            <div style="margin: 40px 0;">
                <a href="' . esc_url($verification_url) . '" 
                   style="background-color: #0073aa; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold; display: inline-block;">
                    Click Here to Verify Your Email
                </a>
            </div>
            
            <p style="font-size: 14px; color: #666; margin-top: 40px;">
                This verification link will expire in 72 hours.
            </p>
            
            <p style="font-size: 14px; color: #666;">
                If you did not request this email verification, please ignore this email.
            </p>
        </div>
    </body>
    </html>';
    
    // Plain text version
    $text_content = "AutoAgora - Verify Your Email Address\n\n";
    $text_content .= "Hello " . $user_name . ",\n\n";
    $text_content .= "Please click the link below to verify your email address: " . $email . "\n\n";
    $text_content .= "Verification Link: " . $verification_url . "\n\n";
    $text_content .= "This verification link will expire in 72 hours.\n\n";
    $text_content .= "If you did not request this email verification, please ignore this email.";
    
    // Send email using SendGrid
    return send_sendgrid_email($email, $subject, $html_content, $text_content);
}

/**
 * Handle email verification URL clicks
 */
function handle_email_verification() {
    if (isset($_GET['token']) && !empty($_GET['token'])) {
        $token = sanitize_text_field($_GET['token']);
        $verification_data = verify_email_verification_token($token);
        
        if ($verification_data) {
            $user_id = $verification_data['user_id'];
            $email = $verification_data['email'];
            
            // Update user email and verification status
            wp_update_user(array(
                'ID' => $user_id,
                'user_email' => $email
            ));
            
            // Mark email as verified
            update_user_meta($user_id, 'email_verified', '1');
            
            // Clear the verification timestamp since email is now verified
            // This prevents any remaining old tokens from being used
            delete_user_meta($user_id, 'latest_email_verification_timestamp');
            
            error_log("Email verification successful for user ID: $user_id, email: $email");
            
            // Redirect to my-account with success message
            wp_redirect(home_url('/my-account/?email_verified=success'));
            exit;
        } else {
            // Invalid or expired token
            error_log("Email verification failed - invalid or expired token");
            wp_redirect(home_url('/my-account/?email_verified=error'));
            exit;
        }
    }
}

// Hook to handle verification URLs
add_action('template_redirect', 'handle_email_verification'); 