<?php
/**
 * Seller-facing Stripe Checkout controls for the My Listings page.
 */
if (!defined('ABSPATH')) {
    exit;
}

function autoagora_enqueue_stripe_checkout_assets()
{
    $base_path = __DIR__ . '/';
    $base_url = get_stylesheet_directory_uri() . '/includes/listing-promotions/';
    wp_enqueue_style(
        'autoagora-stripe-checkout',
        $base_url . 'stripe-checkout.css',
        array('bricks-child-my-listings-css'),
        file_exists($base_path . 'stripe-checkout.css') ? filemtime($base_path . 'stripe-checkout.css') : BRICKS_CHILD_THEME_VERSION
    );
    wp_enqueue_script(
        'autoagora-stripe-checkout',
        $base_url . 'stripe-checkout.js',
        array(),
        file_exists($base_path . 'stripe-checkout.js') ? filemtime($base_path . 'stripe-checkout.js') : BRICKS_CHILD_THEME_VERSION,
        true
    );
    wp_localize_script('autoagora-stripe-checkout', 'autoAgoraStripeCheckout', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action' => AutoAgora_Stripe_Gateway::CHECKOUT_AJAX_ACTION,
        'previewAction' => AutoAgora_Stripe_Gateway::PREVIEW_AJAX_ACTION,
        'nonce' => wp_create_nonce(AutoAgora_Stripe_Gateway::CHECKOUT_NONCE_ACTION),
        'currency' => 'EUR',
        'locale' => str_replace('_', '-', get_locale()),
        'workingText' => 'Opening secure checkout...',
        'checkingScheduleText' => 'Checking the latest promotion schedule...',
        'scheduleChangedText' => 'The schedule changed. Review the updated dates, then continue again.',
        'genericError' => 'Checkout could not be started. Please try again.',
    ));
}

function autoagora_get_seller_promotion_schedule($listing_id)
{
    static $cache = array();

    $listing_id = (int) $listing_id;
    if (isset($cache[$listing_id])) {
        return $cache[$listing_id];
    }
    $cache[$listing_id] = array();
    if (!$listing_id || !is_user_logged_in() || !AutoAgora_Promotion_Schema::is_current()) {
        return $cache[$listing_id];
    }

    $post = get_post($listing_id);
    $user_id = get_current_user_id();
    if (!$post || ((int) $post->post_author !== $user_id && !current_user_can('manage_options'))) {
        return $cache[$listing_id];
    }

    $repository = new AutoAgora_Promotion_Repository();
    $cache[$listing_id] = $repository->current_and_upcoming_for_listing(
        $listing_id,
        gmdate('Y-m-d H:i:s')
    );
    return $cache[$listing_id];
}

