# Listing Rank Implementation

This document describes the listing ranking system used on cars browse pages.

## Purpose

Default browse ordering is now `Best Match` (`score DESC`) instead of featured-first + newest.

The score is persisted on each car post as meta to keep listing queries fast.

## Stored Fields

- `listing_rank_score` (number)
- `listing_rank_updated_at` (datetime, `Y-m-d H:i:s`)
- `fresh_badge` (`'1'` or `'0'`)
- `popular_badge` (`'1'` or `'0'`)

## Formula

```
score = deal_score + freshness_score + engagement_score + new_boost + badge_bonus
```

### Deal score (`price_insight_band`)

- `great` => `100`
- `good` => `70`
- `fair` => `30`
- `above` / `none` / unknown => `0`

### Freshness score (age in days)

- `0-3` days => `+40`
- `4-7` days => `+25`
- `8-14` days => `+10`
- `15+` days => `+0`

### New boost

- `+50` for first `0-3` days

### Engagement score

```
engagement_score = (phone_clicks + whatsapp_clicks) * 20 + total_views * 0.2
```

Meta keys:

- phone: `call_button_clicks`
- whatsapp: `whatsapp_button_clicks`
- views: `total_views_count`

### Badge bonus

- `fulldetailsbadge` => `+8`
- `extradetailsbadge` => `+5`

## Badge Flags

- `fresh_badge = 1` when listing age is `<= 7` days, else `0`
- `popular_badge = 1` when engagement score is `>= 120`, else `0`

## Runtime + Scheduling

Main class: `includes/listing-rank/ListingRankManager.php`

### Registered hooks

- `save_post_car` => queue single-car recompute
- `bricks_child_listing_rank_recompute_single` => recompute one listing
- `bricks_child_listing_rank_refresh_hourly` => hourly recompute for all published cars
- `bricks_child_listing_rank_rebuild` => manual full rebuild hook
- `bricks_child_listing_rank_queue_single` => queue/debounce one listing

### Event-triggered recomputes

Single-car recompute is queued when:

- car is saved
- phone click counter increments
- WhatsApp click counter increments
- total views cache updates

## Query Behavior

`score` is now a first-class sort mode in listings/filter query args:

- `meta_key = listing_rank_score`
- `orderby = meta_value_num`
- `order = DESC` for `Best Match`

When orderby is `score`, featured-first SQL override is intentionally skipped.

## UI Defaults

Pages now default to:

- label: `Best Match`
- sort value: `orderby=score&order=DESC`

`Newest`, `Price`, `Mileage`, and `Year` sorts remain available.

## Manual Rebuild

Run full rank rebuild manually (one-time backfill or maintenance):

```php
do_action('bricks_child_listing_rank_rebuild');
```

## Notes

- Ranking depends on `price_insight_band`; ensure price insight rebuild runs normally.
- For larger datasets in future, consider batched refresh rather than full hourly loop.
