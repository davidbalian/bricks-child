<?php
/**
 * Temporary admin tool for QAing listing notification emails.
 * Intended for local testing only; remove before deploy.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page(
        'Listing Notification Tester',
        'Listing Notification Tester',
        'manage_options',
        'listing-notification-tester',
        'render_listing_notification_tester_page'
    );
});

function render_listing_notification_tester_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied'));
    }

    $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
    $action = isset($_POST['notification_action']) ? sanitize_text_field($_POST['notification_action']) : '';
    $notice = '';

    if (wp_verify_nonce($_POST['_wpnonce'] ?? '', 'listing_notification_tester')) {
        if ($listing_id && get_post_type($listing_id) === 'car') {
            $response = '';
            $manager = listing_notification_manager();
            if ($action === 'view_milestone') {
                $sent = $manager->maybeSendViewMilestoneNotification($listing_id);
                $response = $sent ? 'View milestone email dispatched (if conditions met).' : 'No view milestone sent (probably already marked).';
            } elseif ($action === 'reminder') {
                $refresh_url = admin_url('admin.php?page=my-listings');
                $mark_as_sold_url = admin_url('admin.php?page=my-listings');
                $sent = $manager->maybeSendReminderNotification($listing_id, $refresh_url, $mark_as_sold_url);
                $response = $sent ? 'Reminder email sent.' : 'Reminder not sent (maybe already at limit or preferences off).';
            } else {
                $response = 'Unknown action.';
            }
            $notice = esc_html($response);
        } else {
            $notice = 'Please provide a valid car listing ID.';
        }
    }

    ?>
    <div class="wrap">
        <h1>Listing Notification Tester (QA only)</h1>
        <?php if ($notice): ?>
            <div class="notice notice-success">
                <p><?php echo $notice; ?></p>
            </div>
        <?php endif; ?>
        <form method="post" style="max-width:420px;">
            <?php wp_nonce_field('listing_notification_tester'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="listing_id">Listing ID</label></th>
                    <td><input name="listing_id" type="number" id="listing_id" class="regular-text" required value="<?php echo esc_attr($listing_id); ?>"></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="notification_action" value="view_milestone" class="button button-primary">Send view milestone email</button>
                <button type="submit" name="notification_action" value="reminder" class="button">Send reminder email</button>
            </p>
        </form>
        <p><strong>Note:</strong> This tool reuses the real notification manager and is intended for staging/local QA. Remove before pushing to production.</p>
    </div>
    <?php
}

