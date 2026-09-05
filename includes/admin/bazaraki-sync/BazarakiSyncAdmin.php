<?php
/** Operational UI for centrally managed Bazaraki dealer profiles. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Bazaraki_Sync_Admin
{
    private const PAGE = 'autoagora-bazaraki-sync';

    public static function register(): void
    {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('admin_post_autoagora_save_bazaraki_sync_profile', array(__CLASS__, 'save'));
        add_action('admin_post_autoagora_save_bazaraki_sync_profiles', array(__CLASS__, 'saveAll'));
        add_action('admin_post_autoagora_delete_bazaraki_sync_profile', array(__CLASS__, 'delete'));
    }

    public static function enqueue(string $hook_suffix): void
    {
        if ($hook_suffix !== 'tools_page_' . self::PAGE) {
            return;
        }

        $directory = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        $script_dependencies = array();
        if (defined('GOOGLE_MAPS_API_KEY') && (string) GOOGLE_MAPS_API_KEY !== '') {
            wp_enqueue_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode((string) GOOGLE_MAPS_API_KEY) . '&libraries=places&language=en',
                array(),
                null,
                true
            );
            $script_dependencies[] = 'google-maps';
        }
        wp_enqueue_style(
            'autoagora-location-picker',
            $uri . '/assets/css/location-picker.css',
            array(),
            (string) filemtime($directory . '/assets/css/location-picker.css')
        );
        wp_enqueue_style(
            'autoagora-bazaraki-sync-admin',
            $uri . '/includes/admin/bazaraki-sync/bazaraki-sync-admin.css',
            array('autoagora-location-picker'),
            (string) filemtime($directory . '/includes/admin/bazaraki-sync/bazaraki-sync-admin.css')
        );
        wp_enqueue_script(
            'autoagora-bazaraki-sync-admin',
            $uri . '/includes/admin/bazaraki-sync/bazaraki-sync-admin.js',
            $script_dependencies,
            (string) filemtime($directory . '/includes/admin/bazaraki-sync/bazaraki-sync-admin.js'),
            true
        );
        wp_localize_script('autoagora-bazaraki-sync-admin', 'autoagoraSyncLocation', array(
            'defaultLat' => 35.1856,
            'defaultLng' => 33.3823,
            'defaultZoom' => 8,
            'chooseLocation' => __('Choose location', 'bricks-child'),
            'changeLocation' => __('Change location', 'bricks-child'),
            'clearLocation' => __('Clear location', 'bricks-child'),
            'noLocation' => __('No default location selected.', 'bricks-child'),
            'searchLocation' => __('Search for a location in Cyprus...', 'bricks-child'),
            'applyLocation' => __('Use this location', 'bricks-child'),
            'close' => __('Close', 'bricks-child'),
            'mapsUnavailable' => __('Google Maps could not be loaded. Check the site API key configuration.', 'bricks-child'),
        ));
    }

    public static function menu(): void
    {
        add_management_page(
            __('Bazaraki Sync', 'bricks-child'),
            __('Bazaraki Sync', 'bricks-child'),
            'manage_options',
            self::PAGE,
            array(__CLASS__, 'render')
        );
    }

    /** Add one new profile. Existing profiles are edited through saveAll(). */
    public static function save(): void
    {
        self::requirePermission();
        check_admin_referer('autoagora_save_bazaraki_sync_profile');
        $profile = AutoAgora_Bazaraki_Sync_Profiles::sanitize((array) wp_unslash($_POST['profile'] ?? array()));
        $original_id = sanitize_key((string) wp_unslash($_POST['original_id'] ?? ''));
        if ($original_id !== '') {
            $profile['id'] = $original_id;
        }

        $error = self::profileError($profile);
        if ($error !== '') {
            wp_die(esc_html($error));
        }

        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        if ($original_id === '' && isset($profiles[$profile['id']])) {
            wp_die(esc_html__('A sync profile with that ID already exists.', 'bricks-child'));
        }
        $profiles[$profile['id']] = $profile;
        AutoAgora_Bazaraki_Sync_Profiles::save(array_values($profiles));
        self::redirect(array('added' => 1));
    }

    /** Save every submitted profile only after the whole set validates. */
    public static function saveAll(): void
    {
        self::requirePermission();
        check_admin_referer('autoagora_save_bazaraki_sync_profiles');

        $stored = AutoAgora_Bazaraki_Sync_Profiles::all();
        $submitted = (array) wp_unslash($_POST['profiles'] ?? array());
        $updated = $stored;
        $errors = array();

        foreach ($stored as $profile_id => $current) {
            if (!isset($submitted[$profile_id]) || !is_array($submitted[$profile_id])) {
                continue;
            }
            $candidate = AutoAgora_Bazaraki_Sync_Profiles::sanitize($submitted[$profile_id]);
            // IDs are immutable in the bulk editor and never trusted from POST.
            $candidate['id'] = $profile_id;
            $error = self::profileError($candidate);
            if ($error !== '') {
                $errors[] = sprintf('%s: %s', $current['name'] ?: $profile_id, $error);
                continue;
            }
            $updated[$profile_id] = $candidate;
        }

        if (!empty($errors)) {
            self::setNotice('error', implode(' ', $errors));
            self::redirect(array('save_error' => 1));
        }

        AutoAgora_Bazaraki_Sync_Profiles::save(array_values($updated));
        self::redirect(array('saved_all' => 1));
    }

    public static function delete(): void
    {
        self::requirePermission();
        $profile_id = sanitize_key((string) wp_unslash($_POST['profile_id'] ?? ''));
        check_admin_referer('autoagora_delete_bazaraki_sync_profile_' . $profile_id);
        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        if ($profile_id !== '' && isset($profiles[$profile_id])) {
            unset($profiles[$profile_id]);
            AutoAgora_Bazaraki_Sync_Profiles::save(array_values($profiles));
        }
        self::redirect(array('deleted' => 1));
    }

    public static function render(): void
    {
        self::requirePermission();
        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        $runs = AutoAgora_Bazaraki_Sync_Queue::recentRuns(100);
        $latest_runs = self::latestRunsByProfile($runs);

        echo '<div class="wrap autoagora-sync-admin"><h1>' . esc_html__('Bazaraki dealer sync', 'bricks-child') . '</h1>';
        self::renderNotices();

        $secret_ready = defined('AUTOAGORA_BAZARAKI_SYNC_SECRET') && strlen((string) AUTOAGORA_BAZARAKI_SYNC_SECRET) >= 32;
        echo '<div class="autoagora-sync-overview">';
        echo '<span class="autoagora-sync-auth ' . ($secret_ready ? 'is-ready' : 'is-missing') . '">';
        echo esc_html($secret_ready ? __('API authentication configured', 'bricks-child') : __('API authentication is not configured', 'bricks-child'));
        echo '</span>';
        echo '<p>' . esc_html__('The worker loads only profiles checked “Include in run”. The choice stays saved until you change it.', 'bricks-child') . '</p>';
        echo '</div>';

        echo '<div class="autoagora-sync-section-heading"><div><h2>' . esc_html__('Dealer profiles', 'bricks-child') . '</h2>';
        echo '<p>' . esc_html__('Expand a profile only when you need to edit its details.', 'bricks-child') . '</p></div></div>';

        if (!empty($profiles)) {
            self::profilesForm($profiles, $latest_runs);
        } else {
            echo '<div class="autoagora-sync-empty"><p>' . esc_html__('No dealer profiles have been added yet.', 'bricks-child') . '</p></div>';
        }

        self::newProfileForm(empty($profiles));
        self::runsTable($runs);
        echo '</div>';
    }

    /** @param array<string,array<string,mixed>> $profiles @param array<string,array<string,mixed>> $latest_runs */
    private static function profilesForm(array $profiles, array $latest_runs): void
    {
        $included = count(array_filter($profiles, static function ($profile): bool {
            return !empty($profile['include_in_run']);
        }));

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="autoagora-sync-profiles-form" data-profiles-form>';
        wp_nonce_field('autoagora_save_bazaraki_sync_profiles');
        echo '<input type="hidden" name="action" value="autoagora_save_bazaraki_sync_profiles">';
        echo '<div class="autoagora-sync-toolbar">';
        echo '<label class="screen-reader-text" for="autoagora-sync-profile-search">' . esc_html__('Search dealer profiles', 'bricks-child') . '</label>';
        echo '<input type="search" id="autoagora-sync-profile-search" class="regular-text" placeholder="' . esc_attr__('Search profiles…', 'bricks-child') . '" data-profile-search>';
        echo '<button type="button" class="button" data-select-profiles="all">' . esc_html__('Include all', 'bricks-child') . '</button>';
        echo '<button type="button" class="button" data-select-profiles="none">' . esc_html__('Include none', 'bricks-child') . '</button>';
        echo '<span class="autoagora-sync-included-count" data-included-count data-template="' . esc_attr__('%1$d of %2$d profiles included', 'bricks-child') . '">' . esc_html(sprintf(__('%1$d of %2$d profiles included', 'bricks-child'), $included, count($profiles))) . '</span>';
        echo '<span class="autoagora-sync-dirty" data-unsaved-indicator hidden>' . esc_html__('Unsaved changes', 'bricks-child') . '</span>';
        echo '<button type="submit" class="button button-primary autoagora-sync-save-all">' . esc_html__('Save all changes', 'bricks-child') . '</button>';
        echo '</div><div class="autoagora-sync-profile-list" data-profile-list>';
        foreach ($profiles as $profile) {
            self::profilePanel($profile, $latest_runs[$profile['id']] ?? null);
        }
        echo '<p class="autoagora-sync-no-results" data-profile-no-results hidden>' . esc_html__('No profiles match your search.', 'bricks-child') . '</p>';
        echo '</div></form>';

        // Keep destructive profile actions in independent POST forms without nesting forms.
        foreach ($profiles as $profile) {
            $form_id = 'autoagora-delete-profile-' . sanitize_html_class($profile['id']);
            echo '<form id="' . esc_attr($form_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="autoagora-sync-delete-form" onsubmit="return confirm(\'' . esc_js(__('Delete this sync profile? Existing cars will not be deleted.', 'bricks-child')) . '\');">';
            wp_nonce_field('autoagora_delete_bazaraki_sync_profile_' . $profile['id']);
            echo '<input type="hidden" name="action" value="autoagora_delete_bazaraki_sync_profile"><input type="hidden" name="profile_id" value="' . esc_attr($profile['id']) . '"></form>';
        }
    }

    /** @param array<string,mixed> $profile @param array<string,mixed>|null $latest_run */
    private static function profilePanel(array $profile, ?array $latest_run): void
    {
        $id = (string) $profile['id'];
        $prefix = 'sync-profile-' . sanitize_html_class($id);
        $scope = 'profiles[' . $id . ']';
        $owner = get_userdata((int) $profile['author_id']);
        $owner_name = $owner ? $owner->display_name : __('Unknown owner', 'bricks-child');
        $location = AutoAgora_Bazaraki_Sync_Profiles::defaults($profile);
        $status_class = !empty($profile['enabled']) ? (!empty($profile['dry_run']) ? 'is-dry-run' : 'is-live') : 'is-disabled';
        $status_text = !empty($profile['enabled']) ? (!empty($profile['dry_run']) ? __('Dry run', 'bricks-child') : __('Live', 'bricks-child')) : __('Disabled', 'bricks-child');

        echo '<details class="autoagora-sync-profile" data-sync-profile>';
        echo '<summary class="autoagora-sync-profile-summary">';
        echo '<span class="autoagora-sync-include" data-include-control><input type="hidden" name="' . esc_attr($scope . '[include_in_run]') . '" value="0">';
        echo '<label><input type="checkbox" name="' . esc_attr($scope . '[include_in_run]') . '" value="1" data-include-profile ' . checked(!empty($profile['include_in_run']), true, false) . '> <span>' . esc_html__('Include in run', 'bricks-child') . '</span></label></span>';
        echo '<span class="autoagora-sync-profile-identity"><strong>' . esc_html($profile['name'] ?: $id) . '</strong><code>' . esc_html($id) . '</code></span>';
        echo '<span class="autoagora-sync-profile-meta"><span>' . esc_html($owner_name) . '</span><span>' . esc_html(!empty($location) ? $location['car_address'] : __('No default location', 'bricks-child')) . '</span></span>';
        echo '<span class="autoagora-sync-profile-state"><span class="autoagora-sync-badge ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>';
        if ($latest_run) {
            echo '<span class="autoagora-sync-last-run">' . esc_html(sprintf(__('Last: %1$s · %2$s', 'bricks-child'), self::statusLabel((string) $latest_run['status']), (string) $latest_run['created_at'])) . '</span>';
        } else {
            echo '<span class="autoagora-sync-last-run">' . esc_html__('Never run', 'bricks-child') . '</span>';
        }
        echo '</span><span class="autoagora-sync-chevron" aria-hidden="true"></span></summary>';

        echo '<div class="autoagora-sync-profile-body"><input type="hidden" name="' . esc_attr($scope . '[id]') . '" value="' . esc_attr($id) . '">';
        echo '<div class="autoagora-sync-fields">';
        self::input($prefix, $scope, 'name', __('Dealer name', 'bricks-child'), $profile['name']);
        self::input($prefix, $scope, 'dealer_url', __('Bazaraki dealer URL', 'bricks-child'), $profile['dealer_url'], 'url');
        self::ownerInput($prefix, $scope, (int) $profile['author_id']);
        self::locationPicker($prefix, $scope, $profile);
        self::input($prefix, $scope, 'missing_confirmations', __('Missing runs before expiry', 'bricks-child'), $profile['missing_confirmations'], 'number');
        self::input($prefix, $scope, 'max_missing_ratio', __('Maximum one-run missing ratio', 'bricks-child'), $profile['max_missing_ratio'], 'number');
        self::input($prefix, $scope, 'delay_ms', __('Delay between listings (ms)', 'bricks-child'), $profile['delay_ms'], 'number');
        self::input($prefix, $scope, 'max_images', __('Maximum images per car', 'bricks-child'), $profile['max_images'], 'number');
        self::modeInputs($scope, $profile, false);
        echo '</div><div class="autoagora-sync-profile-actions">';
        echo '<button type="button" class="button button-link-delete" data-submit-external-form="autoagora-delete-profile-' . esc_attr(sanitize_html_class($id)) . '">' . esc_html__('Delete profile', 'bricks-child') . '</button>';
        echo '</div></div></details>';
    }

    private static function newProfileForm(bool $open): void
    {
        $defaults = array(
            'id' => '', 'name' => '', 'dealer_url' => '', 'author_id' => get_current_user_id(),
            'enabled' => false, 'include_in_run' => true, 'dry_run' => true,
            'missing_confirmations' => 3, 'delay_ms' => 3500, 'max_images' => 40,
            'max_missing_ratio' => 0.35, 'car_city' => '', 'car_district' => '',
            'car_address' => '', 'car_latitude' => null, 'car_longitude' => null,
        );
        if ($open) {
            $defaults = array_merge($defaults, array(
                'id' => 'auto-cyprus-8598735',
                'name' => 'Auto.Cyprus',
                'dealer_url' => 'https://www.bazaraki.com/items/author/8598735/?lat=34.6657927&lng=33.0034017&radius=5000',
            ));
        }
        $profile = AutoAgora_Bazaraki_Sync_Profiles::sanitize($defaults);
        $prefix = 'sync-profile-new';
        $scope = 'profile';

        echo '<details class="autoagora-sync-add-profile"' . ($open ? ' open' : '') . '><summary><strong>' . esc_html__('Add dealer profile', 'bricks-child') . '</strong><span>' . esc_html__('Create another independently managed dealer sync.', 'bricks-child') . '</span></summary>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('autoagora_save_bazaraki_sync_profile');
        echo '<input type="hidden" name="action" value="autoagora_save_bazaraki_sync_profile"><input type="hidden" name="original_id" value=""><div class="autoagora-sync-fields">';
        self::input($prefix, $scope, 'id', __('Profile ID', 'bricks-child'), $profile['id'], 'text', false, __('Lowercase letters, numbers, and hyphens; for example cars4less-nicosia.', 'bricks-child'));
        self::input($prefix, $scope, 'name', __('Dealer name', 'bricks-child'), $profile['name']);
        self::input($prefix, $scope, 'dealer_url', __('Bazaraki dealer URL', 'bricks-child'), $profile['dealer_url'], 'url');
        self::ownerInput($prefix, $scope, (int) $profile['author_id']);
        self::locationPicker($prefix, $scope, $profile);
        self::input($prefix, $scope, 'missing_confirmations', __('Missing runs before expiry', 'bricks-child'), $profile['missing_confirmations'], 'number');
        self::input($prefix, $scope, 'max_missing_ratio', __('Maximum one-run missing ratio', 'bricks-child'), $profile['max_missing_ratio'], 'number');
        self::input($prefix, $scope, 'delay_ms', __('Delay between listings (ms)', 'bricks-child'), $profile['delay_ms'], 'number');
        self::input($prefix, $scope, 'max_images', __('Maximum images per car', 'bricks-child'), $profile['max_images'], 'number');
        self::modeInputs($scope, $profile, true);
        echo '</div>';
        submit_button(__('Add dealer profile', 'bricks-child'));
        echo '</form></details>';
    }

    private static function input(string $prefix, string $scope, string $key, string $label, $value, string $type = 'text', bool $readonly = false, string $description = ''): void
    {
        $step = $type === 'number' ? ' step="any"' : '';
        echo '<div class="autoagora-sync-field"><label for="' . esc_attr($prefix . '-' . $key) . '">' . esc_html($label) . '</label>';
        echo '<input class="regular-text" id="' . esc_attr($prefix . '-' . $key) . '" name="' . esc_attr($scope . '[' . $key . ']') . '" type="' . esc_attr($type) . '" value="' . esc_attr((string) $value) . '"' . $step . ($readonly ? ' readonly' : '') . '>';
        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    private static function ownerInput(string $prefix, string $scope, int $author_id): void
    {
        echo '<div class="autoagora-sync-field"><label for="' . esc_attr($prefix . '-author') . '">' . esc_html__('Car owner', 'bricks-child') . '</label>';
        wp_dropdown_users(array('name' => $scope . '[author_id]', 'id' => $prefix . '-author', 'selected' => $author_id));
        echo '</div>';
    }

    /** @param array<string,mixed> $profile */
    private static function locationPicker(string $prefix, string $scope, array $profile): void
    {
        $defaults = AutoAgora_Bazaraki_Sync_Profiles::defaults($profile);
        $has_location = !empty($defaults);
        $summary_id = $prefix . '-location-summary';
        echo '<div class="autoagora-sync-field autoagora-sync-field-wide"><label>' . esc_html__('Default location', 'bricks-child') . '</label>';
        echo '<div class="autoagora-sync-location-picker" data-location-picker>';
        echo '<p id="' . esc_attr($summary_id) . '" class="autoagora-sync-location-summary' . ($has_location ? ' has-location' : '') . '">';
        echo esc_html($has_location ? (string) $defaults['car_address'] : __('No default location selected.', 'bricks-child'));
        echo '</p><div class="autoagora-sync-location-actions">';
        echo '<button type="button" class="button button-secondary" data-choose-location aria-describedby="' . esc_attr($summary_id) . '">' . esc_html($has_location ? __('Change location', 'bricks-child') : __('Choose location', 'bricks-child')) . '</button> ';
        echo '<button type="button" class="button button-link-delete" data-clear-location' . ($has_location ? '' : ' hidden') . '>' . esc_html__('Clear location', 'bricks-child') . '</button></div>';
        foreach (array('car_city', 'car_district', 'car_address', 'car_latitude', 'car_longitude') as $key) {
            $value = $profile[$key] ?? '';
            echo '<input type="hidden" name="' . esc_attr($scope . '[' . $key . ']') . '" value="' . esc_attr((string) $value) . '" data-location-field="' . esc_attr($key) . '">';
        }
        echo '</div><p class="description">' . esc_html__('Used only when a Bazaraki listing omits location data.', 'bricks-child') . '</p></div>';
    }

    /** @param array<string,mixed> $profile */
    private static function modeInputs(string $scope, array $profile, bool $show_include): void
    {
        echo '<div class="autoagora-sync-field autoagora-sync-mode"><span class="autoagora-sync-label">' . esc_html__('Mode', 'bricks-child') . '</span>';
        if ($show_include) {
            echo '<input type="hidden" name="' . esc_attr($scope . '[include_in_run]') . '" value="0">';
            echo '<label><input type="checkbox" name="' . esc_attr($scope . '[include_in_run]') . '" value="1" ' . checked(!empty($profile['include_in_run']), true, false) . '> ' . esc_html__('Include in run', 'bricks-child') . '</label>';
        }
        echo '<input type="hidden" name="' . esc_attr($scope . '[enabled]') . '" value="0"><label><input type="checkbox" name="' . esc_attr($scope . '[enabled]') . '" value="1" ' . checked(!empty($profile['enabled']), true, false) . '> ' . esc_html__('Enabled', 'bricks-child') . '</label>';
        echo '<input type="hidden" name="' . esc_attr($scope . '[dry_run]') . '" value="0"><label><input type="checkbox" name="' . esc_attr($scope . '[dry_run]') . '" value="1" ' . checked(!empty($profile['dry_run']), true, false) . '> ' . esc_html__('Dry run (validate only)', 'bricks-child') . '</label></div>';
    }

    /** @param array<int,array<string,mixed>> $runs */
    private static function runsTable(array $runs): void
    {
        echo '<details class="autoagora-sync-runs"><summary><strong>' . esc_html__('Recent runs', 'bricks-child') . '</strong><span>' . esc_html(sprintf(_n('%d recorded run', '%d recorded runs', count($runs), 'bricks-child'), count($runs))) . '</span></summary>';
        echo '<div class="autoagora-sync-runs-scroll"><table class="widefat striped"><thead><tr>';
        foreach (array(__('Profile', 'bricks-child'), __('Mode', 'bricks-child'), __('Status', 'bricks-child'), __('Results', 'bricks-child'), __('Source', 'bricks-child'), __('Started', 'bricks-child')) as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';
        if (empty($runs)) {
            echo '<tr><td colspan="6">' . esc_html__('No sync runs received yet.', 'bricks-child') . '</td></tr>';
        }
        foreach (array_slice($runs, 0, 20) as $run) {
            $results = sprintf(__('Successful %1$d · Review %2$d · Failed %3$d', 'bricks-child'), (int) $run['success_count'], (int) $run['review_count'], (int) $run['failed_count']);
            echo '<tr><td><strong>' . esc_html($run['profile_id']) . '</strong><br><code>' . esc_html($run['run_id']) . '</code></td><td>' . esc_html(!empty($run['dry_run']) ? __('Dry run', 'bricks-child') : __('Live', 'bricks-child')) . '</td><td>' . esc_html(self::statusLabel((string) $run['status'])) . '</td><td>' . esc_html($results) . '</td><td>' . (int) $run['source_count'] . '</td><td>' . esc_html($run['created_at']) . '</td></tr>';
        }
        echo '</tbody></table></div></details>';
    }

    /** @param array<int,array<string,mixed>> $runs @return array<string,array<string,mixed>> */
    private static function latestRunsByProfile(array $runs): array
    {
        $latest = array();
        foreach ($runs as $run) {
            $profile_id = (string) ($run['profile_id'] ?? '');
            if ($profile_id !== '' && !isset($latest[$profile_id])) {
                $latest[$profile_id] = $run;
            }
        }
        return $latest;
    }

    /** @param array<string,mixed> $profile */
    private static function profileError(array $profile): string
    {
        $host = strtolower((string) wp_parse_url($profile['dealer_url'], PHP_URL_HOST));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/', (string) $profile['id'])) {
            return __('Enter a valid profile ID.', 'bricks-child');
        }
        if ($host !== 'bazaraki.com' && !str_ends_with($host, '.bazaraki.com')) {
            return __('Enter a valid Bazaraki dealer URL.', 'bricks-child');
        }
        if (!get_userdata((int) $profile['author_id'])) {
            return __('Choose a valid car owner.', 'bricks-child');
        }
        return '';
    }

    private static function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    private static function renderNotices(): void
    {
        if (isset($_GET['saved_all'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('All profile changes were saved.', 'bricks-child') . '</p></div>';
        } elseif (isset($_GET['added'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Dealer profile added.', 'bricks-child') . '</p></div>';
        } elseif (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Sync profile deleted.', 'bricks-child') . '</p></div>';
        }

        $notice_key = 'autoagora_bazaraki_sync_notice_' . get_current_user_id();
        $notice = get_transient($notice_key);
        if (is_array($notice) && !empty($notice['message'])) {
            delete_transient($notice_key);
            $type = ($notice['type'] ?? '') === 'error' ? 'error' : 'warning';
            echo '<div class="notice notice-' . esc_attr($type) . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
        }
    }

    private static function setNotice(string $type, string $message): void
    {
        set_transient('autoagora_bazaraki_sync_notice_' . get_current_user_id(), array(
            'type' => $type,
            'message' => $message,
        ), MINUTE_IN_SECONDS);
    }

    /** @param array<string,int> $args */
    private static function redirect(array $args): void
    {
        wp_safe_redirect(add_query_arg(array_merge(array('page' => self::PAGE), $args), admin_url('tools.php')));
        exit;
    }

    private static function requirePermission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to configure sync.', 'bricks-child'));
        }
    }
}
