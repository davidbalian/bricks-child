<?php
/**
 * CSV Car Import Admin Page
 * Provides preview, edit, and selective import functionality for car listings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load if we have admin access and the function exists
if (!function_exists('add_management_page')) {
    return;
}

/**
 * Add CSV Car Import menu item to WordPress admin
 */
function add_csv_car_import_menu() {
    add_management_page(
        'CSV Car Import',
        'Import Cars',
        'manage_options',
        'csv-car-import',
        'csv_car_import_page'
    );
}
add_action('admin_menu', 'add_csv_car_import_menu');

/**
 * Display the CSV import page
 */
function csv_car_import_page() {
    // Handle form submissions
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'preview_csv' && isset($_FILES['csv_file'])) {
            handle_csv_preview();
            return;
        } elseif ($_POST['action'] === 'import_selected' && isset($_POST['selected_cars'])) {
            handle_selective_import();
            return;
        }
    }
    
    // Show upload form
    show_upload_form();
}

/**
 * Show the initial upload form
 */
function show_upload_form() {
    echo '<div class="wrap">';
    echo '<h1>CSV Car Import</h1>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<table class="form-table">';
    echo '<tr><th>CSV File</th><td><input type="file" name="csv_file" accept=".csv" required></td></tr>';
    echo '<tr><th>Post Author</th><td>';
    wp_dropdown_users(array(
        'name' => 'default_author',
        'selected' => get_current_user_id()
    ));
    echo '</td></tr></table>';
    echo '<input type="hidden" name="action" value="preview_csv">';
    wp_nonce_field('csv_import', 'csv_nonce');
    echo '<p><input type="submit" class="button-primary" value="Preview Import"></p>';
    echo '</form></div>';
}

/**
 * Handle CSV preview
 */
function handle_csv_preview() {
    if (!wp_verify_nonce($_POST['csv_nonce'], 'csv_import')) {
        wp_die('Security check failed');
    }
    
    $file = $_FILES['csv_file']['tmp_name'];
    $author = intval($_POST['default_author']);
    
    if (empty($file)) {
        echo '<div class="error"><p>No file uploaded</p></div>';
        show_upload_form();
        return;
    }
    
    $cars = array();
    if (($handle = fopen($file, "r")) !== FALSE) {
        $headers = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) === count($headers)) {
                $cars[] = array_combine($headers, $row);
            }
        }
        fclose($handle);
    }
    
    if (empty($cars)) {
        echo '<div class="error"><p>No valid data found</p></div>';
        show_upload_form();
        return;
    }
    
    show_preview($cars, $author);
}

/**
 * Show preview interface with editable cars
 */
function show_preview($cars, $author) {
    echo '<div class="wrap">';
    echo '<h1>CSV Import Preview - ' . count($cars) . ' cars found</h1>';
    
    echo '<form method="post" id="import-form">';
    echo '<p><button type="button" onclick="selectAll()">Select All</button> ';
    echo '<input type="submit" class="button-primary" value="Import Selected Cars"></p>';
    
    echo '<input type="hidden" name="action" value="import_selected">';
    echo '<input type="hidden" name="author_id" value="' . $author . '">';
    wp_nonce_field('csv_import', 'csv_nonce');
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th><input type="checkbox" id="select-all-checkbox"></th>';
    echo '<th>Car</th><th>Price</th><th>Year</th><th>Mileage</th><th>Issues</th><th>Actions</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($cars as $index => $car) {
        $issues = validate_car($car);
        $has_errors = !empty($issues['errors']);
        
        echo '<tr class="car-row" data-index="' . $index . '">';
        echo '<td><input type="checkbox" name="selected_cars[]" value="' . $index . '" ' . ($has_errors ? 'disabled' : '') . '></td>';
        echo '<td><strong>' . esc_html($car['year'] . ' ' . $car['make'] . ' ' . $car['model']) . '</strong>';
        if ($has_errors) echo '<br><span style="color:red;">HAS ERRORS</span>';
        echo '</td>';
        echo '<td>€' . number_format($car['price']) . '</td>';
        echo '<td>' . $car['year'] . '</td>';
        echo '<td>' . number_format($car['mileage']) . ' km</td>';
        echo '<td>';
        if (!empty($issues['errors'])) {
            echo '<span style="color:red;">Errors: ' . implode(', ', $issues['errors']) . '</span><br>';
        }
        if (!empty($issues['warnings'])) {
            echo '<span style="color:orange;">Warnings: ' . implode(', ', $issues['warnings']) . '</span>';
        }
        echo '</td>';
        echo '<td><button type="button" onclick="editCar(' . $index . ')" class="button">Edit</button></td>';
        echo '</tr>';
        
        // Hidden edit form
        echo '<tr class="edit-row" id="edit-' . $index . '" style="display:none;">';
        echo '<td colspan="7">';
        render_edit_form($car, $index, $author);
        echo '</td></tr>';
        
        // Store original data
        foreach ($car as $key => $value) {
            echo '<input type="hidden" name="cars[' . $index . '][' . $key . ']" value="' . esc_attr($value) . '" id="data_' . $index . '_' . $key . '">';
        }
    }
    
    echo '</tbody></table>';
    echo '</form>';
    
    echo '<script>
    function selectAll() {
        var checkboxes = document.querySelectorAll("input[name=\'selected_cars[]\']:not(:disabled)");
        var allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }
    
    function editCar(index) {
        var editRow = document.getElementById("edit-" + index);
        editRow.style.display = editRow.style.display === "none" ? "" : "none";
    }
    
    function saveCar(index) {
        var form = document.getElementById("edit-form-" + index);
        var formData = new FormData(form);
        
        // Update hidden fields
        for (var pair of formData.entries()) {
            var fieldName = pair[0].replace("edit_" + index + "_", "");
            var hiddenField = document.getElementById("data_" + index + "_" + fieldName);
            if (hiddenField) {
                hiddenField.value = pair[1];
            }
        }
        
        alert("Changes saved! Click Import Selected Cars to proceed.");
    }
    </script>';
    
    echo '</div>';
}

