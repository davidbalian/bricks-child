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
        return is_array($filtered) ? $filtered : array();
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
            'car_city'              => sanitize_text_field((string) ($profile['car_city'] ?? '')),
            'car_district'          => sanitize_text_field((string) ($profile['car_district'] ?? '')),
            'car_address'           => sanitize_text_field((string) ($profile['car_address'] ?? '')),
            'car_latitude'          => is_numeric($profile['car_latitude'] ?? null) ? (float) $profile['car_latitude'] : null,
            'car_longitude'         => is_numeric($profile['car_longitude'] ?? null) ? (float) $profile['car_longitude'] : null,
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
        return array_intersect_key($profile, array_flip(array(
            'car_city', 'car_district', 'car_address', 'car_latitude', 'car_longitude',
        )));
    }
}
