# AutoAgora dealer integration

**Current hosted status, 29 October 2026:** the private dashboard has been deployed, and a separate authenticated staging sandbox now exercises real OAuth/API imports on normal staging car pages. See [hosted staging and partner handoff](STAGING.md). The local implementation/review sections below describe the original fixture and its boundaries.

This is a local implementation candidate in the existing WordPress theme. It adds separate dealer consent for a registered stock-management partner. It does not replace AutoAgora, publish listings automatically, or enable production access by default.

Source: `davidbalian/bricks-child`, based on `main` commit `be0cd509d8cb97a7a2faaab29246005a97c32309` dated 19 September 2026. Inspection and local work: 27 October 2026, Asia/Nicosia.

The requested professional UI is delivered locally. Nothing was pushed or deployed, and no further production checks were made. Test connections, profiles, listings and images are restricted to administrators; public onboarding remains disabled.

## Local review

The disposable WordPress copy is at `.staging/wp`, with its own database and synthetic accounts. It is bound to `127.0.0.1:8899`. The compatibility entry point at `127.0.0.1:8898` redirects to the private workspace.

Start from the repository root:

```sh
python3 tests/dealer-integration/sync-local.py
PHP_CLI_SERVER_WORKERS=4 php -d display_errors=0 -S 127.0.0.1:8899 -t .staging/wp tests/dealer-integration/router.php
```

In another terminal:

```sh
python3 tests/dealer-integration/partner.py
```

Open <http://127.0.0.1:8899/?autoagora_connect=workspace> after signing into the local WordPress copy as the synthetic administrator (`wp-login.php?admin_access=true`). The Tools → Dealer integrations menu opens the same workspace. The retained browser tab is already signed into the local administrator account.

The UI reuses AutoAgora’s logo, Inter font, root design tokens, shared button system and existing icon assets. Connections, stock and activity views support searching, dealer filters, private dealer creation, test application connection/disconnection, sample imports, price/description edits, photo ordering, sold status and reversible withdrawal. Administrator test connections exercise grant persistence directly; they do not simulate a real dealer giving consent or provision a real stock-management provider.

Credentials remain only in ignored `.private/test-accounts.json` and partner files. Never include their contents in documentation, source control, screenshots, logs or chat summaries. Generated UI test dealers have random undisclosed passwords and `.invalid` email addresses; no invitation is sent.

`AUTOAGORA_DEALER_PUBLIC_ENABLED` is absent by default, which makes browser onboarding and API routes administrator-only. Both the `administrator` role and `manage_options` are required. Test users and posts are tagged; public queries, single pages, author pages, REST objects and sitemaps exclude them. Attempts to publish tagged cars or profiles become private. Images are written outside the web root under `AUTOAGORA_DEALER_REVIEW_STORAGE` and served through an authenticated, uncached administrator route. Privacy hooks remain active after the API kill switch or missing dependencies when review data exists. Changing the public-onboarding flag never makes already tagged review data public.

New and edited stock awaits moderation. A returned listing URL is its identity, not proof that visitors can view it. Pending URLs can use WordPress query parameters; read the listing again after approval for its published permalink. The API response includes `status`, `post_status`, `listing_state`, `publicly_available`, and `in_marketplace`.

## Verification

```sh
python3 -u tests/dealer-integration/review_checks.py
```

This is the current default suite. It checks actual administrator form submissions and persistence, visitor/dealer denials, private media, moderation, conflicts, withdrawal, disconnection and failure recovery. The earlier OAuth and native-form suites are retained as public-contract regression tools and refuse to mutate the current private fixture. Their earlier 111 passing checks are a separate baseline, not a claim that those flows are available in administrator review mode.

Photos in the repeatable test suite come from two exact synthetic image URLs supplied by the local fixture plugin. That fixture hook is available only when WordPress reports `local` and its home URL is loopback. Production, hosted staging and development use the real HTTPS downloader. The real download/optimization path was also exercised during the earlier local test run; subsequent runs make no requests to AutoAgora production.

