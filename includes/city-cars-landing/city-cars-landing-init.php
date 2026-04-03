<?php
/**
 * Assets and theme hooks for city cars landing page templates.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/CityCarsLandingCatalog.php';

/**
 * Whether the current singular page uses a city cars landing template.
 */
function autoagora_is_city_cars_landing_template() {
    if (!is_singular('page')) {
        return false;
    }

    $tpl = get_page_template_slug();
    if ($tpl === '') {
        return false;
    }

    $map = CityCarsLandingCatalog::template_slug_map();

    return isset($map[$tpl]);
}

/**
 * Current city landing slug (nicosia, limassol, …) or empty string.
 */
function autoagora_get_city_cars_landing_slug_from_template() {
    if (!is_singular('page')) {
        return '';
    }

    $tpl = get_page_template_slug();
    $map = CityCarsLandingCatalog::template_slug_map();

    return isset($map[$tpl]) ? $map[$tpl] : '';
}

/**
 * @param array<string, mixed>|null $city CityCarsLandingCatalog::get() result.
 */
function autoagora_city_cars_landing_enqueue_assets($city) {
    if (empty($city) || empty($city['slug'])) {
        return;
    }

    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();

    $css1 = $theme_dir . '/assets/css/city-cars-landing-1.css';
    $css2 = $theme_dir . '/assets/css/city-cars-landing-2.css';
    $js   = $theme_dir . '/assets/js/city-cars-landing-browse.js';

    if (file_exists($css1)) {
        wp_enqueue_style(
            'autoagora-city-cars-landing-1',
            $theme_uri . '/assets/css/city-cars-landing-1.css',
            array('bricks-child-theme-css'),
            filemtime($css1)
        );
    }
    if (file_exists($css2)) {
        wp_enqueue_style(
            'autoagora-city-cars-landing-2',
            $theme_uri . '/assets/css/city-cars-landing-2.css',
            array('autoagora-city-cars-landing-1'),
            filemtime($css2)
        );
    }

    if (!file_exists($js)) {
        return;
    }

    $js_map = $theme_dir . '/assets/js/city-cars-landing-map.js';
    if (file_exists($js_map)) {
        wp_enqueue_script(
            'autoagora-city-cars-browse-map',
            $theme_uri . '/assets/js/city-cars-landing-map.js',
            array('jquery'),
            filemtime($js_map),
            true
        );
    }

    $js_sort = $theme_dir . '/assets/js/city-cars-landing-browse-sort-ajax.js';
    if (file_exists($js_sort)) {
        wp_enqueue_script(
            'autoagora-city-cars-browse-sort-ajax',
            $theme_uri . '/assets/js/city-cars-landing-browse-sort-ajax.js',
            array('jquery', 'car-filters-js'),
            filemtime($js_sort),
            true
        );
    }

    $deps = array('jquery', 'car-filters-js');
    if (file_exists($js_map)) {
        $deps[] = 'autoagora-city-cars-browse-map';
    }
    if (file_exists($js_sort)) {
        $deps[] = 'autoagora-city-cars-browse-sort-ajax';
    }

    wp_enqueue_script(
        'autoagora-city-cars-browse',
        $theme_uri . '/assets/js/city-cars-landing-browse.js',
        $deps,
        filemtime($js),
        true
    );

    $js_faq = $theme_dir . '/assets/js/city-cars-landing-faq.js';
    if (file_exists($js_faq)) {
        wp_enqueue_script(
            'autoagora-city-cars-faq',
            $theme_uri . '/assets/js/city-cars-landing-faq.js',
            array(),
            filemtime($js_faq),
            true
        );
    }

    $listings_id = 'city-cars-landing-' . sanitize_html_class($city['slug']);
    $group       = 'city-cars-landing-' . sanitize_html_class($city['slug']);

    wp_localize_script(
        'autoagora-city-cars-browse',
        'autoagoraCityCarsBrowse',
        array(
            'listingsId'      => $listings_id,
            'group'           => $group,
            'defaultCarCity'  => isset($city['car_city_value']) ? $city['car_city_value'] : '',
            'presetLat'       => isset($city['lat']) ? (string) $city['lat'] : '',
            'presetLng'       => isset($city['lng']) ? (string) $city['lng'] : '',
            'presetRadiusKm'  => '25',
            'presetLabel'     => isset($city['loc_label']) ? $city['loc_label'] : '',
            'mapFallbackLat'  => isset($city['lat']) ? (string) $city['lat'] : '35.1856',
            'mapFallbackLng'  => isset($city['lng']) ? (string) $city['lng'] : '33.3823',
            'strings'         => array(
                'resultsSuffix' => __('results found', 'bricks-child'),
                'sortNewest'    => __('Newest', 'bricks-child'),
            ),
        )
    );
}

add_action(
    'wp_enqueue_scripts',
    function () {
        if (!autoagora_is_city_cars_landing_template()) {
            return;
        }
        $slug = autoagora_get_city_cars_landing_slug_from_template();
        $city = CityCarsLandingCatalog::get($slug);
        autoagora_city_cars_landing_enqueue_assets($city);
    },
    25
);

add_filter(
    'body_class',
    function ($classes) {
        if (autoagora_is_city_cars_landing_template()) {
            $classes[] = 'autoagora-city-cars-landing';
            $slug      = autoagora_get_city_cars_landing_slug_from_template();
            if ($slug !== '') {
                $classes[] = 'autoagora-city-cars-landing--' . sanitize_html_class($slug);
            }
        }

        return $classes;
    }
);
