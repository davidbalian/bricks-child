<?php
/**
 * Asynchronous Upload System for Car Listings
 * Handles background image uploads with session tracking and cleanup
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize async upload system
 */
function init_async_upload_system() {
    // Ensure database table exists (theme-compatible approach)
    add_action('init', 'ensure_temp_uploads_table_exists');
    
    // Register AJAX handlers
    add_action('wp_ajax_async_upload_image', 'handle_async_upload_image');
    add_action('wp_ajax_nopriv_async_upload_image', 'handle_async_upload_image');
    
    add_action('wp_ajax_cleanup_upload_session', 'handle_cleanup_upload_session');
    add_action('wp_ajax_nopriv_cleanup_upload_session', 'handle_cleanup_upload_session');
    
    add_action('wp_ajax_delete_async_image', 'handle_delete_async_image');
    add_action('wp_ajax_nopriv_delete_async_image', 'handle_delete_async_image');
    
    // Schedule cleanup cron job
    if (!wp_next_scheduled('cleanup_orphaned_uploads_cron')) {
        wp_schedule_event(time(), 'hourly', 'cleanup_orphaned_uploads_cron');
    }
    add_action('cleanup_orphaned_uploads_cron', 'cleanup_orphaned_uploads');
    
    // Enqueue necessary scripts
    add_action('wp_enqueue_scripts', 'enqueue_async_upload_scripts');
}

/**
 * Ensure the temp uploads table exists (theme-compatible)
 */
function ensure_temp_uploads_table_exists() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'temp_uploads';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if (!$table_exists) {
        create_temp_uploads_table();
    }
}

/**
 * Create database table for tracking temporary uploads
 */
function create_temp_uploads_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'temp_uploads';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        session_id varchar(100) NOT NULL,
        user_id bigint(20) NOT NULL,
        attachment_id bigint(20) NOT NULL,
        original_filename varchar(255) NOT NULL,
        upload_time datetime DEFAULT CURRENT_TIMESTAMP,
        status enum('pending','completed','failed') DEFAULT 'pending',
        form_type varchar(50) DEFAULT 'add_listing',
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY upload_time (upload_time)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Enqueue scripts for async uploads
 */
function enqueue_async_upload_scripts() {
    // Only enqueue on add/edit listing pages
    if (is_page_template('template-add-listing.php') || is_page_template('template-edit-listing.php')) {
        wp_enqueue_script(
            'async-uploads',
            get_stylesheet_directory_uri() . '/includes/core/async-uploads.js',
            array('jquery'),
            filemtime(get_stylesheet_directory() . '/includes/core/async-uploads.js'),
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('async-uploads', 'asyncUploads', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('async_upload_nonce'),
            'userId' => get_current_user_id(),
            'maxFileSize' => 5 * 1024 * 1024, // 5MB
            'allowedTypes' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp')
        ));
    }
}

/**
 * AJAX handler for asynchronous image upload
 */
function handle_async_upload_image() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'async_upload_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'User not logged in'));
        return;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(array('message' => 'File upload error'));
        return;
    }
    
    $session_id = sanitize_text_field($_POST['session_id']);
    $original_filename = sanitize_text_field($_POST['original_filename']);
    $form_type = sanitize_text_field($_POST['form_type']);
    
    // Validate file type
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    if (!in_array($_FILES['image']['type'], $allowed_types)) {
        wp_send_json_error(array('message' => 'Invalid file type'));
        return;
    }
    
    // Validate file size (5MB max)
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        wp_send_json_error(array('message' => 'File too large'));
        return;
    }
    
    // Load WordPress media functions
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    // Upload file to WordPress media library
    $attachment_id = media_handle_upload('image', 0); // 0 = no post parent initially
    
    if (is_wp_error($attachment_id)) {
        wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        return;
    }
    
    // Track upload in database
    global $wpdb;
    $table_name = $wpdb->prefix . 'temp_uploads';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'session_id' => $session_id,
            'user_id' => get_current_user_id(),
            'attachment_id' => $attachment_id,
            'original_filename' => $original_filename,
            'status' => 'pending',
            'form_type' => $form_type
        ),
        array('%s', '%d', '%d', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        // If database insert failed, clean up the uploaded file
        wp_delete_attachment($attachment_id, true);
        wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        return;
    }
    
    // Get attachment URL for preview
    $attachment_url = wp_get_attachment_image_url($attachment_id, 'medium');
    
    wp_send_json_success(array(
        'attachment_id' => $attachment_id,
        'attachment_url' => $attachment_url,
        'original_filename' => $original_filename,
        'message' => 'Image uploaded successfully'
    ));
}

/**
 * AJAX handler for deleting async uploaded image
 */
