<?php
/**
 * Bricks Child Theme - Combined functions and definitions
 *
 * This file combines the original Bricks child theme functions
 * with functionality from a previous theme (Astra Child).
 * Comments have been added to denote the origin of each code block.
 */

// Define theme version constant
define('BRICKS_CHILD_THEME_VERSION', '1.0.0');

// =========================================================================
// Custom Functionality Includes
// FROM: Astra Child (Second File)
// =========================================================================
// IMPORTANT: Ensure these files and the '/vendor' directory exist in your Bricks child theme folder.
require_once get_stylesheet_directory() . '/vendor/autoload.php';
require_once get_stylesheet_directory() . '/includes/core/mapbox-assets.php';
require_once get_stylesheet_directory() . '/includes/car-listings/car-listings.php';
require_once get_stylesheet_directory() . '/includes/user-manage-listings/car-submission.php';
require_once get_stylesheet_directory() . '/includes/car-listing-detailed.php';
require_once get_stylesheet_directory() . '/includes/user-account/my-account/my-account.php';
require_once get_stylesheet_directory() . '/includes/user-account/my-listings/my-listings.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/favourite-listings.php';
require_once get_stylesheet_directory() . '/includes/core/enqueue.php';
require_once get_stylesheet_directory() . '/includes/core/image-optimization.php';
require_once get_stylesheet_directory() . '/includes/core/async-uploads.php';
require_once get_stylesheet_directory() . '/includes/auth/registration.php';
require_once get_stylesheet_directory() . '/includes/auth/roles.php';
require_once get_stylesheet_directory() . '/includes/auth/forgot-password-ajax.php';
require_once get_stylesheet_directory() . '/includes/user-account/user-profile.php';
require_once get_stylesheet_directory() . '/includes/auth/access-control.php';
require_once get_stylesheet_directory() . '/includes/auth/login-logout.php';
require_once get_stylesheet_directory() . '/includes/core/ajax.php';
require_once get_stylesheet_directory() . '/includes/email/sendgrid-config.php';
require_once get_stylesheet_directory() . '/includes/email/test-sendgrid.php';
require_once get_stylesheet_directory() . '/includes/email/email-verification-init.php';
require_once get_stylesheet_directory() . '/includes/email/email-verification.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/account-display.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/favourites-button.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/car-search-form.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/single-car-template-gallery.php';

require_once get_stylesheet_directory() . '/includes/admin/user-favorites-column.php';

// NEWLY ADDED FROM ASTRA CHILD (SECOND FILE)
require_once get_stylesheet_directory() . '/includes/notifications/email-verification-notification.php';
require_once get_stylesheet_directory() . '/includes/legal/legal-pages.php';
require_once get_stylesheet_directory() . '/includes/legal/cookie-consent.php';

// Car Views Counter System
require_once get_stylesheet_directory() . '/includes/views-counter/views-database.php';
require_once get_stylesheet_directory() . '/includes/views-counter/views-tracker.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/car-views-counter/car-views-counter.php';




/**
 * Register and enqueue custom scripts and styles.
 * NOTE: This function combines all script-related actions from both files.
 */
add_action( 'wp_enqueue_scripts', function() {
    // FROM: Bricks (Main File)
    wp_enqueue_style( 'bricks-child', get_stylesheet_uri(), ['bricks-frontend'], filemtime( get_stylesheet_directory() . '/style.css' ) );

    // FROM: Astra Child (Second File)
    // Ensure a script with the handle 'location-picker' is enqueued by one of your included files.
    $theme_url = get_stylesheet_directory_uri();
    wp_localize_script('location-picker', 'locationPickerData', array(
        'citiesJsonUrl' => $theme_url . '/simple_jsons/cities.json'
    ));

    // FROM: Astra Child (Second File)
    // Enqueue carousel CSS/JS on the '/used-cars-facetwp' page
    if ( is_page( 'used-cars-facetwp' ) || strpos( $_SERVER['REQUEST_URI'], '/used-cars-facetwp' ) !== false ) {
        $theme_dir = get_stylesheet_directory_uri();
        wp_enqueue_style( 'car-listings-style', $theme_dir . '/includes/car-listings/car-listings.css', array(), filemtime( get_stylesheet_directory() . '/includes/car-listings/car-listings.css' ) );
        wp_enqueue_script( 'car-listings-js', $theme_dir . '/includes/car-listings/car-listings.js', array( 'jquery' ), filemtime( get_stylesheet_directory() . '/includes/car-listings/car-listings.js' ), true );
    }

    // Enqueue single car gallery styles and scripts
    if ( is_singular('car') ) {
        $theme_dir = get_stylesheet_directory_uri();
        
        // Enqueue gallery styles and scripts (no slider dependencies needed)
        wp_enqueue_style( 'single-car-main-gallery', $theme_dir . '/includes/single-car/single-car-main-gallery.css', array(), filemtime( get_stylesheet_directory() . '/includes/single-car/single-car-main-gallery.css' ) );
        wp_enqueue_script( 'single-car-main-gallery', $theme_dir . '/includes/single-car/single-car-main-gallery.js', array( 'jquery' ), filemtime( get_stylesheet_directory() . '/includes/single-car/single-car-main-gallery.js' ), true );
    }
} );

