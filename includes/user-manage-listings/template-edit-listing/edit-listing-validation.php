<?php
/**
 * Edit Listing Validation - Form Validation Logic
 * Separated from car-submission.php for better organization
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate edit listing form data
 * 
 * @param array $data Form data to validate
 * @param int $car_id Car ID being edited
 * @return array Array of validation errors (empty if valid)
 */
function validate_edit_listing_form($data, $car_id) {
    $errors = array();
    $editable_fields = array(
        'mileage' => 'Mileage',
        'price' => 'Price',
        'description' => 'Description'
    );
    
    // Check for required editable fields
    $missing_fields = array();
    foreach ($editable_fields as $field_key => $field_label) {
        if (!isset($data[$field_key]) || empty(trim($data[$field_key]))) {
            $missing_fields[] = $field_key;
        }
    }
    
    if (!empty($missing_fields)) {
        $errors['missing_fields'] = $missing_fields;
    }
    
    return $errors;
}

/**
 * Validate car ownership
 * 
 * @param int $car_id Car ID to validate
 * @param int $user_id User ID to check ownership against
 * @return bool True if user owns the car, false otherwise
 */
function validate_car_ownership($car_id, $user_id) {
    if (!$car_id) {
        return false;
    }
    
    $car = get_post($car_id);
    if (!$car || $car->post_type !== 'car' || $car->post_author != $user_id) {
        return false;
    }
    
    return true;
}

/**
 * Validate nonce for edit listing form
 * 
 * @param array $data Form data containing nonce
 * @return bool True if nonce is valid, false otherwise
 */
function validate_edit_listing_nonce($data) {
    if (!isset($data['edit_car_listing_nonce'])) {
        return false;
    }
    
    return wp_verify_nonce($data['edit_car_listing_nonce'], 'edit_car_listing_nonce');
}

/**
 * Validate image requirements - UPDATED FOR CONSISTENT IMAGE HANDLING
 * 
 * @param array $existing_images Existing car images (ALL images from car_images field)
 * @param array $removed_images Images to be removed
 * @param array $new_images New images being uploaded
 * @return array Array with 'valid' boolean and 'message' string
 */
function validate_image_requirements($existing_images, $removed_images, $new_images) {
    if (!is_array($existing_images)) {
        $existing_images = array();
    }
    
    // FIXED: car_images field now contains ALL images (no separate featured image)
    // Calculate final image count after removals and additions
    $remaining_existing = $existing_images;
    if (!empty($removed_images)) {
        foreach ($removed_images as $removed_id) {
            $key = array_search($removed_id, $remaining_existing);
            if ($key !== false) {
                unset($remaining_existing[$key]);
            }
        }
    }
    
    $new_image_count = 0;
    if (!empty($new_images['car_images']['name'][0])) {
        $new_image_count = count($new_images['car_images']['name']);
    }
    
    // FIXED: Simple count - no featured image adjustment needed
    $final_count = count($remaining_existing) + $new_image_count;
    
    if ($final_count < 5) {
        return array(
            'valid' => false,
            'message' => 'At least 5 images are required. Current count: ' . $final_count
        );
    }
    
    return array(
        'valid' => true,
        'message' => ''
    );
} 