<?php
/**
 * My Listings Stats Manager
 *
 * Calculates aggregate listing statistics for a dealership user.
 *
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

require_once dirname(__DIR__, 2) . '/user-manage-listings/refresh-listing/RefreshListingManager.php';

class MyListingsStatsManager {
    /**
     * Build aggregate stats for a given user.
     *
     * @param int $user_id User ID.
     * @return array<string,int|float>
     */
    public function get_stats_for_user(int $user_id): array {
        $listing_ids = $this->get_user_listing_ids($user_id);
        if (empty($listing_ids)) {
            return $this->get_empty_stats();
        }

        $stats = $this->get_empty_stats();
        $stats['total_listings'] = count($listing_ids);

        foreach ($listing_ids as $listing_id) {
            $this->accumulate_status_stats($stats, $listing_id);
            $this->accumulate_performance_stats($stats, $listing_id);
        }

        if ($stats['total_listings'] > 0) {
            $stats['average_views_per_listing'] = round(
                $stats['total_views'] / $stats['total_listings'],
                1
            );
        }

        return $stats;
    }

    /**
     * Fetch all listing IDs for a user.
     *
     * @param int $user_id User ID.
     * @return int[]
     */
    private function get_user_listing_ids(int $user_id): array {
        $post_ids = get_posts(array(
            'post_type'      => 'car',
            'author'         => $user_id,
            'post_status'    => array('publish', 'pending'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        return array_map('intval', $post_ids);
    }

    /**
     * Increment status-related counters.
     *
     * @param array<string,int|float> $stats      Stats array by reference.
     * @param int                     $listing_id Listing post ID.
     * @return void
     */
    private function accumulate_status_stats(array &$stats, int $listing_id): void {
        if (ListingStateManager::is_marked_sold($listing_id)) {
            $stats['sold_listings']++;
            return;
        }

        if (ListingStateManager::is_marked_expired($listing_id)) {
            $stats['expired_listings']++;
            return;
        }

        $post_status = get_post_status($listing_id);
        if ($post_status === 'publish') {
            $stats['active_listings']++;
            if ($this->is_published_listing_stale_for_reminder($listing_id)) {
                $stats['stale_listings']++;
            }
            return;
        }

        if ($post_status === 'pending') {
            $stats['pending_listings']++;
        }
    }

    /**
     * Increment views and lead counters.
     *
     * @param array<string,int|float> $stats      Stats array by reference.
     * @param int                     $listing_id Listing post ID.
     * @return void
     */
    private function accumulate_performance_stats(array &$stats, int $listing_id): void {
        $stats['total_views'] += $this->get_int_meta($listing_id, 'total_views_count');
        $stats['unique_views'] += $this->get_int_meta($listing_id, 'unique_views_count');
        $stats['total_leads'] += $this->get_int_meta($listing_id, 'call_button_clicks');
        $stats['total_leads'] += $this->get_int_meta($listing_id, 'whatsapp_button_clicks');
    }

    /**
     * "Posted" time shown on car cards: publication_date meta if set, else WP post date string.
     * Mirrors render_car_card() in car-card.php.
     *
     * @param int $listing_id Listing post ID.
     * @return int|false Unix timestamp or false.
     */
    private function get_listing_card_posted_timestamp(int $listing_id) {
        $publication_date = get_post_meta($listing_id, 'publication_date', true);
        if (empty($publication_date)) {
            $publication_date = get_the_date('Y-m-d H:i:s', $listing_id);
        }
        if ($publication_date === '') {
            return false;
        }
        $ts = strtotime((string) $publication_date);

        return $ts === false ? false : (int) $ts;
    }

    /**
     * Stale if either WP post_date (browse uses orderby date) or the card display date is older
     * than the refresh cooldown. Refresh updates both in RefreshListingManager::perform_refresh().
     *
     * @param int $listing_id Listing post ID.
     * @return bool
     */
    private function is_published_listing_stale_for_reminder(int $listing_id): bool {
        $cutoff_ts = current_time('timestamp')
            - (RefreshListingManager::REFRESH_COOLDOWN_DAYS * DAY_IN_SECONDS);

        $sort_ts = get_post_time('U', false, $listing_id);
        if ($sort_ts !== false && (int) $sort_ts < $cutoff_ts) {
            return true;
        }

        $card_ts = $this->get_listing_card_posted_timestamp($listing_id);
        if ($card_ts !== false && $card_ts < $cutoff_ts) {
            return true;
        }

        return false;
    }

    /**
     * Safe integer meta reader.
     *
     * @param int    $listing_id Listing post ID.
     * @param string $meta_key   Meta key.
     * @return int
     */
    private function get_int_meta(int $listing_id, string $meta_key): int {
        $raw = get_post_meta($listing_id, $meta_key, true);
        if ($raw === '') {
            return 0;
        }

        return (int) $raw;
    }

    /**
     * Return empty stats defaults.
     *
     * @return array<string,int|float>
     */
    private function get_empty_stats(): array {
        return array(
            'total_listings'            => 0,
            'active_listings'           => 0,
            'pending_listings'          => 0,
            'sold_listings'             => 0,
            'expired_listings'          => 0,
            'stale_listings'            => 0,
            'total_views'               => 0,
            'unique_views'              => 0,
            'total_leads'               => 0,
            'average_views_per_listing' => 0.0,
        );
    }
}
