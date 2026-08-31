<?php
/** Durable storage for signed Bazaraki sync runs and their small work queue. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Bazaraki_Sync_Schema
{
    private const VERSION = '1.1.0';
    private const VERSION_OPTION = 'autoagora_bazaraki_sync_schema_version';

    public static function runsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'autoagora_bazaraki_sync_runs';
    }

    public static function jobsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'autoagora_bazaraki_sync_jobs';
    }

    public static function maybeInstall(): void
    {
        if (get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $collate = $wpdb->get_charset_collate();
        $runs = self::runsTable();
        $jobs = self::jobsTable();

        dbDelta("CREATE TABLE {$runs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            run_id varchar(64) NOT NULL,
            profile_id varchar(64) NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'queued',
            dry_run tinyint(1) unsigned NOT NULL DEFAULT 1,
            package_path text NOT NULL,
            source_count int(10) unsigned NOT NULL DEFAULT 0,
            created_count int(10) unsigned NOT NULL DEFAULT 0,
            updated_count int(10) unsigned NOT NULL DEFAULT 0,
            missing_count int(10) unsigned NOT NULL DEFAULT 0,
            unchanged_count int(10) unsigned NOT NULL DEFAULT 0,
            success_count int(10) unsigned NOT NULL DEFAULT 0,
            failed_count int(10) unsigned NOT NULL DEFAULT 0,
            review_count int(10) unsigned NOT NULL DEFAULT 0,
            summary_sent tinyint(1) unsigned NOT NULL DEFAULT 0,
            error_message text NULL,
            created_at datetime NOT NULL,
            completed_at datetime NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY profile_run (profile_id,run_id),
            KEY status_created (status,created_at)
        ) {$collate};");

        dbDelta("CREATE TABLE {$jobs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            run_id varchar(64) NOT NULL,
            profile_id varchar(64) NOT NULL,
            source_id varchar(96) NOT NULL,
            action varchar(16) NOT NULL,
            payload longtext NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'pending',
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            last_error text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY run_action_source (run_id,action,source_id),
            KEY run_status (run_id,status,id),
            KEY profile_status (profile_id,status,id)
        ) {$collate};");

        $runs_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($runs))) === $runs;
        $jobs_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($jobs))) === $jobs;
        $dry_run_exists = $runs_exists && $wpdb->get_var("SHOW COLUMNS FROM {$runs} LIKE 'dry_run'") === 'dry_run';
        if ($runs_exists && $jobs_exists && $dry_run_exists) {
            update_option(self::VERSION_OPTION, self::VERSION, false);
        }
    }
}
