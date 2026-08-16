<?php
/**
 * AutoAgora Facebook group promotion and seller-sharing helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

const AUTOAGORA_FACEBOOK_GROUP_OPTION = 'autoagora_facebook_group_url';

/**
 * Return the configured Facebook group URL.
 *
 * A wp-config.php constant can override the WordPress setting for deployments
 * that prefer environment-managed configuration.
 */
function autoagora_facebook_group_url(): string
{
    $url = defined('AUTOAGORA_FACEBOOK_GROUP_URL')
        ? (string) AUTOAGORA_FACEBOOK_GROUP_URL
        : (string) get_option(AUTOAGORA_FACEBOOK_GROUP_OPTION, '');

    return (string) apply_filters('autoagora_facebook_group_url', trim($url));
}

/**
 * Let administrators inspect placements before the group URL is configured.
 */
function autoagora_facebook_group_admin_preview(): bool
{
    return is_user_logged_in()
        && current_user_can('manage_options')
        && autoagora_facebook_group_url() === '';
}

/**
 * Validate the Settings -> General value as a Facebook group URL.
 */
function autoagora_sanitize_facebook_group_url($value): string
{
    $value = esc_url_raw(trim((string) $value), array('http', 'https'));
    if ($value === '') {
        return '';
    }

    $parts = wp_parse_url($value);
    $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
    $path = isset($parts['path']) ? (string) $parts['path'] : '';
    $allowed_hosts = array('facebook.com', 'www.facebook.com', 'm.facebook.com');

    if (!in_array($host, $allowed_hosts, true) || !preg_match('#^/groups/[^/]+#', $path)) {
        add_settings_error(
            AUTOAGORA_FACEBOOK_GROUP_OPTION,
            'autoagora_facebook_group_invalid_url',
            __('Enter a complete Facebook group URL, for example https://www.facebook.com/groups/your-group.', 'bricks-child'),
            'error'
        );

        return (string) get_option(AUTOAGORA_FACEBOOK_GROUP_OPTION, '');
    }

    return $value;
}

/**
 * Add the single group URL setting to WordPress General Settings.
 */
function autoagora_register_facebook_group_setting(): void
{
    register_setting(
        'general',
        AUTOAGORA_FACEBOOK_GROUP_OPTION,
        array(
            'type' => 'string',
            'sanitize_callback' => 'autoagora_sanitize_facebook_group_url',
            'default' => '',
        )
    );

    add_settings_field(
        AUTOAGORA_FACEBOOK_GROUP_OPTION,
        __('AutoAgora Facebook group URL', 'bricks-child'),
        'autoagora_render_facebook_group_setting_field',
        'general'
    );
}
add_action('admin_init', 'autoagora_register_facebook_group_setting');

/**
 * Render the Settings -> General field.
 */
function autoagora_render_facebook_group_setting_field(): void
{
    $constant_override = defined('AUTOAGORA_FACEBOOK_GROUP_URL');
    $value = $constant_override
        ? (string) AUTOAGORA_FACEBOOK_GROUP_URL
        : (string) get_option(AUTOAGORA_FACEBOOK_GROUP_OPTION, '');
    ?>
    <input
        type="url"
        id="<?php echo esc_attr(AUTOAGORA_FACEBOOK_GROUP_OPTION); ?>"
        name="<?php echo esc_attr(AUTOAGORA_FACEBOOK_GROUP_OPTION); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="regular-text code"
        placeholder="https://www.facebook.com/groups/your-group"
        <?php disabled($constant_override); ?>
    >
    <p class="description">
        <?php if ($constant_override) : ?>
            <?php esc_html_e('This value is controlled by AUTOAGORA_FACEBOOK_GROUP_URL in wp-config.php.', 'bricks-child'); ?>
        <?php else : ?>
            <?php esc_html_e('Activates every Facebook group promotion and seller-sharing button on the site.', 'bricks-child'); ?>
        <?php endif; ?>
    </p>
    <?php
}

/**
 * Return an administrator-only setup hint when the URL is missing.
 */
function autoagora_facebook_group_setup_hint(): string
{
    if (!autoagora_facebook_group_admin_preview()) {
        return '';
    }

    return sprintf(
        '<small class="autoagora-facebook-admin-note">%1$s <a href="%2$s">%3$s</a></small>',
        esc_html__('Administrator preview: visitors will not see this until a group URL is configured.', 'bricks-child'),
        esc_url(admin_url('options-general.php#' . AUTOAGORA_FACEBOOK_GROUP_OPTION)),
        esc_html__('Set the group URL', 'bricks-child')
    );
}

