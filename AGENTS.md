# AutoAgora Agent Entry Point

Purpose: give any AI agent enough project context to act without rereading the whole repository. Start here, then open only the files listed for your role/task.

## Fast Identity

- Project: AutoAgora WordPress marketplace child theme for cars in Cyprus.
- Theme: Bricks child theme (`Template: bricks`) with older Astra-child code migrated into it.
- Main runtime: PHP/WordPress, Bricks Builder, ACF fields, jQuery, WordPress AJAX/admin-post flows.
- Vendor deps: Composer only requires `twilio/sdk`; `vendor/` also contains SendGrid/Twilio code. Do not inspect vendor unless debugging dependency internals.
- External services expected from constants: Twilio Verify (`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_VERIFY_SID`), Cloudflare Turnstile (`TURNSTILE_SECRET_KEY`), Resend (`RESEND_API_KEY`, `RESEND_FROM_EMAIL`, `RESEND_FROM_NAME`), Google Maps assets/API loader.
- Product: car listings, buyer requests, dealer accounts, SEO make/model and city landing pages, seller notifications, favorites, reviews, views/click tracking.

## Token-Saving Read Order

1. Read this file.
2. Read `AI_PROJECT_MAP.md` for architecture, data model, URLs, shortcodes, and dependencies.
3. Read `AI_ROLE_PLAYBOOKS.md` for your role-specific brief.
4. Read `AI_REVIEW_PM_CHECKLIST.md` before reviews, QA, planning, or risk assessment.
5. Open code only from the targeted file list below.

Skip by default:

- `vendor/**`
- `assets/svg/**` (large icon library)
- `simple_jsons/**` except a single JSON sample or make/model taxonomy debugging
- `.DS_Store`
- `.git/**`
- Old backup/disabled files unless explicitly requested: `single-car.php.bak`, `single-car.php.disabled`

## Important Existing Docs

- Standalone coding agent brief: `CODING_AGENT.md`
- Standalone reviewer agent brief: `REVIEWER_AGENT.md`
- Standalone project manager agent brief: `PROJECT_MANAGER_AGENT.md`
- Standalone marketing agent brief: `MARKETING_AGENT.md`
- Standalone SEO agent brief: `SEO_AGENT.md`
- Car filters: `includes/shortcodes/car-filters/README.md`
- Car listings shortcode: `includes/shortcodes/car-listings/README.md`
- Listing rank: `includes/listing-rank/IMPLEMENTATION.md`
- Refresh listing: `includes/user-manage-listings/refresh-listing/README.md`
- Legal/cookies: `includes/legal/README.md`
- SEO make landing source brief: `car-make-landing-pages-guide.md`
- Button system rule: `.cursor/rules`

## Project Hotspots

- Bootstrap and global hooks: `functions.php`
- Global enqueue policy: `includes/core/enqueue.php`
- Main browse/listing query: `includes/shortcodes/car-listings/car-listings.php`
- Filter UI/AJAX: `includes/shortcodes/car-filters/car-filters.php` and `includes/shortcodes/car-filters/filters/filter-base.php`
- Reusable listing card: `includes/shortcodes/car-card/car-card.php`
- Add listing backend: `includes/user-manage-listings/car-submission.php`
- Add listing template/UI: `template-add-listing.php`, `includes/user-manage-listings/template-add-listing/add-listing.js`
- Edit listing backend/UI: `template-edit-listing.php`, `includes/user-manage-listings/template-edit-listing/*`
- Listing state: `includes/listing-state/ListingStateManager.php`
- Price insights: `includes/price-insight/*`
- Ranking: `includes/listing-rank/ListingRankManager.php`
- Make/model SEO landings: `includes/car-make-landings.php`, `taxonomy-car_make-landing.php`
- City landings: `includes/city-cars-landing/*`, `template-cars-city-*.php`
- Auth/registration/login: `includes/auth/*`, `includes/core/ajax.php`
- Email wrapper: `includes/email/email-sender.php`
- Notifications: `includes/notifications/listing-notifications/*`
- Admin reports/import/export: `includes/admin/*`

## Core Product Rules

- Marketplace listings are `post_type = car`; this theme assumes the CPT, taxonomy, and ACF field groups exist in WordPress/ACF, even if not registered in this repo.
- Listing visibility is governed by `listing_state`: `active`, `sold`, `expired`. Marketplace lists should usually show active only.
- New user-submitted car listings are created as `pending`; buyer requests are published immediately.
- Makes/models use one hierarchical taxonomy: `car_make`; parent terms are makes, child terms are models. Model slugs are often `make-model`.
- `simple_jsons/*.json` contains 125 make/model data files used to sync taxonomy terms.
- Car listing images are stored in ACF/meta `car_images`. Upload flows use async upload sessions when available.
- URLs for SEO make/model pages live under `/car_make/{slug}/`. Generic browsing/filter URLs live under `/cars/` and `/cars/filter/...`.
- City pages are static templates for Nicosia, Limassol, Larnaca, and Paphos.
- Default listing sort is `score`/Best Match, not newest.
- Shared buttons must start with `.btn` and one modifier such as `.btn-primary`, `.btn-primary-gradient`, `.btn-secondary`, `.btn-success`, `.btn-danger`, or `.btn-link`.

## No-Test Reality

There is no local test runner, package script, PHPUnit config, or JS build system in this repository. Verification is mostly:

- PHP syntax checks on edited PHP files if `php` is available.
- Targeted manual reasoning through WordPress hooks/nonces/capability checks.
- Browser/manual QA on a WordPress instance when available.
- For SEO changes, inspect generated title/meta/canonical/robots/schema behavior in code and page source.

## Safe Editing Principles

- Keep changes local to the feature cluster.
- Do not refactor `functions.php` broadly unless explicitly asked; it is a dense migration bootstrap.
- Do not edit generated/bundled dependency files.
- Preserve existing ACF/meta key names unless a migration is planned.
- Treat admin-post and AJAX handlers as security-sensitive: nonce, auth, ownership/capability, sanitization, escaping.
- For query work, use existing helpers (`car_listings_build_query_args`, `car_listings_execute_query`, `ListingStateManager`) before inventing new query logic.
- For SEO landings, update config arrays and meta filters together; avoid hardcoded one-off template changes.
