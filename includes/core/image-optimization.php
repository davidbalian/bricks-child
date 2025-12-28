<?php
/**
 * Image Optimization for Car Listings
 * Server-side image processing optimizations
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize all image optimization hooks and filters
 */
function init_car_image_optimization() {
    // Remove unnecessary image sizes to speed up processing
    add_action('init', 'optimize_wordpress_image_sizes');
    
    // Disable automatic image editing for faster uploads
    add_filter('wp_image_editors', 'optimize_image_editors');
    
    // Optimize image quality for car listings (reduce file size)
    add_filter('jpeg_quality', 'optimize_jpeg_quality');
    add_filter('wp_editor_set_quality', 'optimize_jpeg_quality');
    
    // Only generate necessary image sizes for car listings
    add_filter('intermediate_image_sizes_advanced', 'optimize_car_image_sizes');
    
    // Disable WordPress automatic image rotation (saves processing time)
    add_filter('wp_image_maybe_exif_rotate', '__return_false');
    
    // Optimize image metadata generation
    add_filter('wp_generate_attachment_metadata', 'optimize_attachment_metadata', 10, 2);
    
    // Disable big image size threshold for car uploads (prevent unnecessary resizing)
    add_filter('big_image_size_threshold', 'disable_big_image_threshold_for_cars');
    
    // Additional optimizations for maximum performance during car uploads
    add_action('init', 'optimize_car_upload_performance');
    
    // Optimize database queries during image upload
    add_filter('wp_insert_attachment_data', 'optimize_attachment_data', 10, 2);
    
    // Performance monitoring
    add_action('wp_ajax_monitor_car_upload_performance', 'monitor_car_upload_performance');
    add_action('wp_ajax_nopriv_monitor_car_upload_performance', 'monitor_car_upload_performance');
}

/**
 * Lightweight logger for image optimization (enabled only when WP_DEBUG is true).
 *
 * @param string $message
 */
function car_image_opt_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[car-image-opt] ' . $message);
    }
}

/**
 * Remove unnecessary WordPress image sizes and add optimized car-specific sizes
 */
function optimize_wordpress_image_sizes() {
    // Remove unused default WordPress image sizes
    remove_image_size('medium_large'); // 768px - not used by car listings
    remove_image_size('1536x1536');    // Large size - not used
    remove_image_size('2048x2048');    // Extra large - not used
    
    // Set optimal sizes for car listings only
    add_image_size('car_thumbnail', 200, 150, true);  // For previews and admin
    add_image_size('car_medium', 400, 300, true);     // For card listings
    add_image_size('car_large', 800, 600, true);      // For detailed views
}

/**
 * Use only the most efficient image editor
 */
function optimize_image_editors($editors) {
    // Only keep the most efficient image editor
    return array('WP_Image_Editor_GD');
}

/**
 * Optimize JPEG quality for better performance vs quality balance
 */
function optimize_jpeg_quality($quality) {
    // Use 85% quality instead of default 90% for good balance
    return 85;
}

/**
 * Only generate necessary image sizes for car listings
 */
function optimize_car_image_sizes($sizes) {
    // Check if this is a car listing upload
    if (isset($_POST['action']) && ($_POST['action'] === 'add_new_car_listing' || $_POST['action'] === 'edit_car_listing')) {
        // Only generate the sizes we actually use
        $car_sizes = array();
        
        // Keep only essential sizes for car listings
        if (isset($sizes['thumbnail'])) {
            $car_sizes['thumbnail'] = $sizes['thumbnail'];
        }
        if (isset($sizes['medium'])) {
            $car_sizes['medium'] = $sizes['medium'];
        }
        if (isset($sizes['large'])) {
            $car_sizes['large'] = $sizes['large'];
        }
        
        return $car_sizes;
    }
    
    // For other uploads, use default sizes
    return $sizes;
}

/**
 * Optimize image metadata generation
 */
function optimize_attachment_metadata($metadata, $attachment_id) {
    // Check if this is a car listing image
    $parent_post = get_post_parent($attachment_id);
    if ($parent_post && get_post_type($parent_post) === 'car') {
        // Skip generating additional metadata for car images
        if (isset($metadata['image_meta'])) {
            // Keep only essential metadata
            $metadata['image_meta'] = array(
                'width' => $metadata['image_meta']['width'] ?? '',
                'height' => $metadata['image_meta']['height'] ?? '',
                'file' => $metadata['image_meta']['file'] ?? '',
            );
        }
    }
    
    return $metadata;
}

