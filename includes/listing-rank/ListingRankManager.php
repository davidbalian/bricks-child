<?php
/**
 * Listing rank score manager.
 *
 * score = deal_score + freshness_score + engagement_score + new_boost + badge_score
 */
if (!defined('ABSPATH')) {
    exit;
}

final class ListingRankManager
{
    private const HOOK_HOURLY = 'bricks_child_listing_rank_refresh_hourly';
    private const HOOK_SINGLE = 'bricks_child_listing_rank_recompute_single';
    private const HOOK_QUEUE = 'bricks_child_listing_rank_queue_single';

    private const DEAL_SCORE = array(
        'great' => 100.0,
        'good'  => 70.0,
        'fair'  => 30.0,
        'above' => 0.0,
        'none'  => 0.0,
    );

    private const FRESH_BADGE_DAYS = 7;
    private const POPULAR_THRESHOLD = 120.0;
    private const BADGE_FULL_BONUS = 8.0;
    private const BADGE_EXTRA_BONUS = 5.0;

    public static function bootstrap(): void
    {
        add_action('init', array(__CLASS__, 'scheduleCron'), 40);
        add_action(self::HOOK_HOURLY, array(__CLASS__, 'refreshAllPublishedCars'));
        add_action(self::HOOK_SINGLE, array(__CLASS__, 'recomputeSingle'));
        add_action(self::HOOK_QUEUE, array(__CLASS__, 'queueSingleSoon'), 10, 2);
        add_action('bricks_child_listing_rank_rebuild', array(__CLASS__, 'refreshAllPublishedCars'));
        add_action('save_post_car', array(__CLASS__, 'queueAfterCarSave'), 80, 1);
    }

    public static function scheduleCron(): void
    {
        if (!wp_next_scheduled(self::HOOK_HOURLY)) {
            wp_schedule_event(time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::HOOK_HOURLY);
        }
    }

    public static function queueAfterCarSave(int $post_id): void
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (get_post_type($post_id) !== 'car') {
            return;
        }
        self::queueSingleSoon($post_id, 180);
    }

    public static function queueSingleSoon(int $post_id, int $delay_seconds = 120): void
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'car') {
            return;
        }
        $args = array($post_id);
        $next = wp_next_scheduled(self::HOOK_SINGLE, $args);
        if ($next) {
            return;
        }
        wp_schedule_single_event(time() + max(15, $delay_seconds), self::HOOK_SINGLE, $args);
    }

    public static function recomputeSingle(int $post_id): void
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'car') {
            return;
        }
        self::recomputeForCar($post_id);
    }

    public static function refreshAllPublishedCars(): void
    {
        $ids = get_posts(array(
            'post_type'      => 'car',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));
        if (empty($ids)) {
            return;
        }
        foreach ($ids as $post_id) {
            self::recomputeForCar((int) $post_id);
        }
    }

    private static function recomputeForCar(int $post_id): void
    {
        $deal = self::dealScore($post_id);
        $age_days = self::ageDays($post_id);
        $freshness = self::freshnessScore($age_days);
        $new_boost = $age_days <= 3 ? 50.0 : 0.0;
        $engagement = self::engagementScore($post_id);
        $badge_bonus = self::badgeBonus($post_id);

        $score = $deal + $freshness + $engagement + $new_boost + $badge_bonus;

        update_post_meta($post_id, 'listing_rank_score', (string) round($score, 2));
        update_post_meta($post_id, 'listing_rank_updated_at', current_time('mysql'));
        update_post_meta($post_id, 'fresh_badge', $age_days <= self::FRESH_BADGE_DAYS ? '1' : '0');
        update_post_meta($post_id, 'popular_badge', $engagement >= self::POPULAR_THRESHOLD ? '1' : '0');
    }

    private static function dealScore(int $post_id): float
    {
        $band = (string) get_post_meta($post_id, 'price_insight_band', true);
        return isset(self::DEAL_SCORE[$band]) ? self::DEAL_SCORE[$band] : 0.0;
    }

    private static function ageDays(int $post_id): int
    {
        $ts = (int) get_post_time('U', true, $post_id);
        if ($ts <= 0) {
            return 9999;
        }
        return (int) floor((time() - $ts) / DAY_IN_SECONDS);
    }

    private static function freshnessScore(int $age_days): float
    {
        if ($age_days <= 3) {
            return 40.0;
        }
        if ($age_days <= 7) {
            return 25.0;
        }
        if ($age_days <= 14) {
            return 10.0;
        }
        return 0.0;
    }

    private static function engagementScore(int $post_id): float
    {
        $phone = absint(get_post_meta($post_id, 'call_button_clicks', true));
        $wa = absint(get_post_meta($post_id, 'whatsapp_button_clicks', true));
        $views = absint(get_post_meta($post_id, 'total_views_count', true));
        $contacts = $phone + $wa;
        return ($contacts * 20.0) + ($views * 0.2);
    }

    private static function badgeBonus(int $post_id): float
    {
        $bonus = 0.0;
        $full = get_post_meta($post_id, 'fulldetailsbadge', true);
        $extra = get_post_meta($post_id, 'extradetailsbadge', true);
        if (!empty($full)) {
            $bonus += self::BADGE_FULL_BONUS;
        }
        if (!empty($extra)) {
            $bonus += self::BADGE_EXTRA_BONUS;
        }
        return $bonus;
    }
}

ListingRankManager::bootstrap();
