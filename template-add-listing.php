<?php
/**
 * Template Name: Add Car Listing
 *
 * @package Bricks Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If the user is not logged in, send them straight to the login page
// and mark that they came from the "Sell My Car" flow so we can show
// a contextual banner on the login screen.
if ( ! is_user_logged_in() ) {
    $login_url = add_query_arg(
        'selling_car',
        '1',
        wp_login_url()
    );

    wp_safe_redirect( $login_url );
    exit;
}

// Include filter base functions for custom dropdown rendering
require_once get_stylesheet_directory() . '/includes/shortcodes/car-filters/filters/filter-base.php';

// Get all makes (parent terms) from car_make taxonomy for the custom dropdown
$make_terms = get_terms(array(
    'taxonomy' => 'car_make',
    'hide_empty' => false,
    'parent' => 0,
    'orderby' => 'name',
    'order' => 'ASC'
));

// Format makes for the custom dropdown
// IMPORTANT: Use name as value (not term_id) because backend expects name for taxonomy lookup
$add_listing_make_options = array();
if (!is_wp_error($make_terms) && !empty($make_terms)) {
    foreach ($make_terms as $make_term) {
        $add_listing_make_options[] = array(
            'value'   => $make_term->name,
            'label'   => $make_term->name,
            'slug'    => $make_term->slug,
            'term_id' => $make_term->term_id,
        );
    }
}

// Ensure jQuery is loaded
wp_enqueue_script('jquery');

// Enqueue car-filters CSS for custom dropdown styling
wp_enqueue_style(
    'car-filters-css',
    get_stylesheet_directory_uri() . '/includes/shortcodes/car-filters/car-filters.css',
    array(),
    filemtime(get_stylesheet_directory() . '/includes/shortcodes/car-filters/car-filters.css')
);

// Font Awesome is now loaded globally in enqueue.php
// wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', array(), '6.7.2', 'all');

// Enqueue image optimization script
wp_enqueue_script(
    'astra-child-image-optimization',
    get_stylesheet_directory_uri() . '/includes/user-manage-listings/image-optimization.js',
    array('jquery'),
    filemtime(get_stylesheet_directory() . '/includes/user-manage-listings/image-optimization.js'),
    true
);

// Enqueue add-listing script
wp_enqueue_script(
    'astra-child-add-listing-js',
    get_stylesheet_directory_uri() . '/includes/user-manage-listings/template-add-listing/add-listing.js',
    array('jquery', 'astra-child-image-optimization'),
    filemtime(get_stylesheet_directory() . '/includes/user-manage-listings/template-add-listing/add-listing.js'),
    true
);

// Localize the script with necessary data
wp_localize_script('astra-child-add-listing-js', 'addListingData', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('add_car_listing_nonce')
));

get_header(); ?>



<div class="bricks-container">
    <div class="bricks-content">
        <?php
        if ( is_user_logged_in() ) {
            // Check for success message
            if ( isset( $_GET['listing_submitted'] ) && $_GET['listing_submitted'] == 'success' ) {
                ?>
                <div class="listing-success-message">
                    <h2><?php esc_html_e( 'Your listing has been submitted successfully!', 'bricks-child' ); ?></h2>
                    <p><?php esc_html_e( 'Thank you for submitting your car listing. It will be reviewed by our team and published soon.', 'bricks-child' ); ?></p>
                    <p><?php esc_html_e( 'To receive email notifications about views and clicks on your listing,', 'bricks-child' ); ?><br><?php esc_html_e( 'verify your email from your account page if you haven\'t already.', 'bricks-child' ); ?></p>
                    <div class="listing-success-buttons">
                        <a href="<?php echo esc_url( home_url( '/my-account' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'My Account', 'bricks-child' ); ?></a>
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Return to Home', 'bricks-child' ); ?></a>
                    </div>
                </div>
                <?php
            } elseif ( isset( $_GET['listing_error'] ) ) {
                ?>
                <div class="listing-error-message">
                    <h2><?php esc_html_e( 'Submission Error', 'bricks-child' ); ?></h2>
                    <p><?php esc_html_e( 'An error occurred with your submission. Please review all fields and try again.', 'bricks-child' ); ?></p>
                </div>
                <h1><?php esc_html_e( 'Add New Car Listing', 'bricks-child' ); ?></h1>
                <p class="listing-note"><?php esc_html_e( 'Note: Duplicate listings will be flagged and removed. You can find all your ads in "My Listings" on the top of the site.', 'bricks-child' ); ?></p>
                <?php
            } elseif ( isset( $_GET['listing_errors'] ) ) {
                ?>
                <div class="listing-error-message">
                    <h2><?php esc_html_e( 'Submission Error', 'bricks-child' ); ?></h2>
                    <p><?php echo esc_html( $_GET['listing_errors'] ); ?></p>
                </div>
                <h1><?php esc_html_e( 'Add New Car Listing', 'bricks-child' ); ?></h1>
                <p class="listing-note"><?php esc_html_e( 'Note: Duplicate listings will be flagged and removed. You can find all your ads in "My Listings" on the top of the site.', 'bricks-child' ); ?></p>
                <?php
            } else {
                ?>
                <h1><?php esc_html_e( 'Add New Car Listing', 'bricks-child' ); ?></h1>
                <p class="listing-note"><?php esc_html_e( 'Note: Duplicate listings will be flagged and removed. You can find all your ads in "My Listings" on the top of the site.', 'bricks-child' ); ?></p>
                
                <?php
                // Display error messages if any
                if (isset($_GET['error']) && !empty($_GET['error'])) {
                    $error_messages = [];
                    
                    if ($_GET['error'] === 'validation') {
                        $error_messages[] = esc_html__('Please fill in all required fields', 'bricks-child');
                        
                        // Check for specific validation errors
                        if (isset($_GET['fields']) && !empty($_GET['fields'])) {
                            $missing_fields = explode(',', sanitize_text_field($_GET['fields']));
                            echo '<div class="form-error-message">';
                            echo '<p>' . esc_html__('The following fields are required:', 'bricks-child') . '</p>';
                            echo '<ul>';
                            foreach ($missing_fields as $field) {
                                echo '<li>' . esc_html(ucfirst(str_replace('_', ' ', $field))) . '</li>';
                            }
                            echo '</ul>';
                            echo '</div>';
                        } else {
                            echo '<div class="form-error-message">';
                            echo '<p>' . esc_html__('Please fill in all required fields.', 'bricks-child') . '</p>';
                            echo '</div>';
                        }
                    } elseif ($_GET['error'] === 'images') {
                        echo '<div class="form-error-message">';
                        echo '<p>' . esc_html__('Please upload at least one image for your listing.', 'bricks-child') . '</p>';
                        echo '</div>';
                    } elseif ($_GET['error'] === 'post_creation') {
                        echo '<div class="form-error-message">';
                        echo '<p>' . esc_html__('We encountered an issue creating your listing. Please try again.', 'bricks-child') . '</p>';
                        echo '</div>';
                    } elseif ($_GET['error'] === 'generic') {
                        echo '<div class="form-error-message">';
                        echo '<p>' . esc_html__('An error occurred with your submission. Please review all fields and try again.', 'bricks-child') . '</p>';
                        echo '</div>';
                    }
                }
                
                // Display the add listing form
                ?>
                <form id="add-car-listing-form" class="car-listing-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'add_car_listing_nonce', 'add_car_listing_nonce' ); ?>
                    <input type="hidden" name="action" value="add_new_car_listing">
                    <input type="hidden" name="post_type" value="car">
                    <input type="hidden" id="async_session_id" name="async_session_id" value="">

                    <div class="add-listing-images-section input-wrapper">
                        <h2><?php echo get_svg_icon('camera'); ?> <?php esc_html_e( 'Upload Images', 'bricks-child' ); ?></h2>
                        <p class="image-upload-note"><?php esc_html_e( 'Note: ads with good photos get more attention', 'bricks-child' ); ?></p>
                        <div class="image-upload-container">
                            <div class="file-upload-area" id="file-upload-area" role="button" tabindex="0">
                                <div class="upload-message">
                                    <?php echo get_svg_icon('cloud-arrow-up'); ?>
                                    <p><?php esc_html_e( 'Drag & Drop Images Here', 'bricks-child' ); ?></p>
                                    <p class="small"><?php esc_html_e( 'or click to select files', 'bricks-child' ); ?></p>
                                </div>
                            </div>
                            <input type="file" id="car_images" name="car_images[]" multiple accept="image/jpeg,image/jfif,image/jpg,image/png,image/gif,image/webp,.jfif,.jpe" style="display: none;">
                            <div id="image-preview" class="image-preview"></div>
                        </div>
                        <p class="image-upload-info"><?php esc_html_e( 'Hold CTRL to choose several photos. Minimum 2 images per listing. Maximum 25 images per listing. Maximum file size is 12MB, the formats are .jpg, .jpeg, .jfif, .png, .gif, .webp.', 'bricks-child' ); ?></p>
                    </div>  

                    <div class="add-listing-main-row">
                        <div class="add-listing-main-info-column">
                            <div class="form-section basic-details-section input-wrapper">
                                <h2><?php echo get_svg_icon('circle-info'); ?> <?php esc_html_e( 'Basic Details', 'bricks-child' ); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('car-side'); ?> <?php esc_html_e( 'Make', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-make',
                                            'name'        => 'make',
                                            'placeholder' => __('Select Make', 'bricks-child'),
                                            'options'     => $add_listing_make_options,
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                            'data_attrs'  => array(
                                                'filter-type' => 'make',
                                            ),
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('car'); ?> <?php esc_html_e( 'Model', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-model',
                                            'name'        => 'model',
                                            'placeholder' => __('Select Model', 'bricks-child'),
                                            'options'     => array(),
                                            'selected'    => '',
                                            'disabled'    => true,
                                            'show_count'  => false,
                                            'searchable'  => true,
                                            'data_attrs'  => array(
                                                'filter-type' => 'model',
                                            ),
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('calendar'); ?> <?php esc_html_e( 'Year', 'bricks-child' ); ?></label>
                                        <?php
                                        $year_options = array();
                                        for ($year = 2025; $year >= 1948; $year--) {
                                            $year_options[] = array('value' => $year, 'label' => $year);
                                        }
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-year',
                                            'name'        => 'year',
                                            'placeholder' => __('Select Year', 'bricks-child'),
                                            'options'     => $year_options,
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                </div>

                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="mileage"><?php echo get_svg_icon('road'); ?> <?php esc_html_e( 'Mileage', 'bricks-child' ); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="mileage" name="mileage" class="form-control" required placeholder="E.g '180,000'">
                                            <span class="input-suffix">km</span>
                                        </div>
                                    </div>
                                    <div class="form-third">
                                        <label for="price"><?php echo get_svg_icon('euro-sign'); ?> <?php esc_html_e( 'Price', 'bricks-child' ); ?></label>
                                        <input type="text" id="price" name="price" class="form-control" required placeholder="E.g '10,000'">
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('circle-check'); ?> <?php esc_html_e( 'Availability', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-availability',
                                            'name'        => 'availability',
                                            'placeholder' => __('Select Availability', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'In Stock', 'label' => __('In Stock', 'bricks-child')),
                                                array('value' => 'In Transit', 'label' => __('In Transit', 'bricks-child')),
                                            ),
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                </div>

                                <div class="form-row" id="location-row">
                                    <label for="location"><?php echo get_svg_icon('location-dot'); ?> <?php esc_html_e( 'Location', 'bricks-child' ); ?></label>

                                    <!-- Saved Locations Dropdown -->
                                    <div class="saved-locations-wrapper" id="saved-locations-wrapper" style="display: none; margin-bottom: 10px;">
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'saved-locations',
                                            'name'        => 'saved_location_select',
                                            'placeholder' => __('Select from saved locations', 'bricks-child'),
                                            'options'     => array(), // Will be populated via JavaScript
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                            'data_attrs'  => array(
                                                'filter-type' => 'saved-location',
                                            ),
                                        ));
                                        ?>
                                    </div>

                                    <div class="location-input-wrapper">
                                        <input type="text" id="location" name="location" class="form-control" readonly>
                                        <button type="button" class="btn btn-secondary choose-location-btn">Choose Location <?php echo get_svg_icon('map-location-dot'); ?></button>
                                    </div>
                                </div>


                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('engine'); ?> <?php esc_html_e( 'Engine Capacity', 'bricks-child' ); ?></label>
                                        <?php
                                        $engine_options = array();
                                        for ($capacity = 0.4; $capacity <= 12.0; $capacity += 0.1) {
                                            $formatted_capacity = number_format($capacity, 1);
                                            $engine_options[] = array('value' => $formatted_capacity, 'label' => $formatted_capacity . 'L');
                                        }
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-engine-capacity',
                                            'name'        => 'engine_capacity',
                                            'placeholder' => __('Select Engine Capacity', 'bricks-child'),
                                            'options'     => $engine_options,
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('gas-pump'); ?> <?php esc_html_e( 'Fuel Type', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-fuel-type',
                                            'name'        => 'fuel_type',
                                            'placeholder' => __('Select Fuel Type', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'Petrol', 'label' => __('Petrol', 'bricks-child')),
                                                array('value' => 'Diesel', 'label' => __('Diesel', 'bricks-child')),
                                                array('value' => 'Electric', 'label' => __('Electric', 'bricks-child')),
                                                array('value' => 'Petrol hybrid', 'label' => __('Petrol hybrid', 'bricks-child')),
                                                array('value' => 'Diesel hybrid', 'label' => __('Diesel hybrid', 'bricks-child')),
                                                array('value' => 'Plug-in petrol', 'label' => __('Plug-in petrol', 'bricks-child')),
                                                array('value' => 'Plug-in diesel', 'label' => __('Plug-in diesel', 'bricks-child')),
                                                array('value' => 'Bi Fuel', 'label' => __('Bi Fuel', 'bricks-child')),
                                                array('value' => 'Hydrogen', 'label' => __('Hydrogen', 'bricks-child')),
                                                array('value' => 'Natural Gas', 'label' => __('Natural Gas', 'bricks-child')),
                                            ),
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                            'data_attrs'  => array('filter-type' => 'fuel_type'),
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('car-chassis'); ?> <?php esc_html_e( 'Transmission', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-transmission',
                                            'name'        => 'transmission',
                                            'placeholder' => __('Select Transmission', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'Automatic', 'label' => __('Automatic', 'bricks-child')),
                                                array('value' => 'Manual', 'label' => __('Manual', 'bricks-child')),
                                            ),
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label><?php echo get_svg_icon('car-side'); ?> <?php esc_html_e( 'Body Type', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-body-type',
                                            'name'        => 'body_type',
                                            'placeholder' => __('Select Body Type', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'Hatchback', 'label' => __('Hatchback', 'bricks-child')),
                                                array('value' => 'Saloon', 'label' => __('Saloon', 'bricks-child')),
                                                array('value' => 'Coupe', 'label' => __('Coupe', 'bricks-child')),
                                                array('value' => 'Convertible', 'label' => __('Convertible', 'bricks-child')),
                                                array('value' => 'Estate', 'label' => __('Estate', 'bricks-child')),
                                                array('value' => 'SUV', 'label' => __('SUV', 'bricks-child')),
                                                array('value' => 'MPV', 'label' => __('MPV', 'bricks-child')),
                                                array('value' => 'Pickup', 'label' => __('Pickup', 'bricks-child')),
                                                array('value' => 'Camper', 'label' => __('Camper', 'bricks-child')),
                                                array('value' => 'Minibus', 'label' => __('Minibus', 'bricks-child')),
                                                array('value' => 'Limousine', 'label' => __('Limousine', 'bricks-child')),
                                                array('value' => 'Car Derived Van', 'label' => __('Car Derived Van', 'bricks-child')),
                                                array('value' => 'Combi Van', 'label' => __('Combi Van', 'bricks-child')),
                                                array('value' => 'Panel Van', 'label' => __('Panel Van', 'bricks-child')),
                                                array('value' => 'Window Van', 'label' => __('Window Van', 'bricks-child')),
                                            ),
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>

                                    <div class="form-half">
                                        <label><?php echo get_svg_icon('paintbrush'); ?> <?php esc_html_e( 'Exterior Color', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-exterior-color',
                                            'name'        => 'exterior_color',
                                            'placeholder' => __('Select Exterior Color', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'Black', 'label' => __('Black', 'bricks-child')),
                                                array('value' => 'White', 'label' => __('White', 'bricks-child')),
                                                array('value' => 'Silver', 'label' => __('Silver', 'bricks-child')),
                                                array('value' => 'Gray', 'label' => __('Gray', 'bricks-child')),
                                                array('value' => 'Red', 'label' => __('Red', 'bricks-child')),
                                                array('value' => 'Blue', 'label' => __('Blue', 'bricks-child')),
                                                array('value' => 'Green', 'label' => __('Green', 'bricks-child')),
                                                array('value' => 'Yellow', 'label' => __('Yellow', 'bricks-child')),
                                                array('value' => 'Brown', 'label' => __('Brown', 'bricks-child')),
                                                array('value' => 'Beige', 'label' => __('Beige', 'bricks-child')),
                                                array('value' => 'Orange', 'label' => __('Orange', 'bricks-child')),
                                                array('value' => 'Purple', 'label' => __('Purple', 'bricks-child')),
                                                array('value' => 'Gold', 'label' => __('Gold', 'bricks-child')),
                                                array('value' => 'Bronze', 'label' => __('Bronze', 'bricks-child')),
                                            ),
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                </div>
                                
                            </div> 

                            <div class="form-section engine-performance-section input-wrapper collapsible-section collapsed">
                                <div class="section-header" role="button" tabindex="0" aria-expanded="false">
                                    <h2><?php echo get_svg_icon('gauge-high'); ?> <?php esc_html_e( 'More Details (Optional) - Recommended', 'bricks-child' ); ?></h2>
                                    <span class="collapse-arrow"><?php echo get_svg_icon('chevron-down'); ?></span>
                                </div>
                                <div class="section-content">

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label><?php echo get_svg_icon('tire'); ?> <?php esc_html_e( 'Drive Type (Optional)', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-drive-type',
                                            'name'        => 'drive_type',
                                            'placeholder' => __('Select Drive Type', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'Front-Wheel Drive', 'label' => __('Front-Wheel Drive', 'bricks-child')),
                                                array('value' => 'Rear-Wheel Drive', 'label' => __('Rear-Wheel Drive', 'bricks-child')),
                                                array('value' => 'All-Wheel Drive', 'label' => __('All-Wheel Drive', 'bricks-child')),
                                                array('value' => '4-Wheel Drive', 'label' => __('4-Wheel Drive', 'bricks-child')),
                                            ),
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-half">
                                        <label for="hp"><?php echo get_svg_icon('gauge-high'); ?> <?php esc_html_e( 'Horsepower (Optional)', 'bricks-child' ); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="hp" name="hp" class="form-control" min="0" step="1" placeholder="E.g '100'">
                                            <span class="input-suffix">hp</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('car-door'); ?> <?php esc_html_e( 'Number of Doors (Optional)', 'bricks-child' ); ?></label>
                                        <?php
                                        $door_options = array();
                                        foreach (array(0, 2, 3, 4, 5, 6, 7) as $doors) {
                                            $door_options[] = array('value' => $doors, 'label' => $doors);
                                        }
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-doors',
                                            'name'        => 'number_of_doors',
                                            'placeholder' => __('Select Number of Doors', 'bricks-child'),
                                            'options'     => $door_options,
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('car-seat'); ?> <?php esc_html_e( 'Number of Seats (Optional)', 'bricks-child' ); ?></label>
                                        <?php
                                        $seat_options = array();
                                        foreach (array(1, 2, 3, 4, 5, 6, 7, 8) as $seats) {
                                            $seat_options[] = array('value' => $seats, 'label' => $seats);
                                        }
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-seats',
                                            'name'        => 'number_of_seats',
                                            'placeholder' => __('Select Number of Seats', 'bricks-child'),
                                            'options'     => $seat_options,
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('palette'); ?> <?php esc_html_e( 'Interior Color (Optional)', 'bricks-child' ); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-interior-color',
                                            'name'        => 'interior_color',
                                            'placeholder' => __('Select Interior Color', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'Black', 'label' => __('Black', 'bricks-child')),
                                                array('value' => 'Gray', 'label' => __('Gray', 'bricks-child')),
                                                array('value' => 'Beige', 'label' => __('Beige', 'bricks-child')),
                                                array('value' => 'Brown', 'label' => __('Brown', 'bricks-child')),
                                                array('value' => 'White', 'label' => __('White', 'bricks-child')),
                                                array('value' => 'Red', 'label' => __('Red', 'bricks-child')),
                                                array('value' => 'Blue', 'label' => __('Blue', 'bricks-child')),
                                                array('value' => 'Tan', 'label' => __('Tan', 'bricks-child')),
                                                array('value' => 'Cream', 'label' => __('Cream', 'bricks-child')),
                                            ),
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                </div>


                                </div><!-- .section-content -->
                            </div>

                            <div class="form-section mot-section input-wrapper collapsible-section collapsed">
                                <div class="section-header" role="button" tabindex="0" aria-expanded="false">
                                    <h2><?php echo get_svg_icon('clipboard-list'); ?> <?php esc_html_e( 'History & extras (Optional)', 'bricks-child' ); ?></h2>
                                    <span class="collapse-arrow"><?php echo get_svg_icon('chevron-down'); ?></span>
                                </div>
                                <div class="section-content">
                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label><?php echo get_svg_icon('clipboard-check'); ?> <?php esc_html_e( 'MOT Status (Optional)', 'bricks-child' ); ?></label>
                                        <?php
                                        // Build MOT options
                                        $mot_options = array();
                                        $mot_options[] = array('value' => 'Expired', 'label' => __('Expired', 'bricks-child'));

                                        // Get current date
                                        $current_date = new DateTime();
                                        $current_date->modify('first day of this month');
                                        $end_date = new DateTime();
                                        $end_date->modify('+2 years');
                                        $end_date->modify('last day of this month');

                                        while ($current_date <= $end_date) {
                                            $mot_options[] = array(
                                                'value' => $current_date->format('Y-m'),
                                                'label' => $current_date->format('F Y')
                                            );
                                            $current_date->modify('+1 month');
                                        }

                                        car_filter_render_dropdown(array(
                                            'id'          => 'add-listing-mot',
                                            'name'        => 'motuntil',
                                            'placeholder' => __('Select MOT Status', 'bricks-child'),
                                            'options'     => $mot_options,
                                            'selected'    => '',
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-half">
                                        <label for="numowners"><?php echo get_svg_icon('users'); ?> <?php esc_html_e( 'Number of Owners (Optional)', 'bricks-child' ); ?></label>
                                        <input type="number" id="numowners" name="numowners" class="form-control" min="1" max="99" placeholder="E.g '2'">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="checkbox-field">
                                        <input type="checkbox" id="isantique" name="isantique" value="1">
                                        <label for="isantique"><?php esc_html_e( 'Registered as an Antique', 'bricks-child' ); ?></label>
                                    </div>
                                </div>

                                <div class="form-row" id="vehicle-history-row">
                                    <label><?php echo get_svg_icon('clock-rotate-left'); ?> <?php esc_html_e( 'Vehicle History (Optional)', 'bricks-child' ); ?></label>
                                    <div class="vehicle-history-grid">
                                        <?php
                                        $vehicle_history_options = array(
                                            'no_accidents' => 'No Accidents',
                                            'minor_accidents' => 'Minor Accidents',
                                            'major_accidents' => 'Major Accidents',
                                            'regular_maintenance' => 'Regular Maintenance',
                                            'engine_overhaul' => 'Engine Overhaul',
                                            'transmission_replacement' => 'Transmission Replacement',
                                            'repainted' => 'Repainted',
                                            'bodywork_repair' => 'Bodywork Repair',
                                            'rust_treatment' => 'Rust Treatment',
                                            'no_modifications' => 'No Modifications',
                                            'performance_upgrades' => 'Performance Upgrades',
                                            'cosmetic_modifications' => 'Cosmetic Modifications',
                                            'flood_damage' => 'Flood Damage',
                                            'fire_damage' => 'Fire Damage',
                                            'hail_damage' => 'Hail Damage',
                                            'clear_title' => 'Clear Title',
                                            'no_known_issues' => 'No Known Issues',
                                            'odometer_replacement' => 'Odometer Replacement'
                                        );
                                        foreach ($vehicle_history_options as $value => $label) {
                                            echo '<div class="vehicle-history-option">';
                                            echo '<input type="checkbox" id="vehiclehistory_' . esc_attr($value) . '" name="vehiclehistory[]" value="' . esc_attr($value) . '">';
                                            echo '<label for="vehiclehistory_' . esc_attr($value) . '">' . esc_html($label) . '</label>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <label><?php echo get_svg_icon('plus'); ?> <?php esc_html_e( 'Extras', 'bricks-child' ); ?></label>
                                    <div class="extras-grid">
                                        <?php
                                        $extras_options = array(
                                            'alloy_wheels' => 'Alloy Wheels',
                                            'cruise_control' => 'Cruise Control',
                                            'disabled_accessible' => 'Disabled Accessible',
                                            'keyless_start' => 'Keyless Start',
                                            'rear_view_camera' => 'Rear View Camera',
                                            'start_stop' => 'Start/Stop',
                                            'sunroof' => 'Sunroof',
                                            'heated_seats' => 'Heated Seats',
                                            'android_auto' => 'Android Auto',
                                            'apple_carplay' => 'Apple CarPlay',
                                            'folding_mirrors' => 'Folding Mirrors',
                                            'leather_seats' => 'Leather Seats',
                                            'panoramic_roof' => 'Panoramic Roof',
                                            'parking_sensors' => 'Parking Sensors',
                                            'camera_360' => '360° Camera',
                                            'adaptive_cruise_control' => 'Adaptive Cruise Control',
                                            'blind_spot_mirror' => 'Blind Spot Mirror',
                                            'lane_assist' => 'Lane Assist',
                                            'power_tailgate' => 'Power Tailgate'
                                        );
                                        foreach ($extras_options as $value => $label) {
                                            echo '<div class="extra-option">';
                                            echo '<input type="checkbox" id="extra_' . esc_attr($value) . '" name="extras[]" value="' . esc_attr($value) . '">';
                                            echo '<label for="extra_' . esc_attr($value) . '">' . esc_html($label) . '</label>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>

                                </div><!-- .section-content -->
                            </div>
                        </div>
                    </div>

                    <div class="add-listing-description-section input-wrapper collapsible-section collapsed">
                        <div class="section-header" role="button" tabindex="0" aria-expanded="false">
                            <h2><?php echo get_svg_icon('align-left'); ?> <?php esc_html_e( 'Description', 'bricks-child' ); ?></h2>
                            <span class="collapse-arrow"><?php echo get_svg_icon('chevron-down'); ?></span>
                        </div>
                        <div class="section-content">
                        <p class="description-guidelines-green"><?php esc_html_e( 'Focus on condition, upgrades, or unique features.', 'bricks-child' ); ?></p>

                        <div class="form-row">
                            <textarea id="description" name="description" class="form-control" rows="5" placeholder="Enter your description here..."></textarea>
                        </div>
                        </div><!-- .section-content -->
                    </div>

                    <div class="form-row">
                        <button type="submit" class="btn btn-primary-gradient btn-lg"><?php esc_html_e( 'Submit Listing', 'bricks-child' ); ?></button>
                    </div>
                </form>
                <?php
            }
        } else {
            $login_url = wp_login_url( get_permalink() );
            $register_page = get_page_by_path( 'register' ); // Assuming you have a 'register' page

            echo '<div class="login-required-message">';
            echo '<h1>' . esc_html__( 'Please Log in to Submit a Car Listing', 'bricks-child' ) . '</h1>';
            echo '<p>';
            echo '<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log In', 'bricks-child' ) . '</a>';

            if ( $register_page ) {
                echo ' | <a href="' . esc_url( get_permalink( $register_page->ID ) ) . '">' . esc_html__( 'Register', 'bricks-child' ) . '</a>';
            }
            echo '</p>';
            echo '</div>';
        }
        ?>
    </div>
</div>

<?php get_footer(); ?>