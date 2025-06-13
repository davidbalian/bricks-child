<?php
/**
 * Car Submission Functionality
 * 
 * Handles form submissions for car listings
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Process form submission for adding a new car listing
 */
function handle_add_car_listing() {
    // Initialize variables for error tracking
    $errors = array();
    $required_fields = array(
        'make' => 'Make',
        'model' => 'Model',
        'variant' => 'Variant',
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
        'number_of_seats' => 'Number of Seats'
    );
    
    // Verify nonce
    if (!isset($_POST['add_car_listing_nonce']) || !wp_verify_nonce($_POST['add_car_listing_nonce'], 'add_car_listing_nonce')) {
        wp_redirect(add_query_arg('listing_error', 'nonce_failed', wp_get_referer()));
        exit;
    }
    
    // Check for required fields
    $missing_fields = array();
    foreach ($required_fields as $field_key => $field_label) {
        if (!isset($_POST[$field_key]) || empty(trim($_POST[$field_key]))) {
            $missing_fields[] = $field_key;
        }
    }
    
    if (!empty($missing_fields)) {
        // Redirect back with error message
        $redirect_url = add_query_arg(
            array(
                'error' => 'validation',
                'fields' => implode(',', $missing_fields)
            ),
            wp_get_referer()
        );
        wp_redirect($redirect_url);
        exit;
    }
    
    // Check for async upload session ID
    $async_session_id = isset($_POST['async_session_id']) ? sanitize_text_field($_POST['async_session_id']) : '';
    $async_images = array();
    
    car_submission_log('=== ASYNC UPLOAD DEBUG ===');
    car_submission_log('POST async_session_id: ' . ($async_session_id ? $async_session_id : 'NOT SET'));
    car_submission_log('POST data keys: ' . implode(', ', array_keys($_POST)));
    
    if (!empty($async_session_id)) {
        // Get async uploaded images
        $async_images = get_session_attachment_ids($async_session_id, get_current_user_id());
        car_submission_log('Async session found: ' . $async_session_id . ' with ' . count($async_images) . ' images');
        car_submission_log('Async image IDs: ' . implode(', ', $async_images));
    } else {
        car_submission_log('No async session ID found in POST data');
    }
    
    // Check for images - either async uploads or traditional file uploads
    $has_images = false;
    if (!empty($async_images)) {
        $has_images = true;
        car_submission_log('Using async uploaded images: ' . count($async_images));
    } elseif (isset($_FILES['car_images']) && !empty($_FILES['car_images']['name'][0])) {
        $has_images = true;
        car_submission_log('Using traditional file uploads');
    }
    
    if (!$has_images) {
        wp_redirect(add_query_arg('error', 'no_images', wp_get_referer()));
        exit;
    }
    
    // Sanitize form data
    $make = sanitize_text_field($_POST['make']);
    $model = sanitize_text_field($_POST['model']);
    $variant = sanitize_text_field($_POST['variant']);
    $year = intval($_POST['year']);
    $mileage = intval($_POST['mileage']);
    $price = intval($_POST['price']);
    
    // Process location fields
    $city = isset($_POST['car_city']) ? sanitize_text_field($_POST['car_city']) : '';
    $district = isset($_POST['car_district']) ? sanitize_text_field($_POST['car_district']) : '';
    $latitude = isset($_POST['car_latitude']) ? floatval($_POST['car_latitude']) : 0;
    $longitude = isset($_POST['car_longitude']) ? floatval($_POST['car_longitude']) : 0;
    $address = isset($_POST['car_address']) ? sanitize_text_field($_POST['car_address']) : '';
    
    $engine_capacity = sanitize_text_field($_POST['engine_capacity']);
    $fuel_type = sanitize_text_field($_POST['fuel_type']);
    $transmission = sanitize_text_field($_POST['transmission']);
    $body_type = sanitize_text_field($_POST['body_type']);
    $drive_type = sanitize_text_field($_POST['drive_type']);
    $exterior_color = sanitize_text_field($_POST['exterior_color']);
    $interior_color = sanitize_text_field($_POST['interior_color']);
    $description = wp_kses_post($_POST['description']);
    $number_of_doors = intval($_POST['number_of_doors']);
    $number_of_seats = intval($_POST['number_of_seats']);
    
    // Process MOT status (optional)
    $motuntil = isset($_POST['motuntil']) ? sanitize_text_field($_POST['motuntil']) : '';
    
    // Process extras (checkboxes)
    $extras = isset($_POST['extras']) ? array_map('sanitize_text_field', $_POST['extras']) : array();
    
    // Process ACF fields
    $hp = isset($_POST['hp']) ? intval($_POST['hp']) : '';
    $numowners = isset($_POST['numowners']) ? intval($_POST['numowners']) : '';
    $isantique = isset($_POST['isantique']) ? 1 : 0;
    
    // Process vehicle history as an array of selected options
    $vehiclehistory = array();
    if (isset($_POST['vehiclehistory']) && is_array($_POST['vehiclehistory'])) {
        foreach ($_POST['vehiclehistory'] as $history_item) {
            $vehiclehistory[] = sanitize_text_field($history_item);
        }
    }
    
    // Debug logging
    error_log('Vehicle History POST data: ' . print_r($_POST['vehiclehistory'], true));
    error_log('Processed Vehicle History: ' . print_r($vehiclehistory, true));
    
    // Prepare post data
    $post_title = $year . ' ' . $make . ' ' . $model . ' ' . $variant;
    
    // Create the post
    $post_data = array(
        'post_title' => $post_title,
        'post_content' => '',
        'post_status' => 'pending', // Set as pending for admin review
        'post_type' => 'car',
        'post_author' => get_current_user_id(),
    );
    
    // Insert the post
    $post_id = wp_insert_post($post_data);
    
    // Check if post creation was successful
    if (is_wp_error($post_id)) {
        error_log('Error creating car listing: ' . $post_id->get_error_message());
        wp_redirect(add_query_arg('error', 'post_creation', wp_get_referer()));
        exit;
    }
    
    // Add post meta for all the car details
    update_field('make', $make, $post_id);
    update_field('model', $model, $post_id);
    update_field('variant', $variant, $post_id);
    update_field('year', $year, $post_id);
    update_field('mileage', $mileage, $post_id);
    update_field('price', $price, $post_id);
    update_field('car_city', $city, $post_id);
    update_field('car_district', $district, $post_id);
    update_field('car_latitude', $latitude, $post_id);
    update_field('car_longitude', $longitude, $post_id);
    update_field('car_address', $address, $post_id);
    update_field('engine_capacity', $engine_capacity, $post_id);
    update_field('fuel_type', $fuel_type, $post_id);
    update_field('transmission', $transmission, $post_id);
    update_field('body_type', $body_type, $post_id);
    update_field('drive_type', $drive_type, $post_id);
    update_field('exterior_color', $exterior_color, $post_id);
    update_field('interior_color', $interior_color, $post_id);
    update_field('description', $description, $post_id);
    update_field('number_of_doors', $number_of_doors, $post_id);
    update_field('number_of_seats', $number_of_seats, $post_id);
    update_field('motuntil', $motuntil, $post_id);
    update_field('extras', $extras, $post_id);
    
    // Update ACF fields
    update_field('hp', $hp, $post_id);
    update_field('numowners', $numowners, $post_id);
    update_field('isantique', $isantique, $post_id);
    
    // Note: Using post_author field for ownership (original_author ACF field no longer needed)
    
    // Save vehicle history both as post meta and ACF field to ensure compatibility
    update_field('vehiclehistory', $vehiclehistory, $post_id);
    
    // Debug logging after save
    error_log('Saved Vehicle History (post meta): ' . print_r(get_post_meta($post_id, 'vehiclehistory', true), true));
    error_log('Saved Vehicle History (ACF): ' . print_r(get_field('vehiclehistory', $post_id), true));
    
    // Process images - either async or traditional
    if (!empty($async_images)) {
        // Use async uploaded images
        car_submission_log('✅ USING ASYNC UPLOADS - Processing should be instant');
        car_submission_log('Using async uploaded images for post: ' . $post_id);
        
        // Mark session as completed to preserve files
        mark_upload_session_completed($async_session_id, $post_id);
        
        // Update car_images field with async attachment IDs
        update_field('car_images', $async_images, $post_id);
        
        car_submission_log('Successfully linked ' . count($async_images) . ' async images to post: ' . $post_id);
        $image_ids = $async_images;
        
    } else {
        // Traditional image upload processing
        car_submission_log('⚠️ FALLING BACK TO TRADITIONAL UPLOADS - This will be slow');
        car_submission_log('Processing traditional image uploads for post: ' . $post_id);
        $upload_start_time = microtime(true);
        
    $image_ids = handle_car_image_uploads($post_id);
        
        $upload_end_time = microtime(true);
        $upload_duration = round(($upload_end_time - $upload_start_time), 2);
        car_submission_log('Traditional upload completed in: ' . $upload_duration . ' seconds');
    
    // If image processing failed
    if (is_wp_error($image_ids)) {
        error_log('Error processing car images: ' . $image_ids->get_error_message());
        // Delete the post since images are required
        wp_delete_post($post_id, true);
        wp_redirect(add_query_arg('error', 'image_upload', wp_get_referer()));
        exit;
    }
    }
    
    car_submission_log('Car listing created successfully. Post ID: ' . $post_id . ' with ' . count($image_ids) . ' images');
    
    // Redirect to success page
    wp_redirect(add_query_arg('listing_submitted', 'success', wp_get_referer()));
    exit;
}

