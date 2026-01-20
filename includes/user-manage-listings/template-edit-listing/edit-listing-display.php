<?php
/**
 * Edit Listing Display - Main Form HTML and PHP Logic
 * Separated from template-edit-listing.php for better organization
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="page-edit-listing">
    <div class="bricks-container">
        <div class="bricks-content">
            <?php
            if (isset($_GET['listing_error'])) {
                ?>
                <div class="listing-error-message">
                    <h2><?php esc_html_e('Submission Error', 'bricks-child'); ?></h2>
                    <p><?php esc_html_e('There was a problem with your submission. Please check all fields and try again.', 'bricks-child'); ?></p>
                </div>
                <?php
            }
            ?>
            <h1><?php esc_html_e('Edit Car Listing', 'bricks-child'); ?></h1>
            
            <form id="edit-car-listing-form" class="car-listing-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('edit_car_listing_nonce', 'edit_car_listing_nonce'); ?>
                <input type="hidden" name="action" value="edit_car_listing">
                <input type="hidden" name="car_id" value="<?php echo esc_attr($car_id); ?>">
                <input type="hidden" id="async_session_id" name="async_session_id" value="">
                <!-- This hidden array will be populated by JS to reflect the final image order -->
                <div id="image-order-container"></div>

                <div class="add-listing-images-section input-wrapper">
                    <h2><?php esc_html_e('Upload Images', 'bricks-child'); ?></h2>
                    <p class="image-upload-info"><?php esc_html_e('Hold CTRL to choose several photos. Minimum 2 images per listing. Maximum 25 images per listing. Maximum file size is 12MB, the formats are .jpg, .jpeg, .png, .gif, .webp.', 'bricks-child'); ?></p>
                    <p class="image-upload-note"><?php esc_html_e('Note: ads with good photos get more attention', 'bricks-child'); ?></p>
                    <div class="image-upload-container">
                        <div class="file-upload-area" id="file-upload-area" role="button" tabindex="0">
                            <div class="upload-message">
                                <?php echo get_svg_icon('cloud-arrow-up'); ?>
                                <p><?php esc_html_e('Drag & Drop Images Here', 'bricks-child'); ?></p>
                                <p class="small"><?php esc_html_e('or click to select files', 'bricks-child'); ?></p>
                            </div>
                        </div>
                        <input type="file" id="car_images" name="car_images[]" multiple accept="image/jpeg,image/jfif,image/jpg,image/png,image/gif,image/webp,.jfif,.jpe" style="display: none;">
                        <div id="image-preview" class="image-preview">
                            <?php
                            if (!empty($all_images)) {
                                foreach ($all_images as $image_id) {
                                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                    if ($image_url) {
                                        ?>
                                        <div class="image-preview-item">
                                            <img src="<?php echo esc_url($image_url); ?>" alt="Car image">
                                            <button type="button" class="remove-image" data-image-id="<?php echo esc_attr($image_id); ?>">&times;</button>
                                        </div>
                                        <?php
                                    }
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="add-listing-main-row">
                    <div class="add-listing-main-info-column">
                            <div class="form-section basic-details-section input-wrapper">
                                <h2><?php esc_html_e('Basic Details', 'bricks-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="make"><?php echo get_svg_icon('car-side'); ?> <?php esc_html_e('Make', 'bricks-child'); ?></label>
                                        <input type="text" id="make" name="make" class="form-control" value="<?php echo esc_attr($make); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="model"><?php echo get_svg_icon('car'); ?> <?php esc_html_e('Model', 'bricks-child'); ?></label>
                                        <input type="text" id="model" name="model" class="form-control" value="<?php echo esc_attr($model); ?>" readonly>
                                    </div>
                                    <!-- variant field removed -->
                                </div>

                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('calendar'); ?> <?php esc_html_e('Year', 'bricks-child'); ?></label>
                                        <?php
                                        $year_options = array();
                                        for ($y = 2025; $y >= 1948; $y--) {
                                            $year_options[] = array('value' => $y, 'label' => $y);
                                        }
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-year',
                                            'name'        => 'year',
                                            'placeholder' => __('Select Year', 'bricks-child'),
                                            'options'     => $year_options,
                                            'selected'    => $year,
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label for="mileage"><?php echo get_svg_icon('road'); ?> <?php esc_html_e('Mileage', 'bricks-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="mileage" name="mileage" class="form-control" value="<?php echo esc_attr($mileage); ?>" required>
                                            <span class="input-suffix">km</span>
                                        </div>
                                    </div>
                                    <div class="form-third">
                                        <label for="price"><?php echo get_svg_icon('euro-sign'); ?> <?php esc_html_e('Price', 'bricks-child'); ?></label>
                                        <input type="text" id="price" name="price" class="form-control" value="<?php echo esc_attr($price); ?>" required>
                                    </div>
                                </div>

                                <div class="form-row" id="location-row">
                                    <label for="location"><?php echo get_svg_icon('location-dot'); ?> <?php esc_html_e('Location', 'bricks-child'); ?></label>

                                    <div class="location-selector-wrapper">
                                        <!-- Saved Locations Dropdown -->
                                        <div class="saved-locations-container<?php echo !empty($location) ? ' has-location' : ''; ?>" id="saved-locations-wrapper">
                                            <?php
                                            car_filter_render_dropdown(array(
                                                'id'          => 'saved-locations',
                                                'name'        => 'saved_location_select',
                                                'placeholder' => __('Recently used locations', 'bricks-child'),
                                                'options'     => array(),
                                                'selected'    => '',
                                                'show_count'  => false,
                                                'searchable'  => true,
                                                'data_attrs'  => array(
                                                    'filter-type' => 'saved-location',
                                                ),
                                            ));
                                            ?>
                                            <button type="button" class="clear-location-btn" id="clear-location-btn" style="<?php echo !empty($location) ? '' : 'display: none;'; ?>" title="Clear location">
                                                <?php echo get_svg_icon('xmark'); ?>
                                            </button>
                                        </div>

                                        <span class="location-or-separator">OR</span>

                                        <button type="button" class="btn btn-secondary choose-location-btn">Choose Location <?php echo get_svg_icon('map-location-dot'); ?></button>
                                    </div>

                                    <!-- Hidden field to store location for form submission -->
                                    <input type="hidden" id="location" name="location" value="<?php echo esc_attr($location); ?>">
                                    <input type="hidden" name="car_city" id="car_city" value="<?php echo esc_attr(get_field('car_city', $car_id)); ?>">
                                    <input type="hidden" name="car_district" id="car_district" value="<?php echo esc_attr(get_field('car_district', $car_id)); ?>">
                                    <input type="hidden" name="car_latitude" id="car_latitude" value="<?php echo esc_attr(get_field('car_latitude', $car_id)); ?>">
                                    <input type="hidden" name="car_longitude" id="car_longitude" value="<?php echo esc_attr(get_field('car_longitude', $car_id)); ?>">
                                    <input type="hidden" name="car_address" id="car_address" value="<?php echo esc_attr(get_field('car_address', $car_id)); ?>">
                                </div>
                                
                                <div class="form-row">
                                    <label><?php echo get_svg_icon('circle-check'); ?> <?php esc_html_e('Availability', 'bricks-child'); ?></label>
                                    <?php
                                    car_filter_render_dropdown(array(
                                        'id'          => 'edit-listing-availability',
                                        'name'        => 'availability',
                                        'placeholder' => __('Select Availability', 'bricks-child'),
                                        'options'     => array(
                                            array('value' => 'In Stock', 'label' => __('In Stock', 'bricks-child')),
                                            array('value' => 'In Transit', 'label' => __('In Transit', 'bricks-child')),
                                        ),
                                        'selected'    => $availability,
                                        'show_count'  => false,
                                        'searchable'  => false,
                                    ));
                                    ?>
                                </div>
                            </div>

                            <div class="form-section engine-performance-section input-wrapper">
                                <h2><?php esc_html_e('Engine & Performance', 'bricks-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('engine'); ?> <?php esc_html_e('Engine Capacity', 'bricks-child'); ?></label>
                                        <?php
                                        $engine_options = array();
                                        for ($capacity = 0.4; $capacity <= 12.0; $capacity += 0.1) {
                                            $formatted_capacity = number_format($capacity, 1);
                                            $engine_options[] = array('value' => $formatted_capacity, 'label' => $formatted_capacity . 'L');
                                        }
                                        $formatted_engine_capacity = $engine_capacity ? number_format((float) $engine_capacity, 1, '.', '') : '';
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-engine-capacity',
                                            'name'        => 'engine_capacity',
                                            'placeholder' => __('Select Engine Capacity', 'bricks-child'),
                                            'options'     => $engine_options,
                                            'selected'    => $formatted_engine_capacity,
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('gas-pump'); ?> <?php esc_html_e('Fuel Type', 'bricks-child'); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-fuel-type',
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
                                            'selected'    => $fuel_type,
                                            'show_count'  => false,
                                            'searchable'  => true,
                                            'data_attrs'  => array('filter-type' => 'fuel_type'),
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('car-chassis'); ?> <?php esc_html_e('Transmission', 'bricks-child'); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-transmission',
                                            'name'        => 'transmission',
                                            'placeholder' => __('Select Transmission', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'Automatic', 'label' => __('Automatic', 'bricks-child')),
                                                array('value' => 'Manual', 'label' => __('Manual', 'bricks-child')),
                                            ),
                                            'selected'    => $transmission,
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label><?php echo get_svg_icon('tire'); ?> <?php esc_html_e('Drive Type (Optional)', 'bricks-child'); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-drive-type',
                                            'name'        => 'drive_type',
                                            'placeholder' => __('Select Drive Type', 'bricks-child'),
                                            'options'     => array(
                                                array('value' => 'Front-Wheel Drive', 'label' => __('Front-Wheel Drive', 'bricks-child')),
                                                array('value' => 'Rear-Wheel Drive', 'label' => __('Rear-Wheel Drive', 'bricks-child')),
                                                array('value' => 'All-Wheel Drive', 'label' => __('All-Wheel Drive', 'bricks-child')),
                                                array('value' => '4-Wheel Drive', 'label' => __('4-Wheel Drive', 'bricks-child')),
                                            ),
                                            'selected'    => $drive_type,
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-half">
                                        <label for="hp"><?php echo get_svg_icon('gauge-high'); ?> <?php esc_html_e('HorsePower (Optional)', 'bricks-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="hp" name="hp" class="form-control" value="<?php echo esc_attr($hp); ?>">
                                            <span class="input-suffix">HP</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section body-design-section input-wrapper">
                                <h2><?php esc_html_e('Body & Design', 'bricks-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('car-side'); ?> <?php esc_html_e('Body Type', 'bricks-child'); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-body-type',
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
                                            'selected'    => $body_type,
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('paintbrush'); ?> <?php esc_html_e('Exterior Color', 'bricks-child'); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-exterior-color',
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
                                            'selected'    => $exterior_color,
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-third">
                                        <label><?php echo get_svg_icon('palette'); ?> <?php esc_html_e('Interior Color (Optional)', 'bricks-child'); ?></label>
                                        <?php
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-interior-color',
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
                                            'selected'    => $interior_color,
                                            'show_count'  => false,
                                            'searchable'  => true,
                                        ));
                                        ?>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label><?php echo get_svg_icon('car-door'); ?> <?php esc_html_e('Number of Doors (Optional)', 'bricks-child'); ?></label>
                                        <?php
                                        $door_opts = array();
                                        foreach (array(0, 2, 3, 4, 5, 6, 7) as $doors) {
                                            $door_opts[] = array('value' => $doors, 'label' => $doors);
                                        }
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-doors',
                                            'name'        => 'number_of_doors',
                                            'placeholder' => __('Select Number of Doors', 'bricks-child'),
                                            'options'     => $door_opts,
                                            'selected'    => $number_of_doors,
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                    <div class="form-half">
                                        <label><?php echo get_svg_icon('car-seat'); ?> <?php esc_html_e('Number of Seats (Optional)', 'bricks-child'); ?></label>
                                        <?php
                                        $seat_opts = array();
                                        foreach (array(1, 2, 3, 4, 5, 6, 7, 8) as $seats) {
                                            $seat_opts[] = array('value' => $seats, 'label' => $seats);
                                        }
                                        car_filter_render_dropdown(array(
                                            'id'          => 'edit-listing-seats',
                                            'name'        => 'number_of_seats',
                                            'placeholder' => __('Select Number of Seats', 'bricks-child'),
                                            'options'     => $seat_opts,
                                            'selected'    => $number_of_seats,
                                            'show_count'  => false,
                                            'searchable'  => false,
                                        ));
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section mot-section input-wrapper">
                                <h2><?php esc_html_e('Registration & Background Info', 'bricks-child'); ?></h2>

                                <div class="form-row">
                                    <label><?php echo get_svg_icon('clipboard-check'); ?> <?php esc_html_e('MOT Status (Optional)', 'bricks-child'); ?></label>
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
                                        'id'          => 'edit-listing-mot',
                                        'name'        => 'motuntil',
                                        'placeholder' => __('Select MOT Status', 'bricks-child'),
                                        'options'     => $mot_options,
                                        'selected'    => $mot_status,
                                        'show_count'  => false,
                                        'searchable'  => true,
                                    ));
                                    ?>
                                </div>

                                <div class="form-row">
                                    <label for="numowners"><?php echo get_svg_icon('users'); ?> <?php esc_html_e('Number of Owners', 'bricks-child'); ?></label>
                                    <input type="text" id="numowners" name="numowners" class="form-control" value="<?php echo esc_attr($num_owners); ?>">
                                </div>

                                <div class="form-row">
                                    <label for="isantique"><?php echo get_svg_icon('clock-rotate-left'); ?> <?php esc_html_e('Registered as an Antique', 'bricks-child'); ?></label>
                                    <div class="checkbox-field">
                                        <input type="checkbox" id="isantique" name="isantique" value="1" <?php checked($is_antique, 1); ?>>
                                        <span><?php esc_html_e('Mark this listing as registered antique', 'bricks-child'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section vehicle-history-section input-wrapper collapsible-section collapsed">
                                <div class="section-header" role="button" tabindex="0" aria-expanded="false">
                                    <h2><?php esc_html_e('Vehicle History', 'bricks-child'); ?></h2>
                                    <span class="collapse-arrow"><?php echo get_svg_icon('chevron-down'); ?></span>
                                </div>
                                <div class="section-content-wrapper">
                                    <div class="section-content">
                                        <div class="form-row">
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
                                                    ?>
                                                    <div class="vehicle-history-option">
                                                        <input type="checkbox"
                                                               id="vehiclehistory_<?php echo esc_attr($value); ?>"
                                                               name="vehiclehistory[]"
                                                               value="<?php echo esc_attr($value); ?>"
                                                               <?php checked(in_array($value, (array)$vehicle_history), true); ?>>
                                                        <label for="vehiclehistory_<?php echo esc_attr($value); ?>">
                                                            <?php echo esc_html($label); ?>
                                                        </label>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section extras-section input-wrapper collapsible-section collapsed">
                                <div class="section-header" role="button" tabindex="0" aria-expanded="false">
                                    <h2><?php esc_html_e('Extras & Features', 'bricks-child'); ?></h2>
                                    <span class="collapse-arrow"><?php echo get_svg_icon('chevron-down'); ?></span>
                                </div>
                                <div class="section-content-wrapper">
                                    <div class="section-content">
                                        <div class="form-row">
                                            <div class="vehicle-history-grid">
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
                                                    ?>
                                                    <div class="vehicle-history-option">
                                                        <input type="checkbox"
                                                               id="extra_<?php echo esc_attr($value); ?>"
                                                               name="extras[]"
                                                               value="<?php echo esc_attr($value); ?>"
                                                               <?php checked(in_array($value, (array)$extras), true); ?>>
                                                        <label for="extra_<?php echo esc_attr($value); ?>">
                                                            <?php echo esc_html($label); ?>
                                                        </label>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section description-section input-wrapper">
                                <h2><?php esc_html_e('Description', 'bricks-child'); ?></h2>
                                <div class="form-row">
                                    <label for="description"><?php echo get_svg_icon('align-left'); ?> <?php esc_html_e('Description', 'bricks-child'); ?></label>
                                    <textarea id="description" name="description" class="form-control" rows="6"><?php 
                                        // Convert HTML back to plain text with proper line breaks for textarea editing
                                        $clean_description = $description;
                                        $clean_description = str_replace('</p><p>', "\n\n", $clean_description);
                                        $clean_description = str_replace('<p>', '', $clean_description);
                                        $clean_description = str_replace('</p>', "\n", $clean_description);
                                        $clean_description = wp_strip_all_tags($clean_description);
                                        $clean_description = trim($clean_description);
                                        echo esc_textarea($clean_description); 
                                    ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <button type="submit" id="update-listing-button" class="btn btn-primary-gradient btn-lg"><?php esc_html_e('Update Listing', 'bricks-child'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div> 