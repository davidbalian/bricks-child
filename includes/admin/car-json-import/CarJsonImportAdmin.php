<?php
/**
 * WordPress admin workflow for previewing and importing car JSON ZIP packages.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Car_Json_Import_Admin
{
    private const PAGE_SLUG = 'car-json-import';
    private const SESSION_TTL = DAY_IN_SECONDS;
    private const MAX_PACKAGE_BYTES = 512 * 1024 * 1024;

    public static function register(): void
    {
        add_action('admin_menu', array(__CLASS__, 'addMenu'));
    }

    public static function addMenu(): void
    {
        add_management_page(
            __('Car JSON Import', 'bricks-child'),
            __('Import Cars (JSON)', 'bricks-child'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'render')
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to import cars.', 'bricks-child'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = isset($_POST['car_json_import_action'])
                ? sanitize_key(wp_unslash($_POST['car_json_import_action']))
                : '';
            if ($action === 'upload') {
                self::handleUpload();
            } elseif ($action === 'confirm') {
                self::handleConfirm();
            } elseif ($action === 'process') {
                self::handleProcess();
            }
        }

        $token = self::requestToken();
        echo '<div class="wrap">';
        if (isset($_GET['car_json_error'])) {
            self::notice(sanitize_text_field(wp_unslash($_GET['car_json_error'])), 'error');
        }
        if ($token === '') {
            self::renderUpload();
        } else {
            $session = self::loadSession($token);
            if (is_wp_error($session)) {
                self::notice($session->get_error_message(), 'error');
                self::renderUpload();
            } else {
                self::renderSession($token, $session);
            }
        }
        echo '</div>';
    }

    private static function renderUpload(): void
    {
        echo '<h1>' . esc_html__('Import cars from JSON package', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html__('Upload a ZIP containing listings.json at the root and the referenced images/ folders. The importer validates every row before any posts are created.', 'bricks-child') . '</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('car_json_import_upload', 'car_json_import_nonce');
        echo '<input type="hidden" name="car_json_import_action" value="upload">';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="car_json_package">' . esc_html__('JSON + images ZIP', 'bricks-child') . '</label></th>';
        echo '<td><input type="file" id="car_json_package" name="car_json_package" accept=".zip,application/zip" required>';
        echo '<p class="description">' . esc_html(sprintf(__('Maximum package size: %s.', 'bricks-child'), size_format(self::maximumUploadBytes()))) . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="car_json_author">' . esc_html__('Post author', 'bricks-child') . '</label></th><td>';
        wp_dropdown_users(array(
            'name'     => 'car_json_author',
            'id'       => 'car_json_author',
            'selected' => get_current_user_id(),
        ));
        echo '<p class="description">' . esc_html__('Imported cars are created as pending listings owned by this user.', 'bricks-child') . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Fallback location', 'bricks-child') . '</th><td>';
        echo '<p class="description">' . esc_html__('Used only when a listing is missing a location value. Coordinates are required for import-ready rows.', 'bricks-child') . '</p>';
        self::locationInput('car_json_city', __('City', 'bricks-child'), '');
        self::locationInput('car_json_district', __('District', 'bricks-child'), '');
        self::locationInput('car_json_address', __('Address', 'bricks-child'), '');
        self::locationInput('car_json_latitude', __('Latitude', 'bricks-child'), '', 'number', 'any');
        self::locationInput('car_json_longitude', __('Longitude', 'bricks-child'), '', 'number', 'any');
        echo '</td></tr></table>';
        submit_button(__('Upload and validate', 'bricks-child'));
        echo '</form>';
    }

    private static function handleUpload(): void
    {
        check_admin_referer('car_json_import_upload', 'car_json_import_nonce');
        if (empty($_FILES['car_json_package']) || !is_array($_FILES['car_json_package'])) {
            self::redirectWithError(__('Choose a ZIP package to upload.', 'bricks-child'));
        }

        $upload = $_FILES['car_json_package'];
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::redirectWithError(__('The ZIP upload failed.', 'bricks-child'));
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size <= 0 || $size > self::maximumUploadBytes()) {
            self::redirectWithError(__('The ZIP is empty or exceeds the upload limit.', 'bricks-child'));
        }
        $name = sanitize_file_name((string) ($upload['name'] ?? ''));
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            self::redirectWithError(__('Only ZIP packages are accepted.', 'bricks-child'));
        }

        $author_id = isset($_POST['car_json_author']) ? absint($_POST['car_json_author']) : 0;
        if (!$author_id || !get_userdata($author_id)) {
            self::redirectWithError(__('Choose a valid post author.', 'bricks-child'));
        }
        $defaults = self::postedDefaults();
        $token = bin2hex(random_bytes(16));
        $directory = self::sessionDirectory($token, true);
        if (is_wp_error($directory)) {
            self::redirectWithError($directory->get_error_message());
        }
        $zip_path = trailingslashit($directory) . 'package.zip';
        if (!is_uploaded_file($upload['tmp_name']) || !move_uploaded_file($upload['tmp_name'], $zip_path)) {
            self::redirectWithError(__('The uploaded ZIP could not be stored securely.', 'bricks-child'));
        }

        $validation = AutoAgora_Car_Json_Import_Validator::validatePackage($zip_path, $defaults);
        if (is_wp_error($validation)) {
            @unlink($zip_path);
            self::redirectWithError($validation->get_error_message());
        }
        $session = array(
            'token'       => $token,
            'user_id'     => get_current_user_id(),
            'author_id'   => $author_id,
            'created_at'  => time(),
            'state'       => 'preview',
            'position'    => 0,
            'validation'  => $validation,
            'results'     => array(),
        );
        $saved = self::saveSession($token, $session);
        if (is_wp_error($saved)) {
            @unlink($zip_path);
            self::redirectWithError($saved->get_error_message());
        }
        self::redirectToToken($token);
    }

    private static function handleConfirm(): void
    {
        $token = self::requestToken();
        check_admin_referer('car_json_import_confirm_' . $token, 'car_json_import_nonce');
        if (empty($_POST['confirm_import'])) {
            self::redirectWithError(__('Confirm that you reviewed the preview.', 'bricks-child'), $token);
        }
        $session = self::loadSession($token);
        if (is_wp_error($session)) {
            self::redirectWithError($session->get_error_message());
        }
        if (($session['state'] ?? '') !== 'preview') {
            self::redirectWithError(__('This import session is not awaiting confirmation.', 'bricks-child'), $token);
        }
        if (empty($session['validation']['valid_count'])) {
            self::redirectWithError(__('There are no valid rows to import.', 'bricks-child'), $token);
        }
        $session['state'] = 'importing';
        $session['position'] = 0;
        $session['results'] = array();
        $saved = self::saveSession($token, $session);
        if (is_wp_error($saved)) {
            self::redirectWithError($saved->get_error_message(), $token);
        }
        self::redirectToToken($token);
    }

    private static function handleProcess(): void
    {
        $token = self::requestToken();
        check_admin_referer('car_json_import_process_' . $token, 'car_json_import_nonce');
        $session = self::loadSession($token);
        if (is_wp_error($session)) {
            self::redirectWithError($session->get_error_message());
        }
        if (($session['state'] ?? '') !== 'importing') {
            self::redirectToToken($token);
        }

        $rows = (array) ($session['validation']['rows'] ?? array());
        $position = (int) ($session['position'] ?? 0);
        $processed_valid_row = false;
        while ($position < count($rows) && !$processed_valid_row) {
            $row = $rows[$position];
            $position++;
            if (empty($row['valid'])) {
                $session['results'][] = array(
                    'index'   => $row['index'] ?? ($position - 1),
                    'status'  => 'invalid',
                    'message' => implode(' ', (array) ($row['errors'] ?? array())),
                );
                continue;
            }

            $directory = self::sessionDirectory($token, false);
            if (is_wp_error($directory)) {
                self::redirectWithError($directory->get_error_message(), $token);
            }
            $result = AutoAgora_Car_Json_Import_Runner::importRow(
                $row,
                trailingslashit($directory) . 'package.zip',
                (int) $session['author_id']
            );
            if (is_wp_error($result)) {
                $session['results'][] = array(
                    'index'     => $row['index'] ?? ($position - 1),
                    'source_id' => $row['listing']['source_id'] ?? '',
                    'status'    => 'failed',
                    'message'   => $result->get_error_message(),
                );
            } else {
                $session['results'][] = array_merge(array(
                    'index'     => $row['index'] ?? ($position - 1),
                    'source_id' => $row['listing']['source_id'] ?? '',
                ), $result);
            }
            $processed_valid_row = true;
        }

        $session['position'] = $position;
        if ($position >= count($rows)) {
            $session['state'] = 'complete';
            $session['completed_at'] = time();
        }
        $saved = self::saveSession($token, $session);
        if (is_wp_error($saved)) {
            self::redirectWithError($saved->get_error_message(), $token);
        }
        if (($session['state'] ?? '') === 'complete') {
            $directory = self::sessionDirectory($token, false);
            if (!is_wp_error($directory)) {
                $package = trailingslashit($directory) . 'package.zip';
                if (is_file($package)) {
                    @unlink($package);
                }
            }
        }
        self::redirectToToken($token);
    }

    /** @param array<string,mixed> $session */
    private static function renderSession(string $token, array $session): void
    {
        $state = (string) ($session['state'] ?? 'preview');
        if ($state === 'preview') {
            self::renderPreview($token, $session);
        } elseif ($state === 'importing') {
            self::renderImporting($token, $session);
        } else {
            self::renderComplete($session);
        }
    }

    /** @param array<string,mixed> $session */
    private static function renderPreview(string $token, array $session): void
    {
        $validation = $session['validation'];
        $rows = (array) ($validation['rows'] ?? array());
        echo '<h1>' . esc_html__('Preview car JSON import', 'bricks-child') . '</h1>';
        echo '<p><strong>' . esc_html(sprintf(
            __('%1$d valid, %2$d invalid, %3$d total rows.', 'bricks-child'),
            (int) ($validation['valid_count'] ?? 0),
            (int) ($validation['invalid_count'] ?? 0),
            count($rows)
        )) . '</strong></p>';
        echo '<p>' . esc_html__('Only valid rows will be imported. Every created car remains pending for review.', 'bricks-child') . '</p>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Row', 'bricks-child') . '</th><th>' . esc_html__('Car', 'bricks-child') . '</th>';
        echo '<th>' . esc_html__('Price', 'bricks-child') . '</th><th>' . esc_html__('Images', 'bricks-child') . '</th>';
        echo '<th>' . esc_html__('Validation', 'bricks-child') . '</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $listing = (array) ($row['listing'] ?? array());
            $car = trim(sprintf('%s %s %s', $listing['year'] ?? '', $listing['make'] ?? '', $listing['model'] ?? ''));
            echo '<tr><td>' . esc_html((string) ((int) ($row['index'] ?? 0) + 1)) . '</td>';
            echo '<td><strong>' . esc_html($car) . '</strong><br><code>' . esc_html((string) ($listing['source_id'] ?? '')) . '</code></td>';
            echo '<td>' . esc_html(number_format_i18n((int) ($listing['price'] ?? 0))) . '</td>';
            echo '<td>' . esc_html((string) count((array) ($listing['car_images'] ?? array()))) . '</td><td>';
            if (!empty($row['valid'])) {
                echo '<span style="color:#008a20">' . esc_html__('Valid', 'bricks-child') . '</span>';
            } else {
                echo '<span style="color:#b32d2e">' . esc_html(implode(' ', (array) ($row['errors'] ?? array()))) . '</span>';
            }
            if (!empty($row['warnings'])) {
                echo '<br><span style="color:#996800">' . esc_html(implode(' ', (array) $row['warnings'])) . '</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        if (!empty($validation['valid_count'])) {
            echo '<form method="post" style="margin-top:20px">';
            wp_nonce_field('car_json_import_confirm_' . $token, 'car_json_import_nonce');
            echo '<input type="hidden" name="car_json_import_action" value="confirm">';
            echo '<input type="hidden" name="import_token" value="' . esc_attr($token) . '">';
            echo '<label><input type="checkbox" name="confirm_import" value="1" required> ';
            echo esc_html__('I reviewed the validation results and want to import every valid row as pending.', 'bricks-child') . '</label>';
            submit_button(__('Start import', 'bricks-child'));
            echo '</form>';
        }
    }

    /** @param array<string,mixed> $session */
    private static function renderImporting(string $token, array $session): void
    {
        $total = count((array) ($session['validation']['rows'] ?? array()));
        $position = min($total, (int) ($session['position'] ?? 0));
        echo '<h1>' . esc_html__('Importing cars', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html(sprintf(__('Processed %1$d of %2$d rows. Keep this page open.', 'bricks-child'), $position, $total)) . '</p>';
        echo '<form method="post" id="car-json-import-continue">';
        wp_nonce_field('car_json_import_process_' . $token, 'car_json_import_nonce');
        echo '<input type="hidden" name="car_json_import_action" value="process">';
        echo '<input type="hidden" name="import_token" value="' . esc_attr($token) . '">';
        submit_button(__('Continue import', 'bricks-child'), 'primary', 'submit', false);
        echo '</form>';
        echo '<script>window.setTimeout(function(){var f=document.getElementById("car-json-import-continue");if(f){f.submit();}},800);</script>';
    }

    /** @param array<string,mixed> $session */
    private static function renderComplete(array $session): void
    {
        $results = (array) ($session['results'] ?? array());
        $counts = array('imported' => 0, 'skipped' => 0, 'invalid' => 0, 'failed' => 0);
        foreach ($results as $result) {
            $status = (string) ($result['status'] ?? 'failed');
            $counts[$status] = isset($counts[$status]) ? $counts[$status] + 1 : 1;
        }
        echo '<h1>' . esc_html__('Car JSON import complete', 'bricks-child') . '</h1>';
        echo '<p><strong>' . esc_html(sprintf(
            __('Imported: %1$d. Duplicates skipped: %2$d. Invalid: %3$d. Failed: %4$d.', 'bricks-child'),
            $counts['imported'], $counts['skipped'], $counts['invalid'], $counts['failed']
        )) . '</strong></p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Source ID', 'bricks-child') . '</th>';
        echo '<th>' . esc_html__('Status', 'bricks-child') . '</th><th>' . esc_html__('Result', 'bricks-child') . '</th></tr></thead><tbody>';
        foreach ($results as $result) {
            echo '<tr><td><code>' . esc_html((string) ($result['source_id'] ?? '')) . '</code></td>';
            echo '<td>' . esc_html((string) ($result['status'] ?? '')) . '</td><td>';
            if (!empty($result['post_id'])) {
                echo '<a href="' . esc_url(get_edit_post_link((int) $result['post_id'])) . '">';
                echo esc_html(sprintf(__('Edit car #%d', 'bricks-child'), (int) $result['post_id'])) . '</a>. ';
            }
            echo esc_html((string) ($result['message'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=car&post_status=pending')) . '">';
        echo esc_html__('Review pending cars', 'bricks-child') . '</a></p>';
        echo '<p><a class="button" href="' . esc_url(add_query_arg('page', self::PAGE_SLUG, admin_url('tools.php'))) . '">';
        echo esc_html__('Start another import', 'bricks-child') . '</a></p>';
    }

    /** @return array<string,mixed>|WP_Error */
    private static function loadSession(string $token)
    {
        $directory = self::sessionDirectory($token, false);
        if (is_wp_error($directory)) {
            return $directory;
        }
        $file = trailingslashit($directory) . 'session.json';
        if (!is_file($file)) {
            return new WP_Error('car_json_import_session_missing', __('The import session is missing or expired.', 'bricks-child'));
        }
        $session = json_decode((string) file_get_contents($file), true);
        if (!is_array($session) || (int) ($session['user_id'] ?? 0) !== get_current_user_id()) {
            return new WP_Error('car_json_import_session_invalid', __('The import session is invalid.', 'bricks-child'));
        }
        if ((int) ($session['created_at'] ?? 0) < time() - self::SESSION_TTL) {
            return new WP_Error('car_json_import_session_expired', __('The import session expired. Upload the package again.', 'bricks-child'));
        }
        return $session;
    }

    /** @param array<string,mixed> $session @return true|WP_Error */
    private static function saveSession(string $token, array $session)
    {
        $directory = self::sessionDirectory($token, true);
        if (is_wp_error($directory)) {
            return $directory;
        }
        $file = trailingslashit($directory) . 'session.json';
        $temporary = $file . '.tmp';
        $json = wp_json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($temporary, $json, LOCK_EX) === false) {
            return new WP_Error('car_json_import_session_write', __('The import session could not be saved.', 'bricks-child'));
        }
        if (is_file($file) && !@unlink($file)) {
            @unlink($temporary);
            return new WP_Error('car_json_import_session_replace', __('The import session could not be updated.', 'bricks-child'));
        }
        if (!rename($temporary, $file)) {
            @unlink($temporary);
            return new WP_Error('car_json_import_session_replace', __('The import session could not be updated.', 'bricks-child'));
        }
        return true;
    }

    /** @return string|WP_Error */
    private static function sessionDirectory(string $token, bool $create)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return new WP_Error('car_json_import_token', __('The import token is invalid.', 'bricks-child'));
        }
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('car_json_import_uploads', (string) $uploads['error']);
        }
        $base = wp_normalize_path(trailingslashit($uploads['basedir']) . 'car-json-import-sessions/');
        $directory = wp_normalize_path($base . 'session-' . get_current_user_id() . '-' . $token . '/');
        if (!str_starts_with($directory, $base)) {
            return new WP_Error('car_json_import_path', __('The import storage path is invalid.', 'bricks-child'));
        }
        if ($create && !is_dir($directory)) {
            if (!wp_mkdir_p($directory)) {
                return new WP_Error('car_json_import_storage', __('The secure import directory could not be created.', 'bricks-child'));
            }
            if (
                file_put_contents($directory . '.htaccess', "Require all denied\nDeny from all\n", LOCK_EX) === false ||
                file_put_contents($directory . 'index.php', "<?php\n// Silence is golden.\n", LOCK_EX) === false
            ) {
                return new WP_Error('car_json_import_storage_protection', __('The import directory could not be protected.', 'bricks-child'));
            }
        }
        if (!is_dir($directory)) {
            return new WP_Error('car_json_import_session_missing', __('The import session directory is missing.', 'bricks-child'));
        }
        return $directory;
    }

    /** @return array<string,mixed> */
    private static function postedDefaults(): array
    {
        return array(
            'car_city'      => isset($_POST['car_json_city']) ? sanitize_text_field(wp_unslash($_POST['car_json_city'])) : '',
            'car_district'  => isset($_POST['car_json_district']) ? sanitize_text_field(wp_unslash($_POST['car_json_district'])) : '',
            'car_address'   => isset($_POST['car_json_address']) ? sanitize_text_field(wp_unslash($_POST['car_json_address'])) : '',
            'car_latitude'  => isset($_POST['car_json_latitude']) && is_numeric($_POST['car_json_latitude']) ? (float) $_POST['car_json_latitude'] : null,
            'car_longitude' => isset($_POST['car_json_longitude']) && is_numeric($_POST['car_json_longitude']) ? (float) $_POST['car_json_longitude'] : null,
        );
    }

    private static function requestToken(): string
    {
        $raw = $_POST['import_token'] ?? $_GET['import_token'] ?? '';
        $token = sanitize_text_field(wp_unslash($raw));
        return preg_match('/^[a-f0-9]{32}$/', $token) ? $token : '';
    }

    private static function redirectToToken(string $token): void
    {
        wp_safe_redirect(add_query_arg(array('page' => self::PAGE_SLUG, 'import_token' => $token), admin_url('tools.php')));
        exit;
    }

    private static function redirectWithError(string $message, string $token = ''): void
    {
        $args = array('page' => self::PAGE_SLUG, 'car_json_error' => $message);
        if ($token !== '') {
            $args['import_token'] = $token;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('tools.php')));
        exit;
    }

    private static function notice(string $message, string $type): void
    {
        echo '<div class="notice notice-' . esc_attr($type) . '"><p>' . esc_html($message) . '</p></div>';
    }

    private static function locationInput(string $name, string $label, string $value, string $type = 'text', string $step = ''): void
    {
        echo '<label style="display:inline-block;margin:6px 12px 6px 0">' . esc_html($label) . '<br>';
        echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"';
        if ($step !== '') {
            echo ' step="' . esc_attr($step) . '"';
        }
        echo '></label>';
    }

    private static function maximumUploadBytes(): int
    {
        return min(self::MAX_PACKAGE_BYTES, (int) wp_max_upload_size());
    }
}
