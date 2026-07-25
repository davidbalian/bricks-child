<?php
/**
 * Dealer-profile XLSX importer in the Dealer Profiles admin menu.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DealerProfileXlsxReader.php';
require_once __DIR__ . '/DealerProfileImportRunner.php';

final class AutoAgoraDealerProfileImportAdmin
{
    private const PAGE_SLUG = 'dealer-profile-import';
    private const DELETE_PAGE_SLUG = 'dealer-profile-import-delete';
    private const NONCE_UPLOAD = 'dealer_profile_import_upload';
    private const NONCE_CONFIRM = 'dealer_profile_import_confirm';
    private const NONCE_PROCESS = 'dealer_profile_import_process';
    private const NONCE_DELETE = 'dealer_profile_import_delete';
    private const TRANSIENT_PREFIX = 'aag_dp_import_';
    private const RESULT_PREFIX = 'aag_dp_import_result_';
    private const SESSION_TTL = 7200;
    private const MAX_UPLOAD_BYTES = 10485760;
    private const BATCH_SIZE = 20;
    private const DELETE_BATCH_SIZE = 40;
    private const MAX_PREVIEW_ROWS = 100;

    public static function init(): void
    {
        add_action('admin_menu', array(__CLASS__, 'registerSubmenu'), 20);
    }

    public static function registerSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . AUTOAGORA_DEALER_PROFILE_POST_TYPE,
            __('Import dealer profiles', 'bricks-child'),
            __('Import profiles', 'bricks-child'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'renderPage')
        );

        add_submenu_page(
            'edit.php?post_type=' . AUTOAGORA_DEALER_PROFILE_POST_TYPE,
            __('Delete imported dealer profiles', 'bricks-child'),
            __('Delete imported profiles', 'bricks-child'),
            'manage_options',
            self::DELETE_PAGE_SLUG,
            array(__CLASS__, 'renderDeletePage')
        );
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to import dealer profiles.', 'bricks-child'));
        }

        self::cleanupStaleFiles();
        self::renderCompletionNotice();

        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : 'GET';
        if ($method === 'POST') {
            $step = isset($_POST['dealer_profile_import_step'])
                ? sanitize_key(wp_unslash($_POST['dealer_profile_import_step']))
                : '';

            if ($step === 'upload') {
                self::handleUpload();

                return;
            }
            if ($step === 'confirm') {
                self::handleConfirm();

                return;
            }
            if ($step === 'process') {
                self::handleProcess();

                return;
            }
        }

        $token = isset($_GET['import_token'])
            ? sanitize_text_field(wp_unslash($_GET['import_token']))
            : '';
        if ($token !== '') {
            self::renderSession($token);

            return;
        }

        self::renderUploadForm();
    }

    public static function renderDeletePage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete imported dealer profiles.', 'bricks-child'));
        }

        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : 'GET';
        if ($method === 'POST') {
            check_admin_referer(self::NONCE_DELETE);

            $step = isset($_POST['dealer_profile_delete_step'])
                ? sanitize_key(wp_unslash($_POST['dealer_profile_delete_step']))
                : '';
            if ($step === 'start') {
                $confirmation = isset($_POST['dealer_profile_delete_confirmation'])
                    ? strtoupper(trim(sanitize_text_field(wp_unslash($_POST['dealer_profile_delete_confirmation']))))
                    : '';
                if ($confirmation !== 'DELETE' || empty($_POST['dealer_profile_delete_acknowledgement'])) {
                    self::renderDeleteOverview(
                        __('Enter DELETE and confirm the acknowledgement before continuing.', 'bricks-child'),
                        true
                    );

                    return;
                }

                self::processDeleteBatch(0, 0);

                return;
            }

            if ($step === 'process') {
                $deleted = isset($_POST['dealer_profile_deleted_count'])
                    ? absint($_POST['dealer_profile_deleted_count'])
                    : 0;
                $failed = isset($_POST['dealer_profile_failed_count'])
                    ? absint($_POST['dealer_profile_failed_count'])
                    : 0;
                self::processDeleteBatch($deleted, $failed);

                return;
            }
        }

        $deleted = isset($_GET['deleted']) ? absint($_GET['deleted']) : 0;
        $failed = isset($_GET['failed']) ? absint($_GET['failed']) : 0;
        self::renderDeleteOverview('', false, $deleted, $failed);
    }

    private static function processDeleteBatch(int $deleted, int $failed): void
    {
        $ids = self::importedProfileIds(self::DELETE_BATCH_SIZE, true);
        foreach ($ids as $post_id) {
            $result = wp_delete_post($post_id, true);
            if ($result instanceof WP_Post) {
                ++$deleted;
            } else {
                ++$failed;
                update_post_meta($post_id, '_autoagora_import_delete_failed', '1');
            }
        }

        $remaining = self::countImportedProfiles(true);
        if ($remaining <= 0) {
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'post_type' => AUTOAGORA_DEALER_PROFILE_POST_TYPE,
                        'page'      => self::DELETE_PAGE_SLUG,
                        'deleted'   => $deleted,
                        'failed'    => $failed,
                    ),
                    admin_url('edit.php')
                )
            );
            exit;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Deleting imported dealer profiles', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html(
            sprintf(
                /* translators: 1: deleted profiles, 2: remaining profiles */
                __('Deleted %1$d profiles. %2$d remain.', 'bricks-child'),
                $deleted,
                $remaining
            )
        ) . '</p>';
        echo '<p class="description">'
            . esc_html__('Keep this page open. The next deletion batch will start automatically.', 'bricks-child')
            . '</p>';
        echo '<form method="post" id="dealer-profile-delete-continue">';
        wp_nonce_field(self::NONCE_DELETE);
        echo '<input type="hidden" name="dealer_profile_delete_step" value="process">';
        echo '<input type="hidden" name="dealer_profile_deleted_count" value="' . esc_attr((string) $deleted) . '">';
        echo '<input type="hidden" name="dealer_profile_failed_count" value="' . esc_attr((string) $failed) . '">';
        submit_button(__('Continue deletion', 'bricks-child'), 'primary', 'submit', false);
        echo '</form>';
        echo '<script>window.setTimeout(function(){var f=document.getElementById("dealer-profile-delete-continue");'
            . 'if(f){f.submit();}},500);</script>';
        echo '</div>';
    }

    private static function renderDeleteOverview(
        string $error = '',
        bool $preserve_input = false,
        int $deleted = 0,
        int $failed = 0
    ): void {
        $deletable = self::countImportedProfiles(true);
        $all_imported = self::countImportedProfiles(false);
        $protected = max(0, $all_imported - $deletable);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Delete imported dealer profiles', 'bricks-child') . '</h1>';

        if ($deleted > 0 || $failed > 0) {
            $notice_class = $failed > 0 ? 'notice-warning' : 'notice-success';
            echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>';
            echo esc_html(
                sprintf(
                    /* translators: 1: deleted profiles, 2: failed profiles */
                    __('Deletion finished. Deleted: %1$d. Failed: %2$d.', 'bricks-child'),
                    $deleted,
                    $failed
                )
            );
            echo '</p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error inline"><p>' . esc_html($error) . '</p></div>';
        }

        echo '<p>' . esc_html__(
            'This permanently deletes dealer profiles created by the XLSX importer. It cannot be undone.',
            'bricks-child'
        ) . '</p>';
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0;">';
        self::summaryBox(__('Will be deleted', 'bricks-child'), $deletable);
        self::summaryBox(__('Claimed or pending - protected', 'bricks-child'), $protected);
        echo '</div>';

        if ($deletable <= 0) {
            echo '<p><strong>' . esc_html__('There are no unclaimed imported profiles to delete.', 'bricks-child') . '</strong></p>';
            echo '</div>';

            return;
        }

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_DELETE);
        echo '<input type="hidden" name="dealer_profile_delete_step" value="start">';
        echo '<table class="form-table" role="presentation"><tr>';
        echo '<th scope="row"><label for="dealer_profile_delete_confirmation">'
            . esc_html__('Confirmation', 'bricks-child') . '</label></th><td>';
        echo '<input type="text" class="regular-text" id="dealer_profile_delete_confirmation" '
            . 'name="dealer_profile_delete_confirmation" value="'
            . esc_attr($preserve_input ? 'DELETE' : '') . '" autocomplete="off" required>';
        echo '<p class="description">' . esc_html__('Enter DELETE to confirm.', 'bricks-child') . '</p>';
        echo '</td></tr></table>';
        echo '<p><label><input type="checkbox" name="dealer_profile_delete_acknowledgement" value="1" required> '
            . esc_html__('I understand these imported profiles will be permanently deleted.', 'bricks-child')
            . '</label></p>';
        submit_button(__('Permanently delete imported profiles', 'delete', 'submit', false));
        echo '</form></div>';
    }

    /**
     * @return list<int>
     */
    private static function importedProfileIds(int $limit, bool $deletable_only): array
    {
        $meta_query = array(
            'relation' => 'AND',
            array(
                'key'     => 'dealer_import_source_id',
                'value'   => '',
                'compare' => '!=',
            ),
            array(
                'key'     => '_autoagora_import_delete_failed',
                'compare' => 'NOT EXISTS',
            ),
        );

        if ($deletable_only) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'dealer_claim_status',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'dealer_claim_status',
                    'value'   => array('claimed', 'pending'),
                    'compare' => 'NOT IN',
                ),
            );
        }

        $ids = get_posts(
            array(
                'post_type'              => AUTOAGORA_DEALER_PROFILE_POST_TYPE,
                'post_status'            => array('publish', 'draft', 'pending', 'private', 'future', 'trash'),
                'posts_per_page'         => $limit,
                'fields'                 => 'ids',
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
                'meta_query'             => $meta_query,
            )
        );

        return is_array($ids) ? array_map('intval', $ids) : array();
    }

    private static function countImportedProfiles(bool $deletable_only): int
    {
        return count(self::importedProfileIds(-1, $deletable_only));
    }

    private static function handleUpload(): void
    {
        check_admin_referer(self::NONCE_UPLOAD);

        if (empty($_FILES['dealer_profile_xlsx']) || !is_array($_FILES['dealer_profile_xlsx'])) {
            self::renderErrorAndUpload(__('Please select an XLSX workbook.', 'bricks-child'));

            return;
        }

        $file = $_FILES['dealer_profile_xlsx'];
        $upload_error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($upload_error !== UPLOAD_ERR_OK) {
            self::renderErrorAndUpload(self::uploadErrorMessage($upload_error));

            return;
        }

        $temporary_path = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $original_name = isset($file['name']) ? sanitize_file_name((string) $file['name']) : '';
        $actual_size = $temporary_path !== '' && is_file($temporary_path)
            ? (int) filesize($temporary_path)
            : 0;

        if ($temporary_path === '' || !is_uploaded_file($temporary_path)) {
            self::renderErrorAndUpload(__('The uploaded file did not pass the server upload check.', 'bricks-child'));

            return;
        }
        if ($actual_size <= 0 || $actual_size > self::MAX_UPLOAD_BYTES) {
            self::renderErrorAndUpload(__('The workbook must be larger than 0 bytes and no larger than 10 MB.', 'bricks-child'));

            return;
        }

        $allowed_mimes = array(
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $name_check = wp_check_filetype($original_name, $allowed_mimes);
        $content_check = wp_check_filetype_and_ext($temporary_path, $original_name, $allowed_mimes);
        if (($name_check['ext'] ?? '') !== 'xlsx'
            || (!empty($content_check['ext']) && $content_check['ext'] !== 'xlsx')
        ) {
            self::renderErrorAndUpload(__('Only a genuine .xlsx Excel workbook is accepted.', 'bricks-child'));

            return;
        }

        $sheet_key = isset($_POST['dealer_profile_sheet'])
            ? sanitize_key(wp_unslash($_POST['dealer_profile_sheet']))
            : 'all_research';
        $sheet_name = self::sheetNameForKey($sheet_key);
        if ($sheet_name === '') {
            self::renderErrorAndUpload(__('The selected worksheet is not allowed.', 'bricks-child'));

            return;
        }

        $parsed = AutoAgoraDealerProfileXlsxReader::readSheet($temporary_path, $sheet_name);
        if (is_wp_error($parsed)) {
            self::renderErrorAndUpload($parsed->get_error_message());

            return;
        }

        $prepared = AutoAgoraDealerProfileImportRunner::prepareRows(
            $parsed['headers'],
            $parsed['rows']
        );
        if (is_wp_error($prepared)) {
            self::renderErrorAndUpload($prepared->get_error_message());

            return;
        }

        $classifications = array(
            'create'    => 0,
            'update'    => 0,
            'protected' => 0,
            'conflict'  => 0,
            'invalid'   => 0,
        );
        foreach ($prepared['rows'] as &$row) {
            $classification = AutoAgoraDealerProfileImportRunner::classifyRow($row);
            $action = isset($classification['action']) ? $classification['action'] : 'invalid';
            $row['_preview_action'] = $action;
            $row['_preview_post_id'] = isset($classification['post_id'])
                ? (int) $classification['post_id']
                : 0;
            $row['_preview_message'] = isset($classification['message'])
                ? (string) $classification['message']
                : '';
            if (isset($classifications[$action])) {
                ++$classifications[$action];
            } else {
                ++$classifications['invalid'];
            }
        }
        unset($row);

        $token = wp_generate_password(40, false, false);
        $storage = self::createSessionStorage($token);
        if (is_wp_error($storage)) {
            self::renderErrorAndUpload($storage->get_error_message());

            return;
        }

        $rows_file = trailingslashit($storage) . 'rows.json';
        $encoded = wp_json_encode(
            $prepared['rows'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($encoded) || file_put_contents($rows_file, $encoded, LOCK_EX) === false) {
            self::removeStorageDirectory($storage);
            self::renderErrorAndUpload(__('The validated import rows could not be stored for preview.', 'bricks-child'));

            return;
        }

        $payload = array(
            'rows_file'       => $rows_file,
            'sheet_name'      => $sheet_name,
            'original_name'   => $original_name,
            'summary'         => $prepared['summary'],
            'classifications' => $classifications,
            'stage'           => 'preview',
            'next_offset'     => 0,
            'options'         => array(),
            'results'         => self::emptyResults(),
            'created_at'      => time(),
        );
        set_transient(self::sessionKey($token), $payload, self::SESSION_TTL);
        self::redirectScript(
            add_query_arg(
                array(
                    'post_type'    => AUTOAGORA_DEALER_PROFILE_POST_TYPE,
                    'page'         => self::PAGE_SLUG,
                    'import_token' => $token,
                ),
                admin_url('edit.php')
            )
        );
    }

    private static function handleConfirm(): void
    {
        check_admin_referer(self::NONCE_CONFIRM);

        $token = self::postedToken();
        $payload = self::getSession($token);
        if (is_wp_error($payload)) {
            self::renderErrorAndUpload($payload->get_error_message());

            return;
        }
        if (empty($_POST['confirm_import'])) {
            self::renderNoticeError(__('Confirm that you want to run the import.', 'bricks-child'));
            self::renderPreview($token, $payload);

            return;
        }

        $publish_mode = isset($_POST['publish_mode'])
            ? sanitize_key(wp_unslash($_POST['publish_mode']))
            : 'all';
        $index_mode = isset($_POST['index_mode'])
            ? sanitize_key(wp_unslash($_POST['index_mode']))
            : 'quality';
        if (!in_array($publish_mode, array('all', 'workbook'), true)) {
            $publish_mode = 'all';
        }
        if (!in_array($index_mode, array('quality', 'workbook', 'none'), true)) {
            $index_mode = 'quality';
        }

        $payload['stage'] = 'processing';
        $payload['next_offset'] = 0;
        $payload['options'] = array(
            'publish_mode' => $publish_mode,
            'index_mode'   => $index_mode,
        );
        $payload['results'] = self::emptyResults();
        set_transient(self::sessionKey($token), $payload, self::SESSION_TTL);

        self::processBatch($token, $payload);
    }

    private static function handleProcess(): void
    {
        $token = self::postedToken();
        if ($token === '') {
            self::renderErrorAndUpload(__('The import session token is missing.', 'bricks-child'));

            return;
        }
        check_admin_referer(self::NONCE_PROCESS . '_' . $token);

        $payload = self::getSession($token);
        if (is_wp_error($payload)) {
            self::renderErrorAndUpload($payload->get_error_message());

            return;
        }

        self::processBatch($token, $payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function processBatch(string $token, array $payload): void
    {
        if (($payload['stage'] ?? '') !== 'processing') {
            self::renderNoticeError(__('This import session is not ready to process.', 'bricks-child'));
            self::renderPreview($token, $payload);

            return;
        }

        $rows = self::readStoredRows((string) ($payload['rows_file'] ?? ''));
        if (is_wp_error($rows)) {
            self::cleanupSession($token, $payload);
            self::renderErrorAndUpload($rows->get_error_message());

            return;
        }

        $offset = max(0, (int) ($payload['next_offset'] ?? 0));
        $end = min(count($rows), $offset + self::BATCH_SIZE);
        $results = isset($payload['results']) && is_array($payload['results'])
            ? $payload['results']
            : self::emptyResults();
        $options = isset($payload['options']) && is_array($payload['options'])
            ? $payload['options']
            : array('publish_mode' => 'all', 'index_mode' => 'quality');

        for ($index = $offset; $index < $end; ++$index) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? $rows[$index] : array();
            $outcome = AutoAgoraDealerProfileImportRunner::applyRow($row, $options);
            $action = isset($outcome['action']) ? (string) $outcome['action'] : 'failed';
            if (isset($results[$action]) && is_int($results[$action])) {
                ++$results[$action];
            } else {
                ++$results['failed'];
            }

            if (in_array($action, array('failed', 'conflict', 'invalid'), true)
                && count($results['messages']) < 50
            ) {
                $worksheet_row = isset($row['_worksheet_row']) ? (int) $row['_worksheet_row'] : $index + 2;
                $dealer_name = isset($row['dealer_name']) ? (string) $row['dealer_name'] : __('Unknown dealer', 'bricks-child');
                $message = isset($outcome['message']) ? (string) $outcome['message'] : __('Import failed.', 'bricks-child');
                $results['messages'][] = sprintf(
                    /* translators: 1: worksheet row, 2: dealer name, 3: error */
                    __('Row %1$d, %2$s: %3$s', 'bricks-child'),
                    $worksheet_row,
                    $dealer_name,
                    $message
                );
            }
        }

        $payload['next_offset'] = $end;
        $payload['results'] = $results;
        set_transient(self::sessionKey($token), $payload, self::SESSION_TTL);

        if ($end < count($rows)) {
            self::renderProgress($token, $end, count($rows), $results);

            return;
        }

        $result_token = wp_generate_password(24, false, false);
        set_transient(
            self::resultKey($result_token),
            array(
                'results'    => $results,
                'sheet_name' => (string) ($payload['sheet_name'] ?? ''),
                'total'      => count($rows),
            ),
            600
        );
        self::cleanupSession($token, $payload);
        self::redirectScript(
            add_query_arg(
                array(
                    'post_type'    => AUTOAGORA_DEALER_PROFILE_POST_TYPE,
                    'page'         => self::PAGE_SLUG,
                    'import_done'  => '1',
                    'result_token' => $result_token,
                ),
                admin_url('edit.php')
            )
        );
    }

    private static function renderSession(string $token): void
    {
        $payload = self::getSession($token);
        if (is_wp_error($payload)) {
            self::renderErrorAndUpload($payload->get_error_message());

            return;
        }

        if (($payload['stage'] ?? '') === 'processing') {
            $rows = self::readStoredRows((string) ($payload['rows_file'] ?? ''));
            if (is_wp_error($rows)) {
                self::cleanupSession($token, $payload);
                self::renderErrorAndUpload($rows->get_error_message());

                return;
            }
            self::renderProgress(
                $token,
                (int) ($payload['next_offset'] ?? 0),
                count($rows),
                isset($payload['results']) && is_array($payload['results'])
                    ? $payload['results']
                    : self::emptyResults()
            );

            return;
        }

        self::renderPreview($token, $payload);
    }

    private static function renderUploadForm(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Import dealer profiles', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html__(
            'Upload the validated dealer-profile XLSX workbook. The file is checked and previewed before any profiles are changed.',
            'bricks-child'
        ) . '</p>';
        echo '<p class="description">' . esc_html__(
            'This creates dealer_profile posts only. It does not create WordPress users; existing dealership accounts can be connected afterward.',
            'bricks-child'
        ) . '</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE_UPLOAD);
        echo '<input type="hidden" name="dealer_profile_import_step" value="upload">';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="dealer_profile_xlsx">'
            . esc_html__('Excel workbook', 'bricks-child') . '</label></th><td>';
        echo '<input type="file" id="dealer_profile_xlsx" name="dealer_profile_xlsx" '
            . 'accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>';
        echo '<p class="description">'
            . esc_html__('XLSX only, maximum 10 MB. Macros and legacy XLS files are not accepted.', 'bricks-child')
            . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Worksheet', 'bricks-child') . '</th><td>';
        echo '<label><input type="radio" name="dealer_profile_sheet" value="all_research" checked> '
            . esc_html__('All Research - import every independent profile', 'bricks-child') . '</label><br>';
        echo '<label><input type="radio" name="dealer_profile_sheet" value="migration_ready"> '
            . esc_html__('Migration Ready - import ready rows only', 'bricks-child') . '</label>';
        echo '</td></tr></table>';
        submit_button(__('Upload and preview', 'bricks-child'));
        echo '</form></div>';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function renderPreview(string $token, array $payload): void
    {
        $rows = self::readStoredRows((string) ($payload['rows_file'] ?? ''));
        if (is_wp_error($rows)) {
            self::cleanupSession($token, $payload);
            self::renderErrorAndUpload($rows->get_error_message());

            return;
        }

        $summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : array();
        $classifications = isset($payload['classifications']) && is_array($payload['classifications'])
            ? $payload['classifications']
            : array();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Preview dealer-profile import', 'bricks-child') . '</h1>';
        echo '<p><strong>' . esc_html((string) ($payload['original_name'] ?? '')) . '</strong> &middot; '
            . esc_html((string) ($payload['sheet_name'] ?? '')) . '</p>';

        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0;">';
        self::summaryBox(__('Rows', 'bricks-child'), (int) ($summary['total'] ?? 0));
        self::summaryBox(__('Will create', 'bricks-child'), (int) ($classifications['create'] ?? 0));
        self::summaryBox(__('Will update', 'bricks-child'), (int) ($classifications['update'] ?? 0));
        self::summaryBox(__('Protected', 'bricks-child'), (int) ($classifications['protected'] ?? 0));
        self::summaryBox(__('Conflicts', 'bricks-child'), (int) ($classifications['conflict'] ?? 0));
        self::summaryBox(__('Invalid', 'bricks-child'), (int) ($classifications['invalid'] ?? 0));
        self::summaryBox(__('Qualify to index', 'bricks-child'), (int) ($summary['public_quality'] ?? 0));
        self::summaryBox(__('Logo URLs', 'bricks-child'), (int) ($summary['with_logos'] ?? 0));
        echo '</div>';

        echo '<div class="notice notice-info inline"><p>'
            . esc_html__(
                'Re-uploading the workbook updates matching unclaimed profiles by import source ID. Claimed, pending-claim, and rejected profiles are never overwritten.',
                'bricks-child'
            )
            . '</p></div>';

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_CONFIRM);
        echo '<input type="hidden" name="dealer_profile_import_step" value="confirm">';
        echo '<input type="hidden" name="import_token" value="' . esc_attr($token) . '">';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">' . esc_html__('Publishing', 'bricks-child') . '</th><td>';
        echo '<label><input type="radio" name="publish_mode" value="all" checked> '
            . esc_html__('Publish every valid imported profile', 'bricks-child') . '</label><br>';
        echo '<label><input type="radio" name="publish_mode" value="workbook"> '
            . esc_html__('Respect the workbook post_status values', 'bricks-child') . '</label>';
        echo '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Search indexing', 'bricks-child') . '</th><td>';
        echo '<label><input type="radio" name="index_mode" value="quality" checked> '
            . esc_html__('Index published profiles that pass the minimum public-information check', 'bricks-child')
            . '</label><br>';
        echo '<label><input type="radio" name="index_mode" value="workbook"> '
            . esc_html__('Respect the workbook dealer_indexable values', 'bricks-child') . '</label><br>';
        echo '<label><input type="radio" name="index_mode" value="none"> '
            . esc_html__('Keep every imported profile noindex', 'bricks-child') . '</label>';
        echo '</td></tr></table>';

        echo '<p><label><input type="checkbox" name="confirm_import" value="1" required> '
            . esc_html__('I reviewed this preview and want to import all valid rows.', 'bricks-child')
            . '</label></p>';
        submit_button(__('Start import', 'bricks-child'), 'primary', 'submit', false);
        echo ' <a class="button" href="' . esc_url(self::pageUrl()) . '">'
            . esc_html__('Cancel', 'bricks-child') . '</a>';
        echo '</form>';

        echo '<h2>' . esc_html__('Row preview', 'bricks-child') . '</h2>';
        if (count($rows) > self::MAX_PREVIEW_ROWS) {
            echo '<p class="description">'
                . esc_html(
                    sprintf(
                        /* translators: 1: rows shown, 2: total rows */
                        __('Showing the first %1$d of %2$d rows. All valid rows will be processed.', 'bricks-child'),
                        self::MAX_PREVIEW_ROWS,
                        count($rows)
                    )
                )
                . '</p>';
        }
        echo '<table class="widefat striped"><thead><tr>';
        foreach (array('Row', 'Dealer', 'City', 'QA', 'Action', 'Existing profile', 'Import source ID') as $heading) {
            echo '<th>' . esc_html__($heading, 'bricks-child') . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach (array_slice($rows, 0, self::MAX_PREVIEW_ROWS) as $row) {
            $action = isset($row['_preview_action']) ? (string) $row['_preview_action'] : 'invalid';
            echo '<tr>';
            echo '<td>' . esc_html((string) ((int) ($row['_worksheet_row'] ?? 0))) . '</td>';
            echo '<td><strong>' . esc_html((string) ($row['dealer_name'] ?? '')) . '</strong></td>';
            echo '<td>' . esc_html((string) ($row['dealer_city'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['qa_status'] ?? '')) . '</td>';
            echo '<td>' . esc_html(self::actionLabel($action)) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['_preview_post_id'] ?? 0))) . '</td>';
            echo '<td><code>' . esc_html((string) ($row['dealer_import_source_id'] ?? '')) . '</code></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    /**
     * @param array<string,mixed> $results
     */
    private static function renderProgress(string $token, int $processed, int $total, array $results): void
    {
        $percentage = $total > 0 ? min(100, (int) floor(($processed / $total) * 100)) : 100;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Importing dealer profiles', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html(
            sprintf(
                /* translators: 1: processed rows, 2: total rows */
                __('Processed %1$d of %2$d rows.', 'bricks-child'),
                $processed,
                $total
            )
        ) . '</p>';
        echo '<div style="max-width:720px;height:22px;background:#dcdcde;border-radius:3px;overflow:hidden;">';
        echo '<div style="height:100%;width:' . esc_attr((string) $percentage)
            . '%;background:#2271b1;"></div></div>';
        echo '<p><strong>' . esc_html((string) $percentage) . '%</strong></p>';
        echo '<p class="description">'
            . esc_html__('Keep this page open. The next safe batch will start automatically.', 'bricks-child')
            . '</p>';
        echo '<form method="post" id="dealer-profile-import-continue">';
        wp_nonce_field(self::NONCE_PROCESS . '_' . $token);
        echo '<input type="hidden" name="dealer_profile_import_step" value="process">';
        echo '<input type="hidden" name="import_token" value="' . esc_attr($token) . '">';
        submit_button(__('Continue import', 'bricks-child'), 'primary', 'submit', false);
        echo '</form>';
        echo '<script>window.setTimeout(function(){var f=document.getElementById("dealer-profile-import-continue");'
            . 'if(f){f.submit();}},500);</script>';
        echo '</div>';
    }

    private static function renderCompletionNotice(): void
    {
        $import_done = isset($_GET['import_done'])
            ? sanitize_key(wp_unslash($_GET['import_done']))
            : '';
        if ($import_done !== '1') {
            return;
        }

        $result_token = isset($_GET['result_token'])
            ? sanitize_text_field(wp_unslash($_GET['result_token']))
            : '';
        if ($result_token === '') {
            return;
        }

        $key = self::resultKey($result_token);
        $payload = get_transient($key);
        delete_transient($key);
        if (!is_array($payload) || empty($payload['results']) || !is_array($payload['results'])) {
            return;
        }

        $results = $payload['results'];
        echo '<div class="notice notice-success is-dismissible"><p><strong>'
            . esc_html__('Dealer-profile import finished.', 'bricks-child') . '</strong> ';
        echo esc_html(
            sprintf(
                /* translators: 1: created, 2: updated, 3: protected, 4: failed/conflicted/invalid */
                __('Created: %1$d. Updated: %2$d. Protected: %3$d. Skipped or failed: %4$d.', 'bricks-child'),
                (int) ($results['created'] ?? 0),
                (int) ($results['updated'] ?? 0),
                (int) ($results['protected'] ?? 0),
                (int) ($results['failed'] ?? 0)
                    + (int) ($results['conflict'] ?? 0)
                    + (int) ($results['invalid'] ?? 0)
            )
        );
        echo '</p></div>';

        if (!empty($results['messages']) && is_array($results['messages'])) {
            echo '<div class="notice notice-warning"><p><strong>'
                . esc_html__('Rows requiring attention:', 'bricks-child') . '</strong></p><ul>';
            foreach ($results['messages'] as $message) {
                echo '<li>' . esc_html((string) $message) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    private static function summaryBox(string $label, int $value): void
    {
        echo '<div style="min-width:120px;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;">';
        echo '<div style="font-size:20px;font-weight:600;">' . esc_html(number_format_i18n($value)) . '</div>';
        echo '<div class="description">' . esc_html($label) . '</div></div>';
    }

    private static function actionLabel(string $action): string
    {
        $labels = array(
            'create'    => __('Create', 'bricks-child'),
            'update'    => __('Update', 'bricks-child'),
            'protected' => __('Protected', 'bricks-child'),
            'conflict'  => __('Conflict - skipped', 'bricks-child'),
            'invalid'   => __('Invalid - skipped', 'bricks-child'),
        );

        return isset($labels[$action]) ? $labels[$action] : __('Invalid - skipped', 'bricks-child');
    }

    private static function sheetNameForKey(string $key): string
    {
        $sheets = array(
            'all_research'    => 'All Research',
            'migration_ready' => 'Migration Ready',
        );

        return isset($sheets[$key]) ? $sheets[$key] : '';
    }

    /**
     * @return string|WP_Error
     */
    private static function createSessionStorage(string $token)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('dealer_profile_import_upload_dir', (string) $uploads['error']);
        }

        $base = trailingslashit($uploads['basedir']) . 'dealer-profile-imports';
        if (!wp_mkdir_p($base)) {
            return new WP_Error(
                'dealer_profile_import_storage',
                __('The secure import storage directory could not be created.', 'bricks-child')
            );
        }
        file_put_contents(trailingslashit($base) . 'index.html', '', LOCK_EX);
        file_put_contents(
            trailingslashit($base) . '.htaccess',
            "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n",
            LOCK_EX
        );

        $directory = trailingslashit($base) . 'import-' . get_current_user_id() . '-' . $token;
        if (!wp_mkdir_p($directory)) {
            return new WP_Error(
                'dealer_profile_import_storage',
                __('The import session directory could not be created.', 'bricks-child')
            );
        }

        file_put_contents(trailingslashit($directory) . 'index.html', '', LOCK_EX);
        file_put_contents(
            trailingslashit($directory) . '.htaccess',
            "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n",
            LOCK_EX
        );

        return $directory;
    }

    /**
     * @return list<array<string,mixed>>|WP_Error
     */
    private static function readStoredRows(string $path)
    {
        if (!self::isPathInStorage($path) || !is_readable($path)) {
            return new WP_Error(
                'dealer_profile_import_rows_missing',
                __('The validated import rows are missing or the session expired.', 'bricks-child')
            );
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || strlen($contents) > 16777216) {
            return new WP_Error(
                'dealer_profile_import_rows_invalid',
                __('The stored import rows could not be read safely.', 'bricks-child')
            );
        }

        $rows = json_decode($contents, true);
        if (!is_array($rows)) {
            return new WP_Error(
                'dealer_profile_import_rows_invalid',
                __('The stored import rows are invalid.', 'bricks-child')
            );
        }

        return array_values($rows);
    }

    private static function isPathInStorage(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return false;
        }

        $base = wp_normalize_path(trailingslashit($uploads['basedir']) . 'dealer-profile-imports/');
        $normalized = wp_normalize_path($path);

        return strpos($normalized, $base) === 0 && basename($normalized) === 'rows.json';
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function getSession(string $token)
    {
        if ($token === '') {
            return new WP_Error(
                'dealer_profile_import_token',
                __('The import session token is missing.', 'bricks-child')
            );
        }

        $payload = get_transient(self::sessionKey($token));
        if (!is_array($payload)) {
            return new WP_Error(
                'dealer_profile_import_expired',
                __('The import session expired. Upload the workbook again.', 'bricks-child')
            );
        }

        return $payload;
    }

    private static function sessionKey(string $token): string
    {
        return self::TRANSIENT_PREFIX . get_current_user_id() . '_' . hash('sha256', $token);
    }

    private static function resultKey(string $token): string
    {
        return self::RESULT_PREFIX . get_current_user_id() . '_' . hash('sha256', $token);
    }

    private static function postedToken(): string
    {
        return isset($_POST['import_token'])
            ? sanitize_text_field(wp_unslash($_POST['import_token']))
            : '';
    }

    /**
     * @return array{created:int,updated:int,protected:int,conflict:int,invalid:int,failed:int,messages:list<string>}
     */
    private static function emptyResults(): array
    {
        return array(
            'created'   => 0,
            'updated'   => 0,
            'protected' => 0,
            'conflict'  => 0,
            'invalid'   => 0,
            'failed'    => 0,
            'messages'  => array(),
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function cleanupSession(string $token, array $payload): void
    {
        $rows_file = isset($payload['rows_file']) ? (string) $payload['rows_file'] : '';
        if (self::isPathInStorage($rows_file)) {
            self::removeStorageDirectory(dirname($rows_file));
        }
        delete_transient(self::sessionKey($token));
    }

    private static function removeStorageDirectory(string $directory): void
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return;
        }

        $base = wp_normalize_path(trailingslashit($uploads['basedir']) . 'dealer-profile-imports/');
        $normalized = wp_normalize_path(trailingslashit($directory));
        if (strpos($normalized, $base) !== 0 || strpos(basename($directory), 'import-') !== 0) {
            return;
        }

        foreach (array('rows.json', 'index.html', '.htaccess') as $filename) {
            $path = trailingslashit($directory) . $filename;
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    private static function cleanupStaleFiles(): void
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return;
        }

        $base = trailingslashit($uploads['basedir']) . 'dealer-profile-imports';
        if (!is_dir($base)) {
            return;
        }

        $directories = glob(trailingslashit($base) . 'import-*', GLOB_ONLYDIR);
        if (!is_array($directories)) {
            return;
        }
        foreach ($directories as $directory) {
            $modified = (int) filemtime($directory);
            if ($modified > 0 && $modified < time() - DAY_IN_SECONDS) {
                self::removeStorageDirectory($directory);
            }
        }
    }

    private static function uploadErrorMessage(int $error): string
    {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return __('The workbook exceeds the server upload limit.', 'bricks-child');
        }
        if ($error === UPLOAD_ERR_PARTIAL) {
            return __('The workbook upload was interrupted. Try again.', 'bricks-child');
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            return __('Please select an XLSX workbook.', 'bricks-child');
        }

        return __('The workbook could not be uploaded.', 'bricks-child');
    }

    private static function pageUrl(): string
    {
        return add_query_arg(
            array(
                'post_type' => AUTOAGORA_DEALER_PROFILE_POST_TYPE,
                'page'      => self::PAGE_SLUG,
            ),
            admin_url('edit.php')
        );
    }

    private static function redirectScript(string $url): void
    {
        echo '<script>window.location.href=' . wp_json_encode(esc_url_raw($url)) . ';</script>';
        echo '<noscript><p><a href="' . esc_url($url) . '">' . esc_html__('Continue', 'bricks-child') . '</a></p></noscript>';
    }

    private static function renderErrorAndUpload(string $message): void
    {
        self::renderNoticeError($message);
        self::renderUploadForm();
    }

    private static function renderNoticeError(string $message): void
    {
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
}

AutoAgoraDealerProfileImportAdmin::init();