## Runtime boundaries

- The local copy uses cached WordPress 7.1.2 and existing archived Bricks/ACF plugin code. Its car schema and custom tables are explicit empty fixtures. It is not a current production database or a full recreation of the hosted plugin configuration.
- No production customer accounts, uploads, payments or database were copied. The make/model taxonomy was seeded from the supplied 27 October field reference, including empty terms.
- Local WordPress mail and external WordPress HTTP integrations are blocked. No payment credentials are configured. Cron is disabled for the fixture. Both test servers bind only to loopback and emit noindex headers.
- Public dealer profiles are not dealer account identity. Eligibility follows the existing `dealership` role, required capabilities, and an optional suspension flag/filter.
- The inspected theme has paid listing promotions, but no base listing package/allowance enforcement in its add/edit handlers. The API cannot purchase or assign promotions. External plugin/snippet rules still need comparison before a hosted release. Listing writes require `AUTOAGORA_DEALER_RULES_VERIFIED` as an explicit gate.
- View reporting returns `available: false` until the actual tracking path and cache behaviour are verified. It never invents a zero count.
- Generic `Van` and `LPG` are rejected. No meaning is inferred for `Bi Fuel`; a partner must supply a supported factual value or obtain a mapping decision.
- Sold and expired single URLs follow the existing redirect coordinator. Withdrawal retains the car in draft, outside public listings. Resubmission returns it to pending without clearing sold/expired state. Restoring a sold/expired car to active remains an existing site/admin decision.
- Replaced galleries retain old attachments for recovery. Failed and abandoned API uploads are eligible for bounded cleanup after 24 hours. Do not delete retained/replaced media without checking references and backups.

## Files and costs

The module lives in `includes/dealer-integration`. Its OAuth implementation is [League OAuth2 Server](https://oauth2.thephpleague.com/), installed from the locked Composer dependencies. Tokens and PKCE are generated and verified by that library. The site integration supplies WordPress persistence, consent, eligibility and resource permissions.

There is no new paid license or service subscription. A hosted deployment would consume existing PHP, database and image storage resources; its capacity and any resulting cost have not been established.

See [verification results](VERIFICATION.md), [API reference](API.md), [inspection and decisions](INSPECTION.md), and [deployment and rollback](DEPLOYMENT.md).

## Password-only reviewers · 28 October 2026

An administrator can now create an individual reviewer login from **Reviewer access** in the workspace. Username only is supported; no email is required or sent. The new account uses a dedicated WordPress role with only the integration-review capability, not an administrator or dealer role. Its password is generated from 192 bits of randomness and saved only through WordPress password hashing. The plaintext appears in the administrator's immediate, uncached response and is never put into a URL, log or persistent retrieval store.

Share `/?autoagora_connect=review-login` and the password privately. Reviewer sign-in uses the normal WordPress authentication/cookie system, HTTPS outside the loopback fixture, a per-browser CSRF cookie, a host-prefixed secure cookie in HTTPS, and an account/IP limit of 5/20 attempts per 15-minute bucket. Sessions last one hour. Expiry defaults to 30 days (administrator-selectable 1–90 days). Revocation disables new access and destroys existing sessions. Reset/renew issues a new password and destroys prior sessions; no email reset link is needed.

Reviewers can interact only with the tagged synthetic integration workspace and its tagged test images. They cannot reach WordPress admin, AJAX/admin-post, native WordPress REST, the dealer API, unrelated frontend submission flows, real dealer/listing records, untagged private files or reviewer management. Public queries continue excluding test data. Administrators and explicitly enabled, unexpired reviewers are the only workspace viewers. The earlier administrator-only release is now extended by this explicit reviewer exception.

Run `python3 tests/dealer-integration/reviewer_checks.py` locally for the new boundaries. Never run these tests on the live site. Creating the specifically requested real reviewer login is account provisioning, not permission to populate live test stock or sign in as that reviewer for a production test.
