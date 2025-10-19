<?php
/**
 * Refresh Listing Manager
 * 
 * Handles business logic for listing refresh functionality
 * Allows sellers to bump their listings once every 7 days
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class RefreshListingManager
 * 
 * Manages the core business logic for refreshing car listings
 */
class RefreshListingManager {
    
    /**
     * Number of days between allowed refreshes
     * 
     * @var int
     */
    const REFRESH_COOLDOWN_DAYS = 7;
    
    /**
     * Meta key for storing last refresh timestamp
     * 
     * @var string
     */
    const META_KEY_LAST_REFRESH = 'last_refresh_date';
    
    /**
     * Meta key for storing refresh count
     * 
     * @var string
     */
    const META_KEY_REFRESH_COUNT = 'refresh_count';
    
    /**
     * Check if a listing can be refreshed
     * 
     * @param int $post_id The listing post ID
     * @return bool Whether the listing can be refreshed
     */
    public function can_refresh($post_id) {
        // Validate post ID
        if (!$post_id || !$this->is_valid_car_listing($post_id)) {
            return false;
        }
        
        // Check if listing is published
        if (get_post_status($post_id) !== 'publish') {
            return false;
        }
        
        // Check if listing is sold
        if (get_field('is_sold', $post_id)) {
            return false;
        }
        
        // Check cooldown period
        $last_refresh = $this->get_last_refresh_date($post_id);
        if (!$last_refresh) {
            return true; // Never refreshed before
        }
        
        return $this->is_cooldown_expired($last_refresh);
    }
    
    /**
     * Refresh a listing
     * 
     * @param int $post_id The listing post ID
     * @param int $user_id The user attempting the refresh
     * @return array Result with success status and message
     */
    public function refresh_listing($post_id, $user_id) {
        // Validate inputs
        if (!$post_id || !$user_id) {
            return $this->create_error_response('Invalid parameters');
        }
        
        // Verify ownership
        if (!$this->user_owns_listing($post_id, $user_id)) {
            return $this->create_error_response('Unauthorized access');
        }
        
        // Check if can refresh
        if (!$this->can_refresh($post_id)) {
            $next_refresh = $this->get_next_refresh_date($post_id);
            return $this->create_error_response(
                'Cannot refresh yet. Next refresh available: ' . 
                date_i18n('F j, Y', strtotime($next_refresh))
            );
        }
        
        // Perform refresh
        $result = $this->perform_refresh($post_id);
        
        if ($result) {
            return $this->create_success_response('Listing refreshed successfully!');
        }
        
        return $this->create_error_response('Failed to refresh listing');
    }
    
    /**
     * Perform the actual refresh operation
     * 
     * @param int $post_id The listing post ID
     * @return bool Success status
     */
    private function perform_refresh($post_id) {
        $current_time = current_time('mysql');
        $current_time_gmt = get_gmt_from_date($current_time);
        
        // Update BOTH post_date and post_modified to bump listing to top
        // This ensures it works with orderby='date' queries
        $update_result = wp_update_post(array(
            'ID' => $post_id,
            'post_date' => $current_time,
            'post_date_gmt' => $current_time_gmt,
            'post_modified' => $current_time,
            'post_modified_gmt' => $current_time_gmt
        ), true);
        
        if (is_wp_error($update_result)) {
            $this->log_error('Failed to update post: ' . $update_result->get_error_message());
            return false;
        }
        
        // IMPORTANT: Update publication_date field (used for display)
        update_post_meta($post_id, 'publication_date', $current_time);
        
        // Also update ACF field if ACF is active
        if (function_exists('update_field')) {
            update_field('publication_date', $current_time, $post_id);
        }
        
        // Update last refresh meta
        update_post_meta($post_id, self::META_KEY_LAST_REFRESH, $current_time);
        
        // Increment refresh count
        $this->increment_refresh_count($post_id);
        
        // Log the refresh
        $this->log_refresh($post_id);
        
        return true;
    }
    
    /**
     * Get the last refresh date for a listing
     * 
     * @param int $post_id The listing post ID
     * @return string|null The last refresh date or null
     */
    public function get_last_refresh_date($post_id) {
        return get_post_meta($post_id, self::META_KEY_LAST_REFRESH, true);
    }
    
