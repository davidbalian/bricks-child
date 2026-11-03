# Postaki production release — 3 November 2026

Production OAuth and verified seller signup are enabled for the approved Postaki integration. David approved the release and the production credential-creation action separately. The existing `davidbalian/bricks-child` repository and its **Bricks Child / main** Deployer for Git entry were used.

## Registration and contract

- Client ID: `postaki-production`; enabled, with the five existing API scopes.
- Exact registered callback: `https://app.postaki.app/api/v1/autoagora/callback`.
- Authorize: `https://autoagora.cy/?autoagora_connect=authorize`.
- Token: `https://autoagora.cy/wp-json/autoagora-dealer/v1/oauth/token`.
- Revoke: `https://autoagora.cy/wp-json/autoagora-dealer/v1/oauth/revoke`.
- API base: `https://autoagora.cy/wp-json/autoagora-dealer/v1`.
- Authorization Code + S256 PKCE and a confidential backend client secret; every seller must explicitly consent.

The secret is stored only outside the webroot and in the ignored private handoff; it is not in Git or this record. Production credentials, keys and callback are independent from staging. No staging test codes, accounts, stock, grants or tokens were copied. Existing tagged review data remains private. See [API contract](API.md) for fields, scopes, idempotency, photo requirements and errors.

Existing users keep their account type. New users may confirm the dealer/private signup hint and verify their real Cyprus phone through the existing production SMS provider. Token exchange and refresh include `account: {id, name}`. Listing ownership comes from the token; account-type changes stay with AutoAgora. New and updated cars remain **pending moderation**, unlike the isolated staging auto-publication override.

## Scope and evidence

- Deployed runtime commit: `01444d0`, from the production baseline `63d0f96`.
- Fourteen integration-related runtime files changed. All 6,851 baseline theme file hashes matched before deployment; all 6,857 release file hashes matched afterward. Other tracked theme source was unchanged.
- Deployer returned **Package updated successfully**. The next administrator receipt reported `public_release=true`, `private_mode=false`, `module_ready=true`, `signup_ready=true`, and the enabled client with its exact registered callback.
- Production prerequisites inspected: PHP 8.2.35, integration dependencies, existing environment-specific keys, safe seller roles, existing schema, configured Turnstile/Twilio, and InnoDB storage for the inspected core/integration tables.
- Local release checks passed: eight public-configuration cases, 14 installation checks, 79 private-workspace checks, 70 reviewer checks and 18 offline provider checks. Postaki separately reported successful staging connect, dealer/private signup, create, update and sold tests.
- **No production functional tests, test registrations, test grants, test cars or test SMS were performed.** Administrator readiness/configuration receipts do not prove live SMS delivery or Postaki's production deployment.
- The temporary administrator release helper was moved outside the webroot after registration. Only the theme's persistent integration code remains active.

## Activation and rollback

`Installation::load()` accepts the existing private configuration only when an environment-local release option is enabled. Public activation requires both `autoagora_dealer_public_release=1` and strict `public_enabled: true` in the private runtime JSON; verified listing rules are separately gated. The old administrator-only release option was cleared after successful registration. Existing review privacy hooks remain active.

The pre-release files, runtime configuration, release options and sanitized administrator receipt are retained privately under `.autoagora-dealer/postaki-release-20261103`, outside production's webroot. Do not restore the database or copy staging configuration into production.

To stop public partner access quickly, an operator can set `public_enabled` to `false` in that private runtime JSON. Requests then fail closed into the existing private-review boundary. To disable only Postaki and revoke its grants, use the existing operator command `wp autoagora dealer disable postaki-production`; stock is retained. A source rollback must also restore the saved release options/runtime configuration and preserve tagged review-data privacy.
