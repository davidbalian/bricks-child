<?php
/** Shared infrastructure for the opt-in dealer integration. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Support
{
    public const SCOPES = [
        'stock:read' => 'Read your listings and their status',
        'stock:write' => 'Create and update your listings and photos',
        'stock:status' => 'Mark your listings sold or withdraw them',
        'catalog:read' => 'Read supported vehicle specifications',
        'showroom:write' => 'Save your default showroom location',
    ];

    public static function enabled(): bool
    {
        return defined('AUTOAGORA_DEALER_API_ENABLED') && AUTOAGORA_DEALER_API_ENABLED
            && (wp_get_environment_type() !== 'production'
                || (defined('AUTOAGORA_DEALER_API_PRODUCTION_APPROVED') && AUTOAGORA_DEALER_API_PRODUCTION_APPROVED));
    }

    public static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ag_dealer_' . $suffix;
    }

    public static function submissionStatus(int $dealer, int $listing): string
    {
        // A staging sandbox may show complete imports immediately. Production and
        // local/private review always retain moderation, regardless of filters.
        if (wp_get_environment_type() !== 'staging' || Review::privateMode()) { return 'pending'; }
        return apply_filters('autoagora_dealer_api_submission_status', 'pending', $dealer, $listing) === 'publish' ? 'publish' : 'pending';
    }

    public static function error(string $code, string $message, int $status = 422, array $details = []): \WP_Error
    {
        return new \WP_Error($code, $message, ['status' => $status] + $details);
    }

    public static function eligible(int $user): bool
    {
        $account = get_userdata($user);
        // The existing verification badge is based on this role, not public dealer-profile claims.
        return $account && in_array('dealership', $account->roles, true)
            && user_can($user, 'upload_files') && user_can($user, 'edit_posts')
            && !get_user_meta($user, '_autoagora_dealer_api_blocked', true)
            && (bool) apply_filters('autoagora_dealer_api_eligible', true, $user);
    }

    public static function lock(string $key, callable $work)
    {
        global $wpdb;
        $name = 'ag-dealer-' . substr(hash('sha256', $wpdb->prefix . $key), 0, 48);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $name)) !== 1) {
            return self::error('request_busy', 'Another request is processing. Retry shortly.', 409, ['retry_after' => 3]);
        }
        try {
            return $work();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }

    public static function audit(string $event, string $client = '', int $dealer = 0, int $listing = 0): void
    {
        global $wpdb;
        // Never include request bodies, URLs, tokens, names, addresses, IPs, or secrets.
        $wpdb->insert(self::table('audit'), [
            'event' => substr(sanitize_key($event), 0, 64), 'client_id' => $client,
            'dealer_id' => $dealer, 'listing_id' => $listing, 'created_at' => time(),
        ]);
    }

    public static function rate(string $key, int $limit): bool
    {
        global $wpdb;
        $table = self::table('limits');
        $bucket = hash('sha256', $key . ':' . intdiv(time(), 60));
        $ok = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (bucket, hits, expires_at) VALUES (%s, 1, %d) ON DUPLICATE KEY UPDATE hits = hits + 1",
            $bucket, time() + 120
        ));
        return $ok !== false && (int) $wpdb->get_var($wpdb->prepare("SELECT hits FROM $table WHERE bucket = %s", $bucket)) <= $limit;
    }

    public static function ready(): bool
    {
        $loopback = wp_get_environment_type() === 'local' && in_array(wp_parse_url(home_url('/'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true);
        return self::enabled() && ($loopback || is_ssl() || (defined('WP_CLI') && WP_CLI)) && get_option('autoagora_dealer_schema') === '1'
            && class_exists(OAuth::class) && class_exists(Review::class) && class_exists(Workspace::class)
            && class_exists('League\\OAuth2\\Server\\AuthorizationServer')
            && defined('AUTOAGORA_DEALER_PRIVATE_KEY') && is_readable(AUTOAGORA_DEALER_PRIVATE_KEY)
            && defined('AUTOAGORA_DEALER_PUBLIC_KEY') && is_readable(AUTOAGORA_DEALER_PUBLIC_KEY)
            && defined('AUTOAGORA_DEALER_ENCRYPTION_KEY') && strlen(AUTOAGORA_DEALER_ENCRYPTION_KEY) >= 32
            && post_type_exists('car') && taxonomy_exists('car_make')
            && function_exists('update_field') && class_exists('ListingStateManager');
    }

    public static function cleanup(): void
    {
        global $wpdb;
        Media::cleanup();
        foreach (['limits' => ['expires_at', time()], 'audit' => ['created_at', time() - 90 * DAY_IN_SECONDS],
            'requests' => ['created_at', time() - 30 * DAY_IN_SECONDS], 'tokens' => ['expires_at', time() - 30 * DAY_IN_SECONDS]] as $suffix => [$field, $before]) {
            $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table($suffix) . " WHERE $field < %d LIMIT 10000", $before));
        }
    }
}
