# AutoAgora dealer API v1

This reference describes the eventual partner contract. It is not available on AutoAgora production. Public partner onboarding is currently disabled: private review mode returns 404 for non-administrators, including bearer-only callers. The administrator workspace exercises listing and grant services locally. A later explicitly configured public release is required for the OAuth flow below. Tagged review data remains private in either mode.

Local base: `http://127.0.0.1:8899/wp-json/autoagora-dealer/v1`. Hosted deployments require HTTPS. Requests and responses use JSON except OAuth token/revocation requests, which use form encoding. Responses are private and not cacheable.

## Partner registration and authorization

An operator registers the partner application once in each environment, with an exact callback URL. Each dealer then connects independently. Dealer passwords are entered only on AutoAgora; the partner receives a code and tokens after consent.

1. Generate a cryptographically random verifier and state, store them in the partner's server-side session, and compute the RFC 7636 S256 challenge.
2. Redirect the browser to the site's root with the following query parameters:

```text
autoagora_connect=authorize
response_type=code
client_id=REGISTERED_CLIENT_ID
redirect_uri=EXACT_REGISTERED_CALLBACK
scope=stock:read stock:write stock:status catalog:read showroom:write
state=RANDOM_SESSION_BOUND_STATE
code_challenge=S256_CHALLENGE
code_challenge_method=S256
```

3. The dealer signs into AutoAgora and approves or cancels. The callback receives `code` and the original `state`, or an OAuth error. Reject a state mismatch. Do not log the callback query or display/store authorization codes in analytics.
4. Exchange the code server-to-server:

```http
POST /wp-json/autoagora-dealer/v1/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&client_id=CLIENT_ID&client_secret=CLIENT_SECRET&redirect_uri=EXACT_CALLBACK&code=CODE&code_verifier=ORIGINAL_VERIFIER
```

Successful exchange returns `token_type: Bearer`, `expires_in: 3600`, `access_token`, `refresh_token`, and `scope`. Codes expire after five minutes and may be used once. S256 is required, including confidential partner clients. Password, implicit and client-credentials grants are not enabled.

Refresh with a form POST to the same endpoint using `grant_type=refresh_token`, the current `refresh_token`, `client_id` and `client_secret`. The refresh token has a 30-day rolling expiry and is rotated on success; atomically replace the old one. Refresh requests can narrow the original scopes. An old/expired/revoked token returns an OAuth error; reconnect when necessary.

Revoke with a form POST to `/oauth/revoke` containing the partner credentials and `token` (an issued access or refresh token). Revoking either disconnects that dealer's whole grant for that partner. Unknown tokens return 200 without revealing token existence. `/connection` below also disconnects the active grant. Dealers can review/disconnect at `/?autoagora_connect=connections` or through **Connected applications** in their account.

Scopes are explicit and shown during consent:

| Scope | Permission |
|---|---|
| `stock:read` | Read the dealer's own stock, status, showroom and view availability; disconnect the connection |
| `stock:write` | Create/update specifications, descriptions, price, location and photos |
| `stock:status` | Mark sold or withdraw |
| `catalog:read` | Read current field options and make/model relationships |
| `showroom:write` | Save the default showroom |

An access token is valid only for these API routes. It cannot authenticate arbitrary WordPress REST operations.

## Resource routes

All resource requests need `Authorization: Bearer ACCESS_TOKEN`.

| Method and path | Scope | Behaviour |
|---|---|---|
| `GET /catalog?page=1` | `catalog:read` | Current exact field options; parent makes, 50 per page; year/image constraints |
| `GET /catalog?make_id=TERM_ID&page=1` | `catalog:read` | Models belonging to that make, including empty terms |
| `GET /listings?page=1&per_page=20` | `stock:read` | Own listings in ascending ID order, including pending/draft/sold/expired; max 50 per page |
| `GET /listings?external_stock_id=ID` | `stock:read` | Exact external identity lookup for this dealer and partner |
| `GET /listings/ID` | `stock:read` | Specifications, location, ordered photo IDs/URLs, status and version |
| `POST /listings` | `stock:write` | Create one car; 201 means submitted to moderation |
| `PATCH /listings/ID` | `stock:write` | Update supplied fields only; 200 means saved to pending, not published |
| `POST /listings/ID/status` | `stock:status` | Body `{"status":"sold"}` or `{"status":"withdrawn"}` |
| `GET /showroom` | `stock:read` | Saved default or `location: null` |
| `PUT /showroom` | `showroom:write` | Body `{"location":{...}}`; saves a dealer-level default |
| `GET /listings/ID/views` | `stock:read` | Returns `available: false` and the tracking limitation; no invented counts |
| `DELETE /connection` | `stock:read` | Disconnect this dealer's partner grant; retain stock and photos |

Writes need an `Idempotency-Key` header: 8–128 printable ASCII characters. Its scope is partner, dealer, method and route. Retry with the same body, original `If-Match` and key. Successful responses are replayed with `Idempotency-Replayed: true`. A different body/header under the same key returns 409. Failed operations can retry. Receipts are retained for 30 days by cleanup; external stock identity persists independently.

Listing updates/status operations also need `If-Match: "VERSION_HASH"`, obtained from the listing's `version` property or an ETag response header. Omit it or send an old value and the server returns 412. Read again and reconcile the dealer's edit before retrying. A partial/hard-interrupted request may require reading by external ID before retrying; never create a different external ID just to bypass an uncertain result.

`external_stock_id` is required on creation, immutable, and 1–128 letters/digits/dot/colon/slash/underscore/hyphen. It is unique per dealer and partner. A new create key with an existing external ID returns 409 with the existing listing ID; use PATCH. The same value can independently belong to another dealer or partner.

