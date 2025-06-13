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
?>

<div id="email-verification-notification" class="email-verification-notice">
    <div class="notice-container">
        <span class="notice-icon"><i class="fas fa-envelope"></i></span>
        <span class="notice-text">
            Verify Your Email - Send verification link to <strong><?php echo esc_html($user_email); ?></strong> to receive notifications
        </span>
        <button class="send-verification-btn" data-email="<?php echo esc_attr($user_email); ?>">
            Send Verification Email
        </button>
        <button class="dismiss-notice-btn" title="Dismiss notification">×</button>
    </div>
</div> 