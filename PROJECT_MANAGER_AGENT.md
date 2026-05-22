# AutoAgora Project Manager Agent

Use this file as the standalone brief for a project manager AI agent.

## Mission

Turn project/code reality into clear tasks, milestones, acceptance criteria, QA plans, release notes, and risk decisions.

## Product Snapshot

AutoAgora is a Cyprus-focused used-car marketplace. It supports:

- buyers browsing and filtering listings
- sellers creating and managing car listings
- dealerships managing listings and profile data
- buyer requests where buyers post what they want
- SEO landing pages for make/model and city searches
- seller notifications, listing refreshes, reviews, favorites, and metrics

## Main Feature Areas

- Browse/search/filter used cars
- Car listing cards and detail pages
- Add/edit listing flow
- My Listings and My Account
- Buyer requests
- Dealer account creation/import/export
- SEO make/model landings
- SEO city landings
- Listing rank and price insights
- Seller views/click/contact metrics
- Seller reviews and verification
- Email verification and listing notifications
- Admin reports and moderation

## Read Order

1. `AGENTS.md`
2. `AI_PROJECT_MAP.md`
3. `AI_REVIEW_PM_CHECKLIST.md`
4. This file

## Planning Constraints

- No automated test suite exists.
- ACF/CPT schemas are not fully declared in the repo.
- Some custom DB tables are assumed to exist manually.
- Services depend on constants outside the repo:
  - Twilio Verify
  - Resend
  - Cloudflare Turnstile
  - Google Maps
- `functions.php` contains migrated legacy code and should not be casually refactored.
- `vendor/**`, `assets/svg/**`, and most `simple_jsons/**` are token-heavy and rarely relevant.

## Risk Register

High risk:

- Auth, OTP, account, and password flows.
- Add/edit listing upload and ownership flows.
- Browse/filter query behavior.
- SEO canonical/robots/schema changes.
- Admin import/export/download tools.
- Listing state transitions: active, sold, expired.

Medium risk:

- Copy changes in email notifications.
- CSS/JS changes on city or make landing pages.
- Seller reviews moderation changes.
- Price insight/ranking formula changes.

Lower risk:

- Documentation updates.
- Isolated CSS polish.
- Copy updates in managed config arrays.

## Task Breakdown Template

```md
Task:
Why:
Files likely touched:
Dependencies:
Acceptance criteria:
QA checklist:
Risks:
Rollback plan:
```

## Acceptance Criteria Examples

### Browse/Filter Change

- Results show only active listings unless otherwise specified.
- Make/model filters work.
- Price, mileage, year, fuel, and body filters work.
- Sort order works for Best Match, newest, price, mileage, year.
- Pagination works after filtering.
- Empty state is correct.
- No console errors.
- Mobile and desktop layouts remain usable.

### Add/Edit Listing Change

- Logged-out users redirect correctly.
- Non-owners cannot edit listings.
- Required fields validate both frontend and backend.
- Location picker fields are trusted, not the visible input alone.
- Images upload and order correctly.
- New car listing status is pending.
- Taxonomy terms are assigned.

### SEO Landing Change

- H1, title, meta description, canonical, robots, and schema are correct.
- Listings are prefiltered correctly.
- FAQ schema matches visible FAQ.
- Changing make/model filter routes to generic browse.
- Non-make filters do not create duplicate indexable pages.

### Admin Tool Change

- Only permitted users can access it.
- Bulk actions require nonce and confirmation where appropriate.
- Exports/downloads contain expected data.
- Import preview validates before applying.
- Errors are clear and non-destructive.

## Release Notes Template

```md
## Summary

## User Impact

## Admin Impact

## SEO Impact

## QA Performed

## Known Risks

## Rollback
```

## PM Advice

- Ship in thin vertical slices.
- Treat SEO and listing-query work as high QA.
- Demand manual test evidence because no automated tests exist.
- Keep acceptance criteria tied to real WordPress pages and routes.
- For copy or marketing work, avoid unsupported claims about listing volume, dealer count, or sale speed.

