<?php
/**
 * 301 redirects for removed or sold car listings + registry for deleted slugs.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persists slug → target URL when listings are trashed or deleted (permanent 301 targets).
 */
final class Autoagora_Deleted_Car_Redirect_Store {

    private const OPTION_KEY = 'autoagora_deleted_car_redirects';
    private const MAX_ENTRIES  = 1000;

    public static function save(string $post_name, string $target_url): void {
        if ($post_name === '' || $target_url === '') {
            return;
        }

        $map = get_option(self::OPTION_KEY, array());
        if (!is_array($map)) {
            $map = array();
        }

        $map[ $post_name ] = $target_url;

        if (count($map) > self::MAX_ENTRIES) {
            $map = array_slice($map, -self::MAX_ENTRIES, null, true);
        }

        update_option(self::OPTION_KEY, $map, false);
    }

    public static function get_target(string $post_name): string {
        if ($post_name === '') {
            return '';
        }

        $map = get_option(self::OPTION_KEY, array());
        if (!is_array($map) || empty($map[ $post_name ]) || !is_string($map[ $post_name ])) {
            return '';
        }

        return $map[ $post_name ];
    }
}

/**
 * Resolves 301 target: similar active listings → /cars/filter/make:{slug}/, else /cars/.
 */
final class Autoagora_Car_Category_Redirect_Resolver {

    public static function resolve_target_url(int $post_id): string {
        $cars_index = trailingslashit(home_url('/cars/'));

        $terms = wp_get_post_terms($post_id, 'car_make', array('fields' => 'all'));
        if (empty($terms) || is_wp_error($terms)) {
            return $cars_index;
        }

        $model_term = null;
        $make_term  = null;

        foreach ($terms as $term) {
            if ((int) $term->parent > 0) {
                $model_term = $term;
            } else {
                $make_term = $term;
            }
        }

        if (!self::has_similar_available_listings($post_id, $model_term, $make_term)) {
            return $cars_index;
        }

        if ($model_term instanceof WP_Term) {
            return trailingslashit(home_url('/cars/filter/make:' . $model_term->slug . '/'));
        }

        if ($make_term instanceof WP_Term) {
            return trailingslashit(home_url('/cars/filter/make:' . $make_term->slug . '/'));
        }

        return $cars_index;
    }

    /**
     * @param WP_Term|null $model_term Child car_make term.
     * @param WP_Term|null $make_term  Parent car_make term.
     */
    private static function has_similar_available_listings(int $post_id, $model_term, $make_term): bool {
        $exclude_sold = ListingStateManager::meta_query_exclude_sold();

        $args = array(
            'post_type'           => 'car',
            'post_status'         => 'publish',
            'post__not_in'        => array($post_id),
            'posts_per_page'      => 1,
            'fields'              => 'ids',
            'ignore_sticky_posts' => true,
            'meta_query'          => array($exclude_sold),
        );

        if ($model_term instanceof WP_Term) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'car_make',
                    'field'    => 'term_id',
                    'terms'    => (int) $model_term->term_id,
                ),
            );
        } elseif ($make_term instanceof WP_Term) {
            $args['tax_query'] = array(
                array(
                    'taxonomy'         => 'car_make',
                    'field'            => 'term_id',
                    'terms'            => (int) $make_term->term_id,
                    'include_children' => true,
                ),
            );
        } else {
            return false;
        }

        $query = new WP_Query($args);

        return ((int) $query->found_posts) > 0;
    }

    public static function listing_is_marked_sold(int $post_id): bool {
        return ListingStateManager::is_marked_sold($post_id);
    }

    public static function car_rewrite_slug(): string {
        $pto = get_post_type_object('car');
        if ($pto && !empty($pto->rewrite['slug'])) {
            return trim((string) $pto->rewrite['slug'], '/');
        }

        return 'car';
    }

    public static function parse_car_slug_from_404_request(): string {
        if (!is_404()) {
            return '';
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $raw_path    = wp_parse_url($request_uri, PHP_URL_PATH);
        if (!is_string($raw_path)) {
            return '';
        }

        $path = trim($raw_path, '/');
        $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
        $home_path = is_string($home_path) ? trim($home_path, '/') : '';

        if ($home_path !== '' && $path !== '') {
            $prefix = $home_path . '/';
            if (strpos($path, $prefix) === 0) {
                $path = substr($path, strlen($prefix));
            } elseif ($path === $home_path) {
                $path = '';
            }
        }

        $segments = $path === '' ? array() : explode('/', $path);
        $base     = self::car_rewrite_slug();

        if (count($segments) < 2 || $segments[0] !== $base) {
            return '';
        }

        $slug = $segments[1];
        if ($slug === '' || strpbrk($slug, ".\\") !== false) {
            return '';
        }

        return sanitize_title($slug);
    }
}

/**
 * Registers WordPress hooks for redirect behaviour.
 */
final class Autoagora_Car_Listing_Redirect_Coordinator {

    public static function register(): void {
        add_action('wp_trash_post', array(__CLASS__, 'on_trash'), 10, 1);
        add_action('before_delete_post', array(__CLASS__, 'on_before_delete'), 10, 2);
        add_action('template_redirect', array(__CLASS__, 'redirect_expired_singular'), 1);
        add_action('template_redirect', array(__CLASS__, 'redirect_sold_singular'), 1);
        add_action('template_redirect', array(__CLASS__, 'redirect_deleted_car_404'), 2);
    }

    public static function on_trash(int $post_id): void {
        self::persist_redirect_for_post($post_id);
    }

    /**
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public static function on_before_delete(int $post_id, $post): void {
        if (!$post instanceof WP_Post) {
            return;
        }

        self::persist_redirect_for_post((int) $post->ID);
    }

    private static function persist_redirect_for_post(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'car' || $post->post_name === '') {
            return;
        }

        $target = Autoagora_Car_Category_Redirect_Resolver::resolve_target_url($post_id);
        Autoagora_Deleted_Car_Redirect_Store::save($post->post_name, $target);
    }

    public static function redirect_expired_singular(): void {
        if (! is_singular('car')) {
            return;
        }

        $post_id = (int) get_queried_object_id();
        if ($post_id <= 0) {
            return;
        }

        if (! class_exists('ListingStateManager') || ! ListingStateManager::is_marked_expired($post_id)) {
            return;
        }

        $target = Autoagora_Car_Category_Redirect_Resolver::resolve_target_url($post_id);
        wp_safe_redirect($target, 301);
        exit;
    }

    public static function redirect_sold_singular(): void {
        if (!is_singular('car')) {
            return;
        }

        $post_id = (int) get_queried_object_id();
        if ($post_id <= 0) {
            return;
        }

        if (!Autoagora_Car_Category_Redirect_Resolver::listing_is_marked_sold($post_id)) {
            return;
        }

        $target = Autoagora_Car_Category_Redirect_Resolver::resolve_target_url($post_id);
        wp_safe_redirect($target, 301);
        exit;
    }

    public static function redirect_deleted_car_404(): void {
        if (!is_404()) {
            return;
        }

        $slug = Autoagora_Car_Category_Redirect_Resolver::parse_car_slug_from_404_request();
        if ($slug === '') {
            return;
        }

        $target = Autoagora_Deleted_Car_Redirect_Store::get_target($slug);
        if ($target === '') {
            return;
        }

        wp_safe_redirect($target, 301);
        exit;
    }
}

Autoagora_Car_Listing_Redirect_Coordinator::register();
