<?php
/** Minimal operational UI; scraping and scheduling remain server-side. */

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
            __('Bazaraki Sync', 'bricks-child'), __('Bazaraki Sync', 'bricks-child'),
            'manage_options', self::PAGE, array(__CLASS__, 'render')
        );
    }

    public static function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to configure sync.', 'bricks-child'));
        }
        check_admin_referer('autoagora_save_bazaraki_sync_profile');
        $profile = AutoAgora_Bazaraki_Sync_Profiles::sanitize(array_map('wp_unslash', (array) ($_POST['profile'] ?? array())));
        $original_id = sanitize_key((string) wp_unslash($_POST['original_id'] ?? ''));
        $host = strtolower((string) wp_parse_url($profile['dealer_url'], PHP_URL_HOST));
        if ($original_id !== '') {
            $profile['id'] = $original_id;
        }
        if (
            !preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/', $profile['id']) ||
            ($host !== 'bazaraki.com' && !str_ends_with($host, '.bazaraki.com')) ||
            !get_userdata((int) $profile['author_id'])
        ) {
            wp_die(esc_html__('Enter a valid profile ID and Bazaraki URL.', 'bricks-child'));
        }
        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        if ($original_id === '' && isset($profiles[$profile['id']])) {
            wp_die(esc_html__('A sync profile with that ID already exists.', 'bricks-child'));
        }
        $profiles[$profile['id']] = $profile;
        AutoAgora_Bazaraki_Sync_Profiles::save(array_values($profiles));
        wp_safe_redirect(add_query_arg(array('page' => self::PAGE, 'saved' => 1), admin_url('tools.php')));
        exit;
    }

    public static function delete(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to configure sync.', 'bricks-child'));
        }
        $profile_id = sanitize_key((string) wp_unslash($_POST['profile_id'] ?? ''));
        check_admin_referer('autoagora_delete_bazaraki_sync_profile_' . $profile_id);
        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        if ($profile_id !== '' && isset($profiles[$profile_id])) {
            unset($profiles[$profile_id]);
            AutoAgora_Bazaraki_Sync_Profiles::save(array_values($profiles));
        }
        wp_safe_redirect(add_query_arg(array('page' => self::PAGE, 'deleted' => 1), admin_url('tools.php')));
        exit;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view sync.', 'bricks-child'));
        }
        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        echo '<div class="wrap"><h1>' . esc_html__('Bazaraki dealer sync', 'bricks-child') . '</h1>';
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Sync profile saved.', 'bricks-child') . '</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Sync profile deleted.', 'bricks-child') . '</p></div>';
        }
        $secret_ready = defined('AUTOAGORA_BAZARAKI_SYNC_SECRET') && strlen((string) AUTOAGORA_BAZARAKI_SYNC_SECRET) >= 32;
        echo '<p><strong>' . esc_html__('API authentication:', 'bricks-child') . '</strong> ' . esc_html($secret_ready ? __('configured', 'bricks-child') : __('not configured — add AUTOAGORA_BAZARAKI_SYNC_SECRET (32+ characters) to wp-config.php or the server environment.', 'bricks-child')) . '</p>';
        echo '<p>' . esc_html__('The scheduled worker securely loads every enabled profile from this page, then validates, queues, and applies each dealer separately.', 'bricks-child') . '</p>';
        foreach ($profiles as $profile) {
            self::profileForm($profile, false);
        }
        $new_defaults = array(
            'id' => '', 'name' => '', 'dealer_url' => '', 'author_id' => get_current_user_id(),
            'enabled' => false, 'dry_run' => true, 'missing_confirmations' => 3,
            'delay_ms' => 3500, 'max_images' => 40, 'max_missing_ratio' => 0.35,
            'car_city' => '', 'car_district' => '', 'car_address' => '',
            'car_latitude' => null, 'car_longitude' => null,
        );
        if (empty($profiles)) {
            $new_defaults = array_merge($new_defaults, array(
                'id' => 'auto-cyprus-8598735', 'name' => 'Auto.Cyprus',
                'dealer_url' => 'https://www.bazaraki.com/items/author/8598735/?lat=34.6657927&lng=33.0034017&radius=5000',
            ));
        }
        $new_profile = AutoAgora_Bazaraki_Sync_Profiles::sanitize($new_defaults);
        self::profileForm($new_profile, true);
        self::runsTable();
        echo '</div>';
    }

    /** @param array<string,mixed> $profile */
    private static function profileForm(array $profile, bool $is_new): void
    {
        $prefix = 'sync-profile-' . ($is_new ? 'new' : sanitize_html_class($profile['id']));
        echo '<hr><h2>' . esc_html($is_new ? __('Add dealer profile', 'bricks-child') : ($profile['name'] ?: $profile['id'])) . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('autoagora_save_bazaraki_sync_profile');
        echo '<input type="hidden" name="action" value="autoagora_save_bazaraki_sync_profile"><input type="hidden" name="original_id" value="' . esc_attr($is_new ? '' : $profile['id']) . '"><table class="form-table" role="presentation">';
        self::input($prefix, 'id', __('Profile ID', 'bricks-child'), $profile['id'], 'text', !$is_new, __('Lowercase letters, numbers, and hyphens; for example cars4less-nicosia.', 'bricks-child'));
        self::input($prefix, 'name', __('Dealer name', 'bricks-child'), $profile['name']);
        self::input($prefix, 'dealer_url', __('Bazaraki dealer URL', 'bricks-child'), $profile['dealer_url'], 'url');
        echo '<tr><th><label for="' . esc_attr($prefix . '-author') . '">' . esc_html__('Car owner', 'bricks-child') . '</label></th><td>';
        wp_dropdown_users(array('name' => 'profile[author_id]', 'id' => $prefix . '-author', 'selected' => (int) $profile['author_id']));
        echo '</td></tr>';
        self::locationPicker($prefix, $profile);
        self::input($prefix, 'missing_confirmations', __('Missing runs before expiry', 'bricks-child'), $profile['missing_confirmations'], 'number');
        self::input($prefix, 'max_missing_ratio', __('Maximum one-run missing ratio', 'bricks-child'), $profile['max_missing_ratio'], 'number');
        self::input($prefix, 'delay_ms', __('Delay between listings (ms)', 'bricks-child'), $profile['delay_ms'], 'number');
        self::input($prefix, 'max_images', __('Maximum images per car', 'bricks-child'), $profile['max_images'], 'number');
        echo '<tr><th>' . esc_html__('Mode', 'bricks-child') . '</th><td>';
        echo '<label><input type="checkbox" name="profile[enabled]" value="1" ' . checked(!empty($profile['enabled']), true, false) . '> ' . esc_html__('Enabled', 'bricks-child') . '</label><br>';
        echo '<label><input type="checkbox" name="profile[dry_run]" value="1" ' . checked(!empty($profile['dry_run']), true, false) . '> ' . esc_html__('Dry run (validate and report without changing cars)', 'bricks-child') . '</label>';
        echo '</td></tr></table>';
        submit_button($is_new ? __('Add dealer profile', 'bricks-child') : __('Save sync profile', 'bricks-child'));
        echo '</form>';
        if (!$is_new) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Delete this sync profile? Existing cars will not be deleted.', 'bricks-child')) . '\');">';
            wp_nonce_field('autoagora_delete_bazaraki_sync_profile_' . $profile['id']);
            echo '<input type="hidden" name="action" value="autoagora_delete_bazaraki_sync_profile"><input type="hidden" name="profile_id" value="' . esc_attr($profile['id']) . '">';
            submit_button(__('Delete profile', 'bricks-child'), 'delete', 'submit', false);
            echo '</form>';
        }
    }

    private static function input(string $prefix, string $key, string $label, $value, string $type = 'text', bool $readonly = false, string $description = ''): void
    {
        $step = $type === 'number' ? ' step="any"' : '';
        echo '<tr><th><label for="' . esc_attr($prefix . '-' . $key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="' . esc_attr($prefix . '-' . $key) . '" name="profile[' . esc_attr($key) . ']" type="' . esc_attr($type) . '" value="' . esc_attr((string) $value) . '"' . $step . ($readonly ? ' readonly' : '') . '>';
        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</td></tr>';
    }

    /** @param array<string,mixed> $profile */
    private static function locationPicker(string $prefix, array $profile): void
    {
        $defaults = AutoAgora_Bazaraki_Sync_Profiles::defaults($profile);
        $has_location = !empty($defaults);
        $summary_id = $prefix . '-location-summary';
        echo '<tr><th>' . esc_html__('Default location', 'bricks-child') . '</th><td>';
        echo '<div class="autoagora-sync-location-picker" data-location-picker>';
        echo '<p id="' . esc_attr($summary_id) . '" class="autoagora-sync-location-summary' . ($has_location ? ' has-location' : '') . '">';
        echo esc_html($has_location ? (string) $defaults['car_address'] : __('No default location selected.', 'bricks-child'));
        echo '</p><div class="autoagora-sync-location-actions">';
        echo '<button type="button" class="button button-secondary" data-choose-location aria-describedby="' . esc_attr($summary_id) . '">';
        echo esc_html($has_location ? __('Change location', 'bricks-child') : __('Choose location', 'bricks-child'));
        echo '</button> ';
        echo '<button type="button" class="button button-link-delete" data-clear-location' . ($has_location ? '' : ' hidden') . '>' . esc_html__('Clear location', 'bricks-child') . '</button>';
        echo '</div>';
        foreach (array('car_city', 'car_district', 'car_address', 'car_latitude', 'car_longitude') as $key) {
            $value = $profile[$key] ?? '';
            echo '<input type="hidden" name="profile[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" data-location-field="' . esc_attr($key) . '">';
        }
        echo '</div><p class="description">' . esc_html__('Used only when a Bazaraki listing omits location data. Choose the point on the map; the address and coordinates are saved automatically.', 'bricks-child') . '</p>';
        echo '</td></tr>';
    }

    private static function runsTable(): void
    {
        echo '<hr><h2>' . esc_html__('Recent runs', 'bricks-child') . '</h2><table class="widefat striped"><thead><tr>';
        foreach (array('Run', 'Profile', 'Mode', 'Status', 'Source', 'Successful', 'Review', 'Failed', 'Started') as $heading) {
            echo '<th>' . esc_html__($heading, 'bricks-child') . '</th>';
        }
        echo '</tr></thead><tbody>';
        $runs = AutoAgora_Bazaraki_Sync_Queue::recentRuns();
        if (empty($runs)) {
            echo '<tr><td colspan="9">' . esc_html__('No sync runs received yet.', 'bricks-child') . '</td></tr>';
        }
        foreach ($runs as $run) {
            echo '<tr><td><code>' . esc_html($run['run_id']) . '</code></td><td>' . esc_html($run['profile_id']) . '</td><td>' . esc_html(!empty($run['dry_run']) ? __('Dry run', 'bricks-child') : __('Live', 'bricks-child')) . '</td><td>' . esc_html($run['status']) . '</td><td>' . (int) $run['source_count'] . '</td><td>' . (int) $run['success_count'] . '</td><td>' . (int) $run['review_count'] . '</td><td>' . (int) $run['failed_count'] . '</td><td>' . esc_html($run['created_at']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
}
