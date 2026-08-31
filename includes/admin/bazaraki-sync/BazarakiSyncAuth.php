<?php
/** HMAC request authentication with timestamp and replay protection. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Bazaraki_Sync_Auth
{
    public static function verify(WP_REST_Request $request, string $raw_body)
    {
        $secret = defined('AUTOAGORA_BAZARAKI_SYNC_SECRET') ? (string) AUTOAGORA_BAZARAKI_SYNC_SECRET : '';
        if (strlen($secret) < 32) {
            return new WP_Error('bazaraki_sync_not_configured', __('Bazaraki sync secret is not configured.', 'bricks-child'), array('status' => 503));
        }
        $timestamp = (string) $request->get_header('x-autoagora-timestamp');
        $nonce = (string) $request->get_header('x-autoagora-nonce');
        $signature = strtolower((string) $request->get_header('x-autoagora-signature'));
        if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || !preg_match('/^[A-Za-z0-9_-]{16,96}$/', $nonce)) {
            return new WP_Error('bazaraki_sync_auth', __('Invalid or expired sync credentials.', 'bricks-child'), array('status' => 401));
        }
        $canonical = implode("\n", array(
            $timestamp,
            strtoupper($request->get_method()),
            '/' . ltrim($request->get_route(), '/'),
            $nonce,
            hash('sha256', $raw_body),
        ));
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (!preg_match('/^[a-f0-9]{64}$/', $signature) || !hash_equals($expected, $signature)) {
            return new WP_Error('bazaraki_sync_auth', __('Invalid sync signature.', 'bricks-child'), array('status' => 401));
        }
        $replay_key = 'autoagora_bz_replay_' . hash('sha256', $timestamp . '|' . $nonce . '|' . $signature);
        if (get_transient($replay_key)) {
            return new WP_Error('bazaraki_sync_replay', __('This sync request was already used.', 'bricks-child'), array('status' => 409));
        }
        set_transient($replay_key, 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }
}
