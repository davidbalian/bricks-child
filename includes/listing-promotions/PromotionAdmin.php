<?php
/**
 * Secure wp-admin controls for manual listing promotions.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Promotion_Admin
{
    public static function init()
    {
        add_action('add_meta_boxes_car', array(__CLASS__, 'add_meta_box'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('admin_post_autoagora_grant_listing_promotion', array(__CLASS__, 'handle_grant'));
        add_action('admin_post_autoagora_cancel_listing_promotion', array(__CLASS__, 'handle_cancel'));
        add_action('wp_ajax_autoagora_grant_listing_promotion_ajax', array(__CLASS__, 'handle_grant_ajax'));
    }

    public static function enqueue_assets($hook_suffix)
    {
        if (!in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'car' || !current_user_can('manage_options')) {
            return;
        }

        $path = __DIR__ . '/promotion-admin.js';
        wp_enqueue_script(
            'autoagora-promotion-admin',
            get_stylesheet_directory_uri() . '/includes/listing-promotions/promotion-admin.js',
            array(),
            file_exists($path) ? filemtime($path) : BRICKS_CHILD_THEME_VERSION,
            true
        );
        wp_localize_script('autoagora-promotion-admin', 'autoAgoraPromotionsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'workingText' => 'Granting…',
            'buttonText' => 'Grant promotion',
            'genericError' => 'The promotion could not be granted. Please retry.',
        ));
    }

    public static function add_meta_box()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        add_meta_box(
            'autoagora-listing-promotions',
            'AutoAgora Promotions',
            array(__CLASS__, 'render_meta_box'),
            'car',
            'normal',
            'high'
        );
    }

    public static function render_meta_box($post)
    {
        if (!AutoAgora_Promotion_Schema::exists()) {
            echo '<p><strong>Promotion table unavailable.</strong> Reload this admin page after the schema installer has run.</p>';
            return;
        }

        $repository = new AutoAgora_Promotion_Repository();
        $history = $repository->list_for_listing((int) $post->ID);
        $tier = autoagora_get_listing_promotion_tier((int) $post->ID);
        $label = autoagora_listing_promotion_label($tier);
        $ends_gmt = get_post_meta($post->ID, AutoAgora_Promotion_Manager::META_ENDS_AT, true);
        $ends_local = $ends_gmt ? get_date_from_gmt($ends_gmt, 'Y-m-d H:i') : '';

        if (isset($_GET['autoagora_promotion_notice'])) {
            $notice = sanitize_key(wp_unslash($_GET['autoagora_promotion_notice']));
            $messages = array(
                'granted' => 'Promotion granted successfully.',
                'cancelled' => 'Promotion cancelled.',
                'error' => 'The promotion action could not be completed.',
            );
            $error_code = isset($_GET['autoagora_promotion_error']) ? sanitize_key(wp_unslash($_GET['autoagora_promotion_error'])) : '';
            $error_messages = array(
                'promotion_listing_inactive' => 'Only published, active listings can receive a promotion.',
                'promotion_tier_invalid' => 'The selected promotion tier is invalid.',
                'promotion_duration_invalid' => 'The promotion duration must be between one hour and one year.',
                'promotion_table_missing' => 'The listing promotions table is unavailable.',
                'promotion_listing_busy' => 'Another promotion is being added to this listing. Please retry.',
                'promotion_insert_failed' => 'The promotion could not be saved to the database.',
            );
            if ($notice === 'error' && isset($error_messages[$error_code])) {
                $messages['error'] = $error_messages[$error_code];
            }
            if (isset($messages[$notice])) {
                printf('<div class="notice notice-%1$s inline"><p>%2$s</p></div>', $notice === 'error' ? 'error' : 'success', esc_html($messages[$notice]));
            }
        }

        echo '<p><strong>Current marketplace promotion:</strong> ';
        if ($label !== '') {
            echo esc_html($label);
            if ($ends_local !== '') {
                echo ' until ' . esc_html($ends_local) . ' (site time)';
            }
        } else {
            echo 'None';
        }
        echo '</p>';

        wp_nonce_field('autoagora_grant_listing_promotion_' . $post->ID, 'autoagora_promotion_nonce');
        ?>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(150px,1fr));gap:12px;max-width:900px;align-items:end">
            <label>
                <strong>Tier</strong><br>
                <select name="autoagora_promotion_tier" style="width:100%">
                    <?php foreach (AutoAgora_Promotion_Manager::tiers() as $tier_key => $config) : ?>
                        <option value="<?php echo esc_attr($tier_key); ?>"><?php echo esc_html($config['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <strong>Duration (days)</strong><br>
                <input type="number" name="autoagora_promotion_days" value="7" min="1" max="365" step="1" style="width:100%">
            </label>
            <label>
                <strong>Requested start (optional)</strong><br>
                <input type="datetime-local" name="autoagora_promotion_starts_at" style="width:100%">
            </label>
        </div>
        <p>
            <label><strong>Internal note</strong><br>
                <textarea name="autoagora_promotion_notes" rows="2" maxlength="2000" style="width:100%;max-width:900px"></textarea>
            </label>
        </p>
        <p>
            <button type="button"
                    id="autoagora-grant-promotion"
                    class="button button-primary"
                    data-listing-id="<?php echo esc_attr($post->ID); ?>">Grant promotion</button>
            <span id="autoagora-promotion-action-status" role="status" aria-live="polite"></span>
            <span class="description">If another promotion is active or scheduled, this grant is queued after it so paid time is not lost.</span>
        </p>
        <?php

        echo '<h3>Promotion history</h3>';
        if (!$history) {
            echo '<p>No promotion records yet.</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Tier</th><th>Status</th><th>Source</th><th>Starts</th><th>Ends</th><th>Payment reference</th><th>Action</th></tr></thead><tbody>';
        foreach ($history as $record) {
            $cancel_url = wp_nonce_url(
                add_query_arg(
                    array(
                        'action' => 'autoagora_cancel_listing_promotion',
                        'promotion_id' => (int) $record->id,
                    ),
                    admin_url('admin-post.php')
                ),
                'autoagora_cancel_listing_promotion_' . (int) $record->id
            );
            echo '<tr>';
            echo '<td>' . esc_html($record->id) . '</td>';
            echo '<td>' . esc_html(AutoAgora_Promotion_Manager::tier_label($record->tier) ?: $record->tier) . '</td>';
            echo '<td>' . esc_html($record->status) . '</td>';
            echo '<td>' . esc_html($record->source) . '</td>';
            echo '<td>' . esc_html(self::display_gmt($record->starts_at)) . '</td>';
            echo '<td>' . esc_html(self::display_gmt($record->ends_at)) . '</td>';
            echo '<td>' . esc_html(trim((string) $record->payment_provider . ' ' . (string) $record->payment_reference)) . '</td>';
            echo '<td>';
            if (in_array($record->status, array(AutoAgora_Promotion_Manager::STATUS_ACTIVE, AutoAgora_Promotion_Manager::STATUS_SCHEDULED), true)) {
                echo '<a class="button button-small" href="' . esc_url($cancel_url) . '" onclick="return confirm(\'Cancel this promotion?\')">Cancel</a>';
            } else {
                echo '&mdash;';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function handle_grant()
    {
        $listing_id = isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0;
        self::authorize_listing($listing_id);
        check_admin_referer('autoagora_grant_listing_promotion_' . $listing_id, 'autoagora_promotion_nonce');

        $result = self::grant_from_request($listing_id);
        self::redirect($listing_id, is_wp_error($result) ? 'error' : 'granted', is_wp_error($result) ? $result->get_error_code() : '');
    }

    public static function handle_grant_ajax()
    {
        $listing_id = isset($_POST['listing_id']) ? absint($_POST['listing_id']) : 0;
        if (!self::can_manage_listing($listing_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to manage promotions for this listing.'), 403);
        }
        if (!check_ajax_referer('autoagora_grant_listing_promotion_' . $listing_id, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Your session has expired. Reload the page and try again.'), 403);
        }

        $result = self::grant_from_request($listing_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ), 400);
        }

        wp_send_json_success(array(
            'promotion_id' => (int) $result,
            'redirect_url' => self::edit_url($listing_id, 'granted'),
        ));
    }

    private static function grant_from_request($listing_id)
    {
        $tier = isset($_POST['autoagora_promotion_tier']) ? sanitize_key(wp_unslash($_POST['autoagora_promotion_tier'])) : '';
        $days = isset($_POST['autoagora_promotion_days']) ? absint($_POST['autoagora_promotion_days']) : 0;
        $notes = isset($_POST['autoagora_promotion_notes']) ? sanitize_textarea_field(wp_unslash($_POST['autoagora_promotion_notes'])) : '';
        $starts_at = self::parse_local_start(isset($_POST['autoagora_promotion_starts_at']) ? wp_unslash($_POST['autoagora_promotion_starts_at']) : '');

        return autoagora_promotion_manager()->grant_manual(
            $listing_id,
            $tier,
            $days * DAY_IN_SECONDS,
            $starts_at,
            get_current_user_id(),
            $notes
        );
    }

    public static function handle_cancel()
    {
        $promotion_id = isset($_GET['promotion_id']) ? absint($_GET['promotion_id']) : 0;
        $repository = new AutoAgora_Promotion_Repository();
        $record = $repository->find($promotion_id);
        if (!$record) {
            wp_die('Promotion not found.', 'Not found', array('response' => 404));
        }
        $listing_id = (int) $record->listing_id;
        self::authorize_listing($listing_id);
        check_admin_referer('autoagora_cancel_listing_promotion_' . $promotion_id);
        $result = autoagora_promotion_manager()->cancel($promotion_id);
        self::redirect($listing_id, is_wp_error($result) ? 'error' : 'cancelled', is_wp_error($result) ? $result->get_error_code() : '');
    }

    private static function authorize_listing($listing_id)
    {
        if (!self::can_manage_listing($listing_id)) {
            wp_die('You are not allowed to manage promotions for this listing.', 'Forbidden', array('response' => 403));
        }
    }

    private static function can_manage_listing($listing_id)
    {
        return $listing_id
            && get_post_type($listing_id) === 'car'
            && current_user_can('manage_options')
            && current_user_can('edit_post', $listing_id);
    }

    private static function parse_local_start($value)
    {
        $value = sanitize_text_field($value);
        if ($value === '') {
            return gmdate('Y-m-d H:i:s');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
        if (!$date) {
            return gmdate('Y-m-d H:i:s');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function display_gmt($value)
    {
        return $value ? get_date_from_gmt($value, 'Y-m-d H:i') : '—';
    }

    private static function redirect($listing_id, $notice, $error_code = '')
    {
        wp_safe_redirect(self::edit_url($listing_id, $notice, $error_code));
        exit;
    }

    private static function edit_url($listing_id, $notice, $error_code = '')
    {
        $args = array('post' => $listing_id, 'action' => 'edit', 'autoagora_promotion_notice' => $notice);
        if ($error_code !== '') {
            $args['autoagora_promotion_error'] = sanitize_key($error_code);
        }
        return add_query_arg(
            $args,
            admin_url('post.php')
        );
    }
}

AutoAgora_Promotion_Admin::init();
