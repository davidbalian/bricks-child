<?php
/**
 * Cars report admin page.
 *
 * Gives a cleanup-focused overview of listing age and inventory health.
 */
if (!defined('ABSPATH')) {
    exit;
}

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
}

final class CarsReportAdminPage
{
    private const SLUG = 'cars-report';
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
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'bricks-child'));
        }

        $oldAfterDays = $this->resolveOldAfterDays();
        $overview = $this->repository->fetchOverview($oldAfterDays);
        $statusRows = $this->repository->fetchStatusBreakdown();
        $topMakes = $this->repository->fetchTopMakeBreakdown(10);
        $oldestRows = $this->repository->fetchOldestListings($oldAfterDays, 30);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cars report (marketplace cleanup)', 'bricks-child'); ?></h1>
            <p class="description">
                <?php esc_html_e('Use this report to detect aging inventory, monitor upload flow, and prioritize listing cleanup.', 'bricks-child'); ?>
            </p>

            <form method="get" style="margin: 1rem 0 1.25rem; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="post_type" value="car" />
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>" />
                <label>
                    <?php esc_html_e('Consider listing old after (days)', 'bricks-child'); ?><br />
                    <input type="number" name="old_after_days" min="<?php echo esc_attr(self::MIN_OLD_DAYS); ?>" max="<?php echo esc_attr(self::MAX_OLD_DAYS); ?>" value="<?php echo esc_attr($oldAfterDays); ?>" />
                </label>
                <?php submit_button(__('Apply', 'bricks-child'), 'secondary', '', false); ?>
            </form>

            <?php $this->renderOverviewCards($overview, $oldAfterDays); ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;margin-top:16px;">
                <?php $this->renderStatusTable($statusRows); ?>
                <?php $this->renderTopMakesTable($topMakes); ?>
            </div>

            <h2 style="margin-top: 1.5rem;"><?php esc_html_e('Oldest listings (priority actions)', 'bricks-child'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Listing', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Age (days)', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Status', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Views', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Contact clicks', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Actions', 'bricks-child'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($oldestRows)) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No old listings for this threshold.', 'bricks-child'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($oldestRows as $listing) : ?>
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
        </div>
        <?php
    }

    /**
     * @param array{total:int,old_total:int,fresh_total:int,old_percent:float,avg_age_days:float,uploaded_7_days:int,uploaded_30_days:int} $overview
     */
    private function renderOverviewCards(array $overview, int $oldAfterDays): void
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
