# Hosted staging sandbox — 29 October 2026

The current theme/dashboard is installed at **https://staging7.autoagora.cy/**, SiteGround copy `dealer-integration`. This is an authenticated copy with a separate database, fresh cookie salts, independent OAuth signing/encryption keys and new staging-only application credentials. No production deployment or production functional tests were performed for this release.

## What works

A registered stock-management application requests a dealer's consent using OAuth authorization code + S256 PKCE. Its server exchanges the code for scoped access/refresh tokens, then creates or updates that dealer's cars, photos and showroom through the dealer API. External stock IDs and idempotency receipts prevent duplicate imports; version checks prevent stale overwrites. Sold and withdrawn states follow the existing marketplace rules. Withdrawal retains the car and gallery. A dealer can disconnect an application and immediately revoke its access.

The hosted **dealer dashboard** now reuses the existing dashboard template and components for the staging dealership account. Its Connections, Dealer stock and Activity tabs are scoped to the signed-in dealer before querying or paginating records. The demonstration partner uses the real OAuth/API flow; the earlier private review workspace still creates synthetic grants directly for UI review. A successful demonstration proves the AutoAgora flow with this application; it does not establish that a third party's unprovided software has been integrated.

## Alekos's browser test

Open **https://staging7.autoagora.cy/?autoagora_connect=workspace** and use the existing WordPress staging login provided privately. The previous tester URL redirects here. It needs no email and has no WordPress backend or administrator permissions. Access expires on **28 November 2026 at 17:49 UTC**. The existing production reviewer login was not changed.

1. In **Connections**, choose **Connect application**, then **Continue to authorization** and **Authorize connection**. This uses real dealer consent and exchanges the code on the server, returning to the same dashboard session.
2. In **Dealer stock**, choose **Post test car**. The demonstration supplies a complete synthetic BMW payload and two clearly labelled images through the real HTTPS downloader.
3. Choose **View car** or **Browse staging cars**. Published active stock appears in the normal staging marketplace templates. The staging site banner links back to the dashboard.
4. **Edit stock** supports price, description and photo order. **Mark sold**, **Withdraw listing** and connection **Disconnect** operate only on this account. Withdrawal keeps the car and gallery; an active withdrawn car can be resubmitted by saving it. Sold cars remain sold on resubmission.
5. The dealer can edit retained stock after disconnecting an application, using first-party account ownership. New demonstration imports require a fresh active connection. **Activity** shows only this account's events. **Sign out** ends the current WordPress session.

Reviewer/user management, adding other dealers, other dealers' records, WordPress admin and native REST/admin-post/AJAX mutation routes remain unavailable to this account. The existing login password, role and expiry were not changed.

Sample retained for review: https://staging7.autoagora.cy/car/2020-bmw-3-series-52844/ (requires staging login).

## Connecting his own software

Give the developer the [API contract](API.md), with this staging base:

`https://staging7.autoagora.cy/wp-json/autoagora-dealer/v1`

The developer must supply the exact HTTPS **OAuth callback URL** in his application: the page that receives the dealer back after consent. An operator then registers a separate staging client ID/secret for that URL and transfers the secret privately. The hosted demo secret stays on the server; it must not be reused by another application or put in frontend JavaScript. Production URLs, keys and tokens must never be used for this test.

The developer generates state/verifier, redirects the dealer to the authorization endpoint, validates state, exchanges the code on his server, and calls the listing API with its bearer token. This follows the same flow tested by the hosted demonstration. Listing writes need `Idempotency-Key`; updates/status changes also need `If-Match`. View reporting currently returns `available: false`; no webhook feed is promised.

## Isolation and operating notes

- Only the designated staging dealer and existing administrators can sign in. Disposable QA accounts and grants were disabled after testing. Native REST and backend access are blocked for the test dealer; native submission/AJAX mutation flows are also blocked.
- Marketplace car queries are restricted to designated synthetic accounts. Existing tagged private review data stays private. Cross-dealer API filtering is retained when applying the staging query restriction.
- WordPress/app mail, SMS credentials, payment credentials, browser tracking, external WordPress HTTP and scheduled jobs are disabled. Photo ingestion still uses the real bounded public HTTPS downloader. Site fonts are served locally on staging.
- Dynamic pages require authentication and emit noindex/no-store headers; robots disallows crawling. No cache drop-ins or copied production WAF/cache paths are used.
- Only explicit allowlisted staging accounts can auto-publish API imports. `Support::submissionStatus()` retains `pending` in production, development, local and private review, even if a filter asks to publish.
- Staging-specific MU source is saved under `tests/dealer-integration/hosted/`; install `staging-guard.php` as `000-autoagora-staging-isolation.php`, `staging-client.php` as `autoagora-staging-client.php` and `staging-workspace.php` as `autoagora-staging-workspace.php`. The staging modules are pinned to the staging7 environment, host and filesystem root. They are not loaded by the production theme.
- Keys, passwords, tokens and runtime configuration remain outside the web root or in ignored `.private` files. Never commit or deploy those files. Never restore copied production credentials into staging.
- **Never use SiteGround Full Deploy or deploy this staging database/configuration to production.** A future production release needs a separate review and credential/onboarding setup. Recreating or removing staging does not require changing the live site.

## Verification

The [sanitized verification record](STAGING-VERIFICATION.json) contains the checks and their limits: 59 real hosted lifecycle checks, 79 local private-workspace checks, 70 local reviewer checks, eight environment/moderation guard cases, and a 20-check final runtime audit. Browser testing covered consent, create, mobile price edits, withdrawal/resubmission and the normal car page; the tester had no page overflow at 390px. Production theme source hashes for the touched/related files matched the pre-change commit; this is a source-scope check, not a production functional test.

The temporary SiteGround SSH key was removed after setup. Reimporting a temporary scoped key with approval would be required for future server changes. The existing Twilio SSH key was preserved.

## Dealer dashboard access update

The existing WordPress account now lands directly in the full frontend dashboard. Production `Review::canWorkspace()` and administrator/reviewer permissions were not broadened. The staging controller intercepts only the staging dashboard route for the allowlisted eligible dealer; every action rechecks the signed-in identity, listing ownership, enabled staging access and form nonce. The request cannot select a different dealer. Stock edits/status operations use the existing listing services and version guards; the demo import uses real API idempotency.

Deployment used SiteGround File Manager on **staging7 only**, replacing the shared template and two existing staging MU files and adding the staging dashboard MU adapter. No SSH key was added. The uploaded archive/extracted package is retained outside the web root under the staging7 directory; the active files are under staging7/public_html. A matching rollback archive is available privately. Never copy the package into the production site or deploy the staging database.

[Dashboard acceptance evidence](STAGING-DASHBOARD-VERIFICATION.json): 58 local dashboard checks, 51 hosted dashboard checks, the existing 79 workspace and 70 reviewer checks, and eight publication guard checks all passed. Local adapter tests use explicitly adapted private copies in the disposable loopback fixture; the tracked hosted source retains its exact staging7 guards. The normal private fixture was restored before its regression checks.

The dashboard and legacy tester fail closed with HTTP 503 when integration dependencies are missing; four additional local normal/failure/recovery checks passed. The original local private-review fixture was restored afterward.
