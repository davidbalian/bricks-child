# AutoAgora AI Role Playbooks

Use this file to orient a specialized agent quickly. It assumes the agent has already read `AGENTS.md`.

## Coding Agent

Primary job: make small, safe changes in the correct feature cluster.

Read first:

- `AGENTS.md`
- `AI_PROJECT_MAP.md`
- relevant feature README if it exists

Default workflow:

1. Identify the feature cluster and entry file.
2. Trace hooks/shortcodes/AJAX actions before editing.
3. Preserve existing ACF/meta key contracts.
4. Patch the smallest set of files.
5. Verify with PHP syntax checks when possible.

Common task routing:

- Listing cards/UI: `includes/shortcodes/car-card/*`
- Browse query/sort/pagination: `includes/shortcodes/car-listings/*`
- Filter controls/AJAX: `includes/shortcodes/car-filters/*`
- Add listing fields/save: `template-add-listing.php`, `includes/user-manage-listings/car-submission.php`, `field-validation.php`, add-listing JS/CSS
- Edit listing fields/save: `template-edit-listing.php`, `includes/user-manage-listings/template-edit-listing/*`
- Login/register/OTP: `includes/auth/*`, `includes/core/ajax.php`
- Account settings: `includes/user-account/my-account/*`
- My listings: `includes/user-account/my-listings/*`
- SEO make/model landing: `includes/car-make-landings.php`, `taxonomy-car_make-landing.php`
- City landing: `includes/city-cars-landing/*`, `assets/js/city-cars-landing-*`, `assets/css/city-cars-landing-*`
- Ranking: `includes/listing-rank/*`
- Price insight: `includes/price-insight/*`
- Notifications: `includes/notifications/listing-notifications/*`
- Admin reports: `includes/admin/*`

Coding gotchas:

- `car_make` is hierarchical and covers both make and model.
- `ListingStateManager` is the source of truth for active/sold/expired.
- `car_listings_execute_query()` wraps important SQL filters/order clauses.
- `car_filters_ajax_filter_listings()` can return HTML or compact JSON card payloads.
- Do not broaden public AJAX access unless required.
- `functions.php` has duplicate/legacy sections; avoid sweeping cleanup.
- Year validation currently caps at 2025 in form validation and buyer request code. If the product year changes, update all matching spots together.

## Reviewer Agent

Primary job: find behavior, security, SEO, and performance regressions.

Read first:

- changed files
- `AI_REVIEW_PM_CHECKLIST.md`
- relevant feature section in `AI_PROJECT_MAP.md`

Review priorities:

- Security: nonce, capability, ownership, sanitization, escaping
- Data integrity: ACF/meta keys, listing state, taxonomy term assignment
- Query performance: postmeta joins, unbounded queries, cache usage
- SEO: canonical/robots/title/schema, duplicate indexable URLs
- UX regressions: add/edit listing, filters, pagination, sort, location modal
- Backward compatibility with existing Bricks/ACF setup

High-risk files:

- `functions.php`
- `includes/core/ajax.php`
- `includes/shortcodes/car-listings/car-listings.php`
- `includes/shortcodes/car-filters/car-filters.php`
- `includes/user-manage-listings/car-submission.php`
- `includes/user-manage-listings/template-edit-listing/edit-listing-processing.php`
- `includes/car-make-landings.php`
- `taxonomy-car_make-landing.php`
- `includes/admin/*` files that stream CSV/ZIP/image downloads

Useful review questions:

- Can a logged-out user trigger this endpoint?
- Can a logged-in user modify someone else's listing/request?
- Are sold/expired listings leaking into marketplace pages?
- Is a new query accidentally scanning all cars or all postmeta?
- Does a URL produce duplicate indexable content?
- Does JS state match server-rendered `data-atts`?
- Are numeric filters cast as numeric SQL types?

## Project Manager Agent

Primary job: turn code reality into tasks, milestones, QA scope, and risk.

Read first:

- `AGENTS.md`
- `AI_PROJECT_MAP.md`
- `AI_REVIEW_PM_CHECKLIST.md`

Feature areas to track:

- Marketplace browse and filter quality
- Seller listing creation/editing
- Buyer request marketplace
- Dealer accounts and dealer data import/export
- SEO landings for make/model and city pages
- Notification/retention loops
- Listing freshness and expiry management
- Admin reporting and operational workflows
- Trust features: seller verification, reviews, report button

Operational risks:

- No automated tests.
- CPT/ACF schemas are not fully declared in repo.
- Some DB tables are assumed to exist manually.
- Several services depend on constants outside repo.
- The repo includes migrated legacy Astra code and some disabled/commented systems.
- The current shell may not have `git` or `php` available.

Planning advice:

- Ship changes in thin vertical slices with manual QA.
- For listing/filter changes, test desktop/mobile, empty states, sorting, pagination, URL parameters, and location radius.
- For SEO changes, include source inspection and indexability checks in acceptance criteria.
- For admin tools, include capability checks and export/download validation.
- For auth/OTP/email changes, include rate limit and failure state QA.

## Marketing Agent

Primary job: understand AutoAgora's positioning and produce copy/campaigns aligned with product reality.

Product positioning:

