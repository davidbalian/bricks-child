<?php
/**
 * Car Views Database Operations
 * 
 * Handles database table creation, cleanup, and core database operations
 * for the car views tracking system with dual metrics:
 * - Unique Views: One per visitor per listing (forever)
 * - Total Views: 5-minute cooldown per visitor per listing
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CarViewsDatabase {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'car_views';
        
        // Hook into post deletion for cleanup
        add_action('before_delete_post', array($this, 'cleanup_views_on_delete'));
        add_action('wp_trash_post', array($this, 'cleanup_views_on_trash'));
        
        // NOTE: Table creation handled 
        // No ongoing checks needed - table assumed to exist after manual setup
    }
    
    /**
     * Record a new view for a car - implements MVP dual tracking logic
     * 
     * @param int $car_id The car post ID
     * @param string $ip_address The visitor's IP address
     * @param string $user_agent The visitor's user agent
     * @param int $user_id The user ID if logged in (0 for guests)
     * @return array Results with unique_view and total_view booleans
     */
    public function record_view($car_id, $ip_address, $user_agent, $user_id = 0) {
        global $wpdb;
        
        if (!$car_id || !$ip_address || !$user_agent) {
            return array('unique_view' => false, 'total_view' => false);
        }
        
        // Create fingerprint hash (IP + User Agent combined)
        $fingerprint = hash('sha256', $ip_address . '|' . $user_agent . wp_salt());
        
        $results = array('unique_view' => false, 'total_view' => false);
        
        // Check for existing unique view
        $existing_unique = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE car_id = %d AND fingerprint_hash = %s AND view_type = 'unique'",
            $car_id, $fingerprint
        ));
        
        // If no unique view exists, record it
        if (!$existing_unique) {
            $unique_result = $wpdb->insert(
                $this->table_name,
                array(
                    'car_id' => $car_id,
                    'user_ip_hash' => hash('sha256', $ip_address . wp_salt()),
                    'user_id' => $user_id,
                    'user_agent_hash' => hash('sha256', $user_agent . wp_salt()),
                    'view_date' => current_time('mysql'),
                    'view_type' => 'unique',
                    'fingerprint_hash' => $fingerprint
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
            
            if ($unique_result !== false) {
                $results['unique_view'] = true;
                $this->update_unique_views_cache($car_id);
            }
        }
        
        // Check for recent total view (within 5 minutes)
        $recent_total = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE car_id = %d AND fingerprint_hash = %s AND view_type = 'total'
             AND view_date > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY view_date DESC LIMIT 1",
            $car_id, $fingerprint
        ));
        
        // If no recent total view, record it
        if (!$recent_total) {
            $total_result = $wpdb->insert(
                $this->table_name,
                array(
                    'car_id' => $car_id,
                    'user_ip_hash' => hash('sha256', $ip_address . wp_salt()),
                    'user_id' => $user_id,
                    'user_agent_hash' => hash('sha256', $user_agent . wp_salt()),
                    'view_date' => current_time('mysql'),
                    'view_type' => 'total',
                    'fingerprint_hash' => $fingerprint
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
            
            if ($total_result !== false) {
                $results['total_view'] = true;
                $this->update_total_views_cache($car_id);
            }
        }
        
        return $results;
    }
    
    /**
     * Get unique views count for a car
     * 
     * @param int $car_id The car post ID
     * @return int Number of unique visitors
     */
    public function get_unique_views($car_id) {
        // First try to get from cache (post meta)
        $cached_views = get_post_meta($car_id, 'unique_views_count', true);
        
        if ($cached_views !== '') {
            return (int) $cached_views;
        }
        
        // If not cached, calculate and cache it
        return $this->update_unique_views_cache($car_id);
    }
    
    /**
     * Get total views count for a car
     * 
     * @param int $car_id The car post ID
     * @return int Number of total views
     */
    public function get_total_views($car_id) {
        // First try to get from cache (post meta)
        $cached_views = get_post_meta($car_id, 'total_views_count', true);
        
        if ($cached_views !== '') {
            return (int) $cached_views;
        }
        
        // If not cached, calculate and cache it
        return $this->update_total_views_cache($car_id);
    }
    
    /**
     * Get both view counts for display
     * 
     * @param int $car_id The car post ID
     * @return array Array with 'total' and 'unique' counts
     */
    public function get_view_counts($car_id) {
        return array(
            'total' => $this->get_total_views($car_id),
            'unique' => $this->get_unique_views($car_id)
        );
    }
    
    /**
     * Update the cached unique views count for a car
     * 
     * @param int $car_id The car post ID
     * @return int The updated view count
     */
    private function update_unique_views_cache($car_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE car_id = %d AND view_type = 'unique'",
            $car_id
        ));
        
        $count = (int) $count;
        update_post_meta($car_id, 'unique_views_count', $count);
        
        return $count;
    }
    
    /**
     * Update the cached total views count for a car
     * 
     * @param int $car_id The car post ID
     * @return int The updated view count
     */
    private function update_total_views_cache($car_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE car_id = %d AND view_type = 'total'",
            $car_id
        ));
        
        $count = (int) $count;
        update_post_meta($car_id, 'total_views_count', $count);
        
        return $count;
    }
    
    /**
     * Clean up view records when a car post is permanently deleted
     * 
     * @param int $post_id The post ID being deleted
     */
    public function cleanup_views_on_delete($post_id) {
        $post = get_post($post_id);
        
        // Only handle car post type
        if ($post && $post->post_type === 'car') {
            global $wpdb;
            
            $wpdb->delete(
                $this->table_name,
                array('car_id' => $post_id),
                array('%d')
            );
            
            // Also remove the cached meta
            delete_post_meta($post_id, 'unique_views_count');
            delete_post_meta($post_id, 'total_views_count');
        }
    }
    
    /**
     * Handle when a car post is moved to trash
     * We'll keep the data for now in case it's restored
     * 
     * @param int $post_id The post ID being trashed
     */
    public function cleanup_views_on_trash($post_id) {
        // For now, we'll keep the data when trashed
        // Data will only be deleted when permanently deleted
        // This allows for data recovery if the post is restored
    }
    
    /**
     * Get view statistics for a car (for admin/analytics)
     * 
     * @param int $car_id The car post ID
     * @return array Array of view statistics
     */
    public function get_view_stats($car_id) {
        global $wpdb;
        
        $stats = array();
        
        // Current counts
        $stats['total_views'] = $this->get_total_views($car_id);
        $stats['unique_views'] = $this->get_unique_views($car_id);
        
        // Recent activity
        $stats['total_last_7_days'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE car_id = %d AND view_type = 'total' AND view_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $car_id
        ));
        
        $stats['unique_last_7_days'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE car_id = %d AND view_type = 'unique' AND view_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $car_id
        ));
        
        return $stats;
    }
    
    /**
     * Clean up old view records (optional maintenance)
     * 
     * @param int $days_old Delete records older than this many days
     */
    public function cleanup_old_records($days_old = 365) {
        global $wpdb;
        
        // Only clean up total view records (keep unique views forever for analytics)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE view_type = 'total' AND view_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        ));
    }
}

// Initialize the database class
new CarViewsDatabase(); 