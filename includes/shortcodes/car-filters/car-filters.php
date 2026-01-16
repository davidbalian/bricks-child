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

/**
 * ============================================
 * PRETTY URL SUPPORT (JetSmartFilters-style)
 * ============================================
 *
 * URL Format: /cars/filter/make:bmw-m2/meta/price!range:10000_50000/
 */

/**
 * Add rewrite rules for pretty filter URLs
 * Pattern: /{page}/filter/{filter_string}/
 */
add_action('init', 'car_filters_add_rewrite_rules');
function car_filters_add_rewrite_rules() {
    // Match: /any-page/filter/anything/
    add_rewrite_rule(
        '([^/]+)/filter/(.+?)/?$',
        'index.php?pagename=$matches[1]&car_filter=$matches[2]',
        'top'
    );
}

/**
 * Register custom query var
 */
add_filter('query_vars', 'car_filters_query_vars');
function car_filters_query_vars($vars) {
    $vars[] = 'car_filter';
    return $vars;
}

/**
 * Parse pretty filter URL into filter parameters
 *
 * @param string $filter_string The filter portion of the URL
 * @return array Parsed filter parameters
 */
function car_filters_parse_filter_url($filter_string) {
    $result = array();

    // Parse make:slug or make:parent-child
    if (preg_match('/make:([^\/]+)/', $filter_string, $matches)) {
        $combined_slug = sanitize_text_field($matches[1]);

        // Strategy: Database lookup to determine make vs make-model
        // 1. Try exact match as a term (could be make or model)
        $term = get_term_by('slug', $combined_slug, 'car_make');

        if ($term && $term->parent > 0) {
            // It's a model (child term) - extract parent make
            $parent = get_term($term->parent, 'car_make');
            if ($parent && !is_wp_error($parent)) {
                $result['make'] = $parent->slug;
                $result['model'] = $combined_slug;
            }
        } elseif ($term && $term->parent === 0) {
            // It's a make (parent term)
            $result['make'] = $combined_slug;
        } else {
            // No exact match - try splitting: find longest parent match
            // e.g., "mercedes-benz-c-class" -> try "mercedes-benz" + "c-class"
            $parts = explode('-', $combined_slug);
            $found = false;

            for ($i = count($parts) - 1; $i > 0; $i--) {
                $potential_make = implode('-', array_slice($parts, 0, $i));
                $make_term = get_term_by('slug', $potential_make, 'car_make');

                if ($make_term && $make_term->parent === 0) {
                    // Verify model is child of this make
                    $model_term = get_term_by('slug', $combined_slug, 'car_make');
                    if ($model_term && $model_term->parent === $make_term->term_id) {
                        $result['make'] = $potential_make;
                        $result['model'] = $combined_slug;
                        $found = true;
                        break;
                    }
                }
            }

            // Fallback: treat as make only
            if (!$found) {
                $result['make'] = $combined_slug;
            }
        }
    }

    // Parse meta/field:value;field!range:min_max
    if (preg_match('/meta\/([^\/]+)/', $filter_string, $matches)) {
        $meta_parts = explode(';', $matches[1]);

        foreach ($meta_parts as $part) {
            if (strpos($part, '!range:') !== false) {
                // Range: price!range:10000_50000
                $range_split = explode('!range:', $part);
                if (count($range_split) === 2) {
                    $field = sanitize_key($range_split[0]);
                    $values = explode('_', $range_split[1]);

                    if (count($values) === 2) {
                        $min = intval($values[0]);
                        $max = intval($values[1]);

                        // Only set if not default placeholder values
                        if ($min > 0) {
                            $result[$field . '_min'] = $min;
                        }
                        // Check for reasonable max values (not placeholder)
                        if ($max > 0 && $max < 999999999) {
                            $result[$field . '_max'] = $max;
                        }
                    }
                }
            } else if (strpos($part, ':') !== false) {
                // Simple: fuel_type:Diesel
                $simple_split = explode(':', $part, 2);
                if (count($simple_split) === 2) {
                    $field = sanitize_key($simple_split[0]);
                    $value = sanitize_text_field($simple_split[1]);
                    $result[$field] = $value;
                }
            }
        }
    }

    return $result;
}

/**
 * Get parsed filter data from pretty URL
 * Returns cached result for performance
 *
 * @return array|null Parsed filter data or null if not a filter URL
 */
function car_filters_get_parsed_url_data() {
    static $cached_result = null;
    static $has_checked = false;

    if ($has_checked) {
        return $cached_result;
    }

    $has_checked = true;
    $filter_string = get_query_var('car_filter');

    if (!empty($filter_string)) {
        $cached_result = car_filters_parse_filter_url($filter_string);
    }

    return $cached_result;
}

/**
 * AJAX handler to resolve make/model slug
 * Used by JavaScript to determine if a combined slug is make or make-model
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
