<?php
/** Server-side dealer profile configuration. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Bazaraki_Sync_Profiles
{
    private const OPTION = 'autoagora_bazaraki_sync_profiles';

    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        $stored = get_option(self::OPTION, array());
        $profiles = is_array($stored) ? $stored : array();
        $filtered = apply_filters('autoagora_bazaraki_sync_profiles', $profiles);
        if (!is_array($filtered)) {
            return array();
        }
        $clean = array();
        foreach ($filtered as $key => $profile) {
            if (!is_array($profile)) {
                continue;
            }
            if (empty($profile['id']) && is_string($key)) {
                $profile['id'] = $key;
            }
            $profile = self::sanitize($profile);
            if ($profile['id'] !== '') {
                $clean[$profile['id']] = $profile;
            }
        }
        return $clean;
    }

    /** @return array<string,mixed>|null */
    public static function get(string $profile_id): ?array
    {
        $profiles = self::all();
        return isset($profiles[$profile_id]) && is_array($profiles[$profile_id]) ? $profiles[$profile_id] : null;
    }

    /** @param array<string,mixed> $profile */
    public static function sanitize(array $profile): array
    {
        return array(
            'id'                    => sanitize_key((string) ($profile['id'] ?? '')),
            'name'                  => sanitize_text_field((string) ($profile['name'] ?? '')),
            'dealer_url'            => esc_url_raw((string) ($profile['dealer_url'] ?? '')),
            'author_id'             => absint($profile['author_id'] ?? 0),
            'enabled'               => !empty($profile['enabled']),
            'dry_run'               => !empty($profile['dry_run']),
            'missing_confirmations' => max(2, min(10, absint($profile['missing_confirmations'] ?? 3))),
            'delay_ms'               => max(1000, min(30000, absint($profile['delay_ms'] ?? 3500))),
            'max_images'             => max(1, min(40, absint($profile['max_images'] ?? 40))),
            'max_missing_ratio'      => max(0.05, min(0.90, (float) ($profile['max_missing_ratio'] ?? 0.35))),
        );
    }

    /** @param array<string,array<string,mixed>> $profiles */
    public static function save(array $profiles): void
    {
        $clean = array();
        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }
            $profile = self::sanitize($profile);
            if ($profile['id'] !== '') {
                $clean[$profile['id']] = $profile;
            }
        }
        update_option(self::OPTION, $clean, false);
    }

    /** @return array<string,mixed> */
    public static function defaults(array $profile): array
    {
        $author_id = absint($profile['author_id'] ?? 0);
        if (function_exists('autoagora_get_user_saved_locations')) {
            foreach (autoagora_get_user_saved_locations($author_id) as $location) {
                $defaults = self::completeLocation(array(
                    'car_city' => $location['city'] ?? '',
                    'car_district' => $location['district'] ?? '',
                    'car_address' => $location['address'] ?? '',
                    'car_latitude' => $location['latitude'] ?? null,
                    'car_longitude' => $location['longitude'] ?? null,
                ));
                if (!empty($defaults)) {
                    return $defaults;
                }
            }
        }

        return self::dealerProfileLocation($author_id);
    }

    /** @param array<string,mixed> $location @return array<string,mixed> */
    private static function completeLocation(array $location): array
    {
        $city = sanitize_text_field((string) ($location['car_city'] ?? ''));
        $address = sanitize_text_field((string) ($location['car_address'] ?? ''));
        $latitude = is_numeric($location['car_latitude'] ?? null) ? (float) $location['car_latitude'] : 0.0;
        $longitude = is_numeric($location['car_longitude'] ?? null) ? (float) $location['car_longitude'] : 0.0;
        if ($city === '' || $address === '' || $latitude === 0.0 || $longitude === 0.0) {
            return array();
        }
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return array();
        }
        return array(
            'car_city' => $city,
            'car_district' => sanitize_text_field((string) ($location['car_district'] ?? '')),
            'car_address' => $address,
            'car_latitude' => $latitude,
            'car_longitude' => $longitude,
        );
    }

    /** @return array<string,mixed> */
    private static function dealerProfileLocation(int $author_id): array
    {
        if ($author_id <= 0) {
            return array();
        }

        $user_address = function_exists('get_field') ? (string) get_field('dealer_maps_address', 'user_' . $author_id) : '';
        $user_map = function_exists('get_field') ? (string) get_field('dealer_maps_url', 'user_' . $author_id) : '';
        $user_coordinates = self::coordinatesFromMap($user_map);
        if (!empty($user_coordinates) && $user_address !== '') {
            return self::completeLocation(array(
                'car_city' => self::cityFromAddress($user_address),
                'car_address' => $user_address,
                'car_latitude' => $user_coordinates['latitude'],
                'car_longitude' => $user_coordinates['longitude'],
            ));
        }

        $dealer_profiles = get_posts(array(
            'post_type' => 'dealer_profile',
            'post_status' => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
            'meta_key' => 'dealer_claimed_user_id',
            'meta_value' => $author_id,
        ));
        if (empty($dealer_profiles)) {
            return array();
        }

        $post_id = (int) $dealer_profiles[0];
        $city = (string) get_post_meta($post_id, 'dealer_city', true);
        $district = (string) get_post_meta($post_id, 'dealer_district', true);
        $address = (string) get_post_meta($post_id, 'dealer_address', true);
        if ($address === '') {
            $address = (string) get_post_meta($post_id, 'dealer_maps_address', true);
        }
        $coordinates = self::coordinatesFromMap((string) get_post_meta($post_id, 'dealer_maps_url', true));
        if (empty($coordinates)) {
            return array();
        }
        return self::completeLocation(array(
            'car_city' => $city !== '' ? $city : self::cityFromAddress($address),
            'car_district' => $district,
            'car_address' => $address,
            'car_latitude' => $coordinates['latitude'],
            'car_longitude' => $coordinates['longitude'],
        ));
    }

    private static function cityFromAddress(string $address): string
    {
        foreach (array('Nicosia', 'Limassol', 'Larnaca', 'Paphos', 'Famagusta') as $city) {
            if (stripos($address, $city) !== false) {
                return $city;
            }
        }
        return '';
    }

    /** @return array{latitude:float,longitude:float}|array{} */
    private static function coordinatesFromMap(string $value): array
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/<iframe[^>]+src=[\"\']([^\"\']+)/i', $value, $match)) {
            $value = $match[1];
        }
        $value = rawurldecode($value);

        $latitude = null;
        $longitude = null;
        if (preg_match('/!3d(-?\d+(?:\.\d+)?)/', $value, $lat_match) && preg_match('/!2d(-?\d+(?:\.\d+)?)/', $value, $lng_match)) {
            $latitude = (float) $lat_match[1];
            $longitude = (float) $lng_match[1];
        } elseif (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $value, $match)) {
            $latitude = (float) $match[1];
            $longitude = (float) $match[2];
        } elseif (preg_match('/(?:query|q|ll)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i', $value, $match)) {
            $latitude = (float) $match[1];
            $longitude = (float) $match[2];
        }

        if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return array();
        }
        return array('latitude' => $latitude, 'longitude' => $longitude);
    }

    /** @return array<int,array<string,mixed>> */
    public static function enabledForWorker(): array
    {
        $output = array();
        foreach (self::all() as $profile) {
            if (!is_array($profile) || empty($profile['enabled'])) {
                continue;
            }
            $profile = self::sanitize($profile);
            $output[] = array(
                'id' => $profile['id'],
                'dealer_url' => $profile['dealer_url'],
                'headless' => true,
                'browser' => 'chrome',
                'delay_ms' => $profile['delay_ms'],
                'max_images' => $profile['max_images'],
                'max_missing_ratio' => $profile['max_missing_ratio'],
            );
        }
        return $output;
    }
}
