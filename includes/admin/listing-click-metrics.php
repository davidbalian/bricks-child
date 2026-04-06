<?php
/**
 * Listing Click Metrics Admin Page
 *
 * Displays WhatsApp click rate (WCR), listing page views, and WhatsApp clicks
 * for car listings. Phone/call counts remain in the database for other features.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository responsible for fetching listing click stats.
 */
final class ListingClickMetricsRepository
{
    private const WHATSAPP_META_KEY = 'whatsapp_button_clicks';
    /** Cached total listing page loads (see CarViewsDatabase, view_type = total). */
    private const PAGE_VIEWS_META_KEY = 'total_views_count';
    private const POST_TYPE = 'car';

    /**
     * Sum listing page views and WhatsApp clicks across all car listings (same scope as the table).
     *
     * @return array{total_page_views: int, total_whatsapp_clicks: int}
     */
    public function fetchSiteWideTotals(): array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT
                    COALESCE(SUM(CAST(pm_v.meta_value AS UNSIGNED)), 0) AS total_page_views,
                    COALESCE(SUM(CAST(pm_w.meta_value AS UNSIGNED)), 0) AS total_whatsapp_clicks
                FROM {$wpdb->posts} AS p
                LEFT JOIN {$wpdb->postmeta} AS pm_v
                    ON pm_v.post_id = p.ID
                    AND pm_v.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} AS pm_w
                    ON pm_w.post_id = p.ID
                    AND pm_w.meta_key = %s
                WHERE p.post_type = %s
                  AND p.post_status IN ('publish', 'pending', 'draft')
                ",
                self::PAGE_VIEWS_META_KEY,
                self::WHATSAPP_META_KEY,
                self::POST_TYPE
            ),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB

        if (!is_array($row)) {
            return [
                'total_page_views' => 0,
                'total_whatsapp_clicks' => 0,
            ];
        }

        return [
            'total_page_views' => (int) ($row['total_page_views'] ?? 0),
            'total_whatsapp_clicks' => (int) ($row['total_whatsapp_clicks'] ?? 0),
        ];
    }

    /**
     * Fetch listing stats sorted by the requested metric.
     *
     * @param string $orderBy whatsapp|views|wcr
     * @param int    $limit   Maximum rows to return.
     *
     * @return array<int, object>
     */
    public function fetchListings(string $orderBy, int $limit): array
    {
        global $wpdb;

        $orderSql = $this->determineOrderSql($orderBy);

        $query = $wpdb->prepare(
            "
            SELECT
                p.ID,
                p.post_title,
                COALESCE(
                    author.user_login,
                    CONCAT(%s, p.post_author)
                ) AS poster_username,
                NULLIF(author.display_name, '') AS poster_display_name,
                NULLIF(first_name_meta.meta_value, '') AS poster_first_name,
                NULLIF(last_name_meta.meta_value, '') AS poster_last_name,
                COALESCE(CAST(wa_meta.meta_value AS UNSIGNED), 0)        AS whatsapp_clicks,
                COALESCE(CAST(views_meta.meta_value AS UNSIGNED), 0)     AS page_views
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->users} AS author
                ON author.ID = p.post_author
            LEFT JOIN {$wpdb->usermeta} AS first_name_meta
                ON first_name_meta.user_id = author.ID
                AND first_name_meta.meta_key = 'first_name'
            LEFT JOIN {$wpdb->usermeta} AS last_name_meta
                ON last_name_meta.user_id = author.ID
                AND last_name_meta.meta_key = 'last_name'
            LEFT JOIN {$wpdb->postmeta} AS wa_meta
                ON wa_meta.post_id = p.ID
                AND wa_meta.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} AS views_meta
                ON views_meta.post_id = p.ID
                AND views_meta.meta_key = %s
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
            ORDER BY {$orderSql}, p.post_date DESC
            LIMIT %d
            ",
            esc_html__('User #', 'bricks-child'),
            self::WHATSAPP_META_KEY,
            self::PAGE_VIEWS_META_KEY,
            self::POST_TYPE,
            $limit
        );

        $results = $wpdb->get_results($query); // phpcs:ignore WordPress.DB

        return is_array($results) ? $results : [];
    }

    /**
     * Whitelisted ORDER BY fragments (never user-controlled).
     */
    private function determineOrderSql(string $orderBy): string
    {
        $map = [
            'whatsapp' => 'whatsapp_clicks DESC',
            'views' => 'page_views DESC',
            'wcr' => '(COALESCE(CAST(wa_meta.meta_value AS UNSIGNED), 0) / NULLIF(COALESCE(CAST(views_meta.meta_value AS UNSIGNED), 0), 0)) DESC',
        ];

        return $map[$orderBy] ?? $map['wcr'];
    }
}

/**
 * Admin page that renders the listing click metrics table.
 */
final class ListingClickMetricsPage
{
    private const MENU_SLUG = 'listing-click-metrics';
    private const DEFAULT_ORDER = 'wcr';
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;

