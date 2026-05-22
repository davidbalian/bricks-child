# AutoAgora Project Map

This is the dense technical map for future agents. It favors pointers, contracts, and gotchas over prose.

## Stack And Runtime

- WordPress child theme for Bricks Builder.
- PHP is direct theme code; no framework, no build step.
- Frontend JS is mostly jQuery plus vanilla modules in `assets/js` and feature folders.
- CSS is direct theme/feature CSS, with shared variables and `.btn` classes in `style.css`.
- ACF is assumed. Many fields use `get_field()`/`update_field()`.
- CPTs/taxonomies are assumed to exist externally or via WordPress config:
  - `car`
  - `buyer_request`
  - taxonomy `car_make`
- Composer dependency: `twilio/sdk`.

## Root Files

- `functions.php`: bootstraps includes, global hooks, taxonomy sync, Bricks builder filters, legacy AJAX.
- `style.css`: theme header, CSS variables, shared button system, global styling.
- `template-add-listing.php`: add car listing page.
- `template-edit-listing.php`: edit existing car listing page.
- `template-buyer-requests.php`: buyer request index.
- `template-create-buyer-request.php`: buyer request form.
- `single-buyer_request.php`: single buyer request display.
- `taxonomy-car_make-landing.php`: managed make/model SEO landing template.
- `template-cars-city-{nicosia,limassol,larnaca,paphos}.php`: city landing templates.
- `template-test-cars.php`: large browse/test template; inspect only when debugging that exact route.
- `404.php`: branded not-found page.
- `csv_analysis.php`: standalone-ish CSV analysis/import helper.
- `car-make-landing-pages-guide.md`: original SEO implementation brief.

## Bootstrap Flow

`functions.php` loads:

- vendor autoload
- listing state
- Google Maps/core redirects
- listing submission, buyer request submission, my account/listings
- enqueue/image/async upload core
- auth, registration, login/logout, access control
- email + verification
- shortcodes for favorites, gallery, forgot password, seller verification, dealership message
- admin pages/reports/accounts/click metrics/daily deals/reviews
- notifications, legal pages, make/city landings
- views counter, seller reviews, refresh listing, reminders
- shared button/card/listing/filter shortcodes
- price insight and ranking
- CSV import
- taxonomy sync/admin tooling

Avoid duplicate bootstrapping when adding new includes. Put feature bootstrap in a small `init.php` where local patterns already do that.

## Data Model

### Car Post Type

Common ACF/meta fields used across the theme:

- identity/spec: `make`, `model`, `year`, `mileage`, `price`
- location: `car_city`, `car_district`, `car_latitude`, `car_longitude`, `car_address`
- mechanical: `engine_capacity`, `fuel_type`, `transmission`, `body_type`, `drive_type`, `hp`
- appearance: `exterior_color`, `interior_color`
- details: `description`, `number_of_doors`, `number_of_seats`, `motuntil`, `extras`, `vehiclehistory`, `numowners`, `isantique`
- media: `car_images`
- marketplace state: `listing_state`
- publish/recency: `publication_date`, post dates, refresh meta
- badges: `fulldetailsbadge`, `extradetailsbadge`, `popular_badge`, `is_featured`
- price/rank: `price_insight_band`, `listing_rank_score`, `listing_rank_updated_at`, `listing_rank_recency_bucket`
- metrics: `call_button_clicks`, `whatsapp_button_clicks`, `total_views_count`, `unique_views_count`

### Listing State

File: `includes/listing-state/ListingStateManager.php`

- states: `active`, `sold`, `expired`
- public helpers: `resolve_state`, `assign_state`, `is_marked_sold`, `is_marked_expired`, `meta_query_active_only`
- Marketplace pages should hide sold/expired unless explicitly asked.
- Expired listings cannot be toggled sold/available from normal owner actions.

### Make/Model Taxonomy

- Taxonomy: `car_make`
- Parent terms = makes.
- Child terms = models.
- Sync source: `simple_jsons/*.json` with `make` and `models`.
- Sync tools live in `functions.php` under "Car Make/Model Taxonomy Management".
- Add listing form uses term names as values because backend lookup expects names.
- Filter/listing URLs and SEO landings use slugs.

### Buyer Requests

Post type: `buyer_request`

Fields:

- `buyer_make`
- `buyer_model`
- `buyer_year`
- `buyer_price`
- `buyer_description`

Creation file: `includes/user-manage-listings/buyer-request-submission.php`

- authenticated only
- creates published posts immediately
- required: make, year, price
- year range currently 1948-2025
- appends post ID to slug to avoid collisions

### User Meta

Common keys:

- `phone_number`
- `first_name`, `last_name`
- `favorite_cars` array of car post IDs
- email verification/preference fields in `includes/email/*` and `includes/notifications/*`
- dealership profile fields managed in `includes/user-account/my-account/*`

Roles:

- `dealership`
- `client`
- `subscriber`

See `includes/auth/roles.php`.

## Major Feature Clusters

### Listings Browse

Files:

- `includes/shortcodes/car-listings/car-listings.php`
- `includes/shortcodes/car-listings/car-listings-query-cache.php`
- `includes/shortcodes/car-card/car-card.php`
- `includes/shortcodes/car-card/car-card-listing-payload.php`

Shortcode: `[car_listings]`

Important attributes:

- `posts_per_page`
- `layout`: `grid`, `carousel`, `vertical`
- `infinite_scroll`
- `featured`
- `favorites`
- `user_id`
- `author="current"`
- `orderby`: `score`, `date`, `price`, `mileage`, `year`
- `order`
- `show_sold`
- `id`
- `filter_group`
- `card_type="car_card"`
- `default_make_slug`
- `default_model_slug`
- `default_car_city`

Query rules:

- default order is `score DESC`
- active listings only unless `show_sold="true"`
- score ordering uses SQL clause injection for rank fields
- card rendering primes meta cache when possible
- AJAX JSON card payload exists for faster client rendering

### Filters

Files:

- `includes/shortcodes/car-filters/car-filters.php`
- `includes/shortcodes/car-filters/filters/filter-base.php`
- individual filters in `includes/shortcodes/car-filters/filters/`
- frontend JS: `includes/shortcodes/car-filters/car-filters.js`
- JSON card renderer: `includes/shortcodes/car-filters/car-listing-cards-render.js`

Shortcode: `[car_filters]`

Filters: `make`, `model`, `price`, `mileage`, `year`, `fuel`, `body`

Modes:

- `ajax`: updates target listings on same page
- `redirect`: builds URL and navigates

Landing behavior:

- make/model landing pages preserve landing make/model defaults
- city landing pages preserve `default_car_city`
- changing make/model from an SEO landing should route to generic `/cars/` or `/cars/filter/...`, not another `/car_make/` landing

AJAX actions:

- `car_filters_get_models`
- `car_filters_filter_listings`
- `car_filters_get_available_options`
- `car_filters_resolve_slug`

Location radius filters use `car_latitude`/`car_longitude` meta with SQL Haversine clauses.

### Add/Edit Listing

Files:

- `template-add-listing.php`
- `template-edit-listing.php`
- `includes/user-manage-listings/car-submission.php`
- `includes/user-manage-listings/field-validation.php`
- `includes/user-manage-listings/template-add-listing/*`
- `includes/user-manage-listings/template-edit-listing/*`
- `includes/core/async-uploads.php`
- `includes/core/image-optimization.php`

Add listing:

- requires login; redirects to signin with `selling_car=1`
- form action: `admin-post.php`, action `add_new_car_listing`
- backend creates `car` post as `pending`
- requires images and trusted hidden location fields
- uses async upload session if present, otherwise traditional upload
- validates dropdown fields against whitelist
- assigns `car_make` taxonomy terms by make/model names
- recalculates detail badges after save

Edit listing:

- requires login and ownership
- redirects non-owner/bad IDs to `/my-listings/`
- reuses image optimization and custom dropdown styling
- JS handles saved locations, location picker, image ordering, numeric formatting

### Auth And Accounts

Files:

- `includes/auth/registration.php`
- `includes/auth/registration-form.php`
- `includes/auth/login-logout.php`
- `includes/auth/access-control.php`
- `includes/core/ajax.php`
- `includes/user-account/my-account/*`
- `includes/user-account/my-listings/*`

Rules:

- registration shortcode: `[custom_registration]`
- phone OTP uses Twilio Verify and Cloudflare Turnstile
- only Cyprus `+357`/`357` phone numbers can trigger registration OTP
- final registration requires a transient proving OTP passed recently
- username is phone-based; placeholder email is generated
- all new registered users become `client`
- non-admins are blocked from backend and admin bar is hidden except admins

### Email And Notifications

Files:

- `includes/email/email-sender.php`
- `includes/email/email-verification.php`
- `includes/email/email-verification-init.php`
- `includes/notifications/email-verification-notification.php`
- `includes/notifications/listing-notifications/*`

Provider:

- Resend wrapper function: `send_app_email($to, $subject, $html, $text = '')`

Listing notification triggers include:

- listing published
- contact click milestones
- view milestones: 20, 50, 100, 150
- reminder emails for stale listings

Preferences are per-user and managed from account settings.

### Ranking And Price Insights

Files:

- `includes/price-insight/*`
- `includes/listing-rank/ListingRankManager.php`
- `includes/listing-rank/IMPLEMENTATION.md`

Price insight:

- daily scheduled rebuild
- deferred rebuild after car saves
- cohort minimum `MIN_COHORT_N = 5`
- buckets: mileage by 50,000 km, year by 5-year bands, engine bins 1.4/2.0/3.0 L
- bands: `great`, `good`, `fair`, `above`, `none`

Listing rank:

- stored as `listing_rank_score`
- formula: deal score + freshness + engagement + new boost + badge bonus
- engagement uses phone clicks, WhatsApp clicks, total views
- default browse ordering: recency bucket asc, rank score desc, post date desc

### SEO Make/Model Landings

Files:

- `includes/car-make-landings.php`
- `taxonomy-car_make-landing.php`
- `assets/css/car-make-landing.css`
- `assets/js/city-cars-landing-faq.js` for FAQ accordion behavior

