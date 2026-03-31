<?php
/**
 * Car Filters - Main Loader
 *
 * Modular filter system with individual shortcodes and a combined shortcode.
 * Supports AJAX filtering and URL redirect modes with auto-sync between instances.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load base functions
require_once __DIR__ . '/filters/filter-base.php';

// Load individual filters
require_once __DIR__ . '/filters/filter-make.php';
require_once __DIR__ . '/filters/filter-model.php';
require_once __DIR__ . '/filters/filter-price.php';
require_once __DIR__ . '/filters/filter-mileage.php';
require_once __DIR__ . '/filters/filter-year.php';
require_once __DIR__ . '/filters/filter-fuel.php';
require_once __DIR__ . '/filters/filter-body.php';

/**
 * Register the combined [car_filters] shortcode
 */
add_shortcode('car_filters', 'car_filters_shortcode');

/**
 * Combined filters shortcode
 *
 * Usage: [car_filters filters="make,model,price,mileage" mode="ajax" target="listings-1"]
 */
function car_filters_shortcode($atts) {
    $atts = shortcode_atts(array(
        'filters'      => 'make,model,price,mileage',  // Comma-separated filter names
        'group'        => 'default',
        'mode'         => 'ajax',                       // ajax or redirect
        'target'       => '',                           // Target car_listings ID
        'redirect_url' => '/cars/',                     // URL for redirect mode
        'results_base_url' => '/cars/',                 // Base URL for landing page redirects
        'layout'       => 'horizontal',                 // horizontal, vertical, inline
        'show_button'  => 'true',
        'button_text'  => 'Search Cars',
        'landing_make_slug' => '',
        'landing_model_slug' => '',
    ), $atts, 'car_filters');

    // Enqueue assets
    car_filters_enqueue_assets();

    // Parse filter list
    $filter_list = array_map('trim', explode(',', $atts['filters']));

    // Map filter names to shortcode functions
    $filter_map = array(
        'make'    => 'car_filter_make_shortcode',
        'model'   => 'car_filter_model_shortcode',
        'price'   => 'car_filter_price_shortcode',
        'mileage' => 'car_filter_mileage_shortcode',
        'year'    => 'car_filter_year_shortcode',
        'fuel'    => 'car_filter_fuel_shortcode',
        'body'    => 'car_filter_body_shortcode',
    );

    // Shared attributes to pass to each filter
    $shared_atts = array(
        'group'        => $atts['group'],
        'mode'         => $atts['mode'],
        'target'       => $atts['target'],
        'redirect_url' => $atts['redirect_url'],
        'results_base_url' => $atts['results_base_url'],
        'landing_make_slug' => $atts['landing_make_slug'],
        'landing_model_slug' => $atts['landing_model_slug'],
    );

    $instance_id = 'car-filters-' . wp_rand(1000, 9999);

    ob_start();
    ?>
    <div class="car-filters-container car-filters-<?php echo esc_attr($atts['layout']); ?>"
         id="<?php echo esc_attr($instance_id); ?>"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>"
         data-target="<?php echo esc_attr($atts['target']); ?>"
         data-redirect-url="<?php echo esc_attr($atts['redirect_url']); ?>"
         data-results-base-url="<?php echo esc_attr($atts['results_base_url']); ?>"
         data-landing-make-slug="<?php echo esc_attr($atts['landing_make_slug']); ?>"
         data-landing-model-slug="<?php echo esc_attr($atts['landing_model_slug']); ?>">

        <div class="car-filters-wrapper">
            <?php
            foreach ($filter_list as $filter_name) {
                if (isset($filter_map[$filter_name]) && function_exists($filter_map[$filter_name])) {
                    echo '<div class="car-filters-item car-filters-item-' . esc_attr($filter_name) . '">';
                    echo call_user_func($filter_map[$filter_name], $shared_atts);
                    echo '</div>';
                }
            }
            ?>
        </div>

        <?php if ($atts['show_button'] === 'true') : ?>
            <div class="car-filters-button-wrapper">
                <button type="button" class="car-filters-search-btn" data-group="<?php echo esc_attr($atts['group']); ?>">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue CSS and JS assets
 */
function car_filters_enqueue_assets() {
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;

    $base_path = get_stylesheet_directory() . '/includes/shortcodes/car-filters/';
    $base_url = get_stylesheet_directory_uri() . '/includes/shortcodes/car-filters/';

    // Enqueue CSS
    if (file_exists($base_path . 'car-filters.css')) {
        wp_enqueue_style(
            'car-filters-css',
            $base_url . 'car-filters.css',
            array(),
            filemtime($base_path . 'car-filters.css')
        );
    }

    // Enqueue JS
    if (file_exists($base_path . 'car-filters.js')) {
        wp_enqueue_script(
            'car-filters-js',
            $base_url . 'car-filters.js',
            array('jquery'),
            filemtime($base_path . 'car-filters.js'),
            true
        );

        // Localize script
        wp_localize_script('car-filters-js', 'carFiltersConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('car_filters_nonce'),
        ));
    }
}