// Add hooks for handling form submissions
add_action('admin_post_add_new_car_listing', 'handle_add_car_listing');
add_action('admin_post_nopriv_add_new_car_listing', 'handle_add_car_listing');

/**
 * Process image uploads for car listings - OPTIMIZED VERSION
 *
 * @param int $post_id The ID of the car listing post
 * @return array|WP_Error Array of attachment IDs or WP_Error on failure
 */
function handle_car_image_uploads($post_id) {
    // Check if files were uploaded
    if (!isset($_FILES['car_images']) || empty($_FILES['car_images']['name'][0])) {
        return new WP_Error('no_image', __('No images were uploaded', 'astra-child'));
    }
    
    // Log the structure of $_FILES for debugging
    car_submission_log('FILES structure: ' . print_r($_FILES, true));
    
    // Allowed file types
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    
    // Setup return array for attachment IDs
    $attachment_ids = array();
    
    // Get the file count
    $file_count = count($_FILES['car_images']['name']);
    car_submission_log('Number of files to process: ' . $file_count);
    
    // Ensure WordPress media handling is loaded
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    // OPTIMIZATION: Disable intermediate image generation during batch upload
    add_filter('intermediate_image_sizes_advanced', '__return_empty_array');
    
    // Process files in optimized batch
    $processed_files = array();
    
    // Loop through each file
    for ($i = 0; $i < $file_count; $i++) {
        // If the file is empty, skip it
        if (empty($_FILES['car_images']['name'][$i])) {
            car_submission_log('Empty file at index ' . $i . ', skipping');
            continue;
        }
        
        car_submission_log('Processing file: ' . $_FILES['car_images']['name'][$i]);
        
        // Check file type
        $file_type = wp_check_filetype($_FILES['car_images']['name'][$i]);
        $mime_type = $_FILES['car_images']['type'][$i];
        
        car_submission_log('File MIME type: ' . $mime_type);
        
        if (!in_array($mime_type, $allowed_types)) {
            car_submission_log('Invalid file type: ' . $mime_type);
            continue; // Skip this file
        }
        
        // Create a new file array with only the current file
        $file = array(
            'name'     => $_FILES['car_images']['name'][$i],
            'type'     => $_FILES['car_images']['type'][$i],
            'tmp_name' => $_FILES['car_images']['tmp_name'][$i],
            'error'    => $_FILES['car_images']['error'][$i],
            'size'     => $_FILES['car_images']['size'][$i]
        );
        
        // Set up $_FILES array for this single file
        $_FILES['car_image'] = $file;
        
        // OPTIMIZATION: Use faster upload with minimal processing
        $attachment_id = optimized_media_handle_upload('car_image', $post_id);
        
        if (is_wp_error($attachment_id)) {
            car_submission_log('Error creating attachment: ' . $attachment_id->get_error_message());
            continue;
        }
        
        car_submission_log('Successfully created attachment with ID: ' . $attachment_id);
        
        // Add to our array of attachment IDs
        $attachment_ids[] = $attachment_id;
        $processed_files[] = $attachment_id;
    }
    
    // OPTIMIZATION: Re-enable image generation and generate only needed sizes
    remove_filter('intermediate_image_sizes_advanced', '__return_empty_array');
    
    // Generate optimized image sizes in batch for better performance
    if (!empty($processed_files)) {
        car_submission_log('Generating optimized image sizes for ' . count($processed_files) . ' images');
        generate_optimized_car_image_sizes($processed_files);
    }
    
    // If we have no attachments, return an error
    if (empty($attachment_ids)) {
        car_submission_log('No valid images were uploaded');
        return new WP_Error('no_valid_images', __('No valid images were uploaded', 'astra-child'));
    }
    
    car_submission_log('Total attachments processed: ' . count($attachment_ids));
    
    // Save attachment IDs to the ACF gallery field instead of setting featured image
    update_field('car_images', $attachment_ids, $post_id);
    car_submission_log('Saved all attachment IDs to ACF car_images gallery field');
    
    return $attachment_ids;
}

