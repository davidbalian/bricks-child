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
        <h2><?php esc_html_e('Personal Details', 'bricks-child'); ?></h2>
        
        <?php if (isset($_GET['name_updated']) && $_GET['name_updated'] == '1'): ?>
            <div class="success-message">
                <span class="success-icon">✓</span>
                <?php esc_html_e('Name successfully updated', 'bricks-child'); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['email_verified']) && $_GET['email_verified'] == 'success'): ?>
            <div class="success-message">
                <span class="success-icon">✓</span>
                <?php esc_html_e('Email verified successfully! Your email notifications are now active.', 'bricks-child'); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['email_verified']) && $_GET['email_verified'] == 'error'): ?>
            <div class="error-message" style="background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24; padding: 12px 16px; margin: 20px 0; display: flex; align-items: center; font-size: 14px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <span class="error-icon" style="background-color: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; margin-right: 10px; flex-shrink: 0;">✗</span>
                <?php esc_html_e('Email verification failed. The link may be expired or invalid.', 'bricks-child'); ?>
            </div>
        <?php endif; ?>
        
        <div class="account-sections">
            <div class="account-section">
                <h3><?php esc_html_e('Sign In Details', 'bricks-child'); ?></h3>
                <div class="info-row">
                    <span class="label"><?php esc_html_e('Phone Number:', 'bricks-child'); ?></span>
                    <span class="value"><?php echo esc_html($current_user->user_login); ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><?php esc_html_e('Password:', 'bricks-child'); ?></span>
                    <span class="value">******</span>
                    <button class="btn btn-primary reset-password-btn"><?php esc_html_e('Reset Password', 'bricks-child'); ?></button>
                </div>
            </div>

            <div class="account-section">
                <h3><?php esc_html_e('Personal Details', 'bricks-child'); ?></h3>
                <?php
                $user_roles = (array) $current_user->roles;
                $is_dealership_or_admin = in_array('dealership', $user_roles, true) || in_array('administrator', $user_roles, true);
                ?>
                <div class="info-row name-row">
                    <span class="label"><?php esc_html_e('Name:', 'bricks-child'); ?></span>
                    <span class="value" id="display-name"><?php echo esc_html(trim($current_user->first_name . ' ' . $current_user->last_name)); ?></span>
                    <button class="btn btn-primary edit-name-btn"><?php esc_html_e('Edit', 'bricks-child'); ?></button>
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label"><?php esc_html_e('First Name:', 'bricks-child'); ?></span>
                    <input type="text" id="first-name" value="<?php echo esc_attr($current_user->first_name); ?>" class="name-input">
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label"><?php esc_html_e('Last Name:', 'bricks-child'); ?></span>
                    <input type="text" id="last-name" value="<?php echo esc_attr($current_user->last_name); ?>" class="name-input">
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label"></span>
                    <button class="btn btn-primary save-name-btn"><?php esc_html_e('Save Changes', 'bricks-child'); ?></button>
                    <button class="btn btn-secondary cancel-name-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                </div>
                <div class="info-row email-row">
                    <span class="label"><?php esc_html_e('Email:', 'bricks-child'); ?></span>
                    <span class="value" id="display-email"><?php echo esc_html($current_user->user_email); ?></span>
                    <?php
                    // Check email verification status - now properly initialized to '0' or '1'
                    $email_verified = get_user_meta($current_user->ID, 'email_verified', true);
                    if ($email_verified === '1') {
                        echo '<span class="email-status verified">✅ ' . esc_html__('Verified', 'bricks-child') . '</span>';
                        echo '<button class="btn btn-primary edit-email-btn">' . esc_html__('Change Email', 'bricks-child') . '</button>';
                    } else {
                        echo '<div class="email-status-actions">';
                        echo '<span class="email-status not-verified">❌ ' . esc_html__('Not Verified', 'bricks-child') . '</span>';
                        echo '<button class="btn btn-primary edit-email-btn">' . esc_html__('Edit & Verify', 'bricks-child') . '</button>';
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="info-row email-edit-row" style="display: none;">
                    <span class="label"><?php esc_html_e('New Email:', 'bricks-child'); ?></span>
                    <input type="email" id="new-email" value="<?php echo esc_attr($current_user->user_email); ?>" class="email-input" placeholder="<?php esc_attr_e('Enter your email address', 'bricks-child'); ?>">
                </div>
                <div class="info-row email-edit-row" style="display: none;">
                    <span class="label"></span>
                    <button class="btn btn-primary send-verification-btn"><?php esc_html_e('Send Verification Email', 'bricks-child'); ?></button>
                    <button class="btn btn-secondary cancel-email-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
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
                    $has_secondary_phone     = ($secondary_phone_digits !== '');

                    if ($has_secondary_phone) {
                        $secondary_phone_display = '+' . $secondary_phone_country_code . ' ' . $secondary_phone_local;
                    }
                    ?>
                    <div class="info-row secondary-phone-row">
                        <span class="label"><?php esc_html_e('Secondary Phone Number:', 'bricks-child'); ?></span>
                        <span
                            class="value"
                            id="display-secondary-phone"
                            data-full-phone="<?php echo esc_attr($secondary_phone_digits); ?>"
                        >
                            <?php echo esc_html($secondary_phone_display); ?>
                        </span>
                        <button class="btn btn-primary edit-secondary-phone-btn">
                            <?php echo esc_html($has_secondary_phone ? __('Edit', 'bricks-child') : __('Add', 'bricks-child')); ?>
                        </button>
                    </div>
                    <div class="info-row secondary-phone-edit-row" style="display: none;">
                        <span class="label"><?php esc_html_e('Secondary Phone Number:', 'bricks-child'); ?></span>
                        <div class="secondary-phone-input-wrapper">
                            <span class="country-code-prefix">+<?php echo esc_html($secondary_phone_country_code); ?></span>
                            <input
                                type="tel"
                                id="secondary-phone-local"
                                class="secondary-phone-input"
                                value="<?php echo esc_attr($secondary_phone_local); ?>"
                                placeholder="<?php esc_attr_e('Enter phone without country code', 'bricks-child'); ?>"
                            >
                        </div>
                    </div>
                    <div class="info-row secondary-phone-edit-row" style="display: none;">
                        <span class="label"></span>
                        <button class="btn btn-primary save-secondary-phone-btn"><?php esc_html_e('Save Changes', 'bricks-child'); ?></button>
                        <button class="btn btn-secondary cancel-secondary-phone-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label"><?php esc_html_e('Role:', 'bricks-child'); ?></span>
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
                    <h3><?php esc_html_e('Account Logo', 'bricks-child'); ?></h3>
                    <div class="account-logo-wrapper">
                        <?php
                        $logo_manager = new UserLogoManager();
                        $logo_url = $logo_manager->getUserLogoUrl($current_user->ID, 'thumbnail');
                        ?>
                        <div class="account-logo-preview">
                            <?php if (!empty($logo_url)) : ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('Account logo', 'bricks-child'); ?>" id="account-logo-image">
                            <?php else : ?>
                                <div class="account-logo-placeholder" id="account-logo-placeholder">
                                    <span><?php esc_html_e('No logo uploaded', 'bricks-child'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="account-logo-actions">
                            <input type="file" id="account-logo-input" accept="image/*" style="display:none;">
                            <button type="button" class="btn btn-primary" id="upload-account-logo-btn">
                                <?php echo esc_html(!empty($logo_url) ? __('Change Logo', 'bricks-child') : __('Upload Logo', 'bricks-child')); ?>
                            </button>
                            <?php if (!empty($logo_url)) : ?>
                                <button type="button" class="btn btn-secondary" id="remove-account-logo-btn">
                                    <?php esc_html_e('Remove Logo', 'bricks-child'); ?>
                                </button>
                            <?php endif; ?>
                            <p class="account-logo-help-text">
                                <?php esc_html_e('Recommended: square image, max 2 MB.', 'bricks-child'); ?>
                            </p>
                            <div id="account-logo-feedback" class="account-logo-feedback" aria-live="polite"></div>
                        </div>
                    </div>
                </div>

                <div class="account-section dealer-info-section">
                    <h3><?php esc_html_e('Dealership Information', 'bricks-child'); ?></h3>
                    <?php
                    // Get current dealer field values
                    $dealer_website = get_field('dealer_website', 'user_' . $current_user->ID);
                    $dealer_instagram = get_field('dealer_instagram', 'user_' . $current_user->ID);
                    $dealer_facebook = get_field('dealer_facebook', 'user_' . $current_user->ID);
                    $dealer_maps_url = get_field('dealer_maps_url', 'user_' . $current_user->ID);
                    $dealer_maps_address = get_field('dealer_maps_address', 'user_' . $current_user->ID);
                    ?>
                    <div class="info-row dealer-website-row">
                        <span class="label"><?php esc_html_e('Website:', 'bricks-child'); ?></span>
                        <span class="value" id="display-dealer-website"><?php echo esc_html($dealer_website ?: __('Not set', 'bricks-child')); ?></span>
                        <button class="btn btn-primary edit-dealer-website-btn"><?php esc_html_e('Edit', 'bricks-child'); ?></button>
                    </div>
                    <div class="info-row dealer-website-edit-row" style="display: none;">
                        <span class="label"><?php esc_html_e('Website URL:', 'bricks-child'); ?></span>
                        <input type="url" id="dealer-website" value="<?php echo esc_attr($dealer_website); ?>" class="dealer-input" placeholder="https://example.com">
                    </div>
                    <div class="info-row dealer-website-edit-row" style="display: none;">
                        <span class="label"></span>
                        <button class="btn btn-primary save-dealer-website-btn"><?php esc_html_e('Save Changes', 'bricks-child'); ?></button>
                        <button class="btn btn-secondary cancel-dealer-website-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                    </div>

                    <div class="info-row dealer-instagram-row">
                        <span class="label"><?php esc_html_e('Instagram:', 'bricks-child'); ?></span>
                        <span class="value" id="display-dealer-instagram"><?php echo esc_html($dealer_instagram ?: __('Not set', 'bricks-child')); ?></span>
                        <button class="btn btn-primary edit-dealer-instagram-btn"><?php esc_html_e('Edit', 'bricks-child'); ?></button>
                    </div>
                    <div class="info-row dealer-instagram-edit-row" style="display: none;">
                        <span class="label"><?php esc_html_e('Instagram URL:', 'bricks-child'); ?></span>
                        <input type="url" id="dealer-instagram" value="<?php echo esc_attr($dealer_instagram); ?>" class="dealer-input" placeholder="https://instagram.com/username">
                    </div>
                    <div class="info-row dealer-instagram-edit-row" style="display: none;">
                        <span class="label"></span>
                        <button class="btn btn-primary save-dealer-instagram-btn"><?php esc_html_e('Save Changes', 'bricks-child'); ?></button>
                        <button class="btn btn-secondary cancel-dealer-instagram-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                    </div>

                    <div class="info-row dealer-facebook-row">
                        <span class="label"><?php esc_html_e('Facebook:', 'bricks-child'); ?></span>
                        <span class="value" id="display-dealer-facebook"><?php echo esc_html($dealer_facebook ?: __('Not set', 'bricks-child')); ?></span>
                        <button class="btn btn-primary edit-dealer-facebook-btn"><?php esc_html_e('Edit', 'bricks-child'); ?></button>
                    </div>
                    <div class="info-row dealer-facebook-edit-row" style="display: none;">
                        <span class="label"><?php esc_html_e('Facebook URL:', 'bricks-child'); ?></span>
                        <input type="url" id="dealer-facebook" value="<?php echo esc_attr($dealer_facebook); ?>" class="dealer-input" placeholder="https://facebook.com/username">
                    </div>
                    <div class="info-row dealer-facebook-edit-row" style="display: none;">
                        <span class="label"></span>
                        <button class="btn btn-primary save-dealer-facebook-btn"><?php esc_html_e('Save Changes', 'bricks-child'); ?></button>
                        <button class="btn btn-secondary cancel-dealer-facebook-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                    </div>

                    <div class="info-row dealer-maps-url-row">
                        <span class="label"><?php esc_html_e('Maps URL:', 'bricks-child'); ?></span>
                        <span class="value" id="display-dealer-maps-url"><?php echo esc_html($dealer_maps_url ?: __('Not set', 'bricks-child')); ?></span>
                        <button class="btn btn-primary edit-dealer-maps-url-btn"><?php esc_html_e('Edit', 'bricks-child'); ?></button>
                    </div>
                    <div class="info-row dealer-maps-url-edit-row" style="display: none;">
                        <span class="label"><?php esc_html_e('Maps URL:', 'bricks-child'); ?></span>
                        <input type="text" id="dealer-maps-url" value="<?php echo esc_attr($dealer_maps_url); ?>" class="dealer-input" placeholder="<?php esc_attr_e('Google Maps URL', 'bricks-child'); ?>">
                    </div>
                    <div class="info-row dealer-maps-url-edit-row" style="display: none;">
                        <span class="label"></span>
                        <button class="btn btn-primary save-dealer-maps-url-btn"><?php esc_html_e('Save Changes', 'bricks-child'); ?></button>
                        <button class="btn btn-secondary cancel-dealer-maps-url-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                    </div>

                    <div class="info-row dealer-maps-address-row">
                        <span class="label"><?php esc_html_e('Maps Address:', 'bricks-child'); ?></span>
                        <span class="value" id="display-dealer-maps-address"><?php echo esc_html($dealer_maps_address ?: __('Not set', 'bricks-child')); ?></span>
                        <button class="btn btn-primary edit-dealer-maps-address-btn"><?php esc_html_e('Edit', 'bricks-child'); ?></button>
                    </div>
                    <div class="info-row dealer-maps-address-edit-row" style="display: none;">
                        <span class="label"><?php esc_html_e('Maps Address:', 'bricks-child'); ?></span>
                        <input type="text" id="dealer-maps-address" value="<?php echo esc_attr($dealer_maps_address); ?>" class="dealer-input" placeholder="<?php esc_attr_e('Full address', 'bricks-child'); ?>">
                    </div>
                    <div class="info-row dealer-maps-address-edit-row" style="display: none;">
                        <span class="label"></span>
                        <button class="btn btn-primary save-dealer-maps-address-btn"><?php esc_html_e('Save Changes', 'bricks-child'); ?></button>
                        <button class="btn btn-secondary cancel-dealer-maps-address-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                    </div>
                    <div id="dealer-info-feedback" class="dealer-info-feedback" aria-live="polite"></div>
                </div>
            <?php endif; ?>

            <div class="account-section notification-preferences-section">
                <h3><?php esc_html_e('Email Notifications', 'bricks-child'); ?></h3>
                <p class="notification-description"><?php esc_html_e('Only verified emails receive optional car reminders. You can toggle activity emails and 7-day reminders below.', 'bricks-child'); ?></p>
                <div class="info-row notification-row">
                    <label class="notification-toggle">
                        <input type="checkbox" id="activity-notifications-toggle"
                            data-email-verified="<?php echo $email_verified === '1' ? '1' : '0'; ?>"
                            <?php checked($activity_enabled); ?>
                            <?php disabled($notification_disabled); ?>>
                        <span><?php esc_html_e('Listing activity updates (clicks/views)', 'bricks-child'); ?></span>
                    </label>
                    <?php if ($notification_disabled): ?>
                        <p class="notification-hint"><?php esc_html_e('Verify your email to enable activity notifications.', 'bricks-child'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="info-row notification-row">
                    <label class="notification-toggle">
                        <input type="checkbox" id="reminder-notifications-toggle"
                            <?php checked($reminder_enabled); ?>
                            <?php disabled($notification_disabled); ?>>
                        <span><?php esc_html_e('7-day reminders to refresh or mark as sold', 'bricks-child'); ?></span>
                    </label>
                    <?php if ($notification_disabled): ?>
                        <p class="notification-hint"><?php esc_html_e('Verify your email to receive reminders.', 'bricks-child'); ?></p>
                    <?php endif; ?>
                </div>
                <div id="notification-preferences-feedback" class="notification-feedback" aria-live="polite"></div>
            </div>
        </div>

        <div class="account-actions">
            <a href="<?php echo esc_url(wp_logout_url(autoagora_localized_page_url())); ?>" class="btn btn-primary"><?php esc_html_e('Logout', 'bricks-child'); ?></a>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}
