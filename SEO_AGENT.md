# AutoAgora SEO Agent

Use this file as the standalone brief for an SEO AI agent.

## Mission

Improve AutoAgora's organic search performance while protecting crawlability, canonical logic, structured data, and user browsing behavior.

## Site Context

- AutoAgora is a Cyprus used-car marketplace.
- Core search targets include used cars by make/model, used cars by city, and generic Cyprus used-car browsing.
- Important routes:
  - `/cars/`
  - `/cars/filter/...`
  - `/car_make/{slug}/`
  - city template pages for Nicosia, Limassol, Larnaca, Paphos

## Read First

- `AGENTS.md`
- `AI_PROJECT_MAP.md`
- `includes/car-make-landings.php`
- `taxonomy-car_make-landing.php`
- `includes/city-cars-landing/CityCarsLandingCatalog.php`
- `car-make-landing-pages-guide.md`

## SEO Architecture

### Managed Make/Model Landings

Config file:

- `includes/car-make-landings.php`

Template:

- `taxonomy-car_make-landing.php`

Each managed landing config includes:

- `slug`
- `make_name`
- `make_slug`
- `model_name`
- `model_slug`
- `title`
- `meta_description`
- `h1`
- `canonical`
- `intro`
- `faqs`

These pages live at:

```text
/car_make/{slug}/
```

### Generic Browse/Filter URLs

Generic browsing lives at:

```text
/cars/
/cars/filter/...
```

These should support user browsing without competing against managed SEO landings.

### City Landings

Config file:

- `includes/city-cars-landing/CityCarsLandingCatalog.php`

Renderer:

- `includes/city-cars-landing/render-city-cars-landing.php`

Supported cities:

- Nicosia
- Limassol
- Larnaca
- Paphos

## Metadata Integration Points

Make/model landing metadata is handled by filters/functions in `includes/car-make-landings.php`:

- WordPress document title filters
- Yoast title/meta/canonical/robots filters
- Rank Math title/description/canonical/robots filters
- manual fallback meta output in `wp_head`
- FAQ JSON-LD
- canonical/default canonical handling
- `wp_robots`

When changing SEO logic, check all of these together.

## SEO Rules

- One clear canonical per indexable page.
- Managed make/model pages should canonicalize to `/car_make/{slug}/`.
- Equivalent generic filter URLs should not compete with managed landings.
- FAQ schema should only describe visible FAQ content.
- H1 should match page intent.
- Meta descriptions should be unique and useful.
- Do not hardcode content in the template when it belongs in the config array.
- Do not create indexable thin pages for every filter combination.
- Preserve browsing behavior: changing make/model from an SEO landing should move to generic browse, not another landing.

## Content Rules

- Write for Cyprus.
- Mention Nicosia, Limassol, Larnaca, and Paphos where natural.
- Keep price ranges conservative and reviewable.
- Avoid unsupported claims about market share, official distributors, or live inventory counts.
- Avoid keyword stuffing.
- Prefer practical buyer guidance: price, fuel, servicing, right-hand/left-hand drive context, city use, parts availability.

## SEO QA Checklist

For each changed landing:

- H1 is correct.
- Title tag is correct.
- Meta description is correct.
- Canonical is correct.
- Robots directive is correct.
- FAQ visible content exists if FAQ schema exists.
- FAQ schema validates structurally.
- Listings are prefiltered correctly.
- Filter changes do not create indexable duplicate URLs.
- Page source has no conflicting plugin/manual metadata.

## Make/Model Landing Copy Template

```md
Slug:
URL:
Title:
Meta description:
H1:
Intro paragraph 1:
Intro paragraph 2:
Intro paragraph 3:
FAQ 1:
FAQ 2:
FAQ 3:
FAQ 4 optional:
Canonical:
Indexing notes:
```

## City Landing Copy Template

```md
City:
H1:
Browse lead:
Intro heading:
Intro paragraph 1:
Intro paragraph 2:
Canonical:
Internal link target:
Indexing notes:
```

## Technical Gotchas

- The project supports both Yoast and Rank Math filters.
- Manual fallback meta output exists, so duplicate output is possible if changed carelessly.
- `car_make` taxonomy includes both makes and models as hierarchical terms.
- Some unmanaged taxonomy pages may redirect if empty.
- `simple_jsons` controls taxonomy sync data, not landing copy.
- There is no automated SEO test suite; inspect source and behavior manually.

