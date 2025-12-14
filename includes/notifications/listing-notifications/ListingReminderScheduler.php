<?php
/**
 * Handles the 7-day reminder cron and enqueues listing reminders.
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class ListingReminderScheduler
{
    private const CRON_HOOK = 'autoagora_listing_reminder_cron';
    private const POSTS_PER_RUN = 25;
    private const REMINDER_INTERVAL_SECONDS = WEEK_IN_SECONDS;

    private RefreshListingManager $refreshManager;
    private ListingNotificationStateRepository $stateRepository;

    public function __construct()
    {
        $this->refreshManager = new RefreshListingManager();
        $this->stateRepository = new ListingNotificationStateRepository();

        add_action('init', array($this, 'maybeScheduleCron'));
        add_action(self::CRON_HOOK, array($this, 'handleReminderCron'));
    }

    public function maybeScheduleCron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    public function handleReminderCron(): void
    {
        $listings = $this->queryListingsForReminders();

        foreach ($listings as $car_id) {
            if (!$this->shouldSendReminder($car_id)) {
                continue;
            }

            $refresh_url = home_url('/my-listings/?refresh_listing=' . $car_id);
            $mark_as_sold_url = home_url('/my-listings/?mark_sold=' . $car_id);

            listing_notification_manager()->maybeSendReminderNotification($car_id, $refresh_url, $mark_as_sold_url);
        }
    }

    /**
     * @return int[]
     */
    private function queryListingsForReminders(): array
    {
        $query = new WP_Query([
            'post_type' => 'car',
            'post_status' => 'publish',
            'posts_per_page' => self::POSTS_PER_RUN,
            'orderby' => 'post_date',
            'order' => 'DESC',
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'is_sold',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'is_sold',
                    'value' => '',
                    'compare' => '='
                ],
                [
                    'key' => 'is_sold',
                    'value' => '0',
                    'compare' => '='
                ]
            ]
        ]);

        return is_wp_error($query) ? [] : $query->posts;
    }

    private function shouldSendReminder(int $car_id): bool
    {
        if ($this->stateRepository->getReminderCount($car_id) >= 3) {
            return false;
        }

        $lastReminder = $this->stateRepository->getLastReminderTimestamp($car_id);
        $lastReminderTs = $lastReminder ? strtotime($lastReminder) : 0;

        $lastActivityTs = $this->getLastActivityTimestamp($car_id);

        $reference = max($lastReminderTs, $lastActivityTs);

        if ($reference === 0) {
            return false;
        }

        return (time() - $reference) >= self::REMINDER_INTERVAL_SECONDS;
    }

    private function getLastActivityTimestamp(int $car_id): int
    {
        $timestamps = [];

        $publication = get_post_meta($car_id, 'publication_date', true);
        if ($publication) {
            $timestamps[] = strtotime($publication);
        }

        $refresh = $this->refreshManager->get_last_refresh_date($car_id);
        if ($refresh) {
            $timestamps[] = strtotime($refresh);
        }

        $modified = get_post_field('post_modified', $car_id);
        if ($modified) {
            $timestamps[] = strtotime($modified);
        }

        if (empty($timestamps)) {
            return (int) get_post_time('U', true, $car_id);
        }

        return max($timestamps);
    }
}

