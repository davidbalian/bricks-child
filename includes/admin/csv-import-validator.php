<?php
/**
 * CSV Import Validator
 * Shows only cars/fields that need manual review
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CSV_Import_Validator {
    
    private $validation_rules;
    private $make_models;
    
    public function __construct() {
        $this->load_validation_rules();
        $this->load_make_models();
    }
    
    private function load_validation_rules() {
        // Load your website's valid options
        $this->validation_rules = [
            'fuel_type' => ['Petrol', 'Diesel', 'Electric', 'Petrol hybrid', 'Diesel hybrid'],
            'transmission' => ['Automatic', 'Manual'],
            'body_type' => ['Hatchback', 'Saloon', 'Coupe', 'Convertible', 'Estate', 'SUV', 'MPV', 'Pickup'],
            'drive_type' => ['Front-Wheel Drive', 'Rear-Wheel Drive', 'All-Wheel Drive', '4-Wheel Drive'],
            'exterior_color' => ['Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Yellow', 'Brown', 'Beige', 'Orange', 'Purple', 'Gold', 'Bronze']
        ];
    }
    
    private function load_make_models() {
        // Load from your JSON files
        $this->make_models = [];
        $json_dir = get_template_directory() . '/simple_jsons/';
        
        if (is_dir($json_dir)) {
            $files = glob($json_dir . '*.json');
            foreach ($files as $file) {
                $make = basename($file, '.json');
                $make = str_replace('_', '-', $make);
                $make = ucwords($make, '-');
                
                $content = file_get_contents($file);
                $models = json_decode($content, true);
                
                if ($models) {
                    $this->make_models[$make] = array_keys($models);
                }
            }
        }
    }
    
    public function validate_csv_data($csv_data) {
        $issues = [];
        
        foreach ($csv_data as $index => $row) {
            $car_issues = [];
            
            // Check make/model validity
            if (!isset($this->make_models[$row['make']])) {
                $car_issues['make'] = "Unknown make: " . $row['make'];
            } elseif (!in_array($row['model'], $this->make_models[$row['make']])) {
                $car_issues['model'] = "Unknown model for {$row['make']}: " . $row['model'];
            }
            
            // Check other fields
            foreach ($this->validation_rules as $field => $valid_options) {
                if (isset($row[$field]) && !empty($row[$field]) && !in_array($row[$field], $valid_options)) {
                    $car_issues[$field] = "Invalid {$field}: " . $row[$field];
                }
            }
            
            // Check missing required fields
            $required = ['make', 'model', 'year', 'price', 'mileage'];
            foreach ($required as $req_field) {
                if (empty($row[$req_field])) {
                    $car_issues[$req_field] = "Missing required field";
                }
            }
            
            if (!empty($car_issues)) {
                $issues[$index] = [
                    'data' => $row,
                    'issues' => $car_issues
                ];
            }
        }
        
        return $issues;
    }
    
    public function render_validation_page($csv_data) {
        $issues = $this->validate_csv_data($csv_data);
        
        ?>
        <div class="csv-validation-page">
            <h1>CSV Import Validation</h1>
            
            <?php if (empty($issues)): ?>
                <div class="validation-success">
                    <h2>✅ All Data Valid!</h2>
                    <p>All <?php echo count($csv_data); ?> cars passed validation.</p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('bulk_import_cars', 'bulk_import_nonce'); ?>
                        <input type="hidden" name="action" value="bulk_import_cars">
                        <input type="hidden" name="csv_data" value="<?php echo esc_attr(base64_encode(json_encode($csv_data))); ?>">
                        <button type="submit" class="button button-primary button-large">
                            Import All <?php echo count($csv_data); ?> Cars
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="validation-issues">
                    <h2>⚠️ Found Issues in <?php echo count($issues); ?> Cars</h2>
                    <p>Please review and fix the following issues before importing:</p>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('fix_csv_issues', 'fix_csv_nonce'); ?>
                        <input type="hidden" name="action" value="fix_csv_issues">
                        
                        <?php foreach ($issues as $index => $issue): ?>
                            <div class="car-issue-card">
                                <h3>Car #<?php echo $index + 1; ?>: <?php echo esc_html($issue['data']['year'] . ' ' . $issue['data']['make'] . ' ' . $issue['data']['model']); ?></h3>
                                
                                <div class="issue-fields">
                                    <?php foreach ($issue['issues'] as $field => $problem): ?>
                                        <div class="field-issue">
                                            <label><?php echo ucfirst(str_replace('_', ' ', $field)); ?>:</label>
                                            <span class="issue-description"><?php echo esc_html($problem); ?></span>
                                            
                                            <?php if (isset($this->validation_rules[$field])): ?>
                                                <select name="fixes[<?php echo $index; ?>][<?php echo $field; ?>]">
                                                    <option value="">Select correct value...</option>
                                                    <?php foreach ($this->validation_rules[$field] as $option): ?>
                                                        <option value="<?php echo esc_attr($option); ?>"
                                                            <?php selected($issue['data'][$field], $option); ?>>
                                                            <?php echo esc_html($option); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ($field === 'make'): ?>
                                                <select name="fixes[<?php echo $index; ?>][make]">
                                                    <option value="">Select make...</option>
                                                    <?php foreach (array_keys($this->make_models) as $make): ?>
                                                        <option value="<?php echo esc_attr($make); ?>"
                                                            <?php selected($issue['data']['make'], $make); ?>>
                                                            <?php echo esc_html($make); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ($field === 'model'): ?>
                                                <select name="fixes[<?php echo $index; ?>][model]" class="model-select" data-make="<?php echo esc_attr($issue['data']['make']); ?>">
                                                    <option value="">Select model...</option>
                                                    <?php if (isset($this->make_models[$issue['data']['make']])): ?>
                                                        <?php foreach ($this->make_models[$issue['data']['make']] as $model): ?>
                                                            <option value="<?php echo esc_attr($model); ?>"
                                                                <?php selected($issue['data']['model'], $model); ?>>
                                                                <?php echo esc_html($model); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" name="fixes[<?php echo $index; ?>][<?php echo $field; ?>]" 
                                                       value="<?php echo esc_attr($issue['data'][$field]); ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Hidden fields for non-problematic data -->
                                <?php foreach ($issue['data'] as $field => $value): ?>
                                    <?php if (!isset($issue['issues'][$field])): ?>
                                        <input type="hidden" name="car_data[<?php echo $index; ?>][<?php echo $field; ?>]" 
                                               value="<?php echo esc_attr($value); ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="validation-actions">
                            <button type="submit" class="button button-primary">
                                Fix Issues & Import
                            </button>
                            <button type="button" class="button" onclick="skipProblematic()">
                                Skip Problematic Cars & Import Valid Ones
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .car-issue-card {
            border: 1px solid #ddd;
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
        }
        .field-issue {
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .field-issue label {
            min-width: 120px;
            font-weight: bold;
        }
        .issue-description {
            color: #d63638;
            font-style: italic;
        }
        .validation-success {
            background: #d1edff;
            border: 1px solid #0073aa;
            padding: 20px;
            margin: 20px 0;
        }
        .validation-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        </style>
        
        <script>
        function skipProblematic() {
            if (confirm('Skip cars with issues and only import valid ones?')) {
                // Submit form with skip flag
                const form = document.querySelector('form');
                const skipInput = document.createElement('input');
                skipInput.type = 'hidden';
                skipInput.name = 'skip_problematic';
                skipInput.value = '1';
                form.appendChild(skipInput);
                form.submit();
            }
        }
        </script>
        <?php
    }
}

// Usage in admin page
function render_csv_import_page() {
    if (isset($_POST['csv_data'])) {
        $csv_data = json_decode(base64_decode($_POST['csv_data']), true);
        $validator = new CSV_Import_Validator();
        $validator->render_validation_page($csv_data);
    } else {
        // Show CSV upload form
        ?>
        <div class="wrap">
            <h1>Import Car Listings from CSV</h1>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('upload_csv', 'csv_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">CSV File</th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv" required>
                            <p class="description">Upload your car listings CSV file</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="upload_csv" class="button-primary" value="Upload & Validate">
                </p>
            </form>
        </div>
        <?php
    }
}
?> 