/**
 * AJAX handler to get models for a selected make
 */
function car_filters_ajax_get_models() {
    check_ajax_referer('car_filters_nonce', 'nonce');

    $make_term_id = isset($_POST['make_term_id']) ? intval($_POST['make_term_id']) : 0;

    if ($make_term_id <= 0) {
        wp_send_json_error(array('message' => 'Invalid make term ID'));
        return;
    }

    $models = car_filter_get_models($make_term_id);

    $model_data = array();
    foreach ($models as $model) {
        $model_data[] = array(
            'term_id' => $model->term_id,
            'name'    => $model->name,
            'slug'    => $model->slug,
            'count'   => $model->count
        );
    }

    wp_send_json_success($model_data);
}
add_action('wp_ajax_car_filters_get_models', 'car_filters_ajax_get_models');
add_action('wp_ajax_nopriv_car_filters_get_models', 'car_filters_ajax_get_models');

/**
 * Apply optional radius filter using stored car_latitude/car_longitude meta.
 */
function car_filters_location_radius_clauses($clauses, $query) {
    global $wpdb;

    $location_filter = $query->get('car_location_radius_filter');
    if (empty($location_filter) || !is_array($location_filter)) {
        return $clauses;
    }

    $lat = isset($location_filter['lat']) ? floatval($location_filter['lat']) : 0.0;
    $lng = isset($location_filter['lng']) ? floatval($location_filter['lng']) : 0.0;
    $radius_km = isset($location_filter['radius_km']) ? floatval($location_filter['radius_km']) : 0.0;

    if ($lat === 0.0 || $lng === 0.0 || $radius_km <= 0) {
        return $clauses;
    }

    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS loc_lat_meta ON ({$wpdb->posts}.ID = loc_lat_meta.post_id AND loc_lat_meta.meta_key = 'car_latitude')";
    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS loc_lng_meta ON ({$wpdb->posts}.ID = loc_lng_meta.post_id AND loc_lng_meta.meta_key = 'car_longitude')";

    $earth_radius_km = 6371.0;
    $distance_sql = sprintf(
        "(%.4F * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(%.8F)) * COS(RADIANS(CAST(loc_lat_meta.meta_value AS DECIMAL(10,6)))) * COS(RADIANS(CAST(loc_lng_meta.meta_value AS DECIMAL(10,6))) - RADIANS(%.8F)) + SIN(RADIANS(%.8F)) * SIN(RADIANS(CAST(loc_lat_meta.meta_value AS DECIMAL(10,6))))))))",
        $earth_radius_km,
        $lat,
        $lng,
        $lat
    );

    $clauses['where'] .= " AND loc_lat_meta.meta_value <> '' AND loc_lng_meta.meta_value <> ''";
    $clauses['where'] .= " AND {$distance_sql} <= " . floatval($radius_km);

    return $clauses;
}

