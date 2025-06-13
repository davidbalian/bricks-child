<?php
/**
 * Geo Utility Functions
 * 
 * @package Astra Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!function_exists('autoagora_calculate_distance')) {
    /**
     * Calculate distance between two points using Haversine formula.
     *
     * @param float $lat1 Latitude of point 1.
     * @param float $lon1 Longitude of point 1.
     * @param float $lat2 Latitude of point 2.
     * @param float $lon2 Longitude of point 2.
     * @param string $unit Unit of distance (K for kilometers, N for nautical miles, M for miles).
     * @return float Distance in the specified unit.
     */
    function autoagora_calculate_distance($lat1, $lon1, $lat2, $lon2, $unit = 'K') {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
          return 0;
        } else {
          $theta = $lon1 - $lon2;
          $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
          $dist = acos($dist);
          $dist = rad2deg($dist);
          $miles = $dist * 60 * 1.1515;
          $unit = strtoupper($unit);
    
          if ($unit == "K") {
            return ($miles * 1.609344);
          } else if ($unit == "N") {
            return ($miles * 0.8684);
          } else {
            return $miles;
          }
        }
    }
}

if (!function_exists('autoagora_get_bounding_box')) {
    /**
     * Calculate the bounding box for a given center point and radius.
     *
     * @param float $latitude Latitude of the center point.
     * @param float $longitude Longitude of the center point.
     * @param float $radius_km Radius in kilometers.
     * @return array Associative array with min_lat, max_lat, min_lng, max_lng.
     */
    function autoagora_get_bounding_box($latitude, $longitude, $radius_km) {
        if ($radius_km <= 0) {
            return false; // Or handle as an error
        }

        // Earth's radius in kilometers (mean radius)
        $earth_radius_km = 6371.0;

        // Angular radius in radians
        $angular_radius = $radius_km / $earth_radius_km;

        // Convert center latitude and longitude to radians
        $lat_rad = deg2rad($latitude);
        $lng_rad = deg2rad($longitude);

        // Calculate min/max latitudes
        $min_lat = $lat_rad - $angular_radius;
        $max_lat = $lat_rad + $angular_radius;

        // Calculate min/max longitudes (more complex due to convergence at poles)
        $delta_lng = asin(sin($angular_radius) / cos($lat_rad));
        $min_lng = $lng_rad - $delta_lng;
        $max_lng = $lng_rad + $delta_lng;

        // Convert back to degrees
        return array(
            'min_lat' => rad2deg($min_lat),
            'max_lat' => rad2deg($max_lat),
            'min_lng' => rad2deg($min_lng),
            'max_lng' => rad2deg($max_lng),
        );
    }
} 