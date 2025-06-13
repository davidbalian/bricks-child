<?php
/**
 * Car Search Form Shortcode [car_search_form].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Include the new filter form function
require_once get_stylesheet_directory() . '/includes/car-listings/car-filter-form.php';

// Car Search Form Shortcode
function car_search_form_shortcode() {
    global $wpdb; // Keep global wpdb if other non-form PHP remains or might be added
    
    // Removed the old data fetching for makes, models, etc.

    ob_start();

    // Display the new filter form with 'homepage' context
    // echo display_car_filter_form('homepage');

    // Removed the old empty form container and script tag

    ?>
    <div class="car-search-form">
        <form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
            <div class="search-fields">
                <input type="text" name="s" placeholder="Search for a car..." value="<?php echo get_search_query(); ?>">
                <input type="hidden" name="post_type" value="car">
            </div>
            <!-- Removed car spec filters -->
            <?php // echo display_car_filter_form('homepage'); ?>
            <button type="submit" class="search-submit">Search Cars</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('car_search_form', 'car_search_form_shortcode');