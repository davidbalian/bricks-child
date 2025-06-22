<?php
/**
 * Bulk Car Import Functionality
 * 
 * Allows bulk uploading of car listings via CSV/Excel files
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle bulk car import file upload
 */
function handle_bulk_car_import() {
    // Verify nonce and user permissions
    if (!isset($_POST['bulk_import_nonce']) || !wp_verify_nonce($_POST['bulk_import_nonce'], 'bulk_car_import_nonce')) {
        wp_redirect(add_query_arg('import_error', 'nonce_failed', wp_get_referer()));
        exit;
    }
    
    // Check if user is logged in and has appropriate permissions
    if (!is_user_logged_in()) {
        wp_redirect(add_query_arg('import_error', 'not_logged_in', wp_get_referer()));
        exit;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['bulk_import_file']) || $_FILES['bulk_import_file']['error'] !== UPLOAD_ERR_OK) {
        wp_redirect(add_query_arg('import_error', 'file_upload_failed', wp_get_referer()));
        exit;
    }
    
    $file = $_FILES['bulk_import_file'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type
    if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
        wp_redirect(add_query_arg('import_error', 'invalid_file_type', wp_get_referer()));
        exit;
    }
    
    // Process the file
    $import_results = process_bulk_import_file($file);
    
    // Redirect with results
    $redirect_args = array(
        'import_complete' => '1',
        'imported_count' => $import_results['success_count'],
        'error_count' => $import_results['error_count']
    );
    
    if (!empty($import_results['errors'])) {
        $redirect_args['import_errors'] = urlencode(json_encode($import_results['errors']));
    }
    
    wp_redirect(add_query_arg($redirect_args, wp_get_referer()));
    exit;
}

/**
 * Process bulk import file
 */
function process_bulk_import_file($file) {
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $results = array(
        'success_count' => 0,
        'error_count' => 0,
        'errors' => array()
    );
    
    try {
        if ($file_extension === 'csv') {
            $data = parse_csv_file($file['tmp_name']);
        } else {
            // For Excel files, you'd need to include a library like PhpSpreadsheet
            $data = parse_excel_file($file['tmp_name']);
        }
        
        if (empty($data)) {
            $results['errors'][] = 'No data found in file';
            return $results;
        }
        
        // Process each row
        foreach ($data as $row_index => $row) {
            $car_result = create_car_from_row($row, $row_index + 1);
            
            if ($car_result['success']) {
                $results['success_count']++;
            } else {
                $results['error_count']++;
                $results['errors'][] = "Row " . ($row_index + 1) . ": " . $car_result['error'];
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = 'File processing error: ' . $e->getMessage();
        $results['error_count']++;
    }
    
    return $results;
}

/**
 * Parse CSV file
 */
function parse_csv_file($file_path) {
    $data = array();
    $headers = array();
    
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        $row_index = 0;
        
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if ($row_index === 0) {
                // First row contains headers
                $headers = array_map('trim', $row);
            } else {
                // Combine headers with values
                $data[] = array_combine($headers, $row);
            }
            $row_index++;
        }
        fclose($handle);
    }
    
    return $data;
}

/**
 * Parse Excel file (requires PhpSpreadsheet library)
 */
function parse_excel_file($file_path) {
    // This would require installing PhpSpreadsheet via Composer
    // For now, return empty array with note to user
    return array();
}

/**
 * Create car listing from CSV row data
 */
