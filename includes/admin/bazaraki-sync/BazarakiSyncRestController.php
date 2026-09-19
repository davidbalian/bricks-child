<?php
/** Signed REST interface used by the server-side browser worker. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Bazaraki_Sync_REST_Controller
{
    private const MAX_PACKAGE_BYTES = 256 * 1024 * 1024;

    public static function register(): void
    {
        add_action('rest_api_init', static function (): void {
            register_rest_route('autoagora/v1', '/bazaraki-sync/inventory', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'inventory'),
                'permission_callback' => '__return_true',
            ));
            register_rest_route('autoagora/v1', '/bazaraki-sync/ingest', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'ingest'),
                'permission_callback' => '__return_true',
            ));
            register_rest_route('autoagora/v1', '/bazaraki-sync/process', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'process'),
                'permission_callback' => '__return_true',
            ));
            register_rest_route('autoagora/v1', '/bazaraki-sync/report', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'report'),
                'permission_callback' => '__return_true',
            ));
            register_rest_route('autoagora/v1', '/bazaraki-sync/profiles', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'profiles'),
                'permission_callback' => '__return_true',
            ));
        });
    }

    public static function ingest(WP_REST_Request $request)
    {
        $body = (string) $request->get_body();
        $auth = AutoAgora_Bazaraki_Sync_Auth::verify($request, $body);
        if (is_wp_error($auth)) {
            return $auth;
        }
        if ($body === '' || strlen($body) > self::MAX_PACKAGE_BYTES || substr($body, 0, 2) !== 'PK') {
            return new WP_Error('bazaraki_sync_package', __('The sync package is empty, too large, or not a ZIP.', 'bricks-child'), array('status' => 400));
        }
        $profile_id = sanitize_key((string) $request->get_header('x-autoagora-profile'));
        $run_id = sanitize_text_field((string) $request->get_header('x-autoagora-run'));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/', $profile_id) || !preg_match('/^[A-Za-z0-9._-]{8,64}$/', $run_id)) {
            return new WP_Error('bazaraki_sync_identity', __('Invalid sync profile or run ID.', 'bricks-child'), array('status' => 400));
        }
        $profile = AutoAgora_Bazaraki_Sync_Profiles::get($profile_id);
        if (!$profile || empty($profile['enabled']) || !get_userdata((int) ($profile['author_id'] ?? 0))) {
            return new WP_Error('bazaraki_sync_profile', __('The sync profile is missing, disabled, or has no valid owner.', 'bricks-child'), array('status' => 409));
        }
        $existing = AutoAgora_Bazaraki_Sync_Queue::run($run_id, $profile_id);
        if ($existing) {
            $job_counts = AutoAgora_Bazaraki_Sync_Queue::statusCounts($run_id);
            return rest_ensure_response(array(
                'accepted' => true,
                'duplicate' => true,
                'run_id' => $run_id,
                'queued' => $job_counts['pending'],
                'source_count' => (int) $existing['source_count'],
                'dry_run' => !empty($existing['dry_run']),
            ));
        }

        $package_path = self::storePackage($profile_id, $run_id, $body);
        unset($body);
        if (is_wp_error($package_path)) {
            return $package_path;
        }
        $prepared = self::preparePackage($package_path, $profile_id, $run_id, $profile);
        if (is_wp_error($prepared)) {
            @unlink($package_path);
            return $prepared;
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $created = AutoAgora_Bazaraki_Sync_Queue::createRun(
            $run_id, $profile_id, $package_path, (array) $prepared['counts'], count($prepared['present_source_ids']),
            !empty($profile['dry_run']), !empty($prepared['suppress_summary'])
        );
        if (is_wp_error($created)) {
            $wpdb->query('ROLLBACK');
            @unlink($package_path);
            return $created;
        }
        foreach ($prepared['jobs'] as $job) {
            if (!AutoAgora_Bazaraki_Sync_Queue::addJob($run_id, $profile_id, $job['source_id'], $job['action'], $job['payload'])) {
                $wpdb->query('ROLLBACK');
                @unlink($package_path);
                return new WP_Error('bazaraki_sync_queue_store', __('Could not queue every sync job.', 'bricks-child'), array('status' => 500));
            }
        }
        $wpdb->query('COMMIT');
        return rest_ensure_response(array(
            'accepted' => true,
            'run_id' => $run_id,
            'queued' => count($prepared['jobs']),
            'source_count' => count($prepared['present_source_ids']),
            'dry_run' => !empty($profile['dry_run']),
        ));
    }

    public static function process(WP_REST_Request $request)
    {
        $body = (string) $request->get_body();
        $auth = AutoAgora_Bazaraki_Sync_Auth::verify($request, $body);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $input = json_decode($body, true);
        if (!is_array($input)) {
            return new WP_Error('bazaraki_sync_json', __('Invalid processor request.', 'bricks-child'), array('status' => 400));
        }
        $run_id = sanitize_text_field((string) ($input['run_id'] ?? ''));
        $profile_id = sanitize_key((string) ($input['profile_id'] ?? ''));
        $result = AutoAgora_Bazaraki_Sync_Processor::process($run_id, $profile_id, absint($input['limit'] ?? 2));
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function report(WP_REST_Request $request)
    {
        $body = (string) $request->get_body();
        $auth = AutoAgora_Bazaraki_Sync_Auth::verify($request, $body);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $input = json_decode($body, true);
        if (!is_array($input)) {
            return new WP_Error('bazaraki_sync_report', __('Invalid sync failure report.', 'bricks-child'), array('status' => 400));
        }
        $profile_id = sanitize_key((string) ($input['profile_id'] ?? ''));
        $run_id = sanitize_text_field((string) ($input['run_id'] ?? ''));
        $message = sanitize_text_field((string) ($input['error'] ?? ''));
        $profile = AutoAgora_Bazaraki_Sync_Profiles::get($profile_id);
        if (!$profile || empty($profile['enabled']) || !preg_match('/^[A-Za-z0-9._-]{8,64}$/', $run_id) || $message === '') {
            return new WP_Error('bazaraki_sync_report', __('Invalid sync failure report.', 'bricks-child'), array('status' => 400));
        }
        $message = substr($message, 0, 1000);
        if (AutoAgora_Bazaraki_Sync_Queue::run($run_id, $profile_id)) {
            return rest_ensure_response(array('recorded' => true, 'duplicate' => true));
        }
        if (!AutoAgora_Bazaraki_Sync_Queue::recordFailure($run_id, $profile_id, $message)) {
            return new WP_Error('bazaraki_sync_report_store', __('Could not store sync failure.', 'bricks-child'), array('status' => 500));
        }
        $recipient = sanitize_email((string) get_option('admin_email'));
        if ($recipient !== '') {
            $subject = sprintf('[%s] Bazaraki sync failed: %s', wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES), $profile_id);
            $text = sprintf("Bazaraki sync failed\nProfile: %s\nRun: %s\nError: %s", $profile_id, $run_id, $message);
            function_exists('send_app_email')
                ? send_app_email($recipient, $subject, nl2br(esc_html($text)), $text)
                : wp_mail($recipient, $subject, $text);
        }
        return rest_ensure_response(array('recorded' => true));
    }

    public static function profiles(WP_REST_Request $request)
    {
        $body = (string) $request->get_body();
        $auth = AutoAgora_Bazaraki_Sync_Auth::verify($request, $body);
        if (is_wp_error($auth)) {
            return $auth;
        }
        return rest_ensure_response(array(
            'schema_version' => 1,
            'profiles' => AutoAgora_Bazaraki_Sync_Profiles::enabledForWorker(),
        ));
    }

    /** Authenticated, owner-scoped inventory; includes pending/manual imports. */
    public static function inventory(WP_REST_Request $request)
    {
        $body = (string) $request->get_body();
        $auth = AutoAgora_Bazaraki_Sync_Auth::verify($request, $body);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $input = json_decode($body, true);
        $profile = AutoAgora_Bazaraki_Sync_Profiles::get(sanitize_key((string) ($input['profile_id'] ?? '')));
        if (!$profile || empty($profile['enabled']) || !get_userdata((int) $profile['author_id'])) {
            return new WP_Error('bazaraki_sync_profile', 'Invalid or disabled dealer profile.', array('status' => 409));
        }
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='car'
             AND p.post_status IN ('publish','pending','draft','private','future')
             AND p.post_author=%d AND p.ID>%d
             AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_autoagora_import_source' AND m.meta_value='bazaraki')
             ORDER BY p.ID ASC LIMIT 501",
            (int) $profile['author_id'], absint($input['cursor'] ?? 0)
        ));
        if ($wpdb->last_error) {
            return new WP_Error('bazaraki_sync_inventory', 'Could not read inventory.', array('status' => 500));
        }
        $more = count($ids) > 500;
        $ids = array_slice($ids, 0, 500);
        update_meta_cache('post', $ids);
        $listings = array();
        foreach ($ids as $post_id) {
            $source_id = (string) get_post_meta((int) $post_id, '_autoagora_import_source_id', true);
            if ($source_id !== '') {
                $price = get_post_meta((int) $post_id, 'price', true);
                $listings[] = array('source_id' => $source_id, 'price' => is_numeric($price) ? (float) $price : null);
            }
        }
        return rest_ensure_response(array('schema_version' => 2, 'listings' => $listings,
            'next_cursor' => $more ? (int) end($ids) : null, 'dry_run' => !empty($profile['dry_run'])));
    }

    /** @return string|WP_Error */
    private static function storePackage(string $profile_id, string $run_id, string $body)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('bazaraki_sync_storage', (string) $uploads['error'], array('status' => 500));
        }
        $base = wp_normalize_path(trailingslashit($uploads['basedir']) . 'autoagora-bazaraki-sync/');
        $directory = wp_normalize_path($base . $profile_id . '/');
        if (!str_starts_with($directory, $base) || (!is_dir($directory) && !wp_mkdir_p($directory))) {
            return new WP_Error('bazaraki_sync_storage', __('Could not create secure sync storage.', 'bricks-child'), array('status' => 500));
        }
        foreach (array('.htaccess' => "Require all denied\nDeny from all\n", 'index.php' => "<?php\n// Silence is golden.\n") as $name => $contents) {
            $guard = $directory . $name;
            if (!is_file($guard)) {
                file_put_contents($guard, $contents, LOCK_EX);
            }
        }
        try {
            $suffix = bin2hex(random_bytes(16));
        } catch (Throwable $error) {
            return new WP_Error('bazaraki_sync_storage', __('Could not generate a secure package filename.', 'bricks-child'), array('status' => 500));
        }
        $path = wp_normalize_path($directory . sanitize_file_name($run_id) . '-' . $suffix . '.zip');
        if (!str_starts_with($path, $directory) || file_put_contents($path, $body, LOCK_EX) !== strlen($body)) {
            return new WP_Error('bazaraki_sync_storage', __('Could not store the sync package.', 'bricks-child'), array('status' => 500));
        }
        return $path;
    }

    /** @param array<string,mixed> $profile @return array<string,mixed>|WP_Error */
    private static function preparePackage(string $path, string $profile_id, string $run_id, array $profile)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('bazaraki_sync_zip', __('PHP ZIP support is required.', 'bricks-child'), array('status' => 500));
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return new WP_Error('bazaraki_sync_zip', __('The sync ZIP could not be opened.', 'bricks-child'), array('status' => 400));
        }
        $raw = $zip->getFromName('changes.json');
        $zip->close();
        if (!is_string($raw) || strlen($raw) > AutoAgora_Car_Json_Import_Validator::MAX_MANIFEST_BYTES) {
            return new WP_Error('bazaraki_sync_manifest', __('changes.json is missing or too large.', 'bricks-child'), array('status' => 400));
        }
        $changes = json_decode($raw, true);
        if (!is_array($changes) || ($changes['profile_id'] ?? '') !== $profile_id || ($changes['run_id'] ?? '') !== $run_id) {
            return new WP_Error('bazaraki_sync_manifest', __('The sync manifest identity is invalid.', 'bricks-child'), array('status' => 400));
        }
        $present = array_values(array_unique(array_filter(array_map(static function ($value): string {
            return preg_replace('/[^A-Za-z0-9._-]/', '', (string) $value);
        }, (array) ($changes['present_source_ids'] ?? array())))));
        if (empty($present) || count($present) > 10000) {
            return new WP_Error('bazaraki_sync_presence', __('The complete source listing set is missing or unsafe.', 'bricks-child'), array('status' => 400));
        }

        $validation = AutoAgora_Car_Json_Import_Validator::validatePackage($path, AutoAgora_Bazaraki_Sync_Profiles::defaults($profile));
        if (is_wp_error($validation)) {
            return $validation;
        }
        $rows = array();
        $invalid = array();
        foreach ($validation['rows'] as $row) {
            $source_id = (string) ($row['listing']['source_id'] ?? '');
            if ($source_id === '' || empty($row['valid'])) {
                $message = implode(' ', (array) ($row['errors'] ?? array()));
                if (empty($changes['efficient']) || $source_id === '') {
                    return new WP_Error('bazaraki_sync_listing_invalid', $message ?: __('A changed listing failed validation.', 'bricks-child'), array('status' => 400));
                }
                $invalid[$source_id] = $message ?: 'Listing validation failed.';
                continue;
            }
            $rows[$source_id] = $row;
        }

        $jobs = array();
        $changed_ids = array();
        $chunked = !empty($changes['chunked']);
        $final_chunk = !$chunked || !empty($changes['final_chunk']);
        foreach (array('created', 'updated') as $bucket) {
            foreach ((array) ($changes[$bucket] ?? array()) as $change) {
                if (!is_array($change)) {
                    continue;
                }
                $source_id = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($change['source_id'] ?? ''));
                if (isset($invalid[$source_id])) {
                    $jobs[] = array('source_id' => $source_id, 'action' => 'reject', 'payload' => array('error' => $invalid[$source_id]));
                    $changed_ids[$source_id] = true;
                    continue;
                }
                if ($source_id === '' || !isset($rows[$source_id]) || !in_array($source_id, $present, true)) {
                    return new WP_Error('bazaraki_sync_change_invalid', __('A changed listing is absent from the validated package.', 'bricks-child'), array('status' => 400));
                }
                $listing = is_array($change['listing'] ?? null) ? $change['listing'] : array();
                $image_hashes = array_values((array) ($listing['sync_image_hashes'] ?? array()));
                if (
                    count($image_hashes) !== count((array) $rows[$source_id]['listing']['car_images']) ||
                    count(array_filter($image_hashes, static function ($hash): bool {
                        return is_string($hash) && preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
                    })) !== count($image_hashes)
                ) {
                    return new WP_Error('bazaraki_sync_image_hashes', __('A changed listing has invalid image hashes.', 'bricks-child'), array('status' => 400));
                }
                $jobs[] = array('source_id' => $source_id, 'action' => 'upsert', 'payload' => array(
                    'row' => $rows[$source_id],
                    'changed_fields' => array_values((array) ($change['changed_fields'] ?? array())),
                    'baseline' => ($changes['mode'] ?? '') === 'baseline',
                    'new_only' => !empty($changes['efficient']),
                    'image_hashes' => $image_hashes,
                    'source_image_urls' => array_values((array) ($listing['source_image_urls'] ?? array())),
                ));
                $changed_ids[$source_id] = true;
            }
        }
        foreach ((array) ($changes['price_updates'] ?? array()) as $change) {
            $source_id = (string) ($change['source_id'] ?? '');
            $price = $change['price'] ?? null;
            if (!in_array($source_id, $present, true) || isset($changed_ids[$source_id])) {
                return new WP_Error('bazaraki_sync_price', 'Invalid price update identity.', array('status' => 400));
            }
            $valid_price = is_numeric($price) && is_finite((float) $price) && (float) $price > 0 && (float) $price <= 100000000;
            $jobs[] = array('source_id' => $source_id, 'action' => $valid_price ? 'price' : 'reject',
                'payload' => $valid_price ? array('price' => (float) $price) : array('error' => 'Invalid card price.'));
            $changed_ids[$source_id] = true;
        }
        if ($final_chunk) {
            foreach ($present as $source_id) {
                if (!isset($changed_ids[$source_id])) {
                    $jobs[] = array('source_id' => $source_id, 'action' => 'seen', 'payload' => array());
                }
            }

            $profile_posts = get_posts(array(
                'post_type' => 'car', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
                'no_found_rows' => true, 'suppress_filters' => true,
                'meta_key' => '_autoagora_sync_profile_id', 'meta_value' => $profile_id,
            ));
            $missing = array_filter($profile_posts, static function ($post_id) use ($present): bool {
                return !in_array((string) get_post_meta((int) $post_id, '_autoagora_import_source_id', true), $present, true);
            });
            $allow_missing = empty($changes['efficient']) || (!empty($changes['allow_missing']) &&
                count($missing) / max(1, count($profile_posts)) <= (float) ($profile['max_missing_ratio'] ?? 0.35));
            foreach ($allow_missing ? $missing : array() as $post_id) {
                $source_id = (string) get_post_meta((int) $post_id, '_autoagora_import_source_id', true);
                if ($source_id !== '' && !in_array($source_id, $present, true)) {
                    $jobs[] = array('source_id' => $source_id, 'action' => 'missing', 'payload' => array());
                }
            }
        }
        return array(
            'jobs' => $jobs,
            'present_source_ids' => $present,
            'counts' => is_array($changes['counts'] ?? null) ? $changes['counts'] : array(),
            'suppress_summary' => $chunked && !$final_chunk,
        );
    }
}
