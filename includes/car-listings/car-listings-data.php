<?php
/**
 * Car Listings Data Fetching Functions
 * 
 * @package Astra Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Get all unique makes from the database with counts.
 * @return array ['makes' => array, 'counts' => array]
 */
function get_car_makes_with_counts() {
    global $wpdb;
    $makes_query = $wpdb->get_results(
        "SELECT meta_value, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'make' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY meta_value
        ORDER BY meta_value ASC"
    );
    
    // --- DEBUGGING START ---
    // echo '<pre>DEBUG: get_car_makes_with_counts results: ';
    // print_r($makes_query);
    // echo '</pre>';
    // --- DEBUGGING END ---

    $makes = [];
    $make_counts = [];
    foreach ($makes_query as $row) {
        $makes[] = $row->meta_value;
        $make_counts[$row->meta_value] = $row->count;
    }
    return ['makes' => $makes, 'counts' => $make_counts];
}

/**
 * Get all unique models for each make with counts.
 * @param array $makes List of makes.
 * @return array ['models_by_make' => array, 'model_counts' => array]
 */
function get_car_models_by_make_with_counts(array $makes) {
    global $wpdb;
    $models_by_make = array();
    $model_counts = array();
    foreach ($makes as $make) {
        $models_query = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_value, COUNT(*) as count 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'model' 
            AND post_id IN (
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'make' 
                AND meta_value = %s
                AND post_id IN (
                    SELECT ID 
                    FROM {$wpdb->posts} 
                    WHERE post_type = 'car' 
                    AND post_status = 'publish'
                )
            )
            GROUP BY meta_value
            ORDER BY meta_value ASC",
            $make
        ));
        
        // --- DEBUGGING START ---
        // echo "<pre>DEBUG: get_car_models_by_make_with_counts (Make: " . esc_html($make) . ") results: ";
        // print_r($models_query);
        // echo "</pre>";
        // --- DEBUGGING END ---

        $models = [];
        $model_counts[$make] = [];
        foreach ($models_query as $row) {
            $models[] = $row->meta_value;
            $model_counts[$make][$row->meta_value] = $row->count;
        }
        
        $models_by_make[$make] = $models;
    }
    return ['models_by_make' => $models_by_make, 'model_counts' => $model_counts];
}

/**
 * Get all unique variants for each make and model with counts.
 * @param array $models_by_make Models grouped by make.
 * @return array ['variants_by_make_model' => array, 'variant_counts' => array]
 */
function get_car_variants_by_make_model_with_counts(array $models_by_make) {
    global $wpdb;
    $variants_by_make_model = array();
    $variant_counts = array();
    foreach ($models_by_make as $make => $models) {
        $variants_by_make_model[$make] = array();
        $variant_counts[$make] = array();
        
        foreach ($models as $model) {
            $variants_query = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_value, COUNT(*) as count 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'variant' 
                AND post_id IN (
                    SELECT post_id 
                    FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'make' 
                    AND meta_value = %s
                    AND post_id IN (
                        SELECT post_id 
                        FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'model' 
                        AND meta_value = %s
                        AND post_id IN (
                            SELECT ID 
                            FROM {$wpdb->posts} 
                            WHERE post_type = 'car' 
                            AND post_status = 'publish'
                        )
                    )
                )
                GROUP BY meta_value
                ORDER BY meta_value ASC",
                $make,
                $model
            ));
            
            // --- DEBUGGING START ---
            // echo "<pre>DEBUG: get_car_variants (Make: " . esc_html($make) . ", Model: " . esc_html($model) . ") results: ";
            // print_r($variants_query);
            // echo "</pre>";
            // --- DEBUGGING END ---
            
            $variants = [];
            $variant_counts[$make][$model] = [];
            foreach ($variants_query as $row) {
                $variants[] = $row->meta_value;
                $variant_counts[$make][$model][$row->meta_value] = $row->count;
            }
            
            $variants_by_make_model[$make][$model] = $variants;
        }
    }
    return ['variants_by_make_model' => $variants_by_make_model, 'variant_counts' => $variant_counts];
}

/**
 * Get all unique locations from the database.
 * @return array List of locations.
 */
