<?php
/**
 * Refresh Listing AJAX Handler
 * 
 * Handles AJAX requests for listing refresh functionality
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class RefreshListingAjaxHandler
 * 
 * Handles AJAX operations for refreshing car listings
 */
class RefreshListingAjaxHandler {
    
    /**
     * The refresh listing manager instance
     * 
     * @var RefreshListingManager
     */
    private $manager;
    
    /**
     * AJAX action name
     * 
     * @var string
     */
    const AJAX_ACTION = 'refresh_car_listing';
    
    /**
     * Nonce action name
     * 
     * @var string
     */
    const NONCE_ACTION = 'refresh_listing_nonce';
    
    /**
     * Constructor
     * 
     * @param RefreshListingManager $manager The manager instance
     */
    public function __construct(RefreshListingManager $manager) {
        $this->manager = $manager;
        $this->register_hooks();
    }
    
    /**
     * Register WordPress hooks
     * 
     * @return void
     */
    private function register_hooks() {
        add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'handle_refresh_request'));
    }
    
    /**
     * Handle AJAX refresh request
     * 
     * @return void
     */
    public function handle_refresh_request() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            $this->send_error_response('Security check failed');
            return;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            $this->send_error_response('User not logged in');
            return;
        }
        
        // Get and validate car ID
        $car_id = $this->get_car_id_from_request();
        if (!$car_id) {
            $this->send_error_response('Invalid car ID');
            return;
        }
        
        // Perform refresh
        $user_id = get_current_user_id();
        $result = $this->manager->refresh_listing($car_id, $user_id);
        
        if ($result['success']) {
            $this->send_success_response($result['message'], array(
                'car_id' => $car_id,
                'next_refresh' => $this->manager->get_next_refresh_date($car_id),
                'refresh_count' => $this->manager->get_refresh_count($car_id)
            ));
        } else {
            $this->send_error_response($result['message']);
        }
    }
    
    /**
     * Verify AJAX nonce
     * 
     * @return bool Whether nonce is valid
     */
    private function verify_nonce() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        return wp_verify_nonce($nonce, self::NONCE_ACTION);
    }
    
    /**
     * Get car ID from AJAX request
     * 
     * @return int|false Car ID or false
     */
    private function get_car_id_from_request() {
        if (!isset($_POST['car_id'])) {
            return false;
        }
        
        $car_id = intval($_POST['car_id']);
        return $car_id > 0 ? $car_id : false;
    }
    
    /**
     * Send success response
     * 
     * @param string $message Success message
     * @param array $data Additional data
     * @return void
     */
    private function send_success_response($message, $data = array()) {
        wp_send_json_success(array_merge(
            array('message' => $message),
            $data
        ));
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @return void
     */
    private function send_error_response($message) {
        wp_send_json_error(array('message' => $message));
    }
    
    /**
     * Create nonce for frontend use
     * 
     * @return string The nonce
     */
    public static function create_nonce() {
        return wp_create_nonce(self::NONCE_ACTION);
    }
    
    /**
     * Get AJAX action name
     * 
     * @return string The AJAX action
     */
    public static function get_ajax_action() {
        return self::AJAX_ACTION;
    }
}

