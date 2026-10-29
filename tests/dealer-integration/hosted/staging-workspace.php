<?php
/** Dealer dashboard adapter, installed only in the isolated staging7 MU plugins. */
defined('ABSPATH') || exit;
if (wp_get_environment_type() !== 'staging'
    || realpath(ABSPATH) !== '/home/customer/www/staging7.autoagora.cy/public_html'
    || wp_parse_url(get_option('home'), PHP_URL_HOST) !== 'staging7.autoagora.cy') { return; }

use AutoAgora\Dealer\Support;
use AutoAgora\Dealer\Review;
use AutoAgora\Dealer\Workspace;
use AutoAgora\Dealer\Clients;
use AutoAgora\Dealer\Grants;
use AutoAgora\Dealer\Listings;
use AutoAgora\Dealer\Rest;

final class AutoAgoraStagingWorkspace
{
    public static function allowed(): bool
    {
        return class_exists(Support::class) && class_exists(Review::class) && class_exists(Workspace::class) && Support::ready()
            && wp_get_environment_type() === 'staging'
            && realpath(ABSPATH) === '/home/customer/www/staging7.autoagora.cy/public_html'
            && wp_parse_url(get_option('home'), PHP_URL_HOST) === 'staging7.autoagora.cy'
            && is_user_logged_in() && !Review::admin()
            && !Review::privateMode() && Support::eligible(get_current_user_id());
    }

