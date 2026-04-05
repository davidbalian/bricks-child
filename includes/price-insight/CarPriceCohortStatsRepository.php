<?php
/**
 * Reads and writes cohort aggregate rows in wp_car_price_cohorts.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarPriceCohortStatsRepository {

    /**
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . CarPriceInsightConfig::TABLE_BASENAME;
    }

    /**
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;
        $table = self::table_name();
        $like = $wpdb->esc_like($table);
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like)) === $table;
    }

    /**
     * @param string $cohort_key SHA1 hex.
     * @return array{ listing_count: int, median_price: float }|null
     */
    public static function get_stats($cohort_key) {
        global $wpdb;
        if (!self::table_exists()) {
            return null;
        }
        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT listing_count, median_price FROM {$table} WHERE cohort_key = %s LIMIT 1", $cohort_key),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        return array(
            'listing_count' => (int) $row['listing_count'],
            'median_price'  => (float) $row['median_price'],
        );
    }

    /**
     * @param string $cohort_key SHA1 hex.
     * @param int    $listing_count Count of listings in cohort.
     * @param float  $median_price  Median price.
     * @return void
     */
    public static function upsert($cohort_key, $listing_count, $median_price) {
        global $wpdb;
        if (!self::table_exists()) {
            return;
        }
        $table = self::table_name();
        $now = current_time('mysql');
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (cohort_key, listing_count, median_price, updated_at)
                VALUES (%s, %d, %f, %s)
                ON DUPLICATE KEY UPDATE listing_count = VALUES(listing_count), median_price = VALUES(median_price), updated_at = VALUES(updated_at)",
                $cohort_key,
                $listing_count,
                $median_price,
                $now
            )
        );
    }
}
