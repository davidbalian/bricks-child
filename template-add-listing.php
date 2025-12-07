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

// Get Makes data using PHP before the form
$add_listing_makes = [];

// Get all top-level terms (makes) from car_make taxonomy
$make_terms = get_terms(array(
    'taxonomy' => 'car_make',
    'hide_empty' => false,
    'parent' => 0,
    'orderby' => 'name',
    'order' => 'ASC'
));

if (!is_wp_error($make_terms) && !empty($make_terms)) {
    foreach ($make_terms as $make_term) {
        // Get all child terms (models) for this make
        $model_terms = get_terms(array(
            'taxonomy' => 'car_make',
            'hide_empty' => false,
            'parent' => $make_term->term_id,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        if (!is_wp_error($model_terms)) {
            $models = array();
            foreach ($model_terms as $model_term) {
                $models[] = $model_term->name;
            }
            $add_listing_makes[$make_term->name] = $models;
        }
    }
}

// Ensure jQuery is loaded
wp_enqueue_script('jquery');

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
                    <p><?php esc_html_e( 'To receive email notifications about views and clicks on your listing, verify your email from your account page if you haven\'t already.', 'bricks-child' ); ?></p>
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
                        <p class="image-upload-info"><?php esc_html_e( 'Hold CTRL to choose several photos. Minimum 2 images per listing. Maximum 25 images per listing. Maximum file size is 5MB, the formats are .jpg, .jpeg, .jfif, .png, .gif, .webp. Images are automatically optimized and converted to WebP for faster loading.', 'bricks-child' ); ?></p>
                    </div>  

                    <div class="add-listing-main-row">
                        <div class="add-listing-main-info-column">
                            <div class="form-section basic-details-section input-wrapper">
                                <h2><?php echo get_svg_icon('circle-info'); ?> <?php esc_html_e( 'Basic Details', 'bricks-child' ); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="make"><?php echo get_svg_icon('car-side'); ?> <?php esc_html_e( 'Make', 'bricks-child' ); ?></label>
                                        <select id="make" name="make" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Make', 'bricks-child' ); ?></option>
                                            <?php
                                            foreach ( $add_listing_makes as $make_name => $models ) {
                                                echo '<option value="' . esc_attr( $make_name ) . '">' . esc_html( $make_name ) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="model"><?php echo get_svg_icon('car'); ?> <?php esc_html_e( 'Model', 'bricks-child' ); ?></label>
                                        <select id="model" name="model" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Model', 'bricks-child' ); ?></option>
                                        </select>
                                    </div>
                                    <!-- variant field removed -->
                                </div>

                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="year"><?php echo get_svg_icon('calendar'); ?> <?php esc_html_e( 'Year', 'bricks-child' ); ?></label>
                                        <select id="year" name="year" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Year', 'bricks-child' ); ?></option>
                                            <?php
                                            for ($year = 2025; $year >= 1948; $year--) {
                                                echo '<option value="' . esc_attr($year) . '">' . esc_html($year) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
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
                                </div>

                                <div class="form-row" id="location-row">
                                    <label for="location"><?php echo get_svg_icon('location-dot'); ?> <?php esc_html_e( 'Location', 'bricks-child' ); ?></label>
                                    <div class="location-input-wrapper">
                                        <input type="text" id="location" name="location" class="form-control" required readonly>
                                        <button type="button" class="btn btn-secondary choose-location-btn">Choose Location <?php echo get_svg_icon('map-location-dot'); ?></button>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <label for="availability"><?php echo get_svg_icon('circle-check'); ?> <?php esc_html_e( 'Availability', 'bricks-child' ); ?></label>
                                    <select id="availability" name="availability" class="form-control" required>
                                        <option value=""><?php esc_html_e( 'Select Availability', 'bricks-child' ); ?></option>
                                        <option value="In Stock"><?php esc_html_e( 'In Stock', 'bricks-child' ); ?></option>
                                        <option value="In Transit"><?php esc_html_e( 'In Transit', 'bricks-child' ); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-section engine-performance-section input-wrapper">
                                <h2><?php echo get_svg_icon('gauge-high'); ?> <?php esc_html_e( 'Engine & Performance', 'bricks-child' ); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="engine_capacity"><?php echo get_svg_icon('engine'); ?> <?php esc_html_e( 'Engine Capacity', 'bricks-child' ); ?></label>
                                        <select id="engine_capacity" name="engine_capacity" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Engine Capacity', 'bricks-child' ); ?></option>
                                            <?php
                                            for ($capacity = 0.4; $capacity <= 12.0; $capacity += 0.1) {
                                                $formatted_capacity = number_format($capacity, 1);
                                                echo '<option value="' . esc_attr($formatted_capacity) . '">' . esc_html($formatted_capacity) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="fuel_type"><?php echo get_svg_icon('gas-pump'); ?> <?php esc_html_e( 'Fuel Type', 'bricks-child' ); ?></label>
                                        <select id="fuel_type" name="fuel_type" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Fuel Type', 'bricks-child' ); ?></option>
                                            <option value="Petrol"><?php esc_html_e( 'Petrol', 'bricks-child' ); ?></option>
                                            <option value="Diesel"><?php esc_html_e( 'Diesel', 'bricks-child' ); ?></option>
                                            <option value="Electric"><?php esc_html_e( 'Electric', 'bricks-child' ); ?></option>
                                            <option value="Petrol hybrid"><?php esc_html_e( 'Petrol hybrid', 'bricks-child' ); ?></option>
                                            <option value="Diesel hybrid"><?php esc_html_e( 'Diesel hybrid', 'bricks-child' ); ?></option>
                                            <option value="Plug-in petrol"><?php esc_html_e( 'Plug-in petrol', 'bricks-child' ); ?></option>
                                            <option value="Plug-in diesel"><?php esc_html_e( 'Plug-in diesel', 'bricks-child' ); ?></option>
                                            <option value="Bi Fuel"><?php esc_html_e( 'Bi Fuel', 'bricks-child' ); ?></option>
                                            <option value="Hydrogen"><?php esc_html_e( 'Hydrogen', 'bricks-child' ); ?></option>
                                            <option value="Natural Gas"><?php esc_html_e( 'Natural Gas', 'bricks-child' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="transmission"><?php echo get_svg_icon('car-chassis'); ?> <?php esc_html_e( 'Transmission', 'bricks-child' ); ?></label>
                                        <select id="transmission" name="transmission" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Transmission', 'bricks-child' ); ?></option>
                                            <option value="Automatic"><?php esc_html_e( 'Automatic', 'bricks-child' ); ?></option>
                                            <option value="Manual"><?php esc_html_e( 'Manual', 'bricks-child' ); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label for="drive_type"><?php echo get_svg_icon('tire'); ?> <?php esc_html_e( 'Drive Type', 'bricks-child' ); ?></label>
                                        <select id="drive_type" name="drive_type" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Drive Type', 'bricks-child' ); ?></option>
                                            <option value="Front-Wheel Drive"><?php esc_html_e( 'Front-Wheel Drive', 'bricks-child' ); ?></option>
                                            <option value="Rear-Wheel Drive"><?php esc_html_e( 'Rear-Wheel Drive', 'bricks-child' ); ?></option>
                                            <option value="All-Wheel Drive"><?php esc_html_e( 'All-Wheel Drive', 'bricks-child' ); ?></option>
                                            <option value="4-Wheel Drive"><?php esc_html_e( '4-Wheel Drive', 'bricks-child' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-half">
                                        <label for="hp"><?php echo get_svg_icon('gauge-high'); ?> <?php esc_html_e( 'Horsepower (Optional)', 'bricks-child' ); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="hp" name="hp" class="form-control" min="0" step="1" placeholder="E.g '100'">
                                            <span class="input-suffix">hp</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section body-design-section input-wrapper">
                                <h2><?php echo get_svg_icon('car'); ?> <?php esc_html_e( 'Body & Design', 'bricks-child' ); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="body_type"><?php echo get_svg_icon('car-side'); ?> <?php esc_html_e( 'Body Type', 'bricks-child' ); ?></label>
                                        <select id="body_type" name="body_type" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Body Type', 'bricks-child' ); ?></option>
                                            <option value="Hatchback"><?php esc_html_e( 'Hatchback', 'bricks-child' ); ?></option>
                                            <option value="Saloon"><?php esc_html_e( 'Saloon', 'bricks-child' ); ?></option>
                                            <option value="Coupe"><?php esc_html_e( 'Coupe', 'bricks-child' ); ?></option>
                                            <option value="Convertible"><?php esc_html_e( 'Convertible', 'bricks-child' ); ?></option>
                                            <option value="Estate"><?php esc_html_e( 'Estate', 'bricks-child' ); ?></option>
                                            <option value="SUV"><?php esc_html_e( 'SUV', 'bricks-child' ); ?></option>
                                            <option value="MPV"><?php esc_html_e( 'MPV', 'bricks-child' ); ?></option>
                                            <option value="Pickup"><?php esc_html_e( 'Pickup', 'bricks-child' ); ?></option>
                                            <option value="Camper"><?php esc_html_e( 'Camper', 'bricks-child' ); ?></option>
                                            <option value="Minibus"><?php esc_html_e( 'Minibus', 'bricks-child' ); ?></option>
                                            <option value="Limousine"><?php esc_html_e( 'Limousine', 'bricks-child' ); ?></option>
                                            <option value="Car Derived Van"><?php esc_html_e( 'Car Derived Van', 'bricks-child' ); ?></option>
                                            <option value="Combi Van"><?php esc_html_e( 'Combi Van', 'bricks-child' ); ?></option>
                                            <option value="Panel Van"><?php esc_html_e( 'Panel Van', 'bricks-child' ); ?></option>
                                            <option value="Window Van"><?php esc_html_e( 'Window Van', 'bricks-child' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="number_of_doors"><?php echo get_svg_icon('car-door'); ?> <?php esc_html_e( 'Number of Doors', 'bricks-child' ); ?></label>
                                        <select id="number_of_doors" name="number_of_doors" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Number of Doors', 'bricks-child' ); ?></option>
                                            <?php
                                            $door_options = array(0, 2, 3, 4, 5, 6, 7);
                                            foreach ($door_options as $doors) {
                                                echo '<option value="' . esc_attr($doors) . '">' . esc_html($doors) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="number_of_seats"><?php echo get_svg_icon('car-seat'); ?> <?php esc_html_e( 'Number of Seats', 'bricks-child' ); ?></label>
                                        <select id="number_of_seats" name="number_of_seats" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Number of Seats', 'bricks-child' ); ?></option>
                                            <?php
                                            $seat_options = array(1, 2, 3, 4, 5, 6, 7, 8);
                                            foreach ($seat_options as $seats) {
                                                echo '<option value="' . esc_attr($seats) . '">' . esc_html($seats) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="exterior_color"><?php echo get_svg_icon('paintbrush'); ?> <?php esc_html_e( 'Exterior Color', 'bricks-child' ); ?></label>
                                        <select id="exterior_color" name="exterior_color" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Exterior Color', 'bricks-child' ); ?></option>
                                            <option value="Black"><?php esc_html_e( 'Black', 'bricks-child' ); ?></option>
                                            <option value="White"><?php esc_html_e( 'White', 'bricks-child' ); ?></option>
                                            <option value="Silver"><?php esc_html_e( 'Silver', 'bricks-child' ); ?></option>
                                            <option value="Gray"><?php esc_html_e( 'Gray', 'bricks-child' ); ?></option>
                                            <option value="Red"><?php esc_html_e( 'Red', 'bricks-child' ); ?></option>
                                            <option value="Blue"><?php esc_html_e( 'Blue', 'bricks-child' ); ?></option>
                                            <option value="Green"><?php esc_html_e( 'Green', 'bricks-child' ); ?></option>
                                            <option value="Yellow"><?php esc_html_e( 'Yellow', 'bricks-child' ); ?></option>
                                            <option value="Brown"><?php esc_html_e( 'Brown', 'bricks-child' ); ?></option>
                                            <option value="Beige"><?php esc_html_e( 'Beige', 'bricks-child' ); ?></option>
                                            <option value="Orange"><?php esc_html_e( 'Orange', 'bricks-child' ); ?></option>
                                            <option value="Purple"><?php esc_html_e( 'Purple', 'bricks-child' ); ?></option>
                                            <option value="Gold"><?php esc_html_e( 'Gold', 'bricks-child' ); ?></option>
                                            <option value="Bronze"><?php esc_html_e( 'Bronze', 'bricks-child' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="interior_color"><?php echo get_svg_icon('palette'); ?> <?php esc_html_e( 'Interior Color', 'bricks-child' ); ?></label>
                                        <select id="interior_color" name="interior_color" class="form-control" required>
                                            <option value=""><?php esc_html_e( 'Select Interior Color', 'bricks-child' ); ?></option>
                                            <option value="Black"><?php esc_html_e( 'Black', 'bricks-child' ); ?></option>
                                            <option value="Gray"><?php esc_html_e( 'Gray', 'bricks-child' ); ?></option>
                                            <option value="Beige"><?php esc_html_e( 'Beige', 'bricks-child' ); ?></option>
                                            <option value="Brown"><?php esc_html_e( 'Brown', 'bricks-child' ); ?></option>
                                            <option value="White"><?php esc_html_e( 'White', 'bricks-child' ); ?></option>
                                            <option value="Red"><?php esc_html_e( 'Red', 'bricks-child' ); ?></option>
                                            <option value="Blue"><?php esc_html_e( 'Blue', 'bricks-child' ); ?></option>
                                            <option value="Tan"><?php esc_html_e( 'Tan', 'bricks-child' ); ?></option>
                                            <option value="Cream"><?php esc_html_e( 'Cream', 'bricks-child' ); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section mot-section input-wrapper">
                                <h2><?php echo get_svg_icon('clipboard-list'); ?> <?php esc_html_e( 'Registration & Background Info', 'bricks-child' ); ?></h2>
                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label for="motuntil"><?php echo get_svg_icon('clipboard-check'); ?> <?php esc_html_e( 'MOT Status (Optional)', 'bricks-child' ); ?></label>
                                        <select id="motuntil" name="motuntil" class="form-control">
                                            <option value=""><?php esc_html_e( 'Select MOT Status', 'bricks-child' ); ?></option>
                                            <option value="Expired"><?php esc_html_e( 'Expired', 'bricks-child' ); ?></option>
                                            <?php
                                            // Get current date
                                            $current_date = new DateTime();
                                            // Set to first day of current month
                                            $current_date->modify('first day of this month');
                                            // Create end date (2 years from now)
                                            $end_date = new DateTime();
                                            $end_date->modify('+2 years');
                                            $end_date->modify('last day of this month');

                                            // Generate options
                                            while ($current_date <= $end_date) {
                                                $value = $current_date->format('Y-m');
                                                $display = $current_date->format('F Y');
                                                echo '<option value="' . esc_attr($value) . '">' . esc_html($display) . '</option>';
                                                $current_date->modify('+1 month');
                                            }
                                            ?>
                                        </select>
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
                            </div>

                            <div class="form-section extras-section input-wrapper">
                                <h2><?php echo get_svg_icon('plus'); ?> <?php esc_html_e( 'Extras (Optional)', 'bricks-child' ); ?></h2>
                                <div class="form-row">
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
                            </div>
                        </div>
                    </div>

                    <div class="add-listing-description-section input-wrapper">
                        <h2><?php echo get_svg_icon('align-left'); ?> <?php esc_html_e( 'Description', 'bricks-child' ); ?></h2>
                        <p class="description-guidelines-green"><?php esc_html_e( 'Focus on condition, upgrades, or unique features.', 'bricks-child' ); ?></p>
                        
                        <div class="form-row">
                            <textarea id="description" name="description" class="form-control" rows="5" placeholder="Enter your description here..."></textarea>
                        </div>
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