/**
 * Render car edit form
 */
function render_edit_form($car, $index, $author) {
    echo '<div style="padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">';
    echo '<h3>Edit Car Details</h3>';
    echo '<form id="edit-form-' . $index . '">';
    
    echo '<table class="form-table">';
    echo '<tr><td><label>Make:</label></td><td><input type="text" name="edit_' . $index . '_make" value="' . esc_attr($car['make']) . '" required></td>';
    echo '<td><label>Model:</label></td><td><input type="text" name="edit_' . $index . '_model" value="' . esc_attr($car['model']) . '" required></td></tr>';
    echo '<tr><td><label>Year:</label></td><td><input type="number" name="edit_' . $index . '_year" value="' . esc_attr($car['year']) . '" required></td>';
    echo '<td><label>Price:</label></td><td><input type="number" name="edit_' . $index . '_price" value="' . esc_attr($car['price']) . '" required></td></tr>';
    echo '<tr><td><label>Mileage:</label></td><td><input type="number" name="edit_' . $index . '_mileage" value="' . esc_attr($car['mileage']) . '"></td>';
    echo '<td><label>Drive Type:</label></td><td><select name="edit_' . $index . '_drive_type">';
    $drive_types = array('Front-Wheel Drive', 'Rear-Wheel Drive', 'All-Wheel Drive');
    foreach ($drive_types as $type) {
        $selected = ($car['drive_type'] === $type) ? 'selected' : '';
        echo '<option value="' . $type . '" ' . $selected . '>' . $type . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><td><label>Author:</label></td><td>';
    wp_dropdown_users(array(
        'name' => 'edit_' . $index . '_post_author',
        'selected' => $author
    ));
    echo '</td><td colspan="2"></td></tr>';
    echo '</table>';
    
    echo '<p><button type="button" onclick="saveCar(' . $index . ')" class="button-primary">Save Changes</button></p>';
    echo '</form></div>';
}

/**
 * Validate car data
 */
function validate_car($car) {
    $errors = array();
    $warnings = array();
    
    if (empty($car['make'])) $errors[] = 'Missing make';
    if (empty($car['model'])) $errors[] = 'Missing model';
    if (empty($car['year'])) $errors[] = 'Missing year';
    if (empty($car['price'])) $errors[] = 'Missing price';
    
    if (!empty($car['year']) && ($car['year'] < 1900 || $car['year'] > date('Y') + 1)) {
        $errors[] = 'Invalid year';
    }
    
    if (!empty($car['price']) && $car['price'] <= 0) {
        $errors[] = 'Invalid price';
    }
    
    // Drive type warnings
    if ($car['drive_type'] === 'Front-Wheel Drive') {
        if (strtolower($car['make']) === 'bmw' && strpos(strtolower($car['description']), 'xdrive') !== false) {
            $warnings[] = 'BMW xDrive should be AWD';
        }
        if (strtolower($car['make']) === 'rolls-royce') {
            $warnings[] = 'Rolls-Royce should be RWD';
        }
    }
    
    return array('errors' => $errors, 'warnings' => $warnings);
}

/**
 * Handle selective import
 */
function handle_selective_import() {
    if (!wp_verify_nonce($_POST['csv_nonce'], 'csv_import')) {
        wp_die('Security check failed');
    }
    
    if (empty($_POST['selected_cars']) || empty($_POST['cars'])) {
        echo '<div class="error"><p>No cars selected for import</p></div>';
        return;
    }
    
    $cars = $_POST['cars'];
    $selected = $_POST['selected_cars'];
    $author = intval($_POST['author_id']);
    
    $imported = 0;
    $errors = array();
    
    echo '<div class="wrap"><h1>Import Results</h1>';
    
    foreach ($selected as $index) {
        if (!isset($cars[$index])) continue;
        
        $car = $cars[$index];
        $result = import_car($car, $author);
        
        if ($result['success']) {
            $imported++;
            echo '<p style="color:green;">✓ Imported: ' . $car['year'] . ' ' . $car['make'] . ' ' . $car['model'] . '</p>';
        } else {
            $errors[] = $result['error'];
            echo '<p style="color:red;">✗ Failed: ' . $car['year'] . ' ' . $car['make'] . ' ' . $car['model'] . ' - ' . $result['error'] . '</p>';
        }
    }
    
    echo '<h3>Summary</h3>';
    echo '<p>Imported: ' . $imported . ' cars</p>';
    echo '<p>Failed: ' . count($errors) . ' cars</p>';
    
    echo '<p><a href="' . admin_url('edit.php?post_type=car') . '" class="button-primary">View Cars</a></p>';
    echo '<p><a href="' . admin_url('tools.php?page=csv-car-import') . '" class="button">Import More</a></p>';
    echo '</div>';
}

/**
 * Import a single car
 */
function import_car($car, $author) {
    try {
        $title = $car['year'] . ' ' . $car['make'] . ' ' . $car['model'];
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => $car['description'],
            'post_status' => 'pending',
            'post_type' => 'car',
            'post_author' => $author
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return array('success' => false, 'error' => $post_id->get_error_message());
        }
        
        // Update ACF fields (only if ACF is available)
        if (function_exists('update_field')) {
            $fields = array('make', 'model', 'year', 'price', 'mileage', 'fuel_type', 'transmission', 
                           'body_type', 'engine_capacity', 'hp', 'drive_type', 'exterior_color', 
                           'interior_color', 'number_of_doors', 'number_of_seats', 'motuntil', 'description');
            
            foreach ($fields as $field) {
                if (isset($car[$field]) && $car[$field] !== '') {
                    $value = $car[$field];
                    
                    if (in_array($field, array('year', 'price', 'mileage', 'hp', 'number_of_doors', 'number_of_seats'))) {
                        $value = intval(str_replace(',', '', $value));
                    }
                    if ($field === 'engine_capacity') {
                        $value = floatval($value);
                    }
                    
                    update_field($field, $value, $post_id);
                }
            }
        } else {
            // Fallback: store as post meta if ACF is not available
            $fields = array('make', 'model', 'year', 'price', 'mileage', 'fuel_type', 'transmission', 
                           'body_type', 'engine_capacity', 'hp', 'drive_type', 'exterior_color', 
                           'interior_color', 'number_of_doors', 'number_of_seats', 'motuntil', 'description');
            
            foreach ($fields as $field) {
                if (isset($car[$field]) && $car[$field] !== '') {
                    $value = $car[$field];
                    
                    if (in_array($field, array('year', 'price', 'mileage', 'hp', 'number_of_doors', 'number_of_seats'))) {
                        $value = intval(str_replace(',', '', $value));
                    }
                    if ($field === 'engine_capacity') {
                        $value = floatval($value);
                    }
                    
                    update_post_meta($post_id, $field, $value);
                }
            }
        }
        
        // Handle extras
        if (!empty($car['extras'])) {
            $extras = $car['extras'];
            if (is_string($extras) && strpos($extras, '[') === 0) {
                $extras = str_replace(array('[', ']', "'", '"'), '', $extras);
                $extras = array_map('trim', explode(',', $extras));
                $extras = array_filter($extras);
            }
            
            if (is_array($extras)) {
                if (function_exists('update_field')) {
                    update_field('extras', $extras, $post_id);
                } else {
                    update_post_meta($post_id, 'extras', $extras);
                }
            }
        }
        
        // Additional fields
        if (!empty($car['num_owners'])) {
            if (function_exists('update_field')) {
                update_field('numowners', intval($car['num_owners']), $post_id);
            } else {
                update_post_meta($post_id, 'numowners', intval($car['num_owners']));
            }
        }
        
        if (!empty($car['is_antique'])) {
            $is_antique = ($car['is_antique'] === 'True' || $car['is_antique'] === '1');
            if (function_exists('update_field')) {
                update_field('isantique', $is_antique ? 1 : 0, $post_id);
            } else {
                update_post_meta($post_id, 'isantique', $is_antique ? 1 : 0);
            }
        }
        
        // Auto-assign taxonomy (only if function exists)
        if (function_exists('do_action')) {
            do_action('acf/save_post', $post_id);
        }
        
        return array('success' => true, 'post_id' => $post_id);
        
    } catch (Exception $e) {
        return array('success' => false, 'error' => $e->getMessage());
    }
} 