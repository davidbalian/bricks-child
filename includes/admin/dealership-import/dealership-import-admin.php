<?php
/**
 * Admin: import dealership dealer fields from CSV (preview + confirm).
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DealershipImportRunner.php';

final class DealershipImportAdminBootstrap
{
    private const PAGE_SLUG = 'dealerships-import';
    private const NONCE_UPLOAD = 'dealership_import_upload';
    private const NONCE_PREVIEW = 'dealership_import_preview';
    private const NONCE_CONFIRM = 'dealership_import_confirm';
    private const TRANSIENT_BASE = 'd_imp_ctx_';

    public static function init(): void
    {
        add_action('admin_menu', array(__CLASS__, 'registerSubmenu'), 21);
    }

    public static function registerSubmenu(): void
    {
        add_submenu_page(
            'dealerships',
            __('Import dealership data', 'bricks-child'),
            __('Import dealership data', 'bricks-child'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'renderPage')
        );
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'));
        }

        if (isset($_GET['import_done']) && $_GET['import_done'] === '1') {
            $applied = isset($_GET['applied']) ? (int) $_GET['applied'] : 0;
            $skipped = isset($_GET['skipped']) ? (int) $_GET['skipped'] : 0;
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html(
                sprintf(
                    /* translators: 1: accounts updated, 2: rows skipped */
                    __('Import finished. Accounts with updates: %1$d. Rows skipped (unmatched or no changes): %2$d.', 'bricks-child'),
                    $applied,
                    $skipped
                )
            );
            echo '</p></div>';
        }

        $step = isset($_POST['dealership_import_step'])
            ? sanitize_text_field(wp_unslash($_POST['dealership_import_step']))
            : '';

        if ($step === 'upload') {
            self::handleUploadStep();

            return;
        }

        if ($step === 'preview') {
            self::handlePreviewStep();

            return;
        }

        if ($step === 'confirm') {
            self::handleConfirmStep();

            return;
        }

        $token = isset($_GET['import_token'])
            ? sanitize_text_field(wp_unslash($_GET['import_token']))
            : '';
        if ($token !== '') {
            self::renderMappingForm($token);

            return;
        }

        self::renderUploadForm();
    }

    private static function transientKey(string $token): string
    {
        return self::TRANSIENT_BASE . get_current_user_id() . '_' . $token;
    }

    private static function handleUploadStep(): void
    {
        check_admin_referer(self::NONCE_UPLOAD);

        if (empty($_FILES['dealership_csv']['tmp_name'])) {
            self::adminNoticeError(__('Please choose a CSV file.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            self::adminNoticeError($upload['error']);
            self::renderUploadForm();

            return;
        }

        $base = trailingslashit($upload['basedir']) . 'dealership-import/';
        if (!wp_mkdir_p($base)) {
            self::adminNoticeError(__('Could not create upload directory.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $sub = wp_generate_password(12, false, false);
        $dir = trailingslashit($base) . $sub;
        if (!wp_mkdir_p($dir)) {
            self::adminNoticeError(__('Could not create import folder.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $target = trailingslashit($dir) . 'data.csv';
        if (!move_uploaded_file($_FILES['dealership_csv']['tmp_name'], $target)) {
            self::adminNoticeError(__('Could not save uploaded file.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $parsed = DealershipImportRunner::parseCsvFile($target);
        if (isset($parsed['error'])) {
            @unlink($target);
            @rmdir($dir);
            self::adminNoticeError($parsed['error']);
            self::renderUploadForm();

            return;
        }

        $token = wp_generate_password(32, false, false);
        $payload = array(
            'file'    => $target,
            'headers' => $parsed['headers'],
        );
        set_transient(self::transientKey($token), $payload, HOUR_IN_SECONDS);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'         => self::PAGE_SLUG,
                    'import_token' => $token,
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    private static function handlePreviewStep(): void
    {
        check_admin_referer(self::NONCE_PREVIEW);

        $token = isset($_POST['import_token'])
            ? sanitize_text_field(wp_unslash($_POST['import_token']))
            : '';
        if ($token === '') {
            self::adminNoticeError(__('Missing import session.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $data = get_transient(self::transientKey($token));
        if (!is_array($data) || empty($data['file']) || empty($data['headers'])) {
            self::adminNoticeError(__('Import session expired. Upload again.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $mapping = self::readMappingFromPost();
        $parsed = DealershipImportRunner::parseCsvFile($data['file']);
        if (isset($parsed['error'])) {
            self::adminNoticeError($parsed['error']);
            self::renderMappingForm($token);

            return;
        }

        $data['mapping'] = $mapping;
        set_transient(self::transientKey($token), $data, HOUR_IN_SECONDS);

        $preview = DealershipImportRunner::buildPreview(
            $parsed['headers'],
            $parsed['rows'],
            $mapping
        );

        self::renderPreviewTable($token, $mapping, $parsed['headers'], $preview);
    }

    private static function handleConfirmStep(): void
    {
        check_admin_referer(self::NONCE_CONFIRM);

        $token = isset($_POST['import_token'])
            ? sanitize_text_field(wp_unslash($_POST['import_token']))
            : '';
        if ($token === '') {
            self::adminNoticeError(__('Missing import session.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $data = get_transient(self::transientKey($token));
        if (!is_array($data) || empty($data['file']) || empty($data['mapping'])) {
            self::adminNoticeError(__('Import session expired. Upload again.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $selected = isset($_POST['import_rows']) ? (array) wp_unslash($_POST['import_rows']) : array();
        $selected = array_map('intval', $selected);
        $selected = array_values(array_unique(array_filter($selected, static function ($v) {
            return $v >= 0;
        })));

        $parsed = DealershipImportRunner::parseCsvFile($data['file']);
        if (isset($parsed['error'])) {
            delete_transient(self::transientKey($token));
            self::adminNoticeError($parsed['error']);
            self::renderUploadForm();

            return;
        }

        $resolver = new DealershipImportUserResolver();
        $applied = 0;
        $skipped = 0;

        foreach ($selected as $idx) {
            if (!isset($parsed['rows'][$idx])) {
                continue;
            }
            $targets = DealershipImportColumnMapper::rowToTargets(
                $parsed['headers'],
                $parsed['rows'][$idx],
                $data['mapping']
            );
            $name = $targets[DealershipImportColumnMapper::TARGET_DEALERSHIP_NAME];
            $res = $resolver->resolve($name);
            if ($res['status'] !== 'matched' || empty($res['user_id'])) {
                ++$skipped;

                continue;
            }
            $result = DealershipImportRunner::applyRow((int) $res['user_id'], $targets);
            if ($result['updated'] > 0) {
                ++$applied;
            }
        }

        @unlink($data['file']);
        $parent = dirname($data['file']);
        if (is_dir($parent)) {
            @rmdir($parent);
        }
        delete_transient(self::transientKey($token));

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'        => self::PAGE_SLUG,
                    'import_done' => '1',
                    'applied'     => $applied,
                    'skipped'     => $skipped,
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * @return array<string, string>
     */
    private static function readMappingFromPost(): array
    {
        $raw = isset($_POST['dealership_mapping']) ? wp_unslash($_POST['dealership_mapping']) : array();
        if (!is_array($raw)) {
            $raw = array();
        }
        $out = array();
        foreach (DealershipImportColumnMapper::targetFields() as $target) {
            $out[$target] = isset($raw[$target]) ? sanitize_text_field($raw[$target]) : '';
        }

        return $out;
    }

    private static function renderUploadForm(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Import dealership data', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html__(
            'Upload a CSV (Google Sheet export or dealerships_import_ready.csv). Match each column, preview changes, then confirm. Empty cells are skipped (existing values kept). Dealership names are only used to find users — names on the site are not changed.',
            'bricks-child'
        ) . '</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE_UPLOAD);
        echo '<input type="hidden" name="dealership_import_step" value="upload" />';
        echo '<table class="form-table"><tr><th scope="row"><label for="dealership_csv">'
            . esc_html__('CSV file', 'bricks-child') . '</label></th><td>';
        echo '<input type="file" id="dealership_csv" name="dealership_csv" accept=".csv,text/csv" required />';
        echo '</td></tr></table>';
        submit_button(__('Continue to column mapping', 'bricks-child'));
        echo '</form></div>';
    }

    private static function renderMappingForm(string $token): void
    {
        $data = get_transient(self::transientKey($token));
        if (!is_array($data) || empty($data['headers'])) {
            self::adminNoticeError(__('Import session expired. Upload again.', 'bricks-child'));
            self::renderUploadForm();

            return;
        }

        $headers = $data['headers'];
        $guess = DealershipImportColumnMapper::guessMapping($headers);
        if (!empty($data['mapping'])) {
            $guess = array_merge($guess, $data['mapping']);
        }

        $labels = self::targetLabels();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Map CSV columns', 'bricks-child') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field(self::NONCE_PREVIEW);
        echo '<input type="hidden" name="dealership_import_step" value="preview" />';
        echo '<input type="hidden" name="import_token" value="' . esc_attr($token) . '" />';
        echo '<table class="form-table">';
        foreach (DealershipImportColumnMapper::targetFields() as $target) {
            echo '<tr><th scope="row">' . esc_html($labels[$target]) . '</th><td>';
            echo '<select name="dealership_mapping[' . esc_attr($target) . ']">';
            echo '<option value="">' . esc_html__('— None —', 'bricks-child') . '</option>';
            foreach ($headers as $h) {
                if ($h === '') {
                    continue;
                }
                $sel = ($guess[$target] === $h) ? ' selected="selected"' : '';
                echo '<option value="' . esc_attr($h) . '"' . $sel . '>' . esc_html($h) . '</option>';
            }
            echo '</select></td></tr>';
        }
        echo '</table>';
        submit_button(__('Preview import', 'bricks-child'));
        echo '</form></div>';
    }

    /**
     * @param array<string, string> $mapping
     * @param list<string> $headers
     * @param list<array<string,mixed>> $preview
     */
    private static function renderPreviewTable(
        string $token,
        array $mapping,
        array $headers,
        array $preview
    ): void {
        $labels = self::targetLabels();
        $field_keys = array(
            DealershipImportColumnMapper::TARGET_MAPS_ADDRESS,
            DealershipImportColumnMapper::TARGET_MAPS_URL,
            DealershipImportColumnMapper::TARGET_WEBSITE,
            DealershipImportColumnMapper::TARGET_INSTAGRAM,
            DealershipImportColumnMapper::TARGET_FACEBOOK,
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Confirm dealership import', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html__(
            'Review each row. Only checked rows will be updated. Invalid URLs or blocked HTML are not applied.',
            'bricks-child'
        ) . '</p>';

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_CONFIRM);
        echo '<input type="hidden" name="dealership_import_step" value="confirm" />';
        echo '<input type="hidden" name="import_token" value="' . esc_attr($token) . '" />';

        foreach (DealershipImportColumnMapper::targetFields() as $t) {
            echo '<input type="hidden" name="dealership_mapping[' . esc_attr($t) . ']" value="'
                . esc_attr($mapping[$t] ?? '') . '" />';
        }

        echo '<p><button type="submit" class="button button-primary button-large">'
            . esc_html__('Apply selected rows', 'bricks-child') . '</button></p>';

        echo '<table class="widefat striped" style="table-layout:fixed;width:100%;">';
        echo '<thead><tr>';
        echo '<th style="width:40px;">' . esc_html__('Import', 'bricks-child') . '</th>';
        echo '<th>' . esc_html__('Status', 'bricks-child') . '</th>';
        echo '<th>' . esc_html__('User ID', 'bricks-child') . '</th>';
        echo '<th>' . esc_html__('Name (CSV)', 'bricks-child') . '</th>';
        foreach ($field_keys as $fk) {
            echo '<th>' . esc_html($labels[$fk]) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($preview as $entry) {
            $idx = (int) $entry['row_index'];
            $status = $entry['status'];
            $checked = ($status === 'matched') ? ' checked="checked"' : '';
            $disabled = ($status === 'matched') ? '' : ' disabled="disabled"';

            echo '<tr>';
            echo '<td><input type="checkbox" name="import_rows[]" value="' . esc_attr((string) $idx) . '"'
                . $checked . $disabled . ' /></td>';
            echo '<td>' . esc_html(self::statusLabel($status, $entry)) . '</td>';
            echo '<td>' . esc_html((string) (int) $entry['user_id']) . '</td>';
            echo '<td>' . esc_html($entry['name_match']) . '</td>';

            foreach ($field_keys as $fk) {
                $f = $entry['fields'][$fk];
                $cell = '';
                if (!empty($f['warning'])) {
                    $cell .= '<span class="dashicons dashicons-warning" title="' . esc_attr($f['warning'])
                        . '"></span> ';
                }
                if ($f['will_update']) {
                    $cell .= '<strong>' . esc_html__('→', 'bricks-child') . '</strong> ';
                    $snip = function_exists('mb_substr')
                        ? mb_substr($f['sanitized'], 0, 120, 'UTF-8')
                        : substr($f['sanitized'], 0, 120);
                    $long = function_exists('mb_strlen')
                        ? mb_strlen($f['sanitized'], 'UTF-8')
                        : strlen($f['sanitized']);
                    $cell .= '<code style="word-break:break-all;font-size:11px;">'
                        . esc_html($snip)
                        . ($long > 120 ? '…' : '') . '</code>';
                } elseif ($f['proposed_raw'] !== '' && !$f['ok']) {
                    $cell .= '<em>' . esc_html__('Not applied', 'bricks-child') . '</em>';
                } elseif ($f['proposed_raw'] === '') {
                    $cell .= '<span class="description">' . esc_html__('unchanged', 'bricks-child') . '</span>';
                } else {
                    $cell .= '<span class="description">' . esc_html__('no change', 'bricks-child') . '</span>';
                }
                echo '<td style="vertical-align:top;font-size:12px;">' . $cell . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary button-large">'
            . esc_html__('Apply selected rows', 'bricks-child') . '</button></p>';
        echo '</form></div>';
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function statusLabel(string $status, array $entry): string
    {
        if ($status === 'matched') {
            return __('Matched', 'bricks-child');
        }
        if ($status === 'ambiguous') {
            $ids = isset($entry['ambiguous_ids']) ? implode(', ', array_map('intval', $entry['ambiguous_ids'])) : '';

            return sprintf(
                /* translators: %s: user IDs */
                __('Ambiguous (%s)', 'bricks-child'),
                $ids
            );
        }

        return __('Unmatched', 'bricks-child');
    }

    /**
     * @return array<string, string>
     */
    private static function targetLabels(): array
    {
        return array(
            DealershipImportColumnMapper::TARGET_DEALERSHIP_NAME => __('Dealership name (match only)', 'bricks-child'),
            DealershipImportColumnMapper::TARGET_MAPS_ADDRESS    => __('Maps address', 'bricks-child'),
            DealershipImportColumnMapper::TARGET_MAPS_URL        => __('Maps URL / embed', 'bricks-child'),
            DealershipImportColumnMapper::TARGET_WEBSITE         => __('Website', 'bricks-child'),
            DealershipImportColumnMapper::TARGET_INSTAGRAM       => __('Instagram', 'bricks-child'),
            DealershipImportColumnMapper::TARGET_FACEBOOK       => __('Facebook', 'bricks-child'),
        );
    }

    private static function adminNoticeError(string $message): void
    {
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
}

DealershipImportAdminBootstrap::init();