/**
 * Disable big image size threshold for car uploads
 */
function disable_big_image_threshold_for_cars($threshold) {
    // Check if this is a car listing upload
    if (isset($_POST['action']) && ($_POST['action'] === 'add_new_car_listing' || $_POST['action'] === 'edit_car_listing')) {
        return false; // Disable threshold - let our client-side optimization handle it
    }
    
    return $threshold;
}

/**
 * Additional optimizations for maximum performance during car uploads
 */
function optimize_car_upload_performance() {
    // Check if this is a car listing upload request
    if (isset($_POST['action']) && ($_POST['action'] === 'add_new_car_listing' || $_POST['action'] === 'edit_car_listing')) {
        
        // Disable WordPress search indexing during upload
        remove_action('save_post', 'wp_update_search_index');
        
        // Disable revision saving during upload (temporary)
        remove_action('post_updated', 'wp_save_post_revision');
        
        // Disable ping services
        remove_action('do_pings', 'do_all_pings');
        
        // Temporarily increase memory limit for image processing
        ini_set('memory_limit', '512M');
        
        // Increase max execution time for uploads
        ini_set('max_execution_time', 300); // 5 minutes
    }
}

/**
 * Optimize database queries during image upload
 */
function optimize_attachment_data($data, $postarr) {
    // For car listing images, minimize database operations
    if (isset($_POST['action']) && ($_POST['action'] === 'add_new_car_listing' || $_POST['action'] === 'edit_car_listing')) {
        // Skip some unnecessary fields to speed up database insertion
        unset($data['post_content_filtered']);
        unset($data['post_excerpt']);
        unset($data['ping_status']);
        unset($data['comment_status']);
    }
    
    return $data;
}

/**
 * Performance monitoring - Log upload times for optimization tracking
 */
function monitor_car_upload_performance() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $upload_time = isset($_POST['upload_time']) ? intval($_POST['upload_time']) : 0;
        $image_count = isset($_POST['image_count']) ? intval($_POST['image_count']) : 0;
        
        if ($upload_time > 0 && $image_count > 0) {
            $log_message = sprintf(
                'Car Upload Performance: %d images uploaded in %d seconds (%.2f seconds per image)',
                $image_count,
                $upload_time,
                $upload_time / $image_count
            );
            error_log($log_message);
        }
    }
    
    wp_die();
}

/**
 * Check if current request is a car listing operation
 */
function is_car_listing_operation() {
    return isset($_POST['action']) && 
           ($_POST['action'] === 'add_new_car_listing' || $_POST['action'] === 'edit_car_listing');
}

/**
 * Delete original upload and its generated derivatives created before WebP conversion.
 *
 * @param array  $metadata          Attachment metadata prior to conversion.
 * @param string $current_file_path Absolute path to the new WebP file to keep.
 */
function delete_legacy_attachment_files($metadata, $current_file_path) {
    if (empty($metadata) || empty($metadata['file'])) {
        car_image_opt_log('delete_legacy_attachment_files: missing metadata or file; skipping cleanup');
        return;
    }

    $upload_dir = wp_get_upload_dir();
    if (empty($upload_dir['basedir'])) {
        car_image_opt_log('delete_legacy_attachment_files: upload basedir missing; skipping cleanup');
        return;
    }

    $base_dir      = trailingslashit($upload_dir['basedir']);
    $relative_path = $metadata['file'];
    $dir_name      = pathinfo($relative_path, PATHINFO_DIRNAME);
    $root_dir      = ($dir_name && $dir_name !== '.')
        ? trailingslashit($base_dir . $dir_name)
        : $base_dir;

    $files_to_delete = array();

    // Original uploaded file path.
    $original_full_path = $base_dir . $relative_path;
    $files_to_delete[]  = $original_full_path;

    // Any generated size files.
    if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size_data) {
            if (!empty($size_data['file'])) {
                $files_to_delete[] = $root_dir . $size_data['file'];
            }
        }
    }

    foreach ($files_to_delete as $legacy_file) {
        if ($legacy_file && $legacy_file !== $current_file_path && file_exists($legacy_file)) {
            unlink($legacy_file);
            car_image_opt_log('Deleted legacy derivative: ' . $legacy_file);
        }
    }
}