    /**
     * Get the next allowed refresh date
     * 
     * @param int $post_id The listing post ID
     * @return string|null The next refresh date or null
     */
    public function get_next_refresh_date($post_id) {
        $last_refresh = $this->get_last_refresh_date($post_id);
        
        if (!$last_refresh) {
            return current_time('mysql');
        }
        
        $next_date = date(
            'Y-m-d H:i:s',
            strtotime($last_refresh . ' +' . self::REFRESH_COOLDOWN_DAYS . ' days')
        );
        
        return $next_date;
    }
    
    /**
     * Get time remaining until next refresh
     * 
     * @param int $post_id The listing post ID
     * @return string Human-readable time remaining
     */
    public function get_time_until_refresh($post_id) {
        if ($this->can_refresh($post_id)) {
            return 'Available now';
        }
        
        $next_refresh = $this->get_next_refresh_date($post_id);
        $now = current_time('timestamp');
        $next_timestamp = strtotime($next_refresh);
        
        $diff = $next_timestamp - $now;
        
        if ($diff <= 0) {
            return 'Available now';
        }
        
        $days = floor($diff / (60 * 60 * 24));
        $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
        
        if ($days > 0) {
            return sprintf('%d day%s', $days, $days > 1 ? 's' : '');
        }
        
        return sprintf('%d hour%s', $hours, $hours > 1 ? 's' : '');
    }
    
    /**
     * Get refresh count for a listing
     * 
     * @param int $post_id The listing post ID
     * @return int The refresh count
     */
    public function get_refresh_count($post_id) {
        $count = get_post_meta($post_id, self::META_KEY_REFRESH_COUNT, true);
        return $count ? intval($count) : 0;
    }
    
    /**
     * Check if cooldown period has expired
     * 
     * @param string $last_refresh Last refresh date
     * @return bool Whether cooldown has expired
     */
    private function is_cooldown_expired($last_refresh) {
        $cooldown_seconds = self::REFRESH_COOLDOWN_DAYS * 24 * 60 * 60;
        $last_refresh_timestamp = strtotime($last_refresh);
        $now = current_time('timestamp');
        
        return ($now - $last_refresh_timestamp) >= $cooldown_seconds;
    }
    
    /**
     * Increment the refresh count
     * 
     * @param int $post_id The listing post ID
     * @return void
     */
    private function increment_refresh_count($post_id) {
        $current_count = $this->get_refresh_count($post_id);
        update_post_meta($post_id, self::META_KEY_REFRESH_COUNT, $current_count + 1);
    }
    
    /**
     * Verify if post is a valid car listing
     * 
     * @param int $post_id The post ID
     * @return bool Whether it's a valid car listing
     */
    private function is_valid_car_listing($post_id) {
        $post = get_post($post_id);
        return $post && $post->post_type === 'car';
    }
    
    /**
     * Check if user owns the listing
     * 
     * @param int $post_id The listing post ID
     * @param int $user_id The user ID
     * @return bool Whether user owns the listing
     */
    private function user_owns_listing($post_id, $user_id) {
        $post = get_post($post_id);
        return $post && $post->post_author == $user_id;
    }
    
    /**
     * Create success response
     * 
     * @param string $message Success message
     * @return array Response array
     */
    private function create_success_response($message) {
        return array(
            'success' => true,
            'message' => $message
        );
    }
    
    /**
     * Create error response
     * 
     * @param string $message Error message
     * @return array Response array
     */
    private function create_error_response($message) {
        return array(
            'success' => false,
            'message' => $message
        );
    }
    
    /**
     * Log refresh activity
     * 
     * @param int $post_id The listing post ID
     * @return void
     */
    private function log_refresh($post_id) {
        if (WP_DEBUG === true) {
            $user_id = get_current_user_id();
            $count = $this->get_refresh_count($post_id);
            error_log(sprintf(
                'Listing refreshed - Post ID: %d, User ID: %d, Refresh Count: %d',
                $post_id,
                $user_id,
                $count
            ));
        }
    }
    
    /**
     * Log error
     * 
     * @param string $message Error message
     * @return void
     */
    private function log_error($message) {
        if (WP_DEBUG === true) {
            error_log('RefreshListingManager Error: ' . $message);
        }
    }
}