Managed slugs are configured in `autoagora_get_car_make_landing_config()`. Each entry includes:

- slug
- make/model names and slugs
- title
- meta description
- H1
- canonical
- intro paragraphs
- FAQs

SEO outputs:

- document title filters
- Yoast filters
- Rank Math filters
- manual meta/canonical/robots output fallback
- FAQ JSON-LD
- custom template routing
- redirects empty unmanaged car_make archives where appropriate

Do not duplicate SEO content in templates. Add/update content in the config array.

### City Landings

Files:

- `includes/city-cars-landing/CityCarsLandingCatalog.php`
- `includes/city-cars-landing/render-city-cars-landing.php`
- `includes/city-cars-landing/city-cars-landing-init.php`
- `template-cars-city-nicosia.php`
- `template-cars-city-limassol.php`
- `template-cars-city-larnaca.php`
- `template-cars-city-paphos.php`
- CSS/JS in `assets/css/city-cars-landing-*` and `assets/js/city-cars-landing-*`

Supported cities:

- Nicosia
- Limassol
- Larnaca
- Paphos

Each page renders:

- filters bar
- location radius modal
- sort menu
- `car_card` grid filtered by `car_city`
- intro SEO copy

### Seller Reviews

Files:

- `includes/reviews/seller-reviews-database.php`
- `includes/reviews/seller-reviews-handler.php`
- `includes/reviews/seller-reviews-ajax.php`
- `includes/shortcodes/seller-reviews/*`
- `includes/admin/seller-reviews-admin.php`

Storage:

- custom DB table `$wpdb->prefix . 'seller_reviews'`
- table creation appears manual/assumed, not continuously created
- supports guest reviewer email column migration
- reviews are pending until admin approval

Shortcode: `[seller_reviews]`

### Views And Contact Metrics

Views:

- `includes/views-counter/views-database.php`
- `includes/views-counter/views-tracker.php`
- shortcodes in `includes/shortcodes/car-views-counter/car-views-counter.php`
- custom table expected for view records
- cached meta: `total_views_count`, unique views cache

Contact clicks:

- phone: `includes/shortcodes/car-single-call-button/car-single-call-button.php`
- WhatsApp: `includes/shortcodes/car-single-call-button/car-single-whatsapp-button.php`
- buyer request buttons also tracked
- admin reporting: `includes/admin/listing-click-metrics.php`

### Admin Tools

Important admin files:

- car report and bulk expiry: `includes/admin/cars-report-admin.php`, `cars-report-bulk-expire-manager.php`
- click metrics: `includes/admin/listing-click-metrics.php`
- CSV car import: `includes/admin/csv-car-import.php`
- dealership account creation: `includes/admin/dealership-accounts.php`
- dealership import/export: `includes/admin/dealership-import/*`, `includes/admin/dealership-export/*`
- daily deals/social image export: `includes/admin/cars-daily-deals-*`
- seller review moderation: `includes/admin/seller-reviews-admin.php`
- favorites user column: `includes/admin/user-favorites-column.php`

Capabilities are generally `manage_options`; verify before changing.

## URLs And Routes

Known front-end surfaces:

- `/cars/`: main browse/listing destination
- `/cars/filter/...`: generic filtered browse route from rewrite rules in `includes/car-make-landings.php`
- `/car_make/{slug}/`: taxonomy/SEO make-model landings
- `/my-account`
- `/my-listings`
- `/add-listing`
- `/edit-listing?car_id={id}`
- `/buyer-requests`
- `/create-buyer-request`
- `/signin`
- `/register`
- `/forgot-password`
- `/terms-of-service`
- `/privacy-policy`
- city pages using page templates for Nicosia/Limassol/Larnaca/Paphos

## Frontend Style System

- Global CSS variables live at top of `style.css`.
- Shared button system is mandatory; see `.cursor/rules`.
- Avoid creating new legacy button classes.
- Feature CSS is usually loaded conditionally by shortcode/template code.
- Font Awesome may be loaded globally except on browse/light contexts where Bricks FA is used to avoid duplicate payload.
- The project has some mojibake in older docs/code comments. Keep new files ASCII unless editing an existing non-ASCII string intentionally.

## Validation And Security Contracts

For AJAX:

- register `wp_ajax_*` and `wp_ajax_nopriv_*` only when public access is intended
- always check nonce
- sanitize all input
- escape all output
- check ownership for user-owned resources

For admin-post:

- only POST where state changes are involved
- nonce required
- login/capability required
- owner/capability check before edit/delete
- redirect safely

For SQL:

- prefer `$wpdb->prepare`
- whitelist ORDER BY fragments
- be cautious with extra postmeta joins; listing pages are performance-sensitive

## Verification Surface

No automated suite exists. Useful checks after edits:

- PHP syntax: `php -l path/to/file.php`
- Search for duplicate hook/action names
- Load affected WordPress page and inspect console/network
- For SEO: view source for title/meta/canonical/robots/schema
- For filters: test initial URL params, AJAX change, pagination, sort, and clear
- For add/edit: test nonce, unauthorized access, owner access, image upload path, taxonomy assignment

