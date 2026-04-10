<?php
/**
 * Admin download handlers for daily deals images (single file or zip).
 */
if (!defined('ABSPATH')) {
    exit;
}

final class DailyDealsDownloadHandler
{
    private const ACTION_IMAGE = 'bricks_child_daily_deals_image';

    private const ACTION_ZIP = 'bricks_child_daily_deals_zip';

    public static function bootstrap(): void
    {
        add_action('admin_post_' . self::ACTION_IMAGE, array(__CLASS__, 'serveSingleImage'));
        add_action('admin_post_' . self::ACTION_ZIP, array(__CLASS__, 'serveZip'));
    }

    public static function serveSingleImage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'bricks-child'), '', array('response' => 403));
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, 'bricks_child_daily_deals_dl')) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        $id = isset($_GET['attachment_id']) ? absint($_GET['attachment_id']) : 0;
        if ($id <= 0 || ! wp_attachment_is_image($id)) {
            wp_die(esc_html__('Invalid image.', 'bricks-child'), '', array('response' => 400));
        }

        $path = get_attached_file($id);
        if ($path === false || $path === '' || ! is_readable($path)) {
            wp_die(esc_html__('File not found.', 'bricks-child'), '', array('response' => 404));
        }

        $mime = get_post_mime_type($id);
        if (! is_string($mime) || $mime === '') {
            $mime = 'application/octet-stream';
        }

        self::sendDownloadHeaders(basename($path), $mime);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
        readfile($path);
        exit;
    }

    public static function serveZip(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'bricks-child'), '', array('response' => 403));
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, 'bricks_child_daily_deals_dl')) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        if (! class_exists('ZipArchive')) {
            wp_die(esc_html__('Zip is not available on this server.', 'bricks-child'), '', array('response' => 500));
        }

        $raw = isset($_GET['attachment_ids']) ? sanitize_text_field(wp_unslash($_GET['attachment_ids'])) : '';
        $parts = array_filter(array_map('absint', explode(',', $raw)));
        $parts = array_values(array_unique($parts));
        if ($parts === array() || count($parts) > 12) {
            wp_die(esc_html__('Invalid selection.', 'bricks-child'), '', array('response' => 400));
        }

        $zip = new ZipArchive();
        $tmp = wp_tempnam('daily-deals');
        if ($tmp === false || ! $zip->open($tmp, ZipArchive::OVERWRITE)) {
            wp_die(esc_html__('Could not create archive.', 'bricks-child'), '', array('response' => 500));
        }

        $index = 1;
        foreach ($parts as $aid) {
            if (! wp_attachment_is_image($aid)) {
                continue;
            }
            $path = get_attached_file($aid);
            if ($path === false || $path === '' || ! is_readable($path)) {
                continue;
            }
            $local = sprintf('deal-%d-%s', $index, basename($path));
            $zip->addFile($path, $local);
            ++$index;
        }

        if ($zip->numFiles < 1) {
            $zip->close();
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            @unlink($tmp);
            wp_die(esc_html__('No images could be added to the archive.', 'bricks-child'), '', array('response' => 400));
        }

        $zip->close();

        $name = 'daily-deals-images-' . gmdate('Y-m-d') . '.zip';
        self::sendDownloadHeaders($name, 'application/zip');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
        readfile($tmp);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
        @unlink($tmp);
        exit;
    }

    /**
     * @param string $filename Suggested download filename.
     * @param string $mime     MIME type.
     */
    private static function sendDownloadHeaders(string $filename, string $mime): void
    {
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('X-Robots-Tag: noindex');
    }
}