/**
 * Register custom Bricks elements.
 * FROM: Bricks (Main File)
 */
add_action( 'init', function() {
    $element_files = [
        __DIR__ . '/elements/title.php',
    ];

    foreach ( $element_files as $file ) {
        \Bricks\Elements::register_element( $file );
    }
}, 11 );


// =========================================================================
// Custom Functions & Theme-Independent Hooks
// =========================================================================

/**
 * Get SVG icon from assets directory.
 * FROM: Astra Child (Second File)
 *
 * @param string $icon_name The name of the icon file (without .svg extension).
 * @return string The SVG file content or an empty string if not found.
 */
function get_svg_icon( $icon_name ) {
    $svg_path = get_stylesheet_directory() . '/assets/svg/regular/' . $icon_name . '.svg';
    if ( file_exists( $svg_path ) ) {
        return file_get_contents( $svg_path );
    }
    return '';
}

/**
 * Bricks: Get Permalink of Main Post from data-main-post-id attribute.
 * FROM: Bricks (Main File)
 *
 * @return string The permalink of the main post, or '#' if not found.
 */
function get_main_post_permalink_from_data_attribute() {
    if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
        global $bricks_element;
        $element = $bricks_element;
        while ( $element ) {
            if ( isset( $element->attributes['data-main-post-id'] ) && ! empty( $element->attributes['data-main-post-id'] ) ) {
                $main_post_id = $element->attributes['data-main-post-id'];
                return get_permalink( (int) $main_post_id );
            }
            $element = $element->parent;
        }
    } else {
        return get_permalink( get_the_ID() );
    }
    return '#'; // Fallback
}


/**
 * Function to display favourites button via shortcode.
 * FROM: Astra Child (Second File)
 *
 * The original action `add_action('astra_header_right', ...)` was removed as it is specific to the Astra theme.
 * See notes below on how to add this to your Bricks header.
 */
function add_favourites_button_to_header() {
    echo do_shortcode('[favourites_button]');
}
// ACTION REQUIRED: The line below (from Astra Child) was commented out because the 'astra_header_right' hook does not exist in Bricks.
// add_action('astra_header_right', 'add_favourites_button_to_header', 5);


/**
 * Enable shortcodes in FacetWP Listing Builder fields.
 * FROM: Astra Child (Second File)
 */
add_filter( 'facetwp_builder_inner_html', 'do_shortcode' );


// NEWLY ADDED FROM ASTRA CHILD (SECOND FILE)
// Register report listing AJAX hooks (lightweight)
add_action('wp_ajax_submit_listing_report', 'handle_listing_report_submission');
add_action('wp_ajax_nopriv_submit_listing_report', 'handle_listing_report_submission');

// Load the actual handler function only when AJAX is called
function handle_listing_report_submission() {
    // Load the report handler file only when this AJAX call is made
    require_once get_stylesheet_directory() . '/includes/shortcodes/single-car-page/report-handler.php';
    
    // Call the actual handler function
    process_listing_report_submission();
}

// NEWLY ADDED FROM ASTRA CHILD (SECOND FILE)
/**
 * Prevent post_author from changing when admins edit car listings
 * This ensures that the original car listing owner is preserved when admins make edits
 */
