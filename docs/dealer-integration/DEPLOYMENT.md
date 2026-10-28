# AutoAgora integration deployment and rollback

The UI was implemented and tested locally; no production release or further live checks were performed. This runbook prepares a later reviewed release. Keep all current test data restricted to administrators and preserve David’s instruction against live-site testing.

## Local installation

Prerequisites are PHP 8.2+, OpenSSL, cURL, an image editor with the existing AutoAgora optimization functions, MySQL/MariaDB with advisory locks and InnoDB tables, WP-CLI, Composer, and a licensed existing Bricks/ACF runtime compatible with the theme.

The prepared local copy uses existing archived parent/plugin code and a cached WordPress version; it does not establish current hosted compatibility. For a fresh fixture, put WordPress in `.staging/wp`, the existing Bricks parent in its themes directory and the existing ACF Pro plugin code in its plugins directory. Do not copy customer database tables, uploads or production configuration. Then:

```sh
composer install --working-dir=includes/dealer-integration --no-dev --prefer-dist --no-interaction
python3 tests/dealer-integration/setup-local.py
```

The setup creates a new database and scoped local database user, generates environment-specific RSA/encryption keys, installs synthetic dealers and seeds the dated reference catalog. It refuses to overwrite an existing configuration. `finish-local.py` resumes an interrupted fixture installation. `sync-local.py` copies theme source without exposing `.git`, `.private` or `.staging` inside the served theme.

The source archive is needed only to recover existing paid parent/plugin code when no separate installation is available; never import its database or customer uploads. Do not commit the archive or generated runtime.

## Release gates for a later authorized environment

1. Confirm review of withdrawal/resubmission and sold/expired behaviour. Obtain the real partner name and exact callback URL.
2. Compare the current CPT/taxonomy/ACF schema and all package, verification, allowance, moderation, expiry and notification rules against a sanitized isolated copy. This repository alone does not contain the complete hosted configuration. Preserve plan limits through the existing rule owner or the API's `autoagora_dealer_api_can_save` filter; do not bypass a paid allowance.
3. Use a separate staging database, host, client, keys and tokens. Disable customer mail, real payment methods, analytics and unintended webhooks/integrations before any test data is created. Keep staging access restricted and noindexed. Do not clone unnecessary customer data.
4. Confirm InnoDB for WordPress posts/meta/term tables and all integration tables, advisory lock support, PHP execution budget of at least 120 seconds, memory/image limits, disk capacity, resolver behaviour and reverse-proxy timeout. A synchronous batch can time out; callers must reconcile by external stock ID.
5. Check cache invalidation for content changes, sold/expiry/withdrawal and moderation on that isolated environment. Keep view reporting unavailable until its tracker and cache path support a defensible definition.
6. Re-run the full OAuth and listing suite there with synthetic dealers, including token expiry, replay, ownership, invalid inputs, concurrency, photo failures, refresh/revocation and existing website forms. Review frontend behaviour and cleanup after an interrupted request.
7. Obtain David's explicit release authorization. Do not perform live-site checks unless he separately authorizes them; his current instruction prohibits them.

## Package preparation

Production's Deployer for Git tracks this theme's `main`. Do not use `main` to distribute an unreviewed candidate. The implementation remains on `codex/dealer-integration`.

The theme deployer does not run Composer. The release includes `includes/dealer-integration/vendor/` built from the committed lock file, so Deployer for Git needs no server-side Composer step. Keep the existing root vendor dependencies intact. Exclude tests, `.private`, `.staging`, repository metadata and credential files from any deployable artifact. Do not blindly ZIP the entire workspace.

Store the private key and encryption key outside the web root, readable only by the PHP service account. Do not reuse a production key/secret in staging. Configure these values in the environment's private WordPress configuration, never Git:

