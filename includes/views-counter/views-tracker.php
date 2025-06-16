<?php
/**
 * Car Views Tracker
 * 
 * Main class for tracking car listing views automatically.
 * Handles visitor detection, unique view logic, and integration
 * with the car listing pages.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CarViewsTracker {
    
    private $database;
    
    public function __construct() {
        // Initialize database handler
        $this->database = new CarViewsDatabase();
        
        // Hook into WordPress to track views ONLY on specific URLs
        add_action('wp', array($this, 'maybe_track_view'));
    }
    

    
    /**
     * Main function to determine if we should track a view
     * Called on every WordPress page load
     * 
     * ONLY tracks on URLs matching EXACTLY: /car/carname/?car_id=####
     * Example: /car/2010-acura-ilx-ilx/?car_id=2261
     * 
     * NO OTHER DETECTION METHODS - ONLY THIS URL PATTERN
     */
    public function maybe_track_view() {
        // STRICT CHECK: Must have ALL three conditions
        // 1. Must be a single car post page
        // 2. Must have ?car_id=#### in URL 
        // 3. car_id must match the actual post ID
        
        if (!is_singular('car')) {
            return; // Not a car post page - STOP
        }
        
        if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
            return; // No car_id parameter in URL - STOP
        }
        
        $car_id = intval($_GET['car_id']);
        if (!$car_id) {
            return; // Invalid car_id - STOP
        }
        
        $current_post_id = get_the_ID();
        if ($car_id !== $current_post_id) {
            return; // car_id doesn't match current post - STOP
        }
        
        // ALL checks passed - track the view
        $this->track_view($car_id);
    }
    

    
    /**
     * Track a view for a specific car
     * 
     * @param int $car_id The car post ID
     * @return bool True if view was tracked, false otherwise
     */
    public function track_view($car_id) {
        // Validate car ID
        if (!$car_id || !$this->is_valid_car($car_id)) {
            return false;
        }
        
        // Don't track admin users (optional)
        if (current_user_can('manage_options')) {
            return false;
        }
        
        // Don't track the car owner's views
        if ($this->is_car_owner($car_id)) {
            return false;
        }
        
        // Don't track bot visits
        if ($this->is_bot_visit()) {
            return false;
        }
        
        // Get visitor information
        $ip_address = $this->get_visitor_ip();
        $user_agent = $this->get_visitor_user_agent();
        $user_id = get_current_user_id();
        
        if (!$ip_address || !$user_agent) {
            return false;
        }
        
        // Record the view
        return $this->database->record_view($car_id, $ip_address, $user_agent, $user_id);
    }
    
    /**
     * Check if the given ID is a valid car post
     * 
     * @param int $car_id The car post ID
     * @return bool True if valid car post
     */
    private function is_valid_car($car_id) {
        $post = get_post($car_id);
        return $post && $post->post_type === 'car' && $post->post_status === 'publish';
    }
    
    /**
     * Check if the current user is the owner of the car
     * 
     * @param int $car_id The car post ID
     * @return bool True if current user owns the car
     */
    private function is_car_owner($car_id) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $post = get_post($car_id);
        return $post && $post->post_author == $user_id;
    }
    
    /**
     * Detect if the current visitor is a bot/crawler
     * 
     * @return bool True if likely a bot
     */
    private function is_bot_visit() {
        $user_agent = $this->get_visitor_user_agent();
        
        if (!$user_agent) {
            return true; // No user agent = likely bot
        }
        
        // Common bot patterns
        $bot_patterns = array(
            'bot', 'crawler', 'spider', 'scraper', 'facebookexternalhit',
            'twitterbot', 'linkedinbot', 'whatsapp', 'telegram',
            'googlebot', 'bingbot', 'slurp', 'duckduckbot',
            'baiduspider', 'yandexbot', 'facebot', 'ia_archiver'
        );
        
        $user_agent_lower = strtolower($user_agent);
        
        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent_lower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the visitor's IP address (with proxy support)
     * 
     * @return string|false The IP address or false if not found
     */
    private function get_visitor_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR even if it's private (for local development)
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
    }
    
    /**
     * Get the visitor's user agent
     * 
     * @return string|false The user agent or false if not found
     */
    private function get_visitor_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : false;
    }
    
    /**
     * Get view count for a car (public method for shortcodes)
     * 
     * @param int $car_id The car post ID
     * @return int Number of unique views
     */
    public function get_view_count($car_id) {
        return $this->database->get_total_views($car_id);
    }
    
    /**
     * Get detailed view statistics for a car
     * 
     * @param int $car_id The car post ID
     * @return array Array of view statistics
     */
    public function get_view_stats($car_id) {
        return $this->database->get_view_stats($car_id);
    }
    
    /**
     * Force update the view count cache for a car
     * Useful for admin actions or maintenance
     * 
     * @param int $car_id The car post ID
     * @return int The updated view count
     */
    public function refresh_view_count($car_id) {
        // We'll call the database method to recalculate and cache the count
        $this->database->get_total_views($car_id);
        return $this->database->get_total_views($car_id);
    }
}

// Initialize the tracker
global $car_views_tracker;
$car_views_tracker = new CarViewsTracker(); 