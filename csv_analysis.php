<?php
/**
 * CSV Car Data Analysis
 * Analyzes the provided CSV data and identifies potential issues
 */

// CSV data from user
$csv_data = 'make,model,year,price,mileage,fuel_type,transmission,body_type,engine_capacity,hp,drive_type,exterior_color,interior_color,number_of_doors,number_of_seats,motuntil,extras,description,availability,post_status,num_owners,is_antique
BMW,2 Series,2023,54500,8000,Petrol,Automatic,Coupe,3.0,,Front-Wheel Drive,Blue,Black,2,4,,[],BMW M240i 2023 8600km WBA52CM0008D86352 brand new condition FACELIFT Fully loaded xDrive Big screen Arrives - late July,In Stock,pending,,False
BMW,2 Series,2020,24999,42000,Diesel,Automatic,Saloon,2.0,,Front-Wheel Drive,Red,Black,4,4,,"[\'alloy_wheels\', \'cruise_control\', \'keyless_start\', \'rear_view_camera\', \'start_stop\', \'apple_carplay\', \'folding_mirrors\', \'leather_seats\', \'parking_sensors\', \'adaptive_cruise_control\', \'blind_spot_mirror\', \'lane_assist\']",BMW 218d 2020 42.000km M-package Fully loaded Keyless LED headlights Active cruise control Limassol,In Stock,pending,,False
BMW,4 Series,2022,49999,42000,Petrol,Automatic,Saloon,3.0,,All-Wheel Drive,White,Black,,5,,[],BMW 440i 2022 42000km 3.0 petrol xDrive Fully loaded Laser headlights Keyless Pre-order price!!!! Arrives - end of July,In Stock,pending,,False
Rolls-Royce,Ghost,2019,185000,57000,Petrol,Automatic,Saloon,6.6,,Front-Wheel Drive,Black,Black,4,4,,[],"Rolls-Royce Ghost 2019 — Timeless Luxury, Effortless Power A symbol of pure prestige, the 2019 Ghost combines iconic British elegance with remarkable engineering. Specifications: • Engine: 6.6L V12 Twin-Turbocharged • Horsepower: 563 hp (420 kW) • Torque: 820 Nm • Drive: Rear-wheel drive • Transmission: 8-speed automatic • 0–100 km/h: Just 4.8 seconds • Length: 5,399 mm The Ghost offers an unrivaled driving experience — whisper-quiet inside, yet brutally powerful when needed. Every detail is handcrafted, every ride feels like a statement. A true masterpiece, now available at AutoCyprus",In Stock,pending,,False
BMW,X5,2022,62500,51000,Diesel,Automatic,SUV,3.0,,Front-Wheel Drive,Black,Black,4,5,,[],BMW X5 2022 51000km M-package Air Suspension Massage Seats Camera 360 Keyless Head Up display Led Headlights Fully loaded 7 seats Japan import,In Stock,pending,,False';

// Parse CSV data
$lines = explode("\n", trim($csv_data));
$headers = str_getcsv($lines[0]);
$cars = array();

for ($i = 1; $i < count($lines); $i++) {
    $row = str_getcsv($lines[$i]);
    if (count($row) === count($headers)) {
        $cars[] = array_combine($headers, $row);
    }
}

echo "<h1>CSV Car Data Analysis</h1>\n";
echo "<p>Total cars in data: " . count($cars) . "</p>\n\n";

// Analysis results
$issues = array();
$warnings = array();
$stats = array(
    'total_cars' => count($cars),
    'makes' => array(),
    'drive_type_issues' => 0,
    'missing_hp' => 0,
    'missing_doors' => 0,
    'extras_format_issues' => 0
);

foreach ($cars as $index => $car) {
    $row_num = $index + 1;
    $car_title = $car['year'] . ' ' . $car['make'] . ' ' . $car['model'];
    
    // Track makes
    if (!in_array($car['make'], $stats['makes'])) {
        $stats['makes'][] = $car['make'];
    }
    
    // Check drive type accuracy
    $description = strtolower($car['description']);
    $make = strtolower($car['make']);
    
    if ($car['drive_type'] === 'Front-Wheel Drive') {
        // Check for BMW xDrive models
        if ($make === 'bmw' && (strpos($description, 'xdrive') !== false)) {
            $issues[] = "Row $row_num ($car_title): BMW with xDrive should be All-Wheel Drive, not Front-Wheel Drive";
            $stats['drive_type_issues']++;
        }
        
        // Check for Rolls-Royce (should be RWD)
        if ($make === 'rolls-royce') {
            $issues[] = "Row $row_num ($car_title): Rolls-Royce should be Rear-Wheel Drive, not Front-Wheel Drive";
            $stats['drive_type_issues']++;
        }
        
        // Check for BMW X5 (should be AWD)
        if ($make === 'bmw' && strpos(strtolower($car['model']), 'x5') !== false) {
            $issues[] = "Row $row_num ($car_title): BMW X5 should typically be All-Wheel Drive, not Front-Wheel Drive";
            $stats['drive_type_issues']++;
        }
    }
    
    // Check for missing HP values
    if (empty($car['hp'])) {
        $warnings[] = "Row $row_num ($car_title): Missing horsepower (HP) value";
        $stats['missing_hp']++;
    }
    
    // Check for missing door counts
    if (empty($car['number_of_doors'])) {
        $warnings[] = "Row $row_num ($car_title): Missing number of doors";
        $stats['missing_doors']++;
    }
    
    // Check extras format
    $extras = $car['extras'];
    if (!empty($extras) && $extras !== '[]') {
        if (strpos($extras, '[') === 0) {
            // It's an array format - check if it's properly formatted
            if (strpos($extras, "\'") !== false || strpos($extras, '\"') !== false) {
                $warnings[] = "Row $row_num ($car_title): Extras contain escaped quotes that may need cleaning";
                $stats['extras_format_issues']++;
            }
        }
    }
}

