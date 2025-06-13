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
    <div class="ast-container">
        <div class="ast-row">
            <div class="ast-col-md-12">
                <?php
                if (isset($_GET['listing_error'])) {
                    ?>
                    <div class="listing-error-message">
                        <h2><?php esc_html_e('Submission Error', 'astra-child'); ?></h2>
                        <p><?php esc_html_e('There was a problem with your submission. Please check all fields and try again.', 'astra-child'); ?></p>
                    </div>
                    <?php
                }
                ?>
                <h1><?php esc_html_e('Edit Car Listing', 'astra-child'); ?></h1>
                
                <form id="edit-car-listing-form" class="car-listing-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('edit_car_listing_nonce', 'edit_car_listing_nonce'); ?>
                    <input type="hidden" name="action" value="edit_car_listing">
                    <input type="hidden" name="car_id" value="<?php echo esc_attr($car_id); ?>">
                    <input type="hidden" id="async_session_id" name="async_session_id" value="">

                    <div class="add-listing-main-row">
                        <div class="add-listing-main-info-column">
                            <div class="form-section basic-details-section">
                                <h2><?php esc_html_e('Basic Details', 'astra-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="make"><i class="fas fa-car-side"></i> <?php esc_html_e('Make', 'astra-child'); ?></label>
                                        <input type="text" id="make" name="make" class="form-control" value="<?php echo esc_attr($make); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="model"><i class="fas fa-car"></i> <?php esc_html_e('Model', 'astra-child'); ?></label>
                                        <input type="text" id="model" name="model" class="form-control" value="<?php echo esc_attr($model); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="variant"><i class="fas fa-car-side"></i> <?php esc_html_e('Variant', 'astra-child'); ?></label>
                                        <input type="text" id="variant" name="variant" class="form-control" value="<?php echo esc_attr($variant); ?>" readonly>
                                    </div>
                                </div>

                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="year"><i class="far fa-calendar-alt"></i> <?php esc_html_e('Year', 'astra-child'); ?></label>
                                        <input type="text" id="year" name="year" class="form-control" value="<?php echo esc_attr($year); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="mileage"><i class="fas fa-road"></i> <?php esc_html_e('Mileage', 'astra-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="mileage" name="mileage" class="form-control" value="<?php echo esc_attr($mileage); ?>" required>
                                            <span class="input-suffix">km</span>
                                        </div>
                                    </div>
                                    <div class="form-third">
                                        <label for="price"><i class="fas fa-euro-sign"></i> <?php esc_html_e('Price', 'astra-child'); ?></label>
                                        <input type="text" id="price" name="price" class="form-control" value="<?php echo esc_attr($price); ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <label for="location"><i class="fas fa-map-pin"></i> <?php esc_html_e('Location', 'astra-child'); ?></label>
                                    <input type="text" id="location" name="location" class="form-control" value="<?php echo esc_attr($location); ?>" required>
                                    <button type="button" class="btn btn-secondary choose-location-btn">Choose Location ></button>
                                    <input type="hidden" name="car_city" id="car_city" value="<?php echo esc_attr(get_field('car_city', $car_id)); ?>">
                                    <input type="hidden" name="car_district" id="car_district" value="<?php echo esc_attr(get_field('car_district', $car_id)); ?>">
                                    <input type="hidden" name="car_latitude" id="car_latitude" value="<?php echo esc_attr(get_field('car_latitude', $car_id)); ?>">
                                    <input type="hidden" name="car_longitude" id="car_longitude" value="<?php echo esc_attr(get_field('car_longitude', $car_id)); ?>">
                                    <input type="hidden" name="car_address" id="car_address" value="<?php echo esc_attr(get_field('car_address', $car_id)); ?>">
                                </div>
                            </div>

                            <div class="form-section engine-performance-section">
                                <h2><?php esc_html_e('Engine & Performance', 'astra-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="engine_capacity"><i class="fas fa-tachometer-alt"></i> <?php esc_html_e('Engine Capacity', 'astra-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="engine_capacity" name="engine_capacity" class="form-control" value="<?php echo esc_attr($engine_capacity); ?>" readonly>
                                            <span class="input-suffix">L</span>
                                        </div>
                                    </div>
                                    <div class="form-third">
                                        <label for="fuel_type"><i class="fas fa-gas-pump"></i> <?php esc_html_e('Fuel Type', 'astra-child'); ?></label>
                                        <input type="text" id="fuel_type" name="fuel_type" class="form-control" value="<?php echo esc_attr($fuel_type); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="transmission"><i class="fas fa-cogs"></i> <?php esc_html_e('Transmission', 'astra-child'); ?></label>
                                        <input type="text" id="transmission" name="transmission" class="form-control" value="<?php echo esc_attr($transmission); ?>" readonly>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label for="drive_type"><i class="fas fa-car-side"></i> <?php esc_html_e('Drive Type', 'astra-child'); ?></label>
                                        <input type="text" id="drive_type" name="drive_type" class="form-control" value="<?php echo esc_attr($drive_type); ?>" readonly>
                                    </div>
                                    <div class="form-half">
                                        <label for="hp"><i class="fas fa-horse"></i> <?php esc_html_e('HorsePower (Optional)', 'astra-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="hp" name="hp" class="form-control" value="<?php echo esc_attr($hp); ?>">
                                            <span class="input-suffix">HP</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section body-design-section">
                                <h2><?php esc_html_e('Body & Design', 'astra-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="body_type"><i class="fas fa-car-side"></i> <?php esc_html_e('Body Type', 'astra-child'); ?></label>
                                        <input type="text" id="body_type" name="body_type" class="form-control" value="<?php echo esc_attr($body_type); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="exterior_color"><i class="fas fa-paint-brush"></i> <?php esc_html_e('Exterior Color', 'astra-child'); ?></label>
                                        <input type="text" id="exterior_color" name="exterior_color" class="form-control" value="<?php echo esc_attr($exterior_color); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="interior_color"><i class="fas fa-paint-brush"></i> <?php esc_html_e('Interior Color', 'astra-child'); ?></label>
                                        <input type="text" id="interior_color" name="interior_color" class="form-control" value="<?php echo esc_attr($interior_color); ?>" readonly>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label for="number_of_doors"><i class="fas fa-door-open"></i> <?php esc_html_e('Number of Doors', 'astra-child'); ?></label>
                                        <input type="text" id="number_of_doors" name="number_of_doors" class="form-control" value="<?php echo esc_attr($number_of_doors); ?>" readonly>
                                    </div>
                                    <div class="form-half">
                                        <label for="number_of_seats"><i class="fas fa-chair"></i> <?php esc_html_e('Number of Seats', 'astra-child'); ?></label>
                                        <input type="text" id="number_of_seats" name="number_of_seats" class="form-control" value="<?php echo esc_attr($number_of_seats); ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section mot-section">
                                <h2><?php esc_html_e('Registration & Background Info', 'astra-child'); ?></h2>
                                <div class="form-row">
                                    <label for="numowners"><i class="fas fa-users"></i> <?php esc_html_e('Number of Owners', 'astra-child'); ?></label>
                                    <input type="text" id="numowners" name="numowners" class="form-control" value="<?php echo esc_attr($num_owners); ?>" required>
                                </div>

                                <div class="form-row">
                                    <label for="isantique"><i class="fas fa-clock"></i> <?php esc_html_e('Written as antique', 'astra-child'); ?></label>
                                    <input type="text" id="isantique" name="isantique" class="form-control" value="<?php echo $is_antique ? esc_html__('Yes', 'astra-child') : esc_html__('No', 'astra-child'); ?>" readonly>
                                </div>
                            </div>

                            <div class="form-section vehicle-history-section">
                                <h2 class="collapsible-section-title"><?php esc_html_e('Vehicle History', 'astra-child'); ?> <span class="toggle-arrow">▼</span></h2>
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
                                <h2 class="collapsible-section-title"><?php esc_html_e('Extras & Features', 'astra-child'); ?> <span class="toggle-arrow">▼</span></h2>
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
                                <h2><?php esc_html_e('Description', 'astra-child'); ?></h2>
                                <div class="form-row">
                                    <label for="description"><i class="fas fa-align-left"></i> <?php esc_html_e('Description', 'astra-child'); ?></label>
                                    <textarea id="description" name="description" class="form-control" rows="6" required><?php echo esc_textarea(wp_strip_all_tags($description)); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="add-listing-image-column">
                            <div class="add-listing-images-section">
                                <h2><?php esc_html_e('Upload Images', 'astra-child'); ?></h2>
                                <p class="image-upload-info"><?php esc_html_e('Hold CTRL to choose several photos. Maximum 25 images per listing. Maximum file size is 5MB, the formats are .jpg, .jpeg, .png, .gif, .webp', 'astra-child'); ?></p>
                                <p class="image-upload-note"><?php esc_html_e('Note: ads with good photos get more attention', 'astra-child'); ?></p>
                                <div class="image-upload-container">
                                    <div class="file-upload-area" id="file-upload-area" role="button" tabindex="0">
                                        <div class="upload-message">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p><?php esc_html_e('Drag & Drop Images Here', 'astra-child'); ?></p>
                                            <p class="small"><?php esc_html_e('or click to select files', 'astra-child'); ?></p>
                                        </div>
                                    </div>
                                    <input type="file" id="car_images" name="car_images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
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
                                <p class="image-upload-info"><?php esc_html_e('Hold CTRL to choose several photos. Minimum 5 images per listing. Maximum 25 images per listing. Maximum file size is 5MB, the formats are .jpg, .jpeg, .png, .gif, .webp. Images larger than 2MB are automatically compressed.', 'astra-child'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="submit-button gradient-button"><?php esc_html_e('Update Listing', 'astra-child'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> 