<?php
/** Password-authenticated access to synthetic review data, with no WordPress backend rights. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Reviewer
{
    public const ROLE = 'ag_dealer_reviewer';
    public const CAP = 'ag_dealer_review';
    public const ENABLED = '_ag_review_enabled';
    public const EXPIRES = '_ag_review_expires';
    private static bool $authenticating = false;

    public static function roleUser($user): bool
    {
        return $user instanceof \WP_User && count($user->roles) === 1 && in_array(self::ROLE, (array) $user->roles, true);
    }
    public static function allowed(?int $id = null): bool
    {
        $user = $id === null ? wp_get_current_user() : get_userdata($id);
        return self::roleUser($user) && count($user->roles) === 1 && !empty($user->allcaps[self::CAP])
            && get_user_meta($user->ID, self::ENABLED, true) === '1' && (int) get_user_meta($user->ID, self::EXPIRES, true) > time();
    }
    public static function loginAttempt(): bool { return self::$authenticating; }
    public static function url(string $action = 'review-login'): string
    {
        return add_query_arg('autoagora_connect', $action, home_url('/'));
    }
    public static function bootstrap(): void
    {
        add_action('init', static function () {
            if (Support::enabled() && !get_role(self::ROLE)) { add_role(self::ROLE, 'Integration reviewer', [self::CAP => true]); }
            if (!self::roleUser(wp_get_current_user())) { return; }
            // Block backend, AJAX, admin-post, native login and unrelated frontend mutation flows.
            if (is_admin()) { status_header(404); nocache_headers(); exit('Not found.'); }
            $action = $_GET['autoagora_connect'] ?? '';
            $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $media = (wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/') . 'ag-review-media/';
            if (isset($_GET['rest_route']) || (is_string($path) && str_contains($path, '/' . rest_get_url_prefix() . '/'))) { status_header(403); nocache_headers(); exit('Access denied.'); }
            if ($action === 'workspace' && !class_exists(Workspace::class)) { wp_die('Private review is unavailable.', '', ['response' => 503]); }
            if (!is_string($action) || !in_array($action, ['workspace', 'review-login', 'review-logout'], true)) {
                if (is_string($path) && str_starts_with($path, $media)) { return; }
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { status_header(403); exit('Access denied.'); }
                wp_safe_redirect(self::allowed() ? add_query_arg('autoagora_connect', 'workspace', home_url('/')) : self::url()); exit;
            }
        }, -50);
        add_filter('authenticate', static function ($user) {
            if (self::roleUser($user) && (!self::$authenticating || !self::allowed((int) $user->ID))) {
                return new \WP_Error('review_access_denied', 'Sign-in failed.');
            }
            if (self::$authenticating && $user instanceof \WP_User && !self::allowed((int) $user->ID)) { return new \WP_Error('review_access_denied', 'Sign-in failed.'); }
            return $user;
        }, PHP_INT_MAX);
        add_filter('auth_cookie_expiration', static fn ($duration, $id) => self::roleUser(get_userdata($id)) ? HOUR_IN_SECONDS : $duration, 99, 2);
        add_filter('wp_is_application_passwords_available_for_user', static fn ($available, $user) => self::roleUser($user) ? false : $available, 99, 2);
        add_filter('rest_pre_dispatch', static fn ($result) => self::roleUser(wp_get_current_user()) ? Support::error('review_rest_denied', 'Access denied.', 403) : $result, PHP_INT_MAX);
        add_action('template_redirect', [self::class, 'page'], -45);
        add_action('template_redirect', [self::class, 'manage'], -35);
    }
    private static function headers(): void
    {
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
    }
    private static function secureTransport(): bool
    {
        return is_ssl() || (wp_get_environment_type() === 'local' && in_array(wp_parse_url(home_url('/'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true));
    }
    private static function rate(string $key, int $limit): bool
    {
        global $wpdb;
        $bucket = hash_hmac('sha256', 'review-login:' . $key . ':' . intdiv(time(), 900), wp_salt('auth'));
        $table = Support::table('limits');
        $ok = $wpdb->query($wpdb->prepare("INSERT INTO $table (bucket,hits,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1", $bucket, time() + 1800));
        return $ok !== false && (int) $wpdb->get_var($wpdb->prepare("SELECT hits FROM $table WHERE bucket=%s", $bucket)) <= $limit;
    }
    public static function page(): void
    {
        $action = $_GET['autoagora_connect'] ?? '';
        if (!is_string($action) || !in_array($action, ['review-login', 'review-logout'], true)) { return; }
        self::headers();
        if ($action === 'review-logout') {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['review_nonce']) || !is_string($_POST['review_nonce']) || !wp_verify_nonce(wp_unslash($_POST['review_nonce']), 'ag-review-logout')) { wp_die('Access denied.', '', ['response' => 403]); }
            wp_logout(); wp_safe_redirect(self::url()); exit;
        }
        if ((Review::admin() || self::allowed()) && class_exists(Workspace::class)) { wp_safe_redirect(Workspace::url()); exit; }
        if (is_user_logged_in() && !self::roleUser(wp_get_current_user())) { wp_die('Use a separate browser session for reviewer access.', '', ['response' => 403]); }
        if (!self::secureTransport() || !Support::ready()) { wp_die('Private review sign-in is unavailable.', '', ['response' => 503]); }
        $error = '';
        $cookieName = is_ssl() ? '__Host-ag_review_login_csrf' : 'ag_review_login_csrf';
        $csrf = isset($_COOKIE[$cookieName]) && is_string($_COOKIE[$cookieName]) && preg_match('/^[a-f0-9]{64}$/D', $_COOKIE[$cookieName]) ? $_COOKIE[$cookieName] : '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $posted = isset($_POST['review_csrf']) && is_string($_POST['review_csrf']) ? wp_unslash($_POST['review_csrf']) : '';
            if (!$csrf || !hash_equals($csrf, $posted)) { status_header(403); $error = 'This sign-in form expired. Reload and try again.'; }
            else {
                $login = isset($_POST['review_username']) && is_string($_POST['review_username']) ? substr(trim(wp_unslash($_POST['review_username'])), 0, 64) : '';
                $password = isset($_POST['review_password']) && is_string($_POST['review_password']) ? wp_unslash($_POST['review_password']) : '';
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; // Do not trust a caller-supplied forwarded address.
                $ipAllowed = self::rate('ip:' . $ip, 20);
                $accountAllowed = self::rate('account:' . strtolower($login), 5);
                if (!$ipAllowed || !$accountAllowed) { status_header(429); header('Retry-After: 900'); $error = 'Too many attempts. Try again in 15 minutes.'; }
                else {
                    if (self::roleUser(wp_get_current_user())) { wp_logout(); }
                    self::$authenticating = true;
                    try { $user = wp_signon(['user_login' => $login, 'user_password' => strlen($password) <= 4096 ? $password : '', 'remember' => false], is_ssl()); }
                    finally { self::$authenticating = false; }
                    unset($password);
                    if (!is_wp_error($user) && self::allowed((int) $user->ID)) {
                        wp_set_current_user($user->ID);
                        update_user_meta($user->ID, '_ag_review_last_login', time());
                        setcookie($cookieName, '', ['expires' => time() - HOUR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Strict']);
                        wp_safe_redirect(Workspace::url()); exit;
                    }
                    status_header(401); $error = 'Sign-in failed. Check your username and password, or ask David to renew your access.';
                }
            }
        }
        if (!$csrf) { $csrf = bin2hex(random_bytes(32)); }
        setcookie($cookieName, $csrf, ['expires' => time() + 900, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Strict']);
        $created = null;
        require __DIR__ . '/reviewer-template.php'; exit;
    }
    public static function manage(): void
    {
        if (($_GET['autoagora_connect'] ?? '') !== 'workspace' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
        $action = $_POST['workspace_action'] ?? '';
        if (!is_string($action) || !in_array($action, ['create_reviewer', 'revoke_reviewer', 'renew_reviewer'], true)) { return; }
        self::headers();
        if (!Review::admin() || !Review::privateMode() || !Support::ready()) { wp_die('Access denied.', '', ['response' => 403]); }
        if (!isset($_POST['workspace_nonce']) || !is_string($_POST['workspace_nonce']) || !wp_verify_nonce(wp_unslash($_POST['workspace_nonce']), 'ag-workspace')) { wp_die('The form expired.', '', ['response' => 403]); }
        if ($action === 'revoke_reviewer') {
            $id = isset($_POST['reviewer_id']) && is_scalar($_POST['reviewer_id']) ? absint($_POST['reviewer_id']) : 0;
            if (!self::roleUser(get_userdata($id))) { wp_die('Reviewer not found.', '', ['response' => 404]); }
            update_user_meta($id, self::ENABLED, 0);
            \WP_Session_Tokens::get_instance($id)->destroy_all();
            wp_safe_redirect(Workspace::url(['tab' => 'access', 'saved' => 'reviewer_revoked'])); exit;
        }
        $renew = $action === 'renew_reviewer';
        $existing = isset($_POST['reviewer_id']) && is_scalar($_POST['reviewer_id']) ? absint($_POST['reviewer_id']) : 0;
        if ($renew && !self::roleUser(get_userdata($existing))) { wp_die('Reviewer not found.', '', ['response' => 404]); }
        $login = isset($_POST['reviewer_username']) && is_string($_POST['reviewer_username']) ? strtolower(trim(wp_unslash($_POST['reviewer_username']))) : '';
        $days = isset($_POST['reviewer_days']) && is_scalar($_POST['reviewer_days']) ? absint($_POST['reviewer_days']) : 30;
        if ($renew) { $login = get_userdata($existing)->user_login; }
        if ($days < 1 || $days > 90 || (!$renew && (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,31}$/D', $login) || username_exists($login)))) { wp_die('Choose an unused username (3–32 letters/numbers, dot, underscore or hyphen) and an expiry of 1–90 days.', '', ['response' => 422, 'back_link' => true]); }
        $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $id = Review::sandbox(static function () use ($login, $password, $days, $renew, $existing) {
            if ($renew) { wp_set_password($password, $existing); \WP_Session_Tokens::get_instance($existing)->destroy_all(); $id = $existing; }
            else { $id = wp_insert_user(['user_login' => $login, 'user_pass' => $password, 'display_name' => ucfirst($login), 'user_email' => '', 'role' => self::ROLE]); }
            if (!is_wp_error($id)) {
                update_user_meta($id, Review::USER_META, 1);
                update_user_meta($id, self::ENABLED, 1);
                update_user_meta($id, self::EXPIRES, time() + $days * DAY_IN_SECONDS);
            }
            return $id;
        });
        if (is_wp_error($id)) { wp_die('The reviewer could not be created.', '', ['response' => 500]); }
        $created = ['username' => $login, 'password' => $password, 'expires' => (int) get_user_meta($id, self::EXPIRES, true)];
        $error = '';
        require __DIR__ . '/reviewer-template.php'; exit;
    }
}
