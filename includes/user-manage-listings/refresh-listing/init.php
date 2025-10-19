<?php
/**
 * Refresh Listing Initialization
 * 
 * Initializes the refresh listing system
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Initialize Refresh Listing System
 * 
 * Sets up all components and hooks for the refresh listing feature
 */
function init_refresh_listing_system() {
    // Include required files
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingManager.php';
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingAjaxHandler.php';
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingUI.php';
    
    // Initialize the manager
    $refresh_manager = new RefreshListingManager();
    
    // Initialize and register AJAX handler
    new RefreshListingAjaxHandler($refresh_manager);
    
    // Log initialization if in debug mode
    if (WP_DEBUG === true) {
        error_log('Refresh Listing System initialized successfully');
    }
}

// Hook into WordPress init
add_action('init', 'init_refresh_listing_system');

