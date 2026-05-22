# AutoAgora Coding Agent

Use this file as the standalone brief for an AI coding agent working on this repo.

## Mission

Make small, safe, production-minded changes to the AutoAgora WordPress/Bricks child theme. Prefer existing patterns, preserve ACF/meta contracts, and avoid broad refactors unless explicitly requested.

## Context

- Project: AutoAgora, a Cyprus used-car marketplace.
- Stack: WordPress child theme for Bricks Builder, PHP, ACF, jQuery, direct CSS/JS.
- Main product areas: car listings, filters, add/edit listing, buyer requests, dealer accounts, SEO landings, notifications, reviews, views/click tracking.
- The repo assumes CPTs/ACF fields exist in WordPress:
  - `car`
  - `buyer_request`
  - taxonomy `car_make`
- Composer dependency: `twilio/sdk`.

## Read Order

1. `AGENTS.md`
2. `AI_PROJECT_MAP.md`
3. This file
4. Open only the feature files needed for the task.

Skip by default:

- `vendor/**`
- `assets/svg/**`
- most of `simple_jsons/**`
- `.git/**`
- `.DS_Store`
- `single-car.php.bak`
- `single-car.php.disabled`

## Where To Work

- Bootstrap/global hooks: `functions.php`
- Enqueues: `includes/core/enqueue.php`
- Listings query/rendering: `includes/shortcodes/car-listings/car-listings.php`
- Listing cards: `includes/shortcodes/car-card/car-card.php`
- Filters/AJAX: `includes/shortcodes/car-filters/*`
- Add listing: `template-add-listing.php`, `includes/user-manage-listings/car-submission.php`, `includes/user-manage-listings/template-add-listing/*`
- Edit listing: `template-edit-listing.php`, `includes/user-manage-listings/template-edit-listing/*`
- Listing state: `includes/listing-state/ListingStateManager.php`
- Price insights: `includes/price-insight/*`
- Listing ranking: `includes/listing-rank/*`
- Make/model SEO landings: `includes/car-make-landings.php`, `taxonomy-car_make-landing.php`
- City landings: `includes/city-cars-landing/*`
- Auth/login/register: `includes/auth/*`, `includes/core/ajax.php`
- Accounts/my listings: `includes/user-account/*`
- Email/notifications: `includes/email/*`, `includes/notifications/listing-notifications/*`
- Admin tools: `includes/admin/*`

## Hard Rules

- Use `ListingStateManager` for active/sold/expired logic.
- Marketplace listing pages should show `listing_state = active` unless explicitly asked otherwise.
- Preserve existing meta/ACF keys.
- Preserve `car_make` hierarchy: parent = make, child = model.
- New user car listings are `pending`; buyer requests publish immediately.
- Do not inspect/edit `vendor` or large SVG library unless task requires it.
- Use existing shortcode/query helpers before adding new ones.
- Avoid broad cleanup of `functions.php`; it is a migration-heavy bootstrap file.
- Public AJAX and admin-post handlers need nonce, auth/capability, ownership, sanitization, and escaping.
- Shared buttons must use `.btn` plus a modifier from `.cursor/rules`.

## Implementation Checklist

- Identify the feature cluster before editing.
- Trace hook/shortcode/AJAX entry points.
- Patch the smallest set of files.
- Keep frontend CSS/JS scoped to the feature.
- Do not create a build step unless requested.
- If changing queries, check sort, pagination, filters, and listing state.
- If changing SEO, check title/meta/canonical/robots/schema together.
- If changing user flows, check unauthorized and non-owner behavior.

## Verification

There is no test runner. Verify with:

```powershell
php -l path\to\edited-file.php
```

When PHP is unavailable, do a careful static pass:

- no syntax mismatches
- hooks registered once
- nonce names match frontend/backend
- AJAX response shape matches JS
- escaped output
- sanitized input
- no accidental broad query

## Common Pitfalls

- Hardcoded year max appears as `2025` in validation; update all places together if changing it.
- Filter AJAX can return HTML or compact JSON card payloads; preserve both paths.
- Make/model forms may submit names, while filters/URLs use term IDs or slugs.
- Custom DB tables for reviews/views are assumed to exist.
- External services depend on constants outside the repo.

