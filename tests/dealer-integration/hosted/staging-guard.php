<?php
/** Site-specific staging isolation. Install only as an MU plugin on staging7. */
defined('ABSPATH') || exit;
$agStageRoot = '/home/customer/www/staging7.autoagora.cy/public_html';
$agStageHost = 'staging7.autoagora.cy';
if (wp_get_environment_type() !== 'staging' || realpath(ABSPATH) !== $agStageRoot || strtolower((string) wp_parse_url(get_option('home'), PHP_URL_HOST)) !== $agStageHost) { return; }
$agStagePrivate = dirname($agStageRoot) . '/.autoagora-staging';
$agStageConfigFile = $agStagePrivate . '/sandbox.json';
$agStageConfig = is_readable($agStageConfigFile) ? json_decode(file_get_contents($agStageConfigFile), true) : [];
$agStageConfig = is_array($agStageConfig) ? $agStageConfig : [];
$agStageDealers = array_map('intval', $agStageConfig['dealer_ids'] ?? []);
$agStageExpires = (int) ($agStageConfig['expires_at'] ?? 0);
$agStageValues = [
 'AUTOAGORA_DEALER_API_ENABLED' => true, 'AUTOAGORA_DEALER_API_PRODUCTION_APPROVED' => false,
 'AUTOAGORA_DEALER_PUBLIC_ENABLED' => true, 'AUTOAGORA_DEALER_RULES_VERIFIED' => true,
 'AUTOAGORA_DEALER_PRIVATE_KEY' => $agStagePrivate . '/private.pem',
 'AUTOAGORA_DEALER_PUBLIC_KEY' => $agStagePrivate . '/public.pem',
 'AUTOAGORA_DEALER_ENCRYPTION_KEY' => $agStageConfig['encryption'] ?? '',
 'AUTOAGORA_DEALER_REVIEW_STORAGE' => $agStagePrivate . '/review-media',
 'DISABLE_WP_CRON' => true, 'DISALLOW_FILE_EDIT' => true,
];
foreach ($agStageValues as $key => $value) { if (!defined($key)) { define($key, $value); } }
// No customer mail or third-party HTTP calls, including theme Resend notifications.
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);
add_filter('autoagora_pre_send_app_email', '__return_false', PHP_INT_MAX);
add_filter('pre_http_request', static function ($pre, $args, $url) use ($agStageHost) {
 return strtolower((string) wp_parse_url($url, PHP_URL_HOST)) === $agStageHost ? $pre : new WP_Error('staging_external_http_blocked', 'External services are disabled on staging.');
}, PHP_INT_MAX, 3);
add_filter('pre_option_blog_public', '__return_zero');
// Use the username/password form without granting backend access.
add_filter('login_url', static fn ($url, $redirect) => add_query_arg(['admin_access' => 'true', 'redirect_to' => $redirect ?: home_url('/?autoagora_connect=workspace')], site_url('/wp-login.php')), PHP_INT_MAX, 2);
add_filter('login_redirect', static function ($url, $requested, $user) use ($agStageDealers) {
 if ($user instanceof WP_User && in_array((int) $user->ID, $agStageDealers, true)) {
  return class_exists('AutoAgora\\Dealer\\OAuth') && \AutoAgora\Dealer\OAuth::loginReturn($requested) ? $requested : home_url('/?autoagora_connect=workspace');
 }
 return $url;
}, PHP_INT_MAX, 3);
add_filter('wp_robots', static fn ($robots) => ['noindex' => true, 'nofollow' => true, 'noarchive' => true]);
add_filter('autoagora_dealer_api_eligible', static fn ($ok, $id) => $ok && time() < $agStageExpires && in_array((int) $id, $agStageDealers, true), PHP_INT_MAX, 2);
add_filter('autoagora_dealer_api_can_save', static fn ($ok, $id) => time() < $agStageExpires && in_array((int) $id, $agStageDealers, true) ? $ok : new WP_Error('staging_account_required', 'Use the designated staging test account.', ['status' => 403]), PHP_INT_MAX, 2);
add_filter('autoagora_dealer_api_submission_status', static fn ($status, $id) => time() < $agStageExpires && in_array((int) $id, $agStageDealers, true) ? 'publish' : 'pending', PHP_INT_MAX, 2);
add_filter('authenticate', static function ($user, $username) use ($agStageDealers, $agStageExpires) {
 $accountBucket = 'agstage_login_' . hash('sha256', strtolower((string) $username));
 $ipBucket = 'agstage_login_' . hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
 if ((int) get_transient($accountBucket) >= 5 || (int) get_transient($ipBucket) >= 20) { return new WP_Error('staging_login_limited', 'Too many attempts. Try again in 15 minutes.'); }
 if ($user instanceof WP_User && !user_can($user, 'manage_options') && (!in_array((int) $user->ID, $agStageDealers, true) || time() >= $agStageExpires)) { return new WP_Error('staging_access_denied', 'Use your staging test account.'); }
 return $user;
}, PHP_INT_MAX, 2);
add_action('wp_login_failed', static function ($username) {
 foreach ([strtolower((string) $username), 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')] as $subject) { $bucket = 'agstage_login_' . hash('sha256', $subject); set_transient($bucket, (int) get_transient($bucket) + 1, 900); }
});
add_filter('auth_cookie_expiration', static fn ($seconds, $user) => in_array((int) $user, $agStageDealers, true) ? HOUR_IN_SECONDS : $seconds, PHP_INT_MAX, 2);
if (defined('DOING_CRON') && DOING_CRON) { exit; }
add_action('send_headers', static function () {
 header('X-Robots-Tag: noindex, nofollow, noarchive');
 header('X-AutoAgora-Environment: isolated-staging');
 header('Cache-Control: private, no-store');
 header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
});
add_action('template_redirect', static function () use ($agStageDealers, $agStageExpires) {
 if (current_user_can('manage_options') || (time() < $agStageExpires && in_array(get_current_user_id(), $agStageDealers, true))) { return; }
 auth_redirect();
}, -100);
// Cloned customer stock is not part of this partner sandbox.
add_action('pre_get_posts', static function ($query) use ($agStageDealers) {
 if (!is_admin() && in_array('car', (array) $query->get('post_type'), true)) {
  $requested = array_map('intval', (array) $query->get('author__in'));
  if (!$requested && $query->get('author') !== '') { $requested = array_map('intval', explode(',', (string) $query->get('author'))); }
  $query->set('author__in', $requested ? (array_values(array_intersect($requested, $agStageDealers)) ?: [0]) : ($agStageDealers ?: [0]));
 }
});
add_action('template_redirect', static function () use ($agStageDealers) {
 if (is_singular('car') && !in_array((int) get_post_field('post_author', get_queried_object_id()), $agStageDealers, true)) { wp_die('Not part of the staging sandbox.', '', ['response' => 404]); }
}, -90);
// Test users must use the scoped API. Never let native forms mutate cloned records.
add_action('init', static function () use ($agStageDealers) {
 if (!in_array(get_current_user_id(), $agStageDealers, true)) { return; }
 $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
 if (str_starts_with($path, '/wp-admin')) { wp_die('Backend access is disabled for staging test accounts.', '', ['response' => 403]); }
 if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') { return; }
 if ($path === '/wp-login.php' || str_starts_with($path, '/wp-json/autoagora-dealer/v1/') || isset($_GET['autoagora_stage_client']) || in_array($_GET['autoagora_connect'] ?? '', ['authorize', 'connections', 'workspace'], true)) { return; }
 wp_die('Use the staging connection tester to change test stock.', '', ['response' => 403]);
}, 1);
add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
 if (current_user_can('manage_options') || str_starts_with($request->get_route(), '/autoagora-dealer/v1/')) { return $result; }
 return new WP_Error('staging_rest_restricted', 'Staging API access is restricted.', ['status' => 403]);
}, PHP_INT_MAX, 3);
// Remind operators that this isolated database must never be deployed to production.
add_action('wp_head', static function () { echo '<style>@font-face{font-family:Inter;font-style:normal;font-weight:100 900;font-display:swap;src:url(/wp-content/themes/bricks/assets/fonts/builder/Inter.woff2) format("woff2")}@font-face{font-family:Outfit;font-style:normal;font-weight:100 900;font-display:swap;src:url(/staging-test-assets/Outfit-latin.woff2) format("woff2")}</style>'; });
add_action('wp_body_open', static function () { echo '<div role="status" style="background:#fff3cd;color:#664d03;padding:10px 16px;text-align:center;font:600 14px/1.5 system-ui">STAGING TEST SITE — synthetic stock only. Nothing here is published to autoagora.cy. <a href="/?autoagora_connect=workspace" style="color:inherit;text-decoration:underline">Dashboard</a></div>'; });
add_action('after_setup_theme', static function () { remove_action('bricks_after_header', 'show_email_verification_notification'); remove_action('wp_enqueue_scripts', 'enqueue_email_notification_assets'); }, PHP_INT_MAX);
add_action('admin_notices', static function () { echo '<div class="notice notice-warning"><p><strong>STAGING ONLY.</strong> External services and scheduled jobs are disabled. Never deploy this database or sandbox configuration to production.</p></div>'; });
require_once __DIR__ . '/autoagora-staging-client.php';

require_once __DIR__ . '/autoagora-staging-workspace.php';
