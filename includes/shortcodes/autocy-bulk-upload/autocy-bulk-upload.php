<?php
if (!defined('ABSPATH')) { exit; }

add_shortcode('autocy_bulk_upload', 'autocy_bulk_upload_shortcode');

function autocy_bulk_upload_shortcode($atts) {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        return '<p>You must be logged in with appropriate permissions to use the bulk uploader.</p>';
    }

    autocy_bulk_upload_enqueue_assets();

    ob_start();
    ?>
    <div id="bulk-upload-app">
        <!-- Phase 1: Images + Location -->
        <div class="bu-phase bu-phase-1 active" data-phase="1">
            <h2>Step 1: Upload Images & Set Location</h2>
            <p class="bu-phase-desc">Upload all images for all listings at once, then pick a shared location.</p>

            <div class="bu-section">
                <h3>Images</h3>
                <div id="bu-upload-area" class="bu-upload-area">
                    <div class="bu-upload-placeholder">
                        <span class="bu-upload-icon">&#128247;</span>
                        <p>Drag & drop images here or <label for="bu-file-input" class="bu-browse-link">browse</label></p>
                        <p class="bu-upload-hint">Upload all images for all listings. Name files like <code>image_folder_01.jpg</code></p>
                    </div>
                    <input type="file" id="bu-file-input" multiple accept="image/*" style="display:none;">
                </div>
                <div id="bu-upload-stats" class="bu-upload-stats" style="display:none;">
                    <span id="bu-uploaded-count">0</span> images uploaded
                    <span id="bu-uploading-count" style="display:none;">(<span id="bu-uploading-num">0</span> uploading...)</span>
                    <span id="bu-error-count" style="display:none;"> | <span id="bu-error-num">0</span> failed</span>
                </div>
            </div>

            <div class="bu-section">
                <h3>Location</h3>
                <form id="bu-location-form">
                    <input type="text" id="location" class="bu-location-display" placeholder="No location selected" readonly>
                    <button type="button" class="choose-location-btn">Choose Location on Map</button>
                </form>
            </div>

            <button type="button" id="bu-continue-btn" class="bu-btn bu-btn-primary" disabled>Continue to CSV Upload</button>
        </div>

        <!-- Phase 2: CSV & Preview -->
        <div class="bu-phase bu-phase-2" data-phase="2">
            <h2>Step 2: Upload CSV & Review Listings</h2>

            <div class="bu-section">
                <label for="bu-csv-input" class="bu-btn bu-btn-secondary">Choose CSV File</label>
                <input type="file" id="bu-csv-input" accept=".csv" style="display:none;">
                <span id="bu-csv-filename"></span>
            </div>

            <div id="bu-summary" class="bu-summary" style="display:none;">
                <span id="bu-total-listings">0</span> listings parsed |
                <span class="bu-valid-text"><span id="bu-valid-count">0</span> valid</span> |
                <span class="bu-error-text"><span id="bu-error-listing-count">0</span> with errors (will be skipped)</span>
            </div>

            <div id="bu-slider" class="bu-slider" style="display:none;">
                <div class="bu-slider-nav">
                    <button type="button" id="bu-prev-btn" class="bu-nav-btn" disabled>&larr; Previous</button>
                    <span id="bu-slider-counter" class="bu-slider-counter">Listing 1 of 1</span>
                    <button type="button" id="bu-next-btn" class="bu-nav-btn">Next &rarr;</button>
                </div>
                <div id="bu-listing-card" class="bu-listing-card"></div>
            </div>

            <div class="bu-phase-actions">
                <button type="button" id="bu-back-btn" class="bu-btn bu-btn-secondary">Back</button>
                <button type="button" id="bu-submit-btn" class="bu-btn bu-btn-primary" disabled>Submit Valid Listings</button>
            </div>
        </div>

        <!-- Phase 3: Progress & Results -->
        <div class="bu-phase bu-phase-3" data-phase="3">
            <h2>Step 3: Creating Listings</h2>

            <div class="bu-progress-wrapper">
                <div class="bu-progress-bar">
                    <div id="bu-progress-fill" class="bu-progress-fill" style="width:0%"></div>
                </div>
                <div id="bu-progress-text" class="bu-progress-text">0 / 0</div>
            </div>

            <div id="bu-results" class="bu-results"></div>

            <div id="bu-done-actions" class="bu-done-actions" style="display:none;">
                <p id="bu-done-summary"></p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function autocy_bulk_upload_enqueue_assets() {
    static $enqueued = false;
    if ($enqueued) return;
    $enqueued = true;

    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();

    // Google Maps + Location Picker (replicating pattern from google-maps-assets.php)
    $google_maps_url = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode(GOOGLE_MAPS_API_KEY) . '&libraries=places&language=en';
    wp_enqueue_script('google-maps', $google_maps_url, [], null, true);

    wp_enqueue_style('autoagora-location-picker', $theme_uri . '/assets/css/location-picker.css', [], filemtime($theme_dir . '/assets/css/location-picker.css'));
    wp_enqueue_script('autoagora-location-picker', $theme_uri . '/assets/js/location-picker.js', ['jquery', 'google-maps'], filemtime($theme_dir . '/assets/js/location-picker.js'), true);
    wp_localize_script('autoagora-location-picker', 'mapConfig', [
        'defaultLat' => 35.1856,
        'defaultLng' => 33.3823,
        'zoom' => 8,
        'debug' => (strpos($_SERVER['HTTP_HOST'], 'staging') !== false || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false)
    ]);

    // ImageOptimizer
    wp_enqueue_script('image-optimizer', $theme_uri . '/includes/user-manage-listings/image-optimization.js', [], filemtime($theme_dir . '/includes/user-manage-listings/image-optimization.js'), true);

    // AsyncUploadManager + localized config
    wp_enqueue_script('async-uploads', $theme_uri . '/includes/core/async-uploads.js', ['jquery'], filemtime($theme_dir . '/includes/core/async-uploads.js'), true);
    wp_localize_script('async-uploads', 'asyncUploads', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('async_upload_nonce'),
        'userId' => get_current_user_id(),
        'maxFileSize' => 12 * 1024 * 1024,
        'allowedTypes' => ['image/jpeg', 'image/jfif', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp']
    ]);

    // Bulk upload own assets
    wp_enqueue_style('autocy-bulk-upload', $theme_uri . '/includes/shortcodes/autocy-bulk-upload/autocy-bulk-upload.css', [], filemtime($theme_dir . '/includes/shortcodes/autocy-bulk-upload/autocy-bulk-upload.css'));
    wp_enqueue_script('autocy-bulk-upload', $theme_uri . '/includes/shortcodes/autocy-bulk-upload/autocy-bulk-upload.js', ['jquery', 'async-uploads', 'image-optimizer', 'autoagora-location-picker'], filemtime($theme_dir . '/includes/shortcodes/autocy-bulk-upload/autocy-bulk-upload.js'), true);
    wp_localize_script('autocy-bulk-upload', 'autocyBulkUploadConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('autocy_bulk_upload_nonce'),
    ]);
}

