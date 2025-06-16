<?php
/**
 * Bulk Upload Car Images to WordPress Media Library
 * 
 * Instructions:
 * 1. Extract your 100 car images to: /wp-content/uploads/temp-car-images/
 * 2. Run this script once to upload all images to media library
 * 3. Copy the attachment IDs output for use in bulk-create-listings.php
 */

// WordPress environment - load WordPress properly
require_once(__DIR__ . '/wp-load.php');

// Ensure we have the media functions
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// Path to your extracted car images
$images_folder = ABSPATH . 'wp-content/uploads/temp-car-images/';

// Check if folder exists
if (!is_dir($images_folder)) {
    die("Please extract your car images to: $images_folder\n");
}

// Get all image files
$image_files = glob($images_folder . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);

if (empty($image_files)) {
    die("No image files found in: $images_folder\n");
}

echo "Found " . count($image_files) . " images to upload...\n";

$attachment_ids = [];

foreach ($image_files as $index => $image_path) {
    $filename = basename($image_path);
    
    // Read file
    $file_content = file_get_contents($image_path);
    if ($file_content === false) {
        echo "Failed to read: $filename\n";
        continue;
    }
    
    // Create file array for WordPress
    $file_array = [
        'name' => 'stock-car-' . ($index + 1) . '.jpg',
        'tmp_name' => $image_path,
        'type' => mime_content_type($image_path),
        'size' => filesize($image_path),
        'error' => 0
    ];
    
    // Upload to WordPress
    $attachment_id = media_handle_sideload($file_array, 0, 'Stock car image ' . ($index + 1));
    
    if (is_wp_error($attachment_id)) {
        echo "Failed to upload $filename: " . $attachment_id->get_error_message() . "\n";
        continue;
    }
    
    $attachment_ids[] = $attachment_id;
    echo "Uploaded: $filename (ID: $attachment_id)\n";
    
    // Small delay to prevent server overload
    usleep(100000); // 0.1 second
}

echo "\n✅ Upload complete!\n";
echo "Total uploaded: " . count($attachment_ids) . " images\n\n";

// Output attachment IDs for use in bulk creation script
echo "📋 Copy this array for bulk-create-listings.php:\n";
echo '$stock_car_image_ids = [' . implode(', ', $attachment_ids) . '];' . "\n\n";

// Save IDs to file for easy access
file_put_contents(ABSPATH . 'stock-car-image-ids.txt', implode(',', $attachment_ids));
echo "💾 IDs also saved to: stock-car-image-ids.txt\n";

echo "\n🚀 Ready for Step 2: Run bulk-create-listings.php\n";
?> 