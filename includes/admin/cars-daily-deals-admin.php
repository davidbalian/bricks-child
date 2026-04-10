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
require_once __DIR__ . '/cars-daily-deals-sticker-settings-view.php';

final class CarsDailyDealsAdminPage
{
    private const SLUG = 'cars-daily-deals';

    private const ACTION_ZIP = 'bricks_child_daily_deals_zip';

    private const ACTION_SAVE_STICKERS = 'bricks_child_daily_deals_save_stickers';

    private const ACTION_DOWNLOAD = 'bricks_child_daily_deals_download';

    private const NONCE_FETCH = 'bricks_child_daily_deals_fetch';

    private const NONCE_ZIP = 'bricks_child_daily_deals_zip';

    private const NONCE_STICKERS = 'bricks_child_daily_deals_stickers';

    private const NONCE_DOWNLOAD = 'bricks_child_daily_deals_download';

    public static function bootstrap(): void
    {
        $page = new self();
        add_action('admin_menu', array($page, 'registerSubmenu'));
        add_action('admin_enqueue_scripts', array($page, 'enqueueAdminAssets'));
        add_action('admin_post_' . self::ACTION_ZIP, array($page, 'handleZipDownload'));
        add_action('admin_post_' . self::ACTION_SAVE_STICKERS, array($page, 'handleSaveStickers'));
        add_action('admin_post_' . self::ACTION_DOWNLOAD, array($page, 'handleSingleDownload'));
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
        wp_add_inline_script(
            'jquery',
            "(function($){$(function(){
                var frame;
                $(document).on('click','.bricks-dd-sticker-pick',function(e){
                    e.preventDefault();
                    var btn=$(this), hid=$('#'+btn.data('hid')), prev=$('#'+btn.data('preview'));
                    frame=wp.media({title:btn.data('title'),button:{text:btn.data('btntext')},multiple:false,library:{type:'image'}});
                    frame.on('select',function(){
                        var a=frame.state().get('selection').first().toJSON();
                        hid.val(a.id);
                        if(a.sizes&&a.sizes.thumbnail&&a.sizes.thumbnail.url){prev.html('<img src=\"'+a.sizes.thumbnail.url+'\" alt=\"\" style=\"max-width:80px;height:auto;vertical-align:middle;border-radius:2px;\" />');}
                        else if(a.url){prev.html('<img src=\"'+a.url+'\" alt=\"\" style=\"max-width:80px;height:auto;vertical-align:middle;border-radius:2px;\" />');}
                    });
                    frame.open();
                });
                $(document).on('click','.bricks-dd-sticker-clear',function(e){
                    e.preventDefault();
                    var btn=$(this);
                    $('#'+btn.data('hid')).val('0');
                    $('#'+btn.data('preview')).empty();
                });
            });})(jQuery);"
        );
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
                $zip_nonce = wp_create_nonce(self::NONCE_ZIP);
                $use_branded_dl = CarsDailyDealsStickerStore::hasAnySticker()
                    && CarsDailyDealsImageCompositor::canComposite();
                ?>
                <?php if ($use_branded_dl) : ?>
                    <p class="description" style="max-width:720px;">
                        <?php esc_html_e('Downloads use your corner stickers (saved above) and export as JPEG. The grid preview still shows the original photo.', 'bricks-child'); ?>
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
                        $dl_url  = '';
                        if ($img !== '' && $use_branded_dl && $path_ok) {
                            $dl_url = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action'  => self::ACTION_DOWNLOAD,
                                        'post_id' => $pid,
                                    ),
                                    admin_url('admin-post.php')
                                ),
                                self::NONCE_DOWNLOAD
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
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_ZIP); ?>" />
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($zip_nonce); ?>" />
                        <?php foreach ($items as $row) : ?>
                            <input type="hidden" name="post_ids[]" value="<?php echo esc_attr((string) (int) ($row['id'] ?? 0)); ?>" />
                        <?php endforeach; ?>
                        <?php submit_button(__('Download all as ZIP', 'bricks-child'), 'secondary', 'submit', false); ?>
                    </form>
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

    public function handleSingleDownload(): void
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

    public function handleZipDownload(): void
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

        $tmp = wp_tempnam('daily-deals-');
        if (! $tmp) {
            wp_die(esc_html__('Could not create temporary file.', 'bricks-child'), '', array('response' => 500));
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            wp_die(esc_html__('Could not create ZIP archive.', 'bricks-child'), '', array('response' => 500));
        }

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
                    if ($do_brand) {
                        $branded = CarsDailyDealsImageCompositor::compositeToTempJpeg($path);
                        if ($branded !== false && is_string($branded) && is_readable($branded)) {
                            $file_for_zip = $branded;
                            $safe         = 'jpg';
                            $cleanup[]    = $branded;
                        }
                    }
                    $zip->addFile($file_for_zip, 'deal-' . $index . '.' . $safe);
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

        $filename = 'daily-deals-' . gmdate('Y-m-d') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}

CarsDailyDealsAdminPage::bootstrap();