function preserve_car_listing_author_on_admin_edit($data, $postarr) {
    // Only apply to car post type updates (not new posts)
    if (isset($data['post_type']) && $data['post_type'] === 'car' && isset($postarr['ID']) && $postarr['ID'] > 0) {
        
        // Get the current post to preserve its author
        $current_post = get_post($postarr['ID']);
        if ($current_post && $current_post->post_author) {
            // Always preserve the original author
            $data['post_author'] = $current_post->post_author;
        }
    }
    
    return $data;
}
add_filter('wp_insert_post_data', 'preserve_car_listing_author_on_admin_edit', 10, 2);

// NEWLY ADDED FROM ASTRA CHILD (SECOND FILE)
/**
 * Delete associated images when a car listing is deleted or trashed
 * This prevents orphaned images from cluttering the Media Library
 */
function delete_car_listing_images($post_id, $post = null) {
    // Get post object if not provided (for wp_trash_post hook)
    if (!$post) {
        $post = get_post($post_id);
    }
    
    // Check if this is a car post type
    if (!$post || $post->post_type !== 'car') {
        return;
    }
    
    // Get all images associated with this car listing
    $car_images = get_field('car_images', $post_id);
    
    if (!empty($car_images) && is_array($car_images)) {
        foreach ($car_images as $image_id) {
            // Delete the attachment and its files
            wp_delete_attachment($image_id, true);
        }
        
        // Log the deletion for debugging (if WP_DEBUG is enabled)
        if (WP_DEBUG === true) {
            error_log('AutoAgora: Deleted ' . count($car_images) . ' images associated with car listing ID: ' . $post_id);
        }
    }
    
    // Also check for any featured image (for backward compatibility)
    $featured_image_id = get_post_thumbnail_id($post_id);
    if ($featured_image_id) {
        wp_delete_attachment($featured_image_id, true);
        
        if (WP_DEBUG === true) {
            error_log('AutoAgora: Deleted featured image for car listing ID: ' . $post_id);
        }
    }
}
// Hook for permanent deletion (when trash is disabled or forced delete)
add_action('before_delete_post', 'delete_car_listing_images', 10, 2);
// Hook for when post is moved to trash (most common scenario)
add_action('wp_trash_post', 'delete_car_listing_images', 10, 1);

// Include shared button components
require_once get_stylesheet_directory() . '/includes/shortcodes/favorite-button/favorite-button.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/share-button/share-button.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/report-button/report-button.php';


// =========================================================================
// Bricks Builder Specific Filters
// =========================================================================

/**
 * Add text strings to builder.
 * FROM: Bricks (Main File)
 */
add_filter( 'bricks/builder/i18n', function( $i18n ) {
    $i18n['custom'] = esc_html__( 'Custom', 'bricks' );
    return $i18n;
} );

/**
 * Customize builder save messages.
 * FROM: Bricks (Main File)
 */
add_filter( 'bricks/builder/save_messages', function( $messages ) {
    $messages = [
        'Oreos r',
        'Bravo r',
        'Insane r',
    ];
    return $messages;
} );

/**
 * Whitelist functions for use with Bricks dynamic data {echo:}.
 * FROM: Bricks (Main File)
 */
add_filter( 'bricks/code/echo_function_names', function( $functions ) {
    $my_allowed_functions = [
        'number_format',
        'get_permalink',
        'get_main_post_permalink_from_data_attribute'
    ];
    
    // Check if $functions is an array, if not, initialize it.
    if ( ! is_array( $functions ) ) {
        $functions = [];
    }
    
    return array_merge( $functions, $my_allowed_functions );
} );


// =========================================================================
// Commented Out / Optional Filters
// FROM: Bricks (Main File)
// =========================================================================

/**
 * Filter which elements to show in the builder
 */
function bricks_filter_builder_elements( $elements ) {
    // List of elements to keep...
    return $elements;
}
// add_filter( 'bricks/builder/elements', 'bricks_filter_builder_elements' );

/**
 * Customize standard fonts
 */
// add_filter( 'bricks/builder/standard_fonts', function( $standard_fonts ) {
//   return $standard_fonts;
// } );

/**
 * Add custom map style
 */
// add_filter( 'bricks/builder/map_styles', function( $map_styles ) {
//   return $map_styles;
// } );


// =========================================================================
// TEMPORARY BULK CAR LISTING CREATOR - REMOVE AFTER USE
// =========================================================================

