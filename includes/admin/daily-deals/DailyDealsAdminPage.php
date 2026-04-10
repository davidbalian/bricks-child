<?php
/**
 * Admin UI: Daily Deals under Cars (social pick list + image downloads).
 */
if (!defined('ABSPATH')) {
    exit;
}

final class DailyDealsAdminPage
{
    private const SLUG = 'cars-daily-deals';

    public static function bootstrap(): void
    {
        $page = new self();
        add_action('admin_menu', array($page, 'registerSubmenu'));
    }

    public function registerSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=car',
            __('Daily Deals', 'bricks-child'),
            __('Daily Deals', 'bricks-child'),
            'manage_options',
            self::SLUG,
            array($this, 'renderPage')
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'));
        }

        $rows = array();
        $copy = '';

        if (isset($_POST['bricks_child_fetch_daily_deals']) && isset($_POST['_wpnonce'])) {
            if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bricks_child_fetch_daily_deals')) {
                wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
            }
            $picker = new DailyDealsDealPicker();
            $rows = $picker->pickForDay();
            $builder = new DailyDealsSocialCopyBuilder();
            $copy = $builder->build($rows, home_url('/'));
        }

        $ids_csv = '';
        if ($rows !== array()) {
            $ids = array();
            foreach ($rows as $r) {
                $ids[] = (int) ($r['attachment_id'] ?? 0);
            }
            $ids_csv = implode(',', array_filter($ids));
        }

        $zip_url = $ids_csv !== ''
            ? wp_nonce_url(
                admin_url('admin-post.php?action=bricks_child_daily_deals_zip&attachment_ids=' . rawurlencode($ids_csv)),
                'bricks_child_daily_deals_dl',
                '_wpnonce'
            )
            : '';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Daily Deals', 'bricks-child'); ?></h1>
            <p class="description">
                <?php esc_html_e('Loads the top five active “deal” listings (good/great price band) using the same Best match sort as /cars/: live “today / 1–3 days / older” buckets from publication_date (else GMT post date), then listing_rank_score, then post date. Skips cars without price or a usable image.', 'bricks-child'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=car&page=cars-report')); ?>">
                    <?php esc_html_e('← Cars Report', 'bricks-child'); ?>
                </a>
            </p>

            <form method="post" style="margin: 1rem 0;">
                <?php wp_nonce_field('bricks_child_fetch_daily_deals'); ?>
                <button type="submit" name="bricks_child_fetch_daily_deals" value="1" class="button button-primary">
                    <?php esc_html_e('Fetch today’s deals', 'bricks-child'); ?>
                </button>
            </form>

            <?php if (isset($_POST['bricks_child_fetch_daily_deals'])) : ?>
                <?php if ($rows === array()) : ?>
                    <div class="notice notice-warning"><p><?php esc_html_e('No matching listings found. Ensure cars are published, listing_state is active, price insight band is good/great (or fair after fallback), price &gt; 0, and a featured or gallery image exists.', 'bricks-child'); ?></p></div>
                <?php else : ?>
                    <h2><?php esc_html_e('Copy text', 'bricks-child'); ?></h2>
                    <p>
                        <button type="button" class="button" id="bricks-child-daily-deals-copy"><?php esc_html_e('Copy to clipboard', 'bricks-child'); ?></button>
                    </p>
                    <textarea id="bricks-child-daily-deals-text" readonly rows="14" class="large-text code" style="font-size:13px;"><?php echo esc_textarea($copy); ?></textarea>

                    <h2 style="margin-top:1.5rem;"><?php esc_html_e('Images', 'bricks-child'); ?></h2>
                    <?php if ($zip_url !== '') : ?>
                        <p>
                            <a class="button button-secondary" href="<?php echo esc_url($zip_url); ?>"><?php esc_html_e('Download all (zip)', 'bricks-child'); ?></a>
                        </p>
                    <?php endif; ?>

                    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:12px;">
                        <?php
                        $i = 1;
                        foreach ($rows as $r) :
                            $att = (int) ($r['attachment_id'] ?? 0);
                            $img = (string) ($r['image_url'] ?? '');
                            $one = wp_nonce_url(
                                admin_url('admin-post.php?action=bricks_child_daily_deals_image&attachment_id=' . $att),
                                'bricks_child_daily_deals_dl',
                                '_wpnonce'
                            );
                            ?>
                            <div style="width:220px;border:1px solid #c3c4c7;padding:10px;background:#fff;border-radius:4px;">
                                <div style="font-weight:600;margin-bottom:6px;"><?php echo esc_html((string) $i); ?>. <?php echo esc_html($this->shortTitle((string) ($r['title'] ?? ''))); ?></div>
                                <?php if ($img !== '') : ?>
                                    <img src="<?php echo esc_url($img); ?>" alt="" style="width:100%;height:auto;display:block;margin-bottom:8px;" loading="lazy" />
                                <?php endif; ?>
                                <a class="button button-small" href="<?php echo esc_url($one); ?>"><?php esc_html_e('Download image', 'bricks-child'); ?></a>
                            </div>
                            <?php
                            ++$i;
                        endforeach;
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            var btn = document.getElementById('bricks-child-daily-deals-copy');
            var ta = document.getElementById('bricks-child-daily-deals-text');
            if (!btn || !ta) return;
            btn.addEventListener('click', function () {
                ta.select();
                ta.setSelectionRange(0, ta.value.length);
                try {
                    document.execCommand('copy');
                } catch (e) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(ta.value);
                    }
                }
            });
        })();
        </script>
        <?php
    }

    private function shortTitle(string $title): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($title, 'UTF-8') <= 40) {
                return $title;
            }

            return mb_substr($title, 0, 37, 'UTF-8') . '…';
        }

        return strlen($title) <= 40 ? $title : substr($title, 0, 37) . '…';
    }
}
