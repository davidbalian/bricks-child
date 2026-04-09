<?php
/**
 * Transparent result cache for car_listings_execute_query().
 *
 * Does not change filter logic or SQL semantics: on a cache miss the same heavy
 * query runs once (full ordered ID list). Hits serve pages via a small post__in query.
 *
 * Disable: define( 'CAR_LISTINGS_QUERY_CACHE', false );
 * TTL filter: car_listings_query_cache_ttl (default 300 seconds)
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bump cache generation so all prior transients become unused.
 */
function car_listings_query_cache_bump_generation() {
    $n = (int) get_option('car_listings_query_cache_gen', 1);
    update_option('car_listings_query_cache_gen', $n + 1, false);
}

/**
 * Recursive ksort for stable cache keys.
 *
 * @param array $arr Array (by reference).
 */
function car_listings_query_cache_ksort_recursive(array &$arr) {
    ksort($arr);
    foreach ($arr as &$v) {
        if (is_array($v)) {
            car_listings_query_cache_ksort_recursive($v);
        }
    }
}

/**
 * Strip pagination-only keys so the same filter set shares one cache entry.
 *
 * @param array $args WP_Query args.
 * @return array
 */
function car_listings_query_cache_normalize_for_key(array $args) {
    $a = $args;
    unset(
        $a['paged'],
        $a['posts_per_page'],
        $a['offset'],
        $a['fields'],
        $a['no_found_rows'],
        $a['update_post_meta_cache'],
        $a['update_post_term_cache'],
        $a['cache_results'],
        $a['lazy_load_term_meta']
    );
    car_listings_query_cache_ksort_recursive($a);
    return $a;
}

/**
 * Runs WP_Query with featured-first clauses (same as uncached path).
 *
 * @param array $query_args Arguments.
 * @return WP_Query
 */
function car_listings_query_cache_run_wp_query(array $query_args) {
    add_filter('posts_clauses', 'car_listings_featured_first_orderby', 10, 2);
    add_filter('posts_clauses', 'car_listings_active_listing_state_clauses', 12, 2);
    add_filter('posts_clauses', 'car_listings_score_orderby_clauses', 14, 2);
    $query = new WP_Query($query_args);
    remove_filter('posts_clauses', 'car_listings_score_orderby_clauses', 14, 2);
    remove_filter('posts_clauses', 'car_listings_active_listing_state_clauses', 12, 2);
    remove_filter('posts_clauses', 'car_listings_featured_first_orderby', 10, 2);
    return $query;
}

/**
 * Build a paginated WP_Query from a full ordered ID list (cache hit path).
 *
 * @param array $original_args Original request args (includes paged, posts_per_page, offset).
 * @param int[] $all_ids       All matching post IDs in query order.
 * @param int   $found         Total found_posts from the full query.
 * @return WP_Query
 */
function car_listings_query_cache_build_paged_query(array $original_args, array $all_ids, $found) {
    $found = (int) $found;
    $ppp = isset($original_args['posts_per_page']) ? (int) $original_args['posts_per_page'] : 12;
    if ($ppp < 1) {
        $ppp = 12;
    }
    $paged = isset($original_args['paged']) ? max(1, (int) $original_args['paged']) : 1;
    $offset = isset($original_args['offset']) ? max(0, (int) $original_args['offset']) : 0;

    $start = $offset + ($paged - 1) * $ppp;
    $page_ids = array_slice($all_ids, $start, $ppp);

    $post_type = isset($original_args['post_type']) ? $original_args['post_type'] : 'car';
    $post_status = isset($original_args['post_status']) ? $original_args['post_status'] : 'publish';

    $total_after_offset = max(0, $found - $offset);
    $max_num_pages = $ppp > 0 ? (int) ceil($total_after_offset / $ppp) : 0;

    if (empty($page_ids)) {
        $q = new WP_Query(
            array(
                'post_type'           => $post_type,
                'post_status'         => $post_status,
                'post__in'            => array(0),
                'posts_per_page'      => 1,
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            )
        );
        $q->found_posts = $found;
        $q->max_num_pages = $max_num_pages;
        $q->post_count = 0;
        $q->posts = array();
        $q->post = null;
        return $q;
    }

    $q = new WP_Query(
        array(
            'post_type'           => $post_type,
            'post_status'         => $post_status,
            'post__in'            => $page_ids,
            'orderby'             => 'post__in',
            'posts_per_page'      => count($page_ids),
            'paged'               => 1,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        )
    );

    $q->found_posts = $found;
    $q->max_num_pages = $max_num_pages;
    $q->post_count = count($q->posts);

    return $q;
}

/**
 * Execute query with optional transient cache of ordered IDs.
 *
 * @param array $query_args Same as car_listings_execute_query input.
 * @return WP_Query
 */
function car_listings_query_cache_execute(array $query_args) {
    if (defined('CAR_LISTINGS_QUERY_CACHE') && !CAR_LISTINGS_QUERY_CACHE) {
        return car_listings_query_cache_run_wp_query($query_args);
    }
    if (!apply_filters('car_listings_query_cache_enabled', true)) {
        return car_listings_query_cache_run_wp_query($query_args);
    }

    $max_ids = (int) apply_filters('car_listings_query_cache_max_ids', 20000);
    $ttl = (int) apply_filters('car_listings_query_cache_ttl', 300);
    if ($ttl < 30) {
        $ttl = 30;
    }

    $gen = (int) get_option('car_listings_query_cache_gen', 1);
    $cache_version = (string) apply_filters('car_listings_query_cache_version', 'v4');
    $norm = car_listings_query_cache_normalize_for_key($query_args);
    $key = 'car_lq_' . $cache_version . '_' . $gen . '_' . md5(wp_json_encode($norm));

    $blob = get_transient($key);
    if ($blob !== false && is_array($blob) && isset($blob['ids'], $blob['found'])) {
        $ids = is_array($blob['ids']) ? array_map('intval', $blob['ids']) : array();
        $found = (int) $blob['found'];
        return car_listings_query_cache_build_paged_query($query_args, $ids, $found);
    }

    $fetch_args = $query_args;
    $fetch_args['posts_per_page'] = -1;
    $fetch_args['paged'] = 1;
    $fetch_args['fields'] = 'ids';
    $fetch_args['offset'] = 0;
    unset(
        $fetch_args['update_post_meta_cache'],
        $fetch_args['update_post_term_cache']
    );

    $full = car_listings_query_cache_run_wp_query($fetch_args);
    $ids = is_array($full->posts) ? array_map('intval', $full->posts) : array();
    $found = (int) $full->found_posts;

    if (count($ids) <= $max_ids && $found <= $max_ids) {
        set_transient($key, array('ids' => $ids, 'found' => $found), $ttl);
    }

    return car_listings_query_cache_build_paged_query($query_args, $ids, $found);
}

add_action(
    'save_post',
    static function ($post_id) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        if (get_post_type($post_id) === 'car') {
            car_listings_query_cache_bump_generation();
        }
    },
    20
);

add_action(
    'before_delete_post',
    static function ($post_id) {
        if (get_post_type($post_id) === 'car') {
            car_listings_query_cache_bump_generation();
        }
    },
    20
);
