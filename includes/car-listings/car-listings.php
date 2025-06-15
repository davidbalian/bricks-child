<?php
/**
 * Car Listings Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Include helper files
require_once get_stylesheet_directory() . '/includes/car-listings/car-listings-data.php';
require_once get_stylesheet_directory() . '/includes/car-listings/car-listings-query.php';
require_once get_stylesheet_directory() . '/includes/car-listings/car-filter-form.php';
require_once get_stylesheet_directory() . '/includes/car-listings/geo-utils.php';

// Define Cyprus center coordinates and default radius
define('CYPRUS_CENTER_LAT', 35.1856);
define('CYPRUS_CENTER_LNG', 33.3823);
define('CYPRUS_DEFAULT_RADIUS', 150); // 150km to cover all of Cyprus

// Register the shortcode
add_shortcode('car_listings', 'display_car_listings');

function display_car_listings($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'per_page' => 12,
        'orderby' => 'date',
        'order' => 'DESC'
    ), $atts);

    // Enqueue the main stylesheet for this shortcode
    wp_enqueue_style(
        'car-listings-style',
        get_stylesheet_directory_uri() . '/includes/car-listings/car-listings.css',
        array(), 
        filemtime(get_stylesheet_directory() . '/includes/car-listings/car-listings.css')
    );


    // Localize script for AJAX functionality (favorites)
    wp_localize_script('jquery', 'carListingsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('toggle_favorite_car')
    ));

    // Start output buffering
    ob_start();

    // Get the current page number
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Check if a location filter is active via GET parameters
    $has_location_filter_in_url = isset($_GET['lat']) && isset($_GET['lng']) && isset($_GET['radius']);

    // Determine location filter values - use Cyprus defaults if no custom location is set
    $filter_lat = CYPRUS_CENTER_LAT;
    $filter_lng = CYPRUS_CENTER_LNG;
    $filter_radius = CYPRUS_DEFAULT_RADIUS;
    $is_default_cyprus_filter = true;

    // Override with URL parameters if they exist
    if ($has_location_filter_in_url) {
        $filter_lat = floatval($_GET['lat']);
        $filter_lng = floatval($_GET['lng']);
        $filter_radius = floatval($_GET['radius']);
        $is_default_cyprus_filter = false;
    }

    // Build the query arguments using the helper function with location filter
    $args = array(
        'post_type' => 'car',
        'posts_per_page' => $atts['per_page'], 
        'paged' => $paged,
        'orderby' => $atts['orderby'],       // Use shortcode attribute
        'order'   => $atts['order'],         // Use shortcode attribute
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'is_sold',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => 'is_sold',
                'value' => '1',
                'compare' => '!='
            )
        )
    );

    // Apply location filter using the build_car_listings_query_args function
    $location_filters = array(
        'lat' => $filter_lat,
        'lng' => $filter_lng,
        'radius' => $filter_radius
    );
    
    $args = build_car_listings_query_args($atts, $paged, $location_filters);

    // Get car listings
    $car_query = new WP_Query($args);

    // Since we're using the bounding box approach, we need to do precise circular filtering
    $posts_in_bounding_box = $car_query->posts;
    $precisely_filtered_posts = array();

    if (function_exists('autoagora_calculate_distance')) {
        if (!empty($posts_in_bounding_box)) {
            foreach ($posts_in_bounding_box as $post_object) {
                $car_latitude = get_field('car_latitude', $post_object->ID);
                $car_longitude = get_field('car_longitude', $post_object->ID);

                if ($car_latitude && $car_longitude) {
                    $distance = autoagora_calculate_distance($filter_lat, $filter_lng, $car_latitude, $car_longitude);
                    if ($distance <= $filter_radius) {
                        $precisely_filtered_posts[] = $post_object;
                    }
                }
            }
        }
    } else {
        // Fallback to bounding box results if distance function is not available
        $precisely_filtered_posts = $posts_in_bounding_box;
    }

    // Start the output
    ?>
    <div class="car-listings-container">
        <div class="car-listings-location-filter-bar">
            <span id="current-location-filter-text"><?php echo $is_default_cyprus_filter ? 'All of Cyprus' : 'Custom Location'; ?></span>
            <button id="change-location-filter-btn" class="button">Change Location</button>
        </div>

        <!-- Active Filters Bar -->
        <div class="active-filters-bar">
            <div class="active-filters-container">
                <!-- JS will populate this with active spec filters -->
            </div>
            <button class="filters-button" id="open-spec-filters-popup-btn">Filters</button>
        </div>

        <!-- Filters Popup -->
        <div class="filters-popup-overlay" id="spec-filters-popup" style="display:none;">
            <div class="filters-popup-content">
                <div class="filters-popup-header">
                    <h2>Filter Cars</h2>
                    <button class="close-filters" id="close-spec-filters-popup-btn">&times;</button>
                </div>
                <?php 
                // Display the new spec filter form
                if (function_exists('autoagora_display_spec_filters')) {
                    echo autoagora_display_spec_filters();
                }
                ?>
            </div>
        </div>

        <!-- Location Filter Modal -->
        <div id="location-filter-modal" class="location-picker-modal" style="display:none;">
            <div class="location-picker-content">
                <div class="location-picker-header">
                    
                    <button class="close-modal" id="close-location-filter-modal">&times;</button>
                </div>
                <div class="location-picker-body">
                    <div class="geocoder-apply-wrapper">
                        <div id="filter-geocoder" class="geocoder"></div>
                        <button id="apply-location-filter-btn" class="choose-location-btn">Apply Location</button>
                    </div>
                    <div id="filter-map-container" class="location-map">
                        <div class="radius-slider-container">
                            <label for="radius-slider">Radius: <span id="radius-value">10</span> km</label>
                            <input type="range" min="1" max="200" value="10" class="slider" id="radius-slider">
                            <div class="radius-presets">
                                <button class="radius-preset-btn" data-radius="1">+ 1 km</button>
                                <button class="radius-preset-btn" data-radius="2">+ 2 km</button>
                                <button class="radius-preset-btn" data-radius="3">+ 3 km</button>
                                <button class="radius-preset-btn" data-radius="5">+ 5 km</button>
                                <button class="radius-preset-btn" data-radius="10">+ 10 km</button>
                                <button class="radius-preset-btn" data-radius="30">+ 30 km</button>
                                <button class="radius-preset-btn" data-radius="100">+ 100 km</button>
                                <button class="radius-preset-btn" data-radius="200">+ 200 km</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Listings Grid -->
        <div class="car-listings-grid">
            <?php
            if (!empty($precisely_filtered_posts)) :
                global $post; // Required for setup_postdata
                foreach ($precisely_filtered_posts as $post_object) :
                    $post = $post_object; // Set the global $post
                    setup_postdata($post);
                    // Generate the detail page URL once
                    $car_detail_url = esc_url(get_permalink(get_the_ID()));

                    // Get car details
                    $make = get_field('make', get_the_ID());
                    $model = get_field('model', get_the_ID());
                    $variant = get_field('variant', get_the_ID());
                    $year = get_field('year', get_the_ID());
                    $price = get_field('price', get_the_ID());
                    $mileage = get_field('mileage', get_the_ID());
                    $car_city = get_field('car_city', get_the_ID());
                    $car_district = get_field('car_district', get_the_ID());
                    $display_location = '';
                    if (!empty($car_city) && !empty($car_district)) {
                        $display_location = $car_city . ' - ' . $car_district;
                    } elseif (!empty($car_city)) {
                        $display_location = $car_city;
                    } elseif (!empty($car_district)) {
                        $display_location = $car_district;
                    }
                    $engine_capacity = get_field('engine_capacity', get_the_ID());
                    $fuel_type = get_field('fuel_type', get_the_ID());
                    $transmission = get_field('transmission', get_the_ID());
                    $exterior_color = get_field('exterior_color', get_the_ID());
                    $interior_color = get_field('interior_color', get_the_ID());
                    $description = get_field('description', get_the_ID());
                    $body_type = get_field('body_type', get_the_ID());
                    $drive_type = get_field('drive_type', get_the_ID());
                    $number_of_doors = get_field('number_of_doors', get_the_ID());
                    $number_of_seats = get_field('number_of_seats', get_the_ID());
                    $motuntil = get_field('motuntil', get_the_ID());
                    $extras = get_field('extras', get_the_ID());
                    $vehiclehistory = get_field('vehiclehistory', get_the_ID());
                    $publication_date = get_field('publication_date', get_the_ID());
                    ?>
                    <div class="car-listing-card">
                        <?php 
                        // Get all car images
                        $featured_image = get_post_thumbnail_id(get_the_ID());
                        $additional_images = get_field('car_images', get_the_ID());
                        $all_images = array();
                        
                        if ($featured_image) {
                            $all_images[] = $featured_image;
                        }
                        
                        if (is_array($additional_images)) {
                            $all_images = array_merge($all_images, $additional_images);
                        }
                        
                        if (!empty($all_images)) {
                            echo '<div class="car-listing-image-container">';
                            echo '<div class="car-listing-image-carousel" data-post-id="' . get_the_ID() . '">';
                            
                            foreach ($all_images as $index => $image_id) {
                                $image_url = wp_get_attachment_image_url($image_id, 'medium');
                                if ($image_url) {
                                    $clean_year = str_replace(',', '', $year); // Remove comma from year
                                    echo '<div class="car-listing-image' . ($index === 0 ? ' active' : '') . '" data-index="' . $index . '">';
                                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($clean_year . ' ' . $make . ' ' . $model) . '">';
                                    if ($index === count($all_images) - 1 && count($all_images) > 1) {
                                        echo '<a href="' . $car_detail_url . '" class="see-all-images" style="display: none;">See All Images</a>';
                                    }
                                    echo '</div>';
                                }
                            }
                            
                            echo '<button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>';
                            echo '<button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>';
                            
                            $user_id = get_current_user_id();
                            $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
                            $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
                            $is_favorite = in_array(get_the_ID(), $favorite_cars);
                            $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
                            $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
                            echo '<button class="' . esc_attr($button_class) . '" data-car-id="' . get_the_ID() . '"><i class="' . esc_attr($heart_class) . '"></i></button>';
                            
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                        
                        <a href="<?php echo $car_detail_url; ?>" class="car-listing-link">
                            <div class="car-listing-details">
                                <h2 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h2>
                                <div class="car-specs">
                                    <?php 
                                    $specs_array = array();
                                    if (!empty($engine_capacity)) {
                                        $specs_array[] = esc_html($engine_capacity) . 'L';
                                    }
                                    if (!empty($fuel_type)) {
                                        $specs_array[] = esc_html($fuel_type);
                                    }
                                    if (!empty($body_type)) {
                                        $specs_array[] = esc_html($body_type);
                                    }
                                    if (!empty($transmission)) {
                                        $specs_array[] = esc_html($transmission);
                                    }
                                    
                                    echo implode(' | ', $specs_array);
                                    ?>
                                </div>
                                <div class="car-info-boxes">
                                <div class="info-box">
                                        <span class="info-value"><?php echo esc_html(str_replace(',', '', $year)); ?></span>
                                    </div>
                                    <div class="info-box">
                                        <span class="info-value"><?php echo number_format(floatval(str_replace(',', '', $mileage ?? '0'))); ?> km</span>
                                    </div>
                                </div>
                                <div class="car-price">€<?php echo number_format(floatval(str_replace(',', '', $price ?? '0'))); ?></div>
                                <div class="car-listing-additional-info">
                                    <?php 
                                    if (!$publication_date) {
                                        $publication_date = get_the_date('Y-m-d H:i:s');
                                        update_post_meta(get_the_ID(), 'publication_date', $publication_date);
                                    }
                                    $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                                    echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                                    ?>
                                    <p class="car-location"><i class="fas fa-map-marker-alt"></i> <span class="location-text"><?php echo esc_html($display_location); ?></span></p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php
                endforeach;
                wp_reset_postdata();
            else :
                echo '<p class="no-listings">No car listings found.</p>';
            endif;
            ?>
        </div>

        <!-- Pagination -->
        <div class="car-listings-pagination">
            <?php
            // Calculate pagination based on precisely filtered posts
            if (!empty($precisely_filtered_posts)) {
                $total_posts = count($precisely_filtered_posts);
                $posts_per_page = $atts['per_page'];
                $max_num_pages = ceil($total_posts / $posts_per_page);
                
                echo paginate_links(array(
                    'total' => $max_num_pages,
                    'current' => $paged,
                    'prev_text' => '&laquo; Previous',
                    'next_text' => 'Next &raquo;',
                    'format'  => '?paged=%#%',
                ));
            }
            ?>
        </div>
    </div>

    <?php
    // Return the buffered content
    return ob_get_clean();
}

// AJAX handler for filtering listings
add_action('wp_ajax_filter_listings_by_location', 'autoagora_filter_listings_by_location_ajax');
add_action('wp_ajax_nopriv_filter_listings_by_location', 'autoagora_filter_listings_by_location_ajax');

function autoagora_filter_listings_by_location_ajax() {
    // Diagnostic log: Check if WordPress core functions are available
    if (function_exists('get_stylesheet_directory_uri')) {
        error_log('[DEBUG] AJAX Handler: get_stylesheet_directory_uri() is available.');
    } else {
        error_log('[DEBUG] AJAX Handler: CRITICAL - get_stylesheet_directory_uri() IS NOT AVAILABLE.');
    }
    if (function_exists('get_field')) {
        error_log('[DEBUG] AJAX Handler: get_field() is available.');
    } else {
        error_log('[DEBUG] AJAX Handler: CRITICAL - get_field() IS NOT AVAILABLE.');
    }
    error_log('[DEBUG] AJAX Handler: autoagora_filter_listings_by_location_ajax reached for user ID: ' . get_current_user_id() . ', Role: ' . (is_user_logged_in() && !empty(wp_get_current_user()->roles) ? wp_get_current_user()->roles[0] : 'Guest/N/A'));

    try {
        // check_ajax_referer('filter_listings_by_location_nonce', 'nonce');
        $nonce_check = check_ajax_referer('filter_listings_by_location_nonce', 'nonce', false); // false = do not die on failure
        if (!$nonce_check) {
            error_log('[ERROR] AJAX Nonce check failed for User ID: ' . get_current_user_id() . ', Role: ' . (is_user_logged_in() && !empty(wp_get_current_user()->roles) ? wp_get_current_user()->roles[0] : 'Guest/N/A'));
            wp_send_json_error(array(
                'message' => 'Security check failed. Please refresh the page and try again.',
                'exception_type' => 'NonceVerificationError'
            ));
            return; // Exit the function early
        }

        $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 12;

        $filter_lat = isset($_POST['filter_lat']) && $_POST['filter_lat'] !== 'null' && $_POST['filter_lat'] !== '' ? floatval($_POST['filter_lat']) : null;
        $filter_lng = isset($_POST['filter_lng']) && $_POST['filter_lng'] !== 'null' && $_POST['filter_lng'] !== '' ? floatval($_POST['filter_lng']) : null;
        $filter_radius = isset($_POST['filter_radius']) && $_POST['filter_radius'] !== 'null' && $_POST['filter_radius'] !== '' ? floatval($_POST['filter_radius']) : null;

        // Use Cyprus defaults if no location filter is provided
        if ($filter_lat === null || $filter_lng === null || $filter_radius === null) {
            $filter_lat = CYPRUS_CENTER_LAT;
            $filter_lng = CYPRUS_CENTER_LNG;
            $filter_radius = CYPRUS_DEFAULT_RADIUS;
            error_log('[DEBUG] AJAX Handler: Using Cyprus default location filter (lat: ' . $filter_lat . ', lng: ' . $filter_lng . ', radius: ' . $filter_radius . 'km)');
        } else {
            error_log('[DEBUG] AJAX Handler: Using custom location filter (lat: ' . $filter_lat . ', lng: ' . $filter_lng . ', radius: ' . $filter_radius . 'km)');
        }

        $active_filters_for_query = array();
        $active_filters_for_counts = array();

        $potential_filters = ['make', 'model', 'variant', 'year_min', 'year_max', 'price_min', 'price_max', 'mileage_min', 'mileage_max', 'engine_capacity_min', 'engine_capacity_max', 'hp_min', 'hp_max', 'transmission', 'fuel_type', 'exterior_color', 'interior_color', 'body_type', 'drive_type', 'number_of_doors', 'number_of_seats', 'extras', 'availability', 'numowners_min', 'numowners_max', 'isantique', 'vehiclehistory'];

        foreach ($potential_filters as $filter_key) {
            if (isset($_POST[$filter_key]) && !empty($_POST[$filter_key])) {
                if (is_array($_POST[$filter_key])) {
                     $active_filters_for_query[$filter_key] = array_map('sanitize_text_field', $_POST[$filter_key]);
                     $active_filters_for_counts[$filter_key] = array_map('sanitize_text_field', $_POST[$filter_key]);
                } else {
                     $active_filters_for_query[$filter_key] = sanitize_text_field($_POST[$filter_key]);
                     $active_filters_for_counts[$filter_key] = sanitize_text_field($_POST[$filter_key]);
                }
            }
        }
        
        // Add location filters to both query and counts (always set now due to defaults)
        $active_filters_for_query['lat'] = $filter_lat;
        $active_filters_for_query['lng'] = $filter_lng;
        $active_filters_for_query['radius'] = $filter_radius;
        
        $active_filters_for_counts['lat'] = $filter_lat;
        $active_filters_for_counts['lng'] = $filter_lng;
        $active_filters_for_counts['radius'] = $filter_radius;

        error_log('[DEBUG] AJAX Handler - Filters for Query: ' . print_r($active_filters_for_query, true));

        $query_args = build_car_listings_query_args(
            array( 
            'per_page' => $per_page,
                'orderby' => 'date', // Or manage these via AJAX params if needed
                'order' => 'DESC'   // Or manage these via AJAX params if needed
            ),
            $paged, 
            $active_filters_for_query // Pass all filters including location
        );

        error_log('[DEBUG] AJAX Handler - Built Query Args for Listings: ' . print_r($query_args, true));

        $car_query = new WP_Query($query_args);

        error_log('[DEBUG] AJAX Handler - Listing Query SQL: ' . $car_query->request);
        error_log('[DEBUG] AJAX Handler - Listing Query Found Posts (Bounding Box): ' . $car_query->found_posts);

        $posts_in_bounding_box = $car_query->posts; // Get all posts found by the bounding box query
        $precisely_filtered_posts = array();

        if (function_exists('autoagora_calculate_distance')) {
            if (!empty($posts_in_bounding_box)) {
                foreach ($posts_in_bounding_box as $post_object) {
                    $car_latitude = get_field('car_latitude', $post_object->ID);
                    $car_longitude = get_field('car_longitude', $post_object->ID);

                    if ($car_latitude && $car_longitude) {
                        $distance = autoagora_calculate_distance($filter_lat, $filter_lng, $car_latitude, $car_longitude);
                        if ($distance <= $filter_radius) {
                            $precisely_filtered_posts[] = $post_object;
                        }
                    }
                }
                error_log('[DEBUG] AJAX Handler - Posts after precise circular filter: ' . count($precisely_filtered_posts));
            }
        } else {
            // Fallback to bounding box results if distance function is not available
            $precisely_filtered_posts = $posts_in_bounding_box;
            error_log('[DEBUG] AJAX Handler - Distance function not available, using bounding box results. Count: ' . count($precisely_filtered_posts));
        }

        ob_start();
        if (!empty($precisely_filtered_posts)) {
            error_log('[DEBUG] AJAX Handler - Precisely filtered query has posts. Entering loop...');
            global $post; // Required to make setup_postdata work correctly
            foreach ($precisely_filtered_posts as $post_object) {
                $post = $post_object; // Set the global $post object
                setup_postdata($post); // Setup post data for template tags and get_template_part
                
                get_template_part('template-parts/car-listing-card', null, array('post_id' => $post->ID));
            }
            wp_reset_postdata(); // Clean up global $post
        } else {
            error_log('[DEBUG] AJAX Handler - No posts after precise filtering (or initial query returned none).');
            echo '<p>No cars found matching your criteria.</p>';
        }
        $listings_html = ob_get_clean();

        // Calculate total pages based on precisely filtered posts
        $total_pages = 1; // Default
        if (!empty($precisely_filtered_posts)) {
            $per_page_for_pagination = isset($_POST['per_page']) ? intval($_POST['per_page']) : 12;
            if ($per_page_for_pagination > 0) {
                $total_pages = ceil(count($precisely_filtered_posts) / $per_page_for_pagination);
            }
        }

        $pagination_html = paginate_links(array(
            'total' => $total_pages,
            'current' => $paged,
            'prev_text' => __('&laquo; Previous'),
            'next_text' => __('Next &raquo;'),
            'type' => 'list' // Returns an HTML <ul>. Use 'plain' for just link tags.
        ));
        if (!$pagination_html) { // paginate_links returns false if only one page.
            $pagination_html = ''; // Ensure it's an empty string for the JSON response.
        }

        $filter_counts = array();
        $all_makes_list = array(); // Renamed for clarity
        $all_models_by_make = array();
        $all_variants_by_model = array();

        // Use a general flag like get_all_filter_options or rely on get_all_makes for now
        if (isset($_POST['get_all_makes']) && $_POST['get_all_makes'] === 'true') { 
            $all_cars_query_args = array(
                'post_type' => 'car',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => array(
                        'relation' => 'OR',
                        array('key' => 'is_sold', 'compare' => 'NOT EXISTS'),
                    array('key' => 'is_sold', 'value' => '1', 'compare' => '!=')
                )
            );
            $all_cars_query = new WP_Query($all_cars_query_args);
            $temp_makes = array();

            if ($all_cars_query->have_posts()) {
                foreach ($all_cars_query->posts as $car_id) {
                    $make_field = get_field('make', $car_id);
                    $model_field = get_field('model', $car_id);
                    $variant_field = get_field('variant', $car_id); // Variant is a direct string field

                    $make_name = is_object($make_field) ? $make_field->name : (is_string($make_field) ? trim($make_field) : '');
                    $model_name = is_object($model_field) ? $model_field->name : (is_string($model_field) ? trim($model_field) : '');
                    $variant_name = is_string($variant_field) ? trim($variant_field) : '';

                    if ($make_name) {
                        $temp_makes[$make_name] = true; // Collect unique make names

                        if ($model_name) {
                            if (!isset($all_models_by_make[$make_name])) {
                                $all_models_by_make[$make_name] = array();
                            }
                            $all_models_by_make[$make_name][$model_name] = true; // Collect unique model names for this make

                            if ($variant_name) {
                                if (!isset($all_variants_by_model[$make_name])) {
                                    $all_variants_by_model[$make_name] = array();
                                }
                                if (!isset($all_variants_by_model[$make_name][$model_name])) {
                                    $all_variants_by_model[$make_name][$model_name] = array();
                                }
                                $all_variants_by_model[$make_name][$model_name][$variant_name] = true; // Collect unique variant names
                            }
                        }
                    }
                }
            }
            $all_makes_list = array_keys($temp_makes);
            sort($all_makes_list);

            foreach ($all_models_by_make as $make => &$models) {
                $models = array_keys($models);
                sort($models);
            }
            ksort($all_models_by_make);

            foreach ($all_variants_by_model as $make => &$models_with_variants) {
                foreach ($models_with_variants as $model => &$variants) {
                    $variants = array_keys($variants);
                    sort($variants);
                }
                ksort($models_with_variants);
            }
            ksort($all_variants_by_model);
            
            // error_log('[DEBUG] AJAX - All Makes List Collected: ' . print_r($all_makes_list, true));
            // error_log('[DEBUG] AJAX - All Models by Make Collected: ' . print_r($all_models_by_make, true));
            // error_log('[DEBUG] AJAX - All Variants by Model Collected: ' . print_r($all_variants_by_model, true));
        }
        
        if (isset($_POST['get_filter_counts']) && $_POST['get_filter_counts'] === 'true') {
            $filter_counts = autoagora_get_dynamic_filter_counts($active_filters_for_counts);
            // error_log('[DEBUG] AJAX - Filter Counts (after dynamic calculation): ' . print_r($filter_counts, true));
        }

        $response_data = array(
            'listings_html' => $listings_html,
            'pagination_html' => $pagination_html,
            'query_vars' => $car_query->query_vars, // For result count or other info
            'filter_counts' => $filter_counts,
            'all_makes' => $all_makes_list, // This now sends a simple array of make names
            'all_models_by_make' => $all_models_by_make,
            'all_variants_by_model' => $all_variants_by_model
        );
        
        wp_send_json_success($response_data);
        // wp_die(); // wp_send_json_success already calls wp_die()

    } catch (Exception $e) {
        error_log('[ERROR] AJAX Handler Exception in autoagora_filter_listings_by_location_ajax: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' --- Trace: ' . $e->getTraceAsString());
        wp_send_json_error(array(
            'message' => 'Server error processing your request: ' . $e->getMessage(),
            'exception_type' => get_class($e)
            // Optionally, you could include $e->getFile() and $e->getLine() if WP_DEBUG is on,
            // but be cautious about exposing server paths in production.
        )); // This will also wp_die()
    }
}

function autoagora_get_dynamic_filter_counts($current_filters_from_ajax) {
    $counts = array();

    $fields_to_count = [
        'make', 'year', 'price', 'mileage', 
        'engine_capacity', 'hp', 'transmission', 'fuel_type', 
        'exterior_color', 'interior_color', 'body_type', 'drive_type', 
        'number_of_doors', 'number_of_seats', 'extras', 'availability', 
        'number_of_owners', 'is_antique', 'vehicle_history',
        'model_by_make', 'variant_by_model' // Added new complex types
    ];

    // error_log('[DEBUG] get_dynamic_filter_counts - Received Filters for Counting: ' . print_r($current_filters_from_ajax, true));

    foreach ($fields_to_count as $field_key_to_count) {
        $temp_filters = $current_filters_from_ajax;
        $field_values_counts = array(); // Initialize here for each field being counted

        if ($field_key_to_count === 'model_by_make') {
            unset($temp_filters['model']); 
            if (empty($current_filters_from_ajax['make'])) {
                $counts['model_by_make'] = array();
                // error_log("[DEBUG] get_dynamic_filter_counts - Skipping 'model_by_make' as no 'make' is in current_filters_from_ajax.");
                continue;
            }
            // Ensure 'make' is set for context, even if it's the primary filter for model_by_make
            // No, build_car_listings_query_args will take 'make' from $temp_filters if it exists.
            // $temp_filters['make'] = $current_filters_from_ajax['make']; 
        } elseif ($field_key_to_count === 'variant_by_model') {
            unset($temp_filters['variant']);
            if (empty($current_filters_from_ajax['make']) || empty($current_filters_from_ajax['model'])) {
                $counts['variant_by_model'] = array();
                // error_log("[DEBUG] get_dynamic_filter_counts - Skipping 'variant_by_model' as no 'make' or 'model' is in current_filters_from_ajax.");
                continue;
            }
            // Ensure 'make' and 'model' are set for context in $temp_filters
            // $temp_filters['make'] = $current_filters_from_ajax['make'];
            // $temp_filters['model'] = $current_filters_from_ajax['model'];
        } else {
            unset($temp_filters[$field_key_to_count]);
            if (in_array($field_key_to_count, ['year', 'price', 'mileage', 'engine_capacity', 'hp', 'number_of_owners'])) {
                unset($temp_filters[$field_key_to_count . '_min']);
                unset($temp_filters[$field_key_to_count . '_max']);
            }
        }
        
        // Use build_car_listings_query_args to get arguments that respect ALL $temp_filters (including location)
        // We only need IDs, so 'posts_per_page' can be -1 and 'fields' => 'ids'.
        // $atts for build_car_listings_query_args needs 'per_page', 'orderby', 'order'.
        // For counting, orderby and order don't strictly matter but provide sensible defaults.
        $counting_query_atts = array(
            'per_page' => -1, // Get all matching posts
            'orderby' => 'ID', // Order doesn't matter for counts
            'order' => 'ASC'
        );
        // Pass $temp_filters directly. build_car_listings_query_args will use these.
        // Location filters (lat, lng, radius) if present in $temp_filters will be handled by build_car_listings_query_args.
        $query_args_for_count = build_car_listings_query_args($counting_query_atts, 1, $temp_filters);
        
        // Ensure we are only fetching IDs
        $query_args_for_count['fields'] = 'ids';
        // Ensure is_sold check is present if not already handled by build_car_listings_query_args in a conflicting way
        // build_car_listings_query_args already adds the is_sold check with AND relation to other meta queries.
        // If $query_args_for_count['meta_query'] is not set, or if 'relation' is not AND, we might need to adjust.
        // However, build_car_listings_query_args structure seems robust enough.
        
        $query_instance_for_counting = new WP_Query();
        $filtered_ids = $query_instance_for_counting->query( $query_args_for_count ); // $query_instance_for_counting->posts will contain the IDs

        // The $filtered_ids are now already filtered by location if location params were in $temp_filters,
        // because build_car_listings_query_args handles it.
        // So, the call to filter_posts_by_distance is no longer needed here.
        /*
        if ($location_filter_for_sub_query) { // This whole block should be removed
            $filtered_ids = filter_posts_by_distance(
                $filtered_ids,
                $location_filter_for_sub_query['lat'],
                $location_filter_for_sub_query['lng'],
                $location_filter_for_sub_query['radius']
            );
        }
        */
        
        if (empty($filtered_ids)) {
            // Ensure structure exists even if empty
            if ($field_key_to_count === 'model_by_make') {
                 $selected_make = $current_filters_from_ajax['make'] ?? null;
                 if ($selected_make) $counts['model_by_make'][$selected_make] = array();
                 else $counts['model_by_make'] = array();
            } elseif ($field_key_to_count === 'variant_by_model') {
                $selected_make = $current_filters_from_ajax['make'] ?? null;
                $selected_model = $current_filters_from_ajax['model'] ?? null;
                if ($selected_make && $selected_model) {
                    if(!isset($counts['variant_by_model'][$selected_make])) $counts['variant_by_model'][$selected_make] = array();
                    $counts['variant_by_model'][$selected_make][$selected_model] = array();
                } else { $counts['variant_by_model'] = array();}
            } else {
                $counts[$field_key_to_count] = array();
            }
            continue; 
        }

        if ($field_key_to_count === 'model_by_make') {
            $active_make_for_model_count = $current_filters_from_ajax['make']; // Already checked it's set
            $models_for_this_make_counts = array();
            foreach ($filtered_ids as $post_id) {
                $car_make_obj = get_field('make', $post_id);
                $car_make_name = is_object($car_make_obj) ? $car_make_obj->name : (is_string($car_make_obj) ? trim($car_make_obj) : '');
                
                if ($car_make_name === $active_make_for_model_count) { // Count only models of the active make
                    $model_val_obj = get_field('model', $post_id);
                    $model_name = is_object($model_val_obj) ? $model_val_obj->name : (is_string($model_val_obj) ? trim($model_val_obj) : '');
                    if ($model_name) {
                        $models_for_this_make_counts[$model_name] = ($models_for_this_make_counts[$model_name] ?? 0) + 1;
                    }
                }
            }
            if (!isset($counts['model_by_make'])) $counts['model_by_make'] = array();
            $counts['model_by_make'][$active_make_for_model_count] = $models_for_this_make_counts;
            if (!empty($models_for_this_make_counts)) ksort($counts['model_by_make'][$active_make_for_model_count]);

        } elseif ($field_key_to_count === 'variant_by_model') {
            $active_make_for_variant_count = $current_filters_from_ajax['make']; // Checked
            $active_model_for_variant_count = $current_filters_from_ajax['model']; // Checked
            $variants_for_this_model_counts = array();

            foreach ($filtered_ids as $post_id) {
                $car_make_obj = get_field('make', $post_id);
                $car_make_name = is_object($car_make_obj) ? $car_make_obj->name : (is_string($car_make_obj) ? trim($car_make_obj) : '');
                
                $car_model_obj = get_field('model', $post_id);
                $car_model_name = is_object($car_model_obj) ? $car_model_obj->name : (is_string($car_model_obj) ? trim($car_model_obj) : '');

                if ($car_make_name === $active_make_for_variant_count && $car_model_name === $active_model_for_variant_count) {
                    $variant_val = get_field('variant', $post_id); // Variant is a string
                    if (is_string($variant_val) && !empty(trim($variant_val))) {
                        $variant_name_trimmed = trim($variant_val);
                        $variants_for_this_model_counts[$variant_name_trimmed] = ($variants_for_this_model_counts[$variant_name_trimmed] ?? 0) + 1;
                    }
                }
            }
            if(!isset($counts['variant_by_model'])) $counts['variant_by_model'] = array();
            if(!isset($counts['variant_by_model'][$active_make_for_variant_count])) $counts['variant_by_model'][$active_make_for_variant_count] = array();
            $counts['variant_by_model'][$active_make_for_variant_count][$active_model_for_variant_count] = $variants_for_this_model_counts;
            if (!empty($variants_for_this_model_counts)) ksort($counts['variant_by_model'][$active_make_for_variant_count][$active_model_for_variant_count]);

        } else { // For simple fields (make, year, price, etc.)
            foreach ($filtered_ids as $post_id) {
                $field_value_obj = get_field($field_key_to_count, $post_id);
                if (is_object($field_value_obj) && isset($field_value_obj->name)) {
                    $value_name = trim($field_value_obj->name);
                    if ($value_name !== '') $field_values_counts[$value_name] = ($field_values_counts[$value_name] ?? 0) + 1;
                } elseif (is_array($field_value_obj)) { 
                    foreach ($field_value_obj as $single_value) {
                         $s_val = is_object($single_value) && isset($single_value->name) ? $single_value->name : (is_string($single_value) ? trim($single_value) : null);
                         if ($s_val && $s_val !== '') {
                            $field_values_counts[$s_val] = ($field_values_counts[$s_val] ?? 0) + 1;
                         }
                    }
                } elseif (is_string($field_value_obj) || is_numeric($field_value_obj)) {
                    $value_name = trim((string)$field_value_obj);
                    if ($value_name !== '') {
                        $field_values_counts[$value_name] = ($field_values_counts[$value_name] ?? 0) + 1;
                    }
                }
            }
            if (!empty($field_values_counts)) {
                ksort($field_values_counts);
            }
            $counts[$field_key_to_count] = $field_values_counts;
        }
    }
    // error_log("[DEBUG] get_dynamic_filter_counts - Final dynamic counts: " . print_r($counts, true));
    return $counts;
}