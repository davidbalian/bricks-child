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

                <div class="add-listing-main-row">
                    <div class="add-listing-main-info-column">
                            <div class="form-section basic-details-section">
                                <h2><?php esc_html_e('Basic Details', 'bricks-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="make"><i class="fas fa-car-side"></i> <?php esc_html_e('Make', 'bricks-child'); ?></label>
                                        <input type="text" id="make" name="make" class="form-control" value="<?php echo esc_attr($make); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="model"><i class="fas fa-car"></i> <?php esc_html_e('Model', 'bricks-child'); ?></label>
                                        <input type="text" id="model" name="model" class="form-control" value="<?php echo esc_attr($model); ?>" readonly>
                                    </div>
                                    <!-- variant field removed -->
                                </div>

                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="year"><i class="far fa-calendar-alt"></i> <?php esc_html_e('Year', 'bricks-child'); ?></label>
                                        <select id="year" name="year" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Year', 'bricks-child'); ?></option>
                                            <?php
                                            for ($year_option = 2025; $year_option >= 1948; $year_option--) {
                                                printf(
                                                    '<option value="%1$d" %2$s>%1$d</option>',
                                                    esc_attr($year_option),
                                                    selected((int) $year, $year_option, false)
                                                );
                                            }

                                            $year_int = (int) $year;
                                            if ($year_int && ($year_int < 1948 || $year_int > 2025)) {
                                                printf(
                                                    '<option value="%1$d" %2$s>%1$d</option>',
                                                    esc_attr($year_int),
                                                    selected((int) $year, $year_int, false)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="mileage"><i class="fas fa-road"></i> <?php esc_html_e('Mileage', 'bricks-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="mileage" name="mileage" class="form-control" value="<?php echo esc_attr($mileage); ?>" required>
                                            <span class="input-suffix">km</span>
                                        </div>
                                    </div>
                                    <div class="form-third">
                                        <label for="price"><i class="fas fa-euro-sign"></i> <?php esc_html_e('Price', 'bricks-child'); ?></label>
                                        <input type="text" id="price" name="price" class="form-control" value="<?php echo esc_attr($price); ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <label for="location"><i class="fas fa-map-pin"></i> <?php esc_html_e('Location', 'bricks-child'); ?></label>
                                    <input type="text" id="location" name="location" class="form-control" value="<?php echo esc_attr($location); ?>" required>
                                    <button type="button" class="btn btn-secondary choose-location-btn">Choose Location ></button>
                                    <input type="hidden" name="car_city" id="car_city" value="<?php echo esc_attr(get_field('car_city', $car_id)); ?>">
                                    <input type="hidden" name="car_district" id="car_district" value="<?php echo esc_attr(get_field('car_district', $car_id)); ?>">
                                    <input type="hidden" name="car_latitude" id="car_latitude" value="<?php echo esc_attr(get_field('car_latitude', $car_id)); ?>">
                                    <input type="hidden" name="car_longitude" id="car_longitude" value="<?php echo esc_attr(get_field('car_longitude', $car_id)); ?>">
                                    <input type="hidden" name="car_address" id="car_address" value="<?php echo esc_attr(get_field('car_address', $car_id)); ?>">
                                </div>
                                
                                <div class="form-row">
                                    <label for="availability"><i class="fas fa-check-circle"></i> <?php esc_html_e('Availability', 'bricks-child'); ?></label>
                                    <select id="availability" name="availability" class="form-control" required>
                                        <option value="In Stock" <?php selected($availability, 'In Stock'); ?>><?php esc_html_e('In Stock', 'bricks-child'); ?></option>
                                        <option value="In Transit" <?php selected($availability, 'In Transit'); ?>><?php esc_html_e('In Transit', 'bricks-child'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-section engine-performance-section">
                                <h2><?php esc_html_e('Engine & Performance', 'bricks-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="engine_capacity"><i class="fas fa-tachometer-alt"></i> <?php esc_html_e('Engine Capacity', 'bricks-child'); ?></label>
                                        <select id="engine_capacity" name="engine_capacity" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Engine Capacity', 'bricks-child'); ?></option>
                                            <?php
                                            $formatted_capacity_value = number_format((float) $engine_capacity, 1, '.', '');
                                            for ($capacity = 0.4; $capacity <= 12.0; $capacity += 0.1) {
                                                $formatted_capacity = number_format($capacity, 1);
                                                printf(
                                                    '<option value="%1$s" %2$s>%1$s</option>',
                                                    esc_attr($formatted_capacity),
                                                    selected($formatted_capacity_value, $formatted_capacity, false)
                                                );
                                            }

                                            if ($formatted_capacity_value && ((float) $formatted_capacity_value < 0.4 || (float) $formatted_capacity_value > 12.0)) {
                                                printf(
                                                    '<option value="%1$s" %2$s>%1$s</option>',
                                                    esc_attr($formatted_capacity_value),
                                                    selected($formatted_capacity_value, $formatted_capacity_value, false)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="fuel_type"><i class="fas fa-gas-pump"></i> <?php esc_html_e('Fuel Type', 'bricks-child'); ?></label>
                                        <select id="fuel_type" name="fuel_type" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Fuel Type', 'bricks-child'); ?></option>
                                            <option value="Petrol" <?php selected($fuel_type, 'Petrol'); ?>><?php esc_html_e('Petrol', 'bricks-child'); ?></option>
                                            <option value="Diesel" <?php selected($fuel_type, 'Diesel'); ?>><?php esc_html_e('Diesel', 'bricks-child'); ?></option>
                                            <option value="Electric" <?php selected($fuel_type, 'Electric'); ?>><?php esc_html_e('Electric', 'bricks-child'); ?></option>
                                            <option value="Petrol hybrid" <?php selected($fuel_type, 'Petrol hybrid'); ?>><?php esc_html_e('Petrol hybrid', 'bricks-child'); ?></option>
                                            <option value="Diesel hybrid" <?php selected($fuel_type, 'Diesel hybrid'); ?>><?php esc_html_e('Diesel hybrid', 'bricks-child'); ?></option>
                                            <option value="Plug-in petrol" <?php selected($fuel_type, 'Plug-in petrol'); ?>><?php esc_html_e('Plug-in petrol', 'bricks-child'); ?></option>
                                            <option value="Plug-in diesel" <?php selected($fuel_type, 'Plug-in diesel'); ?>><?php esc_html_e('Plug-in diesel', 'bricks-child'); ?></option>
                                            <option value="Bi Fuel" <?php selected($fuel_type, 'Bi Fuel'); ?>><?php esc_html_e('Bi Fuel', 'bricks-child'); ?></option>
                                            <option value="Hydrogen" <?php selected($fuel_type, 'Hydrogen'); ?>><?php esc_html_e('Hydrogen', 'bricks-child'); ?></option>
                                            <option value="Natural Gas" <?php selected($fuel_type, 'Natural Gas'); ?>><?php esc_html_e('Natural Gas', 'bricks-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="transmission"><i class="fas fa-cogs"></i> <?php esc_html_e('Transmission', 'bricks-child'); ?></label>
                                        <select id="transmission" name="transmission" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Transmission', 'bricks-child'); ?></option>
                                            <option value="Automatic" <?php selected($transmission, 'Automatic'); ?>><?php esc_html_e('Automatic', 'bricks-child'); ?></option>
                                            <option value="Manual" <?php selected($transmission, 'Manual'); ?>><?php esc_html_e('Manual', 'bricks-child'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label for="drive_type"><i class="fas fa-car-side"></i> <?php esc_html_e('Drive Type', 'bricks-child'); ?></label>
                                        <select id="drive_type" name="drive_type" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Drive Type', 'bricks-child'); ?></option>
                                            <option value="Front-Wheel Drive" <?php selected($drive_type, 'Front-Wheel Drive'); ?>><?php esc_html_e('Front-Wheel Drive', 'bricks-child'); ?></option>
                                            <option value="Rear-Wheel Drive" <?php selected($drive_type, 'Rear-Wheel Drive'); ?>><?php esc_html_e('Rear-Wheel Drive', 'bricks-child'); ?></option>
                                            <option value="All-Wheel Drive" <?php selected($drive_type, 'All-Wheel Drive'); ?>><?php esc_html_e('All-Wheel Drive', 'bricks-child'); ?></option>
                                            <option value="4-Wheel Drive" <?php selected($drive_type, '4-Wheel Drive'); ?>><?php esc_html_e('4-Wheel Drive', 'bricks-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-half">
                                        <label for="hp"><i class="fas fa-horse"></i> <?php esc_html_e('HorsePower (Optional)', 'bricks-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="hp" name="hp" class="form-control" value="<?php echo esc_attr($hp); ?>">
                                            <span class="input-suffix">HP</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section body-design-section">
                                <h2><?php esc_html_e('Body & Design', 'bricks-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="body_type"><i class="fas fa-car-side"></i> <?php esc_html_e('Body Type', 'bricks-child'); ?></label>
                                        <select id="body_type" name="body_type" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Body Type', 'bricks-child'); ?></option>
                                            <option value="Hatchback" <?php selected($body_type, 'Hatchback'); ?>><?php esc_html_e('Hatchback', 'bricks-child'); ?></option>
                                            <option value="Saloon" <?php selected($body_type, 'Saloon'); ?>><?php esc_html_e('Saloon', 'bricks-child'); ?></option>
                                            <option value="Coupe" <?php selected($body_type, 'Coupe'); ?>><?php esc_html_e('Coupe', 'bricks-child'); ?></option>
                                            <option value="Convertible" <?php selected($body_type, 'Convertible'); ?>><?php esc_html_e('Convertible', 'bricks-child'); ?></option>
                                            <option value="Estate" <?php selected($body_type, 'Estate'); ?>><?php esc_html_e('Estate', 'bricks-child'); ?></option>
                                            <option value="SUV" <?php selected($body_type, 'SUV'); ?>><?php esc_html_e('SUV', 'bricks-child'); ?></option>
                                            <option value="MPV" <?php selected($body_type, 'MPV'); ?>><?php esc_html_e('MPV', 'bricks-child'); ?></option>
                                            <option value="Pickup" <?php selected($body_type, 'Pickup'); ?>><?php esc_html_e('Pickup', 'bricks-child'); ?></option>
                                            <option value="Camper" <?php selected($body_type, 'Camper'); ?>><?php esc_html_e('Camper', 'bricks-child'); ?></option>
                                            <option value="Minibus" <?php selected($body_type, 'Minibus'); ?>><?php esc_html_e('Minibus', 'bricks-child'); ?></option>
                                            <option value="Limousine" <?php selected($body_type, 'Limousine'); ?>><?php esc_html_e('Limousine', 'bricks-child'); ?></option>
                                            <option value="Car Derived Van" <?php selected($body_type, 'Car Derived Van'); ?>><?php esc_html_e('Car Derived Van', 'bricks-child'); ?></option>
                                            <option value="Combi Van" <?php selected($body_type, 'Combi Van'); ?>><?php esc_html_e('Combi Van', 'bricks-child'); ?></option>
                                            <option value="Panel Van" <?php selected($body_type, 'Panel Van'); ?>><?php esc_html_e('Panel Van', 'bricks-child'); ?></option>
                                            <option value="Window Van" <?php selected($body_type, 'Window Van'); ?>><?php esc_html_e('Window Van', 'bricks-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="exterior_color"><i class="fas fa-paint-brush"></i> <?php esc_html_e('Exterior Color', 'bricks-child'); ?></label>
                                        <select id="exterior_color" name="exterior_color" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Exterior Color', 'bricks-child'); ?></option>
                                            <option value="Black" <?php selected($exterior_color, 'Black'); ?>><?php esc_html_e('Black', 'bricks-child'); ?></option>
                                            <option value="White" <?php selected($exterior_color, 'White'); ?>><?php esc_html_e('White', 'bricks-child'); ?></option>
                                            <option value="Silver" <?php selected($exterior_color, 'Silver'); ?>><?php esc_html_e('Silver', 'bricks-child'); ?></option>
                                            <option value="Gray" <?php selected($exterior_color, 'Gray'); ?>><?php esc_html_e('Gray', 'bricks-child'); ?></option>
                                            <option value="Red" <?php selected($exterior_color, 'Red'); ?>><?php esc_html_e('Red', 'bricks-child'); ?></option>
                                            <option value="Blue" <?php selected($exterior_color, 'Blue'); ?>><?php esc_html_e('Blue', 'bricks-child'); ?></option>
                                            <option value="Green" <?php selected($exterior_color, 'Green'); ?>><?php esc_html_e('Green', 'bricks-child'); ?></option>
                                            <option value="Yellow" <?php selected($exterior_color, 'Yellow'); ?>><?php esc_html_e('Yellow', 'bricks-child'); ?></option>
                                            <option value="Brown" <?php selected($exterior_color, 'Brown'); ?>><?php esc_html_e('Brown', 'bricks-child'); ?></option>
                                            <option value="Beige" <?php selected($exterior_color, 'Beige'); ?>><?php esc_html_e('Beige', 'bricks-child'); ?></option>
                                            <option value="Orange" <?php selected($exterior_color, 'Orange'); ?>><?php esc_html_e('Orange', 'bricks-child'); ?></option>
                                            <option value="Purple" <?php selected($exterior_color, 'Purple'); ?>><?php esc_html_e('Purple', 'bricks-child'); ?></option>
                                            <option value="Gold" <?php selected($exterior_color, 'Gold'); ?>><?php esc_html_e('Gold', 'bricks-child'); ?></option>
                                            <option value="Bronze" <?php selected($exterior_color, 'Bronze'); ?>><?php esc_html_e('Bronze', 'bricks-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="interior_color"><i class="fas fa-paint-brush"></i> <?php esc_html_e('Interior Color', 'bricks-child'); ?></label>
                                        <select id="interior_color" name="interior_color" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Interior Color', 'bricks-child'); ?></option>
                                            <option value="Black" <?php selected($interior_color, 'Black'); ?>><?php esc_html_e('Black', 'bricks-child'); ?></option>
                                            <option value="Gray" <?php selected($interior_color, 'Gray'); ?>><?php esc_html_e('Gray', 'bricks-child'); ?></option>
                                            <option value="Beige" <?php selected($interior_color, 'Beige'); ?>><?php esc_html_e('Beige', 'bricks-child'); ?></option>
                                            <option value="Brown" <?php selected($interior_color, 'Brown'); ?>><?php esc_html_e('Brown', 'bricks-child'); ?></option>
                                            <option value="White" <?php selected($interior_color, 'White'); ?>><?php esc_html_e('White', 'bricks-child'); ?></option>
                                            <option value="Red" <?php selected($interior_color, 'Red'); ?>><?php esc_html_e('Red', 'bricks-child'); ?></option>
                                            <option value="Blue" <?php selected($interior_color, 'Blue'); ?>><?php esc_html_e('Blue', 'bricks-child'); ?></option>
                                            <option value="Tan" <?php selected($interior_color, 'Tan'); ?>><?php esc_html_e('Tan', 'bricks-child'); ?></option>
                                            <option value="Cream" <?php selected($interior_color, 'Cream'); ?>><?php esc_html_e('Cream', 'bricks-child'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label for="number_of_doors"><i class="fas fa-door-open"></i> <?php esc_html_e('Number of Doors', 'bricks-child'); ?></label>
                                        <select id="number_of_doors" name="number_of_doors" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Number of Doors', 'bricks-child'); ?></option>
                                            <?php
                                            $door_options = array(0, 2, 3, 4, 5, 6, 7);
                                            foreach ($door_options as $doors) {
                                                printf(
                                                    '<option value="%1$d" %2$s>%1$d</option>',
                                                    esc_attr($doors),
                                                    selected((int) $number_of_doors, $doors, false)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-half">
                                        <label for="number_of_seats"><i class="fas fa-chair"></i> <?php esc_html_e('Number of Seats', 'bricks-child'); ?></label>
                                        <select id="number_of_seats" name="number_of_seats" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Number of Seats', 'bricks-child'); ?></option>
                                            <?php
                                            $seat_options = range(1, 8);
                                            foreach ($seat_options as $seats) {
                                                printf(
                                                    '<option value="%1$d" %2$s>%1$d</option>',
                                                    esc_attr($seats),
                                                    selected((int) $number_of_seats, $seats, false)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section mot-section">
                                <h2><?php esc_html_e('Registration & Background Info', 'bricks-child'); ?></h2>

                                <div class="form-row">
                                    <label for="motuntil"><i class="fas fa-clipboard-check"></i> <?php esc_html_e('MOT Status (Optional)', 'bricks-child'); ?></label>
                                    <select id="motuntil" name="motuntil" class="form-control">
                                        <option value=""><?php esc_html_e('Select MOT Status', 'bricks-child'); ?></option>
                                        <option value="Expired" <?php selected($mot_status, 'Expired'); ?>><?php esc_html_e('Expired', 'bricks-child'); ?></option>
                                        <?php
                                        // Generate month options from current month up to 2 years ahead
                                        try {
                                            $current_date = new DateTime('first day of this month');
                                            $end_date     = new DateTime('last day of +2 years');

                                            while ($current_date <= $end_date) {
                                                $value   = $current_date->format('Y-m');
                                                $display = $current_date->format('F Y');
                                                ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($mot_status, $value); ?>>
                                                    <?php echo esc_html($display); ?>
                                                </option>
                                                <?php
                                                $current_date->modify('+1 month');
                                            }
                                        } catch (Exception $e) {
                                            // Fail silently if DateTime is not available or errors occur.
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="numowners"><i class="fas fa-users"></i> <?php esc_html_e('Number of Owners', 'bricks-child'); ?></label>
                                    <input type="text" id="numowners" name="numowners" class="form-control" value="<?php echo esc_attr($num_owners); ?>">
                                </div>

                                <div class="form-row">
                                    <label for="isantique"><i class="fas fa-clock"></i> <?php esc_html_e('Registered as an Antique', 'bricks-child'); ?></label>
                                    <div class="checkbox-field">
                                        <input type="checkbox" id="isantique" name="isantique" value="1" <?php checked($is_antique, 1); ?>>
                                        <span><?php esc_html_e('Mark this listing as registered antique', 'bricks-child'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section vehicle-history-section">
                                <h2 class="collapsible-section-title"><?php esc_html_e('Vehicle History', 'bricks-child'); ?> <span class="toggle-arrow">▼</span></h2>
                                <div class="collapsible-section-content" style="display: none;">
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

                            <div class="form-section extras-section">
                                <h2 class="collapsible-section-title"><?php esc_html_e('Extras & Features', 'bricks-child'); ?> <span class="toggle-arrow">▼</span></h2>
                                <div class="collapsible-section-content" style="display: none;">
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

                            <div class="form-section description-section">
                                <h2><?php esc_html_e('Description', 'bricks-child'); ?></h2>
                                <div class="form-row">
                                    <label for="description"><i class="fas fa-align-left"></i> <?php esc_html_e('Description', 'bricks-child'); ?></label>
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

                        <div class="add-listing-image-column">
                            <div class="add-listing-images-section">
                                <h2><?php esc_html_e('Upload Images', 'bricks-child'); ?></h2>
                                <p class="image-upload-info"><?php esc_html_e('Hold CTRL to choose several photos. Minimum 2 images per listing. Maximum 25 images per listing. Maximum file size is 12MB, the formats are .jpg, .jpeg, .png, .gif, .webp.', 'bricks-child'); ?></p>
                                <p class="image-upload-note"><?php esc_html_e('Note: ads with good photos get more attention', 'bricks-child'); ?></p>
                                <div class="image-upload-container">
                                    <div class="file-upload-area" id="file-upload-area" role="button" tabindex="0">
                                        <div class="upload-message">
                                            <i class="fas fa-cloud-upload-alt"></i>
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