function autoagora_format_promotion_remaining($seconds)
{
    $seconds = max(0, (int) $seconds);
    if ($seconds < MINUTE_IN_SECONDS) {
        return 'Less than 1 minute';
    }

    $days = (int) floor($seconds / DAY_IN_SECONDS);
    $hours = (int) floor(($seconds % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
    $minutes = (int) floor(($seconds % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
    $parts = array();
    if ($days > 0) {
        $parts[] = $days . ' ' . _n('day', 'days', $days, 'bricks-child');
    }
    if ($hours > 0 && count($parts) < 2) {
        $parts[] = $hours . ' ' . _n('hour', 'hours', $hours, 'bricks-child');
    }
    if ($minutes > 0 && count($parts) < 2) {
        $parts[] = $minutes . ' ' . _n('minute', 'minutes', $minutes, 'bricks-child');
    }
    return implode(' ', $parts);
}

function autoagora_promotion_local_datetime($gmt_datetime)
{
    return $gmt_datetime ? get_date_from_gmt($gmt_datetime, 'j M Y, H:i') : '';
}

function autoagora_render_seller_promotion_status($listing_id)
{
    $schedule = autoagora_get_seller_promotion_schedule($listing_id);
    if (!$schedule) {
        return;
    }

    $active = null;
    foreach ($schedule as $record) {
        if ($record->status === AutoAgora_Promotion_Manager::STATUS_ACTIVE) {
            $active = $record;
            break;
        }
    }
    $primary = $active ?: $schedule[0];
    $queued_count = count($schedule) - ($active ? 1 : 0);
    $tier_label = AutoAgora_Promotion_Manager::tier_label($primary->tier) ?: ucfirst((string) $primary->tier);
    $tier_class = sanitize_html_class((string) $primary->tier);
    ?>
    <section class="autoagora-seller-promotion-status autoagora-seller-promotion-status--<?php echo esc_attr($tier_class); ?>"
             aria-label="Listing promotion status">
        <span class="autoagora-seller-promotion-icon" aria-hidden="true"><i class="fas fa-bolt"></i></span>
        <span class="autoagora-seller-promotion-copy">
            <span class="autoagora-seller-promotion-title">
                <strong><?php echo esc_html($tier_label); ?></strong>
                <span class="autoagora-seller-promotion-state <?php echo $active ? 'is-active' : 'is-scheduled'; ?>"><?php echo $active ? 'Active' : 'Scheduled'; ?></span>
            </span>
            <?php if ($active) : ?>
                <?php $end_timestamp = strtotime($active->ends_at . ' UTC'); ?>
                <span class="autoagora-seller-promotion-timing">
                    <strong data-promotion-end-timestamp="<?php echo esc_attr($end_timestamp); ?>">
                        <?php echo esc_html(autoagora_format_promotion_remaining($end_timestamp - time())); ?> left
                    </strong>
                    <span>Ends <?php echo esc_html(autoagora_promotion_local_datetime($active->ends_at)); ?></span>
                </span>
            <?php else : ?>
                <span class="autoagora-seller-promotion-timing">
                    <strong>Starts <?php echo esc_html(autoagora_promotion_local_datetime($primary->starts_at)); ?></strong>
                    <span><?php echo esc_html(AutoAgora_Stripe_Gateway::duration_label($primary->duration_seconds)); ?></span>
                </span>
            <?php endif; ?>
        </span>
        <?php if ($queued_count > 0) : ?>
            <span class="autoagora-seller-promotion-queued">
                <?php echo esc_html($queued_count . ' ' . _n('promotion queued', 'promotions queued', $queued_count, 'bricks-child')); ?>
            </span>
        <?php endif; ?>
    </section>
    <?php
}

function autoagora_render_seller_promotion_timeline(array $schedule)
{
    if (!$schedule) {
        return;
    }
    ?>
    <section class="autoagora-promotion-schedule" aria-label="Promotion schedule">
        <div class="autoagora-promotion-schedule-heading">
            <strong>Promotion schedule</strong>
            <span><?php echo esc_html(count($schedule) . ' ' . _n('promotion', 'promotions', count($schedule), 'bricks-child')); ?></span>
        </div>
        <ol class="autoagora-promotion-timeline">
            <?php foreach ($schedule as $record) : ?>
                <?php
                $is_active = $record->status === AutoAgora_Promotion_Manager::STATUS_ACTIVE;
                $tier_label = AutoAgora_Promotion_Manager::tier_label($record->tier) ?: ucfirst((string) $record->tier);
                $start_local = autoagora_promotion_local_datetime($record->starts_at);
                $end_local = autoagora_promotion_local_datetime($record->ends_at);
                ?>
                <li class="autoagora-promotion-timeline-item<?php echo $is_active ? ' is-active' : ''; ?>">
                    <span class="autoagora-promotion-timeline-marker" aria-hidden="true"></span>
                    <span class="autoagora-promotion-timeline-copy">
                        <span>
                            <strong><?php echo esc_html($tier_label); ?></strong>
                            <span class="autoagora-promotion-timeline-state"><?php echo $is_active ? 'Active' : 'Scheduled'; ?></span>
                        </span>
                        <?php if ($is_active) : ?>
                            <?php $end_timestamp = strtotime($record->ends_at . ' UTC'); ?>
                            <small>
                                <strong data-promotion-end-timestamp="<?php echo esc_attr($end_timestamp); ?>">
                                    <?php echo esc_html(autoagora_format_promotion_remaining($end_timestamp - time())); ?> left
                                </strong>
                                &middot; Ends <?php echo esc_html($end_local); ?>
                            </small>
                        <?php else : ?>
                            <small>
                                <?php echo esc_html(AutoAgora_Stripe_Gateway::duration_label($record->duration_seconds)); ?>
                                &middot; Starts <?php echo esc_html($start_local); ?>
                                &middot; Ends <?php echo esc_html($end_local); ?>
                            </small>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php
}

function autoagora_render_promotion_purchase_controls($listing_id)
{
    $listing_id = (int) $listing_id;
    if (!AutoAgora_Stripe_Gateway::is_ready()) {
        return;
    }
    if (!AutoAgora_Stripe_Gateway::packages()) {
        return;
    }

    $schedule = autoagora_get_seller_promotion_schedule($listing_id);
    $current_tier = autoagora_get_listing_promotion_tier($listing_id);
    $tier_options = array();
    foreach (AutoAgora_Promotion_Manager::tiers() as $tier_key => $tier_config) {
        $one_day = AutoAgora_Stripe_Gateway::package_for($tier_key, 1);
        if ($one_day) {
            $tier_options[$tier_key] = array(
                'label' => $tier_config['label'],
                'daily_amount_minor' => (int) $one_day['daily_amount_minor'],
                'description' => $tier_key === AutoAgora_Promotion_Manager::TIER_SHOWCASE
                    ? 'Maximum visibility above Lift and regular listings'
                    : 'Stand out above regular listings',
            );
        }
    }
    if (!$tier_options) {
        return;
    }

    $default_tier = isset($tier_options[$current_tier]) ? $current_tier : AutoAgora_Promotion_Manager::TIER_PRIORITY;
    if (!isset($tier_options[$default_tier])) {
        $default_tier = (string) array_key_first($tier_options);
    }
    $is_test = AutoAgora_Stripe_Gateway::mode() === 'test';
    $initial_preview = AutoAgora_Stripe_Gateway::schedule_preview($listing_id, $default_tier, 1);
    if (is_wp_error($initial_preview)) {
        return;
    }
    ?>
    <details class="autoagora-promotion-purchase">
        <summary class="btn btn-primary-gradient autoagora-promotion-trigger">
            <span class="autoagora-promotion-trigger-icon" aria-hidden="true">
                <i class="fas fa-bolt"></i>
            </span>
            <span><?php echo esc_html($schedule ? 'Manage promotion' : 'Promote listing'); ?></span>
            <i class="fas fa-chevron-up autoagora-promotion-trigger-chevron" aria-hidden="true"></i>
        </summary>

        <div class="autoagora-promotion-purchase-panel"
             data-currency="EUR"
             data-preview-signature="<?php echo esc_attr($initial_preview['signature']); ?>">
            <?php if ($is_test) : ?>
                <strong class="autoagora-stripe-test-label">Stripe sandbox test</strong>
            <?php endif; ?>

            <?php autoagora_render_seller_promotion_timeline($schedule); ?>

            <div class="autoagora-promotion-panel-heading">
                <strong><?php echo $schedule ? 'Add another promotion' : 'Choose your promotion'; ?></strong>
                <span>
                    <?php echo $schedule
                        ? 'Select a visibility level and duration. New time is added after the current schedule.'
                        : 'Select a visibility level and duration.'; ?>
                </span>
            </div>

            <div class="autoagora-promotion-tier-options" role="group" aria-label="Promotion type">
                <?php foreach ($tier_options as $tier_key => $option) : ?>
                    <?php $is_selected = $tier_key === $default_tier; ?>
                    <button type="button"
                            class="autoagora-promotion-tier-option<?php echo $is_selected ? ' is-selected' : ''; ?>"
                            data-tier="<?php echo esc_attr($tier_key); ?>"
                            data-label="<?php echo esc_attr($option['label']); ?>"
                            data-daily-amount="<?php echo esc_attr($option['daily_amount_minor']); ?>"
                            aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>">
                        <span class="autoagora-promotion-option-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <strong><?php echo esc_html($option['label']); ?></strong>
                        <small><?php echo esc_html($option['description']); ?></small>
                        <span class="autoagora-promotion-daily-price">
                            &euro;<?php echo esc_html(number_format_i18n($option['daily_amount_minor'] / 100, 2)); ?> / day
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="autoagora-promotion-duration-heading">
                <strong>Duration</strong>
                <span>Each day is 24 hours</span>
            </div>
            <div class="autoagora-promotion-day-options" role="group" aria-label="Promotion duration">
                <?php foreach (AutoAgora_Stripe_Gateway::allowed_days() as $days) : ?>
                    <button type="button"
                            class="autoagora-promotion-day-option<?php echo $days === 1 ? ' is-selected' : ''; ?>"
                            data-days="<?php echo esc_attr($days); ?>"
                            aria-pressed="<?php echo $days === 1 ? 'true' : 'false'; ?>">
                        <strong><?php echo esc_html($days); ?></strong>
                        <span><?php echo esc_html(_n('day', 'days', $days, 'bricks-child')); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="autoagora-promotion-total">
                <span>
                    <small>Total</small>
                    <strong class="autoagora-promotion-total-label"><?php echo esc_html($tier_options[$default_tier]['label']); ?> for 1 day</strong>
                </span>
                <strong class="autoagora-promotion-total-amount">
                    &euro;<?php echo esc_html(number_format_i18n($tier_options[$default_tier]['daily_amount_minor'] / 100, 2)); ?>
                </strong>
            </div>

            <div class="autoagora-promotion-queue-preview<?php echo $initial_preview['queued'] ? ' is-queued' : ' is-immediate'; ?>" aria-live="polite">
                <span class="autoagora-promotion-queue-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                <span>
                    <strong class="autoagora-promotion-preview-headline"><?php echo esc_html($initial_preview['headline']); ?></strong>
                    <small class="autoagora-promotion-preview-detail"><?php echo esc_html($initial_preview['detail']); ?></small>
                </span>
            </div>

            <button type="button"
                    class="btn btn-primary-gradient autoagora-buy-promotion"
                    data-listing-id="<?php echo esc_attr($listing_id); ?>">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Continue to secure checkout</span>
            </button>
            <span class="autoagora-promotion-checkout-status" role="status" aria-live="polite"></span>
        </div>
    </details>
    <?php
}
