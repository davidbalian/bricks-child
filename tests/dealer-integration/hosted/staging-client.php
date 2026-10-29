<?php
/** Real OAuth/API demonstration client. Staging MU plugin only, never theme bootstrap. */
defined('ABSPATH') || exit;
if (wp_get_environment_type() !== 'staging' || realpath(ABSPATH) !== '/home/customer/www/staging7.autoagora.cy/public_html' || wp_parse_url(get_option('home'), PHP_URL_HOST) !== 'staging7.autoagora.cy') { return; }
final class AutoAgoraStagingClient
{
    private static string $private;
    public static function start(string $private): void
    {
        self::$private = $private;
        add_action('template_redirect', [self::class, 'render'], -80);
    }
    private static function url(string $action = 'app'): string { return home_url('/?autoagora_stage_client=' . $action); }
    private static function credentials(): array { return json_decode(file_get_contents(self::$private . '/demo-client.json'), true); }
    private static function tokenFile(): string { return self::$private . '/demo-token-' . get_current_user_id() . '.json'; }
    private static function tokens(?array $write = null): array
    {
        $file = self::tokenFile();
        $key = hash('sha256', AUTOAGORA_DEALER_ENCRYPTION_KEY, true);
        if ($write !== null) {
            $iv = random_bytes(12); $tag = '';
            $cipher = openssl_encrypt(wp_json_encode($write), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher === false) { throw new RuntimeException('Could not protect the connection.'); }
            $tmp = $file . '.' . bin2hex(random_bytes(6));
            $old = umask(0077);
            try { if (file_put_contents($tmp, base64_encode($iv . $tag . $cipher), LOCK_EX) === false || !rename($tmp, $file)) { throw new RuntimeException('Could not save the connection.'); } }
            finally { umask($old); }
            return $write;
        }
        if (!is_readable($file)) { return []; }
        $bytes = base64_decode(file_get_contents($file), true);
        if (!$bytes || strlen($bytes) < 29) { return []; }
        $raw = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16));
        return $raw ? (json_decode($raw, true) ?: []) : [];
    }
    private static function tokenRequest(array $body): array
    {
        $client = self::credentials();
        $reply = wp_remote_post(rest_url('autoagora-dealer/v1/oauth/token'), ['timeout' => 30, 'redirection' => 0, 'body' => $body + ['client_id' => $client['client_id'], 'client_secret' => $client['client_secret']]]);
        $data = is_wp_error($reply) ? [] : json_decode(wp_remote_retrieve_body($reply), true);
        if (is_wp_error($reply) || wp_remote_retrieve_response_code($reply) !== 200 || empty($data['access_token']) || empty($data['refresh_token'])) { throw new RuntimeException('Connection failed. Please connect again.'); }
        $data['expires_at'] = time() + (int) $data['expires_in'];
        return self::tokens($data);
    }
    private static function api(string $path, string $method = 'GET', ?array $body = null, string $version = '', string $key = ''): array
    {
        $tokens = self::tokens();
        if (!$tokens) { throw new RuntimeException('Connect the test application first.'); }
        if (($tokens['expires_at'] ?? 0) < time() + 30) { $tokens = self::tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']]); }
        $headers = ['Authorization' => 'Bearer ' . $tokens['access_token'], 'Content-Type' => 'application/json'];
        if ($method !== 'GET') { $headers['Idempotency-Key'] = $key ?: wp_generate_uuid4(); }
        if ($version !== '') { $headers['If-Match'] = $version; }
        $args = ['method' => $method, 'timeout' => 60, 'redirection' => 0, 'headers' => $headers];
        if ($body !== null) { $args['body'] = wp_json_encode($body); }
        $reply = wp_remote_request(rest_url('autoagora-dealer/v1' . $path), $args);
        $status = is_wp_error($reply) ? 0 : wp_remote_retrieve_response_code($reply);
        $data = is_wp_error($reply) ? [] : json_decode(wp_remote_retrieve_body($reply), true);
        if ($status < 200 || $status > 299 || !is_array($data)) {
            file_put_contents(self::$private . '/client-errors.jsonl', wp_json_encode(['path' => $path, 'url' => rest_url('autoagora-dealer/v1' . $path), 'status' => $status, 'code' => $data['code'] ?? null]) . PHP_EOL, FILE_APPEND | LOCK_EX);
            chmod(self::$private . '/client-errors.jsonl', 0600);
            if ($status === 401) { @unlink(self::tokenFile()); }
            throw new RuntimeException('API request failed (' . (int) $status . '). ' . ($status === 412 ? 'Reload and try again.' : 'Check the connection and try again.'));
        }
        return $data;
    }
    private static function form(string $action, string $label, array $hidden = []): void
    {
        echo '<form method="post" action="' . esc_url(self::url()) . '">';
        wp_nonce_field('ag-staging-client', 'stage_nonce');
        foreach (['stage_action' => $action, 'request_key' => wp_generate_uuid4()] + $hidden as $name => $value) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">'; }
        echo '<button class="btn btn-primary">' . esc_html($label) . '</button></form>';
    }
    public static function render(): void
    {
        if (!isset($_GET['autoagora_stage_client'])) { return; }
        nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Frame-Options: DENY');
        if (!\AutoAgora\Dealer\Support::eligible(get_current_user_id())) { wp_die('Sign in with the designated staging dealership account.', 'Staging connection tester', ['response' => 403]); }
        $message = ''; $items = []; $connected = false;
        try {
            if (($_GET['autoagora_stage_client'] ?? '') === 'callback') {
                $name = 'ag_stage_oauth_' . get_current_user_id();
                $pending = get_transient($name); delete_transient($name);
                $state = isset($_GET['state']) && is_string($_GET['state']) ? wp_unslash($_GET['state']) : '';
                if (!$pending || !hash_equals($pending['state'], $state) || !isset($_GET['code']) || !is_string($_GET['code'])) { throw new RuntimeException('Connection cancelled or expired. Please try again.'); }
                self::tokenRequest(['grant_type' => 'authorization_code', 'code' => wp_unslash($_GET['code']), 'code_verifier' => $pending['verifier'], 'redirect_uri' => self::credentials()['redirect_uri']]);
                wp_safe_redirect(self::url()); exit;
            }
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                if (!isset($_POST['stage_nonce']) || !is_string($_POST['stage_nonce']) || !wp_verify_nonce(wp_unslash($_POST['stage_nonce']), 'ag-staging-client')) { wp_die('Form expired. Reload and try again.', '', ['response' => 403]); }
                $action = is_string($_POST['stage_action'] ?? null) ? $_POST['stage_action'] : '';
                if ($action === 'connect') {
                    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
                    $state = bin2hex(random_bytes(24));
                    set_transient('ag_stage_oauth_' . get_current_user_id(), compact('state', 'verifier'), 300);
                    $client = self::credentials();
                    wp_safe_redirect(add_query_arg(['autoagora_connect' => 'authorize', 'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uri'], 'scope' => implode(' ', array_keys(\AutoAgora\Dealer\Support::SCOPES)), 'state' => $state, 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256'], home_url('/'))); exit;
                }
                $key = isset($_POST['request_key']) && is_string($_POST['request_key']) ? sanitize_text_field(wp_unslash($_POST['request_key'])) : '';
                if (!preg_match('/^[a-zA-Z0-9-]{20,64}$/D', $key)) { throw new RuntimeException('Reload this form and try again.'); }
                if ($action === 'create') {
                    $payload = self::sample('demo-' . $key);
                    $payload['price'] = isset($_POST['price']) && is_string($_POST['price']) && ctype_digit($_POST['price']) ? (int) $_POST['price'] : 12500;
                    self::api('/listings', 'POST', $payload, '', $key);
                } elseif ($action === 'disconnect') {
                    self::api('/connection', 'DELETE', null, '', $key); @unlink(self::tokenFile());
                } elseif (in_array($action, ['edit', 'sold', 'withdrawn'], true)) {
                    $id = absint($_POST['listing_id'] ?? 0);
                    $version = isset($_POST['version']) && is_string($_POST['version']) ? wp_unslash($_POST['version']) : '';
                    if ($action === 'edit') {
                        if (!isset($_POST['price']) || !is_string($_POST['price']) || !ctype_digit($_POST['price'])) { throw new RuntimeException('Enter a whole-number price.'); }
                        self::api('/listings/' . $id, 'PATCH', ['price' => (int) $_POST['price']], $version, $key);
                    } else { self::api('/listings/' . $id . '/status', 'POST', ['status' => $action], $version, $key); }
                } else { throw new RuntimeException('Choose an available action.'); }
                wp_safe_redirect(self::url()); exit;
            }
            if (self::tokens()) { $rows = self::api('/listings?per_page=10')['items']; foreach ($rows as $row) { $items[] = self::api('/listings/' . (int) $row['id']); } $connected = true; }
        } catch (Throwable $error) { $message = $error->getMessage(); }
        $assets = get_stylesheet_directory_uri() . '/includes/dealer-integration/assets/';
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Staging connection tester · AutoAgora</title><link rel="stylesheet" href="<?php echo esc_url($assets . 'tabler/tabler-1.7.1.min.css'); ?>"><link rel="stylesheet" href="<?php echo esc_url($assets . 'workspace.css'); ?>"><link rel="stylesheet" href="<?php echo esc_url($assets . 'button-kit/buttons.css'); ?>"><style>@font-face{font-family:Inter;font-style:normal;font-weight:100 900;font-display:swap;src:url(<?php echo esc_url(get_template_directory_uri() . '/assets/fonts/builder/Inter.woff2'); ?>) format('woff2')}body{font-family:Inter,system-ui,sans-serif}.stage-main{max-width:1100px;margin:auto;padding:24px}.stage-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.stage-actions form{margin:0}.stage-edit{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.stage-edit input{width:160px}.stage-photo{width:96px;height:64px;object-fit:cover}h1{font-size:28px}input.form-control{font-size:16px;min-height:48px}@media(max-width:600px){.stage-main{padding:16px}.stage-actions>*{width:100%}.stage-actions button{width:100%}.stage-edit input{width:100%}.stage-edit{align-items:stretch}.stage-edit label{width:100%}.stage-main table thead{display:none}.stage-main table tbody,.stage-main table tr,.stage-main table td{display:block;width:100%}.stage-main table tr{padding:16px}.stage-main table td{border:0;padding:4px 0;white-space:normal}.stage-main table td:nth-child(2)::before{content:"Status: "}.stage-main table td:nth-child(3)::before{content:"Price: "}.stage-main table .stage-actions{margin-top:8px}}</style></head><body class="agw ag-buttons"><header class="navbar agw-header"><a class="agw-brand" href="<?php echo esc_url(self::url()); ?>"><img src="<?php echo esc_url($assets . 'autoagora-logo.png'); ?>" width="160" height="34" alt="AutoAgora"></a><span class="agw-header-label">Connection tester</span><div class="agw-header-end"><span class="badge bg-yellow-lt">Staging only</span><a href="<?php echo esc_url(wp_logout_url(self::url())); ?>">Sign out</a></div></header><main class="stage-main"><h1>Staging connection tester</h1><p>Test stock appears only on staging7.autoagora.cy. Emails and payments are disabled.</p><?php if ($message) : ?><div class="alert alert-danger" role="alert"><?php echo esc_html($message); ?></div><?php endif; ?><section class="card mb-4"><div class="card-body"><div class="stage-actions"><span class="badge <?php echo $connected ? 'bg-green-lt' : 'bg-secondary-lt'; ?>"><?php echo $connected ? 'Connected' : 'Not connected'; ?></span><?php self::form('connect', $connected ? 'Reconnect application' : 'Connect application'); if ($connected) { self::form('disconnect', 'Disconnect'); } ?><a class="btn btn-primary" href="<?php echo esc_url(home_url('/cars/')); ?>">Browse staging cars</a></div></div></section><?php if ($connected) : ?><section class="card mb-4"><div class="card-header"><h2 class="card-title">Post a test car</h2></div><div class="card-body"><form class="stage-edit" method="post" action="<?php echo esc_url(self::url()); ?>"><?php wp_nonce_field('ag-staging-client','stage_nonce'); ?><input type="hidden" name="stage_action" value="create"><input type="hidden" name="request_key" value="<?php echo esc_attr(wp_generate_uuid4()); ?>"><span>BMW 3 Series · 2020 · 85,000 km</span><label>Price (€)<input class="form-control" name="price" type="number" min="1" max="10000000" required value="12500"></label><button class="btn btn-primary">Post test car</button></form></div></section><?php endif; ?><section class="card"><div class="card-header"><h2 class="card-title">Your test stock</h2></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Car</th><th>Status</th><th>Price</th><th>Actions</th></tr></thead><tbody><?php foreach ($items as $item) : ?><tr><td><strong><?php echo esc_html(trim($item['fields']['make'] . ' ' . $item['fields']['model'] . ' ' . $item['fields']['year'])); ?></strong><br><small><?php echo esc_html('Record #' . $item['id']); ?></small></td><td><?php echo esc_html($item['status']); ?></td><td>€<?php echo number_format_i18n((int) $item['fields']['price']); ?></td><td><div class="stage-actions"><?php if ($item['publicly_available']) : ?><a class="btn btn-primary" href="<?php echo esc_url($item['url']); ?>">View car</a><?php endif; self::form('sold','Mark sold',['listing_id'=>$item['id'],'version'=>$item['version']]); self::form('withdrawn','Withdraw',['listing_id'=>$item['id'],'version'=>$item['version']]); ?><form class="stage-edit" method="post" action="<?php echo esc_url(self::url()); ?>"><?php wp_nonce_field('ag-staging-client','stage_nonce'); foreach (['stage_action'=>'edit','request_key'=>wp_generate_uuid4(),'listing_id'=>$item['id'],'version'=>$item['version']] as $n=>$v) { echo '<input type="hidden" name="'.esc_attr($n).'" value="'.esc_attr($v).'">'; } ?><label class="visually-hidden" for="price-<?php echo (int)$item['id']; ?>">New price</label><input id="price-<?php echo (int)$item['id']; ?>" class="form-control" type="number" name="price" required min="1" value="<?php echo (int)$item['fields']['price']; ?>"><button class="btn btn-primary">Save price</button></form></div></td></tr><?php endforeach; if (!$items) { echo '<tr><td colspan="4">No test stock yet.</td></tr>'; } ?></tbody></table></div></section></main></body></html><?php exit;
    }
    public static function sample(string $external): array
    {
        return ['external_stock_id' => $external, 'make' => 'BMW', 'model' => '3 Series', 'year' => 2020, 'mileage' => 85000, 'price' => 12500, 'availability' => 'In Stock', 'engine_capacity' => '2.0', 'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'body_type' => 'Saloon', 'exterior_color' => 'Black', 'description' => 'STAGING TEST VEHICLE. Synthetic integration test; not offered for sale.', 'location' => ['address' => 'STAGING TEST SHOWROOM', 'city' => 'Nicosia', 'district' => 'Nicosia', 'latitude' => 35.1727, 'longitude' => 33.3639], 'photos' => [['url' => home_url('/staging-test-assets/car-front.png')], ['url' => home_url('/staging-test-assets/car-rear.png')]]];
    }
}
AutoAgoraStagingClient::start($agStagePrivate);
