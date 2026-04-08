<?php
/**
 * Compact JSON payload for car_card listings (AJAX JSON responses).
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/car-card.php';

/**
 * Relative date markup (same rules as render_car_card).
 *
 * @param int $post_id Post ID.
 * @return string HTML span(s).
 */
function car_card_listing_relative_date_markup($post_id) {
    $publication_date = get_post_meta($post_id, 'publication_date', true);
    if (empty($publication_date)) {
        $publication_date = get_the_date('Y-m-d H:i:s', $post_id);
    }
    $now = current_time('timestamp');
    $post_time = strtotime($publication_date);
    $time_diff = $now - $post_time;
    $days = floor($time_diff / (60 * 60 * 24));

    if ($days == 0) {
        return '<span class="post-date posted-today">Today</span>';
    }
    if ($days == 1) {
        return '<span class="post-date">Yesterday</span>';
    }
    if ($days >= 2 && $days <= 6) {
        return '<span class="post-date">' . (int) $days . ' days ago</span>';
    }
    if ($days >= 7 && $days <= 13) {
        return '<span class="post-date">1 week ago</span>';
    }
    if ($days >= 14 && $days <= 20) {
        return '<span class="post-date">2 weeks ago</span>';
    }
    if ($days >= 21 && $days <= 27) {
        return '<span class="post-date">3 weeks ago</span>';
    }
    if ($days >= 28 && $days <= 59) {
        return '<span class="post-date">1 month ago</span>';
    }
    $months = floor($days / 30);
    return '<span class="post-date">' . (int) $months . ' months ago</span>';
}

/**
 * Build one card payload for JSON transport (mirrors render_car_card fields).
 *
 * @param int  $post_id       Post ID.
 * @param int  $listing_index Index in the current grid (0 = first — LCP hints).
 * @param bool $is_favorite   Whether current user favorited this post.
 * @return array<string, mixed>
 */
function car_card_build_listing_json_payload($post_id, $listing_index, $is_favorite) {
    $permalink = get_permalink($post_id);
    $title = get_post_field('post_title', $post_id);

    $mileage = car_card_get_meta_value($post_id, 'mileage');
    $engine_capacity = car_card_get_meta_value($post_id, 'engine_capacity');
    $fuel_type = car_card_get_meta_value($post_id, 'fuel_type');
    $transmission = car_card_get_meta_value($post_id, 'transmission');
    $car_district = car_card_get_meta_value($post_id, 'car_district');
    $car_city = car_card_get_meta_value($post_id, 'car_city');
    $price = car_card_get_meta_value($post_id, 'price');

    $show_full_badge = car_card_get_meta_value($post_id, 'fulldetailsbadge');
    $show_extra_badge = car_card_get_meta_value($post_id, 'extradetailsbadge');
    $fresh_badge = car_card_get_meta_value($post_id, 'fresh_badge');
    $popular_badge = car_card_get_meta_value($post_id, 'popular_badge');
    $is_featured = car_card_get_meta_value($post_id, 'is_featured');

    $raw_images = get_post_meta($post_id, 'car_images', true);
    $image_ids = array();
    if (!empty($raw_images) && is_array($raw_images)) {
        foreach ($raw_images as $img) {
            if (is_array($img) && isset($img['ID'])) {
                $image_ids[] = (int) $img['ID'];
            } elseif (is_numeric($img)) {
                $image_ids[] = (int) $img;
            }
        }
    }

    $total_images = count($image_ids);
    $max_slides = 5;
    $slide_ids = array_slice($image_ids, 0, $max_slides);

    $slides_out = array();
    $is_first_grid = ($listing_index === 0);
    $sizes_attr = '(max-width: 767px) 92vw, (max-width: 1200px) 48vw, 420px';
    foreach ($slide_ids as $index => $img_id) {
        $size = car_card_best_image_size_for_attachment($img_id);
        $src = wp_get_attachment_image_url($img_id, $size);
        if ($src === '') {
            continue;
        }
        $srcset = wp_get_attachment_image_srcset($img_id, $size);
        $slides_out[] = array(
            'src'    => $src,
            'srcset' => $srcset ? $srcset : '',
            'sizes'  => $sizes_attr,
            'eager'  => ($is_first_grid && $index === 0) ? 1 : 0,
        );
    }

    $sc_rendered = count($slides_out);
    if ($sc_rendered === $max_slides && $total_images >= $max_slides) {
        $slides_out[$sc_rendered - 1]['overlay_view_all'] = 1;
    }

    $specs = array();
    if ($engine_capacity) {
        $specs[] = $engine_capacity . 'L';
    }
    if ($fuel_type) {
        $specs[] = $fuel_type;
    }
    if ($transmission) {
        $specs[] = $transmission;
    }

    $location_parts = array();
    if ($car_district) {
        $location_parts[] = $car_district;
    }
    if ($car_city) {
        $location_parts[] = $car_city;
    }

    $band = car_card_get_meta_value($post_id, 'price_insight_band');
    if ($band === '' || $band === null || $band === 'none') {
        $band = null;
    }

    $mileage_fmt = '';
    if ($mileage) {
        $mileage_fmt = number_format(floatval(str_replace(',', '', $mileage)));
    }

    $price_fmt = '';
    if ($price) {
        $price_fmt = number_format(floatval(str_replace(',', '', $price)));
    }

    return array(
        'id'               => (int) $post_id,
        'u'                => $permalink,
        't'                => $title,
        'mileage'          => $mileage_fmt,
        'specs'            => $specs,
        'price'            => $price_fmt,
        'slides'           => $slides_out,
        'ti'               => $total_images,
        'sc'               => $sc_rendered,
        'loc'              => implode(', ', $location_parts),
        'date_html'        => car_card_listing_relative_date_markup($post_id),
        'fav'              => $is_favorite ? 1 : 0,
        'bf'               => !empty($show_full_badge) ? 1 : 0,
        'be'               => !empty($show_extra_badge) ? 1 : 0,
        'fr'               => ($fresh_badge === '1') ? 1 : 0,
        'pop'              => ($popular_badge === '1') ? 1 : 0,
        'feat'             => !empty($is_featured) ? 1 : 0,
        'pi'               => $band,
        'idx'              => (int) $listing_index,
    );
}
