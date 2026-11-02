<?php
/** Branded dealer sign-in and ownership-confirmed OAuth onboarding. Explicit opt-in only. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Connect
{
    private static bool $authenticating = false;
    public static function loginAttempt(): bool { return self::$authenticating; }
    public static function enabled(): bool
    {
        return defined('AUTOAGORA_DEALER_ONBOARDING_ENABLED') && AUTOAGORA_DEALER_ONBOARDING_ENABLED
            && Support::enabled() && !Review::privateMode();
    }
    public static function loginUrl(string $return): string
    {
        return add_query_arg(['autoagora_connect' => 'dealer-login', 'return_to' => self::safeReturn($return)], home_url('/'));
    }
    public static function safeReturn(string $url): string
    {
        $home = wp_parse_url(home_url('/')); $parts = wp_parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== $home['scheme'] || ($parts['host'] ?? '') !== $home['host']
            || ($parts['port'] ?? null) !== ($home['port'] ?? null) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) { return home_url('/?autoagora_connect=workspace'); }
        $path = rawurldecode($parts['path'] ?? '/');
        if (str_contains($path, '..') || preg_match('~/(?:wp-admin|wp-login\.php)(?:/|$)~i', $path)) { return home_url('/?autoagora_connect=workspace'); }
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['rest_route']) || in_array($query['autoagora_connect'] ?? '', ['dealer-login','review-login','review-logout'], true)
            || !str_starts_with($path, $home['path'] ?? '/')) { return home_url('/?autoagora_connect=workspace'); }
        return $url;
    }
    public static function bootstrap(): void
    {
        // Before the isolated staging site's generic auth gate; no broad auth bypass.
        add_action('template_redirect', [self::class, 'page'], -110);
        DealerDirectory::bootstrap();
        add_action('init', static function () {
            if (!self::enabled()) { return; }
            register_post_type('ag_dealer_onboard', ['public' => false, 'publicly_queryable' => false,
                'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'query_var' => false,
                'supports' => [], 'can_export' => false]);
        });
        add_action('admin_menu', static function () {
            if (self::enabled()) { add_management_page('Seller onboarding', 'Seller onboarding', 'manage_options', 'ag-dealer-onboarding', [self::class, 'admin']); }
        });
        add_action('admin_init', static function () {
            if (($_GET['page'] ?? '') !== 'ag-dealer-onboarding' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
                || self::field('operation') !== 'resolve') { return; }
            if (!self::enabled() || !Review::admin()) { wp_die('Access denied.', '', ['response'=>403]); }
            check_admin_referer('ag-onboarding-review');
            $id = absint(self::field('request_id'));
            if (get_post_type($id) !== 'ag_dealer_onboard') { wp_die('Request not found.', '', ['response'=>404]); }
            $updated = wp_update_post(['ID'=>$id,'post_status'=>'draft'], true);
            if (is_wp_error($updated) || !$updated) { wp_die('The request could not be marked reviewed.', '', ['response'=>503]); }
            update_post_meta($id, '_ag_onboard_reviewed_by', get_current_user_id());
            update_post_meta($id, '_ag_onboard_reviewed_at', time());
            wp_safe_redirect(admin_url('tools.php?page=ag-dealer-onboarding')); exit;
        });
    }
    private static function rate(string $subject, int $limit): bool
    {
        global $wpdb;
        $key = hash_hmac('sha256', 'connect:' . $subject . ':' . intdiv(time(), 900), wp_salt('auth'));
        $table = Support::table('limits');
        $ok = $wpdb->query($wpdb->prepare("INSERT INTO $table (bucket,hits,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1", $key, time() + 1800));
        return $ok !== false && (int) $wpdb->get_var($wpdb->prepare("SELECT hits FROM $table WHERE bucket=%s", $key)) <= $limit;
    }
    private static function field(string $key, int $limit = 120): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? mb_substr(sanitize_text_field(wp_unslash($_POST[$key])), 0, $limit) : '';
    }
    private static function saveSession(string $key, array $session): bool
    {
        // WordPress returns false for a value that is already stored unchanged.
        return set_transient($key, $session, 20 * MINUTE_IN_SECONDS) || get_transient($key) === $session;
    }
    public static function page(): void
    {
        $action = $_GET['autoagora_connect'] ?? '';
        if (!is_string($action) || !in_array($action, ['authorize','dealer-login'], true) || !self::enabled()) { return; }
        $local = wp_get_environment_type() === 'local' && in_array(wp_parse_url(home_url('/'), PHP_URL_HOST), ['127.0.0.1','localhost'], true);
        if ((!is_ssl() && !$local) || !Support::ready()) { wp_die('Dealer sign-in is temporarily unavailable.', '', ['response' => 503]); }
        if ($action === 'authorize' && is_user_logged_in()) { return; }
        $authorization = null;
        if ($action === 'authorize') {
            // Validate the application, exact callback, scopes, state and PKCE before showing names or accepting credentials.
            try { $authorization = OAuth::authorizationRequest(); }
            catch (\Throwable $error) { wp_die('This connection request is invalid or expired. Start again from your stock software.', '', ['response' => 400]); }
        }
        $return = $action === 'authorize' ? home_url('/?' . ($_SERVER['QUERY_STRING'] ?? ''))
            : self::safeReturn(isset($_GET['return_to']) && is_string($_GET['return_to']) ? wp_unslash($_GET['return_to']) : '');
        if (is_user_logged_in()) { wp_safe_redirect($return); exit; }
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive'); header('Referrer-Policy: no-referrer'); header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'none'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        $cookie = is_ssl() ? '__Host-ag_connect' : 'ag_connect';
        $browser = isset($_COOKIE[$cookie]) && is_string($_COOKIE[$cookie]) && preg_match('/^[a-f0-9]{64}$/D', $_COOKIE[$cookie]) ? $_COOKIE[$cookie] : '';
        $flow = hash('sha256', $return);
        $sessionKey = 'ag_connect_' . hash_hmac('sha256', $browser . ':' . $flow, wp_salt('nonce'));
        $session = $browser ? get_transient($sessionKey) : false;
        $session = is_array($session) ? $session : [];
        $accountType = $authorization ? OAuth::requestedAccountType() : '';
        $stage = $action === 'authorize' && $accountType === 'dealer' ? 'find' : 'signin'; $error = ''; $reference = '';
        $clientName = $authorization ? $authorization->getClient()->getName() : '';
        $name = ''; $city = ''; $matches = []; $selected = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $posted = self::field('connect_csrf', 64);
            $expected = $browser ? hash_hmac('sha256', $browser . ':' . $flow, wp_salt('nonce')) : '';
            if (!$expected || !hash_equals($expected, $posted)) { wp_die('This form expired. Reload and try again.', '', ['response' => 403]); }
            $operation = self::field('connect_action', 30);
            $name = self::field('dealership_name'); $city = self::field('dealership_city', 80);
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (!self::rate('forms:' . $ip, 45)) { status_header(429); header('Retry-After: 900'); $error = 'Too many attempts. Try again in 15 minutes.'; }
            elseif ($operation === 'find' && $authorization && $accountType === 'dealer') {
                if (mb_strlen(DealerDirectory::normalize($name)) < 3) { status_header(422); $error = 'Enter at least three letters of your dealership name.'; }
                else {
                    $session = ['name' => $name, 'city' => $city, 'candidates' => []];
                    foreach (DealerDirectory::search($name, $city) as $match) { $session['candidates'][bin2hex(random_bytes(16))] = $match; }
                    if (!self::saveSession($sessionKey, $session)) { wp_die('Matching is temporarily unavailable.', '', ['response' => 503]); }
                    $matches = $session['candidates']; $stage = $matches ? 'matches' : 'request';
                }
            } elseif ($operation === 'choose' && $authorization && $accountType === 'dealer') {
                $token = self::field('candidate', 32);
                $selected = $session['candidates'][$token] ?? null;
                if (!$selected || !Support::eligible((int) $selected['id']) || Support::accountType((int) $selected['id']) !== 'dealer') { wp_die('Choose your dealership again.', '', ['response' => 403]); }
                $session['selected'] = $selected;
                if (!self::saveSession($sessionKey, $session)) { wp_die('Sign-in is temporarily unavailable.', '', ['response' => 503]); }
                $stage = 'signin';
            } elseif ($operation === 'direct') {
                unset($session['selected']); set_transient($sessionKey, $session, 20 * MINUTE_IN_SECONDS); $stage = 'signin';
            } elseif ($operation === 'signin') {
                $stage = 'signin'; $selected = $session['selected'] ?? null;
                $login = self::field('dealer_login', 191);
                if (preg_match('/^[+\d\s()-]+$/D', $login) && function_exists('autoagora_normalize_dealership_phone')) {
                    $phone = autoagora_normalize_dealership_phone($login); if (!is_wp_error($phone)) { $login = $phone; }
                }
                $account = $selected ? (string) $selected['id'] : mb_strtolower($login);
                $ipAllowed = self::rate('login-ip:' . $ip, 20); $accountAllowed = self::rate('login-account:' . $account, 5);
                if (!$ipAllowed || !$accountAllowed) { status_header(429); header('Retry-After: 900'); $error = 'Too many attempts. Try again in 15 minutes.'; }
                else {
                    $password = isset($_POST['dealer_password']) && is_string($_POST['dealer_password']) ? wp_unslash($_POST['dealer_password']) : '';
                    self::$authenticating = true;
                    try { $user = wp_authenticate($login, strlen($password) <= 4096 ? $password : ''); }
                    finally { self::$authenticating = false; unset($password); }
                    if (!is_wp_error($user) && !user_can($user, 'manage_options') && Support::eligible((int) $user->ID)
                        && (!$authorization || Support::accountType((int) $user->ID) === $accountType)
                        && (!$selected || (int) $selected['id'] === (int) $user->ID)) {
                        wp_set_current_user($user->ID); wp_set_auth_cookie($user->ID, false, is_ssl());
                        do_action('wp_login', $user->user_login, $user);
                        delete_transient($sessionKey);
                        // Keep the signed-in dealer on the original OAuth request, with its state/verifier challenge unchanged.
                        wp_safe_redirect($return); exit;
                    }
                    status_header(401); $error = 'Sign-in failed. Check your details or request access below.';
                }
            } elseif ($operation === 'request-form' && $authorization) { $stage = 'request'; }
            elseif ($operation === 'request' && $authorization) {
                $stage = 'request'; $name = $name ?: ($session['name'] ?? ''); $city = $city ?: ($session['city'] ?? '');
                $contactName = $accountType === 'private' ? $name : self::field('contact_name'); $contact = self::field('contact', 191);
                $validContact = is_email($contact) || preg_match('/^\+?[\d\s()-]{8,25}$/D', $contact) && strlen(preg_replace('/\D/', '', $contact)) >= 8;
                if (mb_strlen($name) < 3 || $contactName === '' || !$validContact) { status_header(422); $error = $accountType === 'private' ? 'Add your full name and a valid email or phone number.' : 'Add your dealership name, your name, and a valid email or phone number.'; }
                elseif (!self::rate('request:' . $ip, 5)) { status_header(429); header('Retry-After: 900'); $error = 'Too many requests. Try again in 15 minutes.'; }
                else {
                    $key = hash_hmac('sha256', $authorization->getClient()->getIdentifier() . ':' . $accountType . ':' . DealerDirectory::normalize($name) . ':' . mb_strtolower($contact), wp_salt('auth'));
                    $saved = Support::lock('onboard:' . $key, static function () use ($key, $name, $city, $contactName, $contact, $authorization, $accountType) {
                        $existing = get_posts(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','numberposts'=>1,'fields'=>'ids','meta_key'=>'_ag_onboard_key','meta_value'=>$key]);
                        if ($existing) { return (string) get_post_meta($existing[0], '_ag_onboard_reference', true); }
                        $reference = strtoupper(bin2hex(random_bytes(6)));
                        $id = wp_insert_post(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','post_title'=>$name,'post_author'=>0,
                            'meta_input'=>['_ag_onboard_key'=>$key,'_ag_onboard_reference'=>$reference,'_ag_onboard_contact_name'=>$contactName,
                                '_ag_onboard_contact'=>$contact,'_ag_onboard_city'=>$city,'_ag_onboard_client'=>$authorization->getClient()->getIdentifier(), '_ag_onboard_account_type'=>$accountType]], true);
                        return is_wp_error($id) || !$id ? Support::error('request_failed','Your request could not be saved.',503) : $reference;
                    });
                    if (is_wp_error($saved)) { wp_die('Your request could not be saved. Please try again.', '', ['response'=>503]); }
                    $reference = $saved;
                    $stage = 'requested';
                }
            } else { wp_die('Unsupported form action.', '', ['response'=>400]); }
        }
        if (!$browser) { $browser = bin2hex(random_bytes(32)); }
        setcookie($cookie, $browser, ['expires'=>time()+1200,'path'=>'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        $csrf = hash_hmac('sha256', $browser . ':' . $flow, wp_salt('nonce'));
        $name = $name ?: ($session['name'] ?? ''); $city = $city ?: ($session['city'] ?? '');
        require __DIR__ . '/connect-template.php'; exit;
    }

    public static function admin(): void
    {
        if (!self::enabled() || !Review::admin()) { wp_die('Access denied.', '', ['response'=>403]); }
        nocache_headers();
        $matches = []; $name = ''; $city = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            check_admin_referer('ag-onboarding-review');
            $name = self::field('dealership_name'); $city = self::field('dealership_city', 80);
            $matches = DealerDirectory::search($name, $city, true);
        }
        $page = max(1, absint($_GET['paged'] ?? 1));
        $requests = new \WP_Query(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','posts_per_page'=>20,'paged'=>$page,'orderby'=>'ID','order'=>'DESC']);
        require __DIR__ . '/onboarding-admin-template.php';
    }
}