// Upload images functionality
add_action('init', function() {
    if (isset($_GET['bulk_upload_images']) && current_user_can('manage_options')) {
        bulk_upload_car_images();
        exit;
    }
    
    if (isset($_GET['bulk_create_listings']) && current_user_can('manage_options')) {
        bulk_create_car_listings();
        exit;
    }
});

function bulk_upload_car_images() {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    echo "<h1>🚗 Car Images Bulk Upload</h1>";
    
    // Path to images
    $images_folder = ABSPATH . 'wp-content/uploads/temp-car-images/';
    
    // Check if folder exists
    if (!is_dir($images_folder)) {
        die("<p style='color:red;'>❌ Folder not found: $images_folder</p><p>Please create the folder and add your 100 car images.</p>");
    }
    
    // Get image files
    $image_files = glob($images_folder . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    
    if (empty($image_files)) {
        die("<p style='color:red;'>❌ No images found in: $images_folder</p>");
    }
    
    echo "<p>📸 Found " . count($image_files) . " images to upload...</p>";
    echo "<div style='background:#f0f0f0; padding:10px; margin:10px 0; font-family:monospace;'>";
    
    $attachment_ids = [];
    
    foreach ($image_files as $index => $image_path) {
        $filename = basename($image_path);
        
        // Create file array for WordPress
        $file_array = [
            'name' => 'stock-car-' . ($index + 1) . '.jpg',
            'tmp_name' => $image_path,
            'type' => mime_content_type($image_path),
            'size' => filesize($image_path),
            'error' => 0
        ];
        
        // Upload to WordPress
        $attachment_id = media_handle_sideload($file_array, 0, 'Stock car image ' . ($index + 1));
        
        if (is_wp_error($attachment_id)) {
            echo "<span style='color:red;'>❌ Failed: $filename - " . $attachment_id->get_error_message() . "</span><br>";
            continue;
        }
        
        $attachment_ids[] = $attachment_id;
        echo "<span style='color:green;'>✅ Uploaded: $filename (ID: $attachment_id)</span><br>";
        
        // Flush output for real-time progress
        flush();
        ob_flush();
        
        // Small delay
        usleep(100000);
    }
    
    echo "</div>";
    
    if (!empty($attachment_ids)) {
        echo "<h2>🎉 Upload Complete!</h2>";
        echo "<p><strong>Total uploaded:</strong> " . count($attachment_ids) . " images</p>";
        
        // Save IDs to WordPress option
        update_option('bulk_car_image_ids', $attachment_ids);
        
        echo "<p>💾 Image IDs saved to database</p>";
        echo "<p><strong>🚀 Next step:</strong> Visit <a href='" . home_url('?bulk_create_listings=1') . "'>?bulk_create_listings=1</a> to create your car listings!</p>";
        
        echo "<details><summary>📋 Image IDs (for reference)</summary>";
        echo "<textarea style='width:100%;height:100px;'>" . implode(',', $attachment_ids) . "</textarea>";
        echo "</details>";
    } else {
        echo "<p style='color:red;'>❌ No images were uploaded successfully.</p>";
    }
}

function bulk_create_car_listings() {
    echo "<h1>🚗 Bulk Create Car Listings</h1>";
    
    // Load stock car image IDs
    $stock_car_image_ids = get_option('bulk_car_image_ids', []);
    
    if (empty($stock_car_image_ids)) {
        die("<p style='color:red;'>❌ No stock images found. Please run ?bulk_upload_images=1 first.</p>");
    }
    
    echo "<p>📸 Found " . count($stock_car_image_ids) . " stock images</p>";
    
    // Load car makes data from JSON files
    $makes_data = [];
    $jsons_dir = get_stylesheet_directory() . '/simple_jsons/';
    
    if (is_dir($jsons_dir)) {
        $json_files = glob($jsons_dir . '*.json');
        foreach ($json_files as $file) {
            $content = file_get_contents($file);
            if ($content) {
                $data = json_decode($content, true);
                if ($data && json_last_error() === JSON_ERROR_NONE) {
                    $make_name = array_key_first($data);
                    if ($make_name) {
                        $makes_data[$make_name] = $data[$make_name];
                    }
                }
            }
        }
    }
    
    if (empty($makes_data)) {
        die("<p style='color:red;'>❌ No car makes data found in simple_jsons folder.</p>");
    }
    
    echo "<p>🚗 Found " . count($makes_data) . " car makes</p>";
    
    // Realistic data arrays (matching form options exactly)
    $fuel_types = ['Petrol', 'Diesel', 'Electric', 'Petrol hybrid', 'Diesel hybrid', 'Plug-in petrol', 'Plug-in diesel', 'Bi Fuel', 'Hydrogen', 'Natural Gas'];
    $transmissions = ['Manual', 'Automatic'];
    $body_types = ['Hatchback', 'Saloon', 'Coupe', 'Convertible', 'Estate', 'SUV', 'MPV', 'Pickup', 'Camper', 'Minibus'];
    $drive_types = ['Front-Wheel Drive', 'Rear-Wheel Drive', 'All-Wheel Drive', '4-Wheel Drive'];
    $colors = ['Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Yellow', 'Brown', 'Beige', 'Orange', 'Purple', 'Gold', 'Bronze'];
    $interior_colors = ['Black', 'Gray', 'Beige', 'Brown', 'White', 'Red', 'Blue', 'Tan', 'Cream'];
    $cities = ['Nicosia', 'Limassol', 'Larnaca', 'Paphos', 'Famagusta', 'Kyrenia'];
    $districts = ['Nicosia District', 'Limassol District', 'Larnaca District', 'Paphos District', 'Famagusta District', 'Kyrenia District'];
    
    // Start bulk creation with chunking for large batches
    $created_count = 0;
    $target_count = isset($_GET['count']) ? intval($_GET['count']) : 10;
    $chunk_size = 50; // Process in chunks of 50 to avoid timeouts
    $current_chunk = isset($_GET['chunk']) ? intval($_GET['chunk']) : 1;
    
    // Calculate start and end for this chunk
    $start_index = ($current_chunk - 1) * $chunk_size + 1;
    $end_index = min($current_chunk * $chunk_size, $target_count);
    $total_chunks = ceil($target_count / $chunk_size);
    
    echo "<p><strong>🚀 Processing chunk $current_chunk of $total_chunks (listings $start_index to $end_index)...</strong></p>";
    echo "<div style='background:#f0f0f0; padding:10px; margin:10px 0; font-family:monospace; height:300px; overflow-y:scroll;'>";
    
    // Increase memory and time limits
    ini_set('memory_limit', '512M');
    set_time_limit(300); // 5 minutes per chunk
    
    echo "<span style='color:blue;'>🔧 Starting processing loop...</span><br>";
    flush();
    ob_flush();
    
    for ($i = $start_index; $i <= $end_index; $i++) {
        echo "<span style='color:blue;'>🔄 Processing listing $i...</span><br>";
        flush();
        ob_flush();
        
        // Select random make and model with error handling
        try {
            $make_names = array_keys($makes_data);
            $random_make = $make_names[array_rand($make_names)];
            
            if (!isset($makes_data[$random_make]) || empty($makes_data[$random_make])) {
                echo "<span style='color:orange;'>⚠️ No models for $random_make, skipping...</span><br>";
                continue;
            }
            
            $models = array_keys($makes_data[$random_make]);
            $random_model = $models[array_rand($models)];
            
            if (!isset($makes_data[$random_make][$random_model]) || empty($makes_data[$random_make][$random_model])) {
                echo "<span style='color:orange;'>⚠️ No variants for $random_make $random_model, skipping...</span><br>";
                continue;
            }
            
            $variants = $makes_data[$random_make][$random_model];
            $random_variant = $variants[array_rand($variants)];
        } catch (Exception $e) {
            echo "<span style='color:red;'>❌ Error selecting car data: " . $e->getMessage() . "</span><br>";
            continue;
        }
        
        // Generate realistic specs
        $year = rand(2010, 2024);
        $mileage = rand(5000, 300000);
        $price = rand(5000, 150000);
        $engine_capacity = rand(10, 60) / 10;
        $hp = rand(100, 500);
        $doors = rand(3, 5);
        $seats = rand(2, 8);
        $num_owners = rand(1, 4);
        
        // Random selections
        $fuel_type = $fuel_types[array_rand($fuel_types)];
        $transmission = $transmissions[array_rand($transmissions)];
        $body_type = $body_types[array_rand($body_types)];
        $drive_type = $drive_types[array_rand($drive_types)];
        $exterior_color = $colors[array_rand($colors)];
        $interior_color = $interior_colors[array_rand($interior_colors)];
        $city = $cities[array_rand($cities)];
        $district = $districts[array_rand($districts)];
        
        // Create post title and description
        $post_title = "$year $random_make $random_model $random_variant";
        $description = "Beautiful $year $random_make $random_model $random_variant in excellent condition. " .
                      "This $exterior_color $body_type features a $engine_capacity" . "L $fuel_type engine with $transmission transmission. " .
                      "Well maintained with $mileage km on the odometer. Perfect for daily driving.";
        
        // Create the WordPress post with proper user assignment
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            // Get the first admin user as fallback
            $admin_users = get_users(['role' => 'administrator', 'number' => 1]);
            $current_user_id = !empty($admin_users) ? $admin_users[0]->ID : 1;
        }
        
        $post_data = [
            'post_title' => $post_title,
            'post_content' => '',
            'post_status' => 'publish', // Published immediately like you want
            'post_type' => 'car',
            'post_author' => $current_user_id,
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            echo "<span style='color:red;'>❌ Failed listing $i: " . $post_id->get_error_message() . "</span><br>";
            continue;
        }
        
        // Generate additional realistic data
        $availability = rand(0, 1) ? 'In Stock' : 'In Transit';
        
        // MOT Status - random future date or expired
        $mot_options = ['Expired'];
        $current_date = new DateTime();
        for ($j = 0; $j < 24; $j++) { // Next 24 months
            $future_date = new DateTime();
            $future_date->modify("+$j months");
            $mot_options[] = $future_date->format('Y-m');
        }
        $mot_status = $mot_options[array_rand($mot_options)];
        
        // Vehicle History - select 2-5 random positive history items
        $vehicle_history_options = [
            'no_accidents', 'regular_maintenance', 'no_modifications', 'clear_title', 'no_known_issues'
        ];
        $num_history = rand(2, 5);
        $selected_history = array_rand(array_flip($vehicle_history_options), $num_history);
        if (!is_array($selected_history)) $selected_history = [$selected_history];
        
        // Extras - select 3-8 random extras
        $extras_options = [
            'alloy_wheels', 'cruise_control', 'rear_view_camera', 'heated_seats', 
            'android_auto', 'apple_carplay', 'leather_seats', 'parking_sensors',
            'sunroof', 'keyless_start', 'start_stop'
        ];
        $num_extras = rand(3, 8);
        $selected_extras = array_rand(array_flip($extras_options), $num_extras);
        if (!is_array($selected_extras)) $selected_extras = [$selected_extras];
        
        // Ensure ACF is loaded before saving fields
        if (!function_exists('update_field')) {
            // Force load ACF API
            if (function_exists('acf_include')) {
                acf_include('includes/api/api-helpers.php');
            }
            // Alternative: Load admin functions to ensure ACF works
            if (!function_exists('update_field') && defined('ACF_PATH')) {
                require_once(ACF_PATH . 'includes/api/api-helpers.php');
            }
        }
        
        // Add all the ACF fields (like manual submissions)
        update_field('make', $random_make, $post_id);
        update_field('model', $random_model, $post_id);
        update_field('variant', $random_variant, $post_id);
        update_field('year', $year, $post_id);
        update_field('mileage', $mileage, $post_id);
        update_field('price', $price, $post_id);
        update_field('engine_capacity', $engine_capacity, $post_id);
        update_field('fuel_type', $fuel_type, $post_id);
        update_field('transmission', $transmission, $post_id);
        update_field('body_type', $body_type, $post_id);
        update_field('drive_type', $drive_type, $post_id);
        update_field('exterior_color', $exterior_color, $post_id);
        update_field('interior_color', $interior_color, $post_id);
        update_field('description', $description, $post_id);
        update_field('number_of_doors', $doors, $post_id);
        update_field('number_of_seats', $seats, $post_id);
        update_field('car_city', $city, $post_id);
        update_field('car_district', $district, $post_id);
        update_field('hp', $hp, $post_id);
        update_field('numowners', $num_owners, $post_id);
        update_field('isantique', ($year < 1990) ? 1 : 0, $post_id);
        update_field('availability', $availability, $post_id);
        update_field('motuntil', $mot_status, $post_id);
        update_field('vehiclehistory', $selected_history, $post_id);
        update_field('extras', $selected_extras, $post_id);
        
        // Assign random 5-7 images from stock - properly structured
        $selected_images = [];
        if (!empty($stock_car_image_ids)) {
            $num_images = rand(5, 7);
            $available_images = count($stock_car_image_ids);
            
            // Don't try to select more images than available
            $num_images = min($num_images, $available_images);
            
            if ($num_images > 1) {
                $random_image_keys = array_rand($stock_car_image_ids, $num_images);
                if (is_array($random_image_keys)) {
                    foreach ($random_image_keys as $key) {
                        $selected_images[] = $stock_car_image_ids[$key];
                    }
                } else {
                    $selected_images[] = $stock_car_image_ids[$random_image_keys];
                }
            } else {
                // If only one image available
                $selected_images[] = $stock_car_image_ids[0];
            }
            
            // Set first image as featured image (like manual submissions)
            if (!empty($selected_images)) {
                set_post_thumbnail($post_id, $selected_images[0]);
            }
            
            // Update car_images field with ALL images (like manual submissions)
            update_field('car_images', $selected_images, $post_id);
            
            echo "<span style='color:blue;'>📸 Assigned " . count($selected_images) . " images</span><br>";
        } else {
            echo "<span style='color:red;'>❌ No stock images available!</span><br>";
        }
        
        $created_count++;
        $actual_status = get_post_status($post_id);
        echo "<span style='color:green;'>✅ Created: $post_title (ID: $post_id) - Status: $actual_status</span><br>";
        
        // Flush output for real-time progress
        flush();
        ob_flush();
        
        // Small delay
        usleep(50000);
    }
    
    echo "</div>";
    
    // Clear any caches that might affect filter queries
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Force clear object cache
    wp_cache_delete('car_filter_data', 'car_listings');
    
    echo "<h2>🎉 Chunk $current_chunk Complete!</h2>";
    echo "<p><strong>Successfully created:</strong> $created_count car listings in this chunk</p>";
    
    // Test the filter data immediately
    if (function_exists('get_car_fuel_types_with_counts')) {
        $fuel_test = get_car_fuel_types_with_counts();
        echo "<div style='background:#fff3cd; padding:10px; margin:10px 0; border-left:4px solid #ffc107;'>";
        echo "<h4>🔍 Filter Test Results:</h4>";
        echo "<p><strong>Fuel types found:</strong> " . count($fuel_test['fuel_types']) . "</p>";
        echo "<p><strong>Sample data:</strong> " . implode(', ', array_slice($fuel_test['fuel_types'], 0, 5)) . "</p>";
        echo "</div>";
    }
    
    // Show progress and next chunk link
    if ($current_chunk < $total_chunks) {
        $next_chunk = $current_chunk + 1;
        $completed_so_far = ($current_chunk - 1) * $chunk_size + $created_count;
        echo "<div style='background:#e7f3ff; padding:15px; border-left:4px solid #2196F3; margin:20px 0;'>";
        echo "<h3>📊 Progress: $completed_so_far / $target_count listings completed</h3>";
        echo "<p><strong>⏭️ Continue to next chunk:</strong></p>";
        echo "<p><a href='" . home_url("?bulk_create_listings=1&count=$target_count&chunk=$next_chunk") . "' style='background:#2196F3;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Process Chunk $next_chunk</a></p>";
        echo "</div>";
    } else {
        echo "<div style='background:#e8f5e8; padding:15px; border-left:4px solid #4CAF50; margin:20px 0;'>";
        echo "<h3>🎉 ALL DONE!</h3>";
        echo "<p><strong>Successfully created all $target_count car listings!</strong></p>";
        echo "<p>🔗 <a href='" . home_url() . "' target='_blank'>Visit your site to see the new listings!</a></p>";
        echo "</div>";
    }
    
    if ($target_count == 10) {
        echo "<div style='background:#fffbf0; padding:15px; border-left:4px solid #ffb900; margin:20px 0;'>";
        echo "<h3>🚀 Ready for Full Scale?</h3>";
        echo "<p>Test completed successfully! To create 1000 listings:</p>";
        echo "<p><a href='" . home_url('?bulk_create_listings=1&count=1000') . "'>Click here to create 1000 listings (will process in chunks of 50)</a></p>";
        echo "</div>";
    }
}

// END TEMPORARY BULK CAR LISTING CREATOR



