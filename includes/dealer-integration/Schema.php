<?php
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Schema
{
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        update_option('autoagora_dealer_schema', 'installing', false);
        $charset = $wpdb->get_charset_collate();
        $definitions = [
            'clients' => 'id varchar(64) NOT NULL, name varchar(191) NOT NULL, secret_hash varchar(255) NOT NULL, redirects text NOT NULL, scopes text NOT NULL, enabled tinyint NOT NULL DEFAULT 1, PRIMARY KEY  (id)',
            'grants' => 'id varchar(36) NOT NULL, client_id varchar(64) NOT NULL, dealer_id bigint(20) unsigned NOT NULL, scopes text NOT NULL, revoked tinyint NOT NULL DEFAULT 0, created_at bigint(20) NOT NULL, PRIMARY KEY  (id), KEY owner_client (dealer_id,client_id)',
            'tokens' => 'id varchar(100) NOT NULL, kind varchar(12) NOT NULL, grant_id varchar(36) NOT NULL, client_id varchar(64) NOT NULL, dealer_id bigint(20) unsigned NOT NULL, expires_at bigint(20) NOT NULL, revoked tinyint NOT NULL DEFAULT 0, wire_hash varchar(64) NULL, PRIMARY KEY  (id), KEY grant_id (grant_id), KEY wire_hash (wire_hash), KEY expires_at (expires_at)',
            'stocks' => 'stock_key varchar(64) NOT NULL, client_id varchar(64) NOT NULL, dealer_id bigint(20) unsigned NOT NULL, external_id varchar(128) NOT NULL, listing_id bigint(20) unsigned NOT NULL DEFAULT 0, created_at bigint(20) NOT NULL, PRIMARY KEY  (stock_key), KEY listing_id (listing_id), KEY owner_client (dealer_id,client_id)',
            'requests' => 'request_key varchar(64) NOT NULL, payload_hash varchar(64) NOT NULL, response longtext NULL, status smallint NULL, created_at bigint(20) NOT NULL, PRIMARY KEY  (request_key), KEY created_at (created_at)',
            'audit' => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT, event varchar(64) NOT NULL, client_id varchar(64) NOT NULL, dealer_id bigint(20) unsigned NOT NULL, listing_id bigint(20) unsigned NOT NULL, created_at bigint(20) NOT NULL, PRIMARY KEY  (id), KEY created_at (created_at)',
            'limits' => 'bucket varchar(64) NOT NULL, hits int unsigned NOT NULL, expires_at bigint(20) NOT NULL, PRIMARY KEY  (bucket), KEY expires_at (expires_at)',
        ];
        foreach ($definitions as $suffix => $sql) {
            // dbDelta expects one definition per line.
            $sql = str_replace(', PRIMARY KEY', ",\nPRIMARY KEY", $sql);
            $sql = str_replace(', KEY', ",\nKEY", $sql);
            $sql = preg_replace('/, (?=[a-z_]+ (?:varchar|text|longtext|bigint|tinyint|smallint|int))/', ",\n", $sql);
            dbDelta('CREATE TABLE ' . Support::table($suffix) . " (\n$sql\n) $charset;");
            if ($wpdb->last_error) {
                throw new \RuntimeException('Dealer integration table installation failed.');
            }
        }
        $tables = array_merge([$wpdb->posts, $wpdb->postmeta, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships], array_map([Support::class, 'table'], array_keys($definitions)));
        $placeholders = implode(',', array_fill(0, count($tables), '%s'));
        $engines = $wpdb->get_col($wpdb->prepare("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)", $tables));
        if (count($engines) !== count($tables) || array_diff($engines, ['InnoDB'])) {
            throw new \RuntimeException('Transactional InnoDB tables are required for the dealer integration.');
        }
        update_option('autoagora_dealer_schema', '1', false);
    }
}
