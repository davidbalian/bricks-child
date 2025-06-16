<?php
/**
 * Bulk Create 1000 Car Listings
 * 
 * This script creates realistic car listings using your existing JSON data
 * and the pre-uploaded stock car images.
 */

// WordPress environment
require_once(__DIR__ . '/wp-load.php');

// Stock car image IDs (replace with IDs from bulk-upload-images.php output)
$stock_car_image_ids = []; // PASTE YOUR IMAGE IDS HERE

// If no image IDs provided, read from file
if (empty($stock_car_image_ids)) {
    $ids_file = ABSPATH . 'stock-car-image-ids.txt';
    if (file_exists($ids_file)) {
        $ids_content = file_get_contents($ids_file);
        $stock_car_image_ids = array_map('intval', explode(',', $ids_content));
    }
}

if (empty($stock_car_image_ids)) {
    die("❌ No stock images found. Please run bulk-upload-images.php first.\n");
}

echo "📸 Found " . count($stock_car_image_ids) . " stock images\n";

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
    die("❌ No car makes data found in simple_jsons folder.\n");
}

echo "🚗 Found " . count($makes_data) . " car makes\n";

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

echo "\n🚀 Starting bulk creation of $target_count listings...\n\n";

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
    $engine_capacity = rand(10, 60) / 10; // 1.0L to 6.0L
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
        'post_status' => 'publish', // or 'pending' if you want to review first
        'post_type' => 'car',
        'post_author' => 1, // Admin user
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        echo "❌ Failed to create listing $i: " . $post_id->get_error_message() . "\n";
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
    
    // Progress update every 50 listings
    if ($i % 50 === 0) {
        echo "✅ Created $i/$target_count listings...\n";
    }
    
    // Small delay to prevent server overload
    usleep(50000); // 0.05 seconds
}

echo "\n🎉 Bulk creation complete!\n";
echo "📊 Successfully created: $created_count listings\n";
echo "🔗 Visit your site to see the new car listings!\n";
?> 