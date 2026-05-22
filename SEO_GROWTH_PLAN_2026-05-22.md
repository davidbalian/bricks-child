# AutoAgora SEO Growth Plan

Created: 2026-05-22

Source baseline: `SEO_GROWTH_BASELINE_2026-05-22.md`

## Objective

Grow AutoAgora organic traffic by improving marketplace search intent matching, inventory freshness, listing quality, dealer exposure, and conversion from organic visits to seller contact actions.

This plan prioritizes changes that directly support:

1. Organic traffic growth
2. Inventory freshness
3. Marketplace quality
4. Dealer retention
5. Search intent matching
6. CTR optimization
7. Contact conversion

## Current Baseline

GSC last 28 days:

- Clicks: 1,010
- Impressions: 41,200
- CTR: 2.4%
- Average position: 10.7

Inventory:

- Total listings: 803
- Active listings: 541
- Expired listings: 171
- Sold listings: 91
- Active listings under 14 days: 46
- Active under 14 days share: 8.5%
- Active listings over 60 days: 297

Conversion:

- Active listing views: 38,042
- Contact clicks: 117
- Contact rate: 0.31%
- Active listings with 20+ views and 0 contacts: 438

## Strategic Diagnosis

AutoAgora has enough search demand to grow, but the main bottleneck is marketplace quality rather than keyword discovery.

The biggest issues are:

- `/cars/` is not strong enough as the main generic used-car landing page.
- Homepage is carrying too much non-brand marketplace demand.
- Inventory freshness is weak.
- Listing contact conversion is far below target.
- Dealer inventory is often stale.
- City inventory data is missing from the export.
- Some make/model pages have demand but weak CTR or weak inventory conversion.

## Phase 1: Fix Measurement And Marketplace Quality

Timeline: immediate, first 2 weeks

### 1. Fix City Data In Inventory Export

Priority: High

Problem:

- The `city` column is empty for active listings.
- City SEO pages cannot be evaluated reliably.

Action:

- Update the SEO inventory export to check all relevant location fields:
  - `car_city`
  - `car_district`
  - `car_address`
  - location picker fields if applicable
- Confirm whether city is stored as ACF formatted data or raw postmeta.
- Re-export inventory after the fix.

Expected impact:

- Enables proper Nicosia, Limassol, Larnaca, and Paphos SEO analysis.
- Helps identify city inventory gaps.

Owner:

- Coding agent

### 2. Build Dealer Freshness Report

Priority: High

Problem:

- Key dealers have large stale inventory pools.
- Example: Auto Cyprus, AUTOEXCLUSIVE LTD, Autoevresis, VCM Motors Luxury Cars.

Action:

- Add/export dealer-level metrics:
  - dealer ID
  - dealer name
  - active listings
  - listings under 14 days
  - listings over 30 days
  - listings over 60 days
  - listings over 90 days
  - average listing age
  - total views
  - total phone clicks
  - total WhatsApp clicks
  - contact rate
  - high-view zero-contact listings

Expected impact:

- Gives AutoAgora a dealer retention tool.
- Creates a clear operational workflow for asking dealers to refresh, improve, or remove listings.

Owner:

- Coding agent
- Marketplace operations

### 3. Audit High-View Zero-Contact Listings

Priority: High

Problem:

- 438 active listings have 20+ views and 0 contact clicks.

Action:

- Start with the highest-view zero-contact listings.
- Check:
  - price competitiveness
  - photo quality
  - number of photos
  - missing specs
  - stale availability
  - dealer contact setup
  - title clarity
  - price insight band

Expected impact:

- Improves conversion without needing more SEO traffic.
- Reveals whether the issue is pricing, trust, inventory age, UX, or dealer behavior.

Owner:

- SEO Growth Analyst
- Marketplace operations

### 4. Define Freshness Rules

Priority: High

Problem:

- Only 8.5% of active inventory is under 14 days old.

Action:

- Define operating rules:
  - 0-14 days: fresh
  - 15-30 days: acceptable
  - 31-60 days: needs refresh
  - 61-90 days: dealer follow-up
  - 90+ days: review for expiry, price update, or availability check
