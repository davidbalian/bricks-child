<?php
/**
 * Cars report admin page.
 *
 * Gives a cleanup-focused overview of listing age and inventory health.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/cars-report-bulk-expire-manager.php';

final class CarsReportRepository
{
    private const POST_TYPE = 'car';
    private const SECONDS_PER_DAY = 86400;
    private const VIEWS_META_KEY = 'total_views_count';
    private const PHONE_META_KEY = 'call_button_clicks';
    private const WHATSAPP_META_KEY = 'whatsapp_button_clicks';

    /**
     * @return array{
     *     total:int,
     *     old_total:int,
     *     fresh_total:int,
     *     old_percent:float,
     *     avg_age_days:float,
     *     uploaded_7_days:int,
     *     uploaded_30_days:int
     * }
     */
    public function fetchOverview(int $oldAfterDays): array
    {
        global $wpdb;

        $oldCutoff = gmdate('Y-m-d H:i:s', time() - (self::SECONDS_PER_DAY * $oldAfterDays));
        $daysFloat = (float) $oldAfterDays;

        $query = $wpdb->prepare(
            "
            SELECT
                COUNT(*) AS total_listings,
                SUM(CASE WHEN p.post_date_gmt <= %s THEN 1 ELSE 0 END) AS old_listings,
                SUM(CASE WHEN p.post_date_gmt > %s THEN 1 ELSE 0 END) AS fresh_listings,
                AVG(TIMESTAMPDIFF(DAY, p.post_date_gmt, UTC_TIMESTAMP())) AS average_age_days,
                SUM(CASE WHEN p.post_date_gmt >= (UTC_TIMESTAMP() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS uploaded_7_days,
                SUM(CASE WHEN p.post_date_gmt >= (UTC_TIMESTAMP() - INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS uploaded_30_days
            FROM {$wpdb->posts} AS p
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
            ",
            $oldCutoff,
            $oldCutoff,
            self::POST_TYPE
        );

        $row = $wpdb->get_row($query, ARRAY_A); // phpcs:ignore WordPress.DB
        if (!is_array($row)) {
            return [
                'total' => 0,
                'old_total' => 0,
                'fresh_total' => 0,
                'old_percent' => 0.0,
                'avg_age_days' => 0.0,
                'uploaded_7_days' => 0,
                'uploaded_30_days' => 0,
            ];
        }

        $total = (int) ($row['total_listings'] ?? 0);
        $oldTotal = (int) ($row['old_listings'] ?? 0);
        $freshTotal = (int) ($row['fresh_listings'] ?? 0);
        $oldPercent = $total > 0 ? (($oldTotal / $total) * 100.0) : 0.0;

        return [
            'total' => $total,
            'old_total' => $oldTotal,
            'fresh_total' => $freshTotal,
            'old_percent' => $oldPercent,
            'avg_age_days' => (float) ($row['average_age_days'] ?? $daysFloat),
            'uploaded_7_days' => (int) ($row['uploaded_7_days'] ?? 0),
            'uploaded_30_days' => (int) ($row['uploaded_30_days'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{post_status:string,total:int}>
     */
    public function fetchStatusBreakdown(): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "
            SELECT p.post_status, COUNT(*) AS total
            FROM {$wpdb->posts} AS p
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
            GROUP BY p.post_status
            ORDER BY total DESC
            ",
            self::POST_TYPE
        );

        $rows = $wpdb->get_results($query, ARRAY_A); // phpcs:ignore WordPress.DB
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array{term_name:string,total:int}>
     */
    public function fetchTopMakeBreakdown(int $limit = 10): array
    {
        global $wpdb;

        $safeLimit = max(1, min(30, $limit));
        $taxonomy = 'car_make';
        $query = $wpdb->prepare(
            "
            SELECT t.name AS term_name, COUNT(DISTINCT p.ID) AS total
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->term_relationships} AS tr
                ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} AS tt
                ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} AS t
                ON t.term_id = tt.term_id
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
              AND tt.taxonomy = %s
            GROUP BY t.term_id, t.name
            ORDER BY total DESC
            LIMIT %d
            ",
            self::POST_TYPE,
            $taxonomy,
            $safeLimit
        );

        $rows = $wpdb->get_results($query, ARRAY_A); // phpcs:ignore WordPress.DB
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, object>
     */
    public function fetchOldestListings(int $oldAfterDays, int $limit = 30): array
    {
        global $wpdb;

        $safeLimit = max(5, min(100, $limit));
        $oldCutoff = gmdate('Y-m-d H:i:s', time() - (self::SECONDS_PER_DAY * $oldAfterDays));

        $query = $wpdb->prepare(
            "
            SELECT
                p.ID,
                p.post_title,
                p.post_status,
                p.post_date_gmt,
                TIMESTAMPDIFF(DAY, p.post_date_gmt, UTC_TIMESTAMP()) AS age_days,
                COALESCE(CAST(views_meta.meta_value AS UNSIGNED), 0) AS page_views,
                (
                    COALESCE(CAST(phone_meta.meta_value AS UNSIGNED), 0) +
                    COALESCE(CAST(wa_meta.meta_value AS UNSIGNED), 0)
                ) AS contact_clicks
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->postmeta} AS views_meta
                ON views_meta.post_id = p.ID
                AND views_meta.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} AS phone_meta
                ON phone_meta.post_id = p.ID
                AND phone_meta.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} AS wa_meta
                ON wa_meta.post_id = p.ID
                AND wa_meta.meta_key = %s
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
              AND p.post_date_gmt <= %s
            ORDER BY p.post_date_gmt ASC
            LIMIT %d
            ",
            self::VIEWS_META_KEY,
            self::PHONE_META_KEY,
            self::WHATSAPP_META_KEY,
            self::POST_TYPE,
            $oldCutoff,
            $safeLimit
        );

        $rows = $wpdb->get_results($query); // phpcs:ignore WordPress.DB
        return is_array($rows) ? $rows : [];
    }

    /**
     * Count cars by listing_state meta (same post_status scope as overview: publish, pending, draft).
     *
     * "active" = all cars in scope minus sold and expired (includes missing listing_state or other values).
     *
     * @return array{total:int,sold:int,expired:int,active:int}
     */
    public function fetchListingStateCounts(): array
    {
        global $wpdb;

        $meta_key = class_exists('ListingStateManager') ? ListingStateManager::FIELD_NAME : 'listing_state';

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$wpdb->posts} AS p
                WHERE p.post_type = %s
                  AND p.post_status IN ('publish', 'pending', 'draft')
                ",
                self::POST_TYPE
            )
        );

        $sold_sql = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS m
                ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = 'sold'
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
        ";
        $sold = (int) $wpdb->get_var($wpdb->prepare($sold_sql, $meta_key, self::POST_TYPE)); // phpcs:ignore WordPress.DB

        $expired_sql = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS m
                ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = 'expired'
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
        ";
        $expired = (int) $wpdb->get_var($wpdb->prepare($expired_sql, $meta_key, self::POST_TYPE)); // phpcs:ignore WordPress.DB

        $active = max(0, $total - $sold - $expired);

        return array(
            'total'   => $total,
            'sold'    => $sold,
            'expired' => $expired,
            'active'  => $active,
        );
    }

    /**
     * Cars with listing_state = sold|expired (publish, pending, draft).
     *
     * @return array<int, object>
     */
    public function fetchListingsByListingState(string $state, int $limit = 300): array
    {
        $allowed = array('sold', 'expired');
        if (! in_array($state, $allowed, true)) {
            return array();
        }

        global $wpdb;

        $safe_limit = max(1, min(500, $limit));
        $meta_key   = class_exists('ListingStateManager') ? ListingStateManager::FIELD_NAME : 'listing_state';

        $query = $wpdb->prepare(
            "
            SELECT
                p.ID,
                p.post_title,
                p.post_status,
                p.post_date_gmt,
                TIMESTAMPDIFF(DAY, p.post_date_gmt, UTC_TIMESTAMP()) AS age_days,
                COALESCE(CAST(views_meta.meta_value AS UNSIGNED), 0) AS page_views,
                (
                    COALESCE(CAST(phone_meta.meta_value AS UNSIGNED), 0) +
                    COALESCE(CAST(wa_meta.meta_value AS UNSIGNED), 0)
                ) AS contact_clicks
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS ls
                ON ls.post_id = p.ID AND ls.meta_key = %s AND ls.meta_value = %s
            LEFT JOIN {$wpdb->postmeta} AS views_meta
                ON views_meta.post_id = p.ID
                AND views_meta.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} AS phone_meta
                ON phone_meta.post_id = p.ID
                AND phone_meta.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} AS wa_meta
                ON wa_meta.post_id = p.ID
                AND wa_meta.meta_key = %s
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
            ORDER BY p.post_modified_gmt DESC
            LIMIT %d
            ",
            $meta_key,
            $state,
            self::VIEWS_META_KEY,
            self::PHONE_META_KEY,
            self::WHATSAPP_META_KEY,
            self::POST_TYPE,
            $safe_limit
        );

        $rows = $wpdb->get_results($query); // phpcs:ignore WordPress.DB
        return is_array($rows) ? $rows : array();
    }
}

