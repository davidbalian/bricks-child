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
        return $this->grant($listing_id, $tier, $duration_seconds, $starts_at_gmt, 'manual', $granted_by, '', '', $notes);
    }

    public function grant_paid($listing_id, $tier, $duration_seconds, $provider, $reference, $notes = '')
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
            return $this->validate_existing_payment_event($existing, $listing_id, $tier);
        }

        return $this->grant($listing_id, $tier, $duration_seconds, gmdate('Y-m-d H:i:s'), 'payment', 0, $provider, $reference, $notes);
    }

    private function grant($listing_id, $tier, $duration_seconds, $starts_at_gmt, $source, $granted_by, $provider, $reference, $notes)
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

        if (!$this->acquire_listing_lock($listing_id)) {
            return new WP_Error('promotion_listing_busy', 'This listing is receiving another promotion. Please retry.');
        }

        try {
            if ($provider && $reference) {
                $existing = $this->repository->find_payment_event($provider, $reference);
                if ($existing) {
                    return $this->validate_existing_payment_event($existing, $listing_id, $tier);
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
                'notes' => $notes !== '' ? sanitize_textarea_field($notes) : null,
            ));

            if (!$id) {
                if ($provider && $reference) {
                    $existing = $this->repository->find_payment_event($provider, $reference);
                    if ($existing) {
                        return $this->validate_existing_payment_event($existing, $listing_id, $tier);
                    }
                }
                return new WP_Error('promotion_insert_failed', 'The promotion could not be saved.');
            }

            $this->reconcile_listing($listing_id);
            do_action('autoagora_listing_promotion_granted', $id, $listing_id, $tier, $source);
            return $id;
        } finally {
            $this->release_listing_lock($listing_id);
        }
    }

    public function cancel($promotion_id)
    {
        $record = $this->repository->find((int) $promotion_id);
        if (!$record || !in_array($record->status, array(self::STATUS_ACTIVE, self::STATUS_SCHEDULED), true)) {
            return new WP_Error('promotion_not_cancellable', 'This promotion cannot be cancelled.');
        }
        $this->repository->update_status((int) $record->id, self::STATUS_CANCELLED);
        $this->reconcile_listing((int) $record->listing_id);
        return true;
    }

    public function refund_paid($provider, $reference)
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
            return true;
        }

        $this->repository->update_status((int) $record->id, self::STATUS_REFUNDED);
        $this->reconcile_listing((int) $record->listing_id);
        do_action('autoagora_listing_promotion_refunded', (int) $record->id, (int) $record->listing_id);
        return true;
    }

    public function reconcile_listing($listing_id)
    {
        $listing_id = (int) $listing_id;
        $now = gmdate('Y-m-d H:i:s');
        $this->repository->expire_due_for_listing($listing_id, $now);

        $active = $this->repository->active_for_listing($listing_id, $now);
        if (!$active) {
            $due = $this->repository->due_scheduled_for_listing($listing_id, $now);
            if ($due) {
                $this->repository->update_status((int) $due[0]->id, self::STATUS_ACTIVE);
                $active = array($this->repository->find((int) $due[0]->id));
            }
        }

        if ($active) {
            usort($active, static function ($a, $b) {
                return AutoAgora_Promotion_Manager::tier_priority($b->tier) <=> AutoAgora_Promotion_Manager::tier_priority($a->tier);
            });
            $this->sync_snapshot($listing_id, $active[0]);
        } else {
            $this->clear_snapshot($listing_id);
        }
    }

    public function reconcile_due()
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->repository->due_listing_ids($now) as $listing_id) {
            $this->reconcile_listing($listing_id);
        }
    }

    public function handle_deleted_listing($listing_id)
    {
        if (AutoAgora_Promotion_Schema::exists()) {
            $this->repository->cancel_for_deleted_listing((int) $listing_id);
        }
    }

    private function sync_snapshot($listing_id, $record)
    {
        $before = get_post_meta($listing_id, self::META_RECORD_ID, true);
        update_post_meta($listing_id, self::META_MANAGED, '1');
        update_post_meta($listing_id, self::META_TIER, $record->tier);
        update_post_meta($listing_id, self::META_PRIORITY, self::tier_priority($record->tier));
        update_post_meta($listing_id, self::META_STATUS, self::STATUS_ACTIVE);
        update_post_meta($listing_id, self::META_RECORD_ID, (int) $record->id);
        update_post_meta($listing_id, self::META_STARTS_AT, $record->starts_at);
        update_post_meta($listing_id, self::META_ENDS_AT, $record->ends_at);
        if ((string) $before !== (string) $record->id) {
            $this->invalidate_listing_queries();
        }
    }

    private function clear_snapshot($listing_id)
    {
        $was_active = get_post_meta($listing_id, self::META_STATUS, true) === self::STATUS_ACTIVE;
        update_post_meta($listing_id, self::META_MANAGED, '1');
        update_post_meta($listing_id, self::META_TIER, 'none');
        update_post_meta($listing_id, self::META_PRIORITY, 0);
        update_post_meta($listing_id, self::META_STATUS, 'none');
        delete_post_meta($listing_id, self::META_RECORD_ID);
        delete_post_meta($listing_id, self::META_STARTS_AT);
        delete_post_meta($listing_id, self::META_ENDS_AT);
        if ($was_active) {
            $this->invalidate_listing_queries();
        }
    }

    private function invalidate_listing_queries()
    {
        if (function_exists('car_listings_query_cache_bump_generation')) {
            car_listings_query_cache_bump_generation();
        }
    }

    private function normalize_gmt($value, $fallback)
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $fallback;
        }
        return $value;
    }

    private function validate_existing_payment_event($record, $listing_id, $tier)
    {
        if ((int) $record->listing_id !== (int) $listing_id || (string) $record->tier !== (string) $tier) {
            return new WP_Error('promotion_payment_reference_conflict', 'This payment reference is already attached to a different promotion.');
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
