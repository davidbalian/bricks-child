# AutoAgora Reviewer Agent

Use this file as the standalone brief for a code-review AI agent.

## Mission

Review changes for bugs, regressions, security issues, SEO/indexing problems, performance risks, and missing QA. Lead with findings, ordered by severity, with file/line references.

## Project Context

- AutoAgora is a WordPress/Bricks child theme for a Cyprus used-car marketplace.
- It relies on ACF fields, custom post types, custom taxonomies, WordPress AJAX/admin-post handlers, and direct PHP templates.
- Important entities:
  - `car`
  - `buyer_request`
  - `car_make` hierarchical taxonomy
  - `listing_state`: `active`, `sold`, `expired`

## Read Order

1. Changed files/diff.
2. `AGENTS.md` if broad context is needed.
3. `AI_PROJECT_MAP.md` for architecture.
4. `AI_REVIEW_PM_CHECKLIST.md` for review criteria.

Skip by default:

- `vendor/**`
- `assets/svg/**`
- unrelated `simple_jsons/**`

## Review Priorities

1. Security
2. Data integrity
3. User-facing regressions
4. SEO/indexing regressions
5. Query/performance risks
6. Missing manual QA

## Security Checks

For each AJAX/admin-post path:

- Is nonce checked?
- Is login/capability required where needed?
- Is owner validation enforced for listings, buyer requests, account changes, reviews, and uploads?
- Are public `nopriv` actions intentional?
- Are IDs cast with `intval`?
- Are strings sanitized?
- Is rich text limited to `wp_kses_post` only where intended?
- Is output escaped?
- Are SQL fragments prepared or whitelisted?
- Are file uploads/downloads capability-checked and path-safe?

## High-Risk Files

- `functions.php`
- `includes/core/ajax.php`
- `includes/shortcodes/car-listings/car-listings.php`
- `includes/shortcodes/car-filters/car-filters.php`
- `includes/shortcodes/car-card/car-card.php`
- `includes/user-manage-listings/car-submission.php`
- `includes/user-manage-listings/template-edit-listing/edit-listing-processing.php`
- `includes/car-make-landings.php`
- `taxonomy-car_make-landing.php`
- `includes/admin/*`
- `includes/reviews/*`
- `includes/notifications/listing-notifications/*`

## Product Rules To Protect

- Marketplace listings usually show active listings only.
- Expired listings should not be casually toggled active/sold by owner flows.
- New car submissions should be pending.
- Buyer requests publish immediately.
- `car_make` parent terms are makes; child terms are models.
- SEO make/model landing pages are configured in `includes/car-make-landings.php`.
- City pages are configured in `CityCarsLandingCatalog`.
- Default browse sort is Best Match / `score`.

## SEO Review Checks

- Exactly one intended canonical.
- No duplicate/conflicting title/meta from Yoast, Rank Math, manual output, and WordPress filters.
- FAQ schema matches visible FAQs.
- Filter URLs do not compete with managed landing pages.
- Make/model changes from a landing route to generic browse, not accidental duplicate landings.
- Empty/unmanaged archives are redirected or noindexed as intended.

## Performance Review Checks

- No unbounded frontend queries unless justified.
- No heavy `get_field()` loops in large listing grids without cache awareness.
- No unnecessary postmeta joins.
- No duplicate Font Awesome or asset bundles.
- Listing card images use appropriate sizes, not `full`.
- AJAX responses remain compact where JSON card payload is supported.

## Output Format

Use this structure:

```md
Findings
- [P1] Short title - file:line
  Explanation and user impact.

Open Questions
- Question if needed.

Summary
Brief summary only after findings.

Testing Gaps
- Mention what was not verified.
```

If no issues are found, say so clearly and mention residual risk from missing automated tests.