/**
 * AJAX handler to filter car listings
 */
function car_filters_ajax_filter_listings() {
    check_ajax_referer('car_filters_nonce', 'nonce');

    // Get filter parameters
    $make_id      = isset($_POST['make']) ? intval($_POST['make']) : 0;
    $model_id     = isset($_POST['model']) ? intval($_POST['model']) : 0;
    $price_min    = isset($_POST['price_min']) ? intval($_POST['price_min']) : 0;
    $price_max    = isset($_POST['price_max']) ? intval($_POST['price_max']) : 0;
    $mileage_min  = isset($_POST['mileage_min']) ? intval($_POST['mileage_min']) : 0;
    $mileage_max  = isset($_POST['mileage_max']) ? intval($_POST['mileage_max']) : 0;
    $year_min     = isset($_POST['year_min']) ? intval($_POST['year_min']) : 0;
    $year_max     = isset($_POST['year_max']) ? intval($_POST['year_max']) : 0;
    $fuel_type_raw = isset($_POST['fuel_type']) ? sanitize_text_field($_POST['fuel_type']) : '';
    $body_type_raw = isset($_POST['body_type']) ? sanitize_text_field($_POST['body_type']) : '';
    $fuel_types   = !empty($fuel_type_raw) ? array_map('trim', explode(',', $fuel_type_raw)) : array();
    $body_types   = !empty($body_type_raw) ? array_map('trim', explode(',', $body_type_raw)) : array();
    $location_lat = isset($_POST['location_lat']) ? floatval($_POST['location_lat']) : 0.0;
    $location_lng = isset($_POST['location_lng']) ? floatval($_POST['location_lng']) : 0.0;
    $location_radius_km = isset($_POST['location_radius_km']) ? floatval($_POST['location_radius_km']) : 0.0;
    $page         = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;

    // Base listing attributes (passed from the target)
    $atts = isset($_POST['listing_atts']) ? json_decode(stripslashes($_POST['listing_atts']), true) : array();
    $atts = is_array($atts) ? $atts : array();

    // Build query args
    $args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => isset($atts['posts_per_page']) ? intval($atts['posts_per_page']) : 12,
        'paged'          => $page,
    );

    $meta_query = array('relation' => 'AND');
    $tax_query = array();

    // Taxonomy filter (make/model)
    if ($model_id > 0) {
        $tax_query[] = array(
            'taxonomy' => 'car_make',
            'field'    => 'term_id',
            'terms'    => $model_id,
        );
    } elseif ($make_id > 0) {
        // Get all models for this make
        $models = car_filter_get_models($make_id);
        if (!empty($models)) {
            $model_ids = wp_list_pluck($models, 'term_id');
            $tax_query[] = array(
                'taxonomy' => 'car_make',
                'field'    => 'term_id',
                'terms'    => $model_ids,
            );
        }
    }

    // Price range
    if ($price_min > 0) {
        $meta_query[] = array(
            'key'     => 'price',
            'value'   => $price_min,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }
    if ($price_max > 0) {
        $meta_query[] = array(
            'key'     => 'price',
            'value'   => $price_max,
            'compare' => '<=',
            'type'    => 'NUMERIC',
        );
    }

    // Mileage range
    if ($mileage_min > 0) {
        $meta_query[] = array(
            'key'     => 'mileage',
            'value'   => $mileage_min,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }
    if ($mileage_max > 0) {
        $meta_query[] = array(
            'key'     => 'mileage',
            'value'   => $mileage_max,
            'compare' => '<=',
            'type'    => 'NUMERIC',
        );
    }

    // Year range
    if ($year_min > 0) {
        $meta_query[] = array(
            'key'     => 'year',
            'value'   => $year_min,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }
    if ($year_max > 0) {
        $meta_query[] = array(
            'key'     => 'year',
            'value'   => $year_max,
            'compare' => '<=',
            'type'    => 'NUMERIC',
        );
    }

    // Fuel type (supports multiple comma-separated values)
    if (!empty($fuel_types)) {
        if (count($fuel_types) === 1) {
            $meta_query[] = array(
                'key'     => 'fuel_type',
                'value'   => $fuel_types[0],
                'compare' => '=',
            );
        } else {
            $meta_query[] = array(
                'key'     => 'fuel_type',
                'value'   => $fuel_types,
                'compare' => 'IN',
            );
        }
    }

    // Body type (supports multiple comma-separated values)
    if (!empty($body_types)) {
        if (count($body_types) === 1) {
            $meta_query[] = array(
                'key'     => 'body_type',
                'value'   => $body_types[0],
                'compare' => '=',
            );
        } else {
            $meta_query[] = array(
                'key'     => 'body_type',
                'value'   => $body_types,
                'compare' => 'IN',
            );
        }
    }

    // Exclude sold (default behavior)
    if (!isset($atts['show_sold']) || $atts['show_sold'] !== 'true') {
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => 'is_sold',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key'     => 'is_sold',
                'value'   => '1',
                'compare' => '!='
            )
        );
    }

    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    // Sorting
    $orderby = isset($atts['orderby']) ? $atts['orderby'] : 'date';
    $order = isset($atts['order']) ? strtoupper($atts['order']) : 'DESC';

    switch ($orderby) {
        case 'price':
            $args['meta_key'] = 'price';
            $args['orderby'] = 'meta_value_num';
            break;
        case 'mileage':
            $args['meta_key'] = 'mileage';
            $args['orderby'] = 'meta_value_num';
            break;
        case 'year':
            $args['meta_key'] = 'year';
            $args['orderby'] = 'meta_value_num';
            break;
        default:
            $args['orderby'] = 'date';
    }
    $args['order'] = $order;

    $use_location_radius_filter = ($location_lat !== 0.0 && $location_lng !== 0.0 && $location_radius_km > 0);
    if ($use_location_radius_filter) {
        $args['car_location_radius_filter'] = array(
            'lat' => $location_lat,
            'lng' => $location_lng,
            'radius_km' => $location_radius_km,
        );
    }

    // Execute query with featured-first sorting
    if ($use_location_radius_filter) {
        add_filter('posts_clauses', 'car_filters_location_radius_clauses', 11, 2);
    }
    $car_query = car_listings_execute_query($args);
    if ($use_location_radius_filter) {
        remove_filter('posts_clauses', 'car_filters_location_radius_clauses', 11, 2);
    }

    // Pre-fetch meta
    if ($car_query->have_posts()) {
        $post_ids = wp_list_pluck($car_query->posts, 'ID');
        update_postmeta_cache($post_ids);
        update_post_thumbnail_cache($car_query);
    }

    // Determine which card renderer to use
    $card_type = isset($atts['card_type']) ? $atts['card_type'] : '';
    $use_car_card = ($card_type === 'car_card') && function_exists('render_car_card');

    // Render cards
    ob_start();
    if ($car_query->have_posts()) {
        $listing_card_index = 0;
        while ($car_query->have_posts()) {
            $car_query->the_post();
            if ($use_car_card) {
                render_car_card(get_the_ID(), array('listing_index' => $listing_card_index));
            } elseif (function_exists('car_listings_render_card')) {
                car_listings_render_card(get_the_ID());
            }
            $listing_card_index++;
        }
    } else {
        echo '<p class="car-listings-no-results">No car listings found matching your criteria.</p>';
    }
    $html = ob_get_clean();

    // Build pagination HTML if multiple pages
    $pagination_html = '';
    if ($car_query->max_num_pages > 1) {
        $pagination_html = paginate_links(array(
            'total'     => $car_query->max_num_pages,
            'current'   => $page,
            'prev_text' => 'Previous',
            'next_text' => 'Next',
            'type'      => 'list',
            'base'      => '#%#%',     // Dummy base so links are intercepted by JS
            'format'    => '%#%',
        ));
    }

    wp_reset_postdata();

    wp_send_json_success(array(
        'html'            => $html,
        'pagination_html' => $pagination_html,
        'found_posts'     => $car_query->found_posts,
        'max_pages'       => $car_query->max_num_pages,
        'current_page'    => $page,
    ));
}
add_action('wp_ajax_car_filters_filter_listings', 'car_filters_ajax_filter_listings');
add_action('wp_ajax_nopriv_car_filters_filter_listings', 'car_filters_ajax_filter_listings');

