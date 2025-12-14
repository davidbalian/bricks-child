<?php
/**
 * Manages per-user notification preferences for listing emails.
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class ListingNotificationPreferences
{
    private const ACTIVITY_META_KEY = 'notify_activity_milestones';
    private const REMINDER_META_KEY = 'notify_7_day_reminders';

    /**
     * Returns true if contact/view activity alerts are enabled.
     */
    public function isActivityNotificationsEnabled(int $user_id): bool
    {
        return $this->getToggle($user_id, self::ACTIVITY_META_KEY, true);
    }

    /**
     * Returns true if the seller wants 7-day reminder emails.
     */
    public function isReminderNotificationsEnabled(int $user_id): bool
    {
        return $this->getToggle($user_id, self::REMINDER_META_KEY, true);
    }

    /**
     * Toggles activity alerts for a user.
     */
    public function setActivityNotificationsEnabled(int $user_id, bool $enabled): bool
    {
        return update_user_meta($user_id, self::ACTIVITY_META_KEY, $enabled ? '1' : '0') !== false;
    }

    /**
     * Toggles reminder emails for a user.
     */
    public function setReminderNotificationsEnabled(int $user_id, bool $enabled): bool
    {
        return update_user_meta($user_id, self::REMINDER_META_KEY, $enabled ? '1' : '0') !== false;
    }

    private function getToggle(int $user_id, string $meta_key, bool $default): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $value = get_user_meta($user_id, $meta_key, true);

        if ($value === '' || $value === null) {
            return $default;
        }

        return in_array($value, ['1', 'true', 'yes'], true);
    }
}

