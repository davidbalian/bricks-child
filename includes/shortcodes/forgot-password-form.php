<?php
/**
 * Forgot Password Form Shortcode [forgot_password_form]
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Enqueue forgot password assets
 */
function enqueue_forgot_password_assets() {
    global $post;
    $load_assets = false;

    // Check if on the forgot-password page (slug 'forgot-password')
    if (is_page('forgot-password')) {
        $load_assets = true;
    }
    // Check if on a page/post containing the forgot_password_form shortcode
    elseif (is_singular() && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'forgot_password_form')) {
        $load_assets = true;
    }

    if ($load_assets) {
        // Enqueue forgot password CSS
        wp_enqueue_style(
            'forgot-password-css',
            get_stylesheet_directory_uri() . '/includes/auth/forgot-password.css',
            array(),
            '1.0.0'
        );

        // Enqueue forgot password JS
        wp_enqueue_script(
            'forgot-password-js',
            get_stylesheet_directory_uri() . '/includes/auth/forgot-password.js',
            array('jquery', 'autoagora-i18n'),
            '1.0.0',
            true
        );

        // Enqueue intl-tel-input if not already loaded
        if (!wp_script_is('intl-tel-input', 'enqueued')) {
            wp_enqueue_style(
                'intl-tel-input-css',
                'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css',
                array(),
                '17.0.13'
            );
            wp_enqueue_script(
                'intl-tel-input-js',
                'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js',
                array('jquery'),
                '17.0.13',
                true
            );
            wp_enqueue_script(
                'intl-tel-input-utils',
                'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js',
                array('intl-tel-input-js'),
                '17.0.13',
                true
            );
        }

        // Localize script for AJAX
        wp_localize_script('forgot-password-js', 'ForgotPasswordAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'send_otp_nonce' => wp_create_nonce('send_forgot_password_otp_nonce'),
            'verify_otp_nonce' => wp_create_nonce('verify_forgot_password_otp_nonce'),
            'update_password_nonce' => wp_create_nonce('update_forgot_password_nonce')
        ));

        wp_enqueue_script(
            'cf-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            array(),
            null,
            true
          );
          
    }
}
add_action('wp_enqueue_scripts', 'enqueue_forgot_password_assets');

/**
 * Forgot Password Form Shortcode
 * 
 * @return string HTML output of the forgot password form
 */
function forgot_password_form_shortcode() {
    // Only redirect non-admin users who are logged in
    if (is_user_logged_in() && !current_user_can('administrator')) {
        wp_redirect(autoagora_localized_page_url('my-account'));
        exit;
    }

    ob_start();
    ?>
    <div class="forgot-password-container">
        <div id="forgot-password-messages"></div> <!-- Area for success/error messages -->

        <form id="forgot-password-form">
            
            <!-- Step 1: Phone Number Input -->
            <div id="step-phone" class="forgot-password-step">
                <h4><?php esc_html_e('Step 1: Enter Phone Number', 'bricks-child'); ?></h4>
                <div class="form-group">
                    <label for="forgot-phone-number-display"><?php esc_html_e('Phone Number:', 'bricks-child'); ?></label>
                    <input type="tel" name="forgot_phone_number_display" id="forgot-phone-number-display" required placeholder="<?php esc_attr_e('Enter your phone number', 'bricks-child'); ?>">
                </div>
                <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(TURNSTILE_SITE_KEY); ?>"></div>
                <div class="form-actions">
                    <button type="button" id="send-forgot-otp-button" class="btn btn-primary"><?php esc_html_e('Send Verification Code', 'bricks-child'); ?></button>
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="btn btn-secondary"><?php esc_html_e('Back to Login', 'bricks-child'); ?></a>
                </div>
            </div>

            <!-- Step 2: OTP Verification (Initially Hidden) -->
            <div id="step-otp" class="forgot-password-step" style="display: none;">
                <h4><?php esc_html_e('Step 2: Enter Verification Code', 'bricks-child'); ?></h4>
                <p class="step-description"><?php esc_html_e("We've sent a 6-digit code to your phone number. Please enter it below:", 'bricks-child'); ?></p>
                <div class="form-group">
                    <label for="forgot-verification-code"><?php esc_html_e('Verification Code:', 'bricks-child'); ?></label>
                    <input type="text" name="forgot_verification_code" id="forgot-verification-code" maxlength="6" required placeholder="000000" class="verification-input">
                </div>
                <div class="form-actions">
                    <button type="button" id="verify-forgot-otp-button" class="btn btn-primary"><?php esc_html_e('Verify Code', 'bricks-child'); ?></button>
                    <button type="button" id="change-forgot-phone-button" class="btn btn-secondary"><?php esc_html_e('Change Phone Number', 'bricks-child'); ?></button>
                </div>
                <div class="form-actions">
                    <button type="button" id="resend-forgot-otp-button" class="btn btn-link"><?php esc_html_e('Resend Code', 'bricks-child'); ?></button>
                </div>
            </div>

            <!-- Step 3: Set New Password (Initially Hidden) -->
            <div id="step-password" class="forgot-password-step" style="display: none;">
                <h4><?php esc_html_e('Step 3: Set New Password', 'bricks-child'); ?></h4>
                <p class="step-description"><?php esc_html_e('Create a strong password for your account.', 'bricks-child'); ?></p>
                
                <div class="form-group">
                    <label for="forgot-new-password"><?php esc_html_e('New Password:', 'bricks-child'); ?></label>
                    <input type="password" name="forgot_new_password" id="forgot-new-password" required aria-describedby="forgot-password-strength forgot-password-remaining-reqs" placeholder="<?php esc_attr_e('Enter new password', 'bricks-child'); ?>">
                    <div id="forgot-password-strength" class="password-strength" aria-live="polite"></div>
                    <div id="forgot-password-remaining-reqs" class="password-requirements">
                        <!-- Requirements list will be populated by JS -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="forgot-confirm-password"><?php esc_html_e('Confirm Password:', 'bricks-child'); ?></label>
                    <input type="password" name="forgot_confirm_password" id="forgot-confirm-password" required placeholder="<?php esc_attr_e('Confirm new password', 'bricks-child'); ?>">
                </div>
                
                <div class="form-actions">
                    <button type="button" id="update-forgot-password-button" class="btn btn-primary"><?php esc_html_e('Update Password', 'bricks-child'); ?></button>
                    <button type="button" id="cancel-forgot-password-button" class="btn btn-secondary"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                </div>
            </div>

            <!-- Step 4: Success Message (Initially Hidden) -->
            <div id="step-success" class="forgot-password-step success-step" style="display: none;">
                <div class="success-icon">✅</div>
                <h4><?php esc_html_e('Password Reset Complete!', 'bricks-child'); ?></h4>
                <p class="step-description"><?php esc_html_e('Your password has been successfully updated. You can now log in with your new password.', 'bricks-child'); ?></p>
                <div class="form-actions">
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="btn btn-primary"><?php esc_html_e('Go to Login', 'bricks-child'); ?></a>
                    <a href="<?php echo esc_url(autoagora_localized_page_url()); ?>" class="btn btn-secondary"><?php esc_html_e('Go to Homepage', 'bricks-child'); ?></a>
                </div>
            </div>

            <!-- Hidden fields for storing data between steps -->
            <input type="hidden" id="verified-phone-number" value="">
            <input type="hidden" id="user-id-for-reset" value="">
            
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('forgot_password_form', 'forgot_password_form_shortcode');
