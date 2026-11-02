<?php
/** Connection-only verified signup. Never changes an existing account or its role. */
namespace AutoAgora\Dealer;
defined('ABSPATH') || exit;

final class Signup
{
    public static function enabled(): bool
    {
        return Connect::enabled() && defined('AUTOAGORA_CONNECT_SIGNUP_ENABLED') && AUTOAGORA_CONNECT_SIGNUP_ENABLED;
    }
    public static function testing(): bool
    {
        return in_array(wp_get_environment_type(), ['local', 'staging'], true);
    }
    private static function testPhones(): array
    {
        return self::testing() && defined('AUTOAGORA_CONNECT_TEST_PHONES') && is_array(AUTOAGORA_CONNECT_TEST_PHONES)
            && defined('AUTOAGORA_CONNECT_TEST_EXPIRES') && time() < AUTOAGORA_CONNECT_TEST_EXPIRES ? AUTOAGORA_CONNECT_TEST_PHONES : [];
    }
    public static function ready(): bool
    {
        if (!self::enabled()) { return false; }
        if (self::testing()) { return (bool) self::testPhones(); }
        if (wp_get_environment_type() !== 'production') { return false; }
        return defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY && defined('TURNSTILE_SECRET_KEY') && TURNSTILE_SECRET_KEY
            && defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID && defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN
            && defined('TWILIO_VERIFY_SID') && TWILIO_VERIFY_SID && class_exists('Twilio\\Rest\\Client');
    }
    public static function phone(string $raw)
    {
        // No stripping arbitrary text into a different person's number.
        if (!preg_match('/^[+\d\s().-]{8,30}$/D', $raw)) { return Support::error('invalid_phone', 'Enter a Cyprus phone number, including +357.'); }
        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($digits, '00357')) { $digits = substr($digits, 2); }
        if (strlen($digits) === 8) { $digits = '357' . $digits; }
        return preg_match('/^357\d{8}$/D', $digits) ? '+' . $digits : Support::error('invalid_phone', 'Enter a Cyprus phone number, including +357.');
    }
    private static function rate(string $subject, int $limit, int $window): bool
    {
        global $wpdb;
        $bucket = hash_hmac('sha256', 'signup:' . $subject . ':' . intdiv(time(), $window), wp_salt('auth'));
        $table = Support::table('limits');
        $ok = $wpdb->query($wpdb->prepare("INSERT INTO $table (bucket,hits,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1", $bucket, time()+$window*2));
        return $ok !== false && (int) $wpdb->get_var($wpdb->prepare("SELECT hits FROM $table WHERE bucket=%s", $bucket)) <= $limit;
    }
    public static function send(string $phone, string $turnstile)
    {
        if (!self::ready()) { return Support::error('signup_unavailable', 'Account signup is temporarily unavailable.', 503); }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipAllowed = self::rate('send-ip:' . $ip, 5, 600);
        if (!$ipAllowed) { return Support::error('signup_rate', 'Too many verification requests. Please try again later.', 429); }
        if (self::testing()) {
            if (!isset(self::testPhones()[$phone])) { return Support::error('staging_phone', 'Use an approved test phone number supplied by AutoAgora. No SMS is sent on this test site.'); }
            if (!self::rate('send-phone:' . $phone, 3, 3600)) { return Support::error('signup_rate', 'Too many verification requests. Please try again later.', 429); }
            return 'test-' . bin2hex(random_bytes(16));
        }
        if ($turnstile === '' || strlen($turnstile) > 2048) { return Support::error('bot_verification', 'Complete the security check and try again.'); }
        try {
            $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['timeout'=>10, 'body'=>[
                'secret'=>TURNSTILE_SECRET_KEY, 'response'=>$turnstile, 'remoteip'=>$ip]]);
        } catch (\Throwable $error) { return Support::error('bot_verification', 'The security check is temporarily unavailable.', 503); }
        $data = is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 ? [] : json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['success']) || ($data['action'] ?? '') !== 'connect_signup'
            || strtolower((string) ($data['hostname'] ?? '')) !== strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
            return Support::error('bot_verification', 'The security check expired. Please try again.');
        }
        if (!self::rate('send-phone:' . $phone, 3, 3600)) { return Support::error('signup_rate', 'Too many verification requests. Please try again later.', 429); }
        try {
            $client = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
            $result = $client->verify->v2->services(TWILIO_VERIFY_SID)->verifications->create($phone, 'sms');
            return $result->status === 'pending' ? (string) $result->sid : Support::error('verification_send', 'The code could not be sent. Please try again.', 503);
        } catch (\Throwable $error) { return Support::error('verification_send', 'The code could not be sent. Please try again.', 503); }
    }
    public static function verify(string $phone, string $challenge, string $code): bool
    {
        if (!self::ready() || !preg_match('/^\d{6}$/D', $code)) { return false; }
        if (self::testing()) {
            $hash = self::testPhones()[$phone] ?? '';
            return str_starts_with($challenge, 'test-') && is_string($hash) && strlen($hash) === 64 && hash_equals($hash, hash('sha256', $code));
        }
        try {
            $client = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
            $result = $client->verify->v2->services(TWILIO_VERIFY_SID)->verificationChecks->create(['verificationSid'=>$challenge,'code'=>$code]);
            return $result->status === 'approved';
        } catch (\Throwable $error) { return false; }
    }
    public static function create(array $proof, string $first, string $last, string $company, string $password, string $confirm)
    {
        if (!self::ready() || empty($proof['verified_until']) || (int) $proof['verified_until'] < time()
            || !in_array($proof['type'] ?? '', ['dealer','private'], true) || is_wp_error(self::phone((string) ($proof['phone'] ?? '')))) {
            return Support::error('phone_not_verified', 'Verify your phone number before creating an account.', 403);
        }
        $type = $proof['type']; $phone = $proof['phone'];
        if ($type === 'private' && !Support::privateSellersEnabled()) { return Support::error('private_unavailable', 'Private seller signup is unavailable.', 403); }
        if (self::testing() && !isset(self::testPhones()[$phone])) { return Support::error('staging_phone', 'Use an approved staging test number.', 403); }
        if ($first === '' || $last === '' || ($type === 'dealer' && mb_strlen($company) < 2)) { return Support::error('missing_name', 'Enter your first and last name, and your dealership name if applicable.'); }
        if (strlen($password) < 12 || strlen($password) > 128 || $password !== $confirm) { return Support::error('invalid_password', 'Use a password of 12–128 characters and enter it identically in both fields.'); }
        $role = $type === 'dealer' ? 'dealership' : 'client';
        if (!get_role($role) || get_role($role)->has_cap('manage_options') || !get_role($role)->has_cap('upload_files') || !get_role($role)->has_cap('edit_posts')) { return Support::error('role_unavailable', 'Account signup is temporarily unavailable.', 503); }
        return Support::lock('signup-phone:' . hash_hmac('sha256', $phone, wp_salt('auth')), static function () use ($phone,$role,$type,$first,$last,$company,$password) {
            global $wpdb;
            $digits = substr($phone, 1); $local = substr($digits, 3); $login = sanitize_user($phone, true);
            // Normalize legacy spacing/punctuation privately. No names or account list are disclosed.
            $normalized = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(meta_value,' ',''),'-',''),'(',''),')',''),'+','')";
            $exists = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='phone_number' AND $normalized IN (%s,%s,%s) LIMIT 1", $digits,$local,'00'.$digits));
            if ($exists || username_exists($login)) { return Support::error('phone_exists', 'This number already has an AutoAgora account. Sign in with your existing password.'); }
            $meta = ['phone_number'=>$phone,'_ag_connect_registered_at'=>time()];
            if ($type === 'dealer') { $meta['dealership_name'] = $company; }
            if (wp_get_environment_type() === 'staging') { $meta['_ag_connect_staging_user'] = '1'; }
            $id = wp_insert_user(['user_login'=>$login,'user_pass'=>$password,'user_email'=>'','first_name'=>$first,'last_name'=>$last,
                'display_name'=>$type === 'dealer' ? $company : trim($first.' '.$last),'role'=>$role,'meta_input'=>$meta]);
            if (is_wp_error($id)) { return Support::error('signup_failed', 'Your account could not be created. Please try again.', 503); }
            return (int) $id;
        });
    }
}