    private ListingClickMetricsRepository $repository;

    private function __construct(ListingClickMetricsRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Bootstrap the admin page hooks.
     */
    public static function bootstrap(): void
    {
        $instance = new self(new ListingClickMetricsRepository());
        add_action('admin_menu', [$instance, 'registerMenu']);
    }

    /**
     * Register submenu under the Cars post type.
     */
    public function registerMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=car',
            __('Listing Click Metrics', 'bricks-child'),
            __('Click Metrics', 'bricks-child'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Render the admin page contents.
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'bricks-child'));
        }

        $orderBy = $this->getOrderSelection();
        $limit = $this->getLimitSelection();
        $listings = $this->repository->fetchListings($orderBy, $limit);
        $siteWide = $this->repository->fetchSiteWideTotals();
        $overallWcr = $this->computeWcrPercent($siteWide['total_whatsapp_clicks'], $siteWide['total_page_views']);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Listing metrics (WhatsApp conversion)', 'bricks-child'); ?></h1>
            <p class="description">
                <?php esc_html_e('WhatsApp clicks (ACF: whatsapp_button_clicks) and listing page views (total_views_count from the car views tracker). WCR = WhatsApp clicks ÷ listing page views.', 'bricks-child'); ?>
            </p>

            <div class="listing-metrics-wcr-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 1.25rem 0 1.5rem;">
                <div class="notice" style="margin: 0; padding: 12px 14px;">
                    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; color: #646970;">
                        <?php esc_html_e('Total listing page views', 'bricks-child'); ?>
                    </div>
                    <div style="font-size: 28px; font-weight: 600; line-height: 1.2;">
                        <?php echo esc_html(number_format_i18n($siteWide['total_page_views'])); ?>
                    </div>
                </div>
                <div class="notice" style="margin: 0; padding: 12px 14px;">
                    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; color: #646970;">
                        <?php esc_html_e('Total WhatsApp clicks', 'bricks-child'); ?>
                    </div>
                    <div style="font-size: 28px; font-weight: 600; line-height: 1.2;">
                        <?php echo esc_html(number_format_i18n($siteWide['total_whatsapp_clicks'])); ?>
                    </div>
                </div>
                <div class="notice notice-<?php echo esc_attr($this->wcrNoticeVariant($overallWcr)); ?>" style="margin: 0; padding: 12px 14px; border-left-width: 4px;">
                    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; color: #646970;">
                        <?php esc_html_e('WhatsApp click rate (WCR)', 'bricks-child'); ?>
                    </div>
                    <div style="font-size: 28px; font-weight: 600; line-height: 1.2;">
                        <?php echo esc_html($this->formatWcrPercent($overallWcr)); ?>
                    </div>
                    <p style="margin: 8px 0 0; font-size: 13px;">
                        <?php echo esc_html($this->wcrInterpretationLabel($overallWcr)); ?>
                    </p>
                </div>
            </div>

            <p class="description" style="max-width: 720px;">
                <?php esc_html_e('WCR guide: under 2% — conversion (UI / trust / urgency); 2–5% — decent; 5–10% — very good; 10%+ — strong.', 'bricks-child'); ?>
            </p>

            <form method="get" style="margin-bottom: 1rem; display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="post_type" value="car" />
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>" />

                <label>
                    <?php esc_html_e('Order by', 'bricks-child'); ?><br>
                    <select name="click_order">
                        <?php $this->renderOrderOption('wcr', __('WhatsApp click rate (WCR)', 'bricks-child'), $orderBy); ?>
                        <?php $this->renderOrderOption('views', __('Listing page views', 'bricks-child'), $orderBy); ?>
                        <?php $this->renderOrderOption('whatsapp', __('WhatsApp clicks', 'bricks-child'), $orderBy); ?>
                    </select>
                </label>

                <label>
                    <?php esc_html_e('Rows to show', 'bricks-child'); ?><br>
                    <input type="number" min="10" max="<?php echo esc_attr(self::MAX_LIMIT); ?>" step="10" name="per_page" value="<?php echo esc_attr($limit); ?>" />
                </label>

                <?php submit_button(__('Apply', 'bricks-child'), 'secondary', '', false); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Listing', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Poster', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Listing page views', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('WhatsApp clicks', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('WCR', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Actions', 'bricks-child'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listings)) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No listings found.', 'bricks-child'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($listings as $listing) : ?>
                            <?php
                            $pageViews = (int) ($listing->page_views ?? 0);
                            $wa = (int) ($listing->whatsapp_clicks ?? 0);
                            $rowWcr = $this->computeWcrPercent($wa, $pageViews);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($listing->post_title ?: sprintf(__('Listing #%d', 'bricks-child'), $listing->ID)); ?></strong>
                                    <div style="font-size: 12px; color: #555;">
                                        ID: <?php echo esc_html($listing->ID); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo esc_html($this->formatPosterLabel($listing)); ?>
                                </td>
                                <td><?php echo esc_html(number_format_i18n($pageViews)); ?></td>
                                <td><?php echo esc_html(number_format_i18n($wa)); ?></td>
                                <td>
                                    <span class="listing-wcr-pill" style="<?php echo esc_attr($this->wcrPillStyles($rowWcr)); ?>">
                                        <?php echo esc_html($this->formatWcrPercent($rowWcr)); ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(get_edit_post_link($listing->ID)); ?>">
                                        <?php esc_html_e('Edit Listing', 'bricks-child'); ?>
                                    </a>
                                    <a class="button button-small" href="<?php echo esc_url(get_permalink($listing->ID)); ?>" target="_blank" rel="noopener noreferrer">
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
     * Render select option for ordering choices.
     */
    private function renderOrderOption(string $value, string $label, string $current): void
    {
        printf(
            '<option value="%1$s"%2$s>%3$s</option>',
            esc_attr($value),
            selected($current, $value, false),
            esc_html($label)
        );
    }

    /**
     * @param int $whatsappClicks WhatsApp tap count.
     * @param int $pageViews      Listing page views (total_views_count).
     */
    private function computeWcrPercent(int $whatsappClicks, int $pageViews): ?float
    {
        if ($pageViews < 1) {
            return null;
        }

        return ($whatsappClicks / $pageViews) * 100.0;
    }

    private function formatWcrPercent(?float $percent): string
    {
        if ($percent === null) {
            return '—';
        }

        return sprintf('%s%%', number_format_i18n($percent, 2));
    }

    /**
     * WordPress admin notice variant for the overall WCR box.
     */
    private function wcrNoticeVariant(?float $percent): string
    {
        if ($percent === null) {
            return 'info';
        }

        if ($percent < 2.0) {
            return 'error';
        }

        if ($percent < 5.0) {
            return 'warning';
        }

        if ($percent < 10.0) {
            return 'success';
        }

        return 'success';
    }

    private function wcrInterpretationLabel(?float $percent): string
    {
        if ($percent === null) {
            return __('Not enough listing page views yet to calculate WCR.', 'bricks-child');
        }

        if ($percent < 2.0) {
            return __('Below 2% — focus on conversion (UI, trust, urgency).', 'bricks-child');
        }

        if ($percent < 5.0) {
            return __('2–5% — decent; room to improve.', 'bricks-child');
        }

        if ($percent < 10.0) {
            return __('5–10% — very good.', 'bricks-child');
        }

        return __('10%+ — strong WhatsApp conversion.', 'bricks-child');
    }

    /**
     * Inline styles for per-row WCR badge (admin has limited palette).
     */
    private function wcrPillStyles(?float $percent): string
    {
        if ($percent === null) {
            return 'display:inline-block;padding:2px 8px;border-radius:4px;background:#f0f0f1;color:#50575e;font-weight:600;';
        }

        if ($percent < 2.0) {
            return 'display:inline-block;padding:2px 8px;border-radius:4px;background:#fcf0f1;color:#b32d2e;font-weight:600;';
        }

        if ($percent < 5.0) {
            return 'display:inline-block;padding:2px 8px;border-radius:4px;background:#fcf9e8;color:#826200;font-weight:600;';
        }

        if ($percent < 10.0) {
            return 'display:inline-block;padding:2px 8px;border-radius:4px;background:#edfaef;color:#1e4620;font-weight:600;';
        }

        return 'display:inline-block;padding:2px 8px;border-radius:4px;background:#e7f5e9;color:#0f5132;font-weight:700;border:1px solid #00a32a;';
    }

    private function formatPosterLabel(object $listing): string
    {
        $username = trim((string) ($listing->poster_username ?? ''));
        $displayName = trim((string) ($listing->poster_display_name ?? ''));
        $firstName = trim((string) ($listing->poster_first_name ?? ''));
        $lastName = trim((string) ($listing->poster_last_name ?? ''));
        $fullName = trim(sprintf('%s %s', $firstName, $lastName));

        if ($fullName === '') {
            $fullName = $displayName;
        }

        if ($username === '') {
            return sprintf(__('User #%d', 'bricks-child'), $listing->ID);
        }

        if ($fullName !== '' && $fullName !== $username) {
            return sprintf('%s (%s)', $username, $fullName);
        }

        return $username;
    }

    private function getOrderSelection(): string
    {
        $selection = isset($_GET['click_order']) ? sanitize_key($_GET['click_order']) : self::DEFAULT_ORDER;
        $allowed = ['whatsapp', 'views', 'wcr'];

        return in_array($selection, $allowed, true) ? $selection : self::DEFAULT_ORDER;
    }

    private function getLimitSelection(): int
    {
        $limit = isset($_GET['per_page']) ? absint($_GET['per_page']) : self::DEFAULT_LIMIT;

        if ($limit < 10) {
            $limit = 10;
        }

        if ($limit > self::MAX_LIMIT) {
            $limit = self::MAX_LIMIT;
        }

        return $limit;
    }
}

ListingClickMetricsPage::bootstrap();

