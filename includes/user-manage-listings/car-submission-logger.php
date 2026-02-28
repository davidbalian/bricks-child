<?php
/**
 * Car Submission Failure Logger
 * 
 * Custom logging system for car listing submission failures
 * Logs to a separate file from WordPress debug.log
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Get the custom log file path
 * 
 * @return string Path to the log file
 */
function get_car_submission_log_path() {
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/car-submission-logs';
    
    // Create directory if it doesn't exist
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
        // Add .htaccess to protect log files
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents($log_dir . '/.htaccess', $htaccess_content);
    }
    
    return $log_dir . '/car-submission-failures.log';
}

/**
 * Log car submission failure
 * 
 * @param string $error_type Type of error (e.g., 'validation', 'nonce', 'image_upload', 'field_validation', 'post_creation')
 * @param string $error_message Human-readable error message
 * @param array $context Additional context data (user_id, submitted_data, etc.)
 * @return bool True on success, false on failure
 */
function log_car_submission_failure($error_type, $error_message, $context = array()) {
    $log_file = get_car_submission_log_path();
    
    // Get current user info
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $username = $current_user->user_login;
    $user_email = $current_user->user_email;
    
    // Get timestamp
    $timestamp = current_time('Y-m-d H:i:s');
    $date = current_time('Y-m-d');
    
    // Build log entry
    $log_entry = array(
        'timestamp' => $timestamp,
        'date' => $date,
        'error_type' => $error_type,
        'error_message' => $error_message,
        'user' => array(
            'id' => $user_id,
            'username' => $username,
            'email' => $user_email,
        ),
        'context' => $context,
        'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown',
        'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown',
    );
    
    // Convert to JSON for readable format
    $json_entry = json_encode($log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    // Add separator
    $separator = "\n" . str_repeat('=', 80) . "\n";
    
    // Write to log file
    $result = file_put_contents($log_file, $separator . $json_entry . "\n", FILE_APPEND | LOCK_EX);
    
    // Also log to WordPress debug log for immediate visibility
    error_log('[CAR_SUBMISSION_FAILURE] ' . $error_type . ': ' . $error_message);
    
    return $result !== false;
}

/**
 * Get recent car submission failures
 * 
 * @param int $limit Number of entries to retrieve (default: 50)
 * @return array Array of log entries
 */
function get_car_submission_failures($limit = 50) {
    $log_file = get_car_submission_log_path();
    
    if (!file_exists($log_file)) {
        return array();
    }
    
    $content = file_get_contents($log_file);
    if (empty($content)) {
        return array();
    }
    
    // Split by separator
    $entries = explode(str_repeat('=', 80), $content);
    $entries = array_filter($entries, function($entry) {
        return !empty(trim($entry));
    });
    
    // Parse JSON entries
    $parsed_entries = array();
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) {
            continue;
        }
        
        $decoded = json_decode($entry, true);
        if ($decoded !== null) {
            $parsed_entries[] = $decoded;
        }
    }
    
    // Sort by timestamp (newest first)
    usort($parsed_entries, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Limit results
    return array_slice($parsed_entries, 0, $limit);
}

/**
 * Clear car submission failure logs
 * 
 * @return bool True on success, false on failure
 */
function clear_car_submission_failures() {
    $log_file = get_car_submission_log_path();
    
    if (file_exists($log_file)) {
        return unlink($log_file);
    }
    
    return true;
}

/**
 * Get log file size in human-readable format
 * 
 * @return string File size (e.g., "2.5 MB")
 */
function get_car_submission_log_size() {
    $log_file = get_car_submission_log_path();
    
    if (!file_exists($log_file)) {
        return '0 B';
    }
    
    $size = filesize($log_file);
    $units = array('B', 'KB', 'MB', 'GB');
    $unit_index = 0;
    
    while ($size >= 1024 && $unit_index < count($units) - 1) {
        $size /= 1024;
        $unit_index++;
    }
    
    return round($size, 2) . ' ' . $units[$unit_index];
}

