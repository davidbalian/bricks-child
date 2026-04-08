<?php
/**
 * Admin submenu and CSV download for dealership user export.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DealershipUsersCsvExporter.php';

final class DealershipExportAdminBootstrap
{
    private const PAGE_SLUG = 'dealerships-export';
    private const NONCE_ACTION = 'dealership_export_csv';

    public static function init(): void
    {
        add_action('admin_menu', array(__CLASS__, 'registerSubmenu'), 20);
        add_action('admin_init', array(__CLASS__, 'maybeStreamCsv'));
    }

    public static function registerSubmenu(): void
    {
        add_submenu_page(
            'dealerships',
            __('Export Dealership Information', 'bricks-child'),
            __('Export Dealership Information', 'bricks-child'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'renderPage')
        );
    }

    public static function maybeStreamCsv(): void
    {
        if (!is_admin()) {
            return;
        }
        if (empty($_GET['page']) || $_GET['page'] !== self::PAGE_SLUG) {
            return;
        }
        if (empty($_GET['download']) || (string) $_GET['download'] !== '1') {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export dealerships.', 'bricks-child'));
        }

        check_admin_referer(self::NONCE_ACTION);

        $exporter = new DealershipUsersCsvExporter();
        $exporter->sendDownloadResponse();
        exit;
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'));
        }

        $download_url = wp_nonce_url(
            add_query_arg(
                array(
                    'page'     => self::PAGE_SLUG,
                    'download' => '1',
                ),
                admin_url('admin.php')
            ),
            self::NONCE_ACTION
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Export Dealership Information', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html__(
            'Download a CSV of all WordPress users with the dealership role: account fields, known profile meta, published car listing counts, and remaining user meta (excluding session tokens).',
            'bricks-child'
        ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($download_url) . '">' . esc_html__('Download CSV', 'bricks-child') . '</a></p>';
        echo '</div>';
    }
}

DealershipExportAdminBootstrap::init();
