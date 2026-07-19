<?php
/**
 * Database schema for listing promotions.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Promotion_Schema
{
    const VERSION = '1.2.0';
    const VERSION_OPTION = 'autoagora_listing_promotions_schema_version';

    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'autoagora_listing_promotions';
    }

    public static function payment_events_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'autoagora_payment_events';
    }

    public static function maybe_install()
    {
        if (self::is_current()) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;

        $table = self::table_name();
        $events_table = self::payment_events_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            listing_id bigint(20) unsigned NOT NULL,
            tier varchar(32) NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'scheduled',
            source varchar(24) NOT NULL,
            starts_at datetime NULL,
            ends_at datetime NULL,
            duration_seconds bigint(20) unsigned NOT NULL DEFAULT 0,
            remaining_seconds bigint(20) unsigned NOT NULL DEFAULT 0,
            granted_by bigint(20) unsigned NULL,
            payment_provider varchar(32) NULL,
            payment_reference varchar(191) NULL,
            amount_minor bigint(20) unsigned NOT NULL DEFAULT 0,
            currency char(3) NULL,
            refunded_amount_minor bigint(20) unsigned NOT NULL DEFAULT 0,
            stripe_checkout_session_id varchar(191) NULL,
            notes text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY listing_status (listing_id,status),
            KEY status_starts (status,starts_at),
            KEY status_ends (status,ends_at),
            KEY stripe_checkout_session (stripe_checkout_session_id),
            UNIQUE KEY payment_event (payment_provider,payment_reference)
        ) {$charset_collate};";

        $events_sql = "CREATE TABLE {$events_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider varchar(32) NOT NULL,
            event_id varchar(191) NOT NULL,
            event_type varchar(64) NOT NULL,
            object_reference varchar(191) NULL,
            status varchar(24) NOT NULL DEFAULT 'received',
            error_code varchar(64) NULL,
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY provider_event (provider,event_id),
            KEY object_status (provider,object_reference,status),
            KEY status_created (status,created_at)
        ) {$charset_collate};";

        dbDelta($sql);
        dbDelta($events_sql);

        if (self::exists() && self::payment_events_exists() && self::has_payment_snapshot_columns()) {
            update_option(self::VERSION_OPTION, self::VERSION, false);
        }
        self::is_current(true);
    }

    public static function exists()
    {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    public static function payment_events_exists()
    {
        global $wpdb;
        $table = self::payment_events_table_name();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    public static function is_current($refresh = false)
    {
        static $current = null;
        if (!$refresh && $current !== null) {
            return $current;
        }
        $current = get_option(self::VERSION_OPTION) === self::VERSION
            && self::exists()
            && self::payment_events_exists()
            && self::has_payment_snapshot_columns();
        return $current;
    }

    private static function has_payment_snapshot_columns()
    {
        if (!self::exists()) {
            return false;
        }
        global $wpdb;
        $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . self::table_name(), 0);
        $required = array('amount_minor', 'currency', 'refunded_amount_minor', 'stripe_checkout_session_id');
        return !array_diff($required, array_map('strtolower', (array) $columns));
    }
}
