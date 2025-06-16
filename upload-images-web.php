<?php
/**
 * Web-Accessible Bulk Image Upload Script
 * Upload this file to your WordPress root directory
 */

// Load WordPress
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

echo "<h1>🚗 Car Images Bulk Upload</h1>";

// Path to images
$images_folder = ABSPATH . 'wp-content/uploads/temp-car-images/';

// Check if folder exists
if (!is_dir($images_folder)) {
    die("<p style='color:red;'>❌ Folder not found: $images_folder</p><p>Please create the folder and add your 100 car images.</p>");
}

// Get image files
$image_files = glob($images_folder . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);

if (empty($image_files)) {
    die("<p style='color:red;'>❌ No images found in: $images_folder</p>");
}

echo "<p>📸 Found " . count($image_files) . " images to upload...</p>";
echo "<div style='background:#f0f0f0; padding:10px; margin:10px 0; font-family:monospace;'>";

$attachment_ids = [];

foreach ($image_files as $index => $image_path) {
    $filename = basename($image_path);
    
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
        echo "<span style='color:red;'>❌ Failed: $filename - " . $attachment_id->get_error_message() . "</span><br>";
        continue;
    }
    
    $attachment_ids[] = $attachment_id;
    echo "<span style='color:green;'>✅ Uploaded: $filename (ID: $attachment_id)</span><br>";
    
    // Flush output for real-time progress
    flush();
    ob_flush();
    
    // Small delay
    usleep(100000);
}

echo "</div>";

if (!empty($attachment_ids)) {
    echo "<h2>🎉 Upload Complete!</h2>";
    echo "<p><strong>Total uploaded:</strong> " . count($attachment_ids) . " images</p>";
    
    // Save IDs to file
    $ids_string = implode(',', $attachment_ids);
    file_put_contents(ABSPATH . 'stock-car-image-ids.txt', $ids_string);
    
    echo "<p>💾 Image IDs saved to: stock-car-image-ids.txt</p>";
    echo "<p><strong>🚀 Next step:</strong> Visit <a href='create-listings-web.php'>create-listings-web.php</a> to create your car listings!</p>";
    
    echo "<details><summary>📋 Image IDs (for reference)</summary>";
    echo "<textarea style='width:100%;height:100px;'>" . $ids_string . "</textarea>";
    echo "</details>";
} else {
    echo "<p style='color:red;'>❌ No images were uploaded successfully.</p>";
}
?> 