<?php
/**
 * Enqueue scripts and styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define Constants
 * Note: BRICKS_CHILD_THEME_VERSION is defined in the main functions.php
 * Ensure it's defined before this file is included if you move the constant definition.
 */

/**
 * Enqueue styles
 */
function bricks_child_enqueue_styles() {
    // Enqueue Font Awesome from CDN first with higher priority
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', array(), '6.7.2', 'all' );
    
    wp_enqueue_style( 'bricks-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('bricks-frontend', 'font-awesome'), filemtime( get_stylesheet_directory() . '/style.css' ), 'all' );

    // Favourites Button CSS moved to conditional loading in favorite-button.php



    // Enqueue car listings styles

    // Enqueue add listing page styles
    if (is_page_template('template-add-listing.php')) {
        wp_enqueue_style( 'bricks-child-add-listing-css', get_stylesheet_directory_uri() . '/includes/user-manage-listings/template-add-listing/add-listing.css', array('bricks-child-theme-css'), filemtime( get_stylesheet_directory() . '/includes/user-manage-listings/template-add-listing/add-listing.css' ), 'all' );
    }

    // Enqueue edit listing styles
    if (is_page_template('template-edit-listing.php')) {
        wp_enqueue_style( 'bricks-child-edit-listing-css', get_stylesheet_directory_uri() . '/includes/user-manage-listings/template-add-listing/add-listing.css', array('bricks-child-theme-css'), filemtime( get_stylesheet_directory() . '/includes/user-manage-listings/template-add-listing/add-listing.css' ), 'all' );
    }

    // Enqueue my-listings styles conditionally
    global $post;
    if (is_page('my-listings') || (is_singular() && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'my_listings'))) {
        wp_enqueue_style( 'bricks-child-my-listings-css', get_stylesheet_directory_uri() . '/includes/user-account/my-listings/my-listings.css', array('bricks-child-theme-css'), filemtime( get_stylesheet_directory() . '/includes/user-account/my-listings/my-listings.css' ), 'all' );
    }

    // Enqueue my-account styles only on my-account page
    if (is_page('my-account')) {
        wp_enqueue_style( 'bricks-child-my-account-css', get_stylesheet_directory_uri() . '/includes/user-account/my-account/my-account.css', array('bricks-child-theme-css'), filemtime( get_stylesheet_directory() . '/includes/user-account/my-account/my-account.css' ), 'all' );
    }

    // Enqueue account dropdown script for logged-in users
    // if ( is_user_logged_in() ) {
    //     wp_enqueue_script( 'bricks-child-account-dropdown-js', get_stylesheet_directory_uri() . '/includes/user-account/account-dropdown.js', array(), filemtime( get_stylesheet_directory() . '/includes/user-account/account-dropdown.js' ), true ); // true for loading in footer
    // }

    // Enqueue Car Listings script and localize data if relevant shortcodes might be present
    global $post;
    $load_car_listings_script = false;
    
    // Check if the current page has the shortcode
    if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'car_listings' ) || has_shortcode( $post->post_content, 'car_listing_detailed' ) ) ) {
        $load_car_listings_script = true;
    }
    
    // Also load on car archive pages or specific templates
    if ( is_post_type_archive('car') || is_tax('car_make') || is_tax('car_model') ) {
        $load_car_listings_script = true;
    }
    
    // Check if we're on a page with a specific template that might need car listings
    if ( is_page() && ( is_page_template('template-car-listings.php') || is_page_template('template-car-search.php') ) ) {
        $load_car_listings_script = true;
    }
    
    // For debugging - you can temporarily set this to true to always load the script
    // $load_car_listings_script = true;

    if ( $load_car_listings_script ) {
        wp_enqueue_script(
            'car-listings-script',
            get_stylesheet_directory_uri() . '/includes/car-listings/car-listings.js',
            array('jquery'), 
            filemtime(get_stylesheet_directory() . '/includes/car-listings/car-listings.js'),
            false // Load in header instead of footer
        );

        // Prepare ALL data needed by car-listings.js
        
        // 1. Get all published cars with their meta data for filtering
        $all_cars_data = array();
        $args = array(
            'post_type' => 'car',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Get all cars
        );
        $car_query = new WP_Query($args);

        if ($car_query->have_posts()) {
            while ($car_query->have_posts()) {
                $car_query->the_post();
                $car_id = get_the_ID();
                $all_cars_data[] = array(
                    'id' => $car_id,
                    'make' => get_field('make', $car_id),
                    'model' => get_field('model', $car_id),
                    // variant field removed
                    'location' => get_field('location', $car_id),
                    'price' => get_field('price', $car_id),
                    'year' => get_field('year', $car_id),
                    'mileage' => get_field('mileage', $car_id),
                    'engine_capacity' => get_field('engine_capacity', $car_id),
                    'fuel_type' => get_field('fuel_type', $car_id),
                    'body_type' => get_field('body_type', $car_id),
                    'drive_type' => get_field('drive_type', $car_id),
                    'exterior_color' => get_field('exterior_color', $car_id),
                    'interior_color' => get_field('interior_color', $car_id),
                    // Add other relevant fields if needed by JS filtering
                );
            }
            wp_reset_postdata();
        }
        
        // 2. Get filter counts and data using functions from car-listings-data.php
        // $make_data = get_car_makes_with_counts(); // COMMENTED OUT: Function doesn't exist
        // $model_data = get_car_models_by_make_with_counts($make_data['makes']); // COMMENTED OUT: Depends on line above
        // variant data removed
        $price_data = get_car_price_ranges_with_counts();
        $year_data = get_car_years_with_counts();
        $kilometer_data = get_car_kilometer_ranges_with_counts();
        $engine_size_data = get_car_engine_sizes_with_counts();
        $body_type_data = get_car_body_types_with_counts();
        $fuel_type_data = get_car_fuel_types_with_counts();
        $drive_type_data = get_car_drive_types_with_counts();
        $color_data = get_car_colors_with_counts();
        $min_max_data = get_car_filter_min_max_values(); // If needed by JS

        // 3. Structure data for localization
        $localized_data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('toggle_favorite_car'),
            'filter_nonce' => wp_create_nonce('filter_car_listings_nonce'),
            'all_cars' => $all_cars_data, // Pass the array of car objects
            // 'make_counts' => $make_data['counts'], // COMMENTED OUT: Depends on commented function
            // 'model_counts' => $model_data['model_counts'], // COMMENTED OUT: Depends on commented function
            // variant data removed
            'price_counts' => $price_data['counts'],
            'year_counts' => $year_data['counts'],
            'km_counts' => $kilometer_data['counts'],
            'engine_counts' => $engine_size_data['counts'],
            'body_type_counts' => $body_type_data['counts'],
            'fuel_type_counts' => $fuel_type_data['counts'],
            'drive_type_counts' => $drive_type_data['counts'],
            'exterior_color_counts' => $color_data['exterior_counts'],
            'interior_color_counts' => $color_data['interior_counts'],
            // Add min_max_data if your JS needs it: 'min_max' => $min_max_data 
        );
        
        wp_localize_script('car-listings-script', 'carListingsData', $localized_data);
    }
}
add_action( 'wp_enqueue_scripts', 'bricks_child_enqueue_styles', 15 );

