<?php
/**
 * Cars Report: migrate listing_state from legacy is_sold meta and strip is_sold rows.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class CarsReportListingStateSyncCoordinator
{
    private const ACTION = 'brick_child_listing_state_sync';

    private const NONCE_ACTION = 'brick_child_listing_state_sync';

    public static function register(string $parent_page_slug): void
    {
        add_action(
            'admin_init',
            static function () use ($parent_page_slug): void {
                self::handlePost($parent_page_slug);
            }
        );
    }

    public static function renderNotice(): void
    {
        if (! isset($_GET['listing_state_sync_processed'])) {
            return;
        }
        $processed = absint(wp_unslash($_GET['listing_state_sync_processed']));
        $changed   = isset($_GET['listing_state_sync_changed']) ? absint(wp_unslash($_GET['listing_state_sync_changed'])) : 0;
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: rows updated, 2: total cars processed */
                        __('Updated listing_state on %1$d of %2$d car listings; legacy is_sold meta was removed from all cars.', 'bricks-child'),
                        $changed,
                        $processed
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    public static function renderForm(string $parent_page_slug): void
    {
        ?>
        <div style="margin: 0 0 1.25rem; padding: 12px 14px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; max-width: 720px;">
            <h2 style="margin: 0 0 8px; font-size: 14px;"><?php esc_html_e('Sync listing_state from legacy data', 'bricks-child'); ?></h2>
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e('Sets listing_state from old is_sold meta (and converts any legacy custom post status “expired” to publish + listing_state expired), then deletes is_sold post meta on every car. Run once before removing the is_sold field from ACF.', 'bricks-child'); ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('edit.php')); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <input type="hidden" name="post_type" value="car" />
                <input type="hidden" name="cars_report_page" value="<?php echo esc_attr($parent_page_slug); ?>" />
                <button
                    type="submit"
                    name="<?php echo esc_attr(self::ACTION); ?>"
                    value="1"
                    class="button button-secondary"
                    onclick="return confirm('<?php echo esc_js(__('Update listing_state on all car posts? This overwrites existing listing_state values where the derived value differs.', 'bricks-child')); ?>');"
                >
                    <?php esc_html_e('Sync all cars → listing_state', 'bricks-child'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    private static function handlePost(string $parent_page_slug): void
    {
        if (! isset($_POST[self::ACTION])) {
            return;
        }

        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::NONCE_ACTION)) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'), '', array('response' => 403));
        }

        if (! isset($_POST['cars_report_page']) || sanitize_key((string) wp_unslash($_POST['cars_report_page'])) !== $parent_page_slug) {
            return;
        }

        $result = ListingStateManager::sync_all_from_legacy_is_sold_meta();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post_type'                   => 'car',
                    'page'                        => $parent_page_slug,
                    'listing_state_sync_changed'   => $result['changed'],
                    'listing_state_sync_processed' => $result['processed'],
                ),
                admin_url('edit.php')
            )
        );
        exit;
    }
}
