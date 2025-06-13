<?php
/**
 * Car Specification Filter Form
 *
 * @package Astra Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// The geo-utils.php is still required by other parts of the theme for location features.
// Ensure it's loaded if not already. Though it's also in car-listings.php.
if (file_exists(__DIR__ . '/geo-utils.php')) {
    require_once __DIR__ . '/geo-utils.php';
}

function autoagora_display_spec_filters() {
    ob_start();
    ?>
    <div id="car-spec-filters-container" class="car-spec-filters">
        
        <div class="filter-group">
            <label for="filter-make">Make:</label>
            <select name="make" id="filter-make" class="filter-select">
                <option value="">All Makes</option>
                <!-- JS will populate makes with counts -->
            </select>
        </div>

        <div class="filter-group">
            <label for="filter-model">Model:</label>
            <select name="model" id="filter-model" class="filter-select" disabled>
                <option value="">Select Make First</option>
                <!-- JS will populate models based on make, with counts -->
            </select>
        </div>

        <div class="filter-group">
            <label for="filter-variant">Variant:</label>
            <select name="variant" id="filter-variant" class="filter-select" disabled>
                <option value="">Select Model First</option>
                <!-- JS will populate variants based on model, with counts -->
            </select>
        </div>

        <div class="filter-group filter-group-range">
            <label>Year:</label>
            <div class="range-controls">
                <select name="year_min" id="filter-year-min" class="filter-select filter-range-min">
                    <option value="">Min Year</option>
                    <!-- JS will populate years with counts -->
                </select>
                <span>to</span>
                <select name="year_max" id="filter-year-max" class="filter-select filter-range-max">
                    <option value="">Max Year</option>
                    <!-- JS will populate years with counts -->
                </select>
            </div>
        </div>

        <div class="filter-group filter-group-range">
            <label>Price (&euro;):</label>
            <div class="range-controls">
                <select name="price_min" id="filter-price-min" class="filter-select filter-range-min">
                    <option value="">Min Price</option>
                    <!-- JS will populate prices with counts -->
                </select>
                <span>to</span>
                <select name="price_max" id="filter-price-max" class="filter-select filter-range-max">
                    <option value="">Max Price</option>
                    <!-- JS will populate prices with counts -->
                </select>
            </div>
        </div>

        <div class="filter-group filter-group-range">
            <label>Mileage (km):</label>
            <div class="range-controls">
                <select name="mileage_min" id="filter-mileage-min" class="filter-select filter-range-min">
                    <option value="">Min Mileage</option>
                    <!-- JS will populate mileages with counts -->
                </select>
                <span>to</span>
                <select name="mileage_max" id="filter-mileage-max" class="filter-select filter-range-max">
                    <option value="">Max Mileage</option>
                    <!-- JS will populate mileages with counts -->
                </select>
            </div>
        </div>

        <div class="filter-group filter-group-range">
            <label>Engine Capacity (L):</label>
            <div class="range-controls">
                <select name="engine_capacity_min" id="filter-engine-capacity-min" class="filter-select filter-range-min">
                    <option value="">Min CC</option>
                    <!-- JS will populate engine capacities with counts, key: 'engine' -->
                </select>
                <span>to</span>
                <select name="engine_capacity_max" id="filter-engine-capacity-max" class="filter-select filter-range-max">
                    <option value="">Max CC</option>
                    <!-- JS will populate engine capacities with counts -->
                </select>
            </div>
        </div>
        
        <div class="filter-group filter-group-range">
            <label>Horsepower (HP):</label>
            <div class="range-controls">
                <select name="hp_min" id="filter-hp-min" class="filter-select filter-range-min">
                    <option value="">Min HP</option>
                    <!-- JS will populate HP with counts -->
                </select>
                <span>to</span>
                <select name="hp_max" id="filter-hp-max" class="filter-select filter-range-max">
                    <option value="">Max HP</option>
                    <!-- JS will populate HP with counts -->
                </select>
            </div>
        </div>

        <div class="filter-group">
            <label for="filter-transmission">Transmission:</label>
            <select name="transmission" id="filter-transmission" class="filter-select">
                <option value="">Any</option>
                <option value="Automatic">Automatic</option>
                <option value="Manual">Manual</option>
                <!-- JS will add counts -->
            </select>
        </div>
        
        <div class="filter-group multi-select-filter" data-filter-key="fuel_type">
            <label>Fuel Type:</label>
            <ul>
                <li><label><input type="checkbox" name="fuel_type[]" value="Petrol"> Petrol <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Diesel"> Diesel <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Electric"> Electric <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Petrol hybrid"> Petrol hybrid <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Diesel hybrid"> Diesel hybrid <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Plug-in petrol"> Plug-in petrol <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Plug-in diesel"> Plug-in diesel <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Bi Fuel"> Bi Fuel <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Hydrogen"> Hydrogen <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="fuel_type[]" value="Natural Gas"> Natural Gas <span class="option-count">(0)</span></label></li>
            </ul>
        </div>

        <div class="filter-group multi-select-filter" data-filter-key="exterior_color">
            <label>Exterior Color:</label>
            <ul>
                <li><label><input type="checkbox" name="exterior_color[]" value="Black"> Black <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="White"> White <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Silver"> Silver <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Gray"> Gray <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Red"> Red <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Blue"> Blue <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Green"> Green <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Yellow"> Yellow <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Brown"> Brown <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Beige"> Beige <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Orange"> Orange <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Purple"> Purple <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Gold"> Gold <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="exterior_color[]" value="Bronze"> Bronze <span class="option-count">(0)</span></label></li>
            </ul>
        </div>
        
        <div class="filter-group multi-select-filter" data-filter-key="interior_color">
            <label>Interior Color:</label>
            <ul>
                <li><label><input type="checkbox" name="interior_color[]" value="Black"> Black <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="interior_color[]" value="Gray"> Gray <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="interior_color[]" value="Beige"> Beige <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="interior_color[]" value="Brown"> Brown <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="interior_color[]" value="White"> White <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="interior_color[]" value="Red"> Red <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="interior_color[]" value="Blue"> Blue <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="interior_color[]" value="Tan"> Tan <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="interior_color[]" value="Cream"> Cream <span class="option-count">(0)</span></label></li>
            </ul>
        </div>

        <div class="filter-group multi-select-filter" data-filter-key="body_type">
            <label>Body Type:</label>
            <ul>
                <li><label><input type="checkbox" name="body_type[]" value="Hatchback"> Hatchback <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Saloon"> Saloon <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Coupe"> Coupe <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Convertible"> Convertible <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Estate"> Estate <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="SUV"> SUV <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="MPV"> MPV <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Pickup"> Pickup <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Camper"> Camper <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Minibus"> Minibus <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Limousine"> Limousine <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Car Derived Van"> Car Derived Van <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Combi Van"> Combi Van <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Panel Van"> Panel Van <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="body_type[]" value="Window Van"> Window Van <span class="option-count">(0)</span></label></li>
            </ul>
        </div>

        <div class="filter-group multi-select-filter" data-filter-key="drive_type">
            <label>Drive Type:</label>
            <ul>
                <li><label><input type="checkbox" name="drive_type[]" value="Front-Wheel Drive"> Front-Wheel Drive <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="drive_type[]" value="Rear-Wheel Drive"> Rear-Wheel Drive <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="drive_type[]" value="All-Wheel Drive"> All-Wheel Drive <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="drive_type[]" value="4-Wheel Drive"> 4-Wheel Drive <span class="option-count">(0)</span></label></li>
            </ul>
        </div>

        <div class="filter-group">
            <label for="filter-number-of-doors">Number of Doors:</label>
            <select name="number_of_doors" id="filter-number-of-doors" class="filter-select">
                <option value="">Any</option>
                <option value="0">0</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
                <!-- JS will add counts -->
            </select>
        </div>

        <div class="filter-group">
            <label for="filter-number-of-seats">Number of Seats:</label>
            <select name="number_of_seats" id="filter-number-of-seats" class="filter-select">
                <option value="">Any</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
                <option value="8">8</option>
                <!-- JS will add counts -->
            </select>
        </div>

        <div class="filter-group multi-select-filter" data-filter-key="extras">
            <label>Extras:</label>
            <ul>
                <li><label><input type="checkbox" name="extras[]" value="alloy_wheels"> Alloy Wheels <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="cruise_control"> Cruise Control <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="disabled_accessible"> Disabled Accessible <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="keyless_start"> Keyless Start <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="rear_view_camera"> Rear View Camera <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="start_stop"> Start/Stop <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="sunroof"> Sunroof <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="heated_seats"> Heated Seats <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="android_auto"> Android Auto <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="apple_carplay"> Apple CarPlay <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="folding_mirrors"> Folding Mirrors <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="leather_seats"> Leather Seats <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="panoramic_roof"> Panoramic Roof <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="parking_sensors"> Parking Sensors <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="camera_360"> 360 View Camera <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="adaptive_cruise_control"> Adaptive Cruise Control <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="blind_spot_mirror"> Blind Spot Mirror <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="lane_assist"> Lane Assist <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="extras[]" value="power_tailgate"> Power Tailgate <span class="option-count">(0)</span></label></li>
            </ul>
        </div>

        <div class="filter-group">
            <label for="filter-availability">Availability:</label>
            <select name="availability" id="filter-availability" class="filter-select">
                <option value="">Any</option>
                <option value="In Stock">In Stock</option>
                <option value="In Transit">In Transit</option>
                <!-- JS will add counts -->
            </select>
        </div>

        <div class="filter-group filter-group-range">
            <label>Number of Owners:</label>
            <div class="range-controls">
                <select name="numowners_min" id="filter-numowners-min" class="filter-select filter-range-min">
                    <option value="">Min Owners</option>
                     <!-- JS will populate numowners with counts -->
                </select>
                <span>to</span>
                <select name="numowners_max" id="filter-numowners-max" class="filter-select filter-range-max">
                    <option value="">Max Owners</option>
                     <!-- JS will populate numowners with counts -->
                </select>
            </div>
        </div>

        <div class="filter-group">
            <label for="filter-isantique">Antique Vehicle:</label>
            <select name="isantique" id="filter-isantique" class="filter-select">
                <option value="">Any</option>
                <option value="1">Yes</option>
                <option value="0">No</option>
                <!-- JS will add counts -->
            </select>
        </div>

        <div class="filter-group multi-select-filter" data-filter-key="vehiclehistory">
            <label>Vehicle History:</label>
            <ul>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="no_accidents"> No Accidents <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="minor_accidents"> Minor Accidents <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="major_accidents"> Major Accidents <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="regular_maintenance"> Regular Maintenance <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="engine_overhaul"> Engine Overhaul <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="transmission_replacement"> Transmission Replacement <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="repainted"> Repainted <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="bodywork_repair"> Bodywork Repair <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="rust_treatment"> Rust Treatment <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="no_modifications"> No Modifications <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="performance_upgrades"> Performance Upgrades <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="cosmetic_modifications"> Cosmetic Modifications <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="flood_damage"> Flood Damage <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="fire_damage"> Fire Damage <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="hail_damage"> Hail Damage <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="clear_title"> Clear Title <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="no_known_issues"> No Known Issues <span class="option-count">(0)</span></label></li>
                <li><label><input type="checkbox" name="vehiclehistory[]" value="odometer_replacement"> Odometer Replacement <span class="option-count">(0)</span></label></li>
            </ul>
        </div>

        <div class="filter-group">
            <button type="button" id="apply-spec-filters-btn" class="button button-primary">Apply Filters</button>
            <button type="button" id="reset-spec-filters-btn" class="button">Reset Filters</button>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

?>