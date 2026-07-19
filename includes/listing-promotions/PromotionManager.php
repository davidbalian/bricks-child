<?php
/**
 * Promotion lifecycle and current-state snapshot manager.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Promotion_Manager
{
    const TIER_PRIORITY = 'priority';
    const TIER_SHOWCASE = 'showcase';

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    const META_MANAGED = '_autoagora_promotion_managed';
    const META_TIER = '_autoagora_promotion_tier';
    const META_PRIORITY = '_autoagora_promotion_priority';
    const META_STATUS = '_autoagora_promotion_status';
    const META_RECORD_ID = '_autoagora_promotion_record_id';
    const META_STARTS_AT = '_autoagora_promotion_starts_at';
    const META_ENDS_AT = '_autoagora_promotion_ends_at';

    private $repository;

    public function __construct(AutoAgora_Promotion_Repository $repository)
    {
        $this->repository = $repository;
    }

    public static function tiers()
    {
        return array(
            self::TIER_PRIORITY => array('label' => 'AutoAgora Lift', 'priority' => 10),
            self::TIER_SHOWCASE => array('label' => 'AutoAgora Showcase', 'priority' => 20),
        );
    }

    public static function tier_label($tier)
    {
        $tiers = self::tiers();
        return isset($tiers[$tier]) ? $tiers[$tier]['label'] : '';
    }

    public static function tier_priority($tier)
    {
        $tiers = self::tiers();
        return isset($tiers[$tier]) ? (int) $tiers[$tier]['priority'] : 0;
    }

    public function grant_manual($listing_id, $tier, $duration_seconds, $starts_at_gmt, $granted_by, $notes = '')
    {
        return $this->grant($listing_id, $tier, $duration_seconds, $starts_at_gmt, 'manual', $granted_by, '', '', $notes, array());
    }

    public function grant_paid($listing_id, $tier, $duration_seconds, $provider, $reference, $notes = '', array $payment_data = array())
    {
        if (!AutoAgora_Promotion_Schema::exists()) {
            return new WP_Error('promotion_table_missing', 'The listing promotions table is unavailable.');
        }
        $provider = sanitize_key($provider);
        $reference = sanitize_text_field($reference);
        $provider = substr($provider, 0, 32);
        $reference = function_exists('mb_substr') ? mb_substr($reference, 0, 191) : substr($reference, 0, 191);
        if ($provider === '' || $reference === '') {
            return new WP_Error('promotion_payment_reference', 'A payment provider and unique payment reference are required.');
        }

        $existing = $this->repository->find_payment_event($provider, $reference);
        if ($existing) {
            return $this->validate_existing_payment_event($existing, $listing_id, $tier, $duration_seconds, $payment_data);
        }

        return $this->grant($listing_id, $tier, $duration_seconds, gmdate('Y-m-d H:i:s'), 'payment', 0, $provider, $reference, $notes, $payment_data);
    }

    private function grant($listing_id, $tier, $duration_seconds, $starts_at_gmt, $source, $granted_by, $provider, $reference, $notes, array $payment_data)
    {
        $listing_id = (int) $listing_id;
        $duration_seconds = (int) $duration_seconds;
        $post = get_post($listing_id);
        if (!$post || $post->post_type !== 'car') {
            return new WP_Error('promotion_listing_invalid', 'The selected listing does not exist or is not a car.');
        }
        if ($post->post_status !== 'publish' || (class_exists('ListingStateManager') && ListingStateManager::resolve_state($listing_id) !== ListingStateManager::STATE_ACTIVE)) {
            return new WP_Error('promotion_listing_inactive', 'Only published, active listings can receive a promotion.');
        }
        if (!isset(self::tiers()[$tier])) {
            return new WP_Error('promotion_tier_invalid', 'The selected promotion tier is invalid.');
        }
        if ($duration_seconds < HOUR_IN_SECONDS || $duration_seconds > YEAR_IN_SECONDS) {
            return new WP_Error('promotion_duration_invalid', 'Promotion duration must be between one hour and one year.');
        }
        if (!AutoAgora_Promotion_Schema::exists()) {
            return new WP_Error('promotion_table_missing', 'The listing promotions table is unavailable.');
        }

        $amount_minor = $source === 'payment' && isset($payment_data['amount_minor']) ? max(0, (int) $payment_data['amount_minor']) : 0;
        $currency = $source === 'payment' && isset($payment_data['currency']) ? strtolower(sanitize_key($payment_data['currency'])) : '';
        $checkout_session_id = $source === 'payment' && isset($payment_data['stripe_checkout_session_id'])
            ? sanitize_text_field($payment_data['stripe_checkout_session_id'])
            : '';
        $currency = substr($currency, 0, 3);
        $checkout_session_id = function_exists('mb_substr') ? mb_substr($checkout_session_id, 0, 191) : substr($checkout_session_id, 0, 191);
        if ($source === 'payment' && ($amount_minor < 50 || $currency !== 'eur' || strpos($checkout_session_id, 'cs_') !== 0)) {
            return new WP_Error('promotion_payment_snapshot_invalid', 'The paid promotion details are incomplete.');
        }

        if (!$this->acquire_listing_lock($listing_id)) {
            return new WP_Error('promotion_listing_busy', 'This listing is receiving another promotion. Please retry.');
        }

        try {
            if ($provider && $reference) {
                $existing = $this->repository->find_payment_event($provider, $reference);
                if ($existing) {
                    return $this->validate_existing_payment_event($existing, $listing_id, $tier, $duration_seconds, $payment_data);
                }
            }

            $now = gmdate('Y-m-d H:i:s');
            $requested_start = $this->normalize_gmt($starts_at_gmt, $now);
            if ($requested_start < $now) {
                $requested_start = $now;
            }
            $reserved_end = $this->repository->latest_reserved_end($listing_id, $requested_start);
            $start = ($reserved_end && $reserved_end > $requested_start) ? $reserved_end : $requested_start;
            $end = gmdate('Y-m-d H:i:s', strtotime($start . ' UTC') + $duration_seconds);
            $status = ($start <= $now) ? self::STATUS_ACTIVE : self::STATUS_SCHEDULED;

            $id = $this->repository->insert(array(
                'listing_id' => $listing_id,
                'listing_title_snapshot' => $this->listing_title_snapshot($post->post_title),
                'seller_id_snapshot' => (int) $post->post_author,
                'tier' => $tier,
                'status' => $status,
                'source' => $source,
                'starts_at' => $start,
                'ends_at' => $end,
                'duration_seconds' => $duration_seconds,
                'remaining_seconds' => $duration_seconds,
                'granted_by' => $granted_by ? (int) $granted_by : null,
                'payment_provider' => $provider !== '' ? $provider : null,
                'payment_reference' => $reference !== '' ? $reference : null,
                'amount_minor' => $amount_minor,
                'currency' => $currency !== '' ? $currency : null,
                'refunded_amount_minor' => 0,
                'stripe_checkout_session_id' => $checkout_session_id !== '' ? $checkout_session_id : null,
                'notes' => $notes !== '' ? sanitize_textarea_field($notes) : null,
            ));

            if (!$id) {
                if ($provider && $reference) {
                    $existing = $this->repository->find_payment_event($provider, $reference);
                    if ($existing) {
                        return $this->validate_existing_payment_event($existing, $listing_id, $tier, $duration_seconds, $payment_data);
                    }
                }
                return new WP_Error('promotion_insert_failed', 'The promotion could not be saved.');
            }

            $reconciled = $this->reconcile_listing($listing_id);
            if (is_wp_error($reconciled)) {
                return $reconciled;
            }
            do_action('autoagora_listing_promotion_granted', $id, $listing_id, $tier, $source);
            return $id;
        } finally {
            $this->release_listing_lock($listing_id);
        }
    }

    public function cancel($promotion_id)
    {
        $record = $this->repository->find((int) $promotion_id);
        if (!$record) {
            return new WP_Error('promotion_not_cancellable', 'This promotion cannot be cancelled.');
        }
        if ($record->status === self::STATUS_CANCELLED) {
            $reconciled = $this->reconcile_listing((int) $record->listing_id);
            return is_wp_error($reconciled) ? $reconciled : true;
        }
        if (!in_array($record->status, array(self::STATUS_ACTIVE, self::STATUS_SCHEDULED), true)) {
            return new WP_Error('promotion_not_cancellable', 'This promotion cannot be cancelled.');
        }
        if (!$this->repository->update_status((int) $record->id, self::STATUS_CANCELLED)) {
            return new WP_Error('promotion_status_update_failed', 'The promotion status could not be updated.');
        }
        $reconciled = $this->reconcile_listing((int) $record->listing_id);
        if (is_wp_error($reconciled)) {
            return $reconciled;
        }
        return true;
    }

    public function refund_paid($provider, $reference, $amount_minor = 0)
    {
        if (!AutoAgora_Promotion_Schema::exists()) {
            return new WP_Error('promotion_table_missing', 'The listing promotions table is unavailable.');
        }
        $provider = substr(sanitize_key($provider), 0, 32);
        $reference = sanitize_text_field($reference);
        $reference = function_exists('mb_substr') ? mb_substr($reference, 0, 191) : substr($reference, 0, 191);
        $record = $this->repository->find_payment_event($provider, $reference);
        if (!$record || $record->source !== 'payment') {
            return new WP_Error('promotion_payment_not_found', 'No paid promotion matches this payment reference.');
        }
        if ($record->status === self::STATUS_REFUNDED) {
            $reconciled = $this->reconcile_listing((int) $record->listing_id);
            return is_wp_error($reconciled) ? $reconciled : true;
        }

        $refund_amount = (int) $amount_minor > 0 ? (int) $amount_minor : (int) $record->amount_minor;
        if ((int) $record->amount_minor > 0) {
            $refund_amount = min($refund_amount, (int) $record->amount_minor);
        }
        if (!$this->repository->mark_refunded((int) $record->id, $refund_amount)) {
            return new WP_Error('promotion_status_update_failed', 'The promotion refund status could not be saved.');
        }
        $reconciled = $this->reconcile_listing((int) $record->listing_id);
        if (is_wp_error($reconciled)) {
            return $reconciled;
        }
        do_action('autoagora_listing_promotion_refunded', (int) $record->id, (int) $record->listing_id);
        return true;
    }

    public function reconcile_listing($listing_id)
    {
        $listing_id = (int) $listing_id;
        $now = gmdate('Y-m-d H:i:s');
        if ($this->repository->expire_due_for_listing($listing_id, $now) === false) {
            return new WP_Error('promotion_expiry_update_failed', 'Expired promotions could not be updated.');
        }

        $active = $this->repository->active_for_listing($listing_id, $now);
        if (!$active) {
            $due = $this->repository->due_scheduled_for_listing($listing_id, $now);
            if ($due) {
                if (!$this->repository->update_status((int) $due[0]->id, self::STATUS_ACTIVE)) {
                    return new WP_Error('promotion_status_update_failed', 'The scheduled promotion could not be activated.');
                }
                $activated = $this->repository->find((int) $due[0]->id);
                if (!$activated) {
                    return new WP_Error('promotion_record_missing', 'The activated promotion could not be loaded.');
                }
                $active = array($activated);
            }
        }

        if ($active) {
            usort($active, static function ($a, $b) {
                return AutoAgora_Promotion_Manager::tier_priority($b->tier) <=> AutoAgora_Promotion_Manager::tier_priority($a->tier);
            });
            return $this->sync_snapshot($listing_id, $active[0]);
        }
        return $this->clear_snapshot($listing_id);
    }

    public function reconcile_due()
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->repository->due_listing_ids($now) as $listing_id) {
            $result = $this->reconcile_listing($listing_id);
            if (is_wp_error($result)) {
                error_log('AutoAgora promotion reconciliation error for listing ' . (int) $listing_id . ': ' . $result->get_error_code());
                if (!wp_next_scheduled('autoagora_reconcile_single_listing_promotion', array((int) $listing_id))) {
                    wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'autoagora_reconcile_single_listing_promotion', array((int) $listing_id));
                }
            }
        }
    }

    public function handle_deleted_listing($listing_id)
    {
        if (AutoAgora_Promotion_Schema::exists()) {
            $post = get_post((int) $listing_id);
            if ($post && AutoAgora_Promotion_Schema::is_current()) {
                $snapshot_updated = $this->repository->preserve_listing_snapshot(
                    (int) $listing_id,
                    $this->listing_title_snapshot($post->post_title),
                    (int) $post->post_author
                );
                if ($snapshot_updated === false) {
                    error_log('AutoAgora promotion listing snapshot could not be preserved for deleted listing ' . (int) $listing_id . '.');
                }
            }
            if ($this->repository->cancel_for_deleted_listing((int) $listing_id) === false) {
                error_log('AutoAgora promotion cancellation failed for deleted listing ' . (int) $listing_id . '.');
            }
        }
    }

    private function sync_snapshot($listing_id, $record)
    {
        $before = $this->snapshot_values($listing_id);
        $desired = array(
            'managed' => '1',
            'tier' => (string) $record->tier,
            'priority' => (string) self::tier_priority($record->tier),
            'status' => self::STATUS_ACTIVE,
            'record_id' => (string) (int) $record->id,
            'starts_at' => (string) $record->starts_at,
            'ends_at' => (string) $record->ends_at,
        );
        update_post_meta($listing_id, self::META_MANAGED, '1');
        update_post_meta($listing_id, self::META_TIER, $record->tier);
        update_post_meta($listing_id, self::META_PRIORITY, self::tier_priority($record->tier));
        update_post_meta($listing_id, self::META_STATUS, self::STATUS_ACTIVE);
        update_post_meta($listing_id, self::META_RECORD_ID, (int) $record->id);
        update_post_meta($listing_id, self::META_STARTS_AT, $record->starts_at);
        update_post_meta($listing_id, self::META_ENDS_AT, $record->ends_at);
        if ($this->snapshot_values($listing_id) !== $desired) {
            return new WP_Error('promotion_snapshot_update_failed', 'The promotion marketplace snapshot could not be saved.');
        }
        if ($before !== $desired) {
            $this->invalidate_listing_queries($listing_id);
        }
        return true;
    }

    private function clear_snapshot($listing_id)
    {
        $before = $this->snapshot_values($listing_id);
        $desired = array(
            'managed' => '1',
            'tier' => 'none',
            'priority' => '0',
            'status' => 'none',
            'record_id' => '',
            'starts_at' => '',
            'ends_at' => '',
        );
        update_post_meta($listing_id, self::META_MANAGED, '1');
        update_post_meta($listing_id, self::META_TIER, 'none');
        update_post_meta($listing_id, self::META_PRIORITY, 0);
        update_post_meta($listing_id, self::META_STATUS, 'none');
        delete_post_meta($listing_id, self::META_RECORD_ID);
        delete_post_meta($listing_id, self::META_STARTS_AT);
        delete_post_meta($listing_id, self::META_ENDS_AT);
        if ($this->snapshot_values($listing_id) !== $desired) {
            return new WP_Error('promotion_snapshot_update_failed', 'The promotion marketplace snapshot could not be cleared.');
        }
        if ($before !== $desired) {
            $this->invalidate_listing_queries($listing_id);
        }
        return true;
    }

    private function snapshot_values($listing_id)
    {
        return array(
            'managed' => (string) get_post_meta($listing_id, self::META_MANAGED, true),
            'tier' => (string) get_post_meta($listing_id, self::META_TIER, true),
            'priority' => (string) get_post_meta($listing_id, self::META_PRIORITY, true),
            'status' => (string) get_post_meta($listing_id, self::META_STATUS, true),
            'record_id' => (string) get_post_meta($listing_id, self::META_RECORD_ID, true),
            'starts_at' => (string) get_post_meta($listing_id, self::META_STARTS_AT, true),
            'ends_at' => (string) get_post_meta($listing_id, self::META_ENDS_AT, true),
        );
    }

    private function invalidate_listing_queries($listing_id)
    {
        if (function_exists('car_listings_query_cache_bump_generation')) {
            car_listings_query_cache_bump_generation();
        }
        clean_post_cache((int) $listing_id);
        if (!wp_next_scheduled('autoagora_purge_promotion_page_cache')) {
            wp_schedule_single_event(time() + 5, 'autoagora_purge_promotion_page_cache');
        }
        do_action('autoagora_listing_promotion_snapshot_changed', (int) $listing_id);
    }

    private function normalize_gmt($value, $fallback)
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $fallback;
        }
        return $value;
    }

    private function listing_title_snapshot($title)
    {
        $title = wp_strip_all_tags((string) $title, true);
        return function_exists('mb_substr') ? mb_substr($title, 0, 255) : substr($title, 0, 255);
    }

    private function validate_existing_payment_event($record, $listing_id, $tier, $duration_seconds = 0, array $payment_data = array())
    {
        if ((int) $record->listing_id !== (int) $listing_id || (string) $record->tier !== (string) $tier) {
            return new WP_Error('promotion_payment_reference_conflict', 'This payment reference is already attached to a different promotion.');
        }
        if ((int) $duration_seconds > 0 && (int) $record->duration_seconds !== (int) $duration_seconds) {
            return new WP_Error('promotion_payment_reference_conflict', 'This payment reference has different promotion terms.');
        }
        if (isset($payment_data['amount_minor']) && (int) $record->amount_minor !== (int) $payment_data['amount_minor']) {
            return new WP_Error('promotion_payment_reference_conflict', 'This payment reference has a different paid amount.');
        }
        if (isset($payment_data['currency']) && strtolower((string) $record->currency) !== strtolower((string) $payment_data['currency'])) {
            return new WP_Error('promotion_payment_reference_conflict', 'This payment reference has a different currency.');
        }
        if (isset($payment_data['stripe_checkout_session_id']) && (string) $record->stripe_checkout_session_id !== (string) $payment_data['stripe_checkout_session_id']) {
            return new WP_Error('promotion_payment_reference_conflict', 'This payment reference has a different Checkout Session.');
        }
        $reconciled = $this->reconcile_listing((int) $record->listing_id);
        if (is_wp_error($reconciled)) {
            return $reconciled;
        }
        return (int) $record->id;
    }

    private function acquire_listing_lock($listing_id)
    {
        global $wpdb;
        $lock_name = 'autoagora_promotion_' . (int) $listing_id;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name)) === 1;
    }

    private function release_listing_lock($listing_id)
    {
        global $wpdb;
        $lock_name = 'autoagora_promotion_' . (int) $listing_id;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}
