<?php
/**
 * Quick Add Form Functionality
 * 
 * Provides form duplication and auto-fill features for faster car entry
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Enqueue quick add form scripts and styles
 */
function enqueue_quick_add_scripts() {
    wp_enqueue_script(
        'quick-add-form-js',
        get_stylesheet_directory_uri() . '/includes/user-manage-listings/bulk-fast-uploads/quick-add-form.js',
        array('jquery'),
        filemtime(get_stylesheet_directory() . '/includes/user-manage-listings/bulk-fast-uploads/quick-add-form.js'),
        true
    );

    wp_localize_script('quick-add-form-js', 'quickAddData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('quick_add_nonce')
    ));
}

/**
 * Handle quick template save
 */
function handle_save_quick_template() {
    if (!wp_verify_nonce($_POST['nonce'], 'quick_add_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!is_user_logged_in()) {
        wp_die('Not logged in');
    }
    
    $template_name = sanitize_text_field($_POST['template_name']);
    $template_data = array();
    
    // Save common fields that can be reused
    $reusable_fields = array(
        'make', 'engine_capacity', 'fuel_type', 'transmission', 'body_type',
        'drive_type', 'exterior_color', 'interior_color', 'car_city', 
        'car_district', 'extras', 'vehiclehistory'
    );
    
    foreach ($reusable_fields as $field) {
        if (isset($_POST[$field])) {
            $template_data[$field] = sanitize_text_field($_POST[$field]);
        }
    }
    
    // Save template to user meta
    $user_templates = get_user_meta(get_current_user_id(), 'car_form_templates', true);
    if (!$user_templates) {
        $user_templates = array();
    }
    
    $user_templates[$template_name] = $template_data;
    update_user_meta(get_current_user_id(), 'car_form_templates', $user_templates);
    
    wp_send_json_success(array('message' => 'Template saved successfully'));
}

/**
 * Handle load quick template
 */
function handle_load_quick_template() {
    if (!wp_verify_nonce($_POST['nonce'], 'quick_add_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!is_user_logged_in()) {
        wp_die('Not logged in');
    }
    
    $template_name = sanitize_text_field($_POST['template_name']);
    $user_templates = get_user_meta(get_current_user_id(), 'car_form_templates', true);
    
    if ($user_templates && isset($user_templates[$template_name])) {
        wp_send_json_success($user_templates[$template_name]);
    } else {
        wp_send_json_error('Template not found');
    }
}

/**
 * Get user templates for display
 */
function get_user_form_templates() {
    if (!is_user_logged_in()) {
        return array();
    }
    
    $user_templates = get_user_meta(get_current_user_id(), 'car_form_templates', true);
    return $user_templates ? $user_templates : array();
}

// Hook the AJAX functions
add_action('wp_ajax_save_quick_template', 'handle_save_quick_template');
add_action('wp_ajax_load_quick_template', 'handle_load_quick_template'); 