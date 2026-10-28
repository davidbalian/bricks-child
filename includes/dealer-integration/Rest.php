<?php
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Rest
{
    public const NS = 'autoagora-dealer/v1';
    private static array $contexts = [];

    public static function register(): void
    {
        foreach (['token', 'revoke'] as $endpoint) {
            register_rest_route(self::NS, '/oauth/' . $endpoint, ['methods' => 'POST', 'callback' => [OAuth::class, $endpoint], 'permission_callback' => '__return_true']);
        }
        self::route('/listings', 'GET', 'stock:read', [self::class, 'index']);
        self::route('/listings', 'POST', 'stock:write', [Listings::class, 'save']);
        self::route('/listings/(?P<id>\d+)', 'GET', 'stock:read', static fn ($r, $c) => Listings::read((int) $r['id'], $c));
        self::route('/listings/(?P<id>\d+)', 'PATCH', 'stock:write', [Listings::class, 'save']);
        self::route('/listings/(?P<id>\d+)/status', 'POST', 'stock:status', [Listings::class, 'status']);
        self::route('/catalog', 'GET', 'catalog:read', [self::class, 'catalog']);
        self::route('/showroom', 'GET', 'stock:read', static fn ($r, $c) => ['location' => get_user_meta($c['dealer'], '_ag_dealer_showroom', true) ?: null]);
        self::route('/showroom', 'PUT', 'showroom:write', static function ($r, $c) {
            $body = $r->get_json_params();
            if (!is_array($body) || array_keys($body) !== ['location']) { return Support::error('invalid_location', 'Supply a location object.'); }
            $location = Validation::location($body['location']);
            if (is_wp_error($location)) { return $location; }
            update_user_meta($c['dealer'], '_ag_dealer_showroom', $location);
            Support::audit('showroom_updated', $c['client'], $c['dealer']);
            return ['saved' => true];
        });
        self::route('/listings/(?P<id>\d+)/views', 'GET', 'stock:read', [self::class, 'views']);
        self::route('/connection', 'DELETE', 'stock:read', static function ($r, $c) {
            return Support::lock('oauth:' . $c['client'], static function () use ($c) { Grants::revoke($c['client'], $c['dealer']); return ['disconnected' => true]; });
        });
    }
    private static function route(string $path, string $method, string $scope, callable $callback): void
    {
        register_rest_route(self::NS, $path, [
            'methods' => $method,
            'permission_callback' => static function ($request) use ($scope) {
                $context = OAuth::context($request, $scope);
                if (is_wp_error($context)) { return $context; }
                self::$contexts[spl_object_id($request)] = $context;
                return true;
            },
            'callback' => static function ($request) use ($method, $callback) {
                $context = self::$contexts[spl_object_id($request)];
                // Context is overwritten by the permission callback; never trust user-supplied dealer/client IDs.
                if ($method === 'GET') { return $callback($request, $context); }
                if (strlen($request->get_body()) > 262144) { return Support::error('request_too_large', 'JSON requests are limited to 256 KiB.', 413); }
                if ($method !== 'DELETE' && stripos($request->get_header('content-type'), 'application/json') !== 0) {
                    return Support::error('unsupported_media_type', 'Send application/json.', 415);
                }
                if (!Support::rate('write:' . $context['client'] . ':' . $context['dealer'], 10)) { return Support::error('rate_limit', 'Write limit exceeded. Retry in 60 seconds.', 429); }
                return Support::lock('dealer:' . $context['dealer'], static function () use ($request, $context, $callback) {
                    // Recheck revocation/eligibility after waiting for the dealer lock.
                    $verified = OAuth::context($request, self::scopeFor($request), false);
                    if (is_wp_error($verified)) { return $verified; }
                    return self::idempotent($request, $context, $callback);
                });
            },
        ]);
    }
    private static function scopeFor(\WP_REST_Request $request): string
    {
        if (str_ends_with($request->get_route(), '/status')) { return 'stock:status'; }
        if (str_ends_with($request->get_route(), '/showroom')) { return 'showroom:write'; }
        return $request->get_method() === 'DELETE' ? 'stock:read' : 'stock:write';
    }
    private static function idempotent(\WP_REST_Request $request, array $context, callable $callback)
    {
        global $wpdb;
        $key = $request->get_header('idempotency-key');
        if (!preg_match('/^[\x21-\x7E]{8,128}$/D', $key)) { return Support::error('idempotency_key_required', 'Supply an Idempotency-Key header (8–128 printable ASCII characters).', 400); }
        $context['request_key'] = hash('sha256', $context['client'] . ':' . $context['dealer'] . ':' . $request->get_method() . ':' . $request->get_route() . ':' . $key);
        $payloadHash = hash('sha256', $request->get_body() . ':' . $request->get_header('if-match'));
        $table = Support::table('requests');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE request_key = %s", $context['request_key']), ARRAY_A);
        if ($row) {
            if (!hash_equals($row['payload_hash'], $payloadHash)) { return Support::error('idempotency_conflict', 'This Idempotency-Key was already used for a different request.', 409); }
            if ($row['response'] !== null) {
                $cached = json_decode($row['response'], true);
                return new \WP_REST_Response($cached['data'], (int) $row['status'], $cached['headers'] + ['Idempotency-Replayed' => 'true']);
            }
        } elseif (!$wpdb->insert($table, ['request_key' => $context['request_key'], 'payload_hash' => $payloadHash, 'created_at' => time()])) {
            return Support::error('request_storage_failed', 'Could not reserve this request. Retry shortly.', 503);
        }
        $response = $callback($request, $context);
        if (is_wp_error($response)) {
            // Failed operations are retryable with this key; no sensitive payload or failed response is persisted.
            $wpdb->delete($table, ['request_key' => $context['request_key']]);
            return $response;
        }
        $response = rest_ensure_response($response);
        $saved = $wpdb->update($table, ['response' => wp_json_encode(['data' => $response->get_data(), 'headers' => $response->get_headers()]), 'status' => $response->get_status()], ['request_key' => $context['request_key']]);
        if ($saved === false) { return Support::error('request_receipt_failed', 'The operation may have completed. Read the listing before retrying.', 503); }
        return $response;
    }
    public static function index(\WP_REST_Request $request, array $context)
    {
        global $wpdb;
        $page = $request->get_param('page') ?? '1';
        $per = $request->get_param('per_page') ?? '20';
        if (!ctype_digit((string) $page) || !ctype_digit((string) $per) || (int) $page < 1 || (int) $page > 10000 || (int) $per < 1 || (int) $per > 50) {
            return Support::error('invalid_pagination', 'Use page 1–10000 and per_page 1–50.', 400);
        }
        $args = ['post_type' => 'car', 'post_status' => ['publish', 'pending', 'draft', 'private'], 'author' => $context['dealer'],
            'posts_per_page' => (int) $per, 'paged' => (int) $page, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids'];
        if (($external = $request->get_param('external_stock_id')) !== null) {
            if (!is_string($external) || strlen($external) > 128) { return Support::error('invalid_external_id', 'Invalid external stock ID.', 400); }
            $key = hash('sha256', $context['client'] . ':' . $context['dealer'] . ':' . $external);
            $id = (int) $wpdb->get_var($wpdb->prepare('SELECT listing_id FROM ' . Support::table('stocks') . ' WHERE stock_key = %s', $key));
            $args['post__in'] = [$id ?: 0];
        }
        $query = new \WP_Query($args);
        return new \WP_REST_Response(['items' => array_map([Listings::class, 'summary'], $query->posts), 'page' => (int) $page,
            'per_page' => (int) $per, 'total' => (int) $query->found_posts, 'total_pages' => (int) $query->max_num_pages]);
    }
    public static function catalog(\WP_REST_Request $request, array $context)
    {
        $page = $request->get_param('page') ?? '1';
        $parent = $request->get_param('make_id') ?? '0';
        if (!ctype_digit((string) $page) || (int) $page < 1 || (int) $page > 1000 || !ctype_digit((string) $parent)) { return Support::error('invalid_pagination', 'Invalid catalog page or make_id.', 400); }
        if ((int) $parent > 0) {
            $make = get_term((int) $parent, 'car_make');
            if (!$make || is_wp_error($make) || $make->parent !== 0) { return Support::error('invalid_make', 'make_id must identify a parent make.', 400); }
        }
        $terms = get_terms(['taxonomy' => 'car_make', 'parent' => (int) $parent, 'hide_empty' => false, 'number' => 50, 'offset' => ((int) $page - 1) * 50, 'orderby' => 'name']);
        if (is_wp_error($terms)) { return Support::error('catalog_unavailable', 'Catalog unavailable.', 503); }
        $total = (int) wp_count_terms(['taxonomy' => 'car_make', 'parent' => (int) $parent, 'hide_empty' => false]);
        return ['options' => get_allowed_field_values(), 'year' => ['min' => 1948, 'max' => (int) wp_date('Y')],
            'photos' => ['min' => 2, 'max' => 25, 'max_bytes' => Media::MAX_BYTES, 'max_pixels' => Media::MAX_PIXELS, 'max_side' => 12000, 'preferred' => 'image/webp'],
            'terms' => array_map(static fn ($term) => ['id' => (int) $term->term_id, 'name' => $term->name, 'parent' => (int) $term->parent], $terms),
            'page' => (int) $page, 'per_page' => 50, 'total' => $total, 'total_pages' => (int) ceil($total / 50)];
    }
    public static function views(\WP_REST_Request $request, array $context)
    {
        global $wpdb;
        $id = (int) $request['id'];
        if (is_wp_error($owned = Listings::owned($id, $context))) { return $owned; }
        // Tracking undercounts pages served entirely from cache and uses heuristic bot/IP filtering.
        // Keep reporting explicitly unavailable until the actual staging tracking path has been verified.
        return ['available' => false, 'reason' => 'tracking_not_verified',
            'definition' => 'Existing tracker: total has a five-minute cooldown per IP/UA fingerprint; unique is a lifetime fingerprint, not a person. Owners, admins and detected bots are excluded. Cached pages may not reach the tracker.'];
    }
}
