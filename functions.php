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
require_once get_stylesheet_directory() . '/includes/core/google-maps-assets.php';
require_once get_stylesheet_directory() . '/includes/user-manage-listings/listing-details-badge-manager.php';
require_once get_stylesheet_directory() . '/includes/user-manage-listings/car-submission.php';
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
require_once get_stylesheet_directory() . '/includes/email/email-sender.php';
require_once get_stylesheet_directory() . '/includes/email/test-email-sender.php';
require_once get_stylesheet_directory() . '/includes/email/email-verification-init.php';
require_once get_stylesheet_directory() . '/includes/email/email-verification.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/favourites-button.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/single-car-template-gallery.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/forgot-password-form.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/seller-verification/seller-verification.php';

require_once get_stylesheet_directory() . '/includes/admin/user-favorites-column.php';

// Simple Dealership Account Creation
require_once get_stylesheet_directory() . '/includes/admin/dealership-accounts.php';
require_once get_stylesheet_directory() . '/includes/admin/listing-click-metrics.php';

// NEWLY ADDED FROM ASTRA CHILD (SECOND FILE)
require_once get_stylesheet_directory() . '/includes/notifications/email-verification-notification.php';
require_once get_stylesheet_directory() . '/includes/legal/legal-pages.php';
// Cookie consent functionality disabled
// require_once get_stylesheet_directory() . '/includes/legal/cookie-consent.php';

// Car Views Counter System
require_once get_stylesheet_directory() . '/includes/views-counter/views-database.php';
require_once get_stylesheet_directory() . '/includes/views-counter/views-tracker.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/car-views-counter/car-views-counter.php';

// Seller Reviews System
require_once get_stylesheet_directory() . '/includes/reviews/seller-reviews-database.php';
require_once get_stylesheet_directory() . '/includes/reviews/seller-reviews-ajax.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/seller-reviews/seller-reviews-display.php';
require_once get_stylesheet_directory() . '/includes/admin/seller-reviews-admin.php';

// Refresh Listing System
require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/init.php';

// =========================================================================
// WordPress File Upload Configuration
// =========================================================================

/**
 * Allow JFIF file uploads for car images
 * WordPress by default doesn't recognize JFIF as a valid file type
 */
function allow_car_image_file_types($mimes) {
    // Add JFIF support with multiple MIME type variations
    $mimes['jfif'] = 'image/jpeg';
    $mimes['jpe'] = 'image/jpeg';
    
    // Ensure all standard car image formats are allowed
    $mimes['jpg'] = 'image/jpeg';
    $mimes['jpeg'] = 'image/jpeg';
    $mimes['png'] = 'image/png';
    $mimes['gif'] = 'image/gif';
    $mimes['webp'] = 'image/webp';
    
    return $mimes;
}
add_filter('upload_mimes', 'allow_car_image_file_types');

/**
 * Additional file type checking for JFIF files
 * Some browsers report JFIF files with non-standard MIME types
 */
function check_jfif_file_type($data, $file, $filename, $mimes) {
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // If it's a JFIF file, force the correct MIME type
    if ($file_extension === 'jfif' || $file_extension === 'jpe') {
        $data['ext'] = 'jpeg';
        $data['type'] = 'image/jpeg';
        $data['proper_filename'] = false;
    }
    
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'check_jfif_file_type', 10, 4);

// =========================================================================
// QUICK ADD FORM FUNCTIONALITY - ENHANCED SINGLE CAR ENTRY (DISABLED FOR NOW)
// =========================================================================
// Include quick add form features (templates, form duplication, etc.)
// require_once get_stylesheet_directory() . '/includes/user-manage-listings/bulk-add-listings/quick-add-form.php';

// Enqueue quick add form scripts when needed (DISABLED FOR NOW)
/*
add_action('wp_enqueue_scripts', function() {
    // Enqueue quick add form scripts on add listing pages
    if (is_page_template('template-add-listing.php') || is_page('add-listing')) {
        enqueue_quick_add_scripts();
    }
});
*/

