<?php
/**
 * Keeps track of per-listing notification state to avoid duplicates.
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class ListingNotificationStateRepository
{
    private const CONTACT_CLICK_META_KEY       = 'notification_contact_clicks_sent';
    private const VIEW_MILESTONES_META_KEY     = 'notification_view_milestones_sent';
    private const REMINDER_COUNT_META_KEY      = 'notification_reminder_count';
    private const REMINDER_LAST_TS_META_KEY    = 'notification_last_reminder_at';
    private const PUBLISH_NOTIFICATION_META_KEY = 'notification_publish_sent';

    public function hasContactClickNotificationBeenSent(int $car_id): bool
    {
        return get_post_meta($car_id, self::CONTACT_CLICK_META_KEY, true) === '1';
    }

    public function markContactClickNotificationSent(int $car_id): void
    {
        update_post_meta($car_id, self::CONTACT_CLICK_META_KEY, '1');
    }

    public function getViewMilestonesSent(int $car_id): array
    {
        $milestones = get_post_meta($car_id, self::VIEW_MILESTONES_META_KEY, true);

        if (empty($milestones)) {
            return [];
        }

        if (!is_array($milestones)) {
            $milestones = maybe_unserialize($milestones);
        }

        return array_map('intval', (array) $milestones);
    }

    public function markViewMilestoneSent(int $car_id, int $milestone): void
    {
        $sent = $this->getViewMilestonesSent($car_id);

        if (!in_array($milestone, $sent, true)) {
            $sent[] = $milestone;
            update_post_meta($car_id, self::VIEW_MILESTONES_META_KEY, $sent);
        }
    }

    public function getReminderCount(int $car_id): int
    {
        return max(0, absint(get_post_meta($car_id, self::REMINDER_COUNT_META_KEY, true)));
    }

    public function incrementReminderCount(int $car_id): int
    {
        $current = $this->getReminderCount($car_id);
        $next = $current + 1;
        update_post_meta($car_id, self::REMINDER_COUNT_META_KEY, $next);
        return $next;
    }

    public function getLastReminderTimestamp(int $car_id): ?string
    {
        $timestamp = get_post_meta($car_id, self::REMINDER_LAST_TS_META_KEY, true);
        return $timestamp ? $timestamp : null;
    }

    public function updateLastReminderTimestamp(int $car_id): void
    {
        update_post_meta($car_id, self::REMINDER_LAST_TS_META_KEY, current_time('mysql'));
    }

    public function hasPublishNotificationBeenSent(int $car_id): bool
    {
        return get_post_meta($car_id, self::PUBLISH_NOTIFICATION_META_KEY, true) === '1';
    }

    public function markPublishNotificationSent(int $car_id): void
    {
        update_post_meta($car_id, self::PUBLISH_NOTIFICATION_META_KEY, '1');
    }
}

