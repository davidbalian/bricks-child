# Buyer Features Update

## Summary

Added buyer-focused marketplace features for retention, discovery, and confidence:

- Search alerts
- Similar cars
- Price history
- Recently reduced prices
- Compare cars
- New listings since last visit
- Dealer trust indicators
- More visible deal-score context on listing cards

## New Shortcodes

Use these shortcodes in Bricks templates or WordPress pages:

- `[search_alert_form]`  
  Lets verified-email users save a search and receive email alerts when newly published active cars match their filters.

- `[similar_cars]`  
  Shows active cars with the same make and similar price/year range for the current listing.

- `[price_history]`  
  Shows tracked listing price changes when at least two prices are recorded.

- `[price_drop_cars]`  
  Shows active listings with recent price reductions.

- `[new_listings_since_last_visit]`  
  Shows a repeat-visit prompt with the count of cars published since the visitor last viewed the page containing this shortcode.

- `[dealer_trust_indicators]`  
  Shows seller signals such as active listings, member since, response signal, and average views per listing.

- `[compare_button]`  
  Renders a compare button for the current car.

- `[compare_cars]`  
  Renders a control to reopen the compare tray.

## Card Integrations

- Reusable car cards now include a Compare button.
- Legacy listing cards now include a Compare button.
- AJAX-rendered filter cards now preserve compare data.
- Deal badges now show below-typical percentage when price insight median data exists.

## Data Notes

- Price history is tracked when the `price` post meta is added or updated for `car` posts.
- Price-drop listings depend on tracked price reductions after this update is deployed.
- Search alerts require users to have a verified email, using the existing email verification and `send_app_email()` flow.

## Suggested Placement

- Single listing page: `[similar_cars]`, `[price_history]`, `[dealer_trust_indicators]`
- Cars browse page: `[search_alert_form]`, `[new_listings_since_last_visit]`
- Price drops page: `[price_drop_cars]`
- Header/account utility area: `[compare_cars]`

## Verification

Passed:

- `php -l functions.php`
- `php -l includes/buyer-features/init.php`
- `php -l includes/search-alerts/init.php`
- `php -l includes/shortcodes/car-card/car-card.php`
- `php -l includes/shortcodes/car-card/car-card-listing-payload.php`
- `php -l includes/shortcodes/car-listings/car-listings.php`
- Bundled Node syntax check for `includes/buyer-features/buyer-features.js`
- Bundled Node syntax check for `includes/shortcodes/car-filters/car-listing-cards-render.js`

Manual WordPress/Bricks QA is still needed on a running site.

## Loading-State Hotfix

After the buyer feature rollout, `/cars/` could remain in a loading state if a frontend render error or infinite-scroll AJAX error occurred. The listing/filter scripts now:

- Catch card-render errors instead of leaving the loading overlay active.
- Hide the infinite-scroll loader on AJAX failure.
- Keep the infinite-scroll sentinel hidden while idle so `/cars/` does not look like it is permanently loading.
- Add console lifecycle logs under `[AutoAgora listings]` for infinite-scroll init/load/success/error.
- Guard compare storage and DOM rendering so compare failures do not block listings.

## Rollout Isolation Notes

- Compare cars was isolated as the failing rollout slice on June 12, 2026.
- Commit `73dec07 Enable compare cars feature` was reverted by `1f7e675 Revert "Enable compare cars feature"`.
- Suspected cause: the compare `MutationObserver` watched the full document and called `updateButtons()`, while `updateButtons()` rewrote compare button text/classes. With compare buttons on listing cards, that could repeatedly trigger DOM mutations and keep `/cars/` in a loading/frozen state.
- Compare remains disabled in registrations/card integrations until it is re-enabled for another controlled test.
