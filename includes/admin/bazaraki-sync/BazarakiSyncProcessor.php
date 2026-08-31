<?php
/** Bounded queue processor and one-message completion reporting. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Bazaraki_Sync_Processor
{
    /** @return array<string,mixed>|WP_Error */
    public static function process(string $run_id, string $profile_id, int $limit)
    {
        $run = AutoAgora_Bazaraki_Sync_Queue::run($run_id, $profile_id);
        if (!$run) {
            return new WP_Error('bazaraki_sync_run_missing', __('Sync run was not found.', 'bricks-child'), array('status' => 404));
        }
        $profile = AutoAgora_Bazaraki_Sync_Profiles::get($profile_id);
        if (!$profile || empty($profile['enabled'])) {
            return new WP_Error('bazaraki_sync_profile_disabled', __('Sync profile is missing or disabled.', 'bricks-child'), array('status' => 409));
        }
        $profile['dry_run'] = !empty($run['dry_run']);
        $processed = 0;
        while ($processed < max(1, min(3, $limit))) {
            $job = AutoAgora_Bazaraki_Sync_Queue::claim($run_id);
            if (!$job) {
                break;
            }
            try {
                $status = AutoAgora_Bazaraki_Sync_Applier::apply($job, $profile, (string) $run['package_path']);
                AutoAgora_Bazaraki_Sync_Queue::finishJob((int) $job['id'], $status);
            } catch (Throwable $error) {
                AutoAgora_Bazaraki_Sync_Queue::finishJob((int) $job['id'], 'failed', $error->getMessage());
            }
            $processed++;
        }
        $complete = AutoAgora_Bazaraki_Sync_Queue::finalizeIfDone($run_id);
        $counts = AutoAgora_Bazaraki_Sync_Queue::statusCounts($run_id);
        if ($complete) {
            self::sendSummaryOnce($run_id);
            self::cleanupPackageIfSafe($run_id);
        }
        return array('run_id' => $run_id, 'processed' => $processed, 'complete' => $complete, 'jobs' => $counts);
    }

    private static function sendSummaryOnce(string $run_id): void
    {
        global $wpdb;
        $table = AutoAgora_Bazaraki_Sync_Schema::runsTable();
        $claimed = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET summary_sent=2,updated_at=%s WHERE run_id=%s AND summary_sent=0",
            current_time('mysql', true), $run_id
        ));
        if ($claimed !== 1) {
            return;
        }
        $run = AutoAgora_Bazaraki_Sync_Queue::run($run_id);
        if (!$run) {
            return;
        }
        $subject = sprintf('[%s] Bazaraki sync complete: %d successful, %d failed',
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            (int) $run['success_count'], (int) $run['failed_count']
        );
        $text = sprintf(
            "Bazaraki sync complete\nProfile: %s\nMode: %s\nSource listings: %d\nSuccessful jobs: %d\nNeeds review: %d\nFailed: %d",
            $run['profile_id'], !empty($run['dry_run']) ? 'dry run' : 'live', $run['source_count'], $run['success_count'], $run['review_count'], $run['failed_count']
        );
        $recipient = sanitize_email((string) get_option('admin_email'));
        $sent = false;
        if ($recipient !== '') {
            $sent = function_exists('send_app_email')
                ? send_app_email($recipient, $subject, nl2br(esc_html($text)), $text)
                : wp_mail($recipient, $subject, $text);
        }
        $wpdb->update(
            $table,
            array('summary_sent' => $sent ? 1 : 0, 'updated_at' => current_time('mysql', true)),
            array('run_id' => $run_id),
            array('%d', '%s'),
            array('%s')
        );
    }

    private static function cleanupPackageIfSafe(string $run_id): void
    {
        $run = AutoAgora_Bazaraki_Sync_Queue::run($run_id);
        $path = $run ? wp_normalize_path((string) $run['package_path']) : '';
        $uploads = wp_upload_dir();
        $base = wp_normalize_path(trailingslashit((string) ($uploads['basedir'] ?? '')) . 'autoagora-bazaraki-sync/');
        if ($path !== '' && str_starts_with($path, $base) && is_file($path)) {
            @unlink($path);
        }
    }
}
