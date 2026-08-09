<?php
/**
 * Configuration for city-specific cars browse pages.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Static definitions for supported city landing slugs.
 */
final class CityCarsLandingCatalog {

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all() {
        return array(
            'nicosia' => array(
                'slug'           => 'nicosia',
                'car_city_value' => 'Nicosia',
                'display_name'   => 'Nicosia',
                'lat'            => 35.1856,
                'lng'            => 33.3823,
                'loc_label'      => 'Nicosia',
                'h1'             => __('Used cars for sale in Nicosia', 'bricks-child'),
                'browse_lead'    => __('Searching beyond Nicosia? ', 'bricks-child'),
                'browse_link'    => __('Browse all used cars in Cyprus.', 'bricks-child'),
                'intro_heading'  => __('Buying a used car in Nicosia', 'bricks-child'),
                'intro'          => array(
                    __('Nicosia is the largest market in Cyprus for used cars, with listings from dealers and private sellers across the capital and nearby areas.', 'bricks-child'),
                    __('Use the filters to narrow by make, price, mileage, and more. When you refine your search, you will continue on the main Cyprus browse page with your choices applied.', 'bricks-child'),
                ),
            ),
            'limassol' => array(
                'slug'           => 'limassol',
                'car_city_value' => 'Limassol',
                'display_name'   => 'Limassol',
                'lat'            => 34.7071,
                'lng'            => 33.0226,
                'loc_label'      => 'Limassol',
                'h1'             => __('Used cars for sale in Limassol', 'bricks-child'),
                'browse_lead'    => __('Searching beyond Limassol? ', 'bricks-child'),
                'browse_link'    => __('Browse all used cars in Cyprus.', 'bricks-child'),
                'intro_heading'  => __('Buying a used car in Limassol', 'bricks-child'),
                'intro'          => array(
                    __('Limassol’s coastal location and busy port make it a popular place to buy and sell used vehicles.', 'bricks-child'),
                    __('Filter by brand, budget, and specifications. Further filtering continues on the main Cyprus listings page with your settings preserved.', 'bricks-child'),
                ),
            ),
            'larnaca' => array(
                'slug'           => 'larnaca',
                'car_city_value' => 'Larnaca',
                'display_name'   => 'Larnaca',
                'lat'            => 34.9174,
                'lng'            => 33.636,
                'loc_label'      => 'Larnaca',
                'h1'             => __('Used cars for sale in Larnaca', 'bricks-child'),
                'browse_lead'    => __('Searching beyond Larnaca? ', 'bricks-child'),
                'browse_link'    => __('Browse all used cars in Cyprus.', 'bricks-child'),
                'intro_heading'  => __('Buying a used car in Larnaca', 'bricks-child'),
                'intro'          => array(
                    __('Larnaca offers a steady flow of used cars from local owners and dealerships, including options near the airport and along the coast.', 'bricks-child'),
                    __('Adjust filters to match your needs; when you apply changes, you will move to the island-wide browse experience with the same filters.', 'bricks-child'),
                ),
            ),
            'paphos' => array(
                'slug'           => 'paphos',
                'car_city_value' => 'Paphos',
                'display_name'   => 'Paphos',
                'lat'            => 34.7754,
                'lng'            => 32.4247,
                'loc_label'      => 'Paphos',
                'h1'             => __('Used cars for sale in Paphos', 'bricks-child'),
                'browse_lead'    => __('Searching beyond Paphos? ', 'bricks-child'),
                'browse_link'    => __('Browse all used cars in Cyprus.', 'bricks-child'),
                'intro_heading'  => __('Buying a used car in Paphos', 'bricks-child'),
                'intro'          => array(
                    __('Paphos combines tourism and residential demand, with a diverse mix of hatchbacks, SUVs, and family cars on the second-hand market.', 'bricks-child'),
                    __('Use the filters below; any deeper refinement opens the main Cyprus directory with your selections carried over.', 'bricks-child'),
                ),
            ),
        );
    }

    /**
     * @param string $slug Key: nicosia, limassol, larnaca, paphos.
     * @return array<string, mixed>|null
     */
    public static function get($slug) {
        $slug = sanitize_key($slug);
        $all  = self::all();

        return isset($all[$slug]) ? $all[$slug] : null;
    }

    /**
     * @return string[]
     */
    public static function template_filenames() {
        return array(
            'template-cars-city-nicosia.php',
            'template-cars-city-limassol.php',
            'template-cars-city-larnaca.php',
            'template-cars-city-paphos.php',
        );
    }

    /**
     * Map page template file to catalog slug.
     *
     * @return array<string, string>
     */
    public static function template_slug_map() {
        return array(
            'template-cars-city-nicosia.php'  => 'nicosia',
            'template-cars-city-limassol.php' => 'limassol',
            'template-cars-city-larnaca.php'  => 'larnaca',
            'template-cars-city-paphos.php'   => 'paphos',
        );
    }
}