// =========================================================================
// QUICK ADD FORM AJAX HOOKS (DISABLED FOR NOW)
// =========================================================================
// Quick template functionality
/*
add_action('wp_ajax_save_quick_template', 'handle_save_quick_template');
add_action('wp_ajax_load_quick_template', 'handle_load_quick_template');
*/

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
    // COMMENTED OUT: car-listings files were deleted
    /*
    if ( is_page( 'used-cars-facetwp' ) || strpos( $_SERVER['REQUEST_URI'], '/used-cars-facetwp' ) !== false ) {
        $theme_dir = get_stylesheet_directory_uri();
        wp_enqueue_style( 'car-listings-style', $theme_dir . '/includes/car-listings/car-listings.css', array(), filemtime( get_stylesheet_directory() . '/includes/car-listings/car-listings.css' ) );
        wp_enqueue_script( 'car-listings-js', $theme_dir . '/includes/car-listings/car-listings.js', array( 'jquery' ), filemtime( get_stylesheet_directory() . '/includes/car-listings/car-listings.js' ), true );
    }
    */

    // Enqueue single car gallery styles and scripts
    if ( is_singular('car') ) {
        $theme_dir = get_stylesheet_directory_uri();
        
        // Enqueue gallery styles and scripts (no slider dependencies needed)
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
    require_once get_stylesheet_directory() . '/includes/shortcodes/report-button/report-handler.php';
    
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
require_once get_stylesheet_directory() . '/includes/shortcodes/car-single-call-button/car-single-call-button.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/car-single-call-button/car-single-whatsapp-button.php';


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
// Car Make/Model Taxonomy Management
// =========================================================================

/**
 * Sync car makes and models from JSON files to taxonomy
 * This function reads all JSON files and creates a hierarchical taxonomy structure
 */
function sync_car_makes_models_to_taxonomy() {
    if (!taxonomy_exists('car_make')) {
        error_log('Make taxonomy does not exist. Make sure it is registered.');
        return false;
    }
    $json_dir = get_stylesheet_directory() . '/simple_jsons/';
    if (!is_dir($json_dir)) {
        error_log('JSON directory does not exist: ' . $json_dir);
        return false;
    }
    $json_files = glob($json_dir . '*.json');
    if (empty($json_files)) {
        error_log('No JSON files found in: ' . $json_dir);
        return false;
    }
    $existing_terms = get_terms(array(
        'taxonomy' => 'car_make',
        'hide_empty' => false,
        'fields' => 'ids'
    ));
    if (!is_wp_error($existing_terms) && !empty($existing_terms)) {
        foreach ($existing_terms as $term_id) {
            wp_delete_term($term_id, 'car_make');
        }
        error_log('Deleted ' . count($existing_terms) . ' existing make terms');
    }
    $total_makes = 0;
    $total_models = 0;
    $errors = array();
    foreach ($json_files as $json_file) {
        $json_content = file_get_contents($json_file);
        if ($json_content === false) {
            $errors[] = "Failed to read file: " . basename($json_file);
            continue;
        }
        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "Invalid JSON in file: " . basename($json_file);
            continue;
        }
        if (!isset($data['make']) || !isset($data['models'])) {
            $errors[] = "Missing 'make' or 'models' in file: " . basename($json_file);
            continue;
        }
        $make_name = $data['make'];
        $models = $data['models'];
        if (!is_array($models)) {
            $errors[] = "Models is not an array in file: " . basename($json_file);
            continue;
        }
        $make_term = wp_insert_term(
            $make_name,
            'car_make',
            array(
                'description' => 'Car make: ' . $make_name,
                'slug' => sanitize_title($make_name)
            )
        );
        if (is_wp_error($make_term)) {
            $errors[] = "Failed to create make term for: " . $make_name . " - " . $make_term->get_error_message();
            continue;
        }
        $make_term_id = $make_term['term_id'];
        $total_makes++;
        foreach ($models as $model_name) {
            if (empty($model_name)) {
                continue;
            }
            $model_term = wp_insert_term(
                $model_name,
                'car_make',
                array(
                    'description' => $make_name . ' ' . $model_name,
                    'slug' => sanitize_title($make_name . '-' . $model_name),
                    'parent' => $make_term_id
                )
            );
            if (is_wp_error($model_term)) {
                $errors[] = "Failed to create model term: " . $make_name . " " . $model_name . " - " . $model_term->get_error_message();
                continue;
            }
            $total_models++;
        }
    }
    $success_message = "Make taxonomy sync completed: {$total_makes} makes and {$total_models} models created";
    error_log($success_message);
    if (!empty($errors)) {
        error_log("Make taxonomy sync errors: " . implode(', ', $errors));
    }
    return array(
        'success' => true,
        'makes_created' => $total_makes,
        'models_created' => $total_models,
        'errors' => $errors
    );
}

/**
 * Admin function to manually trigger taxonomy sync
 * This creates an admin page to run the sync function
 */
function add_car_taxonomy_admin_page() {
    add_management_page(
        'Car Taxonomy Sync',
        'Car Taxonomy Sync',
        'manage_options',
        'car-taxonomy-sync',
        'car_taxonomy_sync_admin_page'
    );
}
add_action('admin_menu', 'add_car_taxonomy_admin_page');

/**
 * Admin page callback for taxonomy sync
 */
