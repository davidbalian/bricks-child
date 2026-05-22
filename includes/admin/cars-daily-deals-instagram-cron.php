<?php
/**
 * Daily Deals: Instagram publishing cron.
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarsDailyDealsInstagramCron
{
    public const HOOK = 'bricks_child_daily_deals_instagram_publish';

    public static function bootstrap(): void
    {
        add_action('init', array(__CLASS__, 'schedule'), 50);
        add_action(self::HOOK, array(__CLASS__, 'run'));
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_single_event(self::nextCyprusRunTimestamp(), self::HOOK);
        }
    }

    public static function run(): void
    {
        $publisher = new CarsDailyDealsInstagramPublisher();
        $publisher->publishToday(false);

        wp_clear_scheduled_hook(self::HOOK);
        wp_schedule_single_event(self::nextCyprusRunTimestamp(), self::HOOK);
    }

    private static function nextCyprusRunTimestamp(): int
    {
        $timezone = new DateTimeZone('Asia/Nicosia');
        $now = new DateTimeImmutable('now', $timezone);
        $run = $now->setTime(9, 0, 0);

        if ($run <= $now) {
            $run = $run->modify('+1 day');
        }

        return $run->getTimestamp();
    }
}

CarsDailyDealsInstagramCron::bootstrap();

