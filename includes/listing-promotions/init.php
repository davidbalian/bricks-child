<?php
/**
 * Listing promotions bootstrap and public integration helpers.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/PromotionSchema.php';
require_once __DIR__ . '/PromotionRepository.php';
require_once __DIR__ . '/PromotionManager.php';
require_once __DIR__ . '/StripeGateway.php';
require_once __DIR__ . '/StripeCheckoutUI.php';

function autoagora_promotion_manager()
{
    static $manager = null;
    if ($manager === null) {
        $manager = new AutoAgora_Promotion_Manager(new AutoAgora_Promotion_Repository());
    }
    return $manager;
}

add_action('after_switch_theme', array('AutoAgora_Promotion_Schema', 'maybe_install'));
add_action('admin_init', array('AutoAgora_Promotion_Schema', 'maybe_install'));

add_filter('cron_schedules', static function ($schedules) {
    $schedules['autoagora_five_minutes'] = array(
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display' => 'Every five minutes (AutoAgora)',
    );
    return $schedules;
});

add_action('init', static function () {
    if (!wp_next_scheduled('autoagora_reconcile_listing_promotions')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'autoagora_five_minutes', 'autoagora_reconcile_listing_promotions');
    }
});
add_action('autoagora_reconcile_listing_promotions', static function () {
    if (AutoAgora_Promotion_Schema::exists()) {
        autoagora_promotion_manager()->reconcile_due();
    }
});

add_action('autoagora_purge_promotion_page_cache', static function () {
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
});

add_action('before_delete_post', static function ($post_id) {
    if (get_post_type($post_id) === 'car') {
        autoagora_promotion_manager()->handle_deleted_listing((int) $post_id);
    }
}, 10);

/**
 * Entry point for a payment provider after its webhook signature and payment
 * status have been verified. Payment provider/reference make it idempotent.
 */
function autoagora_grant_paid_listing_promotion($listing_id, $tier, $duration_seconds, $payment_provider, $payment_reference, $notes = '')
{
    return autoagora_promotion_manager()->grant_paid(
        (int) $listing_id,
        sanitize_key($tier),
        (int) $duration_seconds,
        $payment_provider,
        $payment_reference,
        $notes
    );
}

/**
 * Provider-neutral refund entry point after a refund webhook is authenticated.
 */
function autoagora_refund_paid_listing_promotion($payment_provider, $payment_reference)
{
    return autoagora_promotion_manager()->refund_paid($payment_provider, $payment_reference);
}

function autoagora_get_listing_promotion_tier($listing_id)
{
    $listing_id = (int) $listing_id;
    $managed = get_post_meta($listing_id, AutoAgora_Promotion_Manager::META_MANAGED, true) === '1';
    if ($managed) {
        $status = get_post_meta($listing_id, AutoAgora_Promotion_Manager::META_STATUS, true);
        $ends_at = get_post_meta($listing_id, AutoAgora_Promotion_Manager::META_ENDS_AT, true);
        if ($status !== AutoAgora_Promotion_Manager::STATUS_ACTIVE || ($ends_at && $ends_at <= gmdate('Y-m-d H:i:s'))) {
            return 'none';
        }
        $tier = get_post_meta($listing_id, AutoAgora_Promotion_Manager::META_TIER, true);
        return AutoAgora_Promotion_Manager::tier_priority($tier) > 0 ? $tier : 'none';
    }

    return get_post_meta($listing_id, 'is_featured', true) ? AutoAgora_Promotion_Manager::TIER_PRIORITY : 'none';
}

function autoagora_get_listing_promotion_priority($listing_id)
{
    return AutoAgora_Promotion_Manager::tier_priority(autoagora_get_listing_promotion_tier($listing_id));
}

function autoagora_listing_promotion_label($tier)
{
    return AutoAgora_Promotion_Manager::tier_label($tier);
}

if (is_admin()) {
    require_once __DIR__ . '/PromotionAdmin.php';
}