- Use existing 7-day refresh system to push dealer action.
- Build a weekly dealer freshness workflow.

Expected impact:

- Better buyer trust.
- Better inventory quality.
- Stronger SEO page usefulness.

Owner:

- Marketplace operations
- Coding agent for reporting support

## Phase 2: Strengthen Core SEO Pages

Timeline: weeks 2-4

### 5. Reposition `/cars/` As Main Generic SEO Page

Priority: High

Problem:

- `/cars/` has search impressions but weak CTR and rankings.
- Homepage ranks for generic terms that should belong to `/cars/`.

Action:

- Review and improve:
  - title tag
  - meta description
  - H1
  - intro copy
  - internal links to key make/model and city pages
  - visible active inventory count if feasible
  - noindex/canonical handling for thin filters

Recommended intent:

- Used Cars for Sale in Cyprus
- Cars for Sale in Cyprus
- Browse used cars from dealers and private sellers
- Nicosia, Limassol, Larnaca, Paphos coverage

Expected impact:

- Higher CTR on generic used-car queries.
- Better query-to-page alignment.
- Better long-term non-brand traffic growth.

Owner:

- SEO Growth Analyst
- Coding agent

### 6. Improve Homepage To Route Search Intent

Priority: High

Problem:

- Homepage receives broad marketplace searches but is less specific than `/cars/`.

Action:

- Keep homepage strong for brand and broad trust.
- Add stronger internal pathing to:
  - `/cars/`
  - city pages
  - best make/model pages
  - dealer discovery if relevant
- Avoid making homepage compete too heavily with `/cars/`.

Expected impact:

- Passes authority and user flow to the browse page.
- Reduces generic intent mismatch.

Owner:

- SEO Growth Analyst
- Coding agent if template changes are needed

### 7. Update Toyota Yaris Cross Landing Page

Priority: High

Problem:

- Strong query demand around price and Greek-language price intent.
- Active supply exists, but conversion is weak.

Action:

- Improve title/meta around:
  - `Toyota Yaris Cross for Sale in Cyprus`
  - `Toyota Yaris Cross Price in Cyprus`
  - hybrid and used-car intent
- Add FAQ coverage for:
  - price in Cyprus
  - hybrid suitability
  - running costs
  - used import availability
- Audit active Yaris Cross listings with zero contacts.

Expected impact:

- Better CTR from position 7-10 queries.
- Better conversion if stale/weak listings are improved.

Owner:

- SEO Growth Analyst
- Coding agent
- Marketplace operations

### 8. Update Volkswagen Golf Landing Page

Priority: Medium-high

Problem:

- Strong GTI/Golf search intent.
- Inventory is thin but conversion is comparatively better.

Action:

- Improve title/meta to include:
  - Golf for sale Cyprus
  - VW Golf for sale Cyprus
  - Golf GTI where natural
- Add internal links to active Golf inventory.
- Ask dealers for more Golf inventory or refresh stale Golf listings.

Expected impact:

- More clicks from existing rankings.
- Better performance if supply improves.

Owner:

- SEO Growth Analyst
- Marketplace operations

### 9. Audit Mazda CX-5 Before Scaling SEO

Priority: Medium

Problem:

- Search demand exists, but active CX-5 listings have 0 contacts.

Action:

- Audit active CX-5 listings before pushing more traffic.
- Check whether price, photos, stale age, or availability is suppressing contacts.
- Then update landing copy/title/meta if inventory quality is acceptable.

Expected impact:

- Prevents sending SEO traffic to weak supply.

Owner:

- SEO Growth Analyst
- Marketplace operations

## Phase 3: Expand Demand Capture

Timeline: month 2

### 10. Prioritize New Or Improved Make/Model Pages

Priority: Medium-high

Selection rule:

- Only prioritize pages where search demand and active supply overlap.

Current strongest candidates:

- Toyota Yaris
- Toyota Yaris Cross
- Toyota Corolla
- Mazda CX-3
- Mazda CX-30
- BMW X5
- Mercedes-Benz GLA
- Mercedes-Benz A-Class
- Nissan Qashqai
- Land Rover Range Rover Sport
- Volkswagen Golf

Action:

- For each page:
  - unique title
  - unique meta description
  - H1
  - Cyprus-specific intro copy
  - visible FAQs
  - FAQ schema
  - canonical to `/car_make/{slug}/`
  - active inventory prefilter

Expected impact:

- More long-tail buyer-intent traffic.

Owner:

- SEO Growth Analyst
- Coding agent

### 11. Improve Informational Blog Pages As Internal Link Assets

Priority: Medium

Pages:

- `/blog/import-car-cyprus/`
- `/blog/cheap-cars-cyprus/`

Action:

- Match titles and intros to the queries they actually rank for.
- Add direct internal links to:
  - `/cars/`
  - cheap inventory if available
  - relevant make/model pages
  - buyer request flow if appropriate

Expected impact:

- Converts informational traffic into marketplace browsing.
- Supports internal linking.

Owner:

- SEO Growth Analyst

## Phase 4: Technical SEO Controls

Timeline: month 2-3

### 12. Audit Canonical And Noindex Logic

Priority: Medium

Problem:

- Marketplaces can create duplicate/thin URLs through filter combinations.

Action:

- Confirm:
  - managed `/car_make/{slug}/` pages self-canonicalize
  - equivalent `/cars/filter/...` URLs do not compete with managed landings
  - empty/unmanaged taxonomy archives redirect or noindex
  - pagination/sort/filter combinations do not create index bloat
  - Yoast, Rank Math, and manual meta output do not conflict

Expected impact:

- Better crawl efficiency and clearer indexable page set.

Owner:

- Coding agent
- SEO Growth Analyst

### 13. Improve Structured Data Where Appropriate

Priority: Medium

Action:

- Maintain FAQ schema only where FAQs are visible.
- Review listing schema/product snippet behavior.
- Review dealer page schema opportunities.

Expected impact:

- Better SERP presentation and possible CTR improvements.

Owner:

- SEO Growth Analyst
- Coding agent

## Operating Cadence

### Weekly

- Export or refresh GSC query/page data.
- Review high-impression low-CTR pages.
- Review positions 5-15.
- Review inventory freshness.
- Review high-view zero-contact listings.
- Send dealer freshness follow-ups.

### Monthly

- Compare organic clicks, impressions, CTR, and average position.
- Re-run inventory export.
- Review make/model supply and demand overlap.
- Decide which 2-3 landing pages to improve next.
- Review dealer SEO exposure and dealer retention risks.

## KPIs

Organic search:

- Clicks: 1,010 to 5,000+ monthly
- Impressions: 41,200 to 300,000+ monthly
- CTR: 2.4% to 3.5-5%
- `/cars/` clicks and CTR materially improved

Marketplace:

- Active listings: 541 to 1,500+
- Active inventory under 14 days: 8.5% to 40%+
- Contact rate: 0.31% to 2%+
- Strong listings: 5%+ contact rate

Dealer:

- Reduce average listing age for major dealers.
- Reduce high-view zero-contact listings.
- Increase dealer page organic sessions and contacts.

## Immediate Next Actions

1. Fix city data in the inventory export.
2. Build dealer freshness/performance export.
3. Audit the first 20 high-view zero-contact listings.
4. Improve `/cars/` title/meta/H1/internal linking.
5. Improve Toyota Yaris Cross title/meta/FAQ and audit its active listings.
6. Create a weekly dealer freshness workflow.

## First Sprint Acceptance Criteria

- City field is populated in inventory export or the storage issue is documented.
- Dealer freshness report/export exists.
- `/cars/` SEO metadata and intro/internal linking plan is implemented or ready for implementation.
- Toyota Yaris Cross page improvements are implemented or ready for implementation.
- At least 20 high-view zero-contact listings have been reviewed and categorized.
- Dealer outreach list is produced for stale inventory owners.