## Vehicle fields

The transport uses typed JSON, not the website form's serialized strings. [sample-vehicle.json](sample-vehicle.json) is synthetic data for the offline fixture; replace its photo URLs with actual public HTTPS stock images for a hosted partner.

Required: `make`, `model`, `year`, `mileage`, `price`, `availability`, `engine_capacity`, `fuel_type`, `transmission`, `body_type`, `exterior_color`, and 2–25 photos. Supply `location` or save a showroom first. A PATCH may omit unchanged fields.

- `make` and `model` are exact catalog names; the model must belong to the make. Do not send term IDs as those field values.
- `year`, `mileage`, `price`, `hp`, `number_of_doors`, `number_of_seats`, `numowners` use integers. Price is whole positive euros; mileage/hp may be zero. Year is 1948 through the current site year. Owners, if supplied, are 1–99. Doors are 0,2,3,4,5,6,7; seats 1–8.
- `engine_capacity` is a one-decimal litre string: `"0.4"` through `"12.0"`. Electric requires `"0.0"`.
- Availability is `In Stock` or `In Transit`, separate from sold/withdrawal.
- Optional: `drive_type`, `hp`, `interior_color`, `description`, `number_of_doors`, `number_of_seats`, `motuntil`, `numowners`, `isantique`, `extras`, `vehiclehistory`. Send `isantique` as a boolean; legacy 0/1 is accepted and returned from the stored field. Extras/history are arrays of exact catalog strings. An optional null clears a value; an empty array clears selections.
- MOT is `Expired` or a valid `YYYY-MM` from the current month through the same month two years later.
- Description is sanitized WordPress-allowed HTML, up to 20,000 bytes. Other strings are bounded to 191 bytes except location strings (500 bytes).
- Unknown fields are rejected, including author, post status, promotion/payment fields, unsupported VIN/trim fields, generic `Van`, and `LPG`. Do not infer unsupported data or use zero for unknown optional facts.

Location is `{"address":"Showroom address","city":"Nicosia","district":"Nicosia","latitude":35.1727,"longitude":33.3639}`. District may be empty/omitted. Coordinates are JSON numbers within geographic latitude/longitude ranges, non-zero and rounded to six decimals. A per-car override does not change the default showroom. Updating a default does not move existing stock.

Photos are an ordered replacement array of `{"url":"https://PUBLIC_HOST/photo.jpg"}` or `{"id":EXISTING_LISTING_IMAGE_ID}`. An ID must already belong to that car's current gallery. Omit `photos` on PATCH to retain the gallery. Include every photo you intend to keep when replacing/reordering it. Duplicate entries are rejected.

The server downloads and stores remote bytes; it does not hotlink stock photos. HTTPS/public DNS/port 443 only; private/reserved IP destinations, credentials in URLs, unsupported schemes and unsafe redirect hops are rejected. Limit: 12 MiB per image, up to three redirects, up to 15 seconds per hop and a 90-second batch target, with a 120-second PHP execution requirement. The system resolver has its own host-level timeout. Valid JPEG, PNG, GIF and WebP bytes are accepted; WebP is preferred, JPEG is acceptable. There is no minimum resolution or required ratio. API decoding safety limits are 40 MP and 12000 pixels per side; the existing optimizer may resize/convert, preserving ratio. A photo failure leaves prior stock unchanged; no success is returned for an incomplete gallery.

## Responses and errors

Mutation example:

```json
{
  "id": 123,
  "url": "https://STAGING_HOST/car/2020-bmw-3-series-123/",
  "post_status": "pending",
  "listing_state": "active",
  "status": "pending",
  "publicly_available": false,
  "in_marketplace": false,
  "version": "\"VERSION_HASH\""
}
```

Sold keeps the existing state logic and redirects its public page; it does not create a new visible Sold page. Withdrawal changes WordPress status to draft and retains the previous active/sold/expired state plus all data/media. Updating withdrawn stock resubmits it to pending. Sold/expired stock is never silently restored to active. There is no destructive DELETE listing endpoint.

Resource error example:

```json
{"code":"invalid_model","message":"Model must belong to the selected make.","data":{"status":422}}
```

| HTTP status | Meaning |
|---|---|
| 400 | Malformed request, invalid pagination or missing idempotency key; OAuth errors use standard `error` fields |
| 401 | Missing, expired, revoked or otherwise invalid token/client credentials |
| 403 | Insufficient scope, ineligible dealer or blocked business rule |
| 404 | Listing missing or owned by another dealer |
| 409 | Duplicate stock identity, reused key with different payload, invalid transition or concurrent write |
| 412 | Missing/stale listing version |
| 413 / 415 | Request too large / wrong content type |
| 422 | Unsupported field, invalid catalog relationship, numeric/type/location/photo-array validation |
| 429 | 60 resource requests/minute or 10 writes/minute per dealer/partner; honor `Retry-After: 60` |
| 502 | Photo download/processing or listing commit failed; retry safely after checking the cause |
| 503 | Runtime/configuration/business-rule verification unavailable, or uncertain receipt persistence |

JSON requests are limited to 256 KiB. OAuth token bodies are limited to 32 KiB and to 30 requests/minute per client. Pagination covers changing stock, so re-read changed pages when synchronizing large inventories; this version does not promise a snapshot or webhook feed.

Use [requests.http](requests.http) or the runnable local HTTP suite as a request collection. Keep actual tokens/client secrets in a private environment or secret manager, never inside the collection. Deliver the registered client secret to the partner through an agreed secret manager or one-time secure share, separately from this documentation. Register new independent credentials/keys for each environment; never copy staging tokens into production.