/**
 * Convert uploaded images to WebP format - handles any input format optimally
 * 
 * @param int   $attachment_id            The attachment ID
 * @param array $pre_conversion_metadata  Metadata (including generated sizes) before WebP conversion
 * @return bool Success/failure
 */
function convert_to_webp_with_fallback($attachment_id, $pre_conversion_metadata = null) {
    try {
        // Use provided metadata (with derivative filenames) if available; fallback to stored metadata.
        $legacy_metadata = $pre_conversion_metadata ?: wp_get_attachment_metadata($attachment_id);
        $legacy_size_count = isset($legacy_metadata['sizes']) && is_array($legacy_metadata['sizes'])
            ? count($legacy_metadata['sizes'])
            : 0;
        car_image_opt_log("Starting WebP conversion for attachment {$attachment_id}; legacy sizes: {$legacy_size_count}");
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            car_image_opt_log("WebP conversion skipped - file not found for attachment {$attachment_id}");
            return false;
        }

        // Get original file info
        $original_mime = get_post_mime_type($attachment_id);
        $pathinfo = pathinfo($file_path);
        
        // Skip if already WebP
        if ($original_mime === 'image/webp') {
            car_image_opt_log("Attachment {$attachment_id} is already WebP, skipping conversion");
            return true;
        }

        // Check if WordPress supports WebP (GD or ImageMagick)
        if (!function_exists('wp_image_editor_supports') || !wp_image_editor_supports(array('mime_type' => 'image/webp'))) {
            car_image_opt_log('WebP not supported by WordPress image editor - keeping original format');
            return false;
        }

        $webp_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.webp';

        // Load the image editor
        $image_editor = wp_get_image_editor($file_path);
        
        if (is_wp_error($image_editor)) {
            car_image_opt_log('Error loading image editor: ' . $image_editor->get_error_message());
            return false;
        }

        // Optimize dimensions if needed (max 1920x1080 for car listings)
        $current_size = $image_editor->get_size();
        if ($current_size && isset($current_size['width']) && isset($current_size['height'])) {
            if ($current_size['width'] > 1920 || $current_size['height'] > 1080) {
                $resize_result = $image_editor->resize(1920, 1080, false);
                if (is_wp_error($resize_result)) {
                    car_image_opt_log('Error resizing image: ' . $resize_result->get_error_message());
                    return false;
                }
                car_image_opt_log("Resized large image for attachment {$attachment_id}");
            }
        }

        // Set quality based on original format
        if ($original_mime === 'image/png') {
            // PNG was lossless, use higher quality for WebP
            $image_editor->set_quality(90);
        } else {
            // JPEG was already compressed, use standard quality
            $image_editor->set_quality(85);
        }

        // Save as WebP
        $saved = $image_editor->save($webp_path, 'image/webp');
        
        if (is_wp_error($saved)) {
            car_image_opt_log('Error saving WebP: ' . $saved->get_error_message());
            return false;
        }

        // Update attachment to point to WebP file
        update_attached_file($attachment_id, $webp_path);
        
        // Update attachment metadata
        $attachment_data = array(
            'ID' => $attachment_id,
            'post_mime_type' => 'image/webp'
        );
        wp_update_post($attachment_data);

        // CRITICAL: Regenerate attachment metadata for WebP file
        // This ensures WordPress generates WebP thumbnails and updates all URLs
        
        // Mark this attachment as "being processed" to prevent infinite loops
        update_post_meta($attachment_id, '_webp_conversion_in_progress', true);
        
        $new_metadata = wp_generate_attachment_metadata($attachment_id, $webp_path);
        if ($new_metadata && !is_wp_error($new_metadata)) {
            wp_update_attachment_metadata($attachment_id, $new_metadata);
            car_image_opt_log("Regenerated metadata for WebP attachment {$attachment_id}");
        } else {
            car_image_opt_log("Failed to regenerate metadata for WebP attachment {$attachment_id}");
        }

        // Remove legacy JPG/PNG derivatives now that WebP variants are in place.
        delete_legacy_attachment_files($legacy_metadata, $webp_path);
        // Fallback cleanup: ensure original source file is removed even if metadata was missing.
        if ($file_path && $file_path !== $webp_path && file_exists($file_path)) {
            unlink($file_path);
            car_image_opt_log('Deleted original source after WebP conversion: ' . $file_path);
        }
        
        // Remove the processing flag
        delete_post_meta($attachment_id, '_webp_conversion_in_progress');

        car_image_opt_log("Successfully converted attachment {$attachment_id} from {$original_mime} to WebP");
        // Return the fresh metadata so filters can persist WebP info upstream.
        return $new_metadata ?: true;
        
    } catch (Exception $e) {
        car_image_opt_log("WebP conversion failed for attachment {$attachment_id}: " . $e->getMessage());
        return false;
    } catch (Error $e) {
        car_image_opt_log("WebP conversion error for attachment {$attachment_id}: " . $e->getMessage());
        return false;
    }
}