/**
 * AJAX handler to get available options for cascading dropdowns
 */
function car_filters_ajax_get_available_options() {
    check_ajax_referer('car_filters_nonce', 'nonce');

    $filters = array(
        'make'        => isset($_POST['make']) ? intval($_POST['make']) : 0,
        'model'       => isset($_POST['model']) ? intval($_POST['model']) : 0,
        'price_min'   => isset($_POST['price_min']) ? intval($_POST['price_min']) : 0,
        'price_max'   => isset($_POST['price_max']) ? intval($_POST['price_max']) : 0,
        'mileage_min' => isset($_POST['mileage_min']) ? intval($_POST['mileage_min']) : 0,
        'mileage_max' => isset($_POST['mileage_max']) ? intval($_POST['mileage_max']) : 0,
        'year_min'    => isset($_POST['year_min']) ? intval($_POST['year_min']) : 0,
        'year_max'    => isset($_POST['year_max']) ? intval($_POST['year_max']) : 0,
        'fuel_type'   => isset($_POST['fuel_type']) ? sanitize_text_field($_POST['fuel_type']) : '',
        'body_type'   => isset($_POST['body_type']) ? sanitize_text_field($_POST['body_type']) : '',
        'fuel_types'  => !empty($_POST['fuel_type']) ? array_map('trim', explode(',', sanitize_text_field($_POST['fuel_type']))) : array(),
        'body_types'  => !empty($_POST['body_type']) ? array_map('trim', explode(',', sanitize_text_field($_POST['body_type']))) : array(),
    );

    $data = car_filter_get_available_options_data($filters);

    wp_send_json_success($data);
}
add_action('wp_ajax_car_filters_get_available_options', 'car_filters_ajax_get_available_options');
add_action('wp_ajax_nopriv_car_filters_get_available_options', 'car_filters_ajax_get_available_options');

