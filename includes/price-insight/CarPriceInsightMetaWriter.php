<?php
/**
 * Persists price insight fields on a car listing (ACF when available).
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarPriceInsightMetaWriter {

    /**
     * @param int   $post_id Car post ID.
     * @param array $data    Keys: band, cohort_n, median, pct, cohort_key.
     * @return void
     */
    public static function write($post_id, array $data) {
        $computed = current_time('mysql');

        self::set_field($post_id, 'price_insight_band', isset($data['band']) ? (string) $data['band'] : 'none');
        self::set_field($post_id, 'price_insight_computed_at', $computed);
        self::set_field($post_id, 'price_insight_cohort_n', isset($data['cohort_n']) ? (int) $data['cohort_n'] : 0);

        if (array_key_exists('median', $data) && $data['median'] !== null) {
            self::set_field($post_id, 'price_insight_median', (float) $data['median']);
        } else {
            self::clear_field($post_id, 'price_insight_median');
        }

        if (array_key_exists('pct', $data) && $data['pct'] !== null) {
            self::set_field($post_id, 'price_vs_median_pct', (float) $data['pct']);
        } else {
            self::clear_field($post_id, 'price_vs_median_pct');
        }

        $cohort_key = isset($data['cohort_key']) ? (string) $data['cohort_key'] : '';
        if ($cohort_key !== '') {
            self::set_field($post_id, 'price_insight_cohort_key', $cohort_key);
        } else {
            self::clear_field($post_id, 'price_insight_cohort_key');
        }
    }

    /**
     * @param int    $post_id Post ID.
     * @param string $name    Field name.
     * @param mixed  $value   Value.
     * @return void
     */
    private static function set_field($post_id, $name, $value) {
        if (function_exists('update_field')) {
            update_field($name, $value, $post_id);
        } else {
            update_post_meta($post_id, $name, $value);
        }
    }

    /**
     * @param int    $post_id Post ID.
     * @param string $name    Field name.
     * @return void
     */
    private static function clear_field($post_id, $name) {
        delete_post_meta($post_id, $name);
        delete_post_meta($post_id, '_' . $name);
    }
}
