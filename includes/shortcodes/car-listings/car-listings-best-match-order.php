<?php
/**
 * Shared SQL ORDER BY for “Best match” / score sort.
 *
 * Mirrors ListingRankManager::ageDays() + recencyBucket() using publication_date meta
 * (fallback post_date_gmt), not listing_rank_recency_bucket — so “today” is correct even
 * before hourly rank cron updates stored meta.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ORDER BY fragment: live recency bucket ASC, rank DESC, post_date DESC.
 *
 * Required JOINs on the same query:
 * - rank_meta: postmeta listing_rank_score
 * - listing_pub_meta: postmeta publication_date
 *
 * @param string $posts_ref Posts table reference (e.g. wp_posts.ID context: `{$wpdb->posts}` or alias `p`).
 */
function car_listings_best_match_orderby_sql(string $posts_ref): string
{
    $pub = 'listing_pub_meta';
    $rank = 'rank_meta';
    $effective = "COALESCE(STR_TO_DATE(NULLIF(TRIM({$pub}.meta_value), ''), '%Y-%m-%d %H:%i:%s'), {$posts_ref}.post_date_gmt)";
    $age_days = "FLOOR(TIMESTAMPDIFF(SECOND, {$effective}, UTC_TIMESTAMP()) / 86400)";

    return "(CASE WHEN ({$age_days}) <= 0 THEN 0 WHEN ({$age_days}) <= 3 THEN 1 ELSE 2 END) ASC, "
        . "CAST(COALESCE(NULLIF({$rank}.meta_value, ''), '0') AS DECIMAL(12,2)) DESC, "
        . "{$posts_ref}.post_date DESC";
}
