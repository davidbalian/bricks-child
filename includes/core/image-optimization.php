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
 * Convert uploaded images to WebP format - handles any input format optimally
 * 
 * @param int $attachment_id The attachment ID
 * @return bool Success/failure
 */
function convert_to_webp_with_fallback($attachment_id) {
    $file_path = get_attached_file($attachment_id);
    
    if (!$file_path || !file_exists($file_path)) {
        return false;
    }

    // Get original file info
    $original_mime = get_post_mime_type($attachment_id);
    $pathinfo = pathinfo($file_path);
    
    // Skip if already WebP
    if ($original_mime === 'image/webp') {
        error_log("Attachment {$attachment_id} is already WebP, skipping conversion");
        return true;
    }

    // Check if WordPress supports WebP (GD or ImageMagick)
    if (!wp_image_editor_supports(array('mime_type' => 'image/webp'))) {
        error_log('WebP not supported by WordPress image editor');
        return false;
    }

    $webp_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.webp';

    // Load the image editor
    $image_editor = wp_get_image_editor($file_path);
    
    if (is_wp_error($image_editor)) {
        error_log('Error loading image editor: ' . $image_editor->get_error_message());
        return false;
    }

    // Optimize dimensions if needed (max 1920x1080 for car listings)
    $current_size = $image_editor->get_size();
    if ($current_size['width'] > 1920 || $current_size['height'] > 1080) {
        $image_editor->resize(1920, 1080, false); // Maintain aspect ratio
        error_log("Resized large image for attachment {$attachment_id}");
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
        error_log('Error saving WebP: ' . $saved->get_error_message());
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

    // Delete original file to save space
    if (file_exists($file_path) && $file_path !== $webp_path) {
        unlink($file_path);
    }

    error_log("Successfully converted attachment {$attachment_id} from {$original_mime} to WebP");
    return true;
}

/**
 * Hook to convert car images to WebP after upload
 */
function convert_car_images_to_webp($attachment_id) {
    // Only convert car listing images
    $parent_post = get_post_parent($attachment_id);
    if ($parent_post && get_post_type($parent_post) === 'car') {
        convert_to_webp_with_fallback($attachment_id);
    }
}
add_action('wp_generate_attachment_metadata', 'convert_car_images_to_webp', 20);

// Initialize optimizations when this file is loaded
init_car_image_optimization(); 