function create_car_from_row($row, $row_number) {
    // Define required fields mapping
    $required_fields = array(
        'make' => 'Make',
        'model' => 'Model',
        'year' => 'Year',
        'mileage' => 'Mileage',
        'price' => 'Price',
        'engine_capacity' => 'Engine Capacity',
        'fuel_type' => 'Fuel Type',
        'transmission' => 'Transmission',
        'body_type' => 'Body Type',
        'drive_type' => 'Drive Type',
        'exterior_color' => 'Exterior Color',
        'interior_color' => 'Interior Color',
        'description' => 'Description',
        'number_of_doors' => 'Number of Doors',
        'number_of_seats' => 'Number of Seats',
        'numowners' => 'Number of Owners'
    );
    
    // Check for required fields
    $missing_fields = array();
    foreach ($required_fields as $field_key => $field_label) {
        if (!isset($row[$field_label]) || empty(trim($row[$field_label]))) {
            $missing_fields[] = $field_label;
        }
    }
    
    if (!empty($missing_fields)) {
        return array(
            'success' => false,
            'error' => 'Missing required fields: ' . implode(', ', $missing_fields)
        );
    }
    
    // Sanitize and prepare data
    $car_data = array();
    foreach ($required_fields as $field_key => $field_label) {
        $car_data[$field_key] = sanitize_text_field($row[$field_label]);
    }
    
    // Handle optional fields
    $optional_fields = array(
        'car_city' => 'City',
        'car_district' => 'District',
        'car_address' => 'Address',
        'car_latitude' => 'Latitude',
        'car_longitude' => 'Longitude',
        'availability' => 'Availability',
        'hp' => 'Horsepower',
        'motuntil' => 'MOT Until',
        'isantique' => 'Is Antique'
    );
    
    foreach ($optional_fields as $field_key => $field_label) {
        if (isset($row[$field_label]) && !empty(trim($row[$field_label]))) {
            if ($field_key === 'car_latitude' || $field_key === 'car_longitude') {
                $car_data[$field_key] = floatval($row[$field_label]);
            } else {
                $car_data[$field_key] = sanitize_text_field($row[$field_label]);
            }
        }
    }
    
    // Handle arrays (extras, vehicle history)
    if (isset($row['Extras']) && !empty($row['Extras'])) {
        $car_data['extras'] = array_map('trim', explode(',', $row['Extras']));
    }
    
    if (isset($row['Vehicle History']) && !empty($row['Vehicle History'])) {
        $car_data['vehiclehistory'] = array_map('trim', explode(',', $row['Vehicle History']));
    }
    
    // Create the car listing
    $post_title = $car_data['year'] . ' ' . $car_data['make'] . ' ' . $car_data['model'];
    
    $post_data = array(
        'post_title' => $post_title,
        'post_content' => '',
        'post_status' => 'pending',
        'post_type' => 'car',
        'post_author' => get_current_user_id(),
    );
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        return array(
            'success' => false,
            'error' => 'Failed to create listing: ' . $post_id->get_error_message()
        );
    }
    
    // Save all the car data as custom fields
    foreach ($car_data as $field_key => $field_value) {
        if ($field_key === 'extras' || $field_key === 'vehiclehistory') {
            update_field($field_key, $field_value, $post_id);
        } else {
            update_field($field_key, $field_value, $post_id);
        }
    }
    
    return array(
        'success' => true,
        'post_id' => $post_id
    );
}

/**
 * Generate CSV template for bulk import
 */
function generate_bulk_import_template() {
    $headers = array(
        // Basic Details (Required)
        'Make', 'Model', 'Year', 'Mileage', 'Price', 'Description',
        
        // Location (Optional)
        'City', 'District', 'Address', 'Latitude', 'Longitude', 'Availability',
        
        // Engine & Performance (Required)
        'Engine Capacity', 'Fuel Type', 'Transmission', 'Drive Type', 'Horsepower',
        
        // Body & Design (Required)
        'Body Type', 'Number of Doors', 'Number of Seats', 'Exterior Color', 'Interior Color',
        
        // Registration & Background (Required/Optional)
        'MOT Until', 'Number of Owners', 'Is Antique',
        
        // Arrays (Optional - comma separated)
        'Vehicle History', 'Extras'
    );
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="car_import_template.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write example row
    $example_row = array(
        // Basic Details
        'BMW', 'X5', '2020', '50000', '35000', 'Excellent condition, full service history',
        
        // Location
        'Dublin', 'Dublin City', '123 Main Street, Dublin', '53.3498', '-6.2603', 'In Stock',
        
        // Engine & Performance
        '3.0', 'Diesel', 'Automatic', 'All-Wheel Drive', '265',
        
        // Body & Design
        'SUV', '5', '5', 'Black', 'Black',
        
        // Registration & Background
        '2025-06', '1', '0',
        
        // Arrays (comma separated)
        'no_accidents,regular_maintenance,clear_title', 'leather_seats,sunroof,parking_sensors'
    );
    fputcsv($output, $example_row);
    
    fclose($output);
    exit;
}

// Hook the functions
add_action('admin_post_bulk_car_import', 'handle_bulk_car_import');
add_action('admin_post_download_import_template', 'generate_bulk_import_template'); 