<?php
/** Authenticated REST API for creating dealership accounts and sync profiles. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Dealer_Onboarding_REST_Controller
{
    private const MAX_BATCH_SIZE = 25;

    public static function register(): void
    {
        add_action('rest_api_init', static function (): void {
            register_rest_route('autoagora/v1', '/dealers/onboard', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'onboard'),
                'permission_callback' => array(__CLASS__, 'permission'),
            ));
            register_rest_route('autoagora/v1', '/dealers/onboarding-state', array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'state'),
                'permission_callback' => array(__CLASS__, 'permission'),
            ));
        });
    }

    public static function permission()
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return new WP_Error(
                'autoagora_dealer_onboarding_forbidden',
                __('Administrator authentication is required.', 'bricks-child'),
                array('status' => rest_authorization_required_code())
            );
        }

        return true;
    }

    public static function state(): WP_REST_Response
    {
        $users = get_users(array('role' => 'dealership', 'orderby' => 'registered', 'order' => 'ASC'));
        $dealership_ids = array_fill_keys(array_map(static function (WP_User $user): int {
            return (int) $user->ID;
        }, $users), true);
        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        $profiles_by_author = array();
        foreach ($profiles as $profile) {
            $author_id = (int) ($profile['author_id'] ?? 0);
            if ($author_id > 0) {
                $profiles_by_author[$author_id][] = array(
                    'profile_id' => (string) $profile['id'],
                    'bazaraki_url' => (string) $profile['dealer_url'],
                    'enabled' => !empty($profile['enabled']),
                    'include_in_run' => !empty($profile['include_in_run']),
                    'dry_run' => !empty($profile['dry_run']),
                );
            }
        }

        $accounts = array();
        foreach ($users as $user) {
            $accounts[] = array(
                'user_id' => (int) $user->ID,
                'name' => (string) $user->display_name,
                'phone' => (string) get_user_meta($user->ID, 'phone_number', true),
                'registered' => (string) $user->user_registered,
                'sync_profiles' => $profiles_by_author[(int) $user->ID] ?? array(),
            );
        }

        return self::response(array(
            'ok' => true,
            'accounts' => $accounts,
            'unassigned_sync_profiles' => array_values(array_filter($profiles, static function (array $profile) use ($dealership_ids): bool {
                $author_id = (int) ($profile['author_id'] ?? 0);
                return $author_id < 1 || !isset($dealership_ids[$author_id]);
            })),
        ));
    }

    public static function onboard(WP_REST_Request $request)
    {
        $input = $request->get_json_params();
        if (!is_array($input)) {
            return self::error('invalid_json', __('Send a JSON request body.', 'bricks-child'), 400);
        }

        $mode = sanitize_key((string) ($input['mode'] ?? 'validate'));
        if (!in_array($mode, array('validate', 'commit'), true)) {
            return self::error('invalid_mode', __('Mode must be validate or commit.', 'bricks-child'), 400);
        }

        $dealers = $input['dealers'] ?? array();
        if (!is_array($dealers) || empty($dealers) || count($dealers) > self::MAX_BATCH_SIZE) {
            return self::error(
                'invalid_batch',
                sprintf(__('Send between 1 and %d dealers.', 'bricks-child'), self::MAX_BATCH_SIZE),
                400
            );
        }

        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        $existing_urls = array();
        foreach ($profiles as $profile) {
            $existing_urls[self::canonicalUrl((string) ($profile['dealer_url'] ?? ''))] = (string) ($profile['id'] ?? '');
        }

        $prepared = array();
        $errors = array();
        $batch_phones = array();
        $batch_urls = array();
        $batch_profile_ids = array_fill_keys(array_keys($profiles), true);

        foreach (array_values($dealers) as $index => $dealer) {
            if (!is_array($dealer)) {
                $errors[] = self::itemError($index, 'invalid_dealer', __('Each dealer must be a JSON object.', 'bricks-child'));
                continue;
            }

            $result = self::prepareDealer($dealer, $profiles, $batch_profile_ids);
            if (is_wp_error($result)) {
                $errors[] = self::itemError($index, $result->get_error_code(), $result->get_error_message());
                continue;
            }

            if (isset($batch_phones[$result['phone']])) {
                $errors[] = self::itemError($index, 'duplicate_batch_phone', __('This phone number appears more than once in the batch.', 'bricks-child'));
                continue;
            }
            if (isset($batch_urls[$result['canonical_bazaraki_url']])) {
                $errors[] = self::itemError($index, 'duplicate_batch_bazaraki_url', __('This Bazaraki profile appears more than once in the batch.', 'bricks-child'));
                continue;
            }
            if (isset($existing_urls[$result['canonical_bazaraki_url']])) {
                $errors[] = self::itemError(
                    $index,
                    'bazaraki_profile_exists',
                    sprintf(__('That Bazaraki URL already belongs to sync profile %s.', 'bricks-child'), $existing_urls[$result['canonical_bazaraki_url']])
                );
                continue;
            }

            $users = get_users(array(
                'meta_key' => 'phone_number',
                'meta_value' => $result['phone'],
                'number' => 1,
                'count_total' => false,
            ));
            if (!empty($users)) {
                $errors[] = self::itemError($index, 'phone_exists', __('This phone number is already registered.', 'bricks-child'));
                continue;
            }

            $batch_phones[$result['phone']] = true;
            $batch_urls[$result['canonical_bazaraki_url']] = true;
            $batch_profile_ids[$result['profile_id']] = true;
            $prepared[] = $result;
        }

        if (!empty($errors)) {
            return self::error(
                'batch_validation_failed',
                __('No changes were made because one or more dealers failed validation.', 'bricks-child'),
                400,
                array('errors' => $errors)
            );
        }

        if ($mode === 'validate') {
            $items = array_map(static function (array $dealer): array {
                return array(
                    'name' => $dealer['name'],
                    'phone' => $dealer['phone'],
                    'phone_source_url' => $dealer['phone_source_url'],
                    'phone_source_is_bazaraki' => $dealer['phone_source_is_bazaraki'],
                    'bazaraki_url' => $dealer['bazaraki_url'],
                    'profile_id' => $dealer['profile_id'],
                    'location_complete' => $dealer['location_complete'],
                );
            }, $prepared);

            return self::response(array(
                'ok' => true,
                'mode' => 'validate',
                'changes_made' => false,
                'dealers' => $items,
            ));
        }

        return self::commit($prepared, $profiles);
    }

    /** @param array<string,mixed> $dealer @param array<string,array<string,mixed>> $profiles */
    private static function prepareDealer(array $dealer, array $profiles, array $reserved_profile_ids)
    {
        $name = sanitize_text_field((string) ($dealer['name'] ?? ''));
        if ($name === '' || strlen($name) > 160) {
            return new WP_Error('invalid_name', __('Enter a dealership name of 160 characters or fewer.', 'bricks-child'));
        }

        $phone = autoagora_normalize_dealership_phone((string) ($dealer['phone'] ?? ''));
        if (is_wp_error($phone)) {
            return $phone;
        }

        $phone_source_url = self::validatedHttpUrl((string) ($dealer['phone_source_url'] ?? ''));
        if ($phone_source_url === '') {
            return new WP_Error('invalid_phone_source', __('A valid public source URL for the phone number is required.', 'bricks-child'));
        }
        if (self::isAutoAgoraUrl($phone_source_url)) {
            return new WP_Error(
                'circular_phone_source',
                __('An AutoAgora dealer page cannot be used as phone evidence because its original source may be Bazaraki.', 'bricks-child')
            );
        }
        $phone_source_is_bazaraki = self::isBazarakiUrl($phone_source_url);
        $fallback_allowed = self::toBool($dealer['allow_bazaraki_phone_fallback'] ?? false);
        $search_notes = sanitize_textarea_field((string) ($dealer['phone_search_notes'] ?? ''));
        if ($phone_source_is_bazaraki && (!$fallback_allowed || strlen($search_notes) < 20)) {
            return new WP_Error(
                'bazaraki_phone_source_forbidden',
                __('A Bazaraki phone is allowed only as an explicit last resort, with allow_bazaraki_phone_fallback=true and notes describing the other sources checked.', 'bricks-child')
            );
        }
        $phone_source_label = sanitize_text_field((string) ($dealer['phone_source_label'] ?? ''));
        if ($phone_source_label === '') {
            $phone_source_label = $phone_source_is_bazaraki ? 'Bazaraki last-resort fallback' : 'External public source';
        }

        $bazaraki_url = self::validatedHttpUrl((string) ($dealer['bazaraki_url'] ?? ''));
        if ($bazaraki_url === '' || !self::isBazarakiDealerUrl($bazaraki_url)) {
            return new WP_Error('invalid_bazaraki_url', __('A valid English Bazaraki /c/ dealer URL or /items/author/ URL is required.', 'bricks-child'));
        }
        $bazaraki_url = self::stripUrlNoise($bazaraki_url);

        $requested_id = strtolower(trim((string) ($dealer['profile_id'] ?? '')));
        if ($requested_id !== '') {
            if (!preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/', $requested_id)) {
                return new WP_Error('invalid_profile_id', __('Profile IDs must contain 3-64 lowercase letters, numbers, or hyphens.', 'bricks-child'));
            }
            if (isset($reserved_profile_ids[$requested_id]) || isset($profiles[$requested_id])) {
                return new WP_Error('profile_id_exists', __('That sync profile ID already exists.', 'bricks-child'));
            }
            $profile_id = $requested_id;
        } else {
            $profile_id = self::uniqueProfileId($name, $reserved_profile_ids);
        }

        $location = is_array($dealer['location'] ?? null) ? $dealer['location'] : array();
        $city = sanitize_text_field((string) ($location['city'] ?? ''));
        $district = sanitize_text_field((string) ($location['district'] ?? ''));
        $address = sanitize_text_field((string) ($location['address'] ?? ''));
        $latitude = is_numeric($location['latitude'] ?? null) ? (float) $location['latitude'] : null;
        $longitude = is_numeric($location['longitude'] ?? null) ? (float) $location['longitude'] : null;
        $has_any_location = $city !== '' || $district !== '' || $address !== '' || $latitude !== null || $longitude !== null;
        $location_complete = $city !== '' && $address !== '' && $latitude !== null && $longitude !== null
            && $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
        if ($has_any_location && !$location_complete) {
            return new WP_Error('incomplete_location', __('If location is supplied, city, address, latitude, and longitude must all be valid.', 'bricks-child'));
        }

        return array(
            'name' => $name,
            'phone' => $phone,
            'phone_source_url' => $phone_source_url,
            'phone_source_label' => $phone_source_label,
            'phone_source_is_bazaraki' => $phone_source_is_bazaraki,
            'phone_search_notes' => $search_notes,
            'bazaraki_url' => $bazaraki_url,
            'canonical_bazaraki_url' => self::canonicalUrl($bazaraki_url),
            'profile_id' => $profile_id,
            'location_complete' => $location_complete,
            'location' => array(
                'car_city' => $city,
                'car_district' => $district,
                'car_address' => $address,
                'car_latitude' => $latitude,
                'car_longitude' => $longitude,
            ),
        );
    }

    /** @param array<int,array<string,mixed>> $dealers @param array<string,array<string,mixed>> $original_profiles */
    private static function commit(array $dealers, array $original_profiles)
    {
        $created_user_ids = array();
        $results = array();
        $updated_profiles = $original_profiles;
        $actor_id = get_current_user_id();
        $created_at = gmdate('c');

        foreach ($dealers as $dealer) {
            $password = wp_generate_password(24, true, true);
            $account = create_dealership_account($dealer['phone'], $dealer['name'], $password);
            if (is_wp_error($account)) {
                self::rollbackUsers($created_user_ids);
                return self::error(
                    'account_creation_failed',
                    __('No profiles were saved and newly created accounts were rolled back.', 'bricks-child'),
                    500,
                    array('dealer' => $dealer['name'], 'reason' => $account->get_error_message())
                );
            }

            $user_id = (int) $account['user_id'];
            $created_user_ids[] = $user_id;
            update_user_meta($user_id, 'autoagora_phone_source_url', $dealer['phone_source_url']);
            update_user_meta($user_id, 'autoagora_phone_source_label', $dealer['phone_source_label']);
            update_user_meta($user_id, 'autoagora_phone_source_is_bazaraki', $dealer['phone_source_is_bazaraki'] ? '1' : '0');
            update_user_meta($user_id, 'autoagora_phone_source_search_notes', $dealer['phone_search_notes']);
            update_user_meta($user_id, 'autoagora_bazaraki_dealer_url', $dealer['bazaraki_url']);
            update_user_meta($user_id, 'autoagora_bulk_onboarded_at', $created_at);
            update_user_meta($user_id, 'autoagora_bulk_onboarded_by', $actor_id);

            $updated_profiles[$dealer['profile_id']] = array_merge(array(
                'id' => $dealer['profile_id'],
                'name' => $dealer['name'],
                'dealer_url' => $dealer['bazaraki_url'],
                'author_id' => $user_id,
                'enabled' => false,
                'include_in_run' => false,
                'dry_run' => true,
                'missing_confirmations' => 3,
                'delay_ms' => 3500,
                'max_images' => 40,
                'max_missing_ratio' => 0.35,
            ), $dealer['location']);

            $results[] = array(
                'name' => $dealer['name'],
                'user_id' => $user_id,
                'username' => (string) $account['username'],
                'password' => $password,
                'phone' => $dealer['phone'],
                'profile_id' => $dealer['profile_id'],
                'bazaraki_url' => $dealer['bazaraki_url'],
            );
            unset($password);
        }

        AutoAgora_Bazaraki_Sync_Profiles::save(array_values($updated_profiles));
        $saved_profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        foreach ($results as $result) {
            $saved = $saved_profiles[$result['profile_id']] ?? null;
            if (!is_array($saved) || (int) ($saved['author_id'] ?? 0) !== (int) $result['user_id']) {
                AutoAgora_Bazaraki_Sync_Profiles::save(array_values($original_profiles));
                self::rollbackUsers($created_user_ids);
                return self::error(
                    'profile_save_failed',
                    __('The sync profiles could not be verified. Newly created accounts were rolled back.', 'bricks-child'),
                    500
                );
            }
        }

        return self::response(array(
            'ok' => true,
            'mode' => 'commit',
            'changes_made' => true,
            'credentials_are_one_time' => true,
            'profiles_enabled' => false,
            'profiles_included_in_run' => false,
            'profiles_dry_run' => true,
            'dealers' => $results,
        ));
    }

    /** @param array<int,int> $user_ids */
    private static function rollbackUsers(array $user_ids): void
    {
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach (array_reverse($user_ids) as $user_id) {
            wp_delete_user((int) $user_id);
        }
    }

    private static function validatedHttpUrl(string $url): string
    {
        $url = esc_url_raw(trim($url));
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        return in_array($scheme, array('http', 'https'), true) && $host !== '' ? $url : '';
    }

    private static function isBazarakiUrl(string $url): bool
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        return $host === 'bazaraki.com' || str_ends_with($host, '.bazaraki.com');
    }

    private static function isBazarakiDealerUrl(string $url): bool
    {
        if (!self::isBazarakiUrl($url)) {
            return false;
        }
        $path = '/' . ltrim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        return preg_match('#^/c/[^/]+/?$#i', $path) === 1
            || preg_match('#^/items/author/[0-9]+/?$#', $path) === 1;
    }

    private static function isAutoAgoraUrl(string $url): bool
    {
        $source_host = preg_replace('/^www\./', '', strtolower((string) wp_parse_url($url, PHP_URL_HOST)));
        $site_host = preg_replace('/^www\./', '', strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST)));
        return $source_host !== '' && $site_host !== ''
            && ($source_host === $site_host || str_ends_with($source_host, '.' . $site_host));
    }

    private static function stripUrlNoise(string $url): string
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = '/' . ltrim((string) ($parts['path'] ?? '/'), '/');
        return $scheme . '://' . $host . trailingslashit($path);
    }

    private static function canonicalUrl(string $url): string
    {
        return strtolower(untrailingslashit(self::stripUrlNoise($url)));
    }

    private static function uniqueProfileId(string $name, array $reserved): string
    {
        $base = strtolower(remove_accents($name));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = substr((string) $base, 0, 56);
        $base = trim($base, '-');
        if (strlen($base) < 3) {
            $base = 'dealer-' . strtolower(wp_generate_password(8, false, false));
        }
        $candidate = $base;
        $suffix = 2;
        while (isset($reserved[$candidate])) {
            $candidate = substr($base, 0, 59) . '-' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    private static function toBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string,mixed> */
    private static function itemError(int $index, string $code, string $message): array
    {
        return array('index' => $index, 'code' => $code, 'message' => $message);
    }

    private static function error(string $code, string $message, int $status, array $extra = array()): WP_Error
    {
        return new WP_Error('autoagora_dealer_onboarding_' . $code, $message, array_merge(array('status' => $status), $extra));
    }

    private static function response(array $body): WP_REST_Response
    {
        $response = new WP_REST_Response($body, 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        return $response;
    }
}