/**
 * OPTIMIZATION: Custom media upload handler with minimal processing
 */
function optimized_media_handle_upload($file_handler, $post_id) {
    $file = $_FILES[$file_handler];
    
    // Basic file validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_error', 'File upload error: ' . $file['error']);
    }
    
    // Move uploaded file to WordPress uploads directory
    $upload = wp_handle_upload($file, array('test_form' => false));
    
    if (isset($upload['error'])) {
        return new WP_Error('upload_error', $upload['error']);
    }
    
    // Create attachment post with minimal data
    $attachment = array(
        'post_mime_type' => $upload['type'],
        'post_title'     => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_parent'    => $post_id
    );
    
    // Insert attachment with minimal processing
    $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
    
    if (!$attachment_id) {
        return new WP_Error('db_error', 'Failed to create attachment');
    }
    
    // Store basic metadata without generating image sizes yet
    $metadata = array(
        'file' => _wp_relative_upload_path($upload['file']),
        'width' => 0,  // Will be set later if needed
        'height' => 0, // Will be set later if needed
        'sizes' => array()
    );
    
    wp_update_attachment_metadata($attachment_id, $metadata);
    
    return $attachment_id;
}

/**
 * OPTIMIZATION: Generate only essential image sizes in batch
 */
function generate_optimized_car_image_sizes($attachment_ids) {
    // Only generate the 3 sizes we actually use
    $essential_sizes = array('thumbnail', 'medium', 'large');
    
    foreach ($attachment_ids as $attachment_id) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            continue;
        }
        
        // Generate metadata with only essential sizes
        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        
        // Filter to keep only essential sizes
        if (isset($metadata['sizes'])) {
            $filtered_sizes = array();
            foreach ($essential_sizes as $size) {
                if (isset($metadata['sizes'][$size])) {
                    $filtered_sizes[$size] = $metadata['sizes'][$size];
                }
            }
            $metadata['sizes'] = $filtered_sizes;
        }
        
        wp_update_attachment_metadata($attachment_id, $metadata);
        
        car_submission_log('Generated optimized sizes for attachment: ' . $attachment_id);
    }
}