    public static function render(): void
    {
        if (($_GET['autoagora_connect'] ?? '') !== 'workspace') { return; }
        if (!class_exists(Support::class) || !class_exists(Review::class) || !class_exists(Workspace::class) || !Support::ready()) { wp_die('The staging dashboard is temporarily unavailable.', '', ['response' => 503]); }
        if (Review::admin()) { return; }
        if (!self::allowed()) { wp_die('Dashboard access is not available for this account.', '', ['response' => 403]); }
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header('X-AutoAgora-Dashboard-Revision: ' . substr(hash_file('sha256', __FILE__), 0, 16) . '-' . substr(hash_file('sha256', get_stylesheet_directory() . '/includes/dealer-integration/workspace-template.php'), 0, 16));
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'");
        $tabs = ['connections', 'stock', 'activity'];
        $tab = isset($_GET['tab']) && is_string($_GET['tab']) ? $_GET['tab'] : 'connections';
        if (!in_array($tab, $tabs, true)) { wp_die('This dashboard section is not available.', '', ['response' => 403]); }
        $notice = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!isset($_POST['workspace_nonce']) || !is_string($_POST['workspace_nonce'])
                || !wp_verify_nonce(wp_unslash($_POST['workspace_nonce']), 'ag-workspace')) {
                wp_die('The form expired. Reload and try again.', '', ['response' => 403]);
            }
            try { $result = self::perform(wp_unslash($_POST)); }
            catch (Throwable $error) { $result = Support::error('staging_action_failed', 'The change could not be completed. Your stock has been retained.', 500); }
            if (is_wp_error($result)) {
                status_header((int) ($result->get_error_data()['status'] ?? 400));
                $notice = ['error', $result->get_error_message()];
            } else {
                $return = isset($_POST['return_tab']) && is_string($_POST['return_tab']) && in_array($_POST['return_tab'], $tabs, true) ? $_POST['return_tab'] : 'connections';
                wp_safe_redirect(Workspace::url(['tab' => $return, 'saved' => $result])); exit;
            }
        }
        $messages = ['connected' => 'Application connected.', 'disconnected' => 'Application disconnected. Listings and photos are retained.', 'stock_created' => 'Test listing posted on staging.', 'stock_updated' => 'Listing changes saved.', 'sold' => 'Listing marked sold.', 'withdrawn' => 'Listing withdrawn. Details and photos are retained.', 'connection_cancelled' => 'Connection cancelled or expired. Try connecting again.'];
        $saved = isset($_GET['saved']) && is_string($_GET['saved']) ? $_GET['saved'] : '';
        if (!$notice && isset($messages[$saved])) { $notice = [$saved === 'connection_cancelled' ? 'error' : 'success', $messages[$saved]]; }
        $search = isset($_GET['q']) && is_string($_GET['q']) ? substr(sanitize_text_field(wp_unslash($_GET['q'])), 0, 80) : '';
        $dealerFilter = isset($_GET['dealer']) && is_scalar($_GET['dealer']) ? absint($_GET['dealer']) : 0;
        $state = self::data($tab, $search, $dealerFilter);
        require get_stylesheet_directory() . '/includes/dealer-integration/workspace-template.php';
        exit;
    }

    public static function stockConnection(int $id): ?array
    {
        if (!self::allowed() || (int) get_post_field('post_author', $id) !== get_current_user_id() || Review::hidden($id)) { return null; }
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT client_id,dealer_id FROM ' . Support::table('stocks') . ' WHERE listing_id=%d AND dealer_id=%d LIMIT 1', $id, get_current_user_id()), ARRAY_A) ?: null;
    }

    private static function perform(array $input)
    {
        if (!self::allowed() || !Support::ready()) { return Support::error('staging_unavailable', 'This staging dashboard is not ready.', 403); }
        $dealer = get_current_user_id();
        if (!isset($input['dealer_id']) || !is_scalar($input['dealer_id']) || !ctype_digit((string) $input['dealer_id']) || (int) $input['dealer_id'] !== $dealer) {
            return Support::error('dealer_forbidden', 'You can manage only your own staging account.', 403);
        }
        $action = is_string($input['workspace_action'] ?? null) ? $input['workspace_action'] : '';
        if (!in_array($action, ['connect', 'disconnect', 'add_stock', 'edit_stock', 'sold', 'withdrawn', 'logout'], true)) {
            return Support::error('action_forbidden', 'This action is not available to dealership accounts.', 403);
        }
        if ($action === 'logout') { wp_logout(); wp_safe_redirect(wp_login_url(Workspace::url())); exit; }
        $client = is_string($input['client_id'] ?? null) ? $input['client_id'] : '';
        if ($action === 'connect') {
            if ($client !== AutoAgoraStagingClient::clientId()) { return Support::error('client_forbidden', 'Choose the staging test application.', 403); }
            AutoAgoraStagingClient::connect(); exit;
        }
        if ($action === 'disconnect') {
            return Support::lock('oauth:' . $client, static function () use ($client, $dealer) {
                if (!Grants::active($client, $dealer)) { return Support::error('connection_missing', 'This application is not connected to your account.', 404); }
                Grants::revoke($client, $dealer);
                if ($client === AutoAgoraStagingClient::clientId()) { AutoAgoraStagingClient::forget(); }
                return 'disconnected';
            });
        }
        if ($action === 'add_stock') {
            if ($client !== AutoAgoraStagingClient::clientId() || !Grants::active($client, $dealer)) { return Support::error('connection_required', 'Connect the staging test application before posting a test car.', 409); }
            $key = is_string($input['request_key'] ?? null) ? $input['request_key'] : '';
            if (!preg_match('/^[a-f0-9-]{36}$/D', $key)) { return Support::error('request_key_required', 'Reload this form and try again.', 400); }
            try { AutoAgoraStagingClient::workspaceRequest('/listings', 'POST', AutoAgoraStagingClient::sample('dashboard-' . $key), '', $key); }
            catch (Throwable $error) { return Support::error('import_failed', 'The test car could not be imported. Reconnect the application and try again.', 502); }
            return 'stock_created';
        }
        $id = isset($input['listing_id']) && is_scalar($input['listing_id']) ? absint($input['listing_id']) : 0;
        $mapping = self::stockConnection($id);
        if (!$mapping || $mapping['client_id'] !== $client) { return Support::error('listing_missing', 'This listing is not part of your staging stock.', 404); }
        return Support::lock('dealer:' . $dealer, static function () use ($input, $action, $id, $client, $dealer) {
            // Recheck ownership and expiry after waiting for another stock operation.
            if (!self::allowed() || !self::stockConnection($id)) { return Support::error('listing_missing', 'This listing is not available.', 404); }
            $request = new WP_REST_Request($action === 'edit_stock' ? 'PATCH' : 'POST', '/' . Rest::NS . '/listings/' . $id);
            $request->set_url_params(['id' => $id]);
            $request->set_header('Content-Type', 'application/json');
            $request->set_header('If-Match', is_string($input['version'] ?? null) ? $input['version'] : '');
            $context = ['dealer' => $dealer, 'client' => $client, 'request_key' => hash('sha256', wp_generate_uuid4())];
            if ($action === 'edit_stock') {
                if (!is_string($input['price'] ?? null) || !ctype_digit($input['price']) || (int) $input['price'] < 1) { return Support::error('invalid_price', 'Enter a positive whole-number price.'); }
                if (isset($input['description']) && !is_string($input['description'])) { return Support::error('invalid_description', 'Enter a valid description.'); }
                $body = ['price' => (int) $input['price'], 'description' => $input['description'] ?? ''];
                if (isset($input['photo_order'])) {
                    $order = is_string($input['photo_order']) ? json_decode($input['photo_order'], true) : null;
                    if (!is_array($order) || !array_is_list($order) || count($order) < 2 || count($order) > 25 || array_filter($order, static fn ($photo) => !is_int($photo) || $photo < 1)) { return Support::error('invalid_photos', 'Photo order is invalid.'); }
                    $body['photos'] = array_map(static fn ($photo) => ['id' => $photo], $order);
                }
                $request->set_body(wp_json_encode($body));
                $result = Listings::save($request, $context);
                return is_wp_error($result) ? $result : 'stock_updated';
            }
            $request->set_body(wp_json_encode(['status' => $action]));
            $result = Listings::status($request, $context);
            return is_wp_error($result) ? $result : $action;
        });
    }

    private static function data(string $tab, string $search, int $dealerFilter): array
    {
        global $wpdb;
        $dealer = get_current_user_id();
        $dealerIds = [$dealer]; $dealers = [get_userdata($dealer)];
        // Every query is scoped before LIMIT or pagination. Never load other dealers to filter later.
        $clients = $wpdb->get_results($wpdb->prepare('SELECT DISTINCT c.id,c.name,c.enabled,c.scopes FROM ' . Support::table('clients') . ' c LEFT JOIN ' . Support::table('grants') . ' g ON g.client_id=c.id AND g.dealer_id=%d WHERE c.id=%s OR g.dealer_id=%d ORDER BY c.name', $dealer, AutoAgoraStagingClient::clientId(), $dealer), ARRAY_A);
        $connections = $wpdb->get_results($wpdb->prepare('SELECT g.* FROM ' . Support::table('grants') . ' g JOIN ' . Support::table('clients') . ' c ON c.id=g.client_id WHERE g.dealer_id=%d AND g.revoked=0 AND c.enabled=1 ORDER BY g.created_at DESC', $dealer), ARRAY_A);
        $stock = new WP_Query(['post_type' => 'car', 'post_status' => ['publish', 'pending', 'draft', 'private'], 'author__in' => $dealerFilter && $dealerFilter !== $dealer ? [0] : [$dealer],
            'meta_key' => '_ag_dealer_stock_key', 'meta_compare' => 'EXISTS', 'posts_per_page' => 20, 'paged' => max(1, absint($_GET['stock_page'] ?? 1)), 'orderby' => 'ID', 'order' => 'DESC', 's' => $tab === 'stock' ? $search : '']);
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type='car' AND p.post_author=%d AND p.post_status NOT IN ('trash','auto-draft') AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_ag_dealer_stock_key')", $dealer));
        $counts = [$dealer => (object) ['count' => $total]];
        $events = $wpdb->get_results($wpdb->prepare('SELECT event,client_id,dealer_id,listing_id,created_at FROM ' . Support::table('audit') . ' WHERE dealer_id=%d ORDER BY id DESC LIMIT 50', $dealer), ARRAY_A);
        $reviewers = []; $staging_workspace = true;
        return compact('clients', 'dealers', 'dealerIds', 'connections', 'stock', 'total', 'counts', 'events', 'reviewers', 'staging_workspace');
    }
}
add_action('template_redirect', [AutoAgoraStagingWorkspace::class, 'render'], -70);
