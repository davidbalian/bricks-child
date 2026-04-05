<?php
/**
 * Full rebuild: cohort aggregates in DB + per-listing insight meta.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarPriceInsightRebuildOrchestrator {

    /**
     * True while a rebuild is running (avoids save_post defer storms).
     *
     * @var bool
     */
    public static $running = false;

    /**
     * @return void
     */
    public static function run() {
        if (!CarPriceCohortStatsRepository::table_exists()) {
            return;
        }

        self::$running = true;

        try {
            global $wpdb;
            $table = CarPriceCohortStatsRepository::table_name();
            $wpdb->query("TRUNCATE TABLE {$table}");

            $market_ids = self::market_listing_ids();
            $cohort_prices = array();
            foreach ($market_ids as $post_id) {
                $key = CarPriceCohortKeyFactory::build_for_post($post_id);
                if ($key === null) {
                    continue;
                }
                $price = self::listing_price($post_id);
                if ($price <= 0) {
                    continue;
                }
                if (!isset($cohort_prices[$key])) {
                    $cohort_prices[$key] = array();
                }
                $cohort_prices[$key][] = $price;
            }

            foreach ($cohort_prices as $key => $prices) {
                $median = self::median($prices);
                if ($median === null) {
                    continue;
                }
                CarPriceCohortStatsRepository::upsert($key, count($prices), $median);
            }

            foreach (self::all_published_car_ids() as $post_id) {
                self::apply_to_listing($post_id);
            }
        } finally {
            self::$running = false;
        }
    }

    /**
     * @return int[]
     */
    private static function all_published_car_ids() {
        $query = new WP_Query(
            array(
                'post_type'              => 'car',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );
        $ids = $query->posts;
        wp_reset_postdata();
        return is_array($ids) ? array_map('intval', $ids) : array();
    }

    /**
     * @return int[]
     */
    private static function market_listing_ids() {
        $query = new WP_Query(
            array(
                'post_type'              => 'car',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'is_sold',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => 'is_sold',
                        'value'   => '1',
                        'compare' => '!=',
                    ),
                ),
            )
        );
        $ids = $query->posts;
        wp_reset_postdata();
        return is_array($ids) ? array_map('intval', $ids) : array();
    }

    /**
     * @param int $post_id Post ID.
     * @return void
     */
    private static function apply_to_listing($post_id) {
        if (!self::is_in_market($post_id)) {
            CarPriceInsightMetaWriter::write(
                $post_id,
                array(
                    'band'       => 'none',
                    'cohort_n'   => 0,
                    'median'     => null,
                    'pct'        => null,
                    'cohort_key' => '',
                )
            );
            return;
        }

        $key = CarPriceCohortKeyFactory::build_for_post($post_id);
        $price = self::listing_price($post_id);
        if ($key === null || $price <= 0) {
            CarPriceInsightMetaWriter::write(
                $post_id,
                array(
                    'band'       => 'none',
                    'cohort_n'   => 0,
                    'median'     => null,
                    'pct'        => null,
                    'cohort_key' => '',
                )
            );
            return;
        }

        $stats = CarPriceCohortStatsRepository::get_stats($key);
        if ($stats === null) {
            CarPriceInsightMetaWriter::write(
                $post_id,
                array(
                    'band'       => 'none',
                    'cohort_n'   => 0,
                    'median'     => null,
                    'pct'        => null,
                    'cohort_key' => $key,
                )
            );
            return;
        }

        $resolved = CarPriceInsightBandResolver::resolve(
            $price,
            $stats['median_price'],
            $stats['listing_count']
        );

        CarPriceInsightMetaWriter::write(
            $post_id,
            array(
                'band'       => $resolved['band'],
                'cohort_n'   => $stats['listing_count'],
                'median'     => $stats['median_price'],
                'pct'        => $resolved['pct_vs_median'],
                'cohort_key' => $key,
            )
        );
    }

    /**
     * @param int $post_id Post ID.
     * @return bool
     */
    private static function is_in_market($post_id) {
        $sold = function_exists('get_field') ? get_field('is_sold', $post_id) : get_post_meta($post_id, 'is_sold', true);
        if ($sold === 1 || $sold === '1' || $sold === true) {
            return false;
        }
        return get_post_status($post_id) === 'publish';
    }

    /**
     * @param int $post_id Post ID.
     * @return float
     */
    private static function listing_price($post_id) {
        $raw = function_exists('get_field') ? get_field('price', $post_id) : get_post_meta($post_id, 'price', true);
        return floatval(str_replace(',', '', (string) $raw));
    }

    /**
     * @param float[] $values Values.
     * @return float|null
     */
    private static function median(array $values) {
        if (empty($values)) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $c = count($values);
        $mid = (int) floor(($c - 1) / 2);
        if ($c % 2 === 1) {
            return (float) $values[$mid];
        }
        return ((float) $values[$mid] + (float) $values[$mid + 1]) / 2.0;
    }
}
