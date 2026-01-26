<?php
/**
 * My Account Display HTML/PHP
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the main my account page
 */
function display_my_account_main($current_user) {
    ob_start();
    ?>
    
    <div class="my-account-container">
        <h2>Personal Details</h2>
        
        <?php if (isset($_GET['name_updated']) && $_GET['name_updated'] == '1'): ?>
            <div class="success-message">
                <span class="success-icon">✓</span>
                Name successfully updated
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['email_verified']) && $_GET['email_verified'] == 'success'): ?>
            <div class="success-message">
                <span class="success-icon">✓</span>
                Email verified successfully! Your email notifications are now active.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['email_verified']) && $_GET['email_verified'] == 'error'): ?>
            <div class="error-message" style="background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24; padding: 12px 16px; margin: 20px 0; display: flex; align-items: center; font-size: 14px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <span class="error-icon" style="background-color: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; margin-right: 10px; flex-shrink: 0;">✗</span>
                Email verification failed. The link may be expired or invalid.
            </div>
        <?php endif; ?>
        
        <div class="account-sections">
            <div class="account-section">
                <h3>Sign In Details</h3>
                <div class="info-row">
                    <span class="label">Phone Number:</span>
                    <span class="value"><?php echo esc_html($current_user->user_login); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Password:</span>
                    <span class="value">******</span>
                    <button class="btn btn-primary reset-password-btn">Reset Password</button>
                </div>
            </div>

            <div class="account-section">
                <h3>Personal Details</h3>
                <?php
                $user_roles = (array) $current_user->roles;
                $is_dealership_or_admin = in_array('dealership', $user_roles, true) || in_array('administrator', $user_roles, true);
                ?>
                <div class="info-row name-row">
                    <span class="label">Name:</span>
                    <span class="value" id="display-name"><?php echo esc_html(trim($current_user->first_name . ' ' . $current_user->last_name)); ?></span>
                    <button class="btn btn-primary edit-name-btn">Edit</button>
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label">First Name:</span>
                    <input type="text" id="first-name" value="<?php echo esc_attr($current_user->first_name); ?>" class="name-input">
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label">Last Name:</span>
                    <input type="text" id="last-name" value="<?php echo esc_attr($current_user->last_name); ?>" class="name-input">
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label"></span>
                    <button class="btn btn-primary save-name-btn">Save Changes</button>
                    <button class="btn btn-secondary cancel-name-btn">Cancel</button>
                </div>
                <div class="info-row email-row">
                    <span class="label">Email:</span>
                    <span class="value" id="display-email"><?php echo esc_html($current_user->user_email); ?></span>
                    <?php
                    // Check email verification status - now properly initialized to '0' or '1'
                    $email_verified = get_user_meta($current_user->ID, 'email_verified', true);
                    if ($email_verified === '1') {
                        echo '<span class="email-status verified">✅ Verified</span>';
                        echo '<button class="btn btn-primary edit-email-btn">Change Email</button>';
                    } else {
                        echo '<div class="email-status-actions">';
                        echo '<span class="email-status not-verified">❌ Not Verified</span>';
                        echo '<button class="btn btn-primary edit-email-btn">Edit & Verify</button>';
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="info-row email-edit-row" style="display: none;">
                    <span class="label">New Email:</span>
                    <input type="email" id="new-email" value="<?php echo esc_attr($current_user->user_email); ?>" class="email-input" placeholder="Enter your email address">
                </div>
                <div class="info-row email-edit-row" style="display: none;">
                    <span class="label"></span>
                    <button class="btn btn-primary send-verification-btn">Send Verification Email</button>
                    <button class="btn btn-secondary cancel-email-btn">Cancel</button>
                </div>
                <?php if ($is_dealership_or_admin) : ?>
                    <?php
                    // Prepare secondary phone number pieces for display and editing
                    $secondary_phone_country_code = '357';
                    $raw_secondary_phone         = get_user_meta($current_user->ID, 'secondary_phone', true);
                    $secondary_phone_digits      = preg_replace('/\D+/', '', (string) $raw_secondary_phone);
                    $secondary_phone_local       = '';

                    if ($secondary_phone_digits !== '') {
                        if (strpos($secondary_phone_digits, $secondary_phone_country_code) === 0) {
                            $secondary_phone_local = substr($secondary_phone_digits, strlen($secondary_phone_country_code));
                        } else {
                            $secondary_phone_local = $secondary_phone_digits;
                        }
                    }

                    $secondary_phone_display = '';
                    if ($secondary_phone_digits !== '') {
                        $secondary_phone_display = '+' . $secondary_phone_country_code . ' ' . $secondary_phone_local;
                    }
                    ?>
                    <div class="info-row secondary-phone-row">
                        <span class="label">Secondary Phone Number:</span>
                        <span
                            class="value"
                            id="display-secondary-phone"
                            data-full-phone="<?php echo esc_attr($secondary_phone_digits); ?>"
                        >
                            <?php echo esc_html($secondary_phone_display); ?>
                        </span>
                        <button class="btn btn-primary edit-secondary-phone-btn">Edit</button>
                    </div>
                    <div class="info-row secondary-phone-edit-row" style="display: none;">
                        <span class="label">Secondary Phone Number:</span>
                        <div class="secondary-phone-input-wrapper">
                            <span class="country-code-prefix">+<?php echo esc_html($secondary_phone_country_code); ?></span>
                            <input
                                type="tel"
                                id="secondary-phone-local"
                                class="secondary-phone-input"
                                value="<?php echo esc_attr($secondary_phone_local); ?>"
                                placeholder="Enter phone without country code"
                            >
                        </div>
                    </div>
                    <div class="info-row secondary-phone-edit-row" style="display: none;">
                        <span class="label"></span>
                        <button class="btn btn-primary save-secondary-phone-btn">Save Changes</button>
                        <button class="btn btn-secondary cancel-secondary-phone-btn">Cancel</button>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label">Role:</span>
                    <span class="value"><?php 
                        echo esc_html(implode(', ', $user_roles)); 
                    ?></span>
                </div>
            </div>
        

            <?php
            $preferences = new ListingNotificationPreferences();
            $activity_enabled = $preferences->isActivityNotificationsEnabled($current_user->ID);
            $reminder_enabled = $preferences->isReminderNotificationsEnabled($current_user->ID);
            $notification_disabled = ($email_verified !== '1');
            ?>

            <?php if ($is_dealership_or_admin) : ?>
                <div class="account-section account-logo-section">
                    <h3>Account Logo</h3>
                    <div class="account-logo-wrapper">
                        <?php
                        $logo_manager = new UserLogoManager();
                        $logo_url = $logo_manager->getUserLogoUrl($current_user->ID, 'thumbnail');
                        ?>
                        <div class="account-logo-preview">
                            <?php if (!empty($logo_url)) : ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="Account logo" id="account-logo-image">
                            <?php else : ?>
                                <div class="account-logo-placeholder" id="account-logo-placeholder">
                                    <span>No logo uploaded</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="account-logo-actions">
                            <input type="file" id="account-logo-input" accept="image/*" style="display:none;">
                            <button type="button" class="btn btn-primary" id="upload-account-logo-btn">
                                <?php echo !empty($logo_url) ? 'Change Logo' : 'Upload Logo'; ?>
                            </button>
                            <?php if (!empty($logo_url)) : ?>
                                <button type="button" class="btn btn-secondary" id="remove-account-logo-btn">
                                    Remove Logo
                                </button>
                            <?php endif; ?>
                            <p class="account-logo-help-text">
                                Recommended: square image, max 2 MB.
                            </p>
                            <div id="account-logo-feedback" class="account-logo-feedback" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="account-section notification-preferences-section">
                <h3>Email Notifications</h3>
                <p class="notification-description">Only verified emails receive optional career reminders. You can toggle activity emails and 7-day reminders below.</p>
                <div class="info-row notification-row">
                    <label class="notification-toggle">
                        <input type="checkbox" id="activity-notifications-toggle"
                            data-email-verified="<?php echo $email_verified === '1' ? '1' : '0'; ?>"
                            <?php checked($activity_enabled); ?>
                            <?php disabled($notification_disabled); ?>>
                        <span>Listing activity updates (clicks/views)</span>
                    </label>
                    <?php if ($notification_disabled): ?>
                        <p class="notification-hint">Verify your email to enable activity notifications.</p>
                    <?php endif; ?>
                </div>
                <div class="info-row notification-row">
                    <label class="notification-toggle">
                        <input type="checkbox" id="reminder-notifications-toggle"
                            <?php checked($reminder_enabled); ?>
                            <?php disabled($notification_disabled); ?>>
                        <span>7-day reminders to refresh or mark as sold</span>
                    </label>
                    <?php if ($notification_disabled): ?>
                        <p class="notification-hint">Verify your email to receive reminders.</p>
                    <?php endif; ?>
                </div>
                <div id="notification-preferences-feedback" class="notification-feedback" aria-live="polite"></div>
            </div>
        </div>

        <div class="account-actions">
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="btn btn-primary">Logout</a>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
} 