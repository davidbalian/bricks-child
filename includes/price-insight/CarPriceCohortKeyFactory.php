<?php
/**
 * Builds a stable cohort_key (sha1 hex) from listing attributes.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarPriceCohortKeyFactory {

    /**
     * @param int $post_id Car post ID.
     * @return string|null Cohort key or null if make/model/year unusable.
     */
    public static function build_for_post($post_id) {
        $parts = self::canonical_parts($post_id);
        if ($parts === null) {
            return null;
        }
        return sha1(implode('|', $parts));
    }

    /**
     * @return string[]|null
     */
    private static function canonical_parts($post_id) {
        $make_slug = '';
        $model_slug = '';

        $terms = wp_get_post_terms($post_id, 'car_make');
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                if ((int) $term->parent === 0) {
                    $make_slug = $term->slug;
                } else {
                    $model_slug = $term->slug;
                }
            }
        }

        if ($make_slug === '' || $model_slug === '') {
            $make_slug = self::acf_or_meta_slug($post_id, 'make');
            $model_slug = self::acf_or_meta_slug($post_id, 'model');
        }

        if ($make_slug === '' || $model_slug === '') {
            return null;
        }

        $year = self::int_field($post_id, 'year');
        if ($year < 1950 || $year > (int) gmdate('Y') + 1) {
            return null;
        }

        $mileage_km = self::float_mileage_km($post_id);
        $mile_bin = self::mileage_bin_label($mileage_km);

        $engine_bin = self::engine_bin_label($post_id);

        return array($make_slug, $model_slug, (string) $year, $mile_bin, $engine_bin);
    }

    /**
     * @param int $post_id Post ID.
     * @return string
     */
    private static function acf_or_meta_slug($post_id, $field) {
        $raw = function_exists('get_field') ? get_field($field, $post_id) : get_post_meta($post_id, $field, true);
        if ($raw === '' || $raw === null) {
            return '';
        }
        if (is_numeric($raw)) {
            $term = get_term((int) $raw, 'car_make');
            if ($term && !is_wp_error($term)) {
                return $term->slug;
            }
        }
        if (is_object($raw) && isset($raw->slug)) {
            return (string) $raw->slug;
        }
        return sanitize_title((string) $raw);
    }

    /**
     * @param int $post_id Post ID.
     * @return int
     */
    private static function int_field($post_id, $field) {
        $raw = function_exists('get_field') ? get_field($field, $post_id) : get_post_meta($post_id, $field, true);
        return (int) round(floatval(str_replace(',', '', (string) $raw)));
    }

    /**
     * @param int $post_id Post ID.
     * @return float
     */
    private static function float_mileage_km($post_id) {
        $raw = function_exists('get_field') ? get_field('mileage', $post_id) : get_post_meta($post_id, 'mileage', true);
        return floatval(str_replace(',', '', (string) $raw));
    }

    /**
     * @param float $mileage_km Mileage in km.
     * @return string
     */
    private static function mileage_bin_label($mileage_km) {
        if ($mileage_km < 0) {
            return 'm_unknown';
        }
        $w = CarPriceInsightConfig::MILEAGE_BUCKET_KM;
        $bin = (int) floor($mileage_km / $w) * $w;
        return 'm' . $bin;
    }

    /**
     * @param int $post_id Post ID.
     * @return string
     */
    private static function engine_bin_label($post_id) {
        $raw = function_exists('get_field') ? get_field('engine_capacity', $post_id) : get_post_meta($post_id, 'engine_capacity', true);
        $litres = floatval(str_replace(',', '', (string) $raw));
        if ($litres <= 0) {
            return 'e_unknown';
        }
        $edges = CarPriceInsightConfig::engine_bin_edges_litres();
        $idx = 0;
        foreach ($edges as $e) {
            if ($litres < $e) {
                return 'e' . $idx;
            }
            $idx++;
        }
        return 'e' . $idx;
    }
}