/**
 * Build a tracked external group link or an administrator preview control.
 */
function autoagora_facebook_group_link(string $label, string $placement, string $classes = 'btn btn-primary'): string
{
    $url = autoagora_facebook_group_url();
    if ($url === '') {
        if (!autoagora_facebook_group_admin_preview()) {
            return '';
        }

        return sprintf(
            '<span class="%1$s is-disabled" aria-disabled="true"><i class="fab fa-facebook-f" aria-hidden="true"></i> %2$s</span>',
            esc_attr($classes),
            esc_html__('Set group URL to activate', 'bricks-child')
        );
    }

    return sprintf(
        '<a class="%1$s" href="%2$s" target="_blank" rel="noopener noreferrer nofollow" data-autoagora-facebook-placement="%3$s" data-autoagora-facebook-action="join"><i class="fab fa-facebook-f" aria-hidden="true"></i> %4$s</a>',
        esc_attr($classes),
        esc_url($url),
        esc_attr($placement),
        esc_html($label)
    );
}

/**
 * Render a reusable promotion card for the requested context.
 */
function autoagora_facebook_group_join_card(string $placement, string $context = 'default'): string
{
    if (autoagora_facebook_group_url() === '' && !autoagora_facebook_group_admin_preview()) {
        return '';
    }

    $copy = array(
        'title' => __('Join the AutoAgora Facebook community', 'bricks-child'),
        'description' => __('Discover newly listed cars, ask local buying questions, and connect with buyers and sellers across Cyprus.', 'bricks-child'),
        'button' => __('Join our Facebook Group', 'bricks-child'),
    );

    if ($context === 'submission') {
        $copy = array(
            'title' => __('Reach more buyers through Facebook', 'bricks-child'),
            'description' => __('Your listing is being reviewed. Join the AutoAgora Facebook community now, and we will remind you to share your car when it becomes publicly visible.', 'bricks-child'),
            'button' => __('Join our Facebook Group', 'bricks-child'),
        );
    } elseif ($context === 'homepage') {
        $copy = array(
            'title' => __("Join Cyprus's car community", 'bricks-child'),
            'description' => __('See newly listed cars, get practical buying advice, and connect with local buyers and sellers.', 'bricks-child'),
            'button' => __('Join us on Facebook', 'bricks-child'),
        );
    }

    $heading_id = 'autoagora-facebook-heading-' . wp_unique_id();

    ob_start();
    ?>
    <section class="autoagora-facebook-card autoagora-facebook-card--<?php echo esc_attr($context); ?>" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <span class="autoagora-facebook-card-icon" aria-hidden="true"><i class="fab fa-facebook-f"></i></span>
        <div class="autoagora-facebook-card-copy">
            <h3 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($copy['title']); ?></h3>
            <p><?php echo esc_html($copy['description']); ?></p>
            <?php echo autoagora_facebook_group_setup_hint(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <div class="autoagora-facebook-card-action">
            <?php echo autoagora_facebook_group_link($copy['button'], $placement); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
}

/**
 * Check whether the current page is an English page or one of its Polylang translations.
 */
function autoagora_facebook_group_is_localized_page(string $english_slug): bool
{
    if (!is_page()) {
        return false;
    }

    $source_page = get_page_by_path($english_slug);
    if (!$source_page instanceof WP_Post) {
        return false;
    }

    $current_id = get_queried_object_id();
    if ($current_id === (int) $source_page->ID) {
        return true;
    }

    if (!function_exists('pll_get_post')) {
        return false;
    }

    $language = function_exists('autoagora_current_language') ? autoagora_current_language() : '';
    $translated_id = $language !== ''
        ? pll_get_post($source_page->ID, $language)
        : pll_get_post($source_page->ID);

    return $translated_id && $current_id === (int) $translated_id;
}

/**
 * Return the campaign page type, or an empty string outside relevant browse journeys.
 */
function autoagora_facebook_group_campaign_page_type(): string
{
    if (is_front_page() || is_page_template('template-custom-homepage.php')) {
        return 'homepage';
    }

    if (is_singular('car')) {
        return 'single_car';
    }

    if (is_singular('buyer_request')) {
        return 'single_buyer_request';
    }

    if (
        is_page_template('template-buyer-requests.php')
        || autoagora_facebook_group_is_localized_page('buyer-requests')
    ) {
        return 'buyer_requests';
    }

    $city_templates = array(
        'template-cars-city-nicosia.php',
        'template-cars-city-limassol.php',
        'template-cars-city-larnaca.php',
        'template-cars-city-paphos.php',
    );
    if (
        is_page_template('template-test-cars.php')
        || is_page_template($city_templates)
        || is_post_type_archive('car')
        || is_tax('car_make')
        || autoagora_facebook_group_is_localized_page('cars')
    ) {
        return 'cars_browse';
    }

    return '';
}

/**
 * Avoid stacking the campaign with the more important email-verification notice.
 */
function autoagora_facebook_group_email_notice_is_expected(): bool
{
    if (!is_user_logged_in()) {
        return false;
    }

    $user_id = get_current_user_id();
    if (get_user_meta($user_id, 'email_verified', true) === '1') {
        return false;
    }

    if (!empty($_SESSION['email_notification_dismissed'])) {
        return false;
    }

    $user = wp_get_current_user();
    return strpos((string) $user->user_email, 'phone_user_') !== 0;
}

/**
 * Render the benefit-led, measured banner below the code-owned header.
 */
function autoagora_render_facebook_group_campaign_banner(): void
{
    $page_type = autoagora_facebook_group_campaign_page_type();
    if (
        (function_exists('autoagora_code_header_is_enabled') && !autoagora_code_header_is_enabled())
        || $page_type === ''
        || autoagora_facebook_group_email_notice_is_expected()
        || (autoagora_facebook_group_url() === '' && !autoagora_facebook_group_admin_preview())
    ) {
        return;
    }

    $heading_id = 'autoagora-facebook-campaign-heading';
    ?>
    <aside
        class="autoagora-facebook-campaign-banner"
        data-autoagora-facebook-campaign-banner
        data-autoagora-facebook-page-type="<?php echo esc_attr($page_type); ?>"
        data-autoagora-facebook-campaign="sticky_benefit_v1"
        aria-labelledby="<?php echo esc_attr($heading_id); ?>"
        hidden
    >
        <div class="autoagora-facebook-campaign-banner__inner">
            <span class="autoagora-facebook-campaign-banner__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M13.5 22v-9h3l.45-3.5H13.5V7.27c0-1.01.28-1.7 1.73-1.7H17V2.44c-.76-.1-1.52-.15-2.28-.14-2.26 0-3.81 1.38-3.81 3.91V9.5H8v3.5h2.91v9h2.59Z"/></svg>
            </span>
            <div class="autoagora-facebook-campaign-banner__copy">
                <strong id="<?php echo esc_attr($heading_id); ?>"><?php esc_html_e('Buying or selling a car in Cyprus?', 'bricks-child'); ?></strong>
                <span><?php esc_html_e('See new listings, price drops and buyer requests in our Facebook community.', 'bricks-child'); ?></span>
            </div>
            <?php echo autoagora_facebook_group_link(__('Join the group', 'bricks-child'), 'browse_banner', 'autoagora-facebook-campaign-banner__cta'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <button
                type="button"
                class="autoagora-facebook-campaign-banner__dismiss"
                data-autoagora-facebook-banner-dismiss
                aria-label="<?php esc_attr_e('Dismiss Facebook group invitation', 'bricks-child'); ?>"
            >&times;</button>
        </div>
    </aside>
    <?php
}
add_action('wp_body_open', 'autoagora_render_facebook_group_campaign_banner', 6);

/**
 * Add attribution parameters to seller-shared listing links.
 */
function autoagora_facebook_group_listing_share_url(int $listing_id): string
{
    return add_query_arg(
        array(
            'utm_source' => 'facebook',
            'utm_medium' => 'group',
            'utm_campaign' => 'seller_share',
        ),
        get_permalink($listing_id)
    );
}

/**
 * Build concise, ready-to-paste Facebook copy from listing data.
 */
function autoagora_facebook_group_listing_caption(int $listing_id): string
{
    $title = html_entity_decode(wp_strip_all_tags(get_the_title($listing_id)), ENT_QUOTES, 'UTF-8');
    $details = array();
    $price = (float) str_replace(',', '', (string) get_post_meta($listing_id, 'price', true));
    $mileage = (float) str_replace(',', '', (string) get_post_meta($listing_id, 'mileage', true));

    if ($price > 0) {
        $details[] = 'EUR ' . number_format_i18n($price, 0);
    }
    if ($mileage > 0) {
        $details[] = number_format_i18n($mileage, 0) . ' km';
    }

    $location_parts = array_filter(array_map('trim', array(
        (string) get_post_meta($listing_id, 'car_city', true),
        (string) get_post_meta($listing_id, 'car_district', true),
    )));
    $location_parts = array_values(array_unique($location_parts));
    if ($location_parts) {
        $details[] = implode(', ', $location_parts);
    }

    $lines = array(sprintf(__('%s for sale', 'bricks-child'), $title));
    if ($details) {
        $lines[] = implode(' | ', $details);
    }
    $lines[] = __('See photos and full details:', 'bricks-child') . ' ' . autoagora_facebook_group_listing_share_url($listing_id);
    $lines[] = __('Listed on AutoAgora.cy', 'bricks-child');

    return implode("\n", $lines);
}

/**
 * Render the published-listing share control used in My Listings.
 */
function autoagora_facebook_group_share_button(int $listing_id, string $placement): string
{
    $post = get_post($listing_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'car' || $post->post_status !== 'publish') {
        return '';
    }
    if (class_exists('ListingStateManager') && ListingStateManager::resolve_state($listing_id) !== ListingStateManager::STATE_ACTIVE) {
        return '';
    }

    $url = autoagora_facebook_group_url();
    if ($url === '') {
        if (!autoagora_facebook_group_admin_preview()) {
            return '';
        }

        return autoagora_facebook_group_link('', $placement, 'btn btn-secondary');
    }

    return sprintf(
        '<span class="autoagora-facebook-share-control"><button type="button" class="btn btn-secondary autoagora-facebook-share-button" data-autoagora-facebook-share data-autoagora-facebook-placement="%1$s" data-group-url="%2$s" data-share-copy="%3$s"><i class="fab fa-facebook-f" aria-hidden="true"></i> %4$s</button><span class="autoagora-facebook-share-feedback" aria-live="polite"></span></span>',
        esc_attr($placement),
        esc_url($url),
        esc_attr(autoagora_facebook_group_listing_caption($listing_id)),
        esc_html__('Post in our Facebook Group', 'bricks-child')
    );
}

/**
 * Enqueue the small global asset because the footer promotion is site-wide.
 */
function autoagora_enqueue_facebook_group_assets(): void
{
    if (is_admin() || (autoagora_facebook_group_url() === '' && !autoagora_facebook_group_admin_preview())) {
        return;
    }

    $css_path = get_stylesheet_directory() . '/includes/facebook-group/facebook-group.css';
    $js_path = get_stylesheet_directory() . '/includes/facebook-group/facebook-group.js';

    wp_enqueue_style(
        'autoagora-facebook-group',
        get_stylesheet_directory_uri() . '/includes/facebook-group/facebook-group.css',
        array('bricks-child-theme-css'),
        file_exists($css_path) ? (string) filemtime($css_path) : BRICKS_CHILD_THEME_VERSION
    );
    wp_enqueue_script(
        'autoagora-facebook-group',
        get_stylesheet_directory_uri() . '/includes/facebook-group/facebook-group.js',
        array(),
        file_exists($js_path) ? (string) filemtime($js_path) : BRICKS_CHILD_THEME_VERSION,
        true
    );
    wp_localize_script('autoagora-facebook-group', 'autoagoraFacebookGroup', array(
        'copiedMessage' => __('Copied. Paste the details into your Facebook post.', 'bricks-child'),
        'copyFailedMessage' => __('The group is open. Copy your listing link into a new Facebook post.', 'bricks-child'),
        'language' => function_exists('autoagora_current_language') ? autoagora_current_language() : 'en',
    ));
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_facebook_group_assets', 20);

/**
 * Output the movable footer placement.
 */
function autoagora_render_facebook_group_movable_placements(): void
{
    $url = autoagora_facebook_group_url();
    $preview = autoagora_facebook_group_admin_preview();
    if ($url === '' && !$preview) {
        return;
    }

    $footer_href = $url !== '' ? $url : admin_url('options-general.php#' . AUTOAGORA_FACEBOOK_GROUP_OPTION);
    $footer_label = $url !== ''
        ? __('Facebook Group', 'bricks-child')
        : __('Facebook Group (set URL)', 'bricks-child');
    ?>
    <a
        hidden
        data-autoagora-facebook-footer-link
        data-autoagora-facebook-placement="footer"
        data-autoagora-facebook-action="join"
        href="<?php echo esc_url($footer_href); ?>"
        <?php if ($url !== '') : ?>target="_blank" rel="noopener noreferrer nofollow"<?php endif; ?>
    ><span class="icon"><i class="fas fa-users" aria-hidden="true"></i></span><span class="text"><?php echo esc_html($footer_label); ?></span></a>
    <?php
}
add_action('wp_footer', 'autoagora_render_facebook_group_movable_placements', 25);
