<?php
/**
 * Helpers for the repository-owned single car template.
 *
 * @package Bricks_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read one car field without requiring ACF's formatting layer.
 *
 * @param int    $post_id Car post ID.
 * @param string $key     Meta key.
 * @return mixed
 */
function autoagora_single_car_field($post_id, $key) {
    if (function_exists('get_field')) {
        return get_field($key, $post_id);
    }

    return get_post_meta($post_id, $key, true);
}

/**
 * Whether a car field contains a displayable value. Numeric zero is valid.
 *
 * @param mixed $value Field value.
 * @return bool
 */
function autoagora_single_car_has_value($value) {
    return $value !== '' && $value !== null && $value !== false;
}

/**
 * Format a stored numeric car value.
 *
 * @param mixed $value Numeric value, possibly containing separators.
 * @return string
 */
function autoagora_single_car_number($value) {
    if ($value === '' || $value === null) {
        return '';
    }

    $number = str_replace(',', '', (string) $value);
    if (!is_numeric($number)) {
        return (string) $value;
    }

    return number_format((float) $number, 0);
}

/**
 * Human-readable publication age used by the current single-car design.
 *
 * @param int $post_id Car post ID.
 * @return string
 */
function autoagora_single_car_relative_date($post_id) {
    $publication_date = get_post_meta($post_id, 'publication_date', true);
    if ($publication_date === '') {
        $publication_date = get_the_date('Y-m-d H:i:s', $post_id);
    }

    $timestamp = strtotime((string) $publication_date);
    if (!$timestamp) {
        return '';
    }

    $days = max(0, (int) floor((current_time('timestamp') - $timestamp) / DAY_IN_SECONDS));
    if ($days === 0) {
        return __('Posted today', 'bricks-child');
    }
    if ($days === 1) {
        return __('Posted yesterday', 'bricks-child');
    }
    if ($days < 7) {
        return sprintf(__('Posted %d days ago', 'bricks-child'), $days);
    }
    if ($days < 14) {
        return __('Posted 1 week ago', 'bricks-child');
    }
    if ($days < 28) {
        return sprintf(__('Posted %d weeks ago', 'bricks-child'), (int) floor($days / 7));
    }

    $months = max(1, (int) floor($days / 30));
    return sprintf(_n('Posted %d month ago', 'Posted %d months ago', $months, 'bricks-child'), $months);
}

/**
 * Normalize ACF checkbox/meta values to a flat string list.
 *
 * @param mixed $value Stored field value.
 * @return string[]
 */
function autoagora_single_car_list_values($value) {
    $value = maybe_unserialize($value);
    if (!is_array($value)) {
        return $value === '' || $value === null ? array() : array((string) $value);
    }

    $values = array();
    foreach ($value as $item) {
        if (is_array($item)) {
            $item = isset($item['value']) ? $item['value'] : (isset($item['label']) ? $item['label'] : '');
        }
        if (is_scalar($item) && (string) $item !== '') {
            $values[] = (string) $item;
        }
    }

    return array_values(array_unique($values));
}

/**
 * Translate stored checkbox keys into their public labels.
 *
 * @param string[] $values Stored values.
 * @param string   $type   extras|history.
 * @return string[]
 */
function autoagora_single_car_list_labels(array $values, $type) {
    $extras = array(
        'alloy_wheels'            => __('Alloy Wheels', 'bricks-child'),
        'cruise_control'          => __('Cruise Control', 'bricks-child'),
        'disabled_accessible'     => __('Disabled Accessible', 'bricks-child'),
        'keyless_start'           => __('Keyless Start', 'bricks-child'),
        'rear_view_camera'        => __('Rear View Camera', 'bricks-child'),
        'start_stop'              => __('Start/Stop', 'bricks-child'),
        'sunroof'                 => __('Sunroof', 'bricks-child'),
        'heated_seats'            => __('Heated Seats', 'bricks-child'),
        'android_auto'            => __('Android Auto', 'bricks-child'),
        'apple_carplay'           => __('Apple CarPlay', 'bricks-child'),
        'folding_mirrors'         => __('Folding Mirrors', 'bricks-child'),
        'leather_seats'           => __('Leather Seats', 'bricks-child'),
        'panoramic_roof'          => __('Panoramic Roof', 'bricks-child'),
        'parking_sensors'         => __('Parking Sensors', 'bricks-child'),
        'camera_360'              => __('360° Camera', 'bricks-child'),
        'adaptive_cruise_control' => __('Adaptive Cruise Control', 'bricks-child'),
        'blind_spot_mirror'       => __('Blind Spot Mirror', 'bricks-child'),
        'lane_assist'             => __('Lane Assist', 'bricks-child'),
        'power_tailgate'          => __('Power Tailgate', 'bricks-child'),
    );

    $history = array(
        'no_accidents'             => __('No Accidents', 'bricks-child'),
        'minor_accidents'          => __('Minor Accidents', 'bricks-child'),
        'major_accidents'          => __('Major Accidents', 'bricks-child'),
        'regular_maintenance'      => __('Regular Maintenance', 'bricks-child'),
        'engine_overhaul'          => __('Engine Overhaul', 'bricks-child'),
        'transmission_replacement' => __('Transmission Replacement', 'bricks-child'),
        'repainted'                => __('Repainted', 'bricks-child'),
        'bodywork_repair'          => __('Bodywork Repair', 'bricks-child'),
        'rust_treatment'           => __('Rust Treatment', 'bricks-child'),
        'no_modifications'         => __('No Modifications', 'bricks-child'),
        'performance_upgrades'     => __('Performance Upgrades', 'bricks-child'),
        'cosmetic_modifications'   => __('Cosmetic Modifications', 'bricks-child'),
        'flood_damage'             => __('Flood Damage', 'bricks-child'),
        'fire_damage'              => __('Fire Damage', 'bricks-child'),
        'hail_damage'              => __('Hail Damage', 'bricks-child'),
        'clear_title'              => __('Clear Title', 'bricks-child'),
        'no_known_issues'          => __('No Known Issues', 'bricks-child'),
        'odometer_replacement'     => __('Odometer Replacement', 'bricks-child'),
    );

    $labels = $type === 'history' ? $history : $extras;
    return array_map(
        static function ($value) use ($labels) {
            if (isset($labels[$value])) {
                return $labels[$value];
            }
            return ucwords(str_replace(array('_', '-'), ' ', $value));
        },
        $values
    );
}

