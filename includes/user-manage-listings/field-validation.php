<?php
/**
 * Field Validation - Whitelist Validation for Car Listing Fields
 * 
 * Ensures only allowed values are accepted for dropdown fields
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Get allowed values for all dropdown fields
 * 
 * @return array Array of field names => allowed values
 */
function get_allowed_field_values() {
    return array(
        'availability' => array(
            'In Stock',
            'In Transit',
        ),
        
        'fuel_type' => array(
            'Petrol',
            'Diesel',
            'Electric',
            'Petrol hybrid',
            'Diesel hybrid',
            'Plug-in petrol',
            'Plug-in diesel',
            'Bi Fuel',
            'Hydrogen',
            'Natural Gas',
        ),
        
        'transmission' => array(
            'Automatic',
            'Manual',
        ),
        
        'body_type' => array(
            'Hatchback',
            'Saloon',
            'Coupe',
            'Convertible',
            'Estate',
            'SUV',
            'MPV',
            'Pickup',
            'Camper',
            'Minibus',
            'Limousine',
            'Car Derived Van',
            'Combi Van',
            'Panel Van',
            'Window Van',
        ),
        
        'exterior_color' => array(
            'Black',
            'White',
            'Silver',
            'Gray',
            'Red',
            'Blue',
            'Green',
            'Yellow',
            'Brown',
            'Beige',
            'Orange',
            'Purple',
            'Gold',
            'Bronze',
        ),
        
        'interior_color' => array(
            'Black',
            'Gray',
            'Beige',
            'Brown',
            'White',
            'Red',
            'Blue',
            'Tan',
            'Cream',
        ),
        
        'drive_type' => array(
            'Front-Wheel Drive',
            'Rear-Wheel Drive',
            'All-Wheel Drive',
            '4-Wheel Drive',
        ),
        
        'extras' => array(
            'alloy_wheels',
            'cruise_control',
            'disabled_accessible',
            'keyless_start',
            'rear_view_camera',
            'start_stop',
            'sunroof',
            'heated_seats',
            'android_auto',
            'apple_carplay',
            'folding_mirrors',
            'leather_seats',
            'panoramic_roof',
            'parking_sensors',
            'camera_360',
            'adaptive_cruise_control',
            'blind_spot_mirror',
            'lane_assist',
            'power_tailgate',
        ),
        
        'vehiclehistory' => array(
            'no_accidents',
            'minor_accidents',
            'major_accidents',
            'regular_maintenance',
            'engine_overhaul',
            'transmission_replacement',
            'repainted',
            'bodywork_repair',
            'rust_treatment',
            'no_modifications',
            'performance_upgrades',
            'cosmetic_modifications',
            'flood_damage',
            'fire_damage',
            'hail_damage',
            'clear_title',
            'no_known_issues',
            'odometer_replacement',
        ),
    );
}

/**
 * Validate a single field value against its whitelist
 * 
 * @param string $field_name Field name to validate
 * @param mixed $value Value to validate
 * @return bool|string True if valid, error message if invalid
 */
function validate_field_value($field_name, $value) {
    $allowed_values = get_allowed_field_values();
    
    // If field not in whitelist, allow it (for fields like make, model, description, etc.)
    if (!isset($allowed_values[$field_name])) {
        return true;
    }
    
    // Empty values are allowed for optional fields
    if (empty($value) || $value === '' || $value === null) {
        return true;
    }
    
    // Check if value is in allowed list
    if (!in_array($value, $allowed_values[$field_name], true)) {
        return sprintf(
            __('Invalid value "%s" for field "%s". Allowed values: %s', 'bricks-child'),
            esc_html($value),
            esc_html($field_name),
            implode(', ', $allowed_values[$field_name])
        );
    }
    
    return true;
}

/**
 * Validate array field (extras, vehiclehistory)
 * 
 * @param string $field_name Field name to validate
 * @param array $values Array of values to validate
 * @return bool|string True if all valid, error message if any invalid
 */
function validate_array_field($field_name, $values) {
    if (!is_array($values) || empty($values)) {
        return true; // Empty arrays are allowed
    }
    
    $allowed_values = get_allowed_field_values();
    
    // If field not in whitelist, allow it
    if (!isset($allowed_values[$field_name])) {
        return true;
    }
    
    // Check each value
    foreach ($values as $value) {
        $validation = validate_field_value($field_name, $value);
        if ($validation !== true) {
            return $validation;
        }
    }
    
    return true;
}

