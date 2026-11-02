# Seller connection and onboarding

This feature is scoped to partner seller integrations. It does not replace the marketplace login or grant WordPress administrator access. Deployment is staging-only; production onboarding remains disabled.

## Dealer flow

1. Click **Connect to AutoAgora** in Postaki. The existing authorization URL and OAuth parameters do not change.
2. The AutoAgora connection screen validates the application, callback, scopes and S256 challenge before showing the dealership finder. The dealer enters their business name and optionally a city. Existing login holders can skip matching and sign in directly.
3. Possible matches show only business names and cities. Choosing one requires the existing account's phone/username and password. Signing into a different account does not claim the selected dealership.
4. Successful sign-in resumes the exact original OAuth request. The dealer reviews and approves Postaki's permissions; code exchange, tokens and account ownership remain the existing OAuth/API implementation.
5. If the dealer cannot find or access their account, **Request access** records a private review request. It does not create a dealership, claim a profile, reset a password, issue a token or connect Postaki. AutoAgora arranges access after verification, then the dealer starts a fresh connection from Postaki.

The separate branded `/?autoagora_connect=dealer-login` screen can sign a staging dealer into an existing dashboard or car-page session. It only accepts same-origin frontend returns, rejects backend/external returns and does not expose the catalogue without a valid partner authorization request. Explicit administrator sign-in continues to use the normal administrator route.

## Private sellers

Postaki sends `account_type=dealer` or `account_type=private` in the **authorization URL**, alongside the existing OAuth parameters. Omission defaults to `dealer` for existing integrations. Invalid or nonscalar types are rejected. Do not send this field in listing JSON. Existing client IDs, secrets, callbacks, scopes and API routes are unchanged.

`private` skips dealership matching and opens the branded sign-in screen for an existing normal AutoAgora account (WordPress `client` role). Its fallback records a private account-access request without searching or exposing personal profiles. Requests are labelled by account type for operator review. A private seller can manage their own connections, defaults and listings through the existing API and dashboard; the stock tab becomes **Your listings**. Moderation and listing allowances remain unchanged.

The URL selects the requested flow; it never changes a role or turns an account into a dealership. Consent and API access require the actual supported account type, existing edit/upload capabilities and eligibility rules. A dealer cannot consent as a private seller or vice versa. Private-seller support additionally requires `AUTOAGORA_DEALER_PRIVATE_SELLERS_ENABLED=true`. Only the exact staging guard enables it; the staging user allowlist still applies. Administrator and arbitrary subscriber accounts are not eligible.

## Operator workflow

Open **Tools → Seller onboarding** as an administrator. Search the supplied business name and optional city to find existing dealership accounts before creating another one. Pending requests include the requester's contact, application, reference and current possible account matches.

Verify ownership using an independently known dealership contact before arranging their existing login. A submitted name, phone or email is a claim, not verification. Use the existing account-management process; this page deliberately cannot assign another user's identity or issue a partner grant. **Mark reviewed** closes the request without granting access.

Public `dealer_profile` posts are separate entities from dealership user accounts. The matcher searches actual `dealership` accounts, not unclaimed public directory profiles. It checks dealership-name metadata, first/last name and display name. It handles punctuation, common company suffixes, token order, typos and Greek accents/transliteration. Similarity is a ranking aid, never a claim of verified ownership. A city match breaks ties without hiding plausible names in a different city.

The directory is read in 200-account pages and ranked across the entire resulting catalogue. A five-minute cache is invalidated on relevant account-name, role and location edits. Public results are limited to five eligible accounts and exclude private review users and administrative accounts. Administrator searches can find otherwise API-ineligible accounts to avoid duplicates. On staging, the existing synthetic-dealer eligibility allowlist prevents cloned real accounts appearing in anonymous lookup results. No login, email, phone, internal account ID or similarity score appears in dealer-facing match results.

## Controls and enablement

- Explicit opt-in: `AUTOAGORA_DEALER_ONBOARDING_ENABLED=true`, alongside a configured, enabled public dealer integration. Private review mode and the production API approval gate remain in force. The flag is added only to the exact-host/root/environment staging MU guard.
- No replacement production login hooks or global CSS. New screens load the existing scoped Tabler/button kit plus connection-only layout styles, including mobile layouts.
- Password verification uses WordPress authentication and its existing filters. Eligible account ownership and requested account type are checked before setting a session. It does not grant capabilities or administrator access.
- Anonymous forms use an HttpOnly SameSite cookie plus a CSRF value bound to both browser and exact return/OAuth request. Candidate handles are opaque, random, short-lived and tied to that browser/flow. Account eligibility is checked again when selected and when signing in.
- Requests and login attempts are rate limited using hashed subjects. No secrets or submitted contact details are written to the API audit log.
- Review records use a nonpublic, non-queryable post type with no REST exposure/export. Administrative access requires both the administrator role and `manage_options`, with nonces on changes. Submissions deduplicate under a database lock; concurrent requests return the same reference.
- Responses are private/no-store, noindex, deny framing and use a same-origin asset policy. The consent page permits only the already-validated partner callback origin for form redirects.
- The matched account still owns its own listings. No changes were made to API listing ownership, existing clients, credentials, grants or moderation rules.

## Local verification

The disposable loopback fixture uses synthetic, noncontactable accounts. It includes more than 200 catalogue rows to prove matching does not stop at the first page. Never deploy its generated MU file, accounts or credential files.

```sh
python3 tests/dealer-integration/connect_fixture.py
PHP_CLI_SERVER_WORKERS=4 php -d display_errors=0 -S 127.0.0.1:8899 -t .staging/wp tests/dealer-integration/router.php
python3 -u tests/dealer-integration/connect_checks.py
python3 tests/dealer-integration/connect_fixture.py restore
```

The 64-check HTTP suite exercises the branded finder, fuzzy/ambiguous results, ownership denial, valid login, real OAuth consent/code/PKCE exchange, stock read, request privacy, deduplication/concurrency, admin review, cache invalidation and rate limits, plus private login, consent, real code/PKCE exchange, own listing create/update/withdraw, cross-account denial, role preservation and feature-disable denial. The original private workspace/reviewer regression suites run after restoring the normal private fixture. Screenshots and credentials remain in ignored private artifacts; sanitized verification is recorded separately.
