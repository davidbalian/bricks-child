# AutoAgora Global Search

The global search is a reusable marketplace search system rather than the default WordPress `s=` search.

## Surfaces

- Desktop code-owned header
- Mobile header button and bottom-dock Search action
- Homepage hero with the existing structured filters under **Advanced filters**
- Cars browse strip
- Shared full-screen mobile/keyboard overlay
- 404 recovery search
- Virtual `/search/?q=...` complete results page (localized home prefix when Polylang is active)

Use `[autoagora_global_search]` for another inline instance. Supported `context` values are `default`, `header`, `hero`, `overlay`, `browse`, and `404`.

## Search sources

The compact `{prefix}_autoagora_search_index` table contains public records for:

- active published cars
- car makes and models
- public-quality dealership profiles
- published buyer requests
- published posts and pages

Pending, password-protected, sold, and expired cars are not indexed. Searchable listing/dealer/request meta changes are coalesced and reindexed once during request shutdown.

The schema is installed through `after_setup_theme`. The initial index rebuild runs in WP-Cron batches of 100 records via `autoagora_global_search_rebuild_batch`.

## Query behavior

`autoagora_global_search_parse_query()` converts deterministic intent such as make/model, price, mileage, year, city, fuel, body, transmission, drive, engine, horsepower, seats, doors, and selected extras into existing `/cars/` query parameters.

Unstructured vehicle text is sent as `car_search`; `car_listings_build_query_args()` resolves it to indexed car IDs and then applies the normal active-state and Best Match pipeline. Search result combinations are `noindex,follow`.

The public AJAX action is `autoagora_global_search` and requires the localized search nonce. Responses are cached for five minutes and invalidated by an index revision. Pressing Enter opens the grouped global results route; the leading **Search cars** suggestion opens the parsed marketplace filter URL.