/**
 * Enqueue styles for the custom login page.
 */
function bricks_child_enqueue_login_styles() {
    wp_enqueue_style( 'bricks-child-login-css', get_stylesheet_directory_uri() . '/includes/auth/login.css', array(), filemtime( get_stylesheet_directory() . '/includes/auth/login.css' ), 'all' );
}
add_action( 'login_enqueue_scripts', 'bricks_child_enqueue_login_styles' );

/**
 * Enqueue login styles on the custom signin page
 */
function bricks_child_enqueue_signin_page_styles() {
    // Check if we're on the custom signin page
    if ( is_page('signin') ) {
        wp_enqueue_style( 'bricks-child-login-css', get_stylesheet_directory_uri() . '/includes/auth/login.css', array(), filemtime( get_stylesheet_directory() . '/includes/auth/login.css' ), 'all' );
    }
}
add_action( 'wp_enqueue_scripts', 'bricks_child_enqueue_signin_page_styles' );

/**
 * Enqueue intl-tel-input library assets for registration and login forms.
 */
function enqueue_intl_tel_input_assets() {
    global $post;
    $load_assets = false;

    // Check if on the custom login page (slug 'signin')
    if ( is_page('signin') ) {
        $load_assets = true;
    }
    // Check if on the registration page (slug 'register')
    elseif ( is_page('register') ) {
        $load_assets = true;
    }
    // Check if on a page/post containing the registration shortcode
    elseif ( is_singular() && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'custom_registration' ) ) {
        $load_assets = true;
    }
    // Check if on the forgot password page (slug 'forgot-password')
    elseif ( is_page('forgot-password') ) {
        $load_assets = true;
    }

    if ( $load_assets ) {
        // Enqueue CSS
        wp_enqueue_style( 'intl-tel-input-css', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css', array(), '17.0.13' );

        // Enqueue registration-specific styles
        if ( is_page('register') || ( is_singular() && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'custom_registration' ) ) ) {
             wp_enqueue_style( 'bricks-child-register-css', get_stylesheet_directory_uri() . '/includes/auth/register.css', array(), filemtime( get_stylesheet_directory() . '/includes/auth/register.css' ), 'all' );
        }

        // Enqueue JS (needs jQuery)
        wp_enqueue_script( 'intl-tel-input-js', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js', array('jquery'), '17.0.13', true );

        // Enqueue utils.js (required for getNumber etc.)
        wp_enqueue_script( 'intl-tel-input-utils-js', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js', array('intl-tel-input-js'), '17.0.13', true );
    }
}
add_action( 'wp_enqueue_scripts', 'enqueue_intl_tel_input_assets' );

function enqueue_theme_scripts() {
    $theme_dir = get_stylesheet_directory_uri();

    // Theme scripts can be added here if needed
}
add_action('wp_enqueue_scripts', 'enqueue_theme_scripts'); 