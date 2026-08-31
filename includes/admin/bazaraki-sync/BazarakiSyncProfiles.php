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
        $latitude = is_numeric($profile['car_latitude'] ?? null) ? (float) $profile['car_latitude'] : null;
        $longitude = is_numeric($profile['car_longitude'] ?? null) ? (float) $profile['car_longitude'] : null;
        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            $latitude = null;
        }
        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            $longitude = null;
        }

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
            'car_city'               => sanitize_text_field((string) ($profile['car_city'] ?? '')),
            'car_district'           => sanitize_text_field((string) ($profile['car_district'] ?? '')),
            'car_address'            => sanitize_text_field((string) ($profile['car_address'] ?? '')),
            'car_latitude'           => $latitude,
            'car_longitude'          => $longitude,
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
        $profile = self::sanitize($profile);
        if (
            $profile['car_city'] === '' ||
            $profile['car_address'] === '' ||
            $profile['car_latitude'] === null ||
            $profile['car_longitude'] === null
        ) {
            return array();
        }
        return array_intersect_key($profile, array_flip(array(
            'car_city', 'car_district', 'car_address', 'car_latitude', 'car_longitude',
        )));
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