/**
 * Log errors to WordPress debug log
 * 
 * @param string $message The error message to log
 */
function car_submission_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

/**
 * Display car gallery images on single car listing pages
 * 
 * @param int $post_id The post ID to display gallery for
 * @return string HTML output of the gallery
 */
function display_car_gallery($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    // Get gallery image IDs from ACF field
    $gallery_images = get_field('car_images', $post_id);
    
    if (empty($gallery_images) || !is_array($gallery_images)) {
        return ''; // No gallery images
    }
    
    $output = '<div class="car-gallery">';
    
    // Display first image as main
    if (!empty($gallery_images[0])) {
        $main_image_id = $gallery_images[0];
        $full_image_url = wp_get_attachment_image_url($main_image_id, 'large');
        $output .= '<div class="car-gallery-main">';
        $output .= '<img src="' . esc_url($full_image_url) . '" alt="' . esc_attr(get_the_title($post_id)) . '" class="main-gallery-image">';
        $output .= '</div>';
    }
    
    // Thumbnail gallery
    if (count($gallery_images) > 1) {
        $output .= '<div class="car-gallery-thumbnails">';
        foreach ($gallery_images as $attachment_id) {
            $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            $full_url = wp_get_attachment_image_url($attachment_id, 'large');
            if ($thumb_url) {
                $output .= '<div class="gallery-thumbnail" data-full-image="' . esc_url($full_url) . '">';
                $output .= '<img src="' . esc_url($thumb_url) . '" alt="' . esc_attr(get_the_title($post_id)) . '">';
                $output .= '</div>';
            }
        }
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Shortcode to display car gallery
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function car_gallery_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => null,
    ), $atts);
    
    return display_car_gallery($atts['id']);
}
add_shortcode('car_gallery', 'car_gallery_shortcode');

