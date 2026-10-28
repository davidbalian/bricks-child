<?php
/** Administrator-only UI over the integration's real persistence and listing services. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Workspace
{
    public static function bootstrap(): void
    {
        add_action('template_redirect', [self::class, 'render'], -30);
        add_action('admin_menu', static function () {
            if (!Review::admin()) { return; }
            $hook = add_management_page('Dealer integrations', 'Dealer integrations', 'manage_options', 'ag-dealer-integrations', '__return_null');
            add_action('load-' . $hook, static function () {
                if (isset($_GET['activated'])) {
                    if (!Review::admin()) { wp_die('Not found.', '', ['response' => 404]); }
                    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Dealer integration activated</title><body><h1>Administrator-only workspace enabled</h1><p>Public onboarding is disabled. No local test accounts, connections, listings or photos were imported.</p><p><a href="' . esc_url(self::url()) . '">Open dealer integrations</a></p></body></html>'; exit;
                }
                wp_safe_redirect(self::url()); exit;
            });
        });
    }
    public static function tabs(): array
    {
        $tabs = ['connections' => ['link', 'Connections'], 'stock' => ['car-side', 'Dealer stock'], 'activity' => ['clock-rotate-left', 'Activity']];
        if (Review::admin()) { $tabs['access'] = ['shield-check', 'Reviewer access']; }
        return $tabs;
    }
    public static function url(array $args = []): string
    {
        return add_query_arg(['autoagora_connect' => 'workspace'] + $args, home_url('/'));
    }
    public static function icon(string $name): string
    {
        $icons = ['link' => 'link', 'car-side' => 'car', 'building' => 'building-store', 'shield-check' => 'shield-check', 'arrow-right' => 'arrow-right', 'plus' => 'plus', 'gear' => 'settings', 'list-check' => 'list-check', 'arrow-up' => 'arrow-up', 'arrow-down' => 'arrow-down', 'xmark' => 'x', 'circle-check' => 'circle-check', 'circle-info' => 'info-circle', 'clock-rotate-left' => 'history'];
        if (!isset($icons[$name])) { return ''; }
        $file = __DIR__ . '/assets/tabler/icons/' . $icons[$name] . '.svg';
        if (!is_readable($file)) { return ''; }
        // Only bundled, allowlisted SVGs are inlined; currentColor follows the component.
        $svg = file_get_contents($file);
        $svg = str_replace('<svg ', '<svg aria-hidden="true" focusable="false" ', $svg);
        return preg_replace('/class="([^"]*)"/', 'class="agw-icon $1"', $svg, 1);
    }
    public static function nonce(): void { wp_nonce_field('ag-workspace', 'workspace_nonce'); }
    public static function clientName(string $id, array $clients): string
    {
        foreach ($clients as $client) { if ($client['id'] === $id) { return $client['name']; } }
        return 'Unavailable application';
    }
    public static function render(): void
    {
        if (($_GET['autoagora_connect'] ?? '') !== 'workspace') { return; }
        if (!Review::canWorkspace()) { status_header(404); echo 'Not found.'; exit; }
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'");
        $notice = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!isset($_POST['workspace_nonce']) || !is_string($_POST['workspace_nonce']) || !wp_verify_nonce(wp_unslash($_POST['workspace_nonce']), 'ag-workspace')) {
                status_header(403); wp_die('The form expired. Reload this workspace and try again.', 'Dealer integrations', ['response' => 403]);
            }
            try {
                $result = self::perform(wp_unslash($_POST));
            } catch (\Throwable $error) {
                $result = Support::error('review_action_failed', 'This action could not be completed. Your private review data has been retained.', 500);
            }
            if (is_wp_error($result)) { $notice = ['error', $result->get_error_message()]; }
            else {
                wp_safe_redirect(self::url(['tab' => in_array($_POST['return_tab'] ?? '', array_keys(self::tabs()), true) ? $_POST['return_tab'] : 'connections', 'saved' => $result]));
                exit;
            }
        }
        $tab = isset($_GET['tab']) && is_string($_GET['tab']) && in_array($_GET['tab'], array_keys(self::tabs()), true) ? $_GET['tab'] : 'connections';
        $search = isset($_GET['q']) && is_string($_GET['q']) ? substr(sanitize_text_field(wp_unslash($_GET['q'])), 0, 80) : '';
        $dealerFilter = isset($_GET['dealer']) && is_scalar($_GET['dealer']) ? absint($_GET['dealer']) : 0;
        try {
            if (!Support::ready()) { throw new \RuntimeException('Not configured'); }
            $state = Review::read(static fn () => self::data($tab, $search, $dealerFilter));
        } catch (\Throwable $error) {
            $notice = ['error', 'The integration is not configured for changes. No public content has been affected.'];
            $state = ['clients' => [], 'dealers' => [], 'dealerIds' => [], 'connections' => [], 'stock' => (object) ['posts' => [], 'found_posts' => 0, 'max_num_pages' => 0], 'total' => 0, 'counts' => [], 'events' => []];
        }
        $saved = isset($_GET['saved']) && is_string($_GET['saved']) ? $_GET['saved'] : '';
        $messages = ['dealer_created' => 'Test dealer created. Its profile is private.', 'connected' => 'Test connection authorized.', 'disconnected' => 'Application access disconnected.', 'reviewer_revoked' => 'Reviewer access revoked. Existing sessions have been signed out.', 'stock_created' => 'Sample stock added for private review.', 'stock_updated' => 'Stock changes saved for review.', 'sold' => 'Stock marked sold.', 'withdrawn' => 'Stock withdrawn. Its details and photos are retained.'];
        if (!$notice && isset($messages[$saved])) { $notice = ['success', $messages[$saved]]; }
        require __DIR__ . '/workspace-template.php';
        exit;
    }
    private static function perform(array $input)
    {
        if (!Review::canWorkspace() || !Review::privateMode() || !Support::ready()) { return Support::error('review_unavailable', 'This private workspace is not ready for changes.', 503); }
        $action = $input['workspace_action'] ?? '';
        if (!is_string($action)) { return Support::error('invalid_action', 'Choose an available action.'); }
        return Review::sandbox(static function () use ($input, $action) {
            if ($action === 'add_dealer') {
                $name = isset($input['dealer_name']) && is_string($input['dealer_name']) ? sanitize_text_field($input['dealer_name']) : '';
                if (strlen($name) < 2 || strlen($name) > 100) { return Support::error('invalid_name', 'Enter a dealer name between 2 and 100 characters.'); }
                $login = 'ag-review-' . strtolower(wp_generate_password(12, false));
                $id = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(64, true, true), 'user_email' => $login . '@example.invalid', 'display_name' => $name, 'role' => 'dealership']);
                if (is_wp_error($id)) { return Support::error('dealer_failed', 'The test dealer could not be created.', 500); }
                update_user_meta($id, Review::USER_META, 1);
                $profile = wp_insert_post(['post_type' => 'dealer_profile', 'post_status' => 'draft', 'post_title' => $name, 'post_author' => $id,
                    'meta_input' => [Review::META => 1, 'dealer_claimed_user_id' => $id, 'dealer_claim_status' => 'claimed']], true);
                if (is_wp_error($profile)) { return Support::error('profile_failed', 'The test account was created, but its private profile needs attention.', 500); }
                Support::audit('review_dealer_created', '', $id, $profile);
                return 'dealer_created';
            }
            $dealer = isset($input['dealer_id']) && is_scalar($input['dealer_id']) ? absint($input['dealer_id']) : 0;
            if (!Review::testDealer($dealer)) { return Support::error('test_dealer_required', 'Choose a test dealer from this workspace.', 403); }
            $client = isset($input['client_id']) && is_string($input['client_id']) ? sanitize_key($input['client_id']) : '';
            if (!($clientRow = Clients::row($client)) || !(int) $clientRow['enabled']) { return Support::error('application_missing', 'Choose an available partner application.'); }
            if (in_array($action, ['connect', 'disconnect'], true)) {
                return Support::lock('oauth:' . $client, static function () use ($action, $client, $dealer) {
                    if ($action === 'connect') { Grants::create($client, $dealer, array_keys(Support::SCOPES)); return 'connected'; }
                    Grants::revoke($client, $dealer); return 'disconnected';
                });
            }
            if (!Grants::active($client, $dealer)) { return Support::error('connection_required', 'Connect this test dealer before managing its stock.', 409); }
            $context = ['dealer' => $dealer, 'client' => $client, 'request_key' => hash('sha256', wp_generate_uuid4())];
            return Support::lock('dealer:' . $dealer, static function () use ($action, $input, $context) {
                if ($action === 'add_stock') {
                    if (wp_get_environment_type() !== 'local' || !in_array(wp_parse_url(home_url('/'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
                        return Support::error('local_samples_only', 'Sample image fixtures are available only in the local copy.', 403);
                    }
                    $payload = ['external_stock_id' => 'review-' . wp_generate_uuid4(), 'make' => 'BMW', 'model' => '3 Series', 'year' => 2020, 'mileage' => 85000, 'price' => 12500,
                        'availability' => 'In Stock', 'engine_capacity' => '2.0', 'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'body_type' => 'Saloon', 'exterior_color' => 'Black',
                        'location' => ['address' => 'Private test showroom', 'city' => 'Nicosia', 'district' => 'Nicosia', 'latitude' => 35.1727, 'longitude' => 33.3639],
                        'description' => 'Synthetic stock for private administrator review.', 'photos' => [['url' => 'https://images.example.com/fixture-one.png'], ['url' => 'https://images.example.com/fixture-two.png']]];
                    $request = new \WP_REST_Request('POST', '/' . Rest::NS . '/listings');
                    $request->set_header('Content-Type', 'application/json');
                    $request->set_body(wp_json_encode($payload));
                    $result = Listings::save($request, $context);
                    return is_wp_error($result) ? $result : 'stock_created';
                }
                $id = isset($input['listing_id']) && is_scalar($input['listing_id']) ? absint($input['listing_id']) : 0;
                if (!$id || !Review::hidden($id) || (int) get_post_field('post_author', $id) !== $context['dealer']) { return Support::error('listing_missing', 'This stock is not part of the selected test account.', 404); }
                $request = new \WP_REST_Request($action === 'edit_stock' ? 'PATCH' : 'POST', '/' . Rest::NS . '/listings/' . $id);
                $request->set_url_params(['id' => $id]);
                $request->set_header('Content-Type', 'application/json');
                $request->set_header('If-Match', isset($input['version']) && is_string($input['version']) ? $input['version'] : '');
                if ($action === 'edit_stock') {
                    if (!isset($input['price']) || !is_string($input['price']) || !ctype_digit($input['price']) || (int) $input['price'] < 1) { return Support::error('invalid_price', 'Enter the price as a positive whole number.'); }
                    $payload = ['price' => (int) $input['price'], 'description' => isset($input['description']) && is_string($input['description']) ? $input['description'] : ''];
                    if (!empty($input['photo_order']) && is_string($input['photo_order'])) {
                        $order = json_decode($input['photo_order'], true);
                        if (!is_array($order) || count($order) < 2 || count($order) > 25 || array_filter($order, static fn ($id) => !is_int($id) || $id < 1)) { return Support::error('invalid_photos', 'Photo order is invalid.'); }
                        $payload['photos'] = array_map(static fn ($id) => ['id' => $id], $order);
                    }
                    $request->set_body(wp_json_encode($payload));
                    $result = Listings::save($request, $context);
                    return is_wp_error($result) ? $result : 'stock_updated';
                }
                if (!in_array($action, ['sold', 'withdrawn'], true)) { return Support::error('invalid_action', 'Choose an available stock action.'); }
                $request->set_body(wp_json_encode(['status' => $action]));
                $result = Listings::status($request, $context);
                return is_wp_error($result) ? $result : $action;
            });
        });
    }
    public static function stockConnection(int $id, array $connections): ?array
    {
        global $wpdb;
        $client = $wpdb->get_var($wpdb->prepare('SELECT client_id FROM ' . Support::table('stocks') . ' WHERE listing_id=%d LIMIT 1', $id));
        foreach ($connections as $connection) {
            if ($connection['client_id'] === $client && (int) $connection['dealer_id'] === (int) get_post_field('post_author', $id)) { return $connection; }
        }
        return null;
    }
    private static function data(string $tab, string $search, int $dealerFilter): array
    {
        global $wpdb;
        $clients = $wpdb->get_results('SELECT id, name, enabled, scopes FROM ' . Support::table('clients') . ' ORDER BY name', ARRAY_A);
        $users = new \WP_User_Query(['role' => 'dealership', 'meta_key' => Review::USER_META, 'meta_value' => '1', 'number' => 50, 'orderby' => 'display_name', 'order' => 'ASC']);
        $dealers = $users->get_results();
        $dealerIds = array_map(static fn ($u) => (int) $u->ID, $dealers);
        $connections = $wpdb->get_results('SELECT * FROM ' . Support::table('grants') . ' WHERE revoked = 0 ORDER BY created_at DESC', ARRAY_A);
        $connections = array_values(array_filter($connections, static fn ($c) => in_array((int) $c['dealer_id'], $dealerIds, true)));
        $query = ['post_type' => 'car', 'post_status' => ['pending', 'draft', 'private', 'publish'], 'meta_key' => Review::META, 'meta_value' => '1',
            'posts_per_page' => 20, 'paged' => max(1, absint($_GET['stock_page'] ?? 1)), 'orderby' => 'ID', 'order' => 'DESC', 's' => $tab === 'stock' ? $search : '', 'author__in' => $dealerFilter ? [in_array($dealerFilter, $dealerIds, true) ? $dealerFilter : 0] : ($dealerIds ?: [0])];
        $stock = new \WP_Query($query);
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type='car' AND p.post_status NOT IN ('trash','auto-draft') AND m.meta_key=%s AND m.meta_value='1'", Review::META));
        $counts = $wpdb->get_results($wpdb->prepare("SELECT p.post_author, COUNT(DISTINCT p.ID) AS count FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type='car' AND p.post_status NOT IN ('trash','auto-draft') AND m.meta_key=%s AND m.meta_value='1' GROUP BY p.post_author", Review::META), OBJECT_K);
        $events = $wpdb->get_results('SELECT event,client_id,dealer_id,listing_id,created_at FROM ' . Support::table('audit') . ' ORDER BY id DESC LIMIT 50', ARRAY_A);
        $events = array_values(array_filter($events, static fn ($e) => in_array((int) $e['dealer_id'], $dealerIds, true)));
        $reviewers = Review::admin() && $tab === 'access' ? get_users(['role' => Reviewer::ROLE, 'number' => 100, 'orderby' => 'ID', 'order' => 'DESC']) : [];
        return compact('clients', 'dealers', 'dealerIds', 'connections', 'stock', 'total', 'counts', 'events', 'reviewers');
    }
}