- AutoAgora is a Cyprus-focused used car marketplace.
- It supports private sellers, dealerships, and buyers who post wanted requests.
- Core buyer value: browse real listings with filters, photos, location, seller contact options, favorites, reviews, and price/deal signals.
- Core seller value: create listings, manage them, refresh visibility, receive activity notifications, and track views/clicks.
- Core dealer value: manage multiple listings, dealer profile, verification badge, import/export tooling, metrics.

Existing copy surfaces:

- Add listing success and notes: `template-add-listing.php`
- Buyer request copy: `template-buyer-requests.php`, `template-create-buyer-request.php`, `single-buyer_request.php`
- Email notifications: `includes/notifications/listing-notifications/ListingNotificationMessageFactory.php`
- Email verification banner: `includes/notifications/email-verification-notification*`
- Make/model SEO content: `includes/car-make-landings.php`
- City SEO content: `includes/city-cars-landing/CityCarsLandingCatalog.php`
- Legal pages: `includes/legal/legal-pages.php`

Tone signals already present:

- practical, direct, marketplace-focused
- Cyprus-specific
- seller encouragement around fresh photos, clear details, and refreshing listings
- "Best Match", "Great Deal", "Good Deal", "Fair Deal", "Popular", "Full Details", "Extra Details"

Do not claim unless verified:

- number of active listings
- exact market share
- dealer count
- guaranteed sale speed
- real-time price accuracy
- official partnerships

Good marketing themes:

- "Used cars for sale in Cyprus"
- "Compare cars from dealers and private sellers"
- "Find listings in Nicosia, Limassol, Larnaca, and Paphos"
- "Post a buyer request and let sellers come to you"
- "Keep your listing fresh with refresh and activity notifications"

## SEO Expert Agent

Primary job: improve crawlability, indexability, metadata, structured data, and search-targeted content without breaking browse UX.

Read first:

- `includes/car-make-landings.php`
- `taxonomy-car_make-landing.php`
- `includes/city-cars-landing/CityCarsLandingCatalog.php`
- `car-make-landing-pages-guide.md`

SEO architecture:

- Managed make/model pages: `/car_make/{model-slug}/`
- Generic filter browsing: `/cars/` and `/cars/filter/...`
- City pages: dedicated page templates
- Make/model pages include unique title, meta description, H1, intro, FAQ, canonical, and FAQ schema.
- Generic filter URLs with equivalent SEO landing content should be noindexed or canonicalized to the landing where appropriate.

Managed make/model page config lives in one array:

- function: `autoagora_get_car_make_landing_config()`
- file: `includes/car-make-landings.php`
- each slug defines content and metadata

Meta integration points:

- WordPress title filters
- Yoast SEO filters
- Rank Math filters
- fallback manual meta output in `wp_head`
- `wp_robots` and plugin robots filters
- canonical override/removal of default canonical on custom routes

SEO checks:

- one canonical URL per page
- no conflicting Yoast/Rank Math/manual title/meta
- FAQ JSON-LD only when FAQs exist
- H1 matches intent but is not duplicated awkwardly
- changing non-make filters on landings should not create indexable duplicate pages
- changing make/model from an SEO landing should move to generic browse, not another landing
- empty/unmanaged taxonomy archives redirect or noindex as intended

Content rules:

- Use Cyprus-specific copy.
- Mention key cities where relevant: Nicosia, Limassol, Larnaca, Paphos.
- Keep price claims conservative and periodically review them.
- Avoid unsupported claims about exact market share or official relationships unless sourced.
- Prefer adding landing copy to config arrays instead of template hardcoding.

## Data/Analytics Agent

Primary job: reason about marketplace metrics, seller performance, and admin reporting.

Key data sources:

- listing views custom table and cached `total_views_count`
- contact clicks: `call_button_clicks`, `whatsapp_button_clicks`
- favorites: user meta `favorite_cars`
- seller reviews custom table
- listing rank fields
- price insight cohorts table
- admin reports in `includes/admin/cars-report-admin.php`
- click metrics in `includes/admin/listing-click-metrics.php`

Metrics caveats:

- Views exclude owners and bots in tracker logic.
- Contact click rate is `(phone + WhatsApp clicks) / total listing page views`.
- Ranking uses current cached metrics, not live recalculation per query.
- Some exports stream from live WordPress data.

## QA Agent

Primary job: produce manual test plans.

High-value test flows:

- Registration: Turnstile, Cyprus phone, OTP send, OTP verify, final account creation, duplicate phone.
- Login: phone-number login, failed login redirect, forgot password OTP, logout redirect.
- Add listing: required fields, custom make/model dropdown, location picker, image upload/order, validation errors, pending status, taxonomy terms.
- Edit listing: owner access, non-owner blocked, saved locations, image reorder/delete/add, sold/active restrictions, expired listing behavior.
- Browse: initial page load, filters, URL params, sort menu, pagination, empty results, favorites, card slider.
- SEO make landing: H1/meta/canonical/schema, listing prefilter, filters changing behavior.
- City landing: city filter, location radius, sort, filters, intro copy.
- Buyer requests: create, list, single view, contact buttons, delete by owner.
- Admin: cars report, bulk expiry, click metrics, CSV import, dealer import/export, seller review moderation.