/**
 * Add car gallery to single car listing content
 * 
 * @param string $content The post content
 * @return string Modified content with gallery
 */
function add_car_gallery_to_content($content) {
    if (is_singular('car')) {
        $gallery = display_car_gallery();
        return $gallery . $content;
    }
    return $content;
}
add_filter('the_content', 'add_car_gallery_to_content', 20);

/**
 * Store the publication date when a car listing is published
 * 
 * @param string $new_status The new post status
 * @param string $old_status The old post status
 * @param WP_Post $post The post object
 */
function store_car_publication_date($new_status, $old_status, $post) {
    // Only proceed if this is a car post type
    if ($post->post_type !== 'car') {
        return;
    }

    // Check if the post is being published
    if ($new_status === 'publish' && $old_status !== 'publish') {
        // Store the current time as the publication date
        $publication_date = current_time('mysql');
        update_post_meta($post->ID, 'publication_date', $publication_date);
        
        // Debug log
        if (WP_DEBUG === true) {
            error_log('Car publication date stored: ' . $publication_date . ' for post ID: ' . $post->ID);
        }
    }
}
add_action('transition_post_status', 'store_car_publication_date', 10, 3);

/**
 * Process form submission for editing a car listing
 */
function handle_edit_car_listing() {
    // Include separated validation and processing files
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/template-edit-listing/edit-listing-validation.php';
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/template-edit-listing/edit-listing-processing.php';
    
    // Verify nonce
    if (!validate_edit_listing_nonce($_POST)) {
        handle_edit_listing_redirect('error', 'nonce_failed', home_url('/my-listings/'));
        return;
    }
    
    // Get car ID
    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    if (!$car_id) {
        handle_edit_listing_redirect('error', 'invalid_car', home_url('/my-listings/'));
        return;
    }
    
    // Check if user owns the car
    if (!validate_car_ownership($car_id, get_current_user_id())) {
        handle_edit_listing_redirect('error', 'unauthorized', home_url('/my-listings/'));
        return;
    }
    
    // Check for async upload session ID (NEW - matches add listing logic)
    $async_session_id = isset($_POST['async_session_id']) ? sanitize_text_field($_POST['async_session_id']) : '';
    $async_images = array();
    
    if (!empty($async_session_id)) {
        // Get async uploaded images
        $async_images = get_session_attachment_ids($async_session_id, get_current_user_id());
    }
    
    // Validate form data
    $validation_errors = validate_edit_listing_form($_POST, $car_id);
    if (!empty($validation_errors['missing_fields'])) {
        $redirect_url = add_query_arg(
            array(
                'error' => 'validation',
                'fields' => implode(',', $validation_errors['missing_fields'])
            ),
            wp_get_referer()
        );
        wp_redirect($redirect_url);
        exit;
    }
    
    // Validate image requirements - UPDATED to account for async uploads
    $existing_images = get_field('car_images', $car_id);
    $removed_images = isset($_POST['removed_images']) ? $_POST['removed_images'] : array();
    
    // Calculate total images after changes
    $remaining_existing = is_array($existing_images) ? count($existing_images) - count($removed_images) : 0;
    $new_images_count = !empty($async_images) ? count($async_images) : (isset($_FILES['car_images']) && !empty($_FILES['car_images']['name'][0]) ? count($_FILES['car_images']['name']) : 0);
    $total_images = $remaining_existing + $new_images_count;
    
    if ($total_images < 5) {
        wp_redirect(add_query_arg('error', 'insufficient_images', wp_get_referer()));
        exit;
    }
    
    // Process form data
    process_edit_listing_form($_POST, $car_id);
    
    // Process images - UPDATED to use async uploads if available
    if (!empty($async_images)) {
        // Use async uploaded images (fast path)
        process_edit_listing_images_async($car_id, $async_images, $removed_images, $async_session_id);
    } else {
        // Traditional image upload processing (slow path)
        process_edit_listing_images($car_id, $_FILES, $removed_images);
    }
    
    // Redirect with success message
    handle_edit_listing_redirect('success', 'listing_updated');
}
add_action('admin_post_edit_car_listing', 'handle_edit_car_listing');
add_action('admin_post_nopriv_edit_car_listing', 'handle_edit_car_listing');