<?php
/** Opt-in only. No endpoint, schema, credential or scheduled-task changes by default. */
defined('ABSPATH') || exit;

require_once __DIR__ . '/Support.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Review.php';
require_once __DIR__ . '/Installation.php';
\AutoAgora\Dealer\Installation::load();
\AutoAgora\Dealer\Installation::bootstrap();
// Keep existing review data private even if the API is disabled or dependencies are absent.
if (\AutoAgora\Dealer\Support::enabled() || get_option('autoagora_dealer_review_data_exists')) {
    \AutoAgora\Dealer\Review::bootstrap();
}
if (!\AutoAgora\Dealer\Support::enabled()) { return; }

if (PHP_VERSION_ID < 80200 || !extension_loaded('openssl') || !extension_loaded('curl') || !is_file(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', static function () {
        if (current_user_can('manage_options')) { echo '<div class="notice notice-error"><p>Dealer integrations require PHP 8.2+, OpenSSL, cURL and the packaged dependencies. The workspace remains disabled.</p></div>'; }
    });
    return;
}
require_once __DIR__ . '/vendor/autoload.php';
foreach (['Entities', 'Repositories', 'OAuth', 'Validation', 'Media', 'Listings', 'Rest', 'Cli', 'Workspace'] as $module) {
    require_once __DIR__ . '/' . $module . '.php';
}
\AutoAgora\Dealer\Workspace::bootstrap();
require_once dirname(__DIR__) . '/user-manage-listings/field-validation.php';
require_once dirname(__DIR__) . '/user-manage-listings/car-field-writer.php';

add_action('rest_api_init', [\AutoAgora\Dealer\Rest::class, 'register']);
add_action('template_redirect', [\AutoAgora\Dealer\OAuth::class, 'browser'], -20);
add_action('autoagora_dealer_cleanup', [\AutoAgora\Dealer\Support::class, 'cleanup']);
add_filter('rest_post_dispatch', static function ($response, $server, $request) {
    if (str_starts_with($request->get_route(), '/' . \AutoAgora\Dealer\Rest::NS . '/')) {
        $response->header('Cache-Control', 'no-store, private');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        if ($response->get_status() === 429) { $response->header('Retry-After', '60'); }
        if ($response->get_status() === 401) { $response->header('WWW-Authenticate', 'Bearer realm="AutoAgora dealer API"'); }
    }
    return $response;
}, 10, 3);

// Keep website/API writes for an API-managed dealer in the same critical section.
// MySQL releases these connection-scoped locks even if the original form handler exits.
add_action('init', static function () {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_user_logged_in()) { return; }
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['add_new_car_listing', 'edit_car_listing', 'mark_car_as_sold', 'toggle_car_status'], true)) { return; }
    $dealer = get_current_user_id();
    if (!\AutoAgora\Dealer\Support::eligible($dealer)) { return; }
    global $wpdb;
    $name = 'ag-dealer-' . substr(hash('sha256', $wpdb->prefix . 'dealer:' . $dealer), 0, 48);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $name)) !== 1) {
        wp_die('Another stock update is processing. Please retry shortly.', 'AutoAgora', ['response' => 409]);
    }
}, 50);
