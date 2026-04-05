<?php
/**
 * One-time cohort stats table creation via a secret URL.
 * Remove this file and its require from functions.php after the table exists.
 *
 * Visit (while logged in as an administrator):
 * {site_url}/?bc_car_price_cohort_setup=1&bc_key={BRICKS_CHILD_CAR_PRICE_COHORT_SETUP_SECRET below}
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates wp_car_price_cohorts (with prefix) if it does not exist.
 */
final class CarPriceCohortTableSetup {

    const DB_VERSION = '1.0.0';

    /**
     * Change this to a long random string before running the URL, then remove this file after success.
     */
    const SETUP_SECRET = 'onlyadmin';

    /**
     * Query args for the one-time installer URL.
     */
    const QUERY_FLAG = 'bc_car_price_cohort_setup';
    const QUERY_KEY = 'bc_key';

    public static function register_hooks() {
        add_action('init', array(__CLASS__, 'maybe_handle_setup_request'), 0);
    }

    /**
     * @return string Unprefixed logical name (prefix applied via $wpdb).
     */
    public static function table_basename() {
        return 'car_price_cohorts';
    }

    /**
     * @return string Full table name including prefix.
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::table_basename();
    }

    /**
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;
        $table = self::table_name();
        $like = $wpdb->esc_like($table);
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        return ($found === $table);
    }

    /**
     * Creates the table when missing. Safe to call multiple times (dbDelta + existence check).
     *
     * @return array{ created: bool, message: string }
     */
    public static function ensure_table() {
        if (self::table_exists()) {
            return array(
                'created' => false,
                'message' => 'Table already exists: ' . self::table_name(),
            );
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table = self::table_name();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cohort_key varchar(191) NOT NULL,
            listing_count int(10) unsigned NOT NULL DEFAULT 0,
            median_price decimal(12,2) NOT NULL DEFAULT 0.00,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY cohort_key (cohort_key)
        ) {$charset_collate};";

        dbDelta($sql);

        if (!self::table_exists()) {
            return array(
                'created' => false,
                'message' => 'Table creation failed. Check database user permissions and try again.',
            );
        }

        update_option('bricks_child_car_price_cohorts_db_version', self::DB_VERSION);

        return array(
            'created' => true,
            'message' => 'Table created: ' . self::table_name(),
        );
    }

    public static function maybe_handle_setup_request() {
        if (!isset($_GET[self::QUERY_FLAG], $_GET[self::QUERY_KEY])) {
            return;
        }

        if (sanitize_text_field(wp_unslash($_GET[self::QUERY_FLAG])) !== '1') {
            return;
        }

        $provided = isset($_GET[self::QUERY_KEY]) ? (string) wp_unslash($_GET[self::QUERY_KEY]) : '';
        if (self::SETUP_SECRET === 'REPLACE_ME_WITH_A_LONG_RANDOM_SECRET_BEFORE_USE' || $provided === '') {
            wp_die('Set CarPriceCohortTableSetup::SETUP_SECRET in code to a long random string, then retry.', 'Cohort table setup', array('response' => 403));
        }

        if (!hash_equals(self::SETUP_SECRET, $provided)) {
            wp_die('Invalid key.', 'Cohort table setup', array('response' => 403));
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die('You must be logged in as an administrator.', 'Cohort table setup', array('response' => 403));
        }

        $result = self::ensure_table();
        $status = $result['created'] ? 200 : 200;
        wp_die(
            esc_html($result['message']),
            'Cohort table setup',
            array('response' => $status)
        );
    }
}

CarPriceCohortTableSetup::register_hooks();
