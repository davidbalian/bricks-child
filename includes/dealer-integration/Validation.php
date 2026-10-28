<?php
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Validation
{
    public const FIELDS = ['make', 'model', 'year', 'mileage', 'price', 'availability', 'engine_capacity', 'fuel_type',
        'transmission', 'body_type', 'drive_type', 'hp', 'exterior_color', 'interior_color', 'description',
        'number_of_doors', 'number_of_seats', 'motuntil', 'numowners', 'isantique', 'extras', 'vehiclehistory'];
    public const REQUIRED = ['make', 'model', 'year', 'mileage', 'price', 'availability', 'engine_capacity', 'fuel_type', 'transmission', 'body_type', 'exterior_color'];

    public static function location($location)
    {
        if (!is_array($location) || array_is_list($location) || array_diff(array_keys($location), ['address', 'city', 'district', 'latitude', 'longitude'])) {
            return Support::error('invalid_location', 'Location must contain address, city, district, latitude and longitude.');
        }
        $location['district'] = $location['district'] ?? '';
        foreach (['address', 'city', 'district'] as $field) {
            if (!isset($location[$field]) || !is_string($location[$field]) || strlen($location[$field]) > 500 || ($field !== 'district' && trim($location[$field]) === '')) {
                return Support::error('invalid_location', 'Location requires an address and city, with an optional district.');
            }
            $location[$field] = sanitize_text_field($location[$field]);
        }
        foreach (['latitude' => 90, 'longitude' => 180] as $field => $max) {
            $value = $location[$field] ?? null;
            if ((!is_float($value) && !is_int($value)) || !is_finite((float) $value) || abs($value) > $max || (float) $value === 0.0) {
                return Support::error('invalid_location', 'Coordinates must be non-zero decimal numbers within latitude/longitude ranges.');
            }
            $location[$field] = round((float) $value, 6);
        }
        return $location;
    }

    public static function listing(array $input, array $current, int $dealer)
    {
        $allowed = array_merge(self::FIELDS, ['external_stock_id', 'location', 'photos']);
        if (array_diff(array_keys($input), $allowed)) { return Support::error('unknown_field', 'Request contains unsupported fields.', 422, ['fields' => array_values(array_diff(array_keys($input), $allowed))]); }
        $values = array_replace(array_intersect_key($current, array_flip(self::FIELDS)), array_intersect_key($input, array_flip(self::FIELDS)));
        foreach (self::REQUIRED as $field) {
            if (!array_key_exists($field, $values) || $values[$field] === '' || $values[$field] === null) {
                return Support::error('required_field', 'A required field is missing.', 422, ['field' => $field]);
            }
        }
        foreach ($values as $field => $value) {
            if ($value === null && !in_array($field, self::REQUIRED, true)) { $values[$field] = in_array($field, ['extras', 'vehiclehistory'], true) ? [] : ''; continue; }
            if (in_array($field, ['extras', 'vehiclehistory'], true)) {
                if (!is_array($value) || !array_is_list($value) || count($value) > 50 || count(array_filter($value, 'is_string')) !== count($value)) {
                    return Support::error('invalid_field', 'Expected an array of catalog strings.', 422, ['field' => $field]);
                }
            } elseif (in_array($field, ['year', 'mileage', 'price', 'hp', 'number_of_doors', 'number_of_seats', 'numowners'], true)) {
                if ($value === '' && !in_array($field, self::REQUIRED, true)) { continue; }
                if (!is_int($value) || $value < 0 || $value > 2147483647) { return Support::error('invalid_integer', 'Expected a nonnegative whole number.', 422, ['field' => $field]); }
            } elseif ($field === 'isantique') {
                if (!is_bool($value) && $value !== 0 && $value !== 1 && $value !== '') { return Support::error('invalid_boolean', 'isantique must be a boolean.'); }
                $values[$field] = (int) (bool) $value;
            } elseif (!is_string($value) || strlen($value) > ($field === 'description' ? 20000 : 191)) {
                return Support::error('invalid_field', 'Expected a string within the field length limit.', 422, ['field' => $field]);
            }
        }
        if (!preg_match('/^(?:[0-9]|1[01]|12)\.[0-9]$/D', $values['engine_capacity']) || (float) $values['engine_capacity'] > 12
            || ($values['fuel_type'] === 'Electric' ? $values['engine_capacity'] !== '0.0' : (float) $values['engine_capacity'] < 0.4)) {
            return Support::error('invalid_engine', 'Use litres with one decimal (0.4–12.0), or 0.0 for Electric.');
        }
        if ($values['price'] < 1 || (isset($values['numowners']) && $values['numowners'] !== '' && ($values['numowners'] < 1 || $values['numowners'] > 99))) {
            return Support::error('invalid_range', 'Price must be positive; owner count must be 1–99 if supplied.');
        }
        if (!empty($values['motuntil']) && $values['motuntil'] !== 'Expired') {
            $start = wp_date('Y-m');
            $end = wp_date('Y-m', strtotime('+2 years'));
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $values['motuntil']) || $values['motuntil'] < $start || $values['motuntil'] > $end) {
                return Support::error('invalid_mot', 'Use Expired or a month within the current 24-month MOT window.');
            }
        }
        $result = validate_car_listing_fields($values);
        if (!$result['valid']) { return Support::error('invalid_fields', 'Vehicle specification is invalid.', 422, ['errors' => $result['errors']]); }
        $make = get_terms(['taxonomy' => 'car_make', 'name' => $values['make'], 'parent' => 0, 'hide_empty' => false]);
        if (is_wp_error($make) || count($make) !== 1 || $make[0]->name !== $values['make']) { return Support::error('invalid_make', 'Choose a make from the current catalog.'); }
        $model = get_terms(['taxonomy' => 'car_make', 'name' => $values['model'], 'parent' => (int) $make[0]->term_id, 'hide_empty' => false]);
        if (is_wp_error($model) || count($model) !== 1 || $model[0]->name !== $values['model']) { return Support::error('invalid_model', 'Model must belong to the selected make.'); }
        $location = $input['location'] ?? ($current['_location'] ?? get_user_meta($dealer, '_ag_dealer_showroom', true));
        $location = self::location($location);
        if (is_wp_error($location)) { return $location; }
        if (isset($input['photos'])) {
            $photos = $input['photos'];
            if (!is_array($photos) || !array_is_list($photos) || count($photos) < 2 || count($photos) > 25) { return Support::error('invalid_photos', 'Supply an ordered array of 2–25 photos.'); }
            foreach ($photos as $photo) {
                if (!is_array($photo) || count($photo) !== 1 || !(isset($photo['url']) && is_string($photo['url']) && strlen($photo['url']) <= 2048 || isset($photo['id']) && is_int($photo['id']) && $photo['id'] > 0)) {
                    return Support::error('invalid_photo', 'Each photo must contain either an HTTPS url or an existing listing image id.');
                }
            }
            if (count(array_unique(array_map('wp_json_encode', $photos))) !== count($photos)) { return Support::error('duplicate_photo', 'The photo array contains duplicates.'); }
        } elseif (!$current) { return Support::error('required_photos', 'At least two photo URLs are required.'); }
        $values['description'] = wp_kses_post($values['description'] ?? '');
        unset($values['_location']);
        return ['fields' => $values, 'location' => $location, 'terms' => [(int) $make[0]->term_id, (int) $model[0]->term_id]];
    }
}