function handle_delete_async_image() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'async_upload_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'User not logged in'));
        return;
    }
    
    $attachment_id = intval($_POST['attachment_id']);
    $session_id = sanitize_text_field($_POST['session_id']);
    $user_id = get_current_user_id();
    
    // Verify user owns this upload
    global $wpdb;
    $table_name = $wpdb->prefix . 'temp_uploads';
    
    // Check what records exist for this session and user
    $session_records = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE session_id = %s AND user_id = %d",
        $session_id, $user_id
    ));
    foreach ($session_records as $record) {
    }
    
    $upload_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE attachment_id = %d AND session_id = %s AND user_id = %d",
        $attachment_id, $session_id, $user_id
    ));
    
    if (!$upload_record) {
        // Try alternative search - maybe there's a timing issue
        $alt_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE attachment_id = %d AND user_id = %d ORDER BY upload_time DESC LIMIT 1",
            $attachment_id, $user_id
        ));
        
        if ($alt_record) {
            // Use the found record if it's recent (within last 5 minutes)
            $upload_time = strtotime($alt_record->upload_time);
            if (time() - $upload_time < 300) {
                $upload_record = $alt_record;
            }
        }
        
        if (!$upload_record) {
            wp_send_json_error(array('message' => 'Upload not found or access denied'));
            return;
        }
    }
    
    // Delete the attachment file
    $deleted = wp_delete_attachment($attachment_id, true);
    
    if ($deleted) {
        // Remove from tracking table
        $delete_result = $wpdb->delete(
            $table_name,
            array(
                'attachment_id' => $attachment_id,
                'user_id' => $user_id
            ),
            array('%d', '%d')
        );
        
        wp_send_json_success(array('message' => 'Image deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete image'));
    }
}

/**
 * AJAX handler for cleaning up upload session
 */
function handle_cleanup_upload_session() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'async_upload_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'User not logged in'));
        return;
    }
    
    $session_id = sanitize_text_field($_POST['session_id']);
    
    // Get all pending uploads for this session
    global $wpdb;
    $table_name = $wpdb->prefix . 'temp_uploads';
    
    $pending_uploads = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE session_id = %s AND user_id = %d AND status = 'pending'",
        $session_id, get_current_user_id()
    ));
    
    $deleted_count = 0;
    foreach ($pending_uploads as $upload) {
        // Delete the attachment file
        if (wp_delete_attachment($upload->attachment_id, true)) {
            $deleted_count++;
        }
    }
    
    // Remove all session records
    $wpdb->delete(
        $table_name,
        array(
            'session_id' => $session_id,
            'user_id' => get_current_user_id()
        ),
        array('%s', '%d')
    );
    
    wp_send_json_success(array(
        'message' => "Cleaned up {$deleted_count} uploads",
        'deleted_count' => $deleted_count
    ));
}

/**
 * Mark upload session as completed (preserve files)
 */
function mark_upload_session_completed($session_id, $post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'temp_uploads';
    
    // Update all pending uploads in this session to completed
    $wpdb->update(
        $table_name,
        array('status' => 'completed'),
        array(
            'session_id' => $session_id,
            'user_id' => get_current_user_id(),
            'status' => 'pending'
        ),
        array('%s'),
        array('%s', '%d', '%s')
    );
    
    // Update attachment post_parent to link them to the car listing
    $uploads = $wpdb->get_results($wpdb->prepare(
        "SELECT attachment_id FROM $table_name WHERE session_id = %s AND user_id = %d",
        $session_id, get_current_user_id()
    ));
    
    foreach ($uploads as $upload) {
        wp_update_post(array(
            'ID' => $upload->attachment_id,
            'post_parent' => $post_id
        ));
    }
}

/**
 * Get attachment IDs for a session
 */
function get_session_attachment_ids($session_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'temp_uploads';
    
    $attachment_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT attachment_id FROM $table_name WHERE session_id = %s AND user_id = %d AND status = 'pending'",
        $session_id, $user_id
    ));
    
    return array_map('intval', $attachment_ids);
}

/**
 * Scheduled cleanup of orphaned uploads (older than 1 hour)
 */
function cleanup_orphaned_uploads() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'temp_uploads';
    
    // Find uploads older than 1 hour that are still pending
    $orphaned_uploads = $wpdb->get_results(
        "SELECT attachment_id FROM $table_name 
         WHERE status = 'pending' 
         AND upload_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    
    $cleaned_count = 0;
    foreach ($orphaned_uploads as $upload) {
        // Delete the actual file
        if (wp_delete_attachment($upload->attachment_id, true)) {
            $cleaned_count++;
        }
    }
    
    // Remove records for deleted uploads
    $wpdb->query(
        "DELETE FROM $table_name 
         WHERE status = 'pending' 
         AND upload_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    
    if ($cleaned_count > 0) {
        error_log("Async uploads: Cleaned up {$cleaned_count} orphaned uploads");
    }
}

// Initialize the system
init_async_upload_system(); 