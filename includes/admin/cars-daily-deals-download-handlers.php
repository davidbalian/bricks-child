<?php
/**
 * Daily Deals: branded / Instagram / ZIP download endpoints (admin-post).
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarsDailyDealsDownloadHandlers
{
    public const ACTION_ZIP = 'bricks_child_daily_deals_zip';

    public const ACTION_DOWNLOAD = 'bricks_child_daily_deals_download';

    public const ACTION_DOWNLOAD_IG = 'bricks_child_daily_deals_download_instagram';

    public const NONCE_ZIP = 'bricks_child_daily_deals_zip';

    public const NONCE_DOWNLOAD = 'bricks_child_daily_deals_download';

    public const NONCE_DOWNLOAD_IG = 'bricks_child_daily_deals_download_instagram';

    public static function handleSingleDownload(): void
    {
        if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::NONCE_DOWNLOAD)) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'), '', array('response' => 403));
        }

        $post_id = isset($_GET['post_id']) ? absint(wp_unslash($_GET['post_id'])) : 0;
        if ($post_id <= 0) {
            wp_die(esc_html__('Invalid listing.', 'bricks-child'), '', array('response' => 400));
        }

        $builder = new CarsDailyDealsSnapshotBuilder();
        $items   = $builder->fetchFirstCarsFromBrowseQuery(5);
        $allowed = array();
        foreach ($items as $row) {
            $allowed[] = (int) ($row['id'] ?? 0);
        }
        if (! in_array($post_id, $allowed, true)) {
            wp_die(esc_html__('Listing is not in the current top deals set. Fetch deals again.', 'bricks-child'), '', array('response' => 400));
        }

        $path = '';
        foreach ($items as $row) {
            if ((int) ($row['id'] ?? 0) === $post_id) {
                $path = (string) ($row['image_path'] ?? '');
                break;
            }
        }

        if ($path === '' || ! is_readable($path)) {
            wp_die(esc_html__('Image file is not available on the server (CDN-only or missing file).', 'bricks-child'), '', array('response' => 404));
        }

        $branded = false;
        if (CarsDailyDealsStickerStore::hasAnySticker() && CarsDailyDealsImageCompositor::canComposite()) {
            $branded = CarsDailyDealsImageCompositor::compositeToTempJpeg($path);
        }

        if ($branded !== false && is_string($branded) && is_readable($branded)) {
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="deal-' . $post_id . '-branded.jpg"');
            header('Content-Length: ' . filesize($branded));
            readfile($branded);
            @unlink($branded);
            exit;
        }

        $type = wp_check_filetype($path);
        $mime = isset($type['type']) && $type['type'] ? $type['type'] : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public static function handleInstagramDownload(): void
    {
        if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::NONCE_DOWNLOAD_IG)) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'), '', array('response' => 403));
        }

        if (! CarsDailyDealsImageCompositor::canComposite()) {
            wp_die(esc_html__('Image processing is not available on this server.', 'bricks-child'), '', array('response' => 500));
        }

        $post_id = isset($_GET['post_id']) ? absint(wp_unslash($_GET['post_id'])) : 0;
        if ($post_id <= 0) {
            wp_die(esc_html__('Invalid listing.', 'bricks-child'), '', array('response' => 400));
        }

        $builder = new CarsDailyDealsSnapshotBuilder();
        $items   = $builder->fetchFirstCarsFromBrowseQuery(5);
        $allowed = array();
        foreach ($items as $row) {
            $allowed[] = (int) ($row['id'] ?? 0);
        }
        if (! in_array($post_id, $allowed, true)) {
            wp_die(esc_html__('Listing is not in the current top deals set. Fetch deals again.', 'bricks-child'), '', array('response' => 400));
        }

        $path = '';
        foreach ($items as $row) {
            if ((int) ($row['id'] ?? 0) === $post_id) {
                $path = (string) ($row['image_path'] ?? '');
                break;
            }
        }

        if ($path === '' || ! is_readable($path)) {
            wp_die(esc_html__('Image file is not available on the server (CDN-only or missing file).', 'bricks-child'), '', array('response' => 404));
        }

        $tmp = CarsDailyDealsImageCompositor::instagramSquareToTempJpeg($path);
        if ($tmp === false || ! is_readable($tmp)) {
            wp_die(esc_html__('Could not build Instagram image.', 'bricks-child'), '', array('response' => 500));
        }

        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="deal-' . $post_id . '-instagram-1080.jpg"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    public static function handleZipDownload(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::NONCE_ZIP)) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'), '', array('response' => 403));
        }

        if (! class_exists('ZipArchive')) {
            wp_die(esc_html__('ZIP is not available on this server.', 'bricks-child'), '', array('response' => 500));
        }

        $raw_ids = isset($_POST['post_ids']) ? wp_unslash($_POST['post_ids']) : array();
        $ids     = array_map('absint', is_array($raw_ids) ? $raw_ids : array());
        $ids     = array_values(array_filter(array_unique($ids)));

        if (count($ids) > 10) {
            wp_die(esc_html__('Too many listings.', 'bricks-child'), '', array('response' => 400));
        }

        $builder = new CarsDailyDealsSnapshotBuilder();
        $items   = $builder->fetchFirstCarsFromBrowseQuery(5);
        $allowed = array();
        foreach ($items as $row) {
            $allowed[] = (int) ($row['id'] ?? 0);
        }
        $allowed = array_values(array_filter(array_unique($allowed)));

        foreach ($ids as $id) {
            if (! in_array($id, $allowed, true)) {
                wp_die(esc_html__('Invalid listing selection.', 'bricks-child'), '', array('response' => 400));
            }
        }

        $zip_format = isset($_POST['zip_format']) ? sanitize_key(wp_unslash($_POST['zip_format'])) : 'original';
        if ($zip_format === 'instagram' && ! CarsDailyDealsImageCompositor::canComposite()) {
            wp_die(esc_html__('Instagram ZIP requires GD or Imagick on the server.', 'bricks-child'), '', array('response' => 500));
        }

        $tmp = wp_tempnam('daily-deals-');
        if (! $tmp) {
            wp_die(esc_html__('Could not create temporary file.', 'bricks-child'), '', array('response' => 500));
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            wp_die(esc_html__('Could not create ZIP archive.', 'bricks-child'), '', array('response' => 500));
        }

        $ig_zip = ($zip_format === 'instagram');

        $index    = 1;
        $cleanup  = array();
        $do_brand = CarsDailyDealsStickerStore::hasAnySticker() && CarsDailyDealsImageCompositor::canComposite();

        foreach ($ids as $post_id) {
            foreach ($items as $row) {
                if ((int) ($row['id'] ?? 0) !== $post_id) {
                    continue;
                }
                $path = (string) ($row['image_path'] ?? '');
                if ($path !== '' && is_readable($path)) {
                    $file_for_zip = $path;
                    $ext          = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $safe         = $ext !== '' ? $ext : 'jpg';

                    if ($ig_zip) {
                        $ig_tmp = CarsDailyDealsImageCompositor::instagramSquareToTempJpeg($path);
                        if ($ig_tmp !== false && is_string($ig_tmp) && is_readable($ig_tmp)) {
                            $file_for_zip = $ig_tmp;
                            $safe         = 'jpg';
                            $cleanup[]    = $ig_tmp;
                        }
                    } elseif ($do_brand) {
                        $branded = CarsDailyDealsImageCompositor::compositeToTempJpeg($path);
                        if ($branded !== false && is_string($branded) && is_readable($branded)) {
                            $file_for_zip = $branded;
                            $safe         = 'jpg';
                            $cleanup[]    = $branded;
                        }
                    }

                    $zip->addFile($file_for_zip, 'deal-' . $index . ($ig_zip ? '-instagram' : '') . '.' . $safe);
                    ++$index;
                }
                break;
            }
        }

        $zip->close();

        foreach ($cleanup as $tmp_branded) {
            @unlink($tmp_branded);
        }

        if (! is_readable($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            wp_die(esc_html__('No images could be added to the archive.', 'bricks-child'), '', array('response' => 400));
        }

        $filename = $ig_zip
            ? 'daily-deals-instagram-' . gmdate('Y-m-d') . '.zip'
            : 'daily-deals-' . gmdate('Y-m-d') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}
