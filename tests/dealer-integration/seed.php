<?php
/** Invoked only by setup-local.py after a new disposable installation. */
if (wp_get_environment_type() !== 'local') { WP_CLI::error('Local fixture only.'); }
$root = getenv('AUTOAGORA_SOURCE_ROOT');
if (!$root || !is_file($root . '/.private/test-accounts.json')) { WP_CLI::error('Missing local seed configuration.'); }
$accounts = json_decode(file_get_contents($root . '/.private/test-accounts.json'), true);
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$charset = $wpdb->get_charset_collate();
dbDelta("CREATE TABLE {$wpdb->prefix}seller_reviews (
id bigint unsigned NOT NULL AUTO_INCREMENT,
seller_id bigint unsigned NOT NULL,
reviewer_id bigint unsigned NOT NULL DEFAULT 0,
reviewer_email varchar(255) NULL,
rating tinyint NOT NULL,
comment text NOT NULL,
contacted_seller tinyint NOT NULL DEFAULT 0,
status varchar(20) NOT NULL DEFAULT 'pending',
review_date datetime NOT NULL,
PRIMARY KEY  (id),
KEY seller_id (seller_id)
) $charset;");
dbDelta("CREATE TABLE {$wpdb->prefix}car_views (
id bigint unsigned NOT NULL AUTO_INCREMENT,
car_id bigint unsigned NOT NULL,
user_id bigint unsigned NOT NULL DEFAULT 0,
user_ip_hash varchar(64) NOT NULL,
user_agent_hash varchar(64) NOT NULL,
fingerprint_hash varchar(64) NOT NULL,
view_type varchar(10) NOT NULL,
view_date datetime NOT NULL,
PRIMARY KEY  (id),
KEY car_id (car_id),
KEY fingerprint_hash (fingerprint_hash)
) $charset;");
foreach (['dealer_one', 'dealer_two'] as $name) {
    $record = $accounts[$name];
    if (username_exists($record['username'])) { continue; }
    $id = wp_insert_user(['user_login' => $record['username'], 'user_pass' => $record['password'], 'user_email' => $name . '@example.invalid', 'display_name' => ucwords(str_replace('_', ' ', $name)), 'role' => 'dealership']);
    if (is_wp_error($id)) { WP_CLI::error($id->get_error_message()); }
}
if (!username_exists('private-seller-test')) { wp_insert_user(['user_login' => 'private-seller-test', 'user_pass' => wp_generate_password(32), 'user_email' => 'private-seller@example.invalid', 'role' => 'client']); }

// Import taxonomy only from the dated field specification, never a customer database.
$source = $accounts['catalog_source'];
$text = file_get_contents($source);
if (!preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $match)) { WP_CLI::error('Could not find the reference make/model catalog.'); }
$catalog = json_decode($match[1], true);
if (!is_array($catalog) || !isset($catalog['BMW'])) { WP_CLI::error('Invalid reference catalog.'); }
foreach ($catalog as $make => $models) {
    $term = term_exists($make, 'car_make', 0);
    if (!$term) { $term = wp_insert_term($make, 'car_make'); }
    if (is_wp_error($term)) { WP_CLI::error('Could not seed make.'); }
    foreach ($models as $model) {
        $parent = (int) $term['term_id'];
        if (!term_exists($model, 'car_make', $parent)) { wp_insert_term($model, 'car_make', ['parent' => $parent, 'slug' => sanitize_title($make . '-' . $model)]); }
    }
}
foreach (['signin' => '[custom_login]', 'my-account' => '[my_account]', 'my-listings' => '[my_listings]', 'cars' => '[car_listings]'] as $slug => $content) {
    if (get_page_by_path($slug)) { continue; }
    $id = wp_insert_post(['post_type' => 'page', 'post_name' => $slug, 'post_title' => ucwords(str_replace('-', ' ', $slug)), 'post_content' => $content, 'post_status' => 'publish']);
    if ($slug === 'signin') { update_post_meta($id, '_wp_page_template', 'template-custom-login.php'); }
}
foreach (['add-listing' => 'template-add-listing.php', 'edit-listing' => 'template-edit-listing.php'] as $slug => $template) {
    $page = get_page_by_path($slug);
    $id = $page ? $page->ID : wp_insert_post(['post_type' => 'page', 'post_name' => $slug, 'post_title' => ucwords(str_replace('-', ' ', $slug)), 'post_status' => 'publish']);
    update_post_meta($id, '_wp_page_template', $template);
}
update_option('blog_public', 0);
update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules(false);
WP_CLI::success('Two synthetic dealerships and the reference catalog were seeded; no customer data copied.');
