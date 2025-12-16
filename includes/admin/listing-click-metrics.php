<?php
/**
 * Listing Click Metrics Admin Page
 *
 * Displays aggregated WhatsApp and phone click counts for car listings.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository responsible for fetching listing click stats.
 */
final class ListingClickMetricsRepository
{
    private const PHONE_META_KEY = 'call_button_clicks';
    private const WHATSAPP_META_KEY = 'whatsapp_button_clicks';
    private const POST_TYPE = 'car';

    /**
     * Fetch listing stats sorted by the requested metric.
     *
     * @param string $orderBy phone|whatsapp|total
     * @param int    $limit   Maximum rows to return.
     *
     * @return array<int, object>
     */
    public function fetchListings(string $orderBy, int $limit): array
    {
        global $wpdb;

        $orderColumn = $this->determineOrderColumn($orderBy);

        $query = $wpdb->prepare(
            "
            SELECT
                p.ID,
                p.post_title,
                COALESCE(CAST(phone_meta.meta_value AS UNSIGNED), 0)     AS phone_clicks,
                COALESCE(CAST(wa_meta.meta_value AS UNSIGNED), 0)        AS whatsapp_clicks,
                (
                    COALESCE(CAST(phone_meta.meta_value AS UNSIGNED), 0) +
                    COALESCE(CAST(wa_meta.meta_value AS UNSIGNED), 0)
                ) AS total_clicks,
                COALESCE(author.user_login, CONCAT(%s, p.post_author)) AS poster_username,
                author.display_name AS poster_display_name
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->users} AS author
                ON author.ID = p.post_author
            LEFT JOIN {$wpdb->postmeta} AS phone_meta
                ON phone_meta.post_id = p.ID
                AND phone_meta.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} AS wa_meta
                ON wa_meta.post_id = p.ID
                AND wa_meta.meta_key = %s
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'pending', 'draft')
            ORDER BY {$orderColumn} DESC, p.post_date DESC
            LIMIT %d
            ",
            esc_html__('User #', 'bricks-child'),
            self::PHONE_META_KEY,
            self::WHATSAPP_META_KEY,
            self::POST_TYPE,
            $limit
        );

        $results = $wpdb->get_results($query); // phpcs:ignore WordPress.DB

        return is_array($results) ? $results : [];
    }

    /**
     * Determine the SQL order column for the selected metric.
     */
    private function determineOrderColumn(string $orderBy): string
    {
        $map = [
            'phone' => 'phone_clicks',
            'whatsapp' => 'whatsapp_clicks',
            'total' => 'total_clicks',
        ];

        return $map[$orderBy] ?? $map['total'];
    }
}

/**
 * Admin page that renders the listing click metrics table.
 */
final class ListingClickMetricsPage
{
    private const MENU_SLUG = 'listing-click-metrics';
    private const DEFAULT_ORDER = 'total';
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
        $totals = $this->summarize($listings);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Listing Click Metrics', 'bricks-child'); ?></h1>
            <p class="description">
                <?php esc_html_e('Tracks WhatsApp (ACF: whatsapp_button_clicks) and phone (ACF: call_button_clicks) interactions recorded on single car pages.', 'bricks-child'); ?>
            </p>

            <form method="get" style="margin-bottom: 1rem; display: flex; gap: 1rem; align-items: flex-end;">
                <input type="hidden" name="post_type" value="car" />
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>" />

                <label>
                    <?php esc_html_e('Order by', 'bricks-child'); ?><br>
                    <select name="click_order">
                        <?php $this->renderOrderOption('total', __('Total clicks', 'bricks-child'), $orderBy); ?>
                        <?php $this->renderOrderOption('phone', __('Phone clicks', 'bricks-child'), $orderBy); ?>
                        <?php $this->renderOrderOption('whatsapp', __('WhatsApp clicks', 'bricks-child'), $orderBy); ?>
                    </select>
                </label>

                <label>
                    <?php esc_html_e('Rows to show', 'bricks-child'); ?><br>
                    <input type="number" min="10" max="<?php echo esc_attr(self::MAX_LIMIT); ?>" step="10" name="per_page" value="<?php echo esc_attr($limit); ?>" />
                </label>

                <?php submit_button(__('Apply', 'bricks-child'), 'secondary', '', false); ?>
            </form>

            <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem;">
                <div>
                    <strong><?php esc_html_e('Phone clicks (visible rows)', 'bricks-child'); ?></strong><br>
                    <?php echo esc_html(number_format_i18n($totals['phone'])); ?>
                </div>
                <div>
                    <strong><?php esc_html_e('WhatsApp clicks (visible rows)', 'bricks-child'); ?></strong><br>
                    <?php echo esc_html(number_format_i18n($totals['whatsapp'])); ?>
                </div>
                <div>
                    <strong><?php esc_html_e('Total clicks (visible rows)', 'bricks-child'); ?></strong><br>
                    <?php echo esc_html(number_format_i18n($totals['total'])); ?>
                </div>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Listing', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Poster', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Phone clicks', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('WhatsApp clicks', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Total clicks', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Actions', 'bricks-child'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listings)) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No listings found.', 'bricks-child'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($listings as $listing) : ?>
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
                                <td><?php echo esc_html(number_format_i18n((int) $listing->phone_clicks)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((int) $listing->whatsapp_clicks)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((int) $listing->total_clicks)); ?></td>
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
     * Compute totals for the currently displayed rows.
     *
     * @param array<int, object> $listings
     */
    private function summarize(array $listings): array
    {
        $summary = [
            'phone' => 0,
            'whatsapp' => 0,
            'total' => 0,
        ];

        foreach ($listings as $listing) {
            $summary['phone'] += (int) $listing->phone_clicks;
            $summary['whatsapp'] += (int) $listing->whatsapp_clicks;
            $summary['total'] += (int) $listing->total_clicks;
        }

        return $summary;
    }

    private function formatPosterLabel(object $listing): string
    {
        $username = trim((string) ($listing->poster_username ?? ''));

        if ($username === '') {
            return sprintf(__('User #%d', 'bricks-child'), $listing->ID);
        }

        $displayName = trim((string) ($listing->poster_display_name ?? ''));

        if ($displayName !== '' && $displayName !== $username) {
            return sprintf('%s (%s)', $username, $displayName);
        }

        return $username;
    }

    private function getOrderSelection(): string
    {
        $selection = isset($_GET['click_order']) ? sanitize_key($_GET['click_order']) : self::DEFAULT_ORDER;
        $allowed = ['total', 'phone', 'whatsapp'];

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

