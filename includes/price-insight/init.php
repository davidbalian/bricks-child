<?php
/**
 * Price insight: cron + deferred rebuild after car saves.
 *
 * Manual run (e.g. from a code snippet plugin): do_action('bricks_child_car_price_insight_rebuild');
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/CarPriceInsightConfig.php';
require_once __DIR__ . '/CarPriceCohortKeyFactory.php';
require_once __DIR__ . '/CarPriceCohortStatsRepository.php';
require_once __DIR__ . '/CarPriceInsightBandResolver.php';
require_once __DIR__ . '/CarPriceInsightMetaWriter.php';
require_once __DIR__ . '/CarPriceInsightRebuildOrchestrator.php';

/**
 * @return void
 */
function bricks_child_car_price_insight_schedule_daily() {
    if (!wp_next_scheduled('bricks_child_car_price_insight_rebuild')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'bricks_child_car_price_insight_rebuild');
    }
}

add_action('after_switch_theme', 'bricks_child_car_price_insight_schedule_daily');
add_action('init', 'bricks_child_car_price_insight_schedule_daily', 30);

add_action(
    'bricks_child_car_price_insight_rebuild',
    static function () {
        CarPriceInsightRebuildOrchestrator::run();
    }
);

add_action(
    'bricks_child_car_price_insight_rebuild_deferred',
    static function () {
        CarPriceInsightRebuildOrchestrator::run();
    }
);

/**
 * Defer a full rebuild so bulk saves do not stack heavy work.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function bricks_child_car_price_insight_defer_rebuild($post_id) {
    if (CarPriceInsightRebuildOrchestrator::$running) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_type($post_id) !== 'car') {
        return;
    }
    wp_clear_scheduled_hook('bricks_child_car_price_insight_rebuild_deferred');
    wp_schedule_single_event(time() + 120, 'bricks_child_car_price_insight_rebuild_deferred');
}

add_action('save_post_car', 'bricks_child_car_price_insight_defer_rebuild', 50);
