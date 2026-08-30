<?php
/**
 * Validates and normalizes AutoAgora car JSON import packages.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Car_Json_Import_Validator
{
    public const MAX_LISTINGS = 500;
    public const MAX_IMAGES_PER_LISTING = 40;
    public const MAX_MANIFEST_BYTES = 10 * 1024 * 1024;
    public const MAX_IMAGE_BYTES = 25 * 1024 * 1024;

    /**
     * @param string $zip_path
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>|WP_Error
     */
    public static function validatePackage(string $zip_path, array $defaults)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('car_json_import_zip_missing', __('The PHP ZIP extension is required.', 'bricks-child'));
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('car_json_import_bad_zip', __('The uploaded ZIP could not be opened.', 'bricks-child'));
        }

        try {
            $manifest_index = $zip->locateName('listings.json', ZipArchive::FL_NODIR);
            if ($manifest_index === false) {
                return new WP_Error('car_json_import_manifest_missing', __('The ZIP must contain listings.json at its root.', 'bricks-child'));
            }

            $stat = $zip->statIndex($manifest_index);
            $manifest_size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
            if ($manifest_size <= 0 || $manifest_size > self::MAX_MANIFEST_BYTES) {
                return new WP_Error('car_json_import_manifest_size', __('listings.json is empty or exceeds the safe size limit.', 'bricks-child'));
            }

            $json = $zip->getFromIndex($manifest_index);
            if (!is_string($json)) {
                return new WP_Error('car_json_import_manifest_read', __('listings.json could not be read.', 'bricks-child'));
            }

            $manifest = json_decode($json, true);
            if (!is_array($manifest) || json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('car_json_import_manifest_json', __('listings.json is not valid JSON.', 'bricks-child'));
            }
            if (!isset($manifest['listings']) || !is_array($manifest['listings'])) {
                return new WP_Error('car_json_import_listings_missing', __('listings.json must contain a listings array.', 'bricks-child'));
            }
            if (count($manifest['listings']) > self::MAX_LISTINGS) {
                return new WP_Error(
                    'car_json_import_too_many',
                    sprintf(__('The package exceeds the %d-listing safety limit.', 'bricks-child'), self::MAX_LISTINGS)
                );
            }

            $source = isset($manifest['source']) && is_array($manifest['source']) ? $manifest['source'] : array();
            $rows = array();
            $seen_source_ids = array();
            foreach (array_values($manifest['listings']) as $index => $raw_listing) {
                $rows[] = self::validateListing($raw_listing, $index, $defaults, $zip, $seen_source_ids, $source);
            }

            return array(
                'schema_version' => isset($manifest['schema_version']) ? (int) $manifest['schema_version'] : 1,
                'source'         => $source,
                'rows'           => $rows,
                'valid_count'    => count(array_filter($rows, static function ($row) {
                    return !empty($row['valid']);
                })),
                'invalid_count'  => count(array_filter($rows, static function ($row) {
                    return empty($row['valid']);
                })),
            );
        } finally {
            $zip->close();
        }
    }

    /**
     * @param mixed $raw_listing
     * @param int $index
     * @param array<string,mixed> $defaults
     * @param ZipArchive $zip
     * @param array<string,int> $seen_source_ids
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function validateListing($raw_listing, int $index, array $defaults, ZipArchive $zip, array &$seen_source_ids, array $source): array
    {
        $errors = array();
        $warnings = array();
        if (!is_array($raw_listing)) {
            return array(
                'index'    => $index,
                'valid'    => false,
                'listing'  => array(),
                'errors'   => array(__('Listing row is not an object.', 'bricks-child')),
                'warnings' => array(),
            );
        }

        $listing = self::sanitizeListing($raw_listing, $defaults);
        $source_key = strtolower((string) $listing['source_platform']) . '|' . strtolower((string) $listing['source_id']);
        if ($listing['source_id'] === '') {
            $errors[] = __('Missing source_id.', 'bricks-child');
        } elseif (isset($seen_source_ids[$source_key])) {
            $errors[] = sprintf(
                __('Duplicate source ID; first seen on JSON row %d.', 'bricks-child'),
                $seen_source_ids[$source_key] + 1
            );
        } else {
            $seen_source_ids[$source_key] = $index;
        }

        $required = array(
            'make', 'model', 'year', 'mileage', 'price', 'engine_capacity',
            'fuel_type', 'transmission', 'body_type', 'exterior_color', 'availability',
            'car_city', 'car_address', 'car_latitude', 'car_longitude',
        );
        foreach ($required as $field) {
            if (self::isMissing($listing[$field] ?? null, $field === 'mileage' || $field === 'engine_capacity')) {
                $errors[] = sprintf(__('Missing required field: %s.', 'bricks-child'), $field);
            }
        }

        if (!empty($listing['car_latitude']) && ((float) $listing['car_latitude'] < -90 || (float) $listing['car_latitude'] > 90)) {
            $errors[] = __('car_latitude is outside the valid range.', 'bricks-child');
        }
        if (!empty($listing['car_longitude']) && ((float) $listing['car_longitude'] < -180 || (float) $listing['car_longitude'] > 180)) {
            $errors[] = __('car_longitude is outside the valid range.', 'bricks-child');
        }

        if (function_exists('validate_car_listing_fields')) {
            $field_validation = validate_car_listing_fields($listing);
            if (empty($field_validation['valid'])) {
                $errors = array_merge($errors, (array) ($field_validation['errors'] ?? array()));
            }
        } else {
            $errors = array_merge($errors, self::validateEnums($listing));
        }

        if (!self::taxonomyCatalogContains((string) $listing['make'], (string) $listing['model'])) {
            $errors[] = sprintf(
                __('Make/model is not in simple_jsons taxonomy data: %1$s / %2$s.', 'bricks-child'),
                $listing['make'],
                $listing['model']
            );
        }

        $image_paths = isset($listing['car_images']) && is_array($listing['car_images'])
            ? array_values(array_unique($listing['car_images']))
            : array();
        if (empty($image_paths)) {
            $errors[] = __('At least one car image is required.', 'bricks-child');
        } elseif (count($image_paths) > self::MAX_IMAGES_PER_LISTING) {
            $errors[] = sprintf(
                __('Listing exceeds the %d-image safety limit.', 'bricks-child'),
                self::MAX_IMAGES_PER_LISTING
            );
        }

        $validated_images = array();
        foreach ($image_paths as $image_path) {
            $path_validation = self::validateArchiveImage($zip, (string) $image_path);
            if (is_wp_error($path_validation)) {
                $errors[] = $path_validation->get_error_message();
            } else {
                $validated_images[] = $path_validation;
            }
        }
        $listing['car_images'] = $validated_images;

        if (self::findExistingImport((string) $listing['source_platform'], (string) $listing['source_id'])) {
            $warnings[] = __('This source ID already exists and will be skipped during import.', 'bricks-child');
        }

        return array(
            'index'    => $index,
            'valid'    => empty($errors),
            'listing'  => $listing,
            'errors'   => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique(array_merge(
                $warnings,
                self::sanitizeWarnings((array) ($raw_listing['warnings'] ?? array()), $listing, $source)
            ))),
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    private static function sanitizeListing(array $raw, array $defaults): array
    {
        $text_fields = array(
            'source_platform', 'source_id', 'source_url', 'title', 'make', 'model',
            'fuel_type', 'transmission', 'body_type', 'drive_type', 'exterior_color',
            'interior_color', 'motuntil', 'availability', 'car_city', 'car_district',
            'car_address',
        );
        $listing = array();
        foreach ($text_fields as $field) {
            $listing[$field] = isset($raw[$field]) ? sanitize_text_field((string) $raw[$field]) : '';
        }

        $listing['source_platform'] = $listing['source_platform'] !== '' ? sanitize_key($listing['source_platform']) : 'bazaraki';
        $listing['source_id'] = preg_replace('/[^A-Za-z0-9._-]/', '', $listing['source_id']);
        $listing['source_url'] = esc_url_raw($listing['source_url']);
        $listing['make'] = self::normalizeMake($listing['make']);
        $listing['model'] = self::normalizeModel($listing['make'], $listing['model']);
        $listing['fuel_type'] = self::normalizeFuel($listing['fuel_type']);
        $listing['transmission'] = self::normalizeTransmission($listing['transmission']);
        $listing['body_type'] = self::normalizeBody($listing['body_type']);
        $listing['exterior_color'] = self::normalizeColor($listing['exterior_color']);
        $listing['availability'] = self::normalizeAvailability($listing['availability']);

        $integer_fields = array('year', 'mileage', 'price', 'hp', 'number_of_seats', 'numowners');
        foreach ($integer_fields as $field) {
            $listing[$field] = isset($raw[$field]) && $raw[$field] !== '' && $raw[$field] !== null
                ? (int) $raw[$field]
                : null;
        }
        $source_fields = isset($raw['source_fields']) && is_array($raw['source_fields']) ? $raw['source_fields'] : array();
        $raw_doors = $raw['number_of_doors'] ?? ($source_fields['doors'] ?? null);
        $listing['number_of_doors'] = self::normalizeDoorCount($raw_doors, $listing['body_type']);
        $listing['engine_capacity'] = isset($raw['engine_capacity']) && $raw['engine_capacity'] !== '' && $raw['engine_capacity'] !== null
            ? round((float) $raw['engine_capacity'], 1)
            : null;

        $listing['description'] = isset($raw['description']) ? wp_kses_post((string) $raw['description']) : '';
        $listing['car_latitude'] = self::coordinate($raw['car_latitude'] ?? null);
        $listing['car_longitude'] = self::coordinate($raw['car_longitude'] ?? null);
        foreach (array('car_city', 'car_district', 'car_address', 'car_latitude', 'car_longitude') as $field) {
            if (self::isMissing($listing[$field] ?? null, false) && isset($defaults[$field])) {
                $listing[$field] = in_array($field, array('car_latitude', 'car_longitude'), true)
                    ? self::coordinate($defaults[$field])
                    : sanitize_text_field((string) $defaults[$field]);
            }
        }

        $listing['extras'] = self::sanitizeStringArray($raw['extras'] ?? array());
        $listing['vehiclehistory'] = self::sanitizeStringArray($raw['vehiclehistory'] ?? array());
        $listing['isantique'] = !empty($raw['isantique']) ? 1 : 0;
        $listing['car_images'] = self::sanitizeStringArray($raw['car_images'] ?? array(), false);

        return $listing;
    }

    /** @return array<int,string> */
    private static function validateEnums(array $listing): array
    {
        $allowed = function_exists('get_allowed_field_values') ? get_allowed_field_values() : array();
        $errors = array();
        foreach ($allowed as $field => $values) {
            if (in_array($field, array('extras', 'vehiclehistory'), true)) {
                foreach ((array) ($listing[$field] ?? array()) as $value) {
                    if (!in_array($value, $values, true)) {
                        $errors[] = sprintf(__('Invalid %1$s value: %2$s.', 'bricks-child'), $field, $value);
                    }
                }
            } elseif (isset($listing[$field]) && $listing[$field] !== '' && !in_array($listing[$field], $values, true)) {
                $errors[] = sprintf(__('Invalid %1$s value: %2$s.', 'bricks-child'), $field, $listing[$field]);
            }
        }
        return $errors;
    }

    /** @return array<string,mixed>|WP_Error */
    private static function validateArchiveImage(ZipArchive $zip, string $path)
    {
        $normalized = str_replace('\\', '/', trim($path));
        if (
            $normalized === '' ||
            str_starts_with($normalized, '/') ||
            str_contains($normalized, "\0") ||
            preg_match('#(^|/)\.\.(/|$)#', $normalized) ||
            preg_match('/^[A-Za-z]:/', $normalized)
        ) {
            return new WP_Error('car_json_import_image_path', sprintf(__('Unsafe image path: %s.', 'bricks-child'), $path));
        }
        if (!preg_match('#^images/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+\.(?:jpe?g|png|gif|webp|avif)$#i', $normalized)) {
            return new WP_Error('car_json_import_image_path', sprintf(__('Unsupported image path: %s.', 'bricks-child'), $path));
        }

        $index = $zip->locateName($normalized);
        if ($index === false) {
            return new WP_Error('car_json_import_image_missing', sprintf(__('Image is missing from ZIP: %s.', 'bricks-child'), $normalized));
        }
        $stat = $zip->statIndex($index);
        $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
        if ($size <= 0 || $size > self::MAX_IMAGE_BYTES) {
            return new WP_Error('car_json_import_image_size', sprintf(__('Image is empty or too large: %s.', 'bricks-child'), $normalized));
        }

        return array('path' => $normalized, 'size' => $size);
    }

    public static function taxonomyCatalogContains(string $make, string $model): bool
    {
        $directory = trailingslashit(get_stylesheet_directory()) . 'simple_jsons/';
        foreach ((array) glob($directory . '*.json') as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data) || !isset($data['make'], $data['models']) || !is_array($data['models'])) {
                continue;
            }
            if (self::fold((string) $data['make']) !== self::fold($make)) {
                continue;
            }
            foreach ($data['models'] as $candidate) {
                if (self::fold((string) $candidate) === self::fold($model)) {
                    return true;
                }
            }
            return false;
        }
        return false;
    }

    public static function findExistingImport(string $platform, string $source_id): int
    {
        if ($platform === '' || $source_id === '') {
            return 0;
        }
        $ids = get_posts(array(
            'post_type'              => 'car',
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'suppress_filters'       => true,
            'meta_query'             => array(
                'relation' => 'AND',
                array('key' => '_autoagora_import_source', 'value' => $platform),
                array('key' => '_autoagora_import_source_id', 'value' => $source_id),
            ),
        ));
        return !empty($ids) ? (int) $ids[0] : 0;
    }

    private static function normalizeMake(string $value): string
    {
        $folded = self::fold($value);
        if ($folded === 'citroen') {
            return 'Citroën';
        }
        if ($folded === 'kia') {
            return 'Kia';
        }
        return trim($value);
    }

    private static function normalizeModel(string $make, string $model): string
    {
        $key = self::fold($make) . '|' . self::fold($model);
        $map = array(
            'bmw|1-series'          => '1 Series',
            'mazda|2'               => '2 / Demio',
            'mazda|demio'           => '2 / Demio',
            'nissan|note e-power'   => 'Note',
            'nissan|serena e-power' => 'Serena',
        );
        return $map[$key] ?? trim($model);
    }

    private static function normalizeFuel(string $value): string
    {
        $map = array(
            'petrol' => 'Petrol', 'diesel' => 'Diesel', 'electric' => 'Electric',
            'hybrid petrol' => 'Petrol hybrid', 'petrol hybrid' => 'Petrol hybrid',
            'hybrid diesel' => 'Diesel hybrid', 'diesel hybrid' => 'Diesel hybrid',
            'plug-in hybrid petrol' => 'Plug-in petrol', 'plug-in petrol' => 'Plug-in petrol',
            'plug-in hybrid diesel' => 'Plug-in diesel', 'plug-in diesel' => 'Plug-in diesel',
            'bi fuel' => 'Bi Fuel', 'hydrogen' => 'Hydrogen', 'natural gas' => 'Natural Gas',
        );
        return $map[self::fold($value)] ?? trim($value);
    }

    private static function normalizeTransmission(string $value): string
    {
        $folded = self::fold($value);
        if (str_contains($folded, 'automatic')) {
            return 'Automatic';
        }
        if (str_contains($folded, 'manual')) {
            return 'Manual';
        }
        return trim($value);
    }

    private static function normalizeBody(string $value): string
    {
        $map = array(
            'sedan' => 'Saloon', 'saloon' => 'Saloon', 'station wagon' => 'Estate', 'estate' => 'Estate',
            'sport utility vehicle' => 'SUV', 'suv' => 'SUV', 'hatchback' => 'Hatchback', 'coupe' => 'Coupe',
            'convertible' => 'Convertible', 'mpv' => 'MPV', 'pickup' => 'Pickup', 'camper' => 'Camper',
            'minibus' => 'Minibus', 'limousine' => 'Limousine', 'car derived van' => 'Car Derived Van',
            'combi van' => 'Combi Van', 'panel van' => 'Panel Van', 'window van' => 'Window Van',
            'city' => 'Car Derived Van', 'refrigerated' => 'Panel Van',
        );
        return $map[self::fold($value)] ?? trim($value);
    }

    private static function normalizeColor(string $value): string
    {
        $map = array(
            'black' => 'Black', 'white' => 'White', 'silver' => 'Silver', 'gray' => 'Gray', 'grey' => 'Gray',
            'red' => 'Red', 'blue' => 'Blue', 'green' => 'Green', 'yellow' => 'Yellow', 'brown' => 'Brown',
            'beige' => 'Beige', 'orange' => 'Orange', 'purple' => 'Purple', 'gold' => 'Gold', 'bronze' => 'Bronze',
        );
        return $map[self::fold($value)] ?? trim($value);
    }

    private static function normalizeAvailability(string $value): string
    {
        $folded = self::fold($value);
        if ($folded === 'in stock' || $folded === 'instock') {
            return 'In Stock';
        }
        if ($folded === 'in transit') {
            return 'In Transit';
        }
        return trim($value);
    }

    private static function normalizeDoorCount($value, string $body_type): ?int
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\s*[0-7]\s*$/', $value))) {
            return (int) $value;
        }
        if (!is_string($value) || !preg_match('/\b([0-7])\s*[-–—]\s*([0-7])\b/u', $value, $matches)) {
            return null;
        }

        $lower = (int) $matches[1];
        $upper = (int) $matches[2];
        $tailgate_counts_as_door = array('Hatchback', 'Estate', 'SUV', 'MPV');
        $conventional_door_count = array('Saloon', 'Coupe', 'Convertible', 'Pickup', 'Limousine');
        if (in_array($body_type, $tailgate_counts_as_door, true)) {
            return $upper;
        }
        if (in_array($body_type, $conventional_door_count, true)) {
            return $lower;
        }
        return null;
    }

    /** @return array<int,string> */
    private static function sanitizeWarnings(array $warnings, array $listing, array $source): array
    {
        $output = array();
        foreach ($warnings as $warning) {
            if (!is_scalar($warning)) {
                continue;
            }
            $warning = sanitize_text_field((string) $warning);
            if ($warning === '') {
                continue;
            }
            if (
                !empty($listing['number_of_doors']) &&
                str_starts_with($warning, 'Ambiguous door count preserved in source_fields:')
            ) {
                continue;
            }
            if (
                str_starts_with($warning, 'Dealer coordinates were ') &&
                self::trustedDealerLocationMatches($listing, $source)
            ) {
                continue;
            }
            $output[] = $warning;
        }
        return array_values(array_unique($output));
    }

    private static function trustedDealerLocationMatches(array $listing, array $source): bool
    {
        if (empty($source['permission_confirmed_by_operator']) || empty($source['dealer_location']) || !is_array($source['dealer_location'])) {
            return false;
        }
        $location = $source['dealer_location'];
        foreach (array('car_city', 'car_address', 'car_latitude', 'car_longitude') as $field) {
            if (!array_key_exists($field, $location) || !array_key_exists($field, $listing)) {
                return false;
            }
        }
        if (
            self::fold((string) $location['car_city']) !== self::fold((string) $listing['car_city']) ||
            self::fold((string) $location['car_address']) !== self::fold((string) $listing['car_address'])
        ) {
            return false;
        }
        return
            abs((float) $location['car_latitude'] - (float) $listing['car_latitude']) < 0.000001 &&
            abs((float) $location['car_longitude'] - (float) $listing['car_longitude']) < 0.000001;
    }

    private static function fold(string $value): string
    {
        $value = remove_accents(strtolower(trim($value)));
        return preg_replace('/\s+/', ' ', $value);
    }

    /** @return array<int,string> */
    private static function sanitizeStringArray($values, bool $sanitize_keys = true): array
    {
        if (!is_array($values)) {
            return array();
        }
        $output = array();
        foreach ($values as $value) {
            if (is_array($value) && isset($value['path'])) {
                $value = $value['path'];
            }
            if (!is_scalar($value)) {
                continue;
            }
            $clean = $sanitize_keys ? sanitize_key((string) $value) : sanitize_text_field((string) $value);
            if ($clean !== '') {
                $output[] = $clean;
            }
        }
        return array_values(array_unique($output));
    }

    private static function coordinate($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    private static function isMissing($value, bool $zero_allowed): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return !$zero_allowed && is_numeric($value) && (float) $value === 0.0;
    }
}
