<?php
/**
 * Builds a snapshot of the first N cars from the public /cars/ browse query (best match / score sort).
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves listing headline fields and first image URL (same priority as car listings cards).
 */
final class CarsDailyDealsSnapshotBuilder
{
    private const DEAL_LABELS = array(
        'great' => 'Great Deal',
        'good'  => 'Good Deal',
        'fair'  => 'Fair Deal',
    );

    /**
     * @return array<int, array{id:int, line:string, image_url:string, image_path:string, permalink:string, deal_suffix:string}>
     */
    public function fetchFirstCarsFromBrowseQuery(int $count = 5): array
    {
        if ($count < 1) {
            return array();
        }

        if (!function_exists('car_listings_build_query_args') || !function_exists('car_listings_execute_query')) {
            return array();
        }

        $posts = $this->withIsolatedBrowseRequest(
            static function () use ($count) {
                $atts = array(
                    'layout'             => 'grid',
                    'posts_per_page'     => $count,
                    'infinite_scroll'    => 'false',
                    'featured'           => 'false',
                    'favorites'          => 'false',
                    'user_id'            => '',
                    'author'             => '',
                    'orderby'            => 'score',
                    'order'              => 'DESC',
                    'show_sold'          => 'false',
                    'offset'             => 0,
                    'default_make_slug'  => '',
                    'default_model_slug' => '',
                    'default_car_city'   => '',
                );

                $args  = car_listings_build_query_args($atts);
                $query = car_listings_execute_query($args);

                return is_object($query) && isset($query->posts) ? $query->posts : array();
            }
        );

        if (!is_array($posts)) {
            return array();
        }

        $out = array();
        foreach ($posts as $post) {
            $id = isset($post->ID) ? (int) $post->ID : 0;
            if ($id <= 0) {
                continue;
            }
            $out[] = $this->buildItem($id);
        }

        return $out;
    }

    /**
     * Temporarily clears browse-related query vars so admin screen GET params cannot change the snapshot.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function withIsolatedBrowseRequest(callable $callback)
    {
        $keys = array(
            'make',
            'model',
            'price_min',
            'price_max',
            'mileage_min',
            'mileage_max',
            'year_min',
            'year_max',
            'fuel_type',
            'body_type',
            'car_city',
            'cars_orderby',
            'cars_order',
            'orderby',
            'order',
            'paged',
        );

        $saved = array();
        foreach ($keys as $key) {
            if (array_key_exists($key, $_GET)) {
                $saved[$key] = $_GET[$key];
                unset($_GET[$key]);
            }
        }

        try {
            return $callback();
        } finally {
            foreach ($saved as $key => $value) {
                $_GET[$key] = $value;
            }
        }
    }

    /**
     * @return array{id:int, line:string, image_url:string, image_path:string, permalink:string, deal_suffix:string}
     */
    private function buildItem(int $post_id): array
    {
        $make  = function_exists('get_field') ? (string) get_field('make', $post_id) : '';
        $model = function_exists('get_field') ? (string) get_field('model', $post_id) : '';
        $year  = function_exists('get_field') ? (string) get_field('year', $post_id) : '';
        $price = function_exists('get_field') ? get_field('price', $post_id) : '';

        $year_clean = $year !== '' ? str_replace(',', '', $year) : '';
        $price_num  = is_numeric(str_replace(',', '', (string) $price))
            ? floatval(str_replace(',', '', (string) $price))
            : 0.0;
        $price_fmt = $price_num > 0
            ? '€' . number_format_i18n($price_num, 0)
            : __('Price on request', 'bricks-child');

        $title = trim($make . ' ' . $model);
        if ($title === '') {
            $title = get_the_title($post_id);
        }
        $headline = trim($title . ($year_clean !== '' ? ' ' . $year_clean : ''));

        $band   = (string) get_post_meta($post_id, 'price_insight_band', true);
        $suffix = '';
        if ($band !== '' && $band !== 'none' && isset(self::DEAL_LABELS[$band])) {
            $suffix = ' (' . self::DEAL_LABELS[$band] . ')';
        }

        $line = $headline !== '' ? ($headline . ' – ' . $price_fmt . $suffix) : ($price_fmt . $suffix);

        $resolved = $this->resolveFirstImage($post_id);

        return array(
            'id'          => $post_id,
            'line'        => $line,
            'image_url'   => $resolved['url'],
            'image_path'  => $resolved['path'],
            'permalink'   => get_permalink($post_id),
            'deal_suffix' => $suffix,
        );
    }

    /**
     * @return array{url:string, path:string}
     */
    private function resolveFirstImage(int $post_id): array
    {
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $url = wp_get_attachment_image_url($thumb_id, 'large');
            if ($url) {
                return array(
                    'url'  => $url,
                    'path' => get_attached_file($thumb_id) ?: '',
                );
            }
        }

        if (function_exists('get_field')) {
            $car_images = get_field('car_images', $post_id);
            if (!empty($car_images) && is_array($car_images)) {
                $first = $car_images[0];
                $aid   = 0;
                if (is_array($first) && isset($first['ID'])) {
                    $aid = (int) $first['ID'];
                } elseif (is_numeric($first)) {
                    $aid = (int) $first;
                }
                if ($aid > 0) {
                    $url = wp_get_attachment_image_url($aid, 'large');
                    if ($url) {
                        return array(
                            'url'  => $url,
                            'path' => get_attached_file($aid) ?: '',
                        );
                    }
                }
            }
        }

        return array('url' => '', 'path' => '');
    }

    /**
     * @param array<int, array{line:string}> $items
     */
    public function buildSocialCaption(array $items): string
    {
        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        if ($host === '') {
            $host = 'autoagora.cy';
        }

        $n = count($items);
        $lines   = array(
            sprintf(
                /* translators: %d: number of deals listed */
                _n('🔥 Top %d Car Deal in Cyprus Today', '🔥 Top %d Car Deals in Cyprus Today', $n, 'bricks-child'),
                $n
            ),
            '',
        );
        $index   = 1;
        foreach ($items as $row) {
            $lines[] = $index . '. ' . (string) ($row['line'] ?? '');
            ++$index;
        }
        $lines[] = '';
        $lines[] = '👉 View all deals: ' . $host;
        $lines[] = '#usedcarscyprus #carsincyprus #cypruscars #autoagoracy #forsalecyprus #cardealscyprus';

        return implode("\n", $lines);
    }
}
