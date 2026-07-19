<?php
/**
 * Marketplace-wide promotion and Stripe event manager.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Promotion_Admin_Page
{
    const MENU_SLUG = 'autoagora-promotions';
    const PER_PAGE = 25;

    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('admin_post_autoagora_admin_grant_promotion', array(__CLASS__, 'handle_grant'));
        add_action('admin_post_autoagora_admin_cancel_promotion', array(__CLASS__, 'handle_cancel'));
    }

    public static function register_menu()
    {
        add_submenu_page(
            'edit.php?post_type=car',
            'AutoAgora Promotions',
            'Promotions',
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'render_page')
        );
    }

    public static function enqueue_assets()
    {
        if (!self::is_current_page() || !current_user_can('manage_options')) {
            return;
        }
        $path = __DIR__ . '/promotion-manager.css';
        wp_enqueue_style(
            'autoagora-promotion-manager',
            get_stylesheet_directory_uri() . '/includes/listing-promotions/promotion-manager.css',
            array(),
            file_exists($path) ? filemtime($path) : BRICKS_CHILD_THEME_VERSION
        );
    }

    public static function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage promotions.');
        }
        ?>
        <div class="wrap autoagora-promotion-manager">
            <h1>AutoAgora Promotions</h1>
            <?php self::render_notice(); ?>
            <?php if (!AutoAgora_Promotion_Schema::is_current()) : ?>
                <div class="notice notice-error inline"><p><strong>The promotion database schema is unavailable or outdated.</strong> Reload this page once to run the versioned migration.</p></div>
            <?php else : ?>
                <?php self::render_readiness(); ?>
                <?php self::render_grant_form(); ?>
                <?php self::render_promotions(); ?>
                <?php self::render_payment_events(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_grant()
    {
        self::authorize();
        check_admin_referer('autoagora_admin_grant_promotion');
        $listing_id = isset($_POST['listing_id']) ? absint($_POST['listing_id']) : 0;
        $tier = isset($_POST['tier']) ? sanitize_key(wp_unslash($_POST['tier'])) : '';
        $days = isset($_POST['days']) ? absint($_POST['days']) : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        $starts_at = self::parse_local_datetime(isset($_POST['starts_at']) ? wp_unslash($_POST['starts_at']) : '');
        $result = autoagora_promotion_manager()->grant_manual(
            $listing_id,
            $tier,
            $days * DAY_IN_SECONDS,
            $starts_at,
            get_current_user_id(),
            $notes
        );
        self::redirect(is_wp_error($result) ? 'grant_error' : 'granted', is_wp_error($result) ? $result->get_error_code() : '');
    }

    public static function handle_cancel()
    {
        self::authorize();
        $promotion_id = isset($_POST['promotion_id']) ? absint($_POST['promotion_id']) : 0;
        check_admin_referer('autoagora_admin_cancel_promotion_' . $promotion_id);
        $result = autoagora_promotion_manager()->cancel($promotion_id);
        self::redirect(is_wp_error($result) ? 'cancel_error' : 'cancelled', is_wp_error($result) ? $result->get_error_code() : '');
    }

    private static function render_readiness()
    {
        $errors = AutoAgora_Stripe_Gateway::configuration_errors();
        $events = new AutoAgora_Payment_Event_Repository();
        $attention = $events->admin_attention_count();
        $next_cron = wp_next_scheduled('autoagora_reconcile_listing_promotions');
        $lift = AutoAgora_Stripe_Gateway::package_for(AutoAgora_Promotion_Manager::TIER_PRIORITY, 1);
        $showcase = AutoAgora_Stripe_Gateway::package_for(AutoAgora_Promotion_Manager::TIER_SHOWCASE, 1);
        ?>
        <section class="autoagora-admin-card autoagora-readiness-card">
            <div>
                <span class="autoagora-admin-eyebrow">Stripe <?php echo esc_html(AutoAgora_Stripe_Gateway::mode()); ?> mode</span>
                <strong class="autoagora-readiness-state <?php echo $errors ? 'is-blocked' : 'is-ready'; ?>">
                    <?php echo $errors ? 'Configuration needs attention' : 'Checkout configuration ready'; ?>
                </strong>
                <span>Webhook: <code><?php echo esc_html(AutoAgora_Stripe_Gateway::webhook_url()); ?></code></span>
                <span>Daily prices: Lift <?php echo esc_html($lift ? 'EUR ' . number_format_i18n($lift['daily_amount_minor'] / 100, 2) : 'unavailable'); ?> / Showcase <?php echo esc_html($showcase ? 'EUR ' . number_format_i18n($showcase['daily_amount_minor'] / 100, 2) : 'unavailable'); ?></span>
                <span>Payment log: <code><?php echo esc_html(AutoAgora_Payment_Logger::path()); ?></code></span>
            </div>
            <div class="autoagora-readiness-metric <?php echo $attention ? 'has-attention' : ''; ?>">
                <strong><?php echo esc_html(number_format_i18n($attention)); ?></strong>
                <span>payment events needing attention</span>
            </div>
            <div class="autoagora-readiness-metric <?php echo $next_cron ? '' : 'has-attention'; ?>">
                <strong><?php echo $next_cron ? esc_html(wp_date('j M H:i', $next_cron, wp_timezone())) : 'Missing'; ?></strong>
                <span>next promotion reconciliation</span>
            </div>
            <?php if ($errors) : ?>
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function render_grant_form()
    {
        ?>
        <section class="autoagora-admin-card">
            <div class="autoagora-admin-section-heading">
                <div><span class="autoagora-admin-eyebrow">Manual operation</span><h2>Grant or extend a promotion</h2></div>
                <p>Every grant joins the listing's queue after its latest unfinished promotion.</p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="autoagora-admin-grant-form">
                <input type="hidden" name="action" value="autoagora_admin_grant_promotion">
                <?php wp_nonce_field('autoagora_admin_grant_promotion'); ?>
                <label><span>Listing ID</span><input type="number" name="listing_id" min="1" required></label>
                <label><span>Tier</span><select name="tier" required>
                    <?php foreach (AutoAgora_Promotion_Manager::tiers() as $key => $tier) : ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($tier['label']); ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span>Duration (days)</span><input type="number" name="days" min="1" max="365" value="1" required></label>
                <label><span>Requested start</span><input type="datetime-local" name="starts_at"><small>Optional; queue rules still prevent overlap.</small></label>
                <label class="autoagora-admin-notes"><span>Internal note</span><input type="text" name="notes" maxlength="2000"></label>
                <button type="submit" class="button button-primary">Grant promotion</button>
            </form>
        </section>
        <?php
    }

    private static function render_promotions()
    {
        $filters = self::promotion_filters();
        $page = max(1, isset($_GET['promo_page']) ? absint($_GET['promo_page']) : 1);
        $repository = new AutoAgora_Promotion_Repository();
        $total = $repository->admin_count($filters);
        $rows = $repository->admin_search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        ?>
        <section class="autoagora-admin-card" id="autoagora-promotions-table">
            <div class="autoagora-admin-section-heading">
                <div><span class="autoagora-admin-eyebrow">Promotion records</span><h2>All promotions</h2></div>
                <strong><?php echo esc_html(number_format_i18n($total)); ?> results</strong>
            </div>
            <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>" class="autoagora-admin-filters">
                <input type="hidden" name="post_type" value="car"><input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
                <label class="is-wide"><span>Search</span><input type="search" name="promotion_search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Listing, seller, promotion or payment ID"></label>
                <label><span>Status</span><select name="promotion_status"><option value="">All statuses</option><?php self::status_options($filters['status']); ?></select></label>
                <label><span>Tier</span><select name="promotion_tier"><option value="">All tiers</option><?php foreach (AutoAgora_Promotion_Manager::tiers() as $key => $tier) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['tier'], $key); ?>><?php echo esc_html($tier['label']); ?></option><?php endforeach; ?></select></label>
                <label><span>Source</span><select name="promotion_source"><option value="">All sources</option><option value="payment" <?php selected($filters['source'], 'payment'); ?>>Stripe payment</option><option value="manual" <?php selected($filters['source'], 'manual'); ?>>Manual</option></select></label>
                <label><span>Date field</span><select name="promotion_date_field"><option value="created_at" <?php selected($filters['date_field'], 'created_at'); ?>>Created</option><option value="starts_at" <?php selected($filters['date_field'], 'starts_at'); ?>>Starts</option><option value="ends_at" <?php selected($filters['date_field'], 'ends_at'); ?>>Ends</option></select></label>
                <label><span>From</span><input type="date" name="promotion_date_from" value="<?php echo esc_attr($filters['date_from']); ?>"></label>
                <label><span>To</span><input type="date" name="promotion_date_to" value="<?php echo esc_attr($filters['date_to']); ?>"></label>
                <div class="autoagora-admin-filter-actions"><button class="button button-primary">Filter</button><a class="button" href="<?php echo esc_url(self::base_url()); ?>">Reset</a></div>
            </form>
            <div class="autoagora-admin-table-wrap">
                <table class="widefat striped autoagora-admin-table"><thead><tr><th>Listing</th><th>Seller</th><th>Promotion</th><th>Schedule (site time)</th><th>Payment</th><th>Created</th><th>Action</th></tr></thead><tbody>
                <?php if (!$rows) : ?><tr><td colspan="7">No promotion records match these filters.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row) : ?><?php self::render_promotion_row($row); ?><?php endforeach; ?>
                </tbody></table>
            </div>
            <?php self::render_pagination($page, $total, self::PER_PAGE, 'promo_page', 'autoagora-promotions-table'); ?>
        </section>
        <?php
    }

    private static function render_promotion_row($row)
    {
        $title = trim((string) $row->listing_title_snapshot);
        if ($title === '') {
            $title = trim((string) $row->current_listing_title);
        }
        $title = $title !== '' ? $title : 'Untitled listing';
        $is_live = !empty($row->live_listing_id);
        $seller_id = (int) $row->seller_id_snapshot;
        $paid = (int) $row->amount_minor > 0
            ? strtoupper((string) $row->currency) . ' ' . number_format_i18n((int) $row->amount_minor / 100, 2)
            : 'Manual';
        ?>
        <tr id="promotion-<?php echo esc_attr($row->id); ?>">
            <td><strong><?php if ($is_live) : ?><a href="<?php echo esc_url(get_edit_post_link((int) $row->listing_id)); ?>"><?php echo esc_html($title); ?></a><?php else : ?>Deleted: <?php echo esc_html($title); ?><?php endif; ?></strong><small>#<?php echo esc_html($row->listing_id); ?> / Promotion #<?php echo esc_html($row->id); ?></small></td>
            <td><?php if (!empty($row->live_seller_id)) : ?><a href="<?php echo esc_url(get_edit_user_link((int) $row->live_seller_id)); ?>"><?php echo esc_html($row->seller_display_name ?: $row->seller_user_login); ?></a><small>User #<?php echo esc_html($row->live_seller_id); ?></small><?php elseif ($seller_id > 0) : ?><span>Deleted user</span><small>User #<?php echo esc_html($seller_id); ?></small><?php else : ?><span>Anonymized</span><?php endif; ?></td>
            <td><strong><?php echo esc_html(AutoAgora_Promotion_Manager::tier_label($row->tier) ?: $row->tier); ?></strong><span class="autoagora-admin-status status-<?php echo esc_attr(sanitize_html_class($row->status)); ?>"><?php echo esc_html(ucfirst($row->status)); ?></span><small><?php echo esc_html(ucfirst($row->source)); ?> / <?php echo esc_html(AutoAgora_Stripe_Gateway::duration_label((int) $row->duration_seconds)); ?></small><?php if ((int) $row->granted_by > 0) : ?><small>Granted by user #<?php echo esc_html($row->granted_by); ?></small><?php endif; ?><?php if ($row->notes) : ?><small title="<?php echo esc_attr($row->notes); ?>"><?php echo esc_html(wp_html_excerpt($row->notes, 90, '...')); ?></small><?php endif; ?></td>
            <td><span>Starts <?php echo esc_html(self::display_gmt($row->starts_at)); ?></span><small>Ends <?php echo esc_html(self::display_gmt($row->ends_at)); ?></small></td>
            <td><strong><?php echo esc_html($paid); ?></strong><?php if ((int) $row->refunded_amount_minor > 0) : ?><small>Refunded <?php echo esc_html(strtoupper((string) $row->currency) . ' ' . number_format_i18n((int) $row->refunded_amount_minor / 100, 2)); ?></small><?php endif; ?><?php if ($row->payment_reference) : ?><a href="<?php echo esc_url(self::stripe_payment_url($row->payment_reference)); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html($row->payment_reference); ?></code></a><?php endif; ?><?php if ($row->stripe_checkout_session_id) : ?><code><?php echo esc_html($row->stripe_checkout_session_id); ?></code><?php endif; ?></td>
            <td><?php echo esc_html(self::display_gmt($row->created_at)); ?></td>
            <td><?php if (in_array($row->status, array(AutoAgora_Promotion_Manager::STATUS_ACTIVE, AutoAgora_Promotion_Manager::STATUS_SCHEDULED), true)) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Cancel this promotion? This does not issue a Stripe refund.');"><input type="hidden" name="action" value="autoagora_admin_cancel_promotion"><input type="hidden" name="promotion_id" value="<?php echo esc_attr($row->id); ?>"><?php wp_nonce_field('autoagora_admin_cancel_promotion_' . (int) $row->id); ?><button class="button button-small">Cancel</button></form><?php else : ?>&mdash;<?php endif; ?></td>
        </tr>
        <?php
    }

    private static function render_payment_events()
    {
        $filters = self::event_filters();
        $page = max(1, isset($_GET['event_page']) ? absint($_GET['event_page']) : 1);
        $repository = new AutoAgora_Payment_Event_Repository();
        $total = $repository->admin_count($filters);
        $rows = $repository->admin_search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        ?>
        <section class="autoagora-admin-card" id="autoagora-payment-events">
            <div class="autoagora-admin-section-heading"><div><span class="autoagora-admin-eyebrow">Webhook receipts</span><h2>Stripe payment events</h2></div><strong><?php echo esc_html(number_format_i18n($total)); ?> results</strong></div>
            <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>" class="autoagora-admin-filters is-events">
                <input type="hidden" name="post_type" value="car"><input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
                <label class="is-wide"><span>Search events</span><input type="search" name="event_search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Event, PaymentIntent, type or error"></label>
                <label><span>Status</span><select name="event_status"><option value="">All statuses</option><?php foreach (self::event_statuses() as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(ucfirst($status)); ?></option><?php endforeach; ?></select></label>
                <label><span>From</span><input type="date" name="event_date_from" value="<?php echo esc_attr($filters['date_from']); ?>"></label>
                <label><span>To</span><input type="date" name="event_date_to" value="<?php echo esc_attr($filters['date_to']); ?>"></label>
                <div class="autoagora-admin-filter-actions"><button class="button button-primary">Filter events</button><a class="button" href="<?php echo esc_url(self::base_url() . '#autoagora-payment-events'); ?>">Reset</a></div>
            </form>
            <div class="autoagora-admin-table-wrap"><table class="widefat striped autoagora-admin-table"><thead><tr><th>Event</th><th>Type</th><th>Object reference</th><th>Status</th><th>Attempts</th><th>Received</th><th>Processed</th></tr></thead><tbody>
                <?php if (!$rows) : ?><tr><td colspan="7">No payment events match these filters.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row) : ?><tr><td><a href="<?php echo esc_url(self::stripe_event_url($row->event_id)); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html($row->event_id); ?></code></a></td><td><?php echo esc_html($row->event_type); ?></td><td><code><?php echo esc_html($row->object_reference ?: '-'); ?></code></td><td><span class="autoagora-admin-status status-<?php echo esc_attr(sanitize_html_class($row->status)); ?>"><?php echo esc_html(ucfirst($row->status)); ?></span><?php if ($row->error_code) : ?><small><?php echo esc_html($row->error_code); ?></small><?php endif; ?></td><td><?php echo esc_html(number_format_i18n((int) $row->attempts)); ?></td><td><?php echo esc_html(self::display_gmt($row->created_at)); ?></td><td><?php echo esc_html(self::display_gmt($row->processed_at)); ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php self::render_pagination($page, $total, self::PER_PAGE, 'event_page', 'autoagora-payment-events'); ?>
        </section>
        <?php
    }

    private static function promotion_filters()
    {
        $statuses = self::promotion_statuses();
        $tiers = array_keys(AutoAgora_Promotion_Manager::tiers());
        $sources = array('payment', 'manual');
        $date_fields = array('created_at', 'starts_at', 'ends_at');
        $status = isset($_GET['promotion_status']) ? sanitize_key(wp_unslash($_GET['promotion_status'])) : '';
        $tier = isset($_GET['promotion_tier']) ? sanitize_key(wp_unslash($_GET['promotion_tier'])) : '';
        $source = isset($_GET['promotion_source']) ? sanitize_key(wp_unslash($_GET['promotion_source'])) : '';
        $date_field = isset($_GET['promotion_date_field']) ? sanitize_key(wp_unslash($_GET['promotion_date_field'])) : 'created_at';
        $from = self::date_input('promotion_date_from');
        $to = self::date_input('promotion_date_to');
        return array(
            'search' => isset($_GET['promotion_search']) ? substr(sanitize_text_field(wp_unslash($_GET['promotion_search'])), 0, 191) : '',
            'status' => in_array($status, $statuses, true) ? $status : '',
            'tier' => in_array($tier, $tiers, true) ? $tier : '',
            'source' => in_array($source, $sources, true) ? $source : '',
            'date_field' => in_array($date_field, $date_fields, true) ? $date_field : 'created_at',
            'date_from' => $from,
            'date_to' => $to,
            'date_from_gmt' => self::date_to_gmt($from, false),
            'date_to_gmt' => self::date_to_gmt($to, true),
        );
    }

    private static function event_filters()
    {
        $status = isset($_GET['event_status']) ? sanitize_key(wp_unslash($_GET['event_status'])) : '';
        $from = self::date_input('event_date_from');
        $to = self::date_input('event_date_to');
        return array(
            'search' => isset($_GET['event_search']) ? substr(sanitize_text_field(wp_unslash($_GET['event_search'])), 0, 191) : '',
            'status' => in_array($status, self::event_statuses(), true) ? $status : '',
            'date_from' => $from,
            'date_to' => $to,
            'date_from_gmt' => self::date_to_gmt($from, false),
            'date_to_gmt' => self::date_to_gmt($to, true),
        );
    }

    private static function render_pagination($current, $total, $per_page, $page_arg, $anchor)
    {
        $pages = (int) ceil($total / $per_page);
        if ($pages <= 1) {
            return;
        }
        echo '<div class="autoagora-admin-pagination"><span>Page ' . esc_html($current) . ' of ' . esc_html($pages) . '</span><div>';
        if ($current > 1) {
            echo '<a class="button" href="' . esc_url(self::filtered_url(array($page_arg => $current - 1)) . '#' . $anchor) . '">&larr; Previous</a>';
        }
        if ($current < $pages) {
            echo '<a class="button" href="' . esc_url(self::filtered_url(array($page_arg => $current + 1)) . '#' . $anchor) . '">Next &rarr;</a>';
        }
        echo '</div></div>';
    }

    private static function status_options($selected)
    {
        foreach (self::promotion_statuses() as $status) {
            echo '<option value="' . esc_attr($status) . '" ' . selected($selected, $status, false) . '>' . esc_html(ucfirst($status)) . '</option>';
        }
    }

    private static function promotion_statuses()
    {
        return array('scheduled', 'active', 'expired', 'cancelled', 'refunded');
    }

    private static function event_statuses()
    {
        return array('received', 'processing', 'pending', 'processed', 'failed');
    }

    private static function display_gmt($value)
    {
        return $value ? get_date_from_gmt($value, 'Y-m-d H:i') : '-';
    }

    private static function parse_local_datetime($value)
    {
        $value = sanitize_text_field((string) $value);
        if ($value === '') {
            return gmdate('Y-m-d H:i:s');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
        return $date ? $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s');
    }

    private static function date_input($key)
    {
        $value = isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return '';
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : '';
    }

    private static function date_to_gmt($value, $exclusive_end)
    {
        if ($value === '') {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        if (!$date) {
            return '';
        }
        if ($exclusive_end) {
            $date = $date->modify('+1 day');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function stripe_event_url($event_id)
    {
        $base = AutoAgora_Stripe_Gateway::mode() === 'live'
            ? 'https://dashboard.stripe.com/events/'
            : 'https://dashboard.stripe.com/test/events/';
        return $base . rawurlencode((string) $event_id);
    }

    private static function stripe_payment_url($payment_intent)
    {
        $base = AutoAgora_Stripe_Gateway::mode() === 'live'
            ? 'https://dashboard.stripe.com/payments/'
            : 'https://dashboard.stripe.com/test/payments/';
        return $base . rawurlencode((string) $payment_intent);
    }

    private static function render_notice()
    {
        $notice = isset($_GET['promotion_notice']) ? sanitize_key(wp_unslash($_GET['promotion_notice'])) : '';
        $code = isset($_GET['promotion_error']) ? sanitize_key(wp_unslash($_GET['promotion_error'])) : '';
        $messages = array(
            'granted' => array('success', 'Promotion added to the listing queue.'),
            'cancelled' => array('success', 'Promotion cancelled.'),
            'grant_error' => array('error', self::error_message($code, 'The promotion could not be granted.')),
            'cancel_error' => array('error', self::error_message($code, 'The promotion could not be cancelled.')),
        );
        if (isset($messages[$notice])) {
            echo '<div class="notice notice-' . esc_attr($messages[$notice][0]) . ' is-dismissible"><p>' . esc_html($messages[$notice][1]) . '</p></div>';
        }
    }

    private static function error_message($code, $fallback)
    {
        $messages = array(
            'promotion_listing_invalid' => 'The listing does not exist or is not a car.',
            'promotion_listing_inactive' => 'Only published, active listings can receive a promotion.',
            'promotion_tier_invalid' => 'Choose a valid promotion tier.',
            'promotion_duration_invalid' => 'Duration must be between one hour and one year.',
            'promotion_listing_busy' => 'Another promotion operation is running for this listing. Try again.',
            'promotion_not_cancellable' => 'This promotion is already complete or cannot be cancelled.',
        );
        return $messages[$code] ?? $fallback;
    }

    private static function authorize()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage promotions.', 'Forbidden', array('response' => 403));
        }
    }

    private static function redirect($notice, $error = '')
    {
        $args = array('promotion_notice' => $notice);
        if ($error !== '') {
            $args['promotion_error'] = sanitize_key($error);
        }
        wp_safe_redirect(add_query_arg($args, self::base_url()));
        exit;
    }

    private static function filtered_url(array $changes)
    {
        $keys = array(
            'promotion_search', 'promotion_status', 'promotion_tier', 'promotion_source',
            'promotion_date_field', 'promotion_date_from', 'promotion_date_to', 'promo_page',
            'event_search', 'event_status', 'event_date_from', 'event_date_to', 'event_page',
        );
        $args = array('post_type' => 'car', 'page' => self::MENU_SLUG);
        foreach ($keys as $key) {
            if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
                $args[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }
        return add_query_arg(array_merge($args, $changes), admin_url('edit.php'));
    }

    private static function base_url()
    {
        return add_query_arg(array('post_type' => 'car', 'page' => self::MENU_SLUG), admin_url('edit.php'));
    }

    private static function is_current_page()
    {
        return isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === self::MENU_SLUG;
    }
}

AutoAgora_Promotion_Admin_Page::init();
