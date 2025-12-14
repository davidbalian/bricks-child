<?php
/**
 * Resolves a verified email address for a seller when sending notifications.
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class ListingNotificationEmailResolver
{
    private const PHONE_PLACEHOLDER_PREFIX = 'phone_user_';

    /**
     * Returns the verified email address if the user has one, otherwise null.
     *
     * @param int $user_id
     * @return string|null
     */
    public function resolveVerifiedEmail(int $user_id): ?string
    {
        if ($user_id <= 0) {
            return null;
        }

        $user = get_userdata($user_id);

        if (!$user) {
            return null;
        }

        $email = strtolower(trim($user->user_email));

        if ($email === '' || strpos($email, self::PHONE_PLACEHOLDER_PREFIX) === 0) {
            return null;
        }

        $is_verified = get_user_meta($user_id, 'email_verified', true);

        if ((string) $is_verified !== '1') {
            return null;
        }

        return sanitize_email($email);
    }

    /**
     * Helper for checking if the user has a verified email.
     *
     * @param int $user_id
     * @return bool
     */
    public function hasVerifiedEmail(int $user_id): bool
    {
        return $this->resolveVerifiedEmail($user_id) !== null;
    }
}

