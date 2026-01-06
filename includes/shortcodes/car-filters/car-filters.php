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
        'layout'       => 'horizontal',                 // horizontal, vertical, inline
        'show_button'  => 'true',
        'button_text'  => 'Search Cars',
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
    );

    $instance_id = 'car-filters-' . wp_rand(1000, 9999);

    ob_start();
    ?>
    <div class="car-filters-container car-filters-<?php echo esc_attr($atts['layout']); ?>"
         id="<?php echo esc_attr($instance_id); ?>"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>"
         data-target="<?php echo esc_attr($atts['target']); ?>"
         data-redirect-url="<?php echo esc_attr($atts['redirect_url']); ?>">

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
    $fuel_type    = isset($_POST['fuel_type']) ? sanitize_text_field($_POST['fuel_type']) : '';
    $body_type    = isset($_POST['body_type']) ? sanitize_text_field($_POST['body_type']) : '';

    // Base listing attributes (passed from the target)
    $atts = isset($_POST['listing_atts']) ? json_decode(stripslashes($_POST['listing_atts']), true) : array();
    $atts = is_array($atts) ? $atts : array();

    // Build query args
    $args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => isset($atts['posts_per_page']) ? intval($atts['posts_per_page']) : 12,
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

    // Fuel type
    if (!empty($fuel_type)) {
        $meta_query[] = array(
            'key'     => 'fuel_type',
            'value'   => $fuel_type,
            'compare' => '=',
        );
    }

    // Body type
    if (!empty($body_type)) {
        $meta_query[] = array(
            'key'     => 'body_type',
            'value'   => $body_type,
            'compare' => '=',
        );
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

    // Execute query
    $car_query = new WP_Query($args);

    // Pre-fetch meta
    if ($car_query->have_posts()) {
        $post_ids = wp_list_pluck($car_query->posts, 'ID');
        update_postmeta_cache($post_ids);
    }

    // Render cards
    ob_start();
    if ($car_query->have_posts()) {
        while ($car_query->have_posts()) {
            $car_query->the_post();
            if (function_exists('car_listings_render_card')) {
                car_listings_render_card(get_the_ID());
            }
        }
    } else {
        echo '<p class="car-listings-no-results">No car listings found matching your criteria.</p>';
    }
    $html = ob_get_clean();
    wp_reset_postdata();

    wp_send_json_success(array(
        'html'        => $html,
        'found_posts' => $car_query->found_posts,
        'max_pages'   => $car_query->max_num_pages,
    ));
}
add_action('wp_ajax_car_filters_filter_listings', 'car_filters_ajax_filter_listings');
add_action('wp_ajax_nopriv_car_filters_filter_listings', 'car_filters_ajax_filter_listings');
