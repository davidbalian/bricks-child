<?php
/**
 * Bulk-expires published car listings past an activity-date threshold.
 *
 * Activity date: publication_date meta if set, otherwise post_date.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class CarsReportBulkExpireManager
{
    private const POST_TYPE = 'car';

    /**
     * Set listing_state to expired for stale published cars (post stays publish; hidden from marketplace).
     *
     * Skips sold and already-expired listing_state.
     *
     * @return int Number of listings updated.
     */
    public function expirePublishedCarsOlderThanDays(int $minAgeDays): int
    {
        $ids = $this->fetchPublishedCarIdsStaleByActivityDate($minAgeDays);
        $count = 0;
        foreach ($ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id <= 0) {
                continue;
            }
            if (! class_exists('ListingStateManager')) {
                continue;
            }
            if (ListingStateManager::assign_state($post_id, ListingStateManager::STATE_EXPIRED)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return int[]
     */
    private function fetchPublishedCarIdsStaleByActivityDate(int $minAgeDays): array
    {
        global $wpdb;

        $safe_days = max(1, $minAgeDays);
        $cutoff_ts = current_time('timestamp') - ($safe_days * DAY_IN_SECONDS);
        $cutoff = wp_date('Y-m-d H:i:s', $cutoff_ts);

        $pub_key = 'publication_date';

        $query = $wpdb->prepare(
            "
            SELECT p.ID
            FROM {$wpdb->posts} AS p
            WHERE p.post_type = %s
              AND p.post_status = 'publish'
              AND NOT EXISTS (
                  SELECT 1 FROM {$wpdb->postmeta} AS ls_sold
                  WHERE ls_sold.post_id = p.ID
                    AND ls_sold.meta_key = 'listing_state'
                    AND ls_sold.meta_value = 'sold'
              )
              AND NOT EXISTS (
                  SELECT 1 FROM {$wpdb->postmeta} AS ls_exp
                  WHERE ls_exp.post_id = p.ID
                    AND ls_exp.meta_key = 'listing_state'
                    AND ls_exp.meta_value = 'expired'
              )
              AND COALESCE(
                  NULLIF(
                      (SELECT meta_value FROM {$wpdb->postmeta}
                       WHERE post_id = p.ID AND meta_key = %s LIMIT 1),
                      ''
                  ),
                  p.post_date
              ) <= %s
            ",
            self::POST_TYPE,
            $pub_key,
            $cutoff
        );

        $col = $wpdb->get_col($query); // phpcs:ignore WordPress.DB
        if (! is_array($col)) {
            return [];
        }

        return array_map('intval', $col);
    }
}