/**
 * Format the month-based MOT field.
 *
 * @param mixed $value Stored MOT value.
 * @return string
 */
function autoagora_single_car_mot_label($value) {
    $value = trim((string) $value);
    if ($value === '' || strcasecmp($value, 'Expired') === 0) {
        return $value;
    }

    $timestamp = strtotime($value . '-01');
    return $timestamp ? wp_date('F Y', $timestamp, wp_timezone()) : $value;
}

/**
 * Return the model term first and make term second.
 *
 * @param int $post_id Car post ID.
 * @return array{model: WP_Term|null, make: WP_Term|null}
 */
function autoagora_single_car_terms($post_id) {
    $terms = wp_get_post_terms($post_id, 'car_make');
    $result = array('model' => null, 'make' => null);
    if (is_wp_error($terms)) {
        return $result;
    }

    foreach ($terms as $term) {
        if ((int) $term->parent > 0 && !$result['model']) {
            $result['model'] = $term;
        } elseif ((int) $term->parent === 0 && !$result['make']) {
            $result['make'] = $term;
        }
    }

    if (!$result['make'] && $result['model']) {
        $parent = get_term((int) $result['model']->parent, 'car_make');
        if ($parent instanceof WP_Term) {
            $result['make'] = $parent;
        }
    }

    return $result;
}

/**
 * Resolve a supported city landing URL from a stored car_city value.
 *
 * @param string $city Stored city.
 * @return string
 */
function autoagora_single_car_city_url($city) {
    $slugs = array(
        'nicosia'  => 'used-cars-for-sale-in-nicosia',
        'limassol' => 'used-cars-for-sale-in-limassol',
        'larnaca'  => 'used-cars-for-sale-in-larnaca',
        'paphos'   => 'used-cars-for-sale-in-paphos',
        'pafos'    => 'used-cars-for-sale-in-paphos',
    );
    $key = sanitize_key($city);

    return isset($slugs[$key]) ? trailingslashit(home_url('/' . $slugs[$key] . '/')) : '';
}

/**
 * Query eight active cars with the same model as the current listing.
 *
 * Uses the marketplace query executor so paid promotion priority, Best Match
 * rank, recency buckets, and the shared result cache remain consistent with
 * homepage/browse listing collections.
 *
 * @param int $post_id Car post ID.
 * @param int $limit   Maximum related cars.
 * @return WP_Query
 */
function autoagora_single_car_related_query($post_id, $limit = 8) {
    $terms = autoagora_single_car_terms($post_id);
    $args = array(
        'post_type'                     => 'car',
        'post_status'                   => 'publish',
        'posts_per_page'                => max(1, (int) $limit),
        'post__not_in'                  => array((int) $post_id),
        'ignore_sticky_posts'           => true,
        'car_listing_state_active_only' => true,
        '_car_listings_orderby'         => 'score',
        '_car_listings_order'           => 'DESC',
        'orderby'                       => 'date',
        'order'                         => 'DESC',
    );

    if ($terms['model'] instanceof WP_Term) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'car_make',
                'field'    => 'term_id',
                'terms'    => array((int) $terms['model']->term_id),
            ),
        );
    } else {
        $make = trim((string) autoagora_single_car_field($post_id, 'make'));
        $model = trim((string) autoagora_single_car_field($post_id, 'model'));
        $args['meta_query'] = array('relation' => 'AND');
        if ($make !== '') {
            $args['meta_query'][] = array('key' => 'make', 'value' => $make, 'compare' => '=');
        }
        if ($model !== '') {
            $args['meta_query'][] = array('key' => 'model', 'value' => $model, 'compare' => '=');
        }
        if (count($args['meta_query']) === 1) {
            $args['post__in'] = array(0);
            unset($args['meta_query']);
        }
    }

    if (function_exists('car_listings_execute_query')) {
        return car_listings_execute_query($args);
    }

    return new WP_Query($args);
}
