<?php
/** Shared, allowlisted persistence for already-validated form and API data. */
defined('ABSPATH') || exit;

function autoagora_write_car_fields(int $listing_id, array $data): void
{
    $integers = ['year', 'mileage', 'price', 'number_of_doors', 'number_of_seats', 'hp', 'numowners', 'isantique'];
    $texts = ['make', 'model', 'availability', 'engine_capacity', 'fuel_type', 'transmission', 'drive_type',
        'body_type', 'exterior_color', 'interior_color', 'motuntil', 'car_city', 'car_district', 'car_address'];
    foreach ($integers as $field) {
        if (array_key_exists($field, $data)) { update_field($field, $data[$field] === '' ? '' : (int) $data[$field], $listing_id); }
    }
    foreach ($texts as $field) {
        if (array_key_exists($field, $data)) { update_field($field, sanitize_text_field($data[$field]), $listing_id); }
    }
    foreach (['car_latitude', 'car_longitude'] as $field) {
        if (array_key_exists($field, $data)) { update_field($field, (float) $data[$field], $listing_id); }
    }
    foreach (['extras', 'vehiclehistory'] as $field) {
        if (array_key_exists($field, $data)) { update_field($field, array_map('sanitize_text_field', (array) $data[$field]), $listing_id); }
    }
    if (array_key_exists('description', $data)) { update_field('description', wp_kses_post($data['description']), $listing_id); }
}
