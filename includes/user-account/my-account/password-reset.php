<?php
/**
 * Password Reset Forms HTML/PHP
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display password reset verification step
 */
function display_password_reset_verify() {
    $current_user = wp_get_current_user();
    
    ob_start();
    ?>
    
    <div class="my-account-container">
        <h2>Reset Password - Step 1</h2>
        
        <div class="password-reset-section">
            <h3>Enter Verification Code</h3>
            <p>We've sent a verification code to your phone number. Please enter the 6-digit code below:</p>
            
            <div class="verification-form">
                <div class="info-row">
                    <label for="verification-code" class="label">Verification Code:</label>
                    <input type="text" id="verification-code" maxlength="6" placeholder="000000" class="verification-input">
                </div>
                <div class="info-row">
                    <button class="button verify-code-btn">Verify Code</button>
                    <button class="button button-secondary cancel-reset-btn">Cancel</button>
                </div>
                <div class="info-row">
                    <button class="button button-link resend-code-btn">Resend Code</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

/**
 * Display new password form
 */
function display_password_reset_form() {
    $current_user = wp_get_current_user();
    $user_id = get_current_user_id();
    
    // Check if user has verified session
    $verified_session = get_transient('password_reset_verified_' . $user_id);
    
    if (!$verified_session || !$verified_session['verified']) {
        // Redirect back to start if no verified session
        echo '<script>window.location.href = "' . strtok($_SERVER["REQUEST_URI"], '?') . '";</script>';
        return '<p>Session expired. Please start over.</p>';
    }
    
    ob_start();
    ?>
    
    <div class="my-account-container">
        <h2>Reset Password - Step 2</h2>
        
        <div class="password-reset-section">
            <h3>Set New Password</h3>
            <p>Please enter your new password. Make sure it's strong and secure.</p>
            
            <div class="password-form">
                <div class="info-row">
                    <label for="new-password" class="label">New Password:</label>
                    <input type="password" id="new-password" placeholder="Enter new password" class="password-input" aria-describedby="password-strength password-remaining-reqs">
                </div>
                <div class="info-row">
                    <label for="confirm-password" class="label">Confirm Password:</label>
                    <input type="password" id="confirm-password" placeholder="Confirm new password" class="password-input">
                </div>
                <div class="info-row">
                    <div class="password-strength" id="password-strength"></div>
                </div>
                <div class="info-row">
                    <div id="password-remaining-reqs" style="font-size: 0.9em; margin-top: 3px;">
                        <!-- Requirements list will be populated by JS -->
                    </div>
                </div>
                <div class="info-row">
                    <button class="button update-password-btn">Update Password</button>
                    <button class="button button-secondary cancel-reset-btn">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

/**
 * Display password reset success page
 */
function display_password_reset_success() {
    ob_start();
    ?>
    
    <div class="my-account-container">
        <h2>Password Reset Complete</h2>
        
        <div class="success-section">
            <div class="success-icon-large">✅</div>
            <h3>Your password has been successfully updated!</h3>
            <p>You may now return to the website and use your new password to log in.</p>
            
            <div class="success-actions">
                <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="button button-primary">Return to My Account</a>
                <a href="<?php echo home_url(); ?>" class="button button-secondary">Go to Homepage</a>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
} 