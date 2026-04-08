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
    private const NONCE_ACTION = 'dealership_export_csv_post';

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
        if (empty($_POST['page']) || sanitize_text_field(wp_unslash($_POST['page'])) !== self::PAGE_SLUG) {
            return;
        }
        if (empty($_POST['dealership_csv_export'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export dealerships.', 'bricks-child'));
        }

        check_admin_referer(self::NONCE_ACTION);

        $raw = isset($_POST['export_columns']) ? wp_unslash($_POST['export_columns']) : array();
        if (!is_array($raw)) {
            $raw = array();
        }
        $selected = array_map('sanitize_text_field', $raw);
        $columns = DealershipExportColumnCatalog::validateSelection($selected);

        if ($columns === array()) {
            wp_die(
                esc_html__('Select at least one column to export.', 'bricks-child'),
                esc_html__('Export', 'bricks-child'),
                array('response' => 400)
            );
        }

        $exporter = new DealershipUsersCsvExporter();
        $exporter->sendDownloadResponse($columns);
        exit;
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'));
        }

        $columns = DealershipExportColumnCatalog::discover();
        $grouped = self::groupColumnsByRank($columns);

        echo '<div class="wrap dealership-export-wrap">';
        echo '<h1>' . esc_html__('Export Dealership Information', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html__(
            'Choose columns for the CSV. ACF fields use formatted values from get_field(). Raw user meta lists every distinct meta_key found on dealership users (duplicates with ACF field names are hidden here—use the ACF columns for those). Session tokens are never exported.',
            'bricks-child'
        ) . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '" id="dealership-export-form">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';
        echo '<input type="hidden" name="dealership_csv_export" value="1" />';
        wp_nonce_field(self::NONCE_ACTION);

        echo '<p>';
        echo '<button type="button" class="button" id="dealership-export-select-all">' . esc_html__('Select all', 'bricks-child') . '</button> ';
        echo '<button type="button" class="button" id="dealership-export-select-none">' . esc_html__('Select none', 'bricks-child') . '</button>';
        echo '</p>';

        foreach ($grouped as $group_label => $group_columns) {
            echo '<div class="dealership-export-group" style="margin-bottom:1.5em;border:1px solid #c3c4c7;padding:12px;max-height:320px;overflow:auto;background:#fff;">';
            echo '<h2 style="margin-top:0;font-size:14px;">' . esc_html($group_label) . '</h2>';
            echo '<ul style="margin:0;columns:2;column-gap:2rem;list-style:none;padding:0;">';
            foreach ($group_columns as $col) {
                $id = $col['id'];
                $label = self::formatColumnLabel($col);
                echo '<li style="break-inside:avoid;margin-bottom:6px;">';
                echo '<label><input type="checkbox" name="export_columns[]" value="' . esc_attr($id) . '" checked="checked" /> ';
                echo esc_html($label) . '</label>';
                echo '</li>';
            }
            echo '</ul></div>';
        }

        submit_button(__('Download CSV', 'bricks-child'), 'primary', 'submit', false);
        echo '</form>';

        echo '<script>
(function(){
  var form = document.getElementById("dealership-export-form");
  if (!form) return;
  function allBoxes(on) {
    var boxes = form.querySelectorAll("input[name=\'export_columns[]\']");
    for (var i = 0; i < boxes.length; i++) { boxes[i].checked = on; }
  }
  var b1 = document.getElementById("dealership-export-select-all");
  var b2 = document.getElementById("dealership-export-select-none");
  if (b1) b1.addEventListener("click", function(){ allBoxes(true); });
  if (b2) b2.addEventListener("click", function(){ allBoxes(false); });
})();
</script>';

        echo '</div>';
    }

    /**
     * @param list<array{id:string,header:string,source:string,key:string,group:string}> $columns
     * @return array<string, list<array{id:string,header:string,source:string,key:string,group:string}>>
     */
    private static function groupColumnsByRank(array $columns): array
    {
        $buckets = array();
        foreach ($columns as $col) {
            $g = $col['group'] ?? 'other';
            if (!isset($buckets[$g])) {
                $buckets[$g] = array();
            }
            $buckets[$g][] = $col;
        }

        $ordered = array();
        $rank = function ($group_key) {
            if ($group_key === 'core') {
                return 0;
            }
            if ($group_key === 'derived') {
                return 1;
            }
            if (strpos($group_key, 'acf') === 0) {
                return 2;
            }
            if ($group_key === 'user_meta_raw') {
                return 99;
            }

            return 50;
        };

        uksort(
            $buckets,
            function ($a, $b) use ($rank) {
                $ra = $rank($a);
                $rb = $rank($b);
                if ($ra !== $rb) {
                    return $ra - $rb;
                }

                return strcmp($a, $b);
            }
        );

        $labels = array(
            'core'           => __('WordPress user (core)', 'bricks-child'),
            'derived'        => __('Derived', 'bricks-child'),
            'user_meta_raw'  => __('User meta (raw keys)', 'bricks-child'),
        );

        foreach ($buckets as $group_key => $group_columns) {
            $label = isset($labels[$group_key]) ? $labels[$group_key] : $group_key;
            $ordered[$label] = $group_columns;
        }

        return $ordered;
    }

    /**
     * @param array{id:string,header:string,source:string,key:string,group:string} $col
     */
    private static function formatColumnLabel(array $col): string
    {
        $source = $col['source'] ?? '';
        $key = $col['key'] ?? '';
        $header = $col['header'] ?? '';

        if ($source === 'core') {
            return sprintf(/* translators: %s: column key */ __('Core: %s', 'bricks-child'), $header);
        }
        if ($source === 'derived') {
            return sprintf(/* translators: %s: column key */ __('Derived: %s', 'bricks-child'), $header);
        }
        if ($source === 'acf') {
            return sprintf(/* translators: 1: field name, 2: CSV header */ __('ACF: %1$s → %2$s', 'bricks-child'), $key, $header);
        }
        if ($source === 'meta') {
            return sprintf(/* translators: %s: meta key */ __('Meta: %s', 'bricks-child'), $key);
        }

        return $header;
    }
}

DealershipExportAdminBootstrap::init();
