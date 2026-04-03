<?php
/**
 * Template Name: Cars in Larnaca
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/includes/city-cars-landing/render-city-cars-landing.php';
autoagora_render_city_cars_landing('larnaca');