/**
 * AJAX handler to resolve make/model slug
 * Used by JavaScript to determine if a slug is make or make-model
 */
function car_filters_ajax_resolve_slug() {
    check_ajax_referer('car_filters_nonce', 'nonce');

    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';

    if (empty($slug)) {
        wp_send_json_error(array('message' => 'No slug provided'));
        return;
    }

    $result = array(
        'make' => null,
        'model' => null,
        'make_term_id' => null,
        'model_term_id' => null
    );

    $term = get_term_by('slug', $slug, 'car_make');

    if ($term && $term->parent > 0) {
        // It's a model
        $parent = get_term($term->parent, 'car_make');
        if ($parent && !is_wp_error($parent)) {
            $result['make'] = $parent->slug;
            $result['make_term_id'] = $parent->term_id;
            $result['model'] = $slug;
            $result['model_term_id'] = $term->term_id;
        }
    } elseif ($term && $term->parent === 0) {
        // It's a make
        $result['make'] = $slug;
        $result['make_term_id'] = $term->term_id;
    }

    wp_send_json_success($result);
}
add_action('wp_ajax_car_filters_resolve_slug', 'car_filters_ajax_resolve_slug');
add_action('wp_ajax_nopriv_car_filters_resolve_slug', 'car_filters_ajax_resolve_slug');