function car_taxonomy_sync_admin_page() {
    ?>
    <div class="wrap">
        <h1>Car Taxonomy Sync</h1>
        <p>This tool will synchronize car makes and models from JSON files to the car_make taxonomy.</p>
        <p><strong>Warning:</strong> This will delete all existing terms in the car_make taxonomy and recreate them from the JSON files.</p>
        <button id="car-taxonomy-sync-btn" class="button button-primary">Sync Now (AJAX)</button>
        <div id="car-taxonomy-sync-progress" style="margin-top:20px; display:none;">
            <div style="width:100%;background:#eee;border-radius:4px;overflow:hidden;height:24px;">
                <div id="car-taxonomy-sync-bar" style="background:#0073aa;height:24px;width:0%;transition:width 0.3s;"></div>
            </div>
            <div id="car-taxonomy-sync-status" style="margin-top:8px;"></div>
        </div>
        <div id="car-taxonomy-sync-result" style="margin-top:20px;"></div>
        <h3>Current Taxonomy Status</h3>
        <?php
        $make_terms = get_terms(array(
            'taxonomy' => 'car_make',
            'hide_empty' => false,
            'parent' => 0
        ));
        if (!is_wp_error($make_terms) && !empty($make_terms)) {
            echo '<p>Current makes in taxonomy: ' . count($make_terms) . '</p>';
            echo '<ul>';
            foreach ($make_terms as $make_term) {
                $model_count = get_terms(array(
                    'taxonomy' => 'car_make',
                    'hide_empty' => false,
                    'parent' => $make_term->term_id,
                    'fields' => 'count'
                ));
                echo '<li>' . esc_html($make_term->name) . ' (' . $model_count . ' models)</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No car makes found in taxonomy.</p>';
        }
        ?>
        <h3>JSON Files Status</h3>
        <?php
        $json_dir = get_stylesheet_directory() . '/simple_jsons/';
        $json_files = glob($json_dir . '*.json');
        echo '<p>JSON files found: ' . count($json_files) . '</p>';
        ?>
    </div>
    <?php
}

/**
 * Automatically assign car posts to taxonomy terms based on make/model ACF fields
 * This function runs when a car post is saved
 */
function auto_assign_car_taxonomy_terms($post_id) {
    // Only run for car post type
    if (get_post_type($post_id) !== 'car') {
        return;
    }

    // Skip during bulk operations or autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Get make and model from ACF fields
    $make = get_field('make', $post_id);
    $model = get_field('model', $post_id);

    if (empty($make)) {
        return;
    }

    $terms_to_assign = array();

    // Find the make term
    $make_term = get_term_by('name', $make, 'car_make');
    if ($make_term && $make_term->parent == 0) {
        $terms_to_assign[] = $make_term->term_id;

        // Find the model term if model is specified
        if (!empty($model)) {
            $model_terms = get_terms(array(
                'taxonomy' => 'car_make',
                'name' => $model,
                'parent' => $make_term->term_id,
                'hide_empty' => false
            ));

            if (!empty($model_terms) && !is_wp_error($model_terms)) {
                $terms_to_assign[] = $model_terms[0]->term_id;
            }
        }
    }

    // Assign the terms to the post
    if (!empty($terms_to_assign)) {
        wp_set_post_terms($post_id, $terms_to_assign, 'car_make');
    }
}
add_action('acf/save_post', 'auto_assign_car_taxonomy_terms', 20);

/**
 * Manual function to sync all existing car posts with taxonomy terms
 * This can be run once to assign taxonomy terms to existing posts
 */
function sync_existing_cars_to_taxonomy() {
    $car_posts = get_posts(array(
        'post_type' => 'car',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'pending', 'draft')
    ));

    $updated_count = 0;

    foreach ($car_posts as $car_post) {
        auto_assign_car_taxonomy_terms($car_post->ID);
        $updated_count++;
    }

    error_log("Synced {$updated_count} car posts with taxonomy terms");
    return $updated_count;
}

/**
 * Add sync existing cars button to admin page
 */
function add_sync_existing_cars_to_admin_page() {
    // This is handled in the main admin page above
}

// Optional: Run taxonomy sync on theme activation (commented out for safety)
// register_activation_hook(__FILE__, 'sync_car_makes_models_to_taxonomy');

// --- AJAX Sync Handler ---
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'tools_page_car-taxonomy-sync') {
        wp_enqueue_script('car-taxonomy-sync', get_stylesheet_directory_uri() . '/assets/js/car-taxonomy-sync.js', ['jquery'], null, true);
        wp_localize_script('car-taxonomy-sync', 'CarTaxonomySync', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('car_taxonomy_sync_ajax')
        ]);
    }
});

