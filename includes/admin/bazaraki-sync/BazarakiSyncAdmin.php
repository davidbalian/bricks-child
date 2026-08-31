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
        add_action('admin_post_autoagora_save_bazaraki_sync_profile', array(__CLASS__, 'save'));
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
        if ($profile['id'] === '' || !preg_match('/^https:\/\/([^\/]+\.)?bazaraki\.com\//i', $profile['dealer_url'])) {
            wp_die(esc_html__('Enter a valid profile ID and Bazaraki URL.', 'bricks-child'));
        }
        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        $profiles[$profile['id']] = $profile;
        AutoAgora_Bazaraki_Sync_Profiles::save(array_values($profiles));
        wp_safe_redirect(add_query_arg(array('page' => self::PAGE, 'saved' => 1), admin_url('tools.php')));
        exit;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view sync.', 'bricks-child'));
        }
        $profiles = AutoAgora_Bazaraki_Sync_Profiles::all();
        if (empty($profiles)) {
            $profiles['auto-cyprus-8598735'] = AutoAgora_Bazaraki_Sync_Profiles::sanitize(array(
                'id' => 'auto-cyprus-8598735', 'name' => 'Auto.Cyprus',
                'dealer_url' => 'https://www.bazaraki.com/items/author/8598735/?lat=34.6657927&lng=33.0034017&radius=5000',
                'author_id' => get_current_user_id(), 'dry_run' => 1, 'missing_confirmations' => 3,
                'car_city' => 'Limassol', 'car_district' => 'Limassol district',
                'car_address' => 'Auto.Cyprus, Limassol', 'car_latitude' => 34.6657927, 'car_longitude' => 33.0034017,
            ));
        }
        echo '<div class="wrap"><h1>' . esc_html__('Bazaraki dealer sync', 'bricks-child') . '</h1>';
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Sync profile saved.', 'bricks-child') . '</p></div>';
        }
        $secret_ready = defined('AUTOAGORA_BAZARAKI_SYNC_SECRET') && strlen((string) AUTOAGORA_BAZARAKI_SYNC_SECRET) >= 32;
        echo '<p><strong>' . esc_html__('API authentication:', 'bricks-child') . '</strong> ' . esc_html($secret_ready ? __('configured', 'bricks-child') : __('not configured — add AUTOAGORA_BAZARAKI_SYNC_SECRET (32+ characters) to wp-config.php or the server environment.', 'bricks-child')) . '</p>';
        echo '<p>' . esc_html__('Run the separate Node worker from a real server cron. AutoAgora only validates, queues, and applies signed packages in batches of at most three cars.', 'bricks-child') . '</p>';
        foreach ($profiles as $profile) {
            self::profileForm($profile);
        }
        self::runsTable();
        echo '</div>';
    }

    /** @param array<string,mixed> $profile */
    private static function profileForm(array $profile): void
    {
        echo '<hr><h2>' . esc_html($profile['name'] ?: $profile['id']) . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('autoagora_save_bazaraki_sync_profile');
        echo '<input type="hidden" name="action" value="autoagora_save_bazaraki_sync_profile"><table class="form-table" role="presentation">';
        self::input('id', __('Profile ID', 'bricks-child'), $profile['id'], 'text', true);
        self::input('name', __('Dealer name', 'bricks-child'), $profile['name']);
        self::input('dealer_url', __('Bazaraki dealer URL', 'bricks-child'), $profile['dealer_url'], 'url');
        echo '<tr><th><label for="profile-author">' . esc_html__('Car owner', 'bricks-child') . '</label></th><td>';
        wp_dropdown_users(array('name' => 'profile[author_id]', 'id' => 'profile-author', 'selected' => (int) $profile['author_id']));
        echo '</td></tr>';
        self::input('missing_confirmations', __('Missing runs before expiry', 'bricks-child'), $profile['missing_confirmations'], 'number');
        foreach (array('car_city' => 'City', 'car_district' => 'District', 'car_address' => 'Address', 'car_latitude' => 'Latitude', 'car_longitude' => 'Longitude') as $key => $label) {
            self::input($key, __($label, 'bricks-child'), $profile[$key] ?? '', str_contains($key, 'latitude') || str_contains($key, 'longitude') ? 'number' : 'text');
        }
        echo '<tr><th>' . esc_html__('Mode', 'bricks-child') . '</th><td>';
        echo '<label><input type="checkbox" name="profile[enabled]" value="1" ' . checked(!empty($profile['enabled']), true, false) . '> ' . esc_html__('Enabled', 'bricks-child') . '</label><br>';
        echo '<label><input type="checkbox" name="profile[dry_run]" value="1" ' . checked(!empty($profile['dry_run']), true, false) . '> ' . esc_html__('Dry run (validate and report without changing cars)', 'bricks-child') . '</label>';
        echo '</td></tr></table>';
        submit_button(__('Save sync profile', 'bricks-child'));
        echo '</form>';
    }

    private static function input(string $key, string $label, $value, string $type = 'text', bool $readonly = false): void
    {
        $step = $type === 'number' ? ' step="any"' : '';
        echo '<tr><th><label for="profile-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="profile-' . esc_attr($key) . '" name="profile[' . esc_attr($key) . ']" type="' . esc_attr($type) . '" value="' . esc_attr((string) $value) . '"' . $step . ($readonly ? ' readonly' : '') . '></td></tr>';
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
