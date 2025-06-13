<?php
/**
 * Forgot Password Form HTML Structure
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="forgot-password-container">
    <div class="forgot-password-header">
        <h1>Reset Your Password</h1>
        <p>Enter your phone number to receive a verification code</p>
    </div>

    <div id="forgot-password-messages"></div> <!-- Area for success/error messages -->

    <form id="forgot-password-form">
        
        <!-- Step 1: Phone Number Input -->
        <div id="step-phone" class="forgot-password-step">
            <h2>Step 1: Enter Phone Number</h2>
            <div class="form-group">
                <label for="forgot-phone-number-display">Phone Number:</label>
                <input type="tel" name="forgot_phone_number_display" id="forgot-phone-number-display" required placeholder="Enter your phone number">
            </div>
            <div class="form-actions">
                <button type="button" id="send-forgot-otp-button" class="button button-primary">Send Verification Code</button>
                <a href="<?php echo wp_login_url(); ?>" class="button button-secondary">Back to Login</a>
            </div>
        </div>

        <!-- Step 2: OTP Verification (Initially Hidden) -->
        <div id="step-otp" class="forgot-password-step" style="display: none;">
            <h2>Step 2: Enter Verification Code</h2>
            <p class="step-description">We've sent a 6-digit code to your phone number. Please enter it below:</p>
            <div class="form-group">
                <label for="forgot-verification-code">Verification Code:</label>
                <input type="text" name="forgot_verification_code" id="forgot-verification-code" maxlength="6" required placeholder="000000" class="verification-input">
            </div>
            <div class="form-actions">
                <button type="button" id="verify-forgot-otp-button" class="button button-primary">Verify Code</button>
                <button type="button" id="change-forgot-phone-button" class="button button-secondary">Change Phone Number</button>
            </div>
            <div class="form-actions">
                <button type="button" id="resend-forgot-otp-button" class="button button-link">Resend Code</button>
            </div>
        </div>

        <!-- Step 3: Set New Password (Initially Hidden) -->
        <div id="step-password" class="forgot-password-step" style="display: none;">
            <h2>Step 3: Set New Password</h2>
            <p class="step-description">Create a strong password for your account.</p>
            
            <div class="form-group">
                <label for="forgot-new-password">New Password:</label>
                <input type="password" name="forgot_new_password" id="forgot-new-password" required aria-describedby="forgot-password-strength forgot-password-remaining-reqs" placeholder="Enter new password">
                <div id="forgot-password-strength" class="password-strength" aria-live="polite"></div>
                <div id="forgot-password-remaining-reqs" class="password-requirements">
                    <!-- Requirements list will be populated by JS -->
                </div>
            </div>
            
            <div class="form-group">
                <label for="forgot-confirm-password">Confirm Password:</label>
                <input type="password" name="forgot_confirm_password" id="forgot-confirm-password" required placeholder="Confirm new password">
            </div>
            
            <div class="form-actions">
                <button type="button" id="update-forgot-password-button" class="button button-primary">Update Password</button>
                <button type="button" id="cancel-forgot-password-button" class="button button-secondary">Cancel</button>
            </div>
        </div>

        <!-- Step 4: Success Message (Initially Hidden) -->
        <div id="step-success" class="forgot-password-step success-step" style="display: none;">
            <div class="success-icon">✅</div>
            <h2>Password Reset Complete!</h2>
            <p class="step-description">Your password has been successfully updated. You can now log in with your new password.</p>
            <div class="form-actions">
                <a href="<?php echo wp_login_url(); ?>" class="button button-primary">Go to Login</a>
                <a href="<?php echo home_url(); ?>" class="button button-secondary">Go to Homepage</a>
            </div>
        </div>

        <!-- Hidden fields for storing data between steps -->
        <input type="hidden" id="verified-phone-number" value="">
        <input type="hidden" id="user-id-for-reset" value="">
        
    </form>
</div> 