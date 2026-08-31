<?php
/** Queue and run persistence. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Bazaraki_Sync_Queue
{
    /** @param array<string,int> $counts */
    public static function createRun(string $run_id, string $profile_id, string $package_path, array $counts, int $source_count, bool $dry_run, bool $suppress_summary = false)
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $inserted = $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . AutoAgora_Bazaraki_Sync_Schema::runsTable() . '
             (run_id,profile_id,status,dry_run,package_path,source_count,created_count,updated_count,missing_count,unchanged_count,summary_sent,created_at,updated_at)
             VALUES (%s,%s,%s,%d,%s,%d,%d,%d,%d,%d,%d,%s,%s)',
            $run_id, $profile_id, 'queued', $dry_run ? 1 : 0, $package_path, $source_count,
            (int) ($counts['created'] ?? 0), (int) ($counts['updated'] ?? 0),
            (int) ($counts['removed'] ?? 0), (int) ($counts['unchanged'] ?? 0),
            $suppress_summary ? 1 : 0, $now, $now
        ));
        if ($inserted === false) {
            return new WP_Error('bazaraki_sync_run_store', $wpdb->last_error ?: __('Could not store the sync run.', 'bricks-child'));
        }
        return true;
    }

    public static function recordFailure(string $run_id, string $profile_id, string $message): bool
    {
        global $wpdb;
        $now = current_time('mysql', true);
        return $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . AutoAgora_Bazaraki_Sync_Schema::runsTable() . '
             (run_id,profile_id,status,dry_run,package_path,failed_count,error_message,created_at,completed_at,updated_at)
             VALUES (%s,%s,%s,%d,%s,%d,%s,%s,%s,%s)',
            $run_id, $profile_id, 'scrape_failed', 0, '', 1, $message, $now, $now, $now
        )) !== false;
    }

    /** @param array<string,mixed> $payload */
    public static function addJob(string $run_id, string $profile_id, string $source_id, string $action, array $payload): bool
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }
        return $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . AutoAgora_Bazaraki_Sync_Schema::jobsTable() . '
             (run_id,profile_id,source_id,action,payload,status,created_at,updated_at)
             VALUES (%s,%s,%s,%s,%s,%s,%s,%s)',
            $run_id, $profile_id, $source_id, $action, $json, 'pending', $now, $now
        )) !== false;
    }

    /** @return array<string,mixed>|null */
    public static function claim(string $run_id): ?array
    {
        global $wpdb;
        $table = AutoAgora_Bazaraki_Sync_Schema::jobsTable();
        $stale_before = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status='pending',updated_at=%s WHERE run_id=%s AND status='processing' AND attempts<3 AND updated_at<%s",
            current_time('mysql', true), $run_id, $stale_before
        ));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status='failed',last_error=%s,updated_at=%s WHERE run_id=%s AND status='processing' AND attempts>=3 AND updated_at<%s",
            __('Processor stopped before the job completed three times.', 'bricks-child'), current_time('mysql', true), $run_id, $stale_before
        ));
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE run_id=%s AND status='pending' ORDER BY id ASC LIMIT 1",
                $run_id
            ), ARRAY_A);
            if (!$row) {
                return null;
            }
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status='processing',attempts=attempts+1,updated_at=%s WHERE id=%d AND status='pending'",
                current_time('mysql', true), (int) $row['id']
            ));
            if ($updated === 1) {
                $row['payload'] = json_decode((string) $row['payload'], true);
                return $row;
            }
        }
        return null;
    }

    public static function finishJob(int $job_id, string $status, string $error = ''): void
    {
        global $wpdb;
        $allowed = array('complete', 'review', 'failed');
        if (!in_array($status, $allowed, true)) {
            $status = 'failed';
        }
        $wpdb->update(
            AutoAgora_Bazaraki_Sync_Schema::jobsTable(),
            array('status' => $status, 'last_error' => $error, 'updated_at' => current_time('mysql', true)),
            array('id' => $job_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }

    /** @return array<string,mixed>|null */
    public static function run(string $run_id, string $profile_id = ''): ?array
    {
        global $wpdb;
        $sql = 'SELECT * FROM ' . AutoAgora_Bazaraki_Sync_Schema::runsTable() . ' WHERE run_id=%s';
        $args = array($run_id);
        if ($profile_id !== '') {
            $sql .= ' AND profile_id=%s';
            $args[] = $profile_id;
        }
        $sql .= ' LIMIT 1';
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$args), ARRAY_A);
        return $row ?: null;
    }

    /** @return array<string,int> */
    public static function statusCounts(string $run_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT status,COUNT(*) AS total FROM ' . AutoAgora_Bazaraki_Sync_Schema::jobsTable() . ' WHERE run_id=%s GROUP BY status',
            $run_id
        ), ARRAY_A);
        $counts = array('pending' => 0, 'processing' => 0, 'complete' => 0, 'review' => 0, 'failed' => 0);
        foreach ((array) $rows as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }
        return $counts;
    }

    public static function finalizeIfDone(string $run_id): bool
    {
        $counts = self::statusCounts($run_id);
        if ($counts['pending'] > 0 || $counts['processing'] > 0) {
            return false;
        }
        global $wpdb;
        $wpdb->update(
            AutoAgora_Bazaraki_Sync_Schema::runsTable(),
            array(
                'status' => $counts['failed'] > 0 ? 'completed_with_errors' : 'completed',
                'success_count' => $counts['complete'],
                'failed_count' => $counts['failed'],
                'review_count' => $counts['review'],
                'completed_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ),
            array('run_id' => $run_id),
            array('%s', '%d', '%d', '%d', '%s', '%s'),
            array('%s')
        );
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public static function recentRuns(int $limit = 20): array
    {
        global $wpdb;
        return (array) $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . AutoAgora_Bazaraki_Sync_Schema::runsTable() . ' ORDER BY id DESC LIMIT %d',
            max(1, min(100, $limit))
        ), ARRAY_A);
    }
}
