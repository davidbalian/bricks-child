# AutoAgora integration inspection

The owning implementation is the existing `bricks-child` WordPress theme, not the abandoned custom rebuild. The requested workspace was an empty repository without a remote. The source was located through the existing theme checkout, confirmed by David's deployment screenshot, fetched from GitHub, and placed on `codex/dealer-integration`. No push or production deployment has been performed.

## Existing behaviour and integration decisions

| Area | Owning source and finding | Integration behaviour |
|---|---|---|
| Dealer identity | `includes/auth/roles.php`, seller verification shortcode: the `dealership` role is the current verified badge condition. `includes/dealer-profiles/init.php` explicitly separates public profiles from real accounts. | Require the dealership role and upload/edit capabilities on authorization, token refresh and every stock operation. Never trust a supplied owner ID or public profile claim. |
| Ownership | Form handlers and `MyListingsAjaxHandler` use the car's `post_author`. | Author is derived from the verified OAuth user. Cross-dealer reads/writes return 404. |
| New stock | `car-submission.php` creates `car` posts as pending. It uses ACF/meta, both make/model taxonomy terms, and detail badges. | Preserve pending moderation and share an allowlisted field writer with add/edit forms. |
| Edits | Existing editor validates whitelists, writes existing fields and sets pending when its year is submitted. | All API content/photo edits return to pending; PATCH preserves omitted optional fields. |
| Packages and payments | Theme add/edit handlers contain no base package quota. `includes/listing-promotions` handles separate paid promotions. CPT/ACF groups and plugins are outside this repository. | API cannot set promotion/payment fields. An explicit rules-verification flag and `autoagora_dealer_api_can_save` filter protect release against uninspected external limits. Do not set the flag on a hosted environment until those rules are resolved. |
| Photos | Traditional add accepts at least one successfully processed image, whereas browser limits are 2–25. Async paths differ. `core/image-optimization.php` handles resizing, WebP conversion and derivative cleanup. | API enforces 2–25 images and 12 MiB per image itself, validates bytes, stages the complete gallery, reuses the optimizer, and commits only after processing succeeds. |
| Dimensions | No mandatory minimum dimensions or aspect ratio found in the inspected server pipeline. Its 1920×1080 resize bounds preserve aspect ratio. | No minimum or ratio requirement. The API adds a decoding safety ceiling of 40 MP / 12000 pixels per side, clearly separate from existing form requirements. |
| Image deletion | `functions.php` deletes listing images on trash as well as permanent deletion. | Withdrawal must not use trash. Failed staged uploads are cleaned; replacements retain previous attachments. |
| Sold | `ListingStateManager` owns active/sold/expired. `core/car-listing-redirects.php` redirects sold single pages and marketplace queries normally require active. | Keep the existing sold meaning; no new publicly visible Sold page policy. |
| Expiry | State manager, bulk-expire tools and listing reminders use existing state/date fields. | API preserves expiry and publication dates. It does not silently reactivate expired/sold stock. |
| Withdrawal | No separate existing withdrawal state. | Proposed local behaviour: set draft and a separate withdrawal marker; preserve the state/data/media. PATCH resubmits to pending. This was explained to David after he requested a recommendation. |
| Location | Form handlers save `car_address`, `car_city`, `car_district`, `car_latitude`, `car_longitude`. Saved locations are derived from previous listings. | Save a dealer-level showroom default separately; use it for new cars unless an override is supplied. Validate coordinates and map into existing fields. Existing cars retain their location on omitted PATCH. |
| Login return | Login redirect and custom login form previously discarded their return destination. | Preserve only a validated same-origin integration continuation; unrelated login destinations keep their existing behaviour. |
| View counts | Tracker requires a matching car URL suffix; excludes owner/admin/detected bots; total has a five-minute fingerprint cooldown and unique counts lifetime fingerprints. Server-side page caching can bypass it. | Reporting disabled until its real operating definition is verified. No claim that fingerprints equal people or ad impressions. |
| Hosted staging | SiteGround's staging list had no copies during read-only inspection. | Per David's subsequent instruction, create/test only a local copy. No hosted copy or live changes. |

## Architecture

One partner client is registered per environment. Each dealer separately signs in and approves a scope list. Authorization Code with S256 PKCE issues a one-hour access token and rotating 30-day refresh token. WordPress sessions are used only for first-party login/consent. API bearer tokens do not grant general WordPress REST authentication.

The library handles OAuth cryptography, code exchange and resource verification. Separate custom tables hold hashed client secrets, grants, token identifiers/expiry/revocation and wire-token hashes, stock identity, idempotency receipts, bounded counters and minimal audit events. Raw tokens, authorization codes and request bodies are not stored in those tables. Keys are environment-specific files outside the web root.

Dealer/client-scoped external stock identity and unique database keys prevent duplicate creation. Connection-scoped database locks serialize API writes for one dealer; matching website form actions acquire the same lock while the integration is enabled. ETags cover listing data, location, gallery, taxonomy, state and WordPress modification time. Updates require an exact `If-Match` value and recheck it after image preparation.

The media downloader accepts public HTTPS URLs only, checks every redirect, resolves public addresses and pins the connection to the validated address while retaining TLS verification. It disables proxies, bounds redirects, transfer size and cURL timeouts, validates image bytes, and stages attachments separately. DNS resolution uses the host's PHP resolver and still depends on its timeout configuration; hard process termination can leave a staged upload for later cleanup. This needs hosted capacity testing before release.

## Outstanding partner and release decisions

The actual partner's name and exact callback URL are still unknown. Their developer supplies that URL; David does not need to invent it. The local test partner is sufficient for implementation review.

Ambiguous Van, petrol/LPG and LPG-only mappings remain unresolved and are rejected rather than guessed. Confirm withdrawal/resubmission behaviour during review. Reconcile the current hosted schema, plugins, eligibility/allowance rules, maximum execution time, cache invalidation and external notification/payment settings using a separately authorized staging environment before production release. Do not make live checks under the current instruction.
