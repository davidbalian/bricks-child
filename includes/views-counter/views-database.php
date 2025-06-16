<?php
/**
 * Car Views Database Operations
 * 
 * Handles database table creation, cleanup, and core database operations
 * for the car views tracking system.
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
        
        // NOTE: Table creation handled by one-time script (create-views-table.php)
        // No ongoing checks needed - table assumed to exist after manual setup
    }
    

    
    /**
     * Record a new view for a car
     * 
     * @param int $car_id The car post ID
     * @param string $ip_address The visitor's IP address
     * @param string $user_agent The visitor's user agent
     * @param int $user_id The user ID if logged in (0 for guests)
     * @return bool True if view was recorded, false if duplicate/error
     */
    public function record_view($car_id, $ip_address, $user_agent, $user_id = 0) {
        global $wpdb;
        
        if (!$car_id || !$ip_address || !$user_agent) {
            return false;
        }
        
        // Create privacy-compliant hashes
        $ip_hash = hash('sha256', $ip_address . wp_salt());
        $agent_hash = hash('sha256', $user_agent . wp_salt());
        
        // Try to insert the view (will fail silently if duplicate due to UNIQUE constraint)
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'car_id' => $car_id,
                'user_ip_hash' => $ip_hash,
                'user_id' => $user_id,
                'user_agent_hash' => $agent_hash,
                'view_date' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s')
        );
        
        if ($result !== false) {
            // Update the cached count
            $this->update_total_views_cache($car_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get total unique views for a car
     * 
     * @param int $car_id The car post ID
     * @return int Number of unique views
     */
    public function get_total_views($car_id) {
        // First try to get from cache (post meta)
        $cached_views = get_post_meta($car_id, 'total_unique_views', true);
        
        if ($cached_views !== '') {
            return (int) $cached_views;
        }
        
        // If not cached, calculate and cache it
        return $this->update_total_views_cache($car_id);
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
            "SELECT COUNT(DISTINCT CONCAT(user_ip_hash, user_agent_hash)) FROM {$this->table_name} WHERE car_id = %d",
            $car_id
        ));
        
        $count = (int) $count;
        update_post_meta($car_id, 'total_unique_views', $count);
        
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
            delete_post_meta($post_id, 'total_unique_views');
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
        
        // Total unique views
        $stats['total_unique'] = $this->get_total_views($car_id);
        
        // Views in last 7 days
        $stats['last_7_days'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(user_ip_hash, user_agent_hash)) 
             FROM {$this->table_name} 
             WHERE car_id = %d AND view_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $car_id
        ));
        
        // Views in last 30 days
        $stats['last_30_days'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(user_ip_hash, user_agent_hash)) 
             FROM {$this->table_name} 
             WHERE car_id = %d AND view_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
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
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE view_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        ));
    }
}

// Initialize the database class
new CarViewsDatabase(); 