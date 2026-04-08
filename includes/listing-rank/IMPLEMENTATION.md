# Listing Rank Implementation

This document describes the listing ranking system used on cars browse pages.

## Purpose

Default browse ordering is now `Best Match` instead of featured-first + newest.

The score is persisted on each car post as meta to keep listing queries fast.

## Stored Fields

- `listing_rank_score` (number)
- `listing_rank_updated_at` (datetime, `Y-m-d H:i:s`)
- `listing_rank_recency_bucket` (`0|1|2`)
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

- `popular_badge = 1` when engagement score is `>= 120`, else `0`
- `fresh_badge` setting was removed by product request.

## Recency Bucket

`listing_rank_recency_bucket` is precomputed and used in SQL ordering:

- `0` => posted today
- `1` => posted in last 1-3 days
- `2` => older than 3 days

Age uses `publication_date` meta when present, otherwise `post_date`.

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

- query args still pass `orderby=score`
- SQL ordering is injected via `posts_clauses` with LEFT JOINs so missing meta does not hide listings
- effective ORDER BY:
  1) `listing_rank_recency_bucket ASC` (`0`, then `1`, then `2`)
  2) `listing_rank_score DESC`
  3) `post_date DESC` tie-break

When orderby is `score`, featured-first SQL override is intentionally skipped.

## UI Defaults

Pages now default to:

- label: `Best Match`
- sort value: `orderby=score&order=DESC`

`Newest`, `Price`, `Mileage`, and `Year` sorts remain available.

## Card/UI Signals

- Price insight labels are rendered as:
  - `Great Deal`
  - `Good Deal`
  - `Fair Deal`
- `Popular` is rendered next to deal badges in the card body (`car-card-signal-badges`), horizontally.
- `Full Details` and `Extra Details` remain in the slider top-left corner.

## Manual Rebuild

Run full rank rebuild manually (one-time backfill or maintenance):

```php
do_action('bricks_child_listing_rank_rebuild');
```

## Notes

- Ranking depends on `price_insight_band`; ensure price insight rebuild runs normally.
- For larger datasets in future, consider batched refresh rather than full hourly loop.
