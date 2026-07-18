<?php
/**
 * Stripe-hosted Checkout integration for paid listing promotions.
 *
 * Secrets are read from wp-config constants or environment variables and are
 * never stored in WordPress options or sent to the browser.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Stripe_Gateway
{
    const PROVIDER = 'stripe';
    const WEBHOOK_NAMESPACE = 'autoagora/v1';
    const WEBHOOK_ROUTE = '/stripe/webhook';
    const CHECKOUT_AJAX_ACTION = 'autoagora_create_stripe_checkout';
    const CHECKOUT_NONCE_ACTION = 'autoagora_stripe_checkout';

    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register_webhook_route'));
        add_action('wp_ajax_' . self::CHECKOUT_AJAX_ACTION, array(__CLASS__, 'handle_checkout_ajax'));
    }

    public static function mode()
    {
        $mode = defined('AUTOAGORA_STRIPE_MODE') ? strtolower((string) AUTOAGORA_STRIPE_MODE) : 'test';
        return $mode === 'live' ? 'live' : 'test';
    }

    public static function packages()
    {
        if (self::mode() === 'test') {
            $packages = array(
                AutoAgora_Promotion_Manager::TIER_PRIORITY => array(
                    'tier' => AutoAgora_Promotion_Manager::TIER_PRIORITY,
                    'label' => AutoAgora_Promotion_Manager::tier_label(AutoAgora_Promotion_Manager::TIER_PRIORITY),
                    'amount_minor' => defined('AUTOAGORA_STRIPE_LIFT_AMOUNT_CENTS') ? (int) AUTOAGORA_STRIPE_LIFT_AMOUNT_CENTS : 100,
                    'duration_seconds' => defined('AUTOAGORA_STRIPE_LIFT_DURATION_SECONDS') ? (int) AUTOAGORA_STRIPE_LIFT_DURATION_SECONDS : HOUR_IN_SECONDS,
                ),
                AutoAgora_Promotion_Manager::TIER_SHOWCASE => array(
                    'tier' => AutoAgora_Promotion_Manager::TIER_SHOWCASE,
                    'label' => AutoAgora_Promotion_Manager::tier_label(AutoAgora_Promotion_Manager::TIER_SHOWCASE),
                    'amount_minor' => defined('AUTOAGORA_STRIPE_SHOWCASE_AMOUNT_CENTS') ? (int) AUTOAGORA_STRIPE_SHOWCASE_AMOUNT_CENTS : 200,
                    'duration_seconds' => defined('AUTOAGORA_STRIPE_SHOWCASE_DURATION_SECONDS') ? (int) AUTOAGORA_STRIPE_SHOWCASE_DURATION_SECONDS : HOUR_IN_SECONDS,
                ),
            );
        } else {
            $packages = array(
                AutoAgora_Promotion_Manager::TIER_PRIORITY => array(
                    'tier' => AutoAgora_Promotion_Manager::TIER_PRIORITY,
                    'label' => AutoAgora_Promotion_Manager::tier_label(AutoAgora_Promotion_Manager::TIER_PRIORITY),
                    'amount_minor' => defined('AUTOAGORA_STRIPE_LIFT_AMOUNT_CENTS') ? (int) AUTOAGORA_STRIPE_LIFT_AMOUNT_CENTS : 0,
                    'duration_seconds' => defined('AUTOAGORA_STRIPE_LIFT_DURATION_SECONDS') ? (int) AUTOAGORA_STRIPE_LIFT_DURATION_SECONDS : 0,
                ),
                AutoAgora_Promotion_Manager::TIER_SHOWCASE => array(
                    'tier' => AutoAgora_Promotion_Manager::TIER_SHOWCASE,
                    'label' => AutoAgora_Promotion_Manager::tier_label(AutoAgora_Promotion_Manager::TIER_SHOWCASE),
                    'amount_minor' => defined('AUTOAGORA_STRIPE_SHOWCASE_AMOUNT_CENTS') ? (int) AUTOAGORA_STRIPE_SHOWCASE_AMOUNT_CENTS : 0,
                    'duration_seconds' => defined('AUTOAGORA_STRIPE_SHOWCASE_DURATION_SECONDS') ? (int) AUTOAGORA_STRIPE_SHOWCASE_DURATION_SECONDS : 0,
                ),
            );
        }

        $packages = apply_filters('autoagora_stripe_promotion_packages', $packages, self::mode());
        foreach ($packages as $tier => $package) {
            if (
                !isset($package['amount_minor'], $package['duration_seconds'])
                || (int) $package['amount_minor'] < 50
                || (int) $package['duration_seconds'] < HOUR_IN_SECONDS
                || (int) $package['duration_seconds'] > YEAR_IN_SECONDS
            ) {
                unset($packages[$tier]);
            }
        }
        return $packages;
    }

    public static function configuration_errors()
    {
        $errors = array();
        $secret_key = self::secret_key();
        $webhook_secret = self::webhook_secret();
        $expected_key_prefix = self::mode() === 'live' ? array('sk_live_', 'rk_live_') : array('sk_test_', 'rk_test_');

        if ($secret_key === '' || !self::starts_with_any($secret_key, $expected_key_prefix)) {
            $errors[] = 'The Stripe ' . self::mode() . ' secret key is missing or does not match the configured mode.';
        }
        if ($webhook_secret === '' || strpos($webhook_secret, 'whsec_') !== 0) {
            $errors[] = 'The Stripe webhook signing secret is missing.';
        }
        if (!self::packages()) {
            $errors[] = 'No valid Stripe promotion packages are configured.';
        }
        if (self::mode() === 'live' && (!defined('AUTOAGORA_STRIPE_LIVE_ENABLED') || AUTOAGORA_STRIPE_LIVE_ENABLED !== true)) {
            $errors[] = 'Stripe live mode is locked. Set AUTOAGORA_STRIPE_LIVE_ENABLED to true only after completing sandbox tests.';
        }
        return $errors;
    }

    public static function is_ready()
    {
        return empty(self::configuration_errors());
    }

    public static function webhook_url()
    {
        return rest_url(self::WEBHOOK_NAMESPACE . self::WEBHOOK_ROUTE);
    }

    public static function handle_checkout_ajax()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to purchase a promotion.'), 401);
        }
        if (!check_ajax_referer(self::CHECKOUT_NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Your session expired. Reload the page and try again.'), 403);
        }
        if (!self::is_ready()) {
            wp_send_json_error(array('message' => 'Stripe Checkout is not configured yet.'), 503);
        }

        $listing_id = isset($_POST['listing_id']) ? absint($_POST['listing_id']) : 0;
        $tier = isset($_POST['tier']) ? sanitize_key(wp_unslash($_POST['tier'])) : '';
        $attempt = isset($_POST['attempt']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) wp_unslash($_POST['attempt'])) : '';
        $attempt = substr($attempt, 0, 80);
        if ($attempt === '') {
            $attempt = wp_generate_uuid4();
        }

        $ownership_error = self::validate_seller_listing($listing_id, get_current_user_id());
        if (is_wp_error($ownership_error)) {
            wp_send_json_error(array('message' => $ownership_error->get_error_message()), 403);
        }
        $packages = self::packages();
        if (!isset($packages[$tier])) {
            wp_send_json_error(array('message' => 'The selected promotion package is unavailable.'), 400);
        }

        $result = self::create_checkout_session($listing_id, get_current_user_id(), $packages[$tier], $attempt);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 502);
        }
        wp_send_json_success($result);
    }

    public static function create_checkout_session($listing_id, $user_id, array $package, $attempt)
    {
        $user = get_userdata((int) $user_id);
        $currency = 'eur';
        $amount = (int) $package['amount_minor'];
        $duration = (int) $package['duration_seconds'];
        $tier = sanitize_key($package['tier']);
        $label = sanitize_text_field($package['label']);
        $metadata = array(
            'integration' => 'autoagora_listing_promotion_v1',
            'listing_id' => (string) (int) $listing_id,
            'user_id' => (string) (int) $user_id,
            'tier' => $tier,
            'duration_seconds' => (string) $duration,
            'expected_amount' => (string) $amount,
            'currency' => $currency,
            'environment' => self::mode(),
        );

        $success_url = home_url('/my-listings/?promotion_payment=success&session_id={CHECKOUT_SESSION_ID}');
        $cancel_url = home_url('/my-listings/?promotion_payment=cancelled');
        $body = array(
            'mode' => 'payment',
            'payment_method_types' => array('card'),
            'client_reference_id' => 'listing-' . (int) $listing_id . '-user-' . (int) $user_id,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'line_items' => array(array(
                'quantity' => 1,
                'price_data' => array(
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => array(
                        'name' => $label . ' listing promotion',
                        'description' => self::duration_label($duration) . ' promotion for listing #' . (int) $listing_id,
                    ),
                ),
            )),
            'metadata' => $metadata,
            'payment_intent_data' => array(
                'description' => $label . ' for AutoAgora listing #' . (int) $listing_id,
                'metadata' => $metadata,
            ),
        );
        if ($user && is_email($user->user_email)) {
            $body['customer_email'] = $user->user_email;
        }

        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
            'timeout' => 20,
            'redirection' => 0,
            'headers' => array(
                'Authorization' => 'Bearer ' . self::secret_key(),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Idempotency-Key' => 'autoagora-promotion-' . (int) $user_id . '-' . (int) $listing_id . '-' . $tier . '-' . $attempt,
            ),
            'body' => http_build_query($body, '', '&'),
        ));
        if (is_wp_error($response)) {
            return new WP_Error('stripe_unavailable', 'Stripe could not be reached. Please try again.');
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($status_code < 200 || $status_code >= 300 || !is_array($decoded)) {
            $message = isset($decoded['error']['message']) ? sanitize_text_field($decoded['error']['message']) : 'Stripe could not create the checkout session.';
            error_log('AutoAgora Stripe Checkout error (' . $status_code . '): ' . $message);
            return new WP_Error('stripe_session_failed', $message);
        }
        if (empty($decoded['id']) || empty($decoded['url']) || !self::is_allowed_checkout_url($decoded['url'])) {
            return new WP_Error('stripe_session_invalid', 'Stripe returned an invalid checkout session.');
        }
        if (isset($decoded['livemode']) && (bool) $decoded['livemode'] !== (self::mode() === 'live')) {
            return new WP_Error('stripe_mode_mismatch', 'Stripe returned a session from the wrong environment.');
        }

        return array(
            'session_id' => sanitize_text_field($decoded['id']),
            'checkout_url' => esc_url_raw($decoded['url']),
        );
    }

    public static function register_webhook_route()
    {
        register_rest_route(self::WEBHOOK_NAMESPACE, self::WEBHOOK_ROUTE, array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function handle_webhook(WP_REST_Request $request)
    {
        if (!self::is_ready()) {
            return new WP_REST_Response(array('error' => 'Stripe is not configured.'), 503);
        }
        $payload = $request->get_body();
        $signature = $request->get_header('stripe-signature');
        if (!self::verify_webhook_signature($payload, $signature, self::webhook_secret())) {
            return new WP_REST_Response(array('error' => 'Invalid signature.'), 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type']) || empty($event['data']['object'])) {
            return new WP_REST_Response(array('error' => 'Invalid event payload.'), 400);
        }
        $object = $event['data']['object'];

        if ($event['type'] === 'checkout.session.completed') {
            $metadata = isset($object['metadata']) && is_array($object['metadata']) ? $object['metadata'] : array();
            if (($metadata['integration'] ?? '') !== 'autoagora_listing_promotion_v1') {
                return new WP_REST_Response(array('received' => true, 'ignored' => true), 200);
            }
            $result = self::fulfill_checkout_session($object);
            if (is_wp_error($result)) {
                error_log('AutoAgora Stripe fulfillment error: ' . $result->get_error_message());
                return new WP_REST_Response(array('error' => $result->get_error_code()), 400);
            }
        } elseif ($event['type'] === 'charge.refunded' && !empty($object['refunded']) && !empty($object['payment_intent'])) {
            $result = autoagora_refund_paid_listing_promotion(self::PROVIDER, sanitize_text_field($object['payment_intent']));
            if (is_wp_error($result) && $result->get_error_code() !== 'promotion_payment_not_found') {
                error_log('AutoAgora Stripe refund error: ' . $result->get_error_message());
                return new WP_REST_Response(array('error' => $result->get_error_code()), 400);
            }
        }

        return new WP_REST_Response(array('received' => true), 200);
    }

    private static function fulfill_checkout_session(array $session)
    {
        if (($session['mode'] ?? '') !== 'payment' || ($session['payment_status'] ?? '') !== 'paid') {
            return new WP_Error('stripe_payment_unpaid', 'Checkout Session is not paid.');
        }
        if ((bool) ($session['livemode'] ?? false) !== (self::mode() === 'live')) {
            return new WP_Error('stripe_mode_mismatch', 'Webhook environment does not match the configured Stripe mode.');
        }
        $metadata = isset($session['metadata']) && is_array($session['metadata']) ? $session['metadata'] : array();
        if (($metadata['integration'] ?? '') !== 'autoagora_listing_promotion_v1') {
            return new WP_Error('stripe_metadata_invalid', 'Checkout Session is not an AutoAgora promotion purchase.');
        }

        $listing_id = isset($metadata['listing_id']) ? absint($metadata['listing_id']) : 0;
        $user_id = isset($metadata['user_id']) ? absint($metadata['user_id']) : 0;
        $tier = isset($metadata['tier']) ? sanitize_key($metadata['tier']) : '';
        $duration = isset($metadata['duration_seconds']) ? absint($metadata['duration_seconds']) : 0;
        $expected_amount = isset($metadata['expected_amount']) ? absint($metadata['expected_amount']) : 0;
        $amount_total = isset($session['amount_total']) ? (int) $session['amount_total'] : -1;
        $currency = isset($session['currency']) ? strtolower(sanitize_key($session['currency'])) : '';
        $payment_intent = isset($session['payment_intent']) ? sanitize_text_field($session['payment_intent']) : '';

        if (!$listing_id || !$user_id || !isset(AutoAgora_Promotion_Manager::tiers()[$tier])) {
            return new WP_Error('stripe_metadata_invalid', 'Required promotion metadata is missing.');
        }
        if ($duration < HOUR_IN_SECONDS || $duration > YEAR_IN_SECONDS || $expected_amount < 50 || $amount_total !== $expected_amount || $currency !== 'eur') {
            return new WP_Error('stripe_amount_invalid', 'The paid amount or promotion duration does not match Checkout metadata.');
        }
        if ($payment_intent === '' || strpos($payment_intent, 'pi_') !== 0) {
            return new WP_Error('stripe_payment_reference_invalid', 'Stripe PaymentIntent reference is missing.');
        }
        $ownership_error = self::validate_seller_listing($listing_id, $user_id);
        if (is_wp_error($ownership_error)) {
            return $ownership_error;
        }

        $session_id = isset($session['id']) ? sanitize_text_field($session['id']) : '';
        return autoagora_grant_paid_listing_promotion(
            $listing_id,
            $tier,
            $duration,
            self::PROVIDER,
            $payment_intent,
            'Stripe Checkout ' . $session_id . '; EUR ' . number_format($amount_total / 100, 2, '.', '') . '; ' . self::mode()
        );
    }

    private static function validate_seller_listing($listing_id, $user_id)
    {
        $post = get_post((int) $listing_id);
        if (!$post || $post->post_type !== 'car' || $post->post_status !== 'publish') {
            return new WP_Error('stripe_listing_invalid', 'Only published car listings can be promoted.');
        }
        if ((int) $post->post_author !== (int) $user_id && !user_can((int) $user_id, 'manage_options')) {
            return new WP_Error('stripe_listing_ownership', 'You can only promote your own listing.');
        }
        if (class_exists('ListingStateManager') && ListingStateManager::resolve_state((int) $listing_id) !== ListingStateManager::STATE_ACTIVE) {
            return new WP_Error('stripe_listing_inactive', 'Only active listings can be promoted.');
        }
        return true;
    }

    private static function verify_webhook_signature($payload, $header, $secret)
    {
        if (!is_string($payload) || $payload === '' || !is_string($header) || $header === '' || $secret === '') {
            return false;
        }
        $timestamp = 0;
        $signatures = array();
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = (int) $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = trim($pair[1]);
            }
        }
        if ($timestamp <= 0 || abs(time() - $timestamp) > 300 || !$signatures) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    private static function secret_key()
    {
        return self::config_value('AUTOAGORA_STRIPE_SECRET_KEY');
    }

    private static function webhook_secret()
    {
        return self::config_value('AUTOAGORA_STRIPE_WEBHOOK_SECRET');
    }

    private static function config_value($name)
    {
        if (defined($name)) {
            return trim((string) constant($name));
        }
        $value = getenv($name);
        return is_string($value) ? trim($value) : '';
    }

    private static function starts_with_any($value, array $prefixes)
    {
        foreach ($prefixes as $prefix) {
            if (strpos($value, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function is_allowed_checkout_url($url)
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        return $host === 'checkout.stripe.com';
    }

    public static function duration_label($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds % DAY_IN_SECONDS === 0) {
            $days = (int) ($seconds / DAY_IN_SECONDS);
            return $days . ' ' . _n('day', 'days', $days, 'bricks-child');
        }
        $hours = max(1, (int) round($seconds / HOUR_IN_SECONDS));
        return $hours . ' ' . _n('hour', 'hours', $hours, 'bricks-child');
    }
}

AutoAgora_Stripe_Gateway::init();