function get_car_locations() {
    global $wpdb;
    $locations_query = $wpdb->get_results(
        "SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'location' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        ORDER BY meta_value ASC"
    );
    
    // --- DEBUGGING START ---
    // echo "<pre>DEBUG: get_car_locations results: ";
    // print_r($locations_query);
    // echo "</pre>";
    // --- DEBUGGING END ---

    $locations = [];
    foreach ($locations_query as $row) {
        $locations[] = $row->meta_value;
    }
    return $locations;
}

/**
 * Get all unique price ranges from the database with counts.
 * @return array ['prices' => array, 'counts' => array]
 */
function get_car_price_ranges_with_counts() {
    global $wpdb;
    $prices_query = $wpdb->get_results(
        "SELECT FLOOR(CAST(meta_value AS DECIMAL) / 500) * 500 as price_range, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'price' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY price_range
        ORDER BY price_range ASC"
    );
    
    // --- DEBUGGING START ---
    // echo "<pre>DEBUG: get_car_price_ranges results: ";
    // print_r($prices_query);
    // echo "</pre>";
    // --- DEBUGGING END ---

    $prices = [];
    $price_counts = [];
    foreach ($prices_query as $row) {
        $prices[] = $row->price_range;
        $price_counts[$row->price_range] = $row->count;
    }
    return ['prices' => $prices, 'counts' => $price_counts];
}

/**
 * Get all unique years from the database with counts.
 * @return array ['years' => array, 'counts' => array]
 */
function get_car_years_with_counts() {
    global $wpdb;
    $years_query = $wpdb->get_results(
        "SELECT meta_value, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'year' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY meta_value
        ORDER BY meta_value ASC"
    );
    
    $years = [];
    $year_counts = [];
    foreach ($years_query as $row) {
        $years[] = $row->meta_value;
        $year_counts[$row->meta_value] = $row->count;
    }
    return ['years' => $years, 'counts' => $year_counts];
}

/**
 * Get all unique kilometer ranges from the database with counts.
 * @return array ['kilometers' => array, 'counts' => array]
 */
function get_car_kilometer_ranges_with_counts() {
    global $wpdb;
    $kilometers_query = $wpdb->get_results(
        "SELECT FLOOR(CAST(meta_value AS DECIMAL) / 10000) * 10000 as km_range, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'mileage' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY km_range
        ORDER BY km_range ASC"
    );
    
    $kilometers = [];
    $km_counts = [];
    foreach ($kilometers_query as $row) {
        $kilometers[] = $row->km_range;
        $km_counts[$row->km_range] = $row->count;
    }
    return ['kilometers' => $kilometers, 'counts' => $km_counts];
}

/**
 * Get all unique engine sizes from the database with counts.
 * @return array ['engine_sizes' => array, 'counts' => array]
 */
function get_car_engine_sizes_with_counts() {
    global $wpdb;
    $engine_sizes_query = $wpdb->get_results(
        "SELECT ROUND(CAST(meta_value AS DECIMAL), 1) as engine_size, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'engine_capacity' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY engine_size
        ORDER BY engine_size ASC"
    );
    
    $engine_sizes = [];
    $engine_counts = [];
    foreach ($engine_sizes_query as $row) {
        $engine_sizes[] = $row->engine_size;
        $engine_counts[$row->engine_size] = $row->count;
    }
    return ['engine_sizes' => $engine_sizes, 'counts' => $engine_counts];
}

/**
 * Get all unique body types from the database with counts.
 * @return array ['body_types' => array, 'counts' => array]
 */
function get_car_body_types_with_counts() {
    global $wpdb;
    $body_types_query = $wpdb->get_results(
        "SELECT meta_value, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'body_type' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY meta_value
        ORDER BY meta_value ASC"
    );
    
    $body_types = [];
    $body_type_counts = [];
    foreach ($body_types_query as $row) {
        $body_types[] = $row->meta_value;
        $body_type_counts[$row->meta_value] = $row->count;
    }
    return ['body_types' => $body_types, 'counts' => $body_type_counts];
}

/**
 * Get all unique fuel types from the database with counts.
 * @return array ['fuel_types' => array, 'counts' => array]
 */
