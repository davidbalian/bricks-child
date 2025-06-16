<?php
/**
 * Web-Accessible Car Listings Creation Script
 * Upload this file to your WordPress root directory
 */

// Load WordPress
require_once('wp-load.php');

echo "<h1>🚗 Bulk Create Car Listings</h1>";

// Load stock car image IDs
$stock_car_image_ids = [];
$ids_file = ABSPATH . 'stock-car-image-ids.txt';

if (file_exists($ids_file)) {
    $ids_content = file_get_contents($ids_file);
    $stock_car_image_ids = array_map('intval', explode(',', $ids_content));
}

if (empty($stock_car_image_ids)) {
    die("<p style='color:red;'>❌ No stock images found. Please run upload-images-web.php first.</p>");
}

echo "<p>📸 Found " . count($stock_car_image_ids) . " stock images</p>";

// Load car makes data from JSON files
$makes_data = [];
$jsons_dir = get_stylesheet_directory() . '/simple_jsons/';

if (is_dir($jsons_dir)) {
    $json_files = glob($jsons_dir . '*.json');
    foreach ($json_files as $file) {
        $content = file_get_contents($file);
        if ($content) {
            $data = json_decode($content, true);
            if ($data && json_last_error() === JSON_ERROR_NONE) {
                $make_name = array_key_first($data);
                if ($make_name) {
                    $makes_data[$make_name] = $data[$make_name];
                }
            }
        }
    }
}

if (empty($makes_data)) {
    die("<p style='color:red;'>❌ No car makes data found in simple_jsons folder.</p>");
}

echo "<p>🚗 Found " . count($makes_data) . " car makes</p>";

// Realistic data arrays
$fuel_types = ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Plug-in hybrid'];
$transmissions = ['Manual', 'Automatic', 'CVT'];
$body_types = ['Sedan', 'Hatchback', 'SUV', 'Coupe', 'Convertible', 'Estate', 'MPV'];
$drive_types = ['FWD', 'RWD', 'AWD', '4WD'];
$colors = ['Black', 'White', 'Silver', 'Grey', 'Blue', 'Red', 'Green', 'Brown', 'Beige'];
$interior_colors = ['Black', 'Beige', 'Grey', 'Brown', 'Red', 'Blue'];
$cities = ['Nicosia', 'Limassol', 'Larnaca', 'Paphos', 'Famagusta', 'Kyrenia'];
$districts = ['Nicosia District', 'Limassol District', 'Larnaca District', 'Paphos District', 'Famagusta District', 'Kyrenia District'];

// Start bulk creation
$created_count = 0;
$target_count = 10; // START SMALL FOR TESTING

echo "<p><strong>🚀 Starting bulk creation of $target_count listings...</strong></p>";
echo "<div style='background:#f0f0f0; padding:10px; margin:10px 0; font-family:monospace; height:300px; overflow-y:scroll;'>";

for ($i = 1; $i <= $target_count; $i++) {
    // Select random make and model
    $make_names = array_keys($makes_data);
    $random_make = $make_names[array_rand($make_names)];
    $models = array_keys($makes_data[$random_make]);
    $random_model = $models[array_rand($models)];
    $variants = $makes_data[$random_make][$random_model];
    $random_variant = $variants[array_rand($variants)];
    
    // Generate realistic specs
    $year = rand(2010, 2024);
    $mileage = rand(5000, 300000);
    $price = rand(5000, 150000);
    $engine_capacity = rand(10, 60) / 10;
    $hp = rand(100, 500);
    $doors = rand(3, 5);
    $seats = rand(2, 8);
    $num_owners = rand(1, 4);
    
    // Random selections
    $fuel_type = $fuel_types[array_rand($fuel_types)];
    $transmission = $transmissions[array_rand($transmissions)];
    $body_type = $body_types[array_rand($body_types)];
    $drive_type = $drive_types[array_rand($drive_types)];
    $exterior_color = $colors[array_rand($colors)];
    $interior_color = $interior_colors[array_rand($interior_colors)];
    $city = $cities[array_rand($cities)];
    $district = $districts[array_rand($districts)];
    
    // Create post title and description
    $post_title = "$year $random_make $random_model $random_variant";
    $description = "Beautiful $year $random_make $random_model $random_variant in excellent condition. " .
                  "This $exterior_color $body_type features a $engine_capacity" . "L $fuel_type engine with $transmission transmission. " .
                  "Well maintained with $mileage km on the odometer. Perfect for daily driving.";
    
    // Create the WordPress post
    $post_data = [
        'post_title' => $post_title,
        'post_content' => '',
        'post_status' => 'publish',
        'post_type' => 'car',
        'post_author' => 1,
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        echo "<span style='color:red;'>❌ Failed listing $i: " . $post_id->get_error_message() . "</span><br>";
        continue;
    }
    
    // Add all the ACF fields
    update_field('make', $random_make, $post_id);
    update_field('model', $random_model, $post_id);
    update_field('variant', $random_variant, $post_id);
    update_field('year', $year, $post_id);
    update_field('mileage', $mileage, $post_id);
    update_field('price', $price, $post_id);
    update_field('engine_capacity', $engine_capacity, $post_id);
    update_field('fuel_type', $fuel_type, $post_id);
    update_field('transmission', $transmission, $post_id);
    update_field('body_type', $body_type, $post_id);
    update_field('drive_type', $drive_type, $post_id);
    update_field('exterior_color', $exterior_color, $post_id);
    update_field('interior_color', $interior_color, $post_id);
    update_field('description', $description, $post_id);
    update_field('number_of_doors', $doors, $post_id);
    update_field('number_of_seats', $seats, $post_id);
    update_field('car_city', $city, $post_id);
    update_field('car_district', $district, $post_id);
    update_field('hp', $hp, $post_id);
    update_field('numowners', $num_owners, $post_id);
    update_field('isantique', ($year < 1990) ? 1 : 0, $post_id);
    
    // Assign random 5-7 images from stock
    $num_images = rand(5, 7);
    $random_image_keys = array_rand($stock_car_image_ids, $num_images);
    $selected_images = [];
    
    if (is_array($random_image_keys)) {
        foreach ($random_image_keys as $key) {
            $selected_images[] = $stock_car_image_ids[$key];
        }
    } else {
        $selected_images[] = $stock_car_image_ids[$random_image_keys];
    }
    
    update_field('car_images', $selected_images, $post_id);
    
    $created_count++;
    echo "<span style='color:green;'>✅ Created: $post_title (ID: $post_id)</span><br>";
    
    // Flush output for real-time progress
    flush();
    ob_flush();
    
    // Small delay
    usleep(50000);
}

echo "</div>";

echo "<h2>🎉 Bulk Creation Complete!</h2>";
echo "<p><strong>Successfully created:</strong> $created_count car listings</p>";
echo "<p>🔗 <a href='" . home_url() . "' target='_blank'>Visit your site to see the new listings!</a></p>";

if ($target_count == 10) {
    echo "<div style='background:#fffbf0; padding:15px; border-left:4px solid #ffb900; margin:20px 0;'>";
    echo "<h3>🚀 Ready for Full Scale?</h3>";
    echo "<p>Test completed successfully! To create 1000 listings:</p>";
    echo "<ol><li>Edit this file</li><li>Change <code>\$target_count = 10;</code> to <code>\$target_count = 1000;</code></li><li>Run this script again</li></ol>";
    echo "</div>";
}
?> 