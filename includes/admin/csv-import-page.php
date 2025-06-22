<?php
/**
 * CSV Import Page for Car Listings
 * Simple approach: Import CSV data directly with intelligent mapping
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function render_csv_import_page() {
    ?>
    <div class="wrap">
        <h1>Import Car Listings from CSV</h1>
        
        <?php
        // Handle CSV upload and processing
        if (isset($_POST['process_csv']) && wp_verify_nonce($_POST['csv_nonce'], 'process_csv')) {
            process_csv_import();
        } else {
            show_csv_upload_form();
        }
        ?>
    </div>
    <?php
}

function show_csv_upload_form() {
    ?>
    <div class="csv-import-form">
        <h2>Upload Your CSV File</h2>
        <p>Upload a CSV file with your car listings. The system will automatically map and validate the data.</p>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('process_csv', 'csv_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">CSV File</th>
                    <td>
                        <input type="file" name="csv_file" accept=".csv" required>
                        <p class="description">Select your car listings CSV file</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Post Author</th>
                    <td>
                        <select name="post_author" required>
                            <option value="">Select who will be the author...</option>
                            <option value="<?php echo get_current_user_id(); ?>">Me (<?php echo wp_get_current_user()->display_name; ?>)</option>
                            <?php
                            // Get all users who can publish posts
                            $users = get_users([
                                'capability' => 'publish_posts',
                                'orderby' => 'display_name',
                                'order' => 'ASC'
                            ]);
                            
                            foreach ($users as $user) {
                                if ($user->ID != get_current_user_id()) {
                                    $role_names = array_map('ucfirst', $user->roles);
                                    echo '<option value="' . $user->ID . '">' . 
                                         esc_html($user->display_name) . ' (' . implode(', ', $role_names) . ')</option>';
                                }
                            }
                            ?>
                        </select>
                        <p class="description">All imported cars will be assigned to this author</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Import Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="create_drafts" value="1" checked>
                            Import as drafts (recommended for review)
                        </label><br>
                        <label>
                            <input type="checkbox" name="skip_duplicates" value="1" checked>
                            Skip duplicate cars (same make, model, year, mileage)
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="process_csv" class="button-primary" value="Process CSV Import">
            </p>
        </form>
        
        <div class="csv-format-info">
            <h3>Expected CSV Format</h3>
            <p>Your CSV should have these columns:</p>
            <code>make,model,year,price,mileage,fuel_type,transmission,body_type,engine_capacity,hp,drive_type,exterior_color,interior_color,number_of_doors,number_of_seats,motuntil,extras,description,availability,post_status,num_owners,is_antique</code>
        </div>
    </div>
    
    <style>
    .csv-format-info {
        background: #f0f0f1;
        border: 1px solid #c3c4c7;
        padding: 15px;
        margin-top: 20px;
    }
    .csv-format-info code {
        display: block;
        background: white;
        padding: 10px;
        margin-top: 10px;
        word-break: break-all;
    }
    </style>
    <?php
}

function process_csv_import() {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error"><p>Error uploading file.</p></div>';
        return;
    }
    
    $csv_file = $_FILES['csv_file']['tmp_name'];
    $create_drafts = isset($_POST['create_drafts']);
    $skip_duplicates = isset($_POST['skip_duplicates']);
    $post_author = intval($_POST['post_author']);
    
    // Validate author selection
    if (empty($post_author)) {
        echo '<div class="notice notice-error"><p>Please select a post author.</p></div>';
        return;
    }
    
    // Parse CSV
    $csv_data = parse_csv_file($csv_file);
    
    if (empty($csv_data)) {
        echo '<div class="notice notice-error"><p>No valid data found in CSV file.</p></div>';
        return;
    }
    
    // Process imports
    $results = import_cars_from_csv($csv_data, $create_drafts, $skip_duplicates, $post_author);
    
    // Show results
    show_import_results($results);
}

function parse_csv_file($file_path) {
    $csv_data = [];
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        $headers = fgetcsv($handle); // Get headers
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) === count($headers)) {
                $csv_data[] = array_combine($headers, $data);
            }
        }
        fclose($handle);
    }
    
    return $csv_data;
}

function import_cars_from_csv($csv_data, $create_drafts = true, $skip_duplicates = true, $post_author = null) {
    $results = [
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0,
        'details' => []
    ];
    
    foreach ($csv_data as $index => $row) {
        try {
            // Check for duplicates
            if ($skip_duplicates && is_duplicate_car($row)) {
                $results['skipped']++;
                $results['details'][] = "Row " . ($index + 1) . ": Skipped duplicate - {$row['year']} {$row['make']} {$row['model']}";
                continue;
            }
            
            // Create car post
            $post_id = create_car_listing($row, $create_drafts, $post_author);
            
            if ($post_id) {
                $results['imported']++;
                $results['details'][] = "Row " . ($index + 1) . ": Imported - {$row['year']} {$row['make']} {$row['model']} (ID: $post_id)";
            } else {
                $results['errors']++;
                $results['details'][] = "Row " . ($index + 1) . ": Failed to create - {$row['year']} {$row['make']} {$row['model']}";
            }
            
        } catch (Exception $e) {
            $results['errors']++;
            $results['details'][] = "Row " . ($index + 1) . ": Error - " . $e->getMessage();
        }
    }
    
    return $results;
}

function is_duplicate_car($row) {
    $args = [
        'post_type' => 'car',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'make',
                'value' => $row['make'],
                'compare' => '='
            ],
            [
                'key' => 'model',
                'value' => $row['model'],
                'compare' => '='
            ],
            [
                'key' => 'year',
                'value' => $row['year'],
                'compare' => '='
            ],
            [
                'key' => 'mileage',
                'value' => $row['mileage'],
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ];
    
    $existing = get_posts($args);
    return !empty($existing);
}

function create_car_listing($row, $create_drafts = true, $post_author = null) {
    // Use provided author or fallback to current user
    $author_id = $post_author ? $post_author : get_current_user_id();
    
    // Create the post
    $post_data = [
        'post_title' => trim($row['year'] . ' ' . $row['make'] . ' ' . $row['model']),
        'post_content' => sanitize_textarea_field($row['description']),
        'post_status' => $create_drafts ? 'draft' : 'publish',
        'post_type' => 'car',
        'post_author' => $author_id
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        throw new Exception('Failed to create post: ' . $post_id->get_error_message());
    }
    
    // Add custom fields
    $fields_mapping = [
        'make' => 'make',
        'model' => 'model', 
        'year' => 'year',
        'price' => 'price',
        'mileage' => 'mileage',
        'fuel_type' => 'fuel_type',
        'transmission' => 'transmission',
        'body_type' => 'body_type',
        'engine_capacity' => 'engine_capacity',
        'hp' => 'hp',
        'drive_type' => 'drive_type',
        'exterior_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'number_of_doors' => 'number_of_doors',
        'number_of_seats' => 'number_of_seats',
        'motuntil' => 'motuntil',
        'availability' => 'availability',
        'num_owners' => 'num_owners',
        'is_antique' => 'is_antique'
    ];
    
    foreach ($fields_mapping as $csv_field => $meta_key) {
        if (isset($row[$csv_field]) && !empty($row[$csv_field])) {
            update_field($meta_key, sanitize_text_field($row[$csv_field]), $post_id);
        }
    }
    
    // Handle extras (convert string array to actual array)
    if (isset($row['extras']) && !empty($row['extras'])) {
        $extras = $row['extras'];
        
        // If it's a string representation of an array, convert it
        if (strpos($extras, '[') === 0) {
            $extras = str_replace(['[', ']', "'", '"'], '', $extras);
            $extras = explode(',', $extras);
            $extras = array_map('trim', $extras);
            $extras = array_filter($extras); // Remove empty values
        }
        
        update_field('extras', $extras, $post_id);
    }
    
    return $post_id;
}

function show_import_results($results) {
    ?>
    <div class="import-results">
        <h2>Import Results</h2>
        
        <div class="results-summary">
            <div class="result-stat success">
                <strong><?php echo $results['imported']; ?></strong>
                <span>Cars Imported</span>
            </div>
            <div class="result-stat warning">
                <strong><?php echo $results['skipped']; ?></strong>
                <span>Cars Skipped</span>
            </div>
            <div class="result-stat error">
                <strong><?php echo $results['errors']; ?></strong>
                <span>Errors</span>
            </div>
        </div>
        
        <?php if ($results['imported'] > 0): ?>
            <div class="notice notice-success">
                <p><strong>Success!</strong> <?php echo $results['imported']; ?> cars have been imported.</p>
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=car&post_status=draft'); ?>" class="button">
                        Review Draft Listings
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=car'); ?>" class="button">
                        View All Listings
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="import-details">
            <h3>Detailed Results</h3>
            <div class="details-log">
                <?php foreach ($results['details'] as $detail): ?>
                    <div class="log-entry"><?php echo esc_html($detail); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <style>
    .results-summary {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .result-stat {
        background: white;
        border: 1px solid #ccd0d4;
        padding: 20px;
        text-align: center;
        border-radius: 4px;
        min-width: 120px;
    }
    .result-stat strong {
        display: block;
        font-size: 24px;
        margin-bottom: 5px;
    }
    .result-stat.success { border-left: 4px solid #00a32a; }
    .result-stat.warning { border-left: 4px solid #dba617; }
    .result-stat.error { border-left: 4px solid #d63638; }
    .details-log {
        background: #f6f7f7;
        border: 1px solid #c3c4c7;
        padding: 15px;
        max-height: 400px;
        overflow-y: auto;
        font-family: monospace;
        font-size: 12px;
    }
    .log-entry {
        margin-bottom: 5px;
        padding: 2px 0;
    }
    </style>
    <?php
}

// Register admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=car',
        'Import CSV',
        'Import CSV', 
        'manage_options',
        'csv-import',
        'render_csv_import_page'
    );
});
?> 