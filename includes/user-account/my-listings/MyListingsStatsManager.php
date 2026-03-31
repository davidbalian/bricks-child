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
        $is_sold = (int) get_post_meta($listing_id, 'is_sold', true) === 1;
        if ($is_sold) {
            $stats['sold_listings']++;
            return;
        }

        $post_status = get_post_status($listing_id);
        if ($post_status === 'publish') {
            $stats['active_listings']++;
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
            'total_views'               => 0,
            'unique_views'              => 0,
            'total_leads'               => 0,
            'average_views_per_listing' => 0.0,
        );
    }
}