final class CarsSeoInventoryCsvExporter
{
    private const ACTION = 'bricks_child_cars_seo_inventory_export';
    private const NONCE_ACTION = 'bricks_child_cars_seo_inventory_export';
    private const POST_TYPE = 'car';
    private const BATCH_SIZE = 500;

    /** @return string */
    public static function actionName(): string
    {
        return self::ACTION;
    }

    /** @return string */
    public static function nonceAction(): string
    {
        return self::NONCE_ACTION;
    }

    public function stream(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export SEO inventory.', 'bricks-child'), '', array('response' => 403));
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::NONCE_ACTION)) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="autoagora-seo-inventory-' . gmdate('Y-m-d-His') . '.csv"');
        header('X-Content-Type-Options: nosniff');

        $output = fopen('php://output', 'w');
        if (! is_resource($output)) {
            wp_die(esc_html__('Could not open CSV output stream.', 'bricks-child'), '', array('response' => 500));
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $this->headers());

        $last_id = 0;
        while (true) {
            $rows = $this->fetchPostBatch($last_id, self::BATCH_SIZE);
            if (empty($rows)) {
                break;
            }

            $ids = array_map(
                static function ($row): int {
                    return (int) $row->ID;
                },
                $rows
            );
            update_meta_cache('post', $ids);
            $terms_by_post = $this->fetchMakeModelTerms($ids);

            foreach ($rows as $row) {
                $post_id = (int) $row->ID;
                fputcsv($output, $this->buildCsvRow($row, $terms_by_post[$post_id] ?? array()));
                $last_id = $post_id;
            }

            fflush($output);
        }

        fclose($output);
        exit;
    }

    /** @return array<int, string> */
    private function headers(): array
    {
        return array(
            'listing_id',
            'post_status',
            'listing_state',
            'title',
            'permalink',
            'edit_url',
            'make',
            'make_slug',
            'model',
            'model_slug',
            'price',
            'year',
            'mileage',
            'post_date',
            'publication_date',
            'modified_date',
            'listing_age_days',
            'modified_age_days',
            'views',
            'unique_views',
            'phone_clicks',
            'whatsapp_clicks',
            'contact_clicks',
            'contact_rate_percent',
            'dealer_user_id',
            'dealer_display_name',
            'price_insight_band',
            'price_insight_computed_at',
            'price_insight_cohort_n',
            'price_insight_median',
            'price_vs_median_pct',
            'price_insight_cohort_key',
            'listing_rank_score',
            'listing_rank_updated_at',
            'listing_rank_recency_bucket',
            'last_refresh_date',
            'refresh_count',
            'popular_badge',
            'full_details_badge',
            'extra_details_badge',
        );
    }

    /**
     * @return array<int, object>
     */
    private function fetchPostBatch(int $last_id, int $limit): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "
            SELECT
                p.ID,
                p.post_title,
                p.post_status,
                p.post_author,
                p.post_date_gmt,
                p.post_modified_gmt,
                NULLIF(u.display_name, '') AS dealer_display_name
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->users} AS u
                ON u.ID = p.post_author
            WHERE p.ID > %d
              AND p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
            ORDER BY p.ID ASC
            LIMIT %d
            ",
            $last_id,
            self::POST_TYPE,
            $limit
        );

        $rows = $wpdb->get_results($query); // phpcs:ignore WordPress.DB
        return is_array($rows) ? $rows : array();
    }

    /**
     * @param array<int, int> $post_ids
     * @return array<int, array{make:array<int,string>,make_slug:array<int,string>,model:array<int,string>,model_slug:array<int,string>}>
     */
    private function fetchMakeModelTerms(array $post_ids): array
    {
        $post_ids = array_values(array_filter(array_map('absint', $post_ids)));
        if (empty($post_ids)) {
            return array();
        }

        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $sql = "
            SELECT
                tr.object_id,
                t.name,
                t.slug,
                tt.parent,
                parent_t.name AS parent_name,
                parent_t.slug AS parent_slug
            FROM {$wpdb->term_relationships} AS tr
            INNER JOIN {$wpdb->term_taxonomy} AS tt
                ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} AS t
                ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->terms} AS parent_t
                ON parent_t.term_id = tt.parent
            WHERE tr.object_id IN ({$placeholders})
              AND tt.taxonomy = %s
            ORDER BY tt.parent ASC, t.name ASC
            ";
        $query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $post_ids, array('car_make')));

        $rows = $wpdb->get_results($query, ARRAY_A); // phpcs:ignore WordPress.DB
        if (! is_array($rows)) {
            return array();
        }

        $map = array();
        foreach ($rows as $term) {
            $post_id = (int) ($term['object_id'] ?? 0);
            if ($post_id <= 0) {
                continue;
            }
            if (! isset($map[$post_id])) {
                $map[$post_id] = array(
                    'make'       => array(),
                    'make_slug'  => array(),
                    'model'      => array(),
                    'model_slug' => array(),
                );
            }

            $parent = (int) ($term['parent'] ?? 0);
            if ($parent === 0) {
                $map[$post_id]['make'][]      = (string) ($term['name'] ?? '');
                $map[$post_id]['make_slug'][] = (string) ($term['slug'] ?? '');
                continue;
            }

            $parent_name = (string) ($term['parent_name'] ?? '');
            $parent_slug = (string) ($term['parent_slug'] ?? '');
            if ($parent_name !== '') {
                $map[$post_id]['make'][] = $parent_name;
            }
            if ($parent_slug !== '') {
                $map[$post_id]['make_slug'][] = $parent_slug;
            }
            $map[$post_id]['model'][]      = (string) ($term['name'] ?? '');
            $map[$post_id]['model_slug'][] = (string) ($term['slug'] ?? '');
        }

        foreach ($map as $post_id => $data) {
            foreach ($data as $key => $values) {
                $map[$post_id][$key] = array_values(array_unique(array_filter($values, 'strlen')));
            }
        }

        return $map;
    }

    /**
     * @param object                                                                                                    $row
     * @param array{make?:array<int,string>,make_slug?:array<int,string>,model?:array<int,string>,model_slug?:array<int,string>} $terms
     * @return array<int, string|int|float>
     */
    private function buildCsvRow(object $row, array $terms): array
    {
        $post_id = (int) $row->ID;
        $views = $this->intMeta($post_id, 'total_views_count');
        $phone = $this->intMeta($post_id, 'call_button_clicks');
        $whatsapp = $this->intMeta($post_id, 'whatsapp_button_clicks');
        $contact_clicks = $phone + $whatsapp;

        $publication_date = (string) get_post_meta($post_id, 'publication_date', true);
        $listing_age_timestamp = $publication_date !== ''
            ? strtotime($publication_date)
            : $this->mysqlGmtTimestamp((string) ($row->post_date_gmt ?? ''));

        return array(
            $post_id,
            (string) ($row->post_status ?? ''),
            $this->listingState($post_id),
            (string) ($row->post_title ?? ''),
            (string) get_permalink($post_id),
            (string) get_edit_post_link($post_id, 'raw'),
            $this->joinTerms($terms['make'] ?? array()),
            $this->joinTerms($terms['make_slug'] ?? array()),
            $this->joinTerms($terms['model'] ?? array()),
            $this->joinTerms($terms['model_slug'] ?? array()),
            $this->rawMeta($post_id, 'price'),
            $this->rawMeta($post_id, 'year'),
            $this->rawMeta($post_id, 'mileage'),
            $this->formatGmtDate((string) ($row->post_date_gmt ?? '')),
            $publication_date,
            $this->formatGmtDate((string) ($row->post_modified_gmt ?? '')),
            $this->ageDays($listing_age_timestamp),
            $this->ageDays($this->mysqlGmtTimestamp((string) ($row->post_modified_gmt ?? ''))),
            $views,
            $this->intMeta($post_id, 'unique_views_count'),
            $phone,
            $whatsapp,
            $contact_clicks,
            $this->contactRatePercent($contact_clicks, $views),
            (int) ($row->post_author ?? 0),
            (string) ($row->dealer_display_name ?? ''),
            $this->rawMeta($post_id, 'price_insight_band'),
            $this->rawMeta($post_id, 'price_insight_computed_at'),
            $this->rawMeta($post_id, 'price_insight_cohort_n'),
            $this->rawMeta($post_id, 'price_insight_median'),
            $this->rawMeta($post_id, 'price_vs_median_pct'),
            $this->rawMeta($post_id, 'price_insight_cohort_key'),
            $this->rawMeta($post_id, 'listing_rank_score'),
            $this->rawMeta($post_id, 'listing_rank_updated_at'),
            $this->rawMeta($post_id, 'listing_rank_recency_bucket'),
            $this->rawMeta($post_id, 'last_refresh_date'),
            $this->rawMeta($post_id, 'refresh_count'),
            $this->rawMeta($post_id, 'popular_badge'),
            $this->rawMeta($post_id, 'fulldetailsbadge'),
            $this->rawMeta($post_id, 'extradetailsbadge'),
        );
    }

    private function listingState(int $post_id): string
    {
        if (class_exists('ListingStateManager')) {
            return ListingStateManager::resolve_state($post_id);
        }

        $state = strtolower(trim((string) get_post_meta($post_id, 'listing_state', true)));
        return in_array($state, array('active', 'sold', 'expired'), true) ? $state : 'active';
    }

    private function rawMeta(int $post_id, string $key): string
    {
        $value = get_post_meta($post_id, $key, true);
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        $encoded = wp_json_encode($value);
        return is_string($encoded) ? $encoded : '';
    }

    private function intMeta(int $post_id, string $key): int
    {
        return max(0, (int) get_post_meta($post_id, $key, true));
    }

    /** @param array<int, string> $terms */
    private function joinTerms(array $terms): string
    {
        return implode(' | ', array_values(array_unique(array_filter($terms, 'strlen'))));
    }

    private function contactRatePercent(int $contacts, int $views): string
    {
        if ($views <= 0) {
            return '';
        }

        return number_format(($contacts / $views) * 100, 2, '.', '');
    }

    private function ageDays($timestamp): string
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        return (string) max(0, (int) floor((time() - $timestamp) / DAY_IN_SECONDS));
    }

    private function mysqlGmtTimestamp(string $date): int
    {
        if ($date === '' || $date === '0000-00-00 00:00:00') {
            return 0;
        }

        $timestamp = strtotime($date . ' UTC');
        return $timestamp ? (int) $timestamp : 0;
    }

    private function formatGmtDate(string $date): string
    {
        if ($date === '' || $date === '0000-00-00 00:00:00') {
            return '';
        }

        return get_date_from_gmt($date, 'Y-m-d H:i:s');
    }
}

