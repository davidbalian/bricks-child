<?php
/**
 * Car Listings Shortcode
 *
 * A robust, customizable shortcode for displaying car listings
 * in grid, carousel, or vertical layout with various filtering options.
 *
 * Usage: [car_listings layout="grid" posts_per_page="12" featured="true"]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register the shortcode
add_shortcode('car_listings', 'car_listings_shortcode');

/**
 * Main shortcode function
 */
function car_listings_shortcode($atts) {
    // Parse and sanitize attributes
    $atts = shortcode_atts(array(
        'layout'          => 'grid',        // grid, carousel, vertical
        'posts_per_page'  => 12,
        'infinite_scroll' => 'false',
        'featured'        => 'false',
        'favorites'       => 'false',
        'user_id'         => '',
        'author'          => '',            // 'current' for logged-in user
        'orderby'         => 'date',        // date, price, mileage, year
        'order'           => 'DESC',
        'show_sold'       => 'false',
        'offset'          => 0,
        'id'              => '',            // Explicit ID for filter targeting
        'filter_group'    => '',            // Auto-connect to filter group
    ), $atts, 'car_listings');

    // Generate instance ID if not provided
    if (empty($atts['id'])) {
        $atts['id'] = 'car-listings-' . wp_rand(1000, 9999);
    }

    // Sanitize layout
    $valid_layouts = array('grid', 'carousel', 'vertical');
    if (!in_array($atts['layout'], $valid_layouts)) {
        $atts['layout'] = 'grid';
    }

    // Enqueue assets (only when shortcode is used)
    car_listings_enqueue_assets($atts);

    // Build WP_Query arguments
    $query_args = car_listings_build_query_args($atts);

    // Execute query with featured-first sorting
    $car_query = car_listings_execute_query($query_args);

    // Start output buffering
    ob_start();

    // Render output based on layout
    car_listings_render_output($car_query, $atts);

    wp_reset_postdata();

    return ob_get_clean();
}

/**
 * Build WP_Query arguments based on shortcode attributes
 */
