<?php
/**
 * Returns five deal listings in marketplace rank order (freshness bucket + score + date).
 */
if (!defined('ABSPATH')) {
    exit;
}

final class DailyDealsDealPicker
{
    /** Fetch extra IDs so some can be skipped (no price / no image) and we still get five. */
    private const SQL_FETCH_LIMIT = 80;

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
    public function pickForDay(): array
    {
        $bands_primary = array('great', 'good');
        $ids_ordered = $this->fetchDealListingIdsOrdered($bands_primary);
        $rows = $this->buildEligibleRowsInOrder($ids_ordered, self::PICK_COUNT);

        if (count($rows) < self::PICK_COUNT) {
            $ids_ordered = $this->fetchDealListingIdsOrdered(array('great', 'good', 'fair'));
            $rows = $this->buildEligibleRowsInOrder($ids_ordered, self::PICK_COUNT);
        }

        return $rows;
    }

    /**
     * Same ORDER BY as /cars/ Best match (car_listings_score_orderby_clauses + car_listings_best_match_orderby_sql).
     *
     * @param list<string> $bands
     * @return list<int>
     */
    private function fetchDealListingIdsOrdered(array $bands): array
    {
        global $wpdb;

        if ($bands === array()) {
            return array();
        }

        $limit = max(self::PICK_COUNT, min(120, self::SQL_FETCH_LIMIT));
        $state_key = ListingStateManager::FIELD_NAME;
        $active = ListingStateManager::STATE_ACTIVE;

        $placeholders = implode(',', array_fill(0, count($bands), '%s'));
        $orderby = function_exists('car_listings_best_match_orderby_sql')
            ? car_listings_best_match_orderby_sql('p')
            : 'p.post_date DESC';

        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS ls
                ON ls.post_id = p.ID AND ls.meta_key = %s AND ls.meta_value = %s
            INNER JOIN {$wpdb->postmeta} AS band
                ON band.post_id = p.ID AND band.meta_key = 'price_insight_band' AND band.meta_value IN ($placeholders)
            LEFT JOIN {$wpdb->postmeta} AS rank_meta
                ON rank_meta.post_id = p.ID AND rank_meta.meta_key = 'listing_rank_score'
            LEFT JOIN {$wpdb->postmeta} AS listing_pub_meta
                ON listing_pub_meta.post_id = p.ID AND listing_pub_meta.meta_key = 'publication_date'
            WHERE p.post_type = 'car'
              AND p.post_status = 'publish'
            ORDER BY {$orderby}
            LIMIT %d
        ";

        $prepare_args = array_merge(
            array($sql, $state_key, $active),
            $bands,
            array($limit)
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders match band list count.
        $prepared = call_user_func_array(array($wpdb, 'prepare'), $prepare_args);
        $col = $wpdb->get_col($prepared); // phpcs:ignore WordPress.DB
        if (! is_array($col)) {
            return array();
        }

        return array_values(array_unique(array_map('intval', $col)));
    }

    /**
     * Walk SQL order; keep first $max that have price + image.
     *
     * @param list<int> $post_ids
     * @return list<array<string,mixed>>
     */
    private function buildEligibleRowsInOrder(array $post_ids, int $max): array
    {
        $out = array();
        foreach ($post_ids as $post_id) {
            if (count($out) >= $max) {
                break;
            }
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
        return '€' . number_format_i18n($amount, 0);
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
}