final class CarsReportAdminPage
{
    private const SLUG = 'cars-report';

    private const TAB_OVERVIEW = 'overview';

    private const TAB_EXPIRED = 'expired';

    private const TAB_SOLD = 'sold';
    private const DEFAULT_OLD_DAYS = 90;
    private const MIN_OLD_DAYS = 15;
    private const MAX_OLD_DAYS = 365;

    private CarsReportRepository $repository;

    private function __construct(CarsReportRepository $repository)
    {
        $this->repository = $repository;
    }

    public static function bootstrap(): void
    {
        $instance = new self(new CarsReportRepository());
        add_action('admin_menu', [$instance, 'registerSubmenu']);
        add_action('admin_init', [$instance, 'handleBulkExpirePost']);
        add_action('admin_post_' . CarsSeoInventoryCsvExporter::actionName(), [$instance, 'handleSeoInventoryCsvExport']);
    }

    public function registerSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=car',
            __('Cars Report', 'bricks-child'),
            __('Cars Report', 'bricks-child'),
            'manage_options',
            self::SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'bricks-child'));
        }

        $oldAfterDays = $this->resolveOldAfterDays();
        $tab          = $this->resolveCurrentTab();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cars report (marketplace cleanup)', 'bricks-child'); ?></h1>
            <p class="description">
                <?php esc_html_e('Use this report to detect aging inventory, monitor upload flow, and prioritize listing cleanup.', 'bricks-child'); ?>
            </p>
            <?php $this->renderSeoInventoryExportForm(); ?>

            <?php $this->renderTabsNav($tab, $oldAfterDays); ?>

            <?php if ($tab === self::TAB_OVERVIEW) : ?>
                <?php $this->renderOverviewTab($oldAfterDays); ?>
            <?php elseif ($tab === self::TAB_EXPIRED) : ?>
                <?php
                $rows = $this->repository->fetchListingsByListingState('expired');
                $this->renderStateListingsSection(
                    __('Expired listings (listing_state)', 'bricks-child'),
                    __('No cars with listing_state = expired.', 'bricks-child'),
                    $rows
                );
                ?>
            <?php else : ?>
                <?php
                $rows = $this->repository->fetchListingsByListingState('sold');
                $this->renderStateListingsSection(
                    __('Sold listings (listing_state)', 'bricks-child'),
                    __('No cars with listing_state = sold.', 'bricks-child'),
                    $rows
                );
                ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderOverviewTab(int $oldAfterDays): void
    {
        $overview           = $this->repository->fetchOverview($oldAfterDays);
        $statusRows         = $this->repository->fetchStatusBreakdown();
        $topMakes           = $this->repository->fetchTopMakeBreakdown(10);
        $oldestRows         = $this->repository->fetchOldestListings($oldAfterDays, 30);
        $listingStateCounts = $this->repository->fetchListingStateCounts();

        $this->renderBulkExpireNotice();

        ?>
        <form method="get" style="margin: 1rem 0 1.25rem; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="post_type" value="car" />
            <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>" />
            <input type="hidden" name="tab" value="<?php echo esc_attr(self::TAB_OVERVIEW); ?>" />
            <label>
                <?php esc_html_e('Consider listing old after (days)', 'bricks-child'); ?><br />
                <input type="number" name="old_after_days" min="<?php echo esc_attr(self::MIN_OLD_DAYS); ?>" max="<?php echo esc_attr(self::MAX_OLD_DAYS); ?>" value="<?php echo esc_attr($oldAfterDays); ?>" />
            </label>
            <?php submit_button(__('Apply', 'bricks-child'), 'secondary', '', false); ?>
        </form>

        <?php $this->renderBulkExpireForm($oldAfterDays); ?>

        <?php $this->renderOverviewCards($overview, $oldAfterDays, $listingStateCounts); ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;margin-top:16px;">
            <?php $this->renderStatusTable($statusRows); ?>
            <?php $this->renderTopMakesTable($topMakes); ?>
        </div>

        <h2 style="margin-top: 1.5rem;"><?php esc_html_e('Oldest listings (priority actions)', 'bricks-child'); ?></h2>
        <?php
        $this->renderListingsDataTable(
            $oldestRows,
            __('No old listings for this threshold.', 'bricks-child')
        );
    }

    /**
     * @param array<int, object> $rows
     */
    private function renderStateListingsSection(string $title, string $empty_message, array $rows): void
    {
        ?>
        <h2 style="margin-top: 1rem;"><?php echo esc_html($title); ?></h2>
        <p class="description">
            <?php esc_html_e('WordPress post status (publish / pending / draft) is shown in the table; listing_state is the ACF field used on the marketplace.', 'bricks-child'); ?>
        </p>
        <?php
        $this->renderListingsDataTable($rows, $empty_message);
    }

    /**
     * @param array<int, object> $rows
     */
    private function renderListingsDataTable(array $rows, string $empty_message): void
    {
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Listing', 'bricks-child'); ?></th>
                    <th><?php esc_html_e('Age (days)', 'bricks-child'); ?></th>
                    <th><?php esc_html_e('Post status', 'bricks-child'); ?></th>
                    <th><?php esc_html_e('Views', 'bricks-child'); ?></th>
                    <th><?php esc_html_e('Contact clicks', 'bricks-child'); ?></th>
                    <th><?php esc_html_e('Actions', 'bricks-child'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)) : ?>
                <tr>
                    <td colspan="6"><?php echo esc_html($empty_message); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($rows as $listing) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($listing->post_title ?: sprintf(__('Listing #%d', 'bricks-child'), $listing->ID)); ?></strong>
                            <div style="font-size:12px;color:#646970;">
                                <?php echo esc_html(get_date_from_gmt((string) $listing->post_date_gmt, 'Y-m-d H:i')); ?>
                            </div>
                        </td>
                        <td><?php echo esc_html(number_format_i18n((int) ($listing->age_days ?? 0))); ?></td>
                        <td><?php echo esc_html($this->humanizeStatus((string) ($listing->post_status ?? ''))); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) ($listing->page_views ?? 0))); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) ($listing->contact_clicks ?? 0))); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(get_edit_post_link((int) $listing->ID)); ?>">
                                <?php esc_html_e('Edit', 'bricks-child'); ?>
                            </a>
                            <a class="button button-small" href="<?php echo esc_url(get_permalink((int) $listing->ID)); ?>" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('View', 'bricks-child'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderTabsNav(string $current_tab, int $oldAfterDays): void
    {
        $tabs = array(
            self::TAB_OVERVIEW => __('Overview', 'bricks-child'),
            self::TAB_EXPIRED  => __('Expired', 'bricks-child'),
            self::TAB_SOLD     => __('Sold', 'bricks-child'),
        );
        ?>
        <h2 class="nav-tab-wrapper" style="margin-bottom: 1rem;">
            <?php foreach ($tabs as $slug => $label) : ?>
                <a
                    href="<?php echo esc_url($this->carsReportTabUrl($slug, $oldAfterDays)); ?>"
                    class="nav-tab<?php echo $current_tab === $slug ? ' nav-tab-active' : ''; ?>"
                >
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </h2>
        <?php
    }

    private function carsReportTabUrl(string $tab, int $oldAfterDays): string
    {
        return add_query_arg(
            array(
                'post_type'      => 'car',
                'page'           => self::SLUG,
                'tab'            => $tab,
                'old_after_days' => $oldAfterDays,
            ),
            admin_url('edit.php')
        );
    }

    private function resolveCurrentTab(): string
    {
        if (! isset($_GET['tab'])) {
            return self::TAB_OVERVIEW;
        }
        $tab = sanitize_key((string) wp_unslash($_GET['tab']));
        if (in_array($tab, array(self::TAB_OVERVIEW, self::TAB_EXPIRED, self::TAB_SOLD), true)) {
            return $tab;
        }

        return self::TAB_OVERVIEW;
    }

    /**
     * @param array{total:int,old_total:int,fresh_total:int,old_percent:float,avg_age_days:float,uploaded_7_days:int,uploaded_30_days:int} $overview
     * @param array{total:int,sold:int,expired:int,active:int}                                      $listingStateCounts
     */
    private function renderOverviewCards(array $overview, int $oldAfterDays, array $listingStateCounts): void
    {
        $oldPercentClass = $overview['old_percent'] >= 50.0 ? 'notice-error' : ($overview['old_percent'] >= 30.0 ? 'notice-warning' : 'notice-success');
        ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;">
            <?php $this->renderCard(__('Total listings', 'bricks-child'), number_format_i18n($overview['total']), 'notice-info'); ?>
            <?php $this->renderCard(sprintf(__('Old listings (%d+ days)', 'bricks-child'), $oldAfterDays), number_format_i18n($overview['old_total']), $oldPercentClass); ?>
            <?php $this->renderCard(__('Old share', 'bricks-child'), sprintf('%s%%', number_format_i18n($overview['old_percent'], 2)), $oldPercentClass); ?>
            <?php $this->renderCard(__('Uploaded last 7 days', 'bricks-child'), number_format_i18n($overview['uploaded_7_days']), 'notice-info'); ?>
            <?php $this->renderCard(__('Uploaded last 30 days', 'bricks-child'), number_format_i18n($overview['uploaded_30_days']), 'notice-info'); ?>
            <?php $this->renderCard(__('Average listing age', 'bricks-child'), sprintf(_x('%s days', 'listing age in days', 'bricks-child'), number_format_i18n($overview['avg_age_days'], 1)), 'notice-info'); ?>
        </div>

        <h2 style="margin: 1.5rem 0 0.5rem;"><?php esc_html_e('By listing_state', 'bricks-child'); ?></h2>
        <p class="description" style="margin-top: 0;">
            <?php esc_html_e('Counts use the same cars as “Total listings” (publish, pending, draft). Active = all of those minus sold and expired (includes cars with no listing_state set).', 'bricks-child'); ?>
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:4px;">
            <?php
            $this->renderCard(
                __('Active', 'bricks-child'),
                number_format_i18n((int) ($listingStateCounts['active'] ?? 0)),
                'notice-success'
            );
            $this->renderCard(
                __('Sold', 'bricks-child'),
                number_format_i18n((int) ($listingStateCounts['sold'] ?? 0)),
                'notice-warning'
            );
            $this->renderCard(
                __('Expired', 'bricks-child'),
                number_format_i18n((int) ($listingStateCounts['expired'] ?? 0)),
                'notice-error'
            );
            ?>
        </div>
        <?php
    }

    private function renderCard(string $label, string $value, string $noticeClass): void
    {
        ?>
        <div class="notice <?php echo esc_attr($noticeClass); ?>" style="margin:0;padding:12px 14px;">
            <div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html($label); ?></div>
            <div style="font-size:28px;font-weight:600;line-height:1.2;"><?php echo esc_html($value); ?></div>
        </div>
        <?php
    }

    /**
     * @param array<int, array{post_status:string,total:int}> $rows
     */
    private function renderStatusTable(array $rows): void
    {
        ?>
        <div>
            <h2><?php esc_html_e('Listings by status', 'bricks-child'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Status', 'bricks-child'); ?></th><th><?php esc_html_e('Total', 'bricks-child'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($this->humanizeStatus((string) ($row['post_status'] ?? ''))); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) ($row['total'] ?? 0))); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param array<int, array{term_name:string,total:int}> $rows
     */
    private function renderTopMakesTable(array $rows): void
    {
        ?>
        <div>
            <h2><?php esc_html_e('Top car makes by volume', 'bricks-child'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Car make', 'bricks-child'); ?></th><th><?php esc_html_e('Total', 'bricks-child'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('No car make data found.', 'bricks-child'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($row['term_name'] ?? '')); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) ($row['total'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handleBulkExpirePost(): void
    {
        if (! isset($_POST['brick_child_cars_report_bulk_expire'])) {
            return;
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'brick_child_cars_report_bulk_expire')) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'), '', array('response' => 403));
        }

        if (! isset($_POST['cars_report_page']) || sanitize_key((string) wp_unslash($_POST['cars_report_page'])) !== self::SLUG) {
            return;
        }

        $days = isset($_POST['old_after_days']) ? absint(wp_unslash($_POST['old_after_days'])) : self::DEFAULT_OLD_DAYS;
        if ($days < self::MIN_OLD_DAYS) {
            $days = self::MIN_OLD_DAYS;
        }
        if ($days > self::MAX_OLD_DAYS) {
            $days = self::MAX_OLD_DAYS;
        }

        $manager = new CarsReportBulkExpireManager();
        $updated = $manager->expirePublishedCarsOlderThanDays($days);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post_type'      => 'car',
                    'page'           => self::SLUG,
                    'tab'            => self::TAB_OVERVIEW,
                    'old_after_days' => $days,
                    'cars_expired'   => $updated,
                ),
                admin_url('edit.php')
            )
        );
        exit;
    }

    public function handleSeoInventoryCsvExport(): void
    {
        $exporter = new CarsSeoInventoryCsvExporter();
        $exporter->stream();
    }

    private function renderSeoInventoryExportForm(): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 1rem 0 1.25rem;">
            <input type="hidden" name="action" value="<?php echo esc_attr(CarsSeoInventoryCsvExporter::actionName()); ?>" />
            <?php wp_nonce_field(CarsSeoInventoryCsvExporter::nonceAction()); ?>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Download SEO Inventory CSV', 'bricks-child'); ?>
            </button>
            <span class="description" style="margin-left: 8px;">
                <?php esc_html_e('Exports publish, pending, and draft cars with listing_state, make/model, engagement, dealer, price insight, rank, and freshness fields. User emails are excluded.', 'bricks-child'); ?>
            </span>
        </form>
        <?php
    }

    private function renderBulkExpireNotice(): void
    {
        if (! isset($_GET['cars_expired'])) {
            return;
        }
        $count = absint(wp_unslash($_GET['cars_expired']));
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: number of listings */
                        _n('%d listing was marked expired (listing_state).', '%d listings were marked expired (listing_state).', $count, 'bricks-child'),
                        $count
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    private function renderBulkExpireForm(int $oldAfterDays): void
    {
        ?>
        <div style="margin: 0 0 1.25rem; padding: 12px 14px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; max-width: 720px;">
            <h2 style="margin: 0 0 8px; font-size: 14px;"><?php esc_html_e('Bulk expire by activity age', 'bricks-child'); ?></h2>
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e('Sets listing_state to expired for matching published cars (they stay published but are hidden from the marketplace). Uses publication_date when set; otherwise the post date. Skips sold and already-expired.', 'bricks-child'); ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('edit.php')); ?>">
                <?php wp_nonce_field('brick_child_cars_report_bulk_expire'); ?>
                <input type="hidden" name="post_type" value="car" />
                <input type="hidden" name="cars_report_page" value="<?php echo esc_attr(self::SLUG); ?>" />
                <input type="hidden" name="tab" value="<?php echo esc_attr(self::TAB_OVERVIEW); ?>" />
                <input type="hidden" name="old_after_days" value="<?php echo esc_attr((string) $oldAfterDays); ?>" />
                <button
                    type="submit"
                    name="brick_child_cars_report_bulk_expire"
                    value="1"
                    class="button button-secondary"
                    onclick="return confirm('<?php echo esc_js(__('Mark all matching listings as expired (listing_state)? They remain published; sellers can set listing_state back to active after editing.', 'bricks-child')); ?>');"
                >
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: day threshold */
                            __('Expire published listings older than %d days', 'bricks-child'),
                            $oldAfterDays
                        )
                    );
                    ?>
                </button>
            </form>
        </div>
        <?php
    }

    private function resolveOldAfterDays(): int
    {
        $value = isset($_GET['old_after_days']) ? absint($_GET['old_after_days']) : self::DEFAULT_OLD_DAYS;
        if ($value < self::MIN_OLD_DAYS) {
            return self::MIN_OLD_DAYS;
        }
        if ($value > self::MAX_OLD_DAYS) {
            return self::MAX_OLD_DAYS;
        }
        return $value;
    }

    private function humanizeStatus(string $status): string
    {
        if ($status === '') {
            return __('Unknown', 'bricks-child');
        }
        $label = get_post_status_object($status);
        return $label && isset($label->label) ? (string) $label->label : ucfirst($status);
    }
}

CarsReportAdminPage::bootstrap();
