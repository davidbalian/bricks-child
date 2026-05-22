# AutoAgora Review And PM Checklist

Use this for code reviews, QA planning, release planning, and risk triage.

## Release Gate Checklist

- [ ] Security-sensitive endpoints have nonce checks.
- [ ] Logged-in ownership checks exist for user-owned listings/requests.
- [ ] Admin tools use `manage_options` or an intentional capability.
- [ ] Public AJAX endpoints are intentionally public.
- [ ] Inputs are sanitized and outputs escaped.
- [ ] Listing pages hide non-active listings unless intended.
- [ ] Existing ACF/meta keys are preserved.
- [ ] Make/model taxonomy behavior remains hierarchical.
- [ ] Filters, sorting, and pagination agree between server-rendered HTML and AJAX state.
- [ ] SEO pages emit correct title, meta description, canonical, robots, and schema.
- [ ] No broad scan of `vendor`, `assets/svg`, or all postmeta was added unnecessarily.
- [ ] Manual QA plan exists because there is no automated test suite.

## Security Checklist

For every `wp_ajax_*`, `wp_ajax_nopriv_*`, or `admin_post_*` path:

- Nonce is checked.
- User auth requirement is explicit.
- Ownership/capability check is explicit.
- POST-only is required for mutations.
- IDs are cast with `intval`.
- Text is sanitized with `sanitize_text_field`, `sanitize_key`, `sanitize_email`, etc.
- HTML input is limited to `wp_kses_post` only where rich text is intended.
- Redirects use `wp_safe_redirect` when possible.
- SQL uses `$wpdb->prepare` or whitelisted fragments.
- File downloads/uploads validate path, type, and capability.

Known sensitive areas:

- `includes/core/ajax.php`: OTP, old listing filter, favorite/sold toggles.
- `includes/user-manage-listings/car-submission.php`: user uploads and post creation.
- `includes/user-manage-listings/template-edit-listing/edit-listing-processing.php`: owner edits.
- `includes/admin/cars-daily-deals-download-handlers.php`: download responses.
- `includes/admin/dealership-import/*`: CSV upload/preview/apply.
- `includes/admin/dealership-export/*`: CSV stream.
- `includes/reviews/seller-reviews-handler.php`: public review submission/admin moderation.
- `includes/shortcodes/report-button/report-handler.php`: report submission and email.

## Performance Checklist

High-traffic pages are likely `/cars/`, `/car_make/*`, city pages, and homepage filters.

Watch for:

- unbounded `posts_per_page => -1` on frontend requests
- multiple `get_field()` calls inside listing card loops
- extra postmeta joins in filters
- taxonomy queries that accidentally include parent makes only when models are needed
- missing `update_postmeta_cache`/thumbnail cache before rendering grids
- loading Font Awesome or large CSS/JS bundles twice
- image sizes using `full` on listing cards
- AJAX returning huge HTML when compact JSON is available

Existing performance helpers:

- `car_card_get_meta_value()` uses meta cache.
- `car_card_best_image_size_for_attachment()` prefers card-sized images.
- `car_card_build_listing_json_payload()` supports compact AJAX card data.
- `car_listings_execute_query()` wraps SQL order/state clauses.
- `car-listings-query-cache.php` caches some listing query results.
- `car_filters_perf_log` can be enabled by filter for profiling.

## SEO Checklist

For make/model landing changes:

- Update `autoagora_get_car_make_landing_config()`, not only the template.
- Confirm `title`, `meta_description`, `h1`, `canonical`, `intro`, and `faqs`.
- Confirm FAQ schema reflects visible FAQs.
- Confirm canonical points to `/car_make/{slug}/`.
- Confirm equivalent generic filter URLs do not compete in the index.
- Confirm filters that change make/model leave the landing flow as intended.

For city pages:

- Update `CityCarsLandingCatalog`.
- Confirm H1 and intro copy match city.
- Confirm `default_car_city` filters listings.
- Confirm further filtering goes through intended browse behavior.

For generic browse:

- Avoid indexable thin combinations if adding new filter URL patterns.
- Ensure sorted/paginated/filtered URLs do not create duplicate canonical conflicts.

## Data Integrity Checklist

Listings:

- New listings should be `pending`.
- `post_author` should remain the original owner after admin edits.
- Images should remain attached through `car_images`.
- Trashing/deleting a car deletes related images by current hook behavior. Be careful changing this.
- Detail badges should update when relevant fields change.
- `listing_state` should be assigned and respected.

Taxonomy:

- `car_make` terms should include both make and model term IDs on car posts.
- Syncing taxonomy from JSON can delete/recreate terms. Treat this as destructive operationally.
- Add listing form values use make/model names; filter URLs use slugs/term IDs.

Buyer requests:

- Buyer requests publish immediately.
- Slug should include post ID to avoid collisions.
- Owners only can delete their own buyer requests.

Reviews:

- Seller reviews table is assumed to exist.
- Reviews require moderation before public display.
- Duplicate review prevention uses reviewer ID and/or reviewer email depending mode.

## Product/PM Risk Register

High risk:

- No automated tests.
- WordPress/ACF schema not codified in repo.
- Custom DB tables are assumed/manual in some systems.
- `functions.php` is a large mixed migration file.
- Date/year validation contains hardcoded upper year `2025`.
- Public pages depend on external services/assets.

Medium risk:

- Multiple SEO plugins are supported through filters; conflict possible.
- Old disabled systems remain in comments and backup files.
- Some docs/comments contain mojibake from encoding conversions.
- Listing queries are complex and performance-sensitive.
- Admin reports/export features touch live operational data.

Lower risk:

- Copy-only updates to managed landing config.
- CSS-only tweaks in isolated feature CSS.
- Shortcode documentation updates.

## Acceptance Criteria Templates

### Listing Browse/Filters

- Initial results render expected active listings.
- Make/model, price, mileage, year, fuel, and body filters work.
- Sort options update order and URL/state.
- Pagination works after filtering.
- Empty state is clear.
- Sold/expired listings are hidden unless explicitly included.
- No console errors.

### Add/Edit Listing

- Unauthorized users redirect correctly.
- Non-owners cannot edit/delete.
- Required fields block submission.
- Location must come from hidden picker fields.
- Images upload and order correctly.
- Created listing is pending.
- Taxonomy terms are assigned.
- Listing appears in My Listings with correct state.

### SEO Landing

- Correct H1 and intro/FAQ visible.
- Correct title/meta/canonical/robots in source.
- FAQ JSON-LD exists and validates structurally.
- Listings are prefiltered by make/model or city.
- Non-make filters work without unintended canonical/indexing changes.
- Make/model changes route to generic browse.

### Auth/Notifications

- OTP only accepts Cyprus phone numbers.
- Turnstile required for OTP send.
- Rate limit failure state is understandable.
- Final registration fails without recent OTP transient.
- Email send gracefully fails when constants missing.
- User can update notification preferences.

### Admin Tool

- Only permitted users can access.
- Bulk actions require confirmation/nonce.
- Downloads have correct file type/content.
- CSV import validates before applying.
- Data changes are visible in relevant reports.

## Known Manual Commands

If available:

```powershell
php -l functions.php
php -l includes/path/to/edited-file.php
```

Repository lacks a test runner. If a future agent adds one, document it here and in `AGENTS.md`.

