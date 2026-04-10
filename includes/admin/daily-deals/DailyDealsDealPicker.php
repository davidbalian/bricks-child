<?php
/**
 * Picks published active listings for social "daily deals" using rank + freshness, with daily variety.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class DailyDealsDealPicker
{
    private const POOL_LIMIT = 55;

    private const PICK_COUNT = 5;

    /**
     * @return list<array{
     *     post_id:int,
     *     title:string,
     *     price_display:string,
     *     band:string,
     *     deal_suffix:string,
     *     attachment_id:int,
     *     image_url:string
     * }>
     */
    public function pickForDay(string $day_ymd): array
    {
        $bands_primary = array('great', 'good');
        $ids_ordered = $this->fetchOrderedDealListingIds($bands_primary, self::POOL_LIMIT);
        $rows = $this->buildEligibleRows($ids_ordered);

        if (count($rows) < self::PICK_COUNT) {
            $ids_ordered = $this->fetchOrderedDealListingIds(array('great', 'good', 'fair'), self::POOL_LIMIT);
            $rows = $this->buildEligibleRows($ids_ordered);
        }

        if ($rows === array()) {
            return array();
        }

        $pool = array_slice($rows, 0, min(self::POOL_LIMIT, count($rows)));
        $shuffled = $this->orderWithDailySeed($pool, $day_ymd);

        return array_slice($shuffled, 0, self::PICK_COUNT);
    }

    /**
     * @param list<string> $bands
     * @return list<int>
     */
    private function fetchOrderedDealListingIds(array $bands, int $limit): array
    {
        global $wpdb;

        if ($bands === array()) {
            return array();
        }

        $safe_limit = max(self::PICK_COUNT, min(120, $limit));
        $state_key = ListingStateManager::FIELD_NAME;
        $active = ListingStateManager::STATE_ACTIVE;

        $placeholders = implode(',', array_fill(0, count($bands), '%s'));
        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS ls
                ON ls.post_id = p.ID AND ls.meta_key = %s AND ls.meta_value = %s
            INNER JOIN {$wpdb->postmeta} AS band
                ON band.post_id = p.ID AND band.meta_key = 'price_insight_band' AND band.meta_value IN ($placeholders)
            LEFT JOIN {$wpdb->postmeta} AS rank_meta
                ON rank_meta.post_id = p.ID AND rank_meta.meta_key = 'listing_rank_score'
            LEFT JOIN {$wpdb->postmeta} AS recency_meta
                ON recency_meta.post_id = p.ID AND recency_meta.meta_key = 'listing_rank_recency_bucket'
            WHERE p.post_type = 'car'
              AND p.post_status = 'publish'
            ORDER BY
                CAST(COALESCE(NULLIF(recency_meta.meta_value, ''), '2') AS UNSIGNED) ASC,
                CAST(COALESCE(NULLIF(rank_meta.meta_value, ''), '0') AS DECIMAL(12,2)) DESC,
                p.post_date_gmt DESC
            LIMIT %d
        ";

        $prepare_args = array_merge(
            array($sql, $state_key, $active),
            $bands,
            array($safe_limit)
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built from counted band list.
        $prepared = call_user_func_array(array($wpdb, 'prepare'), $prepare_args);
        $col = $wpdb->get_col($prepared); // phpcs:ignore WordPress.DB
        if (! is_array($col)) {
            return array();
        }

        $ids = array_map('intval', $col);

        return array_values(array_unique($ids));
    }

    /**
     * @param list<int> $post_ids
     * @return list<array<string,mixed>>
     */
    private function buildEligibleRows(array $post_ids): array
    {
        $out = array();
        foreach ($post_ids as $post_id) {
            if ($post_id <= 0) {
                continue;
            }
            $price_raw = get_post_meta($post_id, 'price', true);
            $price_num = is_numeric($price_raw) ? (float) $price_raw : 0.0;
            if ($price_num <= 0.0) {
                continue;
            }
            $attachment_id = DailyDealsFirstImageResolver::resolveAttachmentId($post_id);
            if ($attachment_id <= 0) {
                continue;
            }
            $url = wp_get_attachment_image_url($attachment_id, 'large');
            if ($url === false || $url === '') {
                continue;
            }
            $band = (string) get_post_meta($post_id, 'price_insight_band', true);
            $title = get_the_title($post_id);
            if ($title === '') {
                $title = sprintf(__('Listing #%d', 'bricks-child'), $post_id);
            }
            $out[] = array(
                'post_id'       => $post_id,
                'title'         => $title,
                'price_display' => $this->formatPriceEur($price_num),
                'band'          => $band,
                'deal_suffix'   => $this->dealSuffix($band),
                'attachment_id' => $attachment_id,
                'image_url'     => $url,
            );
        }

        return $out;
    }

    private function formatPriceEur(float $amount): string
    {
        $formatted = number_format_i18n($amount, 0);

        return '€' . $formatted;
    }

    private function dealSuffix(string $band): string
    {
        if ($band === 'great') {
            return ' (' . __('Great Deal', 'bricks-child') . ')';
        }
        if ($band === 'good') {
            return ' (' . __('Good Deal', 'bricks-child') . ')';
        }

        return '';
    }

    /**
     * Stable pseudo-shuffle: same site + day + listing IDs → same order; pool already rank/freshness ordered.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function orderWithDailySeed(array $rows, string $day_ymd): array
    {
        $salt = (string) wp_salt('auth');
        $scored = array();
        foreach ($rows as $row) {
            $id = (int) ($row['post_id'] ?? 0);
            $bin = hash('sha256', $day_ymd . $salt . $id, true);
            $u = unpack('N', substr($bin, 0, 4));
            $scored[] = array(
                'row' => $row,
                'k'   => isset($u[1]) ? (int) $u[1] : 0,
            );
        }

        usort(
            $scored,
            static function (array $a, array $b): int {
                return $a['k'] <=> $b['k'];
            }
        );

        $ordered = array();
        foreach ($scored as $item) {
            $ordered[] = $item['row'];
        }

        return $ordered;
    }
}
