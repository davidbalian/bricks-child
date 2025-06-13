<?php
/**
 * Car Listings Query Argument Builder
 * 
 * @package Astra Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once __DIR__ . '/geo-utils.php'; // Include geo utility functions



/**
 * Builds the WP_Query arguments array for car listings based on shortcode attributes and GET parameters.
 * 
 * @param array $atts Shortcode attributes.
 * @param int   $paged Current page number.
 * @param array $filters (Optional) An array of filters passed directly (e.g., from AJAX). Defaults to null.
 * @return array The WP_Query arguments array.
 */
function build_car_listings_query_args($atts, $paged, $filters = null) {
    global $wpdb; // Needed for direct DB query for location
    // Determine the source of filters: direct parameter or $_GET if $filters is null
    // If $filters is provided (e.g. from AJAX), it should contain all necessary filter keys including lat, lng, radius
    $filter_source = is_array($filters) ? $filters : $_GET;

    // Base query arguments
    $args = array(
        'post_type' => 'car',
        'posts_per_page' => isset($atts['per_page']) ? intval($atts['per_page']) : 12,
        'paged' => intval($paged),
        'orderby' => isset($atts['orderby']) ? $atts['orderby'] : 'date',
        'order' => isset($atts['order']) ? $atts['order'] : 'DESC',
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND', 
            array(
                'relation' => 'OR',
                array(
                    'key' => 'is_sold',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'is_sold',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        )
    );

    // --- Location Filter (lat, lng, radius) ---
    $filter_lat = isset($filter_source['lat']) && $filter_source['lat'] !== 'null' && $filter_source['lat'] !== '' ? floatval($filter_source['lat']) : null;
    $filter_lng = isset($filter_source['lng']) && $filter_source['lng'] !== 'null' && $filter_source['lng'] !== '' ? floatval($filter_source['lng']) : null;
    $filter_radius = isset($filter_source['radius']) && $filter_source['radius'] !== 'null' && $filter_source['radius'] !== '' ? floatval($filter_source['radius']) : null;

    error_log('[DEBUG Query Args] Received Location Filters: lat=' . print_r($filter_lat, true) . ', lng=' . print_r($filter_lng, true) . ', radius=' . print_r($filter_radius, true));

    if ($filter_lat !== null && $filter_lng !== null && $filter_radius !== null && function_exists('autoagora_get_bounding_box')) {
        $bounding_box = autoagora_get_bounding_box($filter_lat, $filter_lng, $filter_radius);
        error_log('[DEBUG Query Args] Calculated Bounding Box: ' . print_r($bounding_box, true));

        if ($bounding_box) {
            // Add meta query for latitude range
            $lat_meta_query = array(
                'key' => 'car_latitude',
                'value' => array($bounding_box['min_lat'], $bounding_box['max_lat']),
                'type' => 'DECIMAL(10,6)', // Assuming coordinates are stored with precision
                'compare' => 'BETWEEN'
            );
            $args['meta_query'][] = $lat_meta_query;
            error_log('[DEBUG Query Args] Added Latitude Meta Query: ' . print_r($lat_meta_query, true));

            // Add meta query for longitude range
            $lng_meta_query = array(
                'key' => 'car_longitude',
                'value' => array($bounding_box['min_lng'], $bounding_box['max_lng']),
                'type' => 'DECIMAL(10,6)', // Assuming coordinates are stored with precision
                'compare' => 'BETWEEN'
            );
            $args['meta_query'][] = $lng_meta_query;
            error_log('[DEBUG Query Args] Added Longitude Meta Query: ' . print_r($lng_meta_query, true));

            // Note: This bounding box filter is now part of the main query.
            // The old logic of querying all IDs first and then manually filtering in PHP is removed.
            // If precise circular filtering is still needed, it must be done *after* this query executes,
            // by looping through its results and applying autoagora_calculate_distance.
        } else {
            // Invalid bounding box (e.g., radius was 0 or negative), effectively means no location results
            $args['post__in'] = [0]; 
            error_log('[DEBUG Query Args] Invalid bounding box or radius. Setting post__in to [0].');
        }
    } 
    // --- End Location Filter ---
    error_log('[DEBUG Query Args] Final Query Args before return: ' . print_r($args, true));

    // Add other spec filter arguments from the determined source
    $filter_params = array(
        'make' => 'make',
        'model' => 'model',
        'variant' => 'variant',
        'transmission' => 'transmission',
        'number_of_doors' => 'number_of_doors',
        'number_of_seats' => 'number_of_seats',
        'availability' => 'availability',
        'isantique' => 'isantique' // For true/false, ACF stores 1 or 0. Comparison should be '='.
    );

    foreach ($filter_params as $filter_key => $meta_key) {
        if (isset($filter_source[$filter_key]) && $filter_source[$filter_key] !== '') { // Check for not empty string
            $value = sanitize_text_field($filter_source[$filter_key]);
            $compare_operator = '=';

            if ($meta_key === 'isantique') {
                 // Ensure value is 1 or 0 for boolean ACF fields
                $value = ($value == '1' || strtolower($value) === 'true') ? '1' : '0';
            }

            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => $value,
                'compare' => $compare_operator
            );
        }
    }

    // --- Range Filters (min/max) ---
    // JS is expected to send parameters like year_min, year_max, price_min, price_max etc.
    $range_filters = array(
        'year' => 'year', // meta_key 'year'
        'price' => 'price', // meta_key 'price'
        'mileage' => 'mileage', // meta_key 'mileage'
        'engine_capacity' => 'engine_capacity', // meta_key 'engine_capacity'
        'hp' => 'hp', // meta_key 'hp'
        'numowners' => 'numowners', // meta_key 'numowners'
    );

    foreach ($range_filters as $filter_key => $meta_key) {
        $min_val = isset($filter_source[$filter_key . '_min']) ? $filter_source[$filter_key . '_min'] : null;
        $max_val = isset($filter_source[$filter_key . '_max']) ? $filter_source[$filter_key . '_max'] : null;

        if ($min_val !== null && $min_val !== '') {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => sanitize_text_field($min_val),
                'type'    => 'NUMERIC',
                'compare' => '>='
            );
        }
        if ($max_val !== null && $max_val !== '') {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => sanitize_text_field($max_val),
                'type'    => 'NUMERIC',
                'compare' => '<='
            );
        }
    }

    // --- Checkbox / Multi-Select Filters ---
    // JS is expected to send parameters like fuel_type[]=Petrol&fuel_type[]=Diesel
    $checkbox_filters = array(
        'fuel_type' => 'fuel_type',
        'exterior_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'body_type' => 'body_type',
        'drive_type' => 'drive_type',
        'extras' => 'extras',
        'vehiclehistory' => 'vehiclehistory'
    );

    foreach ($checkbox_filters as $filter_key => $meta_key) {
        $actual_key = $filter_key; // Simpler if JS sends plain keys e.g. fuel_type: ['Petrol', 'Diesel']
        // Check if JS sent it as fuel_type[] which PHP converts to an array under the key 'fuel_type'
        if (isset($filter_source[$filter_key]) && is_array($filter_source[$filter_key])) {
            $values_raw = $filter_source[$filter_key];
        } 
        // Legacy check if JS sent it as filter_key[] which gets converted to filter_key by some JS methods or manual construction
        // else if (isset($filter_source[$filter_key . '[]']) && is_array($filter_source[$filter_key . '[]'])) { 
        //    $values_raw = $filter_source[$filter_key . '[]'];
        // }
        else {
            $values_raw = null;
        }
        
        if ($values_raw && !empty($values_raw)) {
            $values = array_filter(array_map('sanitize_text_field', $values_raw)); 
            if (!empty($values)) {
                // For ACF checkboxes/multi-select, values are often stored serialized (e.g., a:1:{i:0;s:6:"Petrol";})
                // or as a simple array. If it is a serialized array of choices, a LIKE query for each is needed.
                $group_relation = ($meta_key === 'extras' || $meta_key === 'vehiclehistory') ? 'AND' : 'OR'; // For extras, all selected must match. For others, any match.
                $individual_meta_queries = array('relation' => $group_relation);

                foreach ($values as $single_value) {
                    $individual_meta_queries[] = array(
                        'key' => $meta_key, 
                        'value' => '"' . $single_value . '"', // searching for "value" within serialized string
                        'compare' => 'LIKE'
                    );
                }
                if (count($individual_meta_queries) > 1) { // only add if there are actual conditions
                    $args['meta_query'][] = $individual_meta_queries;
                }
            }
        }
    }
    
    // If after all filters, post__in is set to [0] (no location match) and other meta queries exist,
    // the query will correctly return no results. If post__in is not set, other meta queries apply normally.

    return $args;
}