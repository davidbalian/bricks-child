<?php
/**
 * Daily Deals admin: sticker upload UI (Media Library pickers).
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarsDailyDealsStickerSettingsView
{
    /**
     * @param string $save_action   admin-post action name.
     * @param string $nonce_action  Nonce action for wp_nonce_field / verify.
     */
    public static function render(string $save_action, string $nonce_action): void
    {
        $ids = CarsDailyDealsStickerStore::getAttachmentIds();
        ?>
        <div style="margin:1.25rem 0;padding:14px 16px;max-width:820px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;">
            <h2 style="margin:0 0 10px;font-size:15px;"><?php esc_html_e('Branding stickers (downloads)', 'bricks-child'); ?></h2>
            <p class="description" style="margin-top:0;">
                <?php esc_html_e('Upload PNG or WebP images with transparency (e.g. site name, handle, logo). They are scaled to about 28% of the photo width and placed in each corner. Saved stickers are burned onto JPEGs when you use “Download with stickers” or “Download all as ZIP”.', 'bricks-child'); ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <input type="hidden" name="action" value="<?php echo esc_attr($save_action); ?>" />
                <?php wp_nonce_field($nonce_action); ?>
                <?php
                $slots = array(
                    'sticker_tl' => array(
                        'key'   => 'top_left',
                        'label' => __('Top left', 'bricks-child'),
                        'id'    => (int) $ids['top_left'],
                    ),
                    'sticker_tr' => array(
                        'key'   => 'top_right',
                        'label' => __('Top right', 'bricks-child'),
                        'id'    => (int) $ids['top_right'],
                    ),
                    'sticker_bl' => array(
                        'key'   => 'bottom_left',
                        'label' => __('Bottom left', 'bricks-child'),
                        'id'    => (int) $ids['bottom_left'],
                    ),
                    'sticker_br' => array(
                        'key'   => 'bottom_right',
                        'label' => __('Bottom right', 'bricks-child'),
                        'id'    => (int) $ids['bottom_right'],
                    ),
                );
                foreach ($slots as $field_name => $slot) :
                    $hid     = 'dd-hid-' . $slot['key'];
                    $preview = 'dd-preview-' . $slot['key'];
                    ?>
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f0f0f1;">
                        <span style="min-width:110px;font-weight:600;"><?php echo esc_html($slot['label']); ?></span>
                        <input type="hidden" name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($hid); ?>" value="<?php echo esc_attr((string) $slot['id']); ?>" />
                        <button
                            type="button"
                            class="button bricks-dd-sticker-pick"
                            data-hid="<?php echo esc_attr($hid); ?>"
                            data-preview="<?php echo esc_attr($preview); ?>"
                            data-title="<?php echo esc_attr__('Choose sticker image', 'bricks-child'); ?>"
                            data-btntext="<?php echo esc_attr__('Use image', 'bricks-child'); ?>"
                        >
                            <?php esc_html_e('Select from Media Library', 'bricks-child'); ?>
                        </button>
                        <button type="button" class="button-link bricks-dd-sticker-clear" data-hid="<?php echo esc_attr($hid); ?>" data-preview="<?php echo esc_attr($preview); ?>">
                            <?php esc_html_e('Remove', 'bricks-child'); ?>
                        </button>
                        <span id="<?php echo esc_attr($preview); ?>" style="min-height:40px;display:inline-flex;align-items:center;">
                            <?php
                            if ($slot['id'] > 0) {
                                echo wp_get_attachment_image($slot['id'], array(80, 80), false, array('style' => 'max-width:80px;height:auto;border-radius:2px;'));
                            }
                            ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php submit_button(__('Save stickers', 'bricks-child'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }
}