function get_car_fuel_types_with_counts() {
    global $wpdb;
    $fuel_types_query = $wpdb->get_results(
        "SELECT meta_value, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'fuel_type' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY meta_value
        ORDER BY meta_value ASC"
    );
    
    $fuel_types = [];
    $fuel_type_counts = [];
    foreach ($fuel_types_query as $row) {
        $fuel_types[] = $row->meta_value;
        $fuel_type_counts[$row->meta_value] = $row->count;
    }
    return ['fuel_types' => $fuel_types, 'counts' => $fuel_type_counts];
}

/**
 * Get all unique drive types from the database with counts.
 * @return array ['drive_types' => array, 'counts' => array]
 */
function get_car_drive_types_with_counts() {
    global $wpdb;
    $drive_types_query = $wpdb->get_results(
        "SELECT meta_value, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'drive_type' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY meta_value
        ORDER BY meta_value ASC"
    );
    
    $drive_types = [];
    $drive_type_counts = [];
    foreach ($drive_types_query as $row) {
        $drive_types[] = $row->meta_value;
        $drive_type_counts[$row->meta_value] = $row->count;
    }
    return ['drive_types' => $drive_types, 'counts' => $drive_type_counts];
}

/**
 * Get all unique colors from the database with counts for exterior and interior.
 * @return array ['colors' => array, 'exterior_counts' => array, 'interior_counts' => array]
 */
function get_car_colors_with_counts() {
    global $wpdb;
    $colors_query = $wpdb->get_results(
        "SELECT meta_key, meta_value, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key IN ('exterior_color', 'interior_color') 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY meta_key, meta_value
        ORDER BY meta_value ASC"
    );
    
    // --- DEBUGGING START ---
    // echo "<pre>DEBUG: get_car_colors_with_counts results: ";
    // print_r($colors_query);
    // echo "</pre>";
    // --- DEBUGGING END ---

    $colors = [];
    $exterior_color_counts = [];
    $interior_color_counts = [];
    foreach ($colors_query as $row) {
        if (!in_array($row->meta_value, $colors)) {
            $colors[] = $row->meta_value;
        }
        if ($row->meta_key === 'exterior_color') {
            $exterior_color_counts[$row->meta_value] = $row->count;
        } else {
            $interior_color_counts[$row->meta_value] = $row->count;
        }
    }
    sort($colors); // Sort colors alphabetically
    return ['colors' => $colors, 'exterior_counts' => $exterior_color_counts, 'interior_counts' => $interior_color_counts];
}

/**
 * Get min and max values for numeric car fields.
 * @return stdClass Object containing min/max values.
 */
function get_car_filter_min_max_values() {
    global $wpdb;
    $min_max_query = $wpdb->get_results(
        "SELECT 
            MIN(CASE WHEN meta_key = 'price' THEN CAST(meta_value AS DECIMAL(15,2)) END) as min_price,
            MAX(CASE WHEN meta_key = 'price' THEN CAST(meta_value AS DECIMAL(15,2)) END) as max_price,
            MIN(CASE WHEN meta_key = 'year' THEN CAST(meta_value AS UNSIGNED) END) as min_year,
            MAX(CASE WHEN meta_key = 'year' THEN CAST(meta_value AS UNSIGNED) END) as max_year,
            MIN(CASE WHEN meta_key = 'mileage' THEN CAST(meta_value AS UNSIGNED) END) as min_mileage,
            MAX(CASE WHEN meta_key = 'mileage' THEN CAST(meta_value AS UNSIGNED) END) as max_mileage,
            MIN(CASE WHEN meta_key = 'engine_capacity' THEN CAST(meta_value AS DECIMAL(4,1)) END) as min_engine,
            MAX(CASE WHEN meta_key = 'engine_capacity' THEN CAST(meta_value AS DECIMAL(4,1)) END) as max_engine
        FROM {$wpdb->postmeta} 
        WHERE meta_key IN ('price', 'year', 'mileage', 'engine_capacity')
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )"
    );

    return $min_max_query[0] ?? (object)[]; // Return empty object if no results
}