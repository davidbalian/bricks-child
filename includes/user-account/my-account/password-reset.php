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
        <h2><?php esc_html_e('Reset Password - Step 1', 'bricks-child'); ?></h2>
        
        <div class="password-reset-section">
            <h3><?php esc_html_e('Enter Verification Code', 'bricks-child'); ?></h3>
            <p><?php esc_html_e("We've sent a verification code to your phone number. Please enter the 6-digit code below:", 'bricks-child'); ?></p>
            
            <div class="verification-form">
                <div class="info-row">
                    <label for="verification-code" class="label"><?php esc_html_e('Verification Code:', 'bricks-child'); ?></label>
                    <input type="text" id="verification-code" maxlength="6" placeholder="000000" class="verification-input">
                </div>
                <div class="info-row">
                    <button class="btn btn-primary verify-code-btn"><?php esc_html_e('Verify Code', 'bricks-child'); ?></button>
                    <button class="btn btn-secondary cancel-reset-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                </div>
                <div class="info-row">
                    <button class="btn btn-link resend-code-btn"><?php esc_html_e('Resend Code', 'bricks-child'); ?></button>
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
        return '<p>' . esc_html__('Session expired. Please start over.', 'bricks-child') . '</p>';
    }
    
    ob_start();
    ?>
    
    <div class="my-account-container">
        <h2><?php esc_html_e('Reset Password - Step 2', 'bricks-child'); ?></h2>
        
        <div class="password-reset-section">
            <h3><?php esc_html_e('Set New Password', 'bricks-child'); ?></h3>
            <p><?php esc_html_e("Please enter your new password. Make sure it's strong and secure.", 'bricks-child'); ?></p>
            
            <div class="password-form">
                <div class="info-row">
                    <label for="new-password" class="label"><?php esc_html_e('New Password:', 'bricks-child'); ?></label>
                    <input type="password" id="new-password" placeholder="<?php esc_attr_e('Enter new password', 'bricks-child'); ?>" class="password-input" aria-describedby="password-strength password-remaining-reqs">
                </div>
                <div class="info-row">
                    <label for="confirm-password" class="label"><?php esc_html_e('Confirm Password:', 'bricks-child'); ?></label>
                    <input type="password" id="confirm-password" placeholder="<?php esc_attr_e('Confirm new password', 'bricks-child'); ?>" class="password-input">
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
                    <button class="btn btn-primary update-password-btn"><?php esc_html_e('Update Password', 'bricks-child'); ?></button>
                    <button class="btn btn-secondary cancel-reset-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
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
        <h2><?php esc_html_e('Password Reset Complete', 'bricks-child'); ?></h2>
        
        <div class="success-section">
            <div class="success-icon-large">✅</div>
            <h3><?php esc_html_e('Your password has been successfully updated!', 'bricks-child'); ?></h3>
            <p><?php esc_html_e('You may now return to the website and use your new password to log in.', 'bricks-child'); ?></p>
            
            <div class="success-actions">
                <a href="<?php echo esc_url(strtok($_SERVER["REQUEST_URI"], '?')); ?>" class="btn btn-primary"><?php esc_html_e('Return to My Account', 'bricks-child'); ?></a>
                <a href="<?php echo esc_url(autoagora_localized_page_url()); ?>" class="btn btn-secondary"><?php esc_html_e('Go to Homepage', 'bricks-child'); ?></a>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}
