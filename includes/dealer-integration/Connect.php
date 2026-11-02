<?php
/** Branded dealer sign-in and ownership-confirmed OAuth onboarding. Explicit opt-in only. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;
require_once __DIR__ . '/Signup.php';

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
        if ((!is_ssl() && !$local) || !Support::ready()) { wp_die('Seller sign-in is temporarily unavailable.', '', ['response'=>503]); }
        if ($action === 'authorize' && is_user_logged_in()) { return; }
        $authorization = null;
        if ($action === 'authorize') {
            try { $authorization = OAuth::authorizationRequest(); }
            catch (\Throwable $error) { wp_die('This connection request is invalid or expired. Start again from your stock software.', '', ['response'=>400]); }
        }
        $return = $authorization ? home_url('/?' . ($_SERVER['QUERY_STRING'] ?? ''))
            : self::safeReturn(isset($_GET['return_to']) && is_string($_GET['return_to']) ? wp_unslash($_GET['return_to']) : '');
        if (is_user_logged_in()) { wp_safe_redirect($return); exit; }
        nocache_headers(); header('X-Robots-Tag: noindex, nofollow, noarchive'); header('Referrer-Policy: no-referrer'); header('X-Frame-Options: DENY');
        $botAssets = Signup::ready() && !Signup::testing() ? ' https://challenges.cloudflare.com' : '';
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'$botAssets; frame-src 'self'$botAssets; connect-src 'self'$botAssets; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        $cookie = is_ssl() ? '__Host-ag_connect' : 'ag_connect';
        $browser = isset($_COOKIE[$cookie]) && is_string($_COOKIE[$cookie]) && preg_match('/^[a-f0-9]{64}$/D', $_COOKIE[$cookie]) ? $_COOKIE[$cookie] : '';
        $flow = hash('sha256', $return);
        $sessionKey = 'ag_connect_' . hash_hmac('sha256', $browser . ':' . $flow, wp_salt('nonce'));
        $saved = $browser ? get_transient($sessionKey) : false; $session = is_array($saved) ? $saved : [];
        $accountType = $authorization ? OAuth::requestedAccountType() : '';
        $stage = in_array($session['view'] ?? '', ['signup_phone','signup_verify','signup_details'], true) && $authorization && Signup::ready() ? $session['view'] : 'signin';
        $error = ''; $clientName = $authorization ? $authorization->getClient()->getName() : '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $expected = $browser ? hash_hmac('sha256', $browser . ':' . $flow, wp_salt('nonce')) : '';
            if (!$expected || !hash_equals($expected, self::field('connect_csrf', 64))) { wp_die('This form expired. Reload and try again.', '', ['response'=>403]); }
            $operation = self::field('connect_action', 30); $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (!self::rate('forms:' . $ip, 45)) { status_header(429); header('Retry-After: 900'); $error = 'Too many attempts. Try again in 15 minutes.'; }
            elseif (in_array($operation, ['direct','signin-form'], true)) {
                delete_transient($sessionKey); wp_safe_redirect($return); exit;
            } elseif ($operation === 'signin') {
                $stage = 'signin'; $login = self::field('dealer_login', 191);
                if (preg_match('/^[+\d\s().-]+$/D', $login)) { $phone = Signup::phone($login); if (!is_wp_error($phone)) { $login = $phone; } }
                $ipAllowed = self::rate('login-ip:' . $ip, 20); $accountAllowed = self::rate('login-account:' . mb_strtolower($login), 5);
                if (!$ipAllowed || !$accountAllowed) { status_header(429); header('Retry-After: 900'); $error = 'Too many attempts. Try again in 15 minutes.'; }
                else {
                    $password = isset($_POST['dealer_password']) && is_string($_POST['dealer_password']) ? wp_unslash($_POST['dealer_password']) : '';
                    self::$authenticating = true;
                    try { $user = wp_authenticate($login, strlen($password) <= 4096 ? $password : ''); }
                    finally { self::$authenticating = false; unset($password); }
                    // The URL is a signup hint only. Existing accounts retain their actual type.
                    if (!is_wp_error($user) && !user_can($user, 'manage_options') && Support::eligible((int) $user->ID)) {
                        wp_set_current_user($user->ID); wp_set_auth_cookie($user->ID, false, is_ssl()); do_action('wp_login', $user->user_login, $user);
                        delete_transient($sessionKey); wp_safe_redirect($return); exit;
                    }
                    status_header(401); $error = 'Sign-in failed. Check your details and try again.';
                }
            } elseif (in_array($operation, ['signup','send-code','verify-code','create-account'], true) && $authorization && Signup::ready()) {
                $result = Support::lock('connect-session:' . $sessionKey, static function () use ($operation,$sessionKey,$accountType) {
                    $fresh = get_transient($sessionKey); $state = is_array($fresh) ? $fresh : [];
                    if ($operation === 'signup') { $state = ['view'=>'signup_phone']; }
                    elseif ($operation === 'send-code') {
                        $phone = Signup::phone(self::field('signup_phone', 30)); $type = self::field('signup_type', 20);
                        if (is_wp_error($phone)) { return $phone; }
                        if (!in_array($type, ['dealer','private'], true) || ($type === 'private' && !Support::privateSellersEnabled())) { return Support::error('invalid_type', 'Choose an available account type.'); }
                        $state = ['view'=>'signup_phone']; if (!self::saveSession($sessionKey, $state)) { return Support::error('signup_session', 'Signup is temporarily unavailable.', 503); }
                        $challenge = Signup::send($phone, self::field('cf-turnstile-response', 2048));
                        if (is_wp_error($challenge)) { return $challenge; }
                        $state = ['view'=>'signup_verify','signup'=>['phone'=>$phone,'type'=>$type,'challenge'=>$challenge,'sent_at'=>time(),'attempts'=>0]];
                    } else {
                        $proof = $state['signup'] ?? [];
                        if (!$proof || ($operation === 'verify-code' && (int) ($proof['sent_at'] ?? 0) + 600 < time())) { return Support::error('verification_expired', 'Your verification expired. Request a new code.', 403); }
                        if ($operation === 'verify-code') {
                            if (!empty($proof['verified_until']) || (int) ($proof['attempts'] ?? 0) >= 5) { return Support::error('verification_attempts', 'Request a new code before trying again.', 429); }
                            $proof['attempts'] = (int) ($proof['attempts'] ?? 0) + 1; $state['signup'] = $proof;
                            if (!self::saveSession($sessionKey, $state)) { return Support::error('verification_failed', 'Verification is temporarily unavailable.', 503); }
                            if (!Signup::verify($proof['phone'], $proof['challenge'], self::field('signup_code', 20))) { return Support::error('invalid_code', 'The verification code is incorrect or expired.'); }
                            $state['signup']['verified_until'] = time()+600; $state['view'] = 'signup_details';
                        } else {
                            $password = isset($_POST['signup_password']) && is_string($_POST['signup_password']) ? wp_unslash($_POST['signup_password']) : '';
                            $confirm = isset($_POST['signup_confirm']) && is_string($_POST['signup_confirm']) ? wp_unslash($_POST['signup_confirm']) : '';
                            $id = Signup::create($proof,self::field('first_name',80),self::field('last_name',80),self::field('company_name'),$password,$confirm);
                            unset($password,$confirm);
                            if (is_wp_error($id)) { return $id; }
                            delete_transient($sessionKey); return $id;
                        }
                    }
                    return self::saveSession($sessionKey, $state) ? true : Support::error('signup_session', 'Signup is temporarily unavailable.', 503);
                });
                if (is_wp_error($result)) {
                    $data = $result->get_error_data(); $status = is_array($data) ? (int) ($data['status'] ?? 422) : 422;
                    status_header($status); if ($status === 429) { header('Retry-After: 600'); }
                    $error = $result->get_error_message();
                    $stage = ['signup'=>'signup_phone','send-code'=>'signup_phone','verify-code'=>'signup_verify','create-account'=>'signup_details'][$operation];
                    $fresh = get_transient($sessionKey); $session = is_array($fresh) ? $fresh : [];
                } else {
                    if (is_int($result)) {
                        if (!Support::eligible($result) || user_can($result, 'manage_options')) { wp_die('Your account was created but cannot connect yet. Contact AutoAgora.', '', ['response'=>403]); }
                        wp_set_current_user($result); wp_set_auth_cookie($result,false,is_ssl()); $user = get_userdata($result); do_action('wp_login',$user->user_login,$user);
                    }
                    wp_safe_redirect($return); exit;
                }
            } else { wp_die('Unsupported form action.', '', ['response'=>400]); }
        }
        if (!$browser) { $browser = bin2hex(random_bytes(32)); }
        setcookie($cookie,$browser,['expires'=>time()+1200,'path'=>'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        $csrf = hash_hmac('sha256',$browser.':'.$flow,wp_salt('nonce'));
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
