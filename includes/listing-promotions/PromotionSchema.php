<?php
/**
 * Database schema for listing promotions.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Promotion_Schema
{
    const VERSION = '1.0.0';
    const VERSION_OPTION = 'autoagora_listing_promotions_schema_version';

    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'autoagora_listing_promotions';
    }

    public static function maybe_install()
    {
        if (get_option(self::VERSION_OPTION) === self::VERSION && self::exists()) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;

        $table = self::table_name();
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
            notes text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY listing_status (listing_id,status),
            KEY status_starts (status,starts_at),
            KEY status_ends (status,ends_at),
            UNIQUE KEY payment_event (payment_provider,payment_reference)
        ) {$charset_collate};";

        dbDelta($sql);

        if (self::exists()) {
            update_option(self::VERSION_OPTION, self::VERSION, false);
        }
    }

    public static function exists()
    {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }
}
