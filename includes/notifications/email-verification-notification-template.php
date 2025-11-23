<?php
/**
 * Email Verification Notification Template
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Use the helper function if available
if (function_exists('get_email_verification_banner_html')) {
    echo get_email_verification_banner_html($user_email);
} else {
    // Fallback if function doesn't exist (shouldn't happen)
    ?>
    <div id="email-verification-notification" class="email-verification-notice">
        <div class="notice-container">
            <div class="text-and-icon">
                <span class="notice-icon"><i class="fas fa-envelope"></i></span>
                <span class="notice-text">
                    To receive notifications, activate <strong><?php echo esc_html($user_email); ?></strong>
                </span>
            </div>
            <button class="btn btn-primary-gradient send-verification-btn" data-email="<?php echo esc_attr($user_email); ?>">
                Send Verification Email
            </button>
            <button class="dismiss-notice-btn" title="Dismiss notification">×</button>
        </div>
    </div> 
    <?php
}
