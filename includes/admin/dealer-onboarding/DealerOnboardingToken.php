<?php
/** Dedicated authentication token for the dealership onboarding API. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Dealer_Onboarding_Token
{
    private const OPTION = 'autoagora_dealer_onboarding_api_token';
    public const HEADER = 'x-autoagora-onboarding-token';

    /** @return string|WP_Error Plaintext token is returned only at creation time. */
    public static function generate(int $user_id)
    {
        if ($user_id < 1 || !user_can($user_id, 'manage_options')) {
            return new WP_Error('dealer_onboarding_token_user', __('A valid administrator is required.', 'bricks-child'));
        }

        try {
            $token = 'aag_' . bin2hex(random_bytes(32));
        } catch (Throwable $error) {
            return new WP_Error('dealer_onboarding_token_random', __('Could not generate a secure token.', 'bricks-child'));
        }

        $record = array(
            'hash' => wp_hash_password($token),
            'prefix' => substr($token, 0, 12),
            'created_at' => gmdate('c'),
            'created_by' => $user_id,
        );
        update_option(self::OPTION, $record, false);
        $saved = get_option(self::OPTION, array());
        if (!is_array($saved) || empty($saved['hash']) || !wp_check_password($token, (string) $saved['hash'])) {
            return new WP_Error('dealer_onboarding_token_store', __('Could not store the onboarding token.', 'bricks-child'));
        }

        return $token;
    }

    public static function revoke(): void
    {
        delete_option(self::OPTION);
    }

    /** @return array<string,mixed> */
    public static function status(): array
    {
        $record = get_option(self::OPTION, array());
        if (!is_array($record) || empty($record['hash'])) {
            return array('configured' => false);
        }

        return array(
            'configured' => true,
            'prefix' => sanitize_text_field((string) ($record['prefix'] ?? '')),
            'created_at' => sanitize_text_field((string) ($record['created_at'] ?? '')),
            'created_by' => absint($record['created_by'] ?? 0),
        );
    }

    /** @return int|WP_Error Authenticated administrator user ID. */
    public static function authenticate(WP_REST_Request $request)
    {
        $token = trim((string) $request->get_header(self::HEADER));
        if (!preg_match('/^aag_[a-f0-9]{64}$/', $token)) {
            return self::authenticationError();
        }

        $record = get_option(self::OPTION, array());
        if (!is_array($record) || empty($record['hash']) || !wp_check_password($token, (string) $record['hash'])) {
            return self::authenticationError();
        }

        $user_id = absint($record['created_by'] ?? 0);
        if ($user_id < 1 || !user_can($user_id, 'manage_options')) {
            return self::authenticationError();
        }

        wp_set_current_user($user_id);
        return $user_id;
    }

    private static function authenticationError(): WP_Error
    {
        return new WP_Error(
            'autoagora_dealer_onboarding_forbidden',
            __('A valid dealership onboarding token is required.', 'bricks-child'),
            array('status' => 403)
        );
    }
}