/**
 * Validate numeric range fields
 * 
 * @param string $field_name Field name
 * @param mixed $value Value to validate
 * @return bool|string True if valid, error message if invalid
 */
function validate_numeric_range_field($field_name, $value) {
    // Empty values allowed for optional fields
    if (empty($value) || $value === '' || $value === null) {
        return true;
    }
    
    // Convert to appropriate type
    $numeric_value = is_numeric($value) ? floatval($value) : null;
    
    if ($numeric_value === null) {
        return sprintf(__('Invalid numeric value for field "%s"', 'bricks-child'), esc_html($field_name));
    }
    
    // Validate ranges
    switch ($field_name) {
        case 'year':
            // Year range: 1948 to 2025
            if ($numeric_value < 1948 || $numeric_value > 2025) {
                return sprintf(__('Year must be between 1948 and 2025. Got: %s', 'bricks-child'), esc_html($value));
            }
            break;
            
        case 'engine_capacity':
            // Engine capacity range: 0.4 to 12.0 (in 0.1 increments)
            if ($numeric_value < 0.4 || $numeric_value > 12.0) {
                return sprintf(__('Engine capacity must be between 0.4 and 12.0. Got: %s', 'bricks-child'), esc_html($value));
            }
            // Check if it's a valid increment (0.1 steps)
            $rounded = round($numeric_value, 1);
            if (abs($numeric_value - $rounded) > 0.01) {
                return sprintf(__('Engine capacity must be in 0.1 increments. Got: %s', 'bricks-child'), esc_html($value));
            }
            break;
            
        case 'number_of_doors':
            // Allowed: 0, 2, 3, 4, 5, 6, 7
            $allowed_doors = array(0, 2, 3, 4, 5, 6, 7);
            if (!in_array(intval($numeric_value), $allowed_doors, true)) {
                return sprintf(__('Number of doors must be one of: %s. Got: %s', 'bricks-child'), implode(', ', $allowed_doors), esc_html($value));
            }
            break;
            
        case 'number_of_seats':
            // Allowed: 1, 2, 3, 4, 5, 6, 7, 8
            $allowed_seats = array(1, 2, 3, 4, 5, 6, 7, 8);
            if (!in_array(intval($numeric_value), $allowed_seats, true)) {
                return sprintf(__('Number of seats must be one of: %s. Got: %s', 'bricks-child'), implode(', ', $allowed_seats), esc_html($value));
            }
            break;
            
        case 'mileage':
        case 'price':
        case 'hp':
        case 'numowners':
            // Just check it's a positive number
            if ($numeric_value < 0) {
                return sprintf(__('%s must be a positive number. Got: %s', 'bricks-child'), esc_html(ucfirst(str_replace('_', ' ', $field_name))), esc_html($value));
            }
            break;
    }
    
    return true;
}

/**
 * Validate all car listing fields
 * 
 * @param array $data Form data to validate
 * @return array Array with 'valid' (bool) and 'errors' (array of error messages)
 */
function validate_car_listing_fields($data) {
    $errors = array();
    $allowed_fields = get_allowed_field_values();
    
    // Validate dropdown fields
    foreach ($allowed_fields as $field_name => $allowed_values) {
        // Skip array fields (handled separately)
        if (in_array($field_name, array('extras', 'vehiclehistory'), true)) {
            continue;
        }
        
        if (isset($data[$field_name])) {
            $validation = validate_field_value($field_name, $data[$field_name]);
            if ($validation !== true) {
                $errors[] = $validation;
            }
        }
    }
    
    // Validate array fields
    if (isset($data['extras']) && is_array($data['extras'])) {
        $validation = validate_array_field('extras', $data['extras']);
        if ($validation !== true) {
            $errors[] = $validation;
        }
    }
    
    if (isset($data['vehiclehistory']) && is_array($data['vehiclehistory'])) {
        $validation = validate_array_field('vehiclehistory', $data['vehiclehistory']);
        if ($validation !== true) {
            $errors[] = $validation;
        }
    }
    
    // Validate numeric range fields
    $numeric_fields = array('year', 'engine_capacity', 'number_of_doors', 'number_of_seats', 'mileage', 'price', 'hp', 'numowners');
    foreach ($numeric_fields as $field_name) {
        if (isset($data[$field_name]) && $data[$field_name] !== '' && $data[$field_name] !== null) {
            $validation = validate_numeric_range_field($field_name, $data[$field_name]);
            if ($validation !== true) {
                $errors[] = $validation;
            }
        }
    }
    
    return array(
        'valid' => empty($errors),
        'errors' => $errors,
    );
}

