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
require_once get_stylesheet_directory() . '/includes/admin/user-favorites-column.php';

// NEWLY ADDED FROM ASTRA CHILD (SECOND FILE)
require_once get_stylesheet_directory() . '/includes/notifications/email-verification-notification.php';
require_once get_stylesheet_directory() . '/includes/legal/legal-pages.php';
require_once get_stylesheet_directory() . '/includes/legal/cookie-consent.php';

// Include shortcodes
require_once get_stylesheet_directory() . '/includes/shortcodes/car-gallery-shortcode.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/car-gallery-slider.php';

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
        wp_enqueue_style( 'single-car-gallery', $theme_dir . '/includes/single-car/single-car-gallery.css', array(), filemtime( get_stylesheet_directory() . '/includes/single-car/single-car-gallery.css' ) );
        wp_enqueue_style( 'single-car-main-gallery', $theme_dir . '/includes/single-car/single-car-main-gallery.css', array(), filemtime( get_stylesheet_directory() . '/includes/single-car/single-car-main-gallery.css' ) );
        wp_enqueue_script( 'single-car-gallery', $theme_dir . '/includes/single-car/single-car-gallery.js', array( 'jquery' ), filemtime( get_stylesheet_directory() . '/includes/single-car/single-car-gallery.js' ), true );
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
    require_once get_stylesheet_directory() . '/includes/single-car/report-handler.php';
    
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