// AJAX handler: create one listing
add_action('wp_ajax_autocy_bulk_upload_create_listing', 'handle_autocy_bulk_upload_create_listing');

function handle_autocy_bulk_upload_create_listing() {
    check_ajax_referer('autocy_bulk_upload_nonce', 'nonce');

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Sanitize fields
    $make           = sanitize_text_field($_POST['make'] ?? '');
    $model          = sanitize_text_field($_POST['model'] ?? '');
    $year           = sanitize_text_field($_POST['year'] ?? '');
    $price          = sanitize_text_field($_POST['price'] ?? '');
    $mileage        = sanitize_text_field($_POST['mileage'] ?? '');
    $fuel_type      = sanitize_text_field($_POST['fuel_type'] ?? '');
    $transmission   = sanitize_text_field($_POST['transmission'] ?? '');
    $engine_capacity = sanitize_text_field($_POST['engine_capacity'] ?? '');
    $body_type      = sanitize_text_field($_POST['body_type'] ?? '');
    $drive_type     = sanitize_text_field($_POST['drive_type'] ?? '');
    $exterior_color = sanitize_text_field($_POST['exterior_color'] ?? '');
    $interior_color = sanitize_text_field($_POST['interior_color'] ?? '');
    $number_of_doors = sanitize_text_field($_POST['number_of_doors'] ?? '');
    $number_of_seats = sanitize_text_field($_POST['number_of_seats'] ?? '');
    $availability   = sanitize_text_field($_POST['availability'] ?? '');
    $description    = sanitize_textarea_field($_POST['description'] ?? '');
    $extras_raw     = sanitize_text_field($_POST['extras'] ?? '');
    $attachment_ids = isset($_POST['attachment_ids']) ? array_map('intval', (array) $_POST['attachment_ids']) : [];

    // Location fields
    $car_city       = sanitize_text_field($_POST['car_city'] ?? '');
    $car_district   = sanitize_text_field($_POST['car_district'] ?? '');
    $car_latitude   = sanitize_text_field($_POST['car_latitude'] ?? '');
    $car_longitude  = sanitize_text_field($_POST['car_longitude'] ?? '');
    $car_address    = sanitize_text_field($_POST['car_address'] ?? '');

    // Validate required fields
    if (empty($make) || empty($model) || empty($year) || empty($price)) {
        wp_send_json_error(['message' => 'Missing required fields (make, model, year, price)']);
    }

    $title = intval($year) . ' ' . $make . ' ' . $model;

    $post_id = wp_insert_post([
        'post_title'   => $title,
        'post_content' => $description,
        'post_status'  => 'pending',
        'post_type'    => 'car',
        'post_author'  => get_current_user_id(),
    ]);

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => $post_id->get_error_message()]);
    }

    // Update ACF fields
    if (function_exists('update_field')) {
        $int_fields = ['year', 'price', 'mileage', 'hp', 'number_of_doors', 'number_of_seats'];
        $float_fields = ['engine_capacity'];

        $car_data = [
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'price' => $price,
            'mileage' => $mileage,
            'fuel_type' => $fuel_type,
            'transmission' => $transmission,
            'engine_capacity' => $engine_capacity,
            'body_type' => $body_type,
            'drive_type' => $drive_type,
            'exterior_color' => $exterior_color,
            'interior_color' => $interior_color,
            'number_of_doors' => $number_of_doors,
            'number_of_seats' => $number_of_seats,
            'description' => $description,
            'availability' => $availability,
        ];

        foreach ($car_data as $field => $value) {
            if ($value !== '') {
                if (in_array($field, $int_fields)) {
                    $value = intval(str_replace(',', '', $value));
                } elseif (in_array($field, $float_fields)) {
                    $value = floatval($value);
                }
                update_field($field, $value, $post_id);
            }
        }

        // Extras
        if (!empty($extras_raw)) {
            $extras = array_map('trim', explode(',', $extras_raw));
            $extras = array_filter($extras);
            if (!empty($extras)) {
                update_field('extras', $extras, $post_id);
            }
        }

        // Location fields
        if (!empty($car_city)) update_field('car_city', $car_city, $post_id);
        if (!empty($car_district)) update_field('car_district', $car_district, $post_id);
        if (!empty($car_latitude)) update_field('car_latitude', $car_latitude, $post_id);
        if (!empty($car_longitude)) update_field('car_longitude', $car_longitude, $post_id);
        if (!empty($car_address)) update_field('car_address', $car_address, $post_id);

        // Assign images
        if (!empty($attachment_ids)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'temp_uploads';

            update_field('car_images', $attachment_ids, $post_id);

            foreach ($attachment_ids as $att_id) {
                // Set post_parent
                wp_update_post(['ID' => $att_id, 'post_parent' => $post_id]);

                // Mark completed in temp_uploads
                $wpdb->update(
                    $table_name,
                    ['status' => 'completed'],
                    [
                        'attachment_id' => $att_id,
                        'user_id' => get_current_user_id(),
                        'status' => 'pending',
                    ],
                    ['%s'],
                    ['%d', '%d', '%s']
                );

                // WebP conversion
                if (function_exists('convert_to_webp_with_fallback')) {
                    convert_to_webp_with_fallback($att_id);
                }
            }
        }
    }

    // Trigger taxonomy assignment
    do_action('acf/save_post', $post_id);

    wp_send_json_success([
        'post_id' => $post_id,
        'title' => $title,
    ]);
}