// Display issues
if (!empty($issues)) {
    echo "<h2 style='color: red;'>Critical Issues Found (" . count($issues) . ")</h2>\n";
    echo "<ul>\n";
    foreach ($issues as $issue) {
        echo "<li style='color: red;'>$issue</li>\n";
    }
    echo "</ul>\n\n";
}

if (!empty($warnings)) {
    echo "<h2 style='color: orange;'>Warnings (" . count($warnings) . ")</h2>\n";
    echo "<ul>\n";
    foreach ($warnings as $warning) {
        echo "<li style='color: orange;'>$warning</li>\n";
    }
    echo "</ul>\n\n";
}

// Display statistics
echo "<h2>Data Statistics</h2>\n";
echo "<ul>\n";
echo "<li><strong>Total cars:</strong> " . $stats['total_cars'] . "</li>\n";
echo "<li><strong>Unique makes found:</strong> " . implode(', ', $stats['makes']) . "</li>\n";
echo "<li><strong>Drive type issues:</strong> " . $stats['drive_type_issues'] . "</li>\n";
echo "<li><strong>Missing HP values:</strong> " . $stats['missing_hp'] . "</li>\n";
echo "<li><strong>Missing door counts:</strong> " . $stats['missing_doors'] . "</li>\n";
echo "<li><strong>Extras format issues:</strong> " . $stats['extras_format_issues'] . "</li>\n";
echo "</ul>\n\n";

// Recommendations
echo "<h2>Recommendations</h2>\n";
echo "<ol>\n";

if ($stats['drive_type_issues'] > 0) {
    echo "<li><strong>Fix Drive Types:</strong> Update drive types for BMW xDrive models, Rolls-Royce, and other luxury/performance vehicles to be accurate.</li>\n";
}

if ($stats['missing_hp'] > 0) {
    echo "<li><strong>Add HP Values:</strong> Consider adding horsepower values for better search and filtering capabilities.</li>\n";
}

if ($stats['missing_doors'] > 0) {
    echo "<li><strong>Add Door Counts:</strong> Fill in missing door counts - this is typically important for buyers.</li>\n";
}

if ($stats['extras_format_issues'] > 0) {
    echo "<li><strong>Clean Extras Format:</strong> Some extras arrays contain escaped quotes that should be cleaned up before import.</li>\n";
}

echo "<li><strong>Verify Make/Model Matching:</strong> Ensure all makes and models match your existing JSON files for proper categorization.</li>\n";
echo "<li><strong>Review Descriptions:</strong> Some descriptions contain detailed specifications that could be extracted into separate fields.</li>\n";
echo "</ol>\n\n";

// Import readiness assessment
$critical_issues = count($issues);
$total_warnings = count($warnings);

echo "<h2>Import Readiness Assessment</h2>\n";

if ($critical_issues === 0) {
    echo "<p style='color: green; font-weight: bold;'>✅ READY TO IMPORT</p>\n";
    echo "<p>Your data has no critical issues and can be imported safely. ";
    if ($total_warnings > 0) {
        echo "However, you may want to address the $total_warnings warnings for optimal data quality.";
    }
    echo "</p>\n";
} else {
    echo "<p style='color: red; font-weight: bold;'>⚠️ NEEDS ATTENTION</p>\n";
    echo "<p>Your data has $critical_issues critical issues that should be fixed before import. These issues may affect the accuracy and usability of your car listings.</p>\n";
}

echo "\n<h2>Sample Data Preview</h2>\n";
echo "<p>Here are the first 3 cars from your data:</p>\n";

for ($i = 0; $i < min(3, count($cars)); $i++) {
    $car = $cars[$i];
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>\n";
    echo "<h3>" . htmlspecialchars($car['year'] . ' ' . $car['make'] . ' ' . $car['model']) . "</h3>\n";
    echo "<p><strong>Price:</strong> €" . number_format($car['price']) . " | ";
    echo "<strong>Mileage:</strong> " . number_format($car['mileage']) . " km | ";
    echo "<strong>Fuel:</strong> " . htmlspecialchars($car['fuel_type']) . " | ";
    echo "<strong>Drive:</strong> " . htmlspecialchars($car['drive_type']) . "</p>\n";
    
    if (!empty($car['extras']) && $car['extras'] !== '[]') {
        echo "<p><strong>Extras:</strong> " . htmlspecialchars(substr($car['extras'], 0, 100)) . "...</p>\n";
    }
    
    echo "<p><strong>Description:</strong> " . htmlspecialchars(substr($car['description'], 0, 150)) . "...</p>\n";
    echo "</div>\n";
}
?> 