function car_listings_build_query_args($atts) {
    $args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => intval($atts['posts_per_page']),
    );

    // Handle offset
    if (!empty($atts['offset'])) {
        $args['offset'] = intval($atts['offset']);
    }

    // Meta query array
    $meta_query = array('relation' => 'AND');

    // === FILTERING BY SOURCE FLAGS ===

    // Featured listings filter
    if ($atts['featured'] === 'true') {
        $meta_query[] = array(
            'key'     => 'is_featured',
            'value'   => '1',
            'compare' => '='
        );
    }

    // Favorites filter (requires logged-in user)
    if ($atts['favorites'] === 'true') {
        $user_id = get_current_user_id();
        if ($user_id) {
            $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
            $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
            if (!empty($favorite_cars)) {
                $args['post__in'] = $favorite_cars;
            } else {
                // No favorites - return empty query
                $args['post__in'] = array(0);
            }
        } else {
            // Not logged in - return empty
            $args['post__in'] = array(0);
        }
    }

    // User ID filter
    if (!empty($atts['user_id'])) {
        $args['author'] = intval($atts['user_id']);
    }

    // Author 'current' shorthand
    if ($atts['author'] === 'current') {
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $args['author'] = $current_user_id;
        } else {
            $args['post__in'] = array(0); // Not logged in
        }
    }

    // === SOLD LISTINGS FILTER ===
    // Simplified: exclude only cars explicitly marked as sold (is_sold = '1')
    if ($atts['show_sold'] !== 'true') {
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

    // === URL PARAMETER FILTERS (for filter integration) ===
    $tax_query = array();

    // Check for pretty URL filter data first
    $pretty_url_filters = null;
    if (function_exists('car_filters_get_parsed_url_data')) {
        $pretty_url_filters = car_filters_get_parsed_url_data();
    }

    // Make/Model filter from URL
    if ($pretty_url_filters && !empty($pretty_url_filters['model'])) {
        // Pretty URL: model is the full slug (e.g., "bmw-m2")
        $model_term = get_term_by('slug', $pretty_url_filters['model'], 'car_make');
        if ($model_term) {
            $tax_query[] = array(
                'taxonomy' => 'car_make',
                'field'    => 'term_id',
                'terms'    => $model_term->term_id,
            );
        }
    } elseif ($pretty_url_filters && !empty($pretty_url_filters['make'])) {
        // Pretty URL: make only
        $make_term = get_term_by('slug', $pretty_url_filters['make'], 'car_make');
        if ($make_term && $make_term->parent === 0) {
            // Get all models for this make
            $models = get_terms(array(
                'taxonomy' => 'car_make',
                'parent' => $make_term->term_id,
                'hide_empty' => true,
                'fields' => 'ids',
            ));
            if (!empty($models) && !is_wp_error($models)) {
                $tax_query[] = array(
                    'taxonomy' => 'car_make',
                    'field'    => 'term_id',
                    'terms'    => $models,
                );
            }
        }
    } elseif (isset($_GET['model']) && !empty($_GET['model'])) {
        // Fallback: Query param model
        $model_param = sanitize_text_field($_GET['model']);
        $model_term = get_term_by('slug', $model_param, 'car_make');
        if ($model_term) {
            $tax_query[] = array(
                'taxonomy' => 'car_make',
                'field'    => 'term_id',
                'terms'    => $model_term->term_id,
            );
        }
    } elseif (isset($_GET['make']) && !empty($_GET['make'])) {
        // Fallback: Query param make
        $make_param = sanitize_text_field($_GET['make']);
        $make_term = get_term_by('slug', $make_param, 'car_make');
        if ($make_term && $make_term->parent === 0) {
            // Get all models for this make
            $models = get_terms(array(
                'taxonomy' => 'car_make',
                'parent' => $make_term->term_id,
                'hide_empty' => true,
                'fields' => 'ids',
            ));
            if (!empty($models) && !is_wp_error($models)) {
                $tax_query[] = array(
                    'taxonomy' => 'car_make',
                    'field'    => 'term_id',
                    'terms'    => $models,
                );
            }
        }
    }

    // Helper to get filter value from pretty URL or $_GET
    $get_filter_value = function($key) use ($pretty_url_filters) {
        if ($pretty_url_filters && isset($pretty_url_filters[$key]) && $pretty_url_filters[$key] !== null) {
            return $pretty_url_filters[$key];
        }
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            return sanitize_text_field($_GET[$key]);
        }
        return null;
    };

    // Price range from URL
    $price_min = $get_filter_value('price_min');
    if ($price_min !== null && is_numeric($price_min)) {
        $meta_query[] = array(
            'key'     => 'price',
            'value'   => intval($price_min),
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }
    $price_max = $get_filter_value('price_max');
    if ($price_max !== null && is_numeric($price_max)) {
        $meta_query[] = array(
            'key'     => 'price',
            'value'   => intval($price_max),
            'compare' => '<=',
            'type'    => 'NUMERIC',
        );
    }

    // Mileage range from URL
    $mileage_min = $get_filter_value('mileage_min');
    if ($mileage_min !== null && is_numeric($mileage_min)) {
        $meta_query[] = array(
            'key'     => 'mileage',
            'value'   => intval($mileage_min),
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }
    $mileage_max = $get_filter_value('mileage_max');
    if ($mileage_max !== null && is_numeric($mileage_max)) {
        $meta_query[] = array(
            'key'     => 'mileage',
            'value'   => intval($mileage_max),
            'compare' => '<=',
            'type'    => 'NUMERIC',
        );
    }

    // Year range from URL
    $year_min = $get_filter_value('year_min');
    if ($year_min !== null && is_numeric($year_min)) {
        $meta_query[] = array(
            'key'     => 'year',
            'value'   => intval($year_min),
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }
    $year_max = $get_filter_value('year_max');
    if ($year_max !== null && is_numeric($year_max)) {
        $meta_query[] = array(
            'key'     => 'year',
            'value'   => intval($year_max),
            'compare' => '<=',
            'type'    => 'NUMERIC',
        );
    }

    // Fuel type from URL
    $fuel_type = $get_filter_value('fuel_type');
    if (!empty($fuel_type)) {
        $meta_query[] = array(
            'key'     => 'fuel_type',
            'value'   => $fuel_type,
            'compare' => '=',
        );
    }

    // Body type from URL
    $body_type = $get_filter_value('body_type');
    if (!empty($body_type)) {
        $meta_query[] = array(
            'key'     => 'body_type',
            'value'   => $body_type,
            'compare' => '=',
        );
    }

    // Add tax_query if we have conditions
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    // Add meta_query if we have conditions
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    // === SORTING ===
    $valid_orderby = array('date', 'price', 'mileage', 'year');
    $orderby = in_array($atts['orderby'], $valid_orderby) ? $atts['orderby'] : 'date';
    $order = strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC';

    // Store sort params for the filter
    $args['_car_listings_orderby'] = $orderby;
    $args['_car_listings_order'] = $order;

    // We'll use a filter to add featured-first sorting via SQL
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

    return $args;
}

/**
 * Render the car listings output
 */
function car_listings_render_output($car_query, $atts) {
    $layout = sanitize_text_field($atts['layout']);
    $layout_class = 'car-listings-' . $layout;
    $infinite_scroll = $atts['infinite_scroll'] === 'true';

    // Pre-fetch all post meta in one query to avoid N+1 queries
    if ($car_query->have_posts()) {
        $post_ids = wp_list_pluck($car_query->posts, 'ID');
        update_postmeta_cache($post_ids);
    }

    // Use ID from attributes (already set in main function)
    $instance_id = $atts['id'];
    $filter_group = $atts['filter_group'];
    ?>

    <div class="car-listings-container <?php echo esc_attr($layout_class); ?>"
         id="<?php echo esc_attr($instance_id); ?>"
         data-layout="<?php echo esc_attr($layout); ?>"
         data-infinite-scroll="<?php echo $infinite_scroll ? 'true' : 'false'; ?>"
         data-page="1"
         data-max-pages="<?php echo esc_attr($car_query->max_num_pages); ?>"
         data-atts="<?php echo esc_attr(wp_json_encode($atts)); ?>"
         <?php if (!empty($filter_group)) : ?>data-filter-group="<?php echo esc_attr($filter_group); ?>"<?php endif; ?>>

        <?php if ($car_query->have_posts()) : ?>
            <div class="car-listings-wrapper">
                <?php while ($car_query->have_posts()) : $car_query->the_post(); ?>
                    <?php car_listings_render_card(get_the_ID()); ?>
                <?php endwhile; ?>
            </div>

            <?php if ($infinite_scroll && $car_query->max_num_pages > 1) : ?>
                <div class="car-listings-loader" style="display: none;">
                    <span class="loader-spinner"></span>
                    <span class="loader-text">Loading more...</span>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <p class="car-listings-no-results">No car listings found.</p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render individual car card (minimal template)
 */
function car_listings_render_card($post_id) {
    // Get ACF fields
    $make = get_field('make', $post_id);
    $model = get_field('model', $post_id);
    $price = get_field('price', $post_id);
    $year = get_field('year', $post_id);
    $mileage = get_field('mileage', $post_id);
    $car_city = get_field('car_city', $post_id);
    $is_sold = get_field('is_sold', $post_id);

    // Get featured image
    $image_url = get_the_post_thumbnail_url($post_id, 'medium');
    if (!$image_url) {
        $car_images = get_field('car_images', $post_id);
        if (!empty($car_images) && is_array($car_images)) {
            $first_image = $car_images[0];
            if (is_array($first_image) && isset($first_image['ID'])) {
                $image_url = wp_get_attachment_image_url($first_image['ID'], 'medium');
            } elseif (is_numeric($first_image)) {
                $image_url = wp_get_attachment_image_url($first_image, 'medium');
            }
        }
    }

    // Clean and format values
    $clean_year = str_replace(',', '', $year);
    $formatted_price = number_format(floatval(str_replace(',', '', $price)));
    $formatted_mileage = number_format(floatval(str_replace(',', '', $mileage)));
    ?>

    <article class="car-listings-card<?php echo $is_sold ? ' is-sold' : ''; ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="car-listings-card-link">
            <div class="car-listings-card-image">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>"
                         alt="<?php echo esc_attr($clean_year . ' ' . $make . ' ' . $model); ?>"
                         loading="lazy">
                <?php else : ?>
                    <div class="car-listings-no-image">No Image</div>
                <?php endif; ?>

                <?php if ($is_sold) : ?>
                    <span class="car-listings-sold-badge">SOLD</span>
                <?php endif; ?>
            </div>

            <div class="car-listings-card-content">
                <h3 class="car-listings-card-title"><?php echo esc_html($make . ' ' . $model); ?></h3>

                <div class="car-listings-card-meta">
                    <?php if ($clean_year) : ?>
                        <span class="car-listings-meta-year"><?php echo esc_html($clean_year); ?></span>
                    <?php endif; ?>
                    <?php if ($clean_year && $mileage) : ?>
                        <span class="car-listings-meta-separator">|</span>
                    <?php endif; ?>
                    <?php if ($mileage) : ?>
                        <span class="car-listings-meta-mileage"><?php echo esc_html($formatted_mileage); ?> km</span>
                    <?php endif; ?>
                </div>

                <?php if ($price) : ?>
                    <div class="car-listings-card-price">
                        &euro;<?php echo esc_html($formatted_price); ?>
                    </div>
                <?php endif; ?>

                <?php if ($car_city) : ?>
                    <div class="car-listings-card-location">
                        <?php echo esc_html($car_city); ?>
                    </div>
                <?php endif; ?>
            </div>
        </a>

        <?php car_listings_render_favorite_button($post_id); ?>
    </article>
    <?php
}

/**
 * Render favorite button inline (avoids do_shortcode overhead)
 */
function car_listings_render_favorite_button($post_id) {
    static $user_id = null;
    static $favorite_cars = null;

    // Cache user data for all cards in the loop
    if ($user_id === null) {
        $user_id = get_current_user_id();
        $favorite_cars = $user_id ? get_user_meta($user_id, 'favorite_cars', true) : array();
        $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
    }

    $is_favorite = $user_id && in_array($post_id, $favorite_cars);
    $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
    $button_class = 'favorite-btn favorite-btn-listing' . ($is_favorite ? ' active' : '');
    ?>
    <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($post_id); ?>" title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
        <i class="<?php echo esc_attr($heart_class); ?>"></i>
    </button>
    <?php
}

/**
 * Enqueue CSS and JS assets only when shortcode is used
 */
function car_listings_enqueue_assets($atts) {
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;

    $base_path = get_stylesheet_directory() . '/includes/shortcodes/car-listings/';
    $base_url = get_stylesheet_directory_uri() . '/includes/shortcodes/car-listings/';

    // Enqueue CSS
    wp_enqueue_style(
        'car-listings-shortcode',
        $base_url . 'car-listings.css',
        array(),
        file_exists($base_path . 'car-listings.css') ? filemtime($base_path . 'car-listings.css') : '1.0'
    );

    // Enqueue JS (only if infinite scroll or carousel)
    if ($atts['infinite_scroll'] === 'true' || $atts['layout'] === 'carousel') {
        wp_enqueue_script(
            'car-listings-shortcode',
            $base_url . 'car-listings.js',
            array('jquery'),
            file_exists($base_path . 'car-listings.js') ? filemtime($base_path . 'car-listings.js') : '1.0',
            true
        );

        wp_localize_script('car-listings-shortcode', 'carListingsConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('car_listings_load_more'),
        ));
    }
}

/**
 * AJAX handler for loading more listings (infinite scroll)
 */
function car_listings_ajax_load_more() {
    // Verify nonce
    check_ajax_referer('car_listings_load_more', 'nonce');

    // Get parameters
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $atts = isset($_POST['atts']) ? json_decode(stripslashes($_POST['atts']), true) : array();
    $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : array();

    // Temporarily set $_GET from filters so build_query_args can use them
    // This allows the URL parameter filtering logic to work in AJAX context
    if (!empty($filters) && is_array($filters)) {
        // Handle make_slug from pretty URLs (resolve to make/model)
        if (!empty($filters['make_slug']) && function_exists('car_filters_parse_filter_url')) {
            $resolved = car_filters_parse_filter_url('make:' . $filters['make_slug']);
            if (!empty($resolved['model'])) {
                $_GET['model'] = sanitize_text_field($resolved['model']);
            } elseif (!empty($resolved['make'])) {
                $_GET['make'] = sanitize_text_field($resolved['make']);
            }
            unset($filters['make_slug']);
        }

        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $_GET[$key] = sanitize_text_field($value);
            }
        }
    }

    // Sanitize atts
    $atts = shortcode_atts(array(
        'layout'          => 'grid',
        'posts_per_page'  => 12,
        'infinite_scroll' => 'false',
        'featured'        => 'false',
        'favorites'       => 'false',
        'user_id'         => '',
        'author'          => '',
        'orderby'         => 'date',
        'order'           => 'DESC',
        'show_sold'       => 'false',
        'offset'          => 0,
    ), $atts);

    // Build query args (will use $_GET for filters)
    $query_args = car_listings_build_query_args($atts);
    $query_args['paged'] = $page;

    // Remove offset for paged queries (offset breaks paged)
    unset($query_args['offset']);

    // Execute query with featured-first sorting
    $car_query = car_listings_execute_query($query_args);

    // Pre-fetch all post meta in one query
    if ($car_query->have_posts()) {
        $post_ids = wp_list_pluck($car_query->posts, 'ID');
        update_postmeta_cache($post_ids);
    }

    // Render cards
    ob_start();
    if ($car_query->have_posts()) {
        while ($car_query->have_posts()) {
            $car_query->the_post();
            car_listings_render_card(get_the_ID());
        }
    }
    $html = ob_get_clean();
    wp_reset_postdata();

    wp_send_json_success(array(
        'html'      => $html,
        'has_more'  => $page < $car_query->max_num_pages,
        'max_pages' => $car_query->max_num_pages,
    ));
}
add_action('wp_ajax_car_listings_load_more', 'car_listings_ajax_load_more');
add_action('wp_ajax_nopriv_car_listings_load_more', 'car_listings_ajax_load_more');

/**
 * Filter to add featured-first sorting via SQL
 * This adds a LEFT JOIN for is_featured and sorts by it first
 */
function car_listings_featured_first_orderby($clauses, $query) {
    global $wpdb;

    // Add LEFT JOIN for is_featured meta
    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS featured_meta ON ({$wpdb->posts}.ID = featured_meta.post_id AND featured_meta.meta_key = 'is_featured')";

    // Prepend featured sorting to orderby (featured=1 first, then NULL/0)
    $clauses['orderby'] = "CASE WHEN featured_meta.meta_value = '1' THEN 0 ELSE 1 END ASC, " . $clauses['orderby'];

    return $clauses;
}

/**
 * Execute a car listings query with featured-first sorting
 * Adds the filter only for this specific query, then removes it
 */
function car_listings_execute_query($query_args) {
    add_filter('posts_clauses', 'car_listings_featured_first_orderby', 10, 2);
    $query = new WP_Query($query_args);
    remove_filter('posts_clauses', 'car_listings_featured_first_orderby', 10, 2);
    return $query;
}