add_action('wp_ajax_car_taxonomy_sync', function() {
    check_ajax_referer('car_taxonomy_sync_ajax', 'nonce');
    // We'll process the JSON files in batches for progress updates
    $json_dir = get_stylesheet_directory() . '/simple_jsons/';
    $json_files = glob($json_dir . '*.json');
    $total = count($json_files);
    $batch = isset($_POST['batch']) ? intval($_POST['batch']) : 0;
    $batch_size = 5; // Process 5 files per request
    $start = $batch * $batch_size;
    $end = min($start + $batch_size, $total);
    $errors = [];
    $makes = 0;
    $models = 0;
    for ($i = $start; $i < $end; $i++) {
        $json_file = $json_files[$i];
        $json_content = file_get_contents($json_file);
        if ($json_content === false) {
            $errors[] = "Failed to read file: " . basename($json_file);
            continue;
        }
        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "Invalid JSON in file: " . basename($json_file);
            continue;
        }
        if (!isset($data['make']) || !isset($data['models'])) {
            $errors[] = "Missing 'make' or 'models' in file: " . basename($json_file);
            continue;
        }
        $make_name = $data['make'];
        $models_arr = $data['models'];
        if (!is_array($models_arr)) {
            $errors[] = "Models is not an array in file: " . basename($json_file);
            continue;
        }
        // Create or get the make term (parent)
        $make_term = term_exists($make_name, 'car_make');
        if (!$make_term) {
            $make_term = wp_insert_term($make_name, 'car_make', [
                'description' => 'Car make: ' . $make_name,
                'slug' => sanitize_title($make_name)
            ]);
            if (is_wp_error($make_term)) {
                $errors[] = "Failed to create make term for: $make_name - " . $make_term->get_error_message();
                continue;
            }
            $make_term_id = $make_term['term_id'];
            $makes++;
        } else {
            $make_term_id = is_array($make_term) ? $make_term['term_id'] : $make_term;
        }
        // Create model terms (children)
        foreach ($models_arr as $model_name) {
            if (empty($model_name)) continue;
            $model_term = term_exists($model_name, 'car_make', $make_term_id);
            if (!$model_term) {
                $model_term = wp_insert_term($model_name, 'car_make', [
                    'description' => $make_name . ' ' . $model_name,
                    'slug' => sanitize_title($make_name . '-' . $model_name),
                    'parent' => $make_term_id
                ]);
                if (is_wp_error($model_term)) {
                    $errors[] = "Failed to create model term: $make_name $model_name - " . $model_term->get_error_message();
                    continue;
                }
                $models++;
            }
        }
    }
    $done = $end >= $total;
    wp_send_json([
        'success' => true,
        'done' => $done,
        'batch' => $batch,
        'total' => $total,
        'makes' => $makes,
        'models' => $models,
        'errors' => $errors
    ]);
});

// =========================================================================
// CSV Import Functionality
// =========================================================================

// Include CSV car import functionality
require_once get_stylesheet_directory() . '/includes/admin/csv-car-import.php';

// =========================================================================
// AJAX Handlers for Add Listing Form
// =========================================================================

/**
 * AJAX handler to get models for a selected make
 */
function get_models_for_make() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'add_car_listing_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    $make_name = sanitize_text_field($_POST['make']);
    
    if (empty($make_name)) {
        wp_send_json_error('Make name is required');
        return;
    }
    
    // Find the make term
    $make_term = get_term_by('name', $make_name, 'car_make');
    
    if (!$make_term || is_wp_error($make_term)) {
        wp_send_json_error('Make not found');
        return;
    }
    
    // Get all child terms (models) for this make
    $model_terms = get_terms(array(
        'taxonomy' => 'car_make',
        'hide_empty' => false,
        'parent' => $make_term->term_id,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (is_wp_error($model_terms)) {
        wp_send_json_error('Error fetching models');
        return;
    }
    
    $models = array();
    foreach ($model_terms as $model_term) {
        $models[] = $model_term->name;
    }
    
    wp_send_json_success($models);
}

add_action('wp_ajax_get_models_for_make', 'get_models_for_make');
add_action('wp_ajax_nopriv_get_models_for_make', 'get_models_for_make');

// =========================================================================

// =========================================================================
// Manual Taxonomy Sync Trigger (for testing)
// =========================================================================

/**
 * Manual function to trigger taxonomy sync - can be called from admin or via WP-CLI
 */
function manual_sync_car_taxonomy() {
    if (!taxonomy_exists('car_make')) {
        error_log('Car make taxonomy does not exist. Please ensure it is registered.');
        return false;
    }
    
    $result = sync_car_makes_models_to_taxonomy();
    
    if ($result && $result['success']) {
        error_log("Manual taxonomy sync completed successfully!");
        error_log("Makes created: " . $result['makes_created']);
        error_log("Models created: " . $result['models_created']);
        if (!empty($result['errors'])) {
            error_log("Errors: " . implode(', ', $result['errors']));
        }
        return true;
    } else {
        error_log("Manual taxonomy sync failed!");
        return false;
    }
}

// Uncomment the line below to run sync on next page load (for testing)
// add_action('init', 'manual_sync_car_taxonomy');

// =========================================================================






