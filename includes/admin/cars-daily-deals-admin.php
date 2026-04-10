<?php
/**
 * Admin: Cars → Daily Deals — snapshot of first 5 /cars/ best-match results for social posting.
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/cars-daily-deals-snapshot.php';
require_once __DIR__ . '/cars-daily-deals-composite.php';
require_once __DIR__ . '/cars-daily-deals-download-handlers.php';
require_once __DIR__ . '/cars-daily-deals-sticker-settings-view.php';

final class CarsDailyDealsAdminPage
{
    private const SLUG = 'cars-daily-deals';

    private const ACTION_SAVE_STICKERS = 'bricks_child_daily_deals_save_stickers';

    private const NONCE_FETCH = 'bricks_child_daily_deals_fetch';

    private const NONCE_STICKERS = 'bricks_child_daily_deals_stickers';

    public static function bootstrap(): void
    {
        $page = new self();
        add_action('admin_menu', array($page, 'registerSubmenu'));
        add_action('admin_enqueue_scripts', array($page, 'enqueueAdminAssets'));
        add_action('admin_post_' . CarsDailyDealsDownloadHandlers::ACTION_ZIP, array('CarsDailyDealsDownloadHandlers', 'handleZipDownload'));
        add_action('admin_post_' . self::ACTION_SAVE_STICKERS, array($page, 'handleSaveStickers'));
        add_action('admin_post_' . CarsDailyDealsDownloadHandlers::ACTION_DOWNLOAD, array('CarsDailyDealsDownloadHandlers', 'handleSingleDownload'));
        add_action('admin_post_' . CarsDailyDealsDownloadHandlers::ACTION_DOWNLOAD_IG, array('CarsDailyDealsDownloadHandlers', 'handleInstagramDownload'));
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

    /**
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueueAdminAssets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'car_page_' . self::SLUG) {
            return;
        }
        wp_enqueue_script('jquery');
        wp_enqueue_media();
        $inline_js = <<<'JS'
(function ($) {
    $(function () {
        var frame;
        $(document).on('click', '.bricks-dd-sticker-pick', function (e) {
            e.preventDefault();
            var btn = $(this),
                hid = $('#' + btn.data('hid')),
                prev = $('#' + btn.data('preview'));
            frame = wp.media({
                title: btn.data('title'),
                button: { text: btn.data('btntext') },
                multiple: false,
                library: { type: 'image' },
            });
            frame.on('select', function () {
                var a = frame.state().get('selection').first().toJSON();
                hid.val(a.id);
                if (a.sizes && a.sizes.thumbnail && a.sizes.thumbnail.url) {
                    prev.html(
                        '<img src="' +
                            a.sizes.thumbnail.url +
                            '" alt="" style="max-width:80px;height:auto;vertical-align:middle;border-radius:2px;" />'
                    );
                } else if (a.url) {
                    prev.html(
                        '<img src="' + a.url + '" alt="" style="max-width:80px;height:auto;vertical-align:middle;border-radius:2px;" />'
                    );
                }
            });
            frame.open();
        });
        $(document).on('click', '.bricks-dd-sticker-clear', function (e) {
            e.preventDefault();
            var btn = $(this);
            $('#' + btn.data('hid')).val('0');
            $('#' + btn.data('preview')).empty();
        });
    });
})(jQuery);
JS;

        wp_add_inline_script('jquery', $inline_js);
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'));
        }

        $items    = array();
        $fetched  = false;
        $caption  = '';
        $builder  = new CarsDailyDealsSnapshotBuilder();

        if (isset($_GET['fetched']) && (string) wp_unslash($_GET['fetched']) === '1') {
            check_admin_referer(self::NONCE_FETCH);
            $items   = $builder->fetchFirstCarsFromBrowseQuery(5);
            $fetched = true;
            if (! empty($items)) {
                $caption = $builder->buildSocialCaption($items);
            }
        }

        $zip_available = class_exists('ZipArchive');

        ?>
        <div class="wrap bricks-child-daily-deals">
            <h1><?php esc_html_e('Daily Deals', 'bricks-child'); ?></h1>
            <p class="description">
                <?php esc_html_e('Fetches the first five active listings from the public browse page using the same “Best match” ordering as /cars/ (rank score + freshness). Use this to build today’s social post.', 'bricks-child'); ?>
            </p>

            <?php if (isset($_GET['sticker-saved']) && (string) wp_unslash($_GET['sticker-saved']) === '1') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Sticker settings saved.', 'bricks-child'); ?></p></div>
            <?php endif; ?>

            <?php CarsDailyDealsStickerSettingsView::render(self::ACTION_SAVE_STICKERS, self::NONCE_STICKERS); ?>

            <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>" style="margin: 1rem 0;">
                <input type="hidden" name="post_type" value="car" />
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>" />
                <input type="hidden" name="fetched" value="1" />
                <?php wp_nonce_field(self::NONCE_FETCH); ?>
                <?php
                submit_button(
                    __('Fetch today’s deals', 'bricks-child'),
                    'primary',
                    'submit',
                    false
                );
                ?>
            </form>

            <?php if ($fetched && empty($items)) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e('No listings matched the browse query.', 'bricks-child'); ?></p></div>
            <?php endif; ?>

            <?php if (! empty($items)) : ?>
                <?php
                $zip_nonce = wp_create_nonce(CarsDailyDealsDownloadHandlers::NONCE_ZIP);
                $use_branded_dl = CarsDailyDealsStickerStore::hasAnySticker()
                    && CarsDailyDealsImageCompositor::canComposite();
                $can_ig         = CarsDailyDealsImageCompositor::canComposite();
                ?>
                <?php if ($use_branded_dl || $can_ig) : ?>
                    <p class="description" style="max-width:720px;">
                        <?php
                        if ($use_branded_dl) {
                            esc_html_e('“Download with stickers” and Instagram (1080×1080) use your corner assets. The Instagram version fits the entire photo on a square canvas with letterboxing (no cropping). The grid preview still shows the original photo.', 'bricks-child');
                        } else {
                            esc_html_e('Instagram (1080×1080) fits the full photo inside a square feed canvas with letterboxing (no cropping). Add stickers above to include them on exports.', 'bricks-child');
                        }
                        ?>
                    </p>
                <?php elseif (CarsDailyDealsStickerStore::hasAnySticker() && ! CarsDailyDealsImageCompositor::canComposite()) : ?>
                    <div class="notice notice-warning"><p><?php esc_html_e('Stickers are set, but this server cannot composite images (enable the GD or Imagick PHP extension). Downloads will be the original files.', 'bricks-child'); ?></p></div>
                <?php endif; ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin:1.5rem 0;align-items:start;">
                    <?php foreach ($items as $row) : ?>
                        <?php
                        $pid = (int) ($row['id'] ?? 0);
                        $img = (string) ($row['image_url'] ?? '');
                        $path_ok = ((string) ($row['image_path'] ?? '')) !== '' && is_readable((string) ($row['image_path'] ?? ''));
                        $dl_url = '';
                        if ($img !== '' && $use_branded_dl && $path_ok) {
                            $dl_url = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action'  => CarsDailyDealsDownloadHandlers::ACTION_DOWNLOAD,
                                        'post_id' => $pid,
                                    ),
                                    admin_url('admin-post.php')
                                ),
                                CarsDailyDealsDownloadHandlers::NONCE_DOWNLOAD
                            );
                        }
                        $ig_url = '';
                        if ($img !== '' && $can_ig && $path_ok) {
                            $ig_url = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action'  => CarsDailyDealsDownloadHandlers::ACTION_DOWNLOAD_IG,
                                        'post_id' => $pid,
                                    ),
                                    admin_url('admin-post.php')
                                ),
                                CarsDailyDealsDownloadHandlers::NONCE_DOWNLOAD_IG
                            );
                        }
                        ?>
                        <div style="border:1px solid #c3c4c7;border-radius:4px;padding:10px;background:#fff;">
                            <?php if ($img !== '') : ?>
                                <img src="<?php echo esc_url($img); ?>" alt="" style="width:100%;height:auto;border-radius:2px;display:block;" />
                            <?php else : ?>
                                <div style="aspect-ratio:4/3;background:#f0f0f1;display:flex;align-items:center;justify-content:center;font-size:12px;color:#646970;">
                                    <?php esc_html_e('No image', 'bricks-child'); ?>
                                </div>
                            <?php endif; ?>
                            <p style="font-size:12px;margin:8px 0 6px;line-height:1.4;">
                                <?php echo esc_html((string) ($row['line'] ?? '')); ?>
                            </p>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                <?php if ($img !== '') : ?>
                                    <?php if ($ig_url !== '') : ?>
                                        <a class="button button-small" href="<?php echo esc_url($ig_url); ?>">
                                            <?php esc_html_e('Instagram (1080×1080)', 'bricks-child'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($dl_url !== '') : ?>
                                        <a class="button button-small" href="<?php echo esc_url($dl_url); ?>">
                                            <?php esc_html_e('Download with stickers', 'bricks-child'); ?>
                                        </a>
                                        <a class="button button-small" href="<?php echo esc_url($img); ?>" download>
                                            <?php esc_html_e('Download original', 'bricks-child'); ?>
                                        </a>
                                    <?php else : ?>
                                        <a class="button button-small" href="<?php echo esc_url($img); ?>" download>
                                            <?php esc_html_e('Download image', 'bricks-child'); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a class="button button-small" href="<?php echo esc_url((string) ($row['permalink'] ?? '')); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e('View listing', 'bricks-child'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h2><?php esc_html_e('Caption (copy)', 'bricks-child'); ?></h2>
                <p class="description"><?php esc_html_e('Copy this text for Instagram, Facebook, or X.', 'bricks-child'); ?></p>
                <textarea id="bricks-child-daily-deals-caption" readonly rows="14" style="width:100%;max-width:640px;font-family:monospace;font-size:13px;"><?php echo esc_textarea($caption); ?></textarea>
                <p>
                    <button type="button" class="button button-secondary" id="bricks-child-daily-deals-copy">
                        <?php esc_html_e('Copy caption', 'bricks-child'); ?>
                    </button>
                </p>

                <h2><?php esc_html_e('Download all images', 'bricks-child'); ?></h2>
                <?php if (! $zip_available) : ?>
                    <p class="description"><?php esc_html_e('ZIP is not available on this server; use the per-image download buttons above.', 'bricks-child'); ?></p>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:10px;">
                        <input type="hidden" name="action" value="<?php echo esc_attr(CarsDailyDealsDownloadHandlers::ACTION_ZIP); ?>" />
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($zip_nonce); ?>" />
                        <input type="hidden" name="zip_format" value="original" />
                        <?php foreach ($items as $row) : ?>
                            <input type="hidden" name="post_ids[]" value="<?php echo esc_attr((string) (int) ($row['id'] ?? 0)); ?>" />
                        <?php endforeach; ?>
                        <?php submit_button(__('Download all as ZIP', 'bricks-child'), 'secondary', 'submit', false); ?>
                    </form>
                    <?php if ($can_ig) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr(CarsDailyDealsDownloadHandlers::ACTION_ZIP); ?>" />
                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($zip_nonce); ?>" />
                            <input type="hidden" name="zip_format" value="instagram" />
                            <?php foreach ($items as $row) : ?>
                                <input type="hidden" name="post_ids[]" value="<?php echo esc_attr((string) (int) ($row['id'] ?? 0)); ?>" />
                            <?php endforeach; ?>
                            <?php
                            submit_button(
                                __('Download all as ZIP (Instagram 1080×1080)', 'bricks-child'),
                                'secondary',
                                'submit',
                                false
                            );
                            ?>
                        </form>
                        <p class="description"><?php esc_html_e('Instagram ZIP: each file is a square JPEG with the full photo visible (letterboxed) and your stickers applied if configured.', 'bricks-child'); ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <script>
                (function () {
                    var btn = document.getElementById('bricks-child-daily-deals-copy');
                    var ta = document.getElementById('bricks-child-daily-deals-caption');
                    if (!btn || !ta) return;
                    btn.addEventListener('click', function () {
                        ta.select();
                        ta.setSelectionRange(0, ta.value.length);
                        try {
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(ta.value).then(function () {
                                    btn.textContent = <?php echo wp_json_encode(__('Copied!', 'bricks-child')); ?>;
                                    setTimeout(function () {
                                        btn.textContent = <?php echo wp_json_encode(__('Copy caption', 'bricks-child')); ?>;
                                    }, 2000);
                                });
                            } else {
                                document.execCommand('copy');
                                btn.textContent = <?php echo wp_json_encode(__('Copied!', 'bricks-child')); ?>;
                                setTimeout(function () {
                                    btn.textContent = <?php echo wp_json_encode(__('Copy caption', 'bricks-child')); ?>;
                                }, 2000);
                            }
                        } catch (e) {}
                    });
                })();
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handleSaveStickers(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::NONCE_STICKERS)) {
            wp_die(esc_html__('Security check failed.', 'bricks-child'), '', array('response' => 403));
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bricks-child'), '', array('response' => 403));
        }

        CarsDailyDealsStickerStore::saveAttachmentIds(
            array(
                'top_left'     => isset($_POST['sticker_tl']) ? absint(wp_unslash($_POST['sticker_tl'])) : 0,
                'top_right'    => isset($_POST['sticker_tr']) ? absint(wp_unslash($_POST['sticker_tr'])) : 0,
                'bottom_left'  => isset($_POST['sticker_bl']) ? absint(wp_unslash($_POST['sticker_bl'])) : 0,
                'bottom_right' => isset($_POST['sticker_br']) ? absint(wp_unslash($_POST['sticker_br'])) : 0,
            )
        );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post_type'     => 'car',
                    'page'          => self::SLUG,
                    'sticker-saved' => '1',
                ),
                admin_url('edit.php')
            )
        );
        exit;
    }
}

CarsDailyDealsAdminPage::bootstrap();