```php
define('AUTOAGORA_DEALER_API_ENABLED', true);
// Mandatory private image storage outside the web root for administrator review.
define('AUTOAGORA_DEALER_REVIEW_STORAGE', '/PRIVATE_PATH/review-media');
// Leave absent/false for administrator-only review. Never enable on the review fixture.
// define('AUTOAGORA_DEALER_PUBLIC_ENABLED', true);
define('AUTOAGORA_DEALER_PRIVATE_KEY', '/PRIVATE_PATH/private.pem');
define('AUTOAGORA_DEALER_PUBLIC_KEY', '/PRIVATE_PATH/public.pem');
define('AUTOAGORA_DEALER_ENCRYPTION_KEY', 'ENVIRONMENT_SPECIFIC_SECRET');
// Set only after the environment business rules have actually been reconciled.
define('AUTOAGORA_DEALER_RULES_VERIFIED', true);
// Required only for a separately authorized production release.
// define('AUTOAGORA_DEALER_API_PRODUCTION_APPROVED', true);
```

`WP_ENVIRONMENT_TYPE` must identify the environment correctly. HTTPS is required outside the loopback local fixture. If a proxy terminates HTTPS, configure WordPress's existing trusted-proxy handling correctly; do not disable TLS checks.

Install tables explicitly with `wp autoagora dealer install`. Then register the partner once:

```sh
wp autoagora dealer client PARTNER_ID --name='Partner display name' --redirect='https://PARTNER_HOST/exact/callback' --output=/PRIVATE_PATH/new-partner-credentials.json
```

The output must be a new private file outside the web root. The command does not print secrets. Deliver its client ID/secret through an agreed secure channel; do not include the file in the release artifact. Independent dealer consent requires the separate public-onboarding release flag. The private workspace only creates synthetic administrator test connections.

The install command schedules bounded hourly abandoned-upload and audit/receipt/token/limiter cleanup. `wp autoagora dealer cleanup` also runs it explicitly. Retain external stock mappings so a retry cannot recreate previously managed stock under the same identity. Replaced images are kept for recovery, not automatically deleted.

## Rollback and emergency disable

Before a later release, create verified file/database backups and record the exact previous theme revision in the private deployment record. Keep a known-good artifact that includes the original dependencies.

Fast disable: set `AUTOAGORA_DEALER_API_ENABLED` to false in the environment's private configuration. The theme then loads no integration routes, workspace, consent handlers or website write locks. Persistent privacy hooks still protect existing tagged review data and its media. Preserve those hooks and the private storage when rolling code back; removing them while leaving test records can break that protection. Clear only the relevant opcode/application cache through the existing deployment mechanism if necessary. Do not run live checks under the current instruction.

To disable one partner while the module is enabled, use `wp autoagora dealer disable PARTNER_ID`. It disables that client and revokes its grants, retaining stock. Dealer self-disconnection likewise retains listings and photos.

Restore the prior theme artifact if code rollback is required. Keep the integration tables and keys for investigation/recovery; do not drop them as part of an emergency disable. A later re-enable must deliberately address existing grants/tokens and external stock identity. A full database restore can discard unrelated listing/customer changes, so it is not the default code rollback method.

No production rollback is needed for this task because no production change has been made.

## Administrator-only release · 28 October 2026

David authorized pushing this work to the existing `davidbalian/bricks-child` repository and deploying the **bricks-child** card from Deployer for Git. No new repository or theme is created. No live-site tests or test data creation are authorized.

The release archive excludes tests, docs, local runtime, repository metadata and private credentials through `.gitattributes`. Locked dependencies are packaged. After the theme deployment, Tools → Dealer integrations offers an explicit administrator-only activation form. Activation creates only new empty integration tables and fresh private configuration outside the web root; it imports no local fixture records, clients, tokens or photos. The persistent administrator-release option forces public onboarding off even if a public-onboarding constant is accidentally enabled. Public listing business-rule approval remains unset.

Deployment preparation passed 77 local review checks and 14 local activation checks. Activation's final receipt is deployment confirmation, not a live functional test. Do not open the workspace or exercise live dealer/listing operations as a test.
