<?php
/** Applies one validated queue item without doing any network scraping. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Bazaraki_Sync_Applier
{
    private const PROFILE_META = '_autoagora_sync_profile_id';
    private const MISSING_META = '_autoagora_sync_missing_count';
    private const EXPIRED_META = '_autoagora_sync_expired';
    private const HASHES_META = '_autoagora_sync_image_hashes';
    private const URLS_META = '_autoagora_sync_image_urls';

    /** @param array<string,mixed> $job @param array<string,mixed> $profile */
    public static function apply(array $job, array $profile, string $package_path): string
    {
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : array();
        $action = (string) ($job['action'] ?? '');
        if (!empty($profile['dry_run'])) {
            return $action === 'missing' ? 'review' : 'complete';
        }

        AutoAgora_Car_Json_Import_Runner::beginAdminNotificationSuppression();
        try {
            if ($action === 'seen') {
                return self::markSeen((string) $job['source_id'], (string) $job['profile_id']);
            }
            if ($action === 'missing') {
                return self::markMissing((string) $job['source_id'], $profile);
            }
            if ($action === 'upsert') {
                return self::upsert($payload, $profile, $package_path, (string) $job['profile_id']);
            }
            throw new RuntimeException(__('Unknown Bazaraki sync action.', 'bricks-child'));
        } finally {
            AutoAgora_Car_Json_Import_Runner::endAdminNotificationSuppression();
        }
    }

    private static function find(string $source_id): int
    {
        return AutoAgora_Car_Json_Import_Validator::findExistingImport('bazaraki', $source_id);
    }

    private static function markSeen(string $source_id, string $profile_id): string
    {
        $post_id = self::find($source_id);
        if ($post_id <= 0) {
            return 'review';
        }
        update_post_meta($post_id, self::PROFILE_META, $profile_id);
        delete_post_meta($post_id, self::MISSING_META);
        if ((int) get_post_meta($post_id, self::EXPIRED_META, true) === 1) {
            if (class_exists('ListingStateManager') && ListingStateManager::resolve_state($post_id) === ListingStateManager::STATE_EXPIRED) {
                ListingStateManager::assign_state($post_id, ListingStateManager::STATE_ACTIVE);
            }
            delete_post_meta($post_id, self::EXPIRED_META);
        }
        update_post_meta($post_id, '_autoagora_sync_seen_at', current_time('mysql', true));
        return 'complete';
    }

    /** @param array<string,mixed> $profile */
    private static function markMissing(string $source_id, array $profile): string
    {
        $post_id = self::find($source_id);
        if ($post_id <= 0) {
            return 'complete';
        }
        $count = max(0, (int) get_post_meta($post_id, self::MISSING_META, true)) + 1;
        update_post_meta($post_id, self::MISSING_META, $count);
        $required = max(2, (int) ($profile['missing_confirmations'] ?? 3));
        if ($count < $required) {
            return 'review';
        }
        if (class_exists('ListingStateManager')) {
            $state = ListingStateManager::resolve_state($post_id);
            if ($state === ListingStateManager::STATE_SOLD) {
                return 'complete';
            }
            ListingStateManager::assign_state($post_id, ListingStateManager::STATE_EXPIRED);
        } else {
            AutoAgora_Car_Json_Import_Runner::updateField('listing_state', 'expired', $post_id);
        }
        update_post_meta($post_id, self::EXPIRED_META, 1);
        return 'complete';
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $profile */
    private static function upsert(array $payload, array $profile, string $package_path, string $profile_id): string
    {
        $row = isset($payload['row']) && is_array($payload['row']) ? $payload['row'] : array();
        if (empty($row['valid']) || empty($row['listing']) || !is_array($row['listing'])) {
            throw new RuntimeException(__('The queued sync listing is not valid.', 'bricks-child'));
        }
        $listing = $row['listing'];
        $post_id = self::find((string) $listing['source_id']);
        if ($post_id <= 0) {
            $result = AutoAgora_Car_Json_Import_Runner::importRow($row, $package_path, (int) $profile['author_id']);
            if (is_wp_error($result)) {
                throw new RuntimeException($result->get_error_message());
            }
            $post_id = (int) ($result['post_id'] ?? 0);
            if ($post_id <= 0 || ($result['status'] ?? '') !== 'imported') {
                throw new RuntimeException(__('The new sync listing was not imported.', 'bricks-child'));
            }
            self::storeSyncMeta($post_id, $profile_id, $listing, $payload);
            return 'complete';
        }

        $changed = array_values(array_filter(array_map('sanitize_key', (array) ($payload['changed_fields'] ?? array()))));
        $baseline = !empty($payload['baseline']);
        $field_names = array(
            'make', 'model', 'year', 'mileage', 'price', 'engine_capacity', 'fuel_type',
            'transmission', 'body_type', 'drive_type', 'exterior_color', 'interior_color',
            'description', 'number_of_doors', 'number_of_seats', 'motuntil', 'extras',
            'vehiclehistory', 'hp', 'numowners', 'isantique', 'availability', 'car_city',
            'car_district', 'car_latitude', 'car_longitude', 'car_address',
        );
        foreach ($field_names as $field) {
            if (($baseline || in_array($field, $changed, true)) && array_key_exists($field, $listing)) {
                AutoAgora_Car_Json_Import_Runner::updateField($field, $listing[$field], $post_id);
            }
        }
        if ($baseline || array_intersect(array('make', 'model'), $changed)) {
            AutoAgora_Car_Json_Import_Runner::assignTaxonomy($post_id, (string) $listing['make'], (string) $listing['model']);
        }
        if ($baseline || array_intersect(array('title', 'make', 'model', 'year', 'description'), $changed)) {
            $updated = wp_update_post(array(
                'ID' => $post_id,
                'post_title' => sanitize_text_field(sprintf('%d %s %s', (int) $listing['year'], $listing['make'], $listing['model'])),
                'post_content' => wp_kses_post((string) $listing['description']),
            ), true);
            if (is_wp_error($updated)) {
                throw new RuntimeException($updated->get_error_message());
            }
        }

        $image_fields = array('source_image_urls', 'images', 'car_images');
        $incoming_hashes = array_values(array_filter(array_map('sanitize_text_field', (array) ($payload['image_hashes'] ?? array()))));
        $stored_hashes = array_values((array) get_post_meta($post_id, self::HASHES_META, true));
        $gallery_changed = !empty(array_intersect($image_fields, $changed)) && $incoming_hashes !== $stored_hashes;
        if (!$baseline && $gallery_changed) {
            $old_ids = array_values(array_filter(array_map('absint', (array) get_post_meta($post_id, 'car_images', true))));
            if (function_exists('get_field')) {
                $acf_ids = get_field('car_images', $post_id, false);
                if (is_array($acf_ids)) {
                    $old_ids = array_values(array_filter(array_map('absint', $acf_ids)));
                }
            }
            $new_ids = AutoAgora_Car_Json_Import_Runner::importImages($post_id, $package_path, (array) $listing['car_images']);
            AutoAgora_Car_Json_Import_Runner::updateField('car_images', $new_ids, $post_id);
            foreach (array_diff($old_ids, $new_ids) as $attachment_id) {
                if ((int) get_post_field('post_parent', $attachment_id) === $post_id) {
                    wp_delete_attachment($attachment_id, true);
                }
            }
        }

        update_post_meta($post_id, '_autoagora_import_source_url', esc_url_raw((string) $listing['source_url']));
        self::storeSyncMeta($post_id, $profile_id, $listing, $payload);
        do_action('acf/save_post', $post_id);
        if (class_exists('Listing_Details_Badge_Manager')) {
            Listing_Details_Badge_Manager::update_badges_for_listing($post_id);
        }
        return self::markSeen((string) $listing['source_id'], $profile_id);
    }

    /** @param array<string,mixed> $listing @param array<string,mixed> $payload */
    private static function storeSyncMeta(int $post_id, string $profile_id, array $listing, array $payload): void
    {
        update_post_meta($post_id, self::PROFILE_META, $profile_id);
        update_post_meta($post_id, '_autoagora_synced_at', current_time('mysql', true));
        update_post_meta($post_id, self::HASHES_META, array_values(array_filter(array_map('sanitize_text_field', (array) ($payload['image_hashes'] ?? array())))));
        update_post_meta($post_id, self::URLS_META, array_values(array_filter(array_map('esc_url_raw', (array) ($payload['source_image_urls'] ?? ($listing['source_image_urls'] ?? array()))))));
        delete_post_meta($post_id, self::MISSING_META);
    }
}