/**
 * Hook to convert car images to WebP after upload (safe version)
 */
function convert_car_images_to_webp($metadata, $attachment_id) {
    // Only proceed if we have valid metadata and attachment ID
    if (!$metadata || !$attachment_id) {
        return $metadata;
    }
    
    // ROBUST: Check if this attachment is already being processed to prevent infinite loops
    if (get_post_meta($attachment_id, '_webp_conversion_in_progress', true)) {
        car_image_opt_log("WebP conversion skipped for attachment {$attachment_id} - already in progress");
        return $metadata;
    }
    
    // Check if already WebP to prevent unnecessary processing
    if (get_post_mime_type($attachment_id) === 'image/webp') {
        return $metadata;
    }
    
    try {
        // Check if this could be a car listing image (safe check)
        if (should_convert_to_webp($attachment_id)) {
            $webp_metadata = convert_to_webp_with_fallback($attachment_id, $metadata);
            if (is_array($webp_metadata)) {
                // Persist WebP metadata so caller does not overwrite with pre-conversion data.
                return $webp_metadata;
            }
        }
    } catch (Exception $e) {
        // Log error but don't break the upload process
        car_image_opt_log('WebP conversion failed for attachment ' . $attachment_id . ': ' . $e->getMessage());
        // Clean up the processing flag if it exists
        delete_post_meta($attachment_id, '_webp_conversion_in_progress');
    }
    
    return $metadata;
}

/**
 * Safe check if image should be converted to WebP
 */
function should_convert_to_webp($attachment_id) {
    // Method 1: Check if it's a direct car listing operation
    if (is_car_listing_operation()) {
        return true;
    }
    
    // Method 2: Check if it's an async upload (safe database check)
    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'temp_uploads';
        
        // Only check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $upload_record = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE attachment_id = %d",
                $attachment_id
            ));
            return $upload_record > 0;
        }
    } catch (Exception $e) {
        // If database check fails, don't convert (safer)
        error_log('Database check failed for WebP conversion: ' . $e->getMessage());
    }
    
    return false;
}
// Only add WebP conversion hook if NOT during async uploads to prevent response interference
if (!defined('DOING_AJAX') || !DOING_AJAX || !isset($_POST['action']) || $_POST['action'] !== 'async_upload_image') {
    add_filter('wp_generate_attachment_metadata', 'convert_car_images_to_webp', 20, 2);
}

/**
 * Scheduled WebP conversion for async uploaded images
 */
function handle_scheduled_webp_conversion($attachment_id) {
    // ROBUST: Add same safety checks as the main hook
    if (!$attachment_id) {
        error_log('Scheduled WebP conversion called with invalid attachment ID');
        return;
    }
    
    // Check if already being processed
    if (get_post_meta($attachment_id, '_webp_conversion_in_progress', true)) {
        error_log("Scheduled WebP conversion skipped for attachment {$attachment_id} - already in progress");
        return;
    }
    
    // Check if already WebP
    if (get_post_mime_type($attachment_id) === 'image/webp') {
        error_log("Scheduled WebP conversion skipped for attachment {$attachment_id} - already WebP");
        return;
    }
    
    // Check if attachment still exists
    if (!get_attached_file($attachment_id)) {
        error_log("Scheduled WebP conversion skipped for attachment {$attachment_id} - file not found");
        return;
    }
    
    if (function_exists('convert_to_webp_with_fallback')) {
        error_log("Starting scheduled WebP conversion for attachment {$attachment_id}");
        convert_to_webp_with_fallback($attachment_id);
    } else {
        error_log('Scheduled WebP conversion failed - convert_to_webp_with_fallback function not available');
    }
}
add_action('convert_async_image_to_webp', 'handle_scheduled_webp_conversion');

// Initialize optimizations when this file is loaded
init_car_image_optimization(); 