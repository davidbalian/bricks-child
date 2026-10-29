<?php
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Listings
{
    public static function fields(int $id): array
    {
        $fields = [];
        $integers = ['year', 'mileage', 'price', 'hp', 'number_of_doors', 'number_of_seats', 'numowners', 'isantique'];
        foreach (Validation::FIELDS as $key) {
            $value = get_post_meta($id, $key, true);
            if (in_array($key, $integers, true) && $value !== '') { $value = (int) $value; }
            if (in_array($key, ['extras', 'vehiclehistory'], true)) { $value = is_array($value) ? $value : []; }
            $fields[$key] = $value;
        }
        return $fields;
    }
    public static function location(int $id): array
    {
        $result = [];
        foreach (['address', 'city', 'district', 'latitude', 'longitude'] as $key) {
            $value = get_post_meta($id, 'car_' . $key, true);
            $result[$key] = in_array($key, ['latitude', 'longitude'], true) ? (float) $value : $value;
        }
        return $result;
    }
    public static function version(int $id): string
    {
        clean_post_cache($id);
        return '"' . hash('sha256', wp_json_encode([
            self::fields($id), self::location($id), get_post_meta($id, 'car_images', true),
            get_post_status($id), \ListingStateManager::resolve_state($id), get_post_meta($id, '_ag_dealer_withdrawn', true),
            get_post_field('post_modified_gmt', $id), wp_get_post_terms($id, 'car_make', ['fields' => 'ids']),
        ])) . '"';
    }
    public static function summary(int $id): array
    {
        $post = get_post($id);
        $state = \ListingStateManager::resolve_state($id);
        $withdrawn = (bool) get_post_meta($id, '_ag_dealer_withdrawn', true);
        return ['id' => $id, 'url' => get_permalink($id), 'post_status' => $post->post_status,
            'listing_state' => $state, 'status' => $withdrawn ? 'withdrawn' : ($state !== 'active' ? $state : ($post->post_status === 'publish' ? 'published' : $post->post_status)),
            // Existing redirect coordinator redirects sold and expired single pages.
            'publicly_available' => $post->post_status === 'publish' && $state === 'active' && !$withdrawn,
            'in_marketplace' => $post->post_status === 'publish' && $state === 'active' && !$withdrawn,
            'version' => self::version($id)];
    }
    public static function owned(int $id, array $context)
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'car' || (int) $post->post_author !== $context['dealer'] || in_array($post->post_status, ['trash', 'auto-draft'], true)) {
            return Support::error('listing_not_found', 'Listing not found.', 404);
        }
        return $post;
    }
    public static function read(int $id, array $context)
    {
        if (is_wp_error($owned = self::owned($id, $context))) { return $owned; }
        $photos = [];
        foreach ((array) get_post_meta($id, 'car_images', true) as $image) {
            $photos[] = ['id' => (int) $image, 'url' => wp_get_attachment_url((int) $image)];
        }
        return self::summary($id) + ['fields' => self::fields($id), 'location' => self::location($id), 'photos' => $photos];
    }

    public static function save(\WP_REST_Request $request, array $context)
    {
        global $wpdb;
        $id = (int) $request['id'];
        $input = $request->get_json_params();
        if (!is_array($input) || array_is_list($input)) { return Support::error('invalid_json', 'Expected a JSON object.', 400); }
        if (!defined('AUTOAGORA_DEALER_RULES_VERIFIED') || !AUTOAGORA_DEALER_RULES_VERIFIED) {
            return Support::error('rules_not_verified', 'Listing writes are disabled until site business rules have been verified.', 503);
        }
        $current = [];
        $stockKey = '';
        if ($id) {
            if (is_wp_error($owned = self::owned($id, $context))) { return $owned; }
            if ($request->get_header('if-match') !== self::version($id)) { return Support::error('version_conflict', 'Listing changed. Read it again and retry with its current ETag.', 412); }
            $current = self::fields($id) + ['_location' => self::location($id)];
            // A partner may update all dealer-owned listings, but cannot claim another partner's external ID.
            if (array_key_exists('external_stock_id', $input)) { return Support::error('immutable_external_id', 'External stock IDs are set only on creation.'); }
        } else {
            $external = $input['external_stock_id'] ?? null;
            if (!is_string($external) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,127}$/D', $external)) {
                return Support::error('invalid_external_id', 'external_stock_id must be 1–128 letters, numbers, dot, colon, slash, underscore or hyphen.');
            }
            $stockKey = hash('sha256', $context['client'] . ':' . $context['dealer'] . ':' . $external);
            $existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Support::table('stocks') . ' WHERE stock_key = %s', $stockKey), ARRAY_A);
            if ($existing && (int) $existing['listing_id'] > 0) {
                $existingId = (int) $existing['listing_id'];
                if (get_post_meta($existingId, '_ag_dealer_request_key', true) === ($context['request_key'] ?? '') && !is_wp_error(self::owned($existingId, $context))) {
                    return new \WP_REST_Response(self::summary($existingId), 201, ['ETag' => self::version($existingId)]);
                }
                return Support::error('stock_already_exists', 'This external stock ID already has a listing. Use update.', 409, ['listing_id' => (int) $existing['listing_id']]);
            }
        }
        $validation = Validation::listing($input, $current, $context['dealer']);
        if (is_wp_error($validation)) { return $validation; }
        // Extension point for actual plan/allowance rules; defaults preserve the inspected theme's free submission flow.
        $eligibility = apply_filters('autoagora_dealer_api_can_save', true, $context['dealer'], $id, $input);
        if (is_wp_error($eligibility)) { return $eligibility; }
        if ($eligibility !== true) { return Support::error('listing_not_allowed', 'Your account cannot submit this listing.', 403); }
        $stage = ['ordered' => [], 'created' => []];
        $phase = 'photos';
        // An authorized bounded gallery batch can exceed PHP's usual 30-second form budget.
        if (isset($input['photos'])) {
            @set_time_limit(120);
            $budget = (int) ini_get('max_execution_time');
            if ($budget > 0 && $budget < 120) { return Support::error('image_runtime_unavailable', 'Image imports require a 120-second PHP execution budget.', 503); }
        }
        try {
            if (isset($input['photos'])) {
                $stage = Media::stage($input['photos'], $context['dealer'], $id, wp_generate_uuid4());
            } else {
                $stage['ordered'] = array_map('intval', (array) get_post_meta($id, 'car_images', true));
                if (count($stage['ordered']) < 2 || count($stage['ordered']) > 25) {
                    return Support::error('invalid_photos', 'Replace the gallery with 2–25 valid images.');
                }
            }
            // Human edits made while images were being downloaded must not be overwritten.
            if ($id && $request->get_header('if-match') !== self::version($id)) {
                Media::discard($stage['created']);
                return Support::error('version_conflict', 'Listing changed during image processing. Read it again.', 412);
            }
            $originalId = $id;
            $phase = 'save';
            $wpdb->query('START TRANSACTION');
            try {
                if (!$id) {
                    if (!$existing && !$wpdb->insert(Support::table('stocks'), ['stock_key' => $stockKey, 'client_id' => $context['client'], 'dealer_id' => $context['dealer'], 'external_id' => $external, 'listing_id' => 0, 'created_at' => time()])) {
                        throw new \RuntimeException('Could not reserve external stock ID.');
                    }
                    $id = wp_insert_post(['post_type' => 'car', 'post_status' => 'draft', 'post_author' => $context['dealer'],
                        'post_title' => $validation['fields']['year'] . ' ' . $validation['fields']['make'] . ' ' . $validation['fields']['model'],
                        'meta_input' => ['_ag_dealer_stock_key' => $stockKey, '_ag_dealer_request_key' => $context['request_key']]], true);
                    if (is_wp_error($id) || !$id) { throw new \RuntimeException('Could not create listing.'); }
                    if ($wpdb->update(Support::table('stocks'), ['listing_id' => $id], ['stock_key' => $stockKey]) === false) { throw new \RuntimeException('Could not save external stock ID.'); }
                    \ListingStateManager::assign_active_for_new_car($id);
                }
                $data = $validation['fields'];
                foreach ($validation['location'] as $key => $value) { $data['car_' . $key] = $value; }
                autoagora_write_car_fields($id, $data);
                $terms = wp_set_post_terms($id, $validation['terms'], 'car_make');
                if (is_wp_error($terms)) { throw new \RuntimeException('Could not assign vehicle taxonomy.'); }
                update_field('car_images', $stage['ordered'], $id);
                foreach ($stage['ordered'] as $image) {
                    // Existing images stay in place; only attach this operation's newly-created media.
                    if (in_array($image, $stage['created'], true)) {
                        if (is_wp_error(wp_update_post(['ID' => $image, 'post_parent' => $id], true))) { throw new \RuntimeException('Could not attach image.'); }
                    }
                }
                delete_post_meta($id, '_ag_dealer_withdrawn');
                if (class_exists('Listing_Details_Badge_Manager')) { \Listing_Details_Badge_Manager::update_badges_for_listing($id); }
                $submissionStatus = Support::submissionStatus($context['dealer'], $id);
                $saved = wp_update_post(['ID' => $id, 'post_title' => $data['year'] . ' ' . $data['make'] . ' ' . $data['model'],
                    'post_name' => $originalId ? get_post_field('post_name', $id) : sanitize_title($data['year'] . ' ' . $data['make'] . ' ' . $data['model'] . '-' . $id),
                    'post_status' => $submissionStatus], true);
                if (is_wp_error($saved) || $wpdb->last_error) { throw new \RuntimeException('Could not save listing.'); }
                // Confirm persisted state before declaring a successful submission.
                clean_post_cache($id);
                if (get_post_status($id) !== $submissionStatus || array_map('intval', (array) get_post_meta($id, 'car_images', true)) !== $stage['ordered']) {
                    throw new \RuntimeException('Listing did not reach the expected state.');
                }
                $wpdb->query('COMMIT');
            } catch (\Throwable $error) {
                $wpdb->query('ROLLBACK');
                if (is_int($id)) { clean_post_cache($id); }
                foreach ($stage['created'] as $image) { clean_post_cache($image); }
                throw $error;
            }
            Support::audit($originalId ? 'listing_updated' : 'listing_created', $context['client'], $context['dealer'], $id);
            // Replaced media are retained for recovery, never deleted just because a gallery changed.
            return new \WP_REST_Response(self::summary($id), $originalId ? 200 : 201, ['ETag' => self::version($id)]);
        } catch (\Throwable $error) {
            Media::discard($stage['created']);
            Support::audit('listing_write_failed', $context['client'], $context['dealer'], is_int($id) ? $id : 0);
            if ($phase === 'photos') { return Support::error('image_failed', $error->getMessage(), 502, ['field' => 'photos']); }
            return Support::error('listing_write_failed', 'Listing was not submitted. Check the photos and retry.', 502);
        }
    }

    public static function status(\WP_REST_Request $request, array $context)
    {
        if (!defined('AUTOAGORA_DEALER_RULES_VERIFIED') || !AUTOAGORA_DEALER_RULES_VERIFIED) {
            return Support::error('rules_not_verified', 'Listing writes are disabled until site business rules have been verified.', 503);
        }
        $id = (int) $request['id'];
        if (is_wp_error($owned = self::owned($id, $context))) { return $owned; }
        if ($request->get_header('if-match') !== self::version($id)) { return Support::error('version_conflict', 'Read the listing and supply its current ETag.', 412); }
        $input = $request->get_json_params();
        if (!is_array($input) || array_keys($input) !== ['status'] || !in_array($input['status'], ['sold', 'withdrawn'], true)) {
            return Support::error('invalid_status', 'Supply only status: sold or withdrawn.');
        }
        if ($input['status'] === 'sold') {
            if (\ListingStateManager::is_marked_expired($id) || get_post_meta($id, '_ag_dealer_withdrawn', true)) { return Support::error('invalid_transition', 'Expired or withdrawn listings cannot be marked sold.', 409); }
            \ListingStateManager::assign_state($id, \ListingStateManager::STATE_SOLD);
        } else {
            // Draft is reversible and avoids the existing trash hook that permanently deletes photos.
            $result = wp_update_post(['ID' => $id, 'post_status' => 'draft'], true);
            if (is_wp_error($result)) { return Support::error('withdrawal_failed', 'Could not withdraw listing.', 500); }
            update_post_meta($id, '_ag_dealer_withdrawn', 1);
        }
        clean_post_cache($id);
        if (($input['status'] === 'sold' && !\ListingStateManager::is_marked_sold($id))
            || ($input['status'] === 'withdrawn' && (get_post_status($id) !== 'draft' || !get_post_meta($id, '_ag_dealer_withdrawn', true)))) {
            return Support::error('status_write_failed', 'The listing did not reach the requested state. Read its status before retrying.', 503);
        }
        if (function_exists('rocket_clean_post')) { rocket_clean_post($id); }
        Support::audit('listing_' . $input['status'], $context['client'], $context['dealer'], $id);
        return new \WP_REST_Response(self::summary($id), 200, ['ETag' => self::version($id)]);
    }
}
