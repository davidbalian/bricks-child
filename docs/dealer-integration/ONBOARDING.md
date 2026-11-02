# Seller connection and signup

The Postaki contract is login or verified signup on AutoAgora, consent, then code/token exchange. All release work is restricted to staging7; production onboarding stays disabled. Existing clients, secrets, exact callbacks, scopes, listing routes and moderation/ownership rules are unchanged.

## User flow

1. Postaki opens the existing authorize URL with state, S256 challenge and optional `account_type=dealer` or `account_type=private`.
2. The branded AutoAgora sign-in form opens directly. Existing users enter their phone/username and password. The actual account type wins; the hint neither converts nor rejects an otherwise eligible account.
3. A new user chooses **Create an AutoAgora account**, confirms the account type and phone, verifies the six-digit code, then supplies their name (plus dealership name when applicable) and a password. The only assignable roles are `client` and `dealership`. Administrators/subscribers are not connection accounts.
4. The exact original OAuth request resumes. Consent identifies the signed-in account and offers **Use a different account** without losing state/PKCE. Cancel returns `access_denied` and the original state to the validated callback.
5. Both code exchange and every refresh return `account: {id, name}` alongside standard token fields. Postaki uses it for “Connected as”. It never sends an account ID to select listing ownership.

There is no public directory, fuzzy claim, account-claim approval or partner type-change endpoint. Invalid OAuth requests never enter signup. A valid request by an already signed-in ineligible account returns `access_denied` to the exact registered callback. Main-site registration/login and styling remain unchanged.

## Signup controls

- `AUTOAGORA_CONNECT_SIGNUP_ENABLED=true` is separate from the existing onboarding, API approval and private-seller gates.
- Browser/session CSRF is tied to the exact OAuth URL. Signup proof is bound to the browser and flow; phone/type are stored server-side. Altering submitted phone, role or account type at the final step cannot change them.
- OTP requests require Cyprus phone normalization. Production requires server-validated Turnstile success, hostname and `connect_signup` action before contacting Twilio Verify. Verification uses the SID of the sent challenge. Staging/local never contact those services.
- Production provider failures fail closed with generic messages. IP/phone send limits and five attempts per challenge constrain abuse. OTP and verified proof expire after ten minutes. Passwords/codes are never stored in the flow session or audit log.
- Final signup requires a 12–128 character matching password. Name fields are bounded; phone uniqueness is checked after verification, under a shared phone lock, including legacy formatting. An existing phone always requires existing-password login; it is never claimed, reset or converted by signup.
- Flow and phone locks prevent concurrent duplicate creation and proof reuse. No application grant is created until explicit OAuth consent.
- New account names and token metadata are escaped/serialized; only the authorized account ID/name are returned. Account identity is resolved from the issued token ledger, not from WordPress browser cookies or request IDs.

## Staging signup

Staging reads only hashed codes and an expiry from private `signup-tests.json` outside the webroot. `AUTOAGORA_CONNECT_TEST_PHONES` and `AUTOAGORA_CONNECT_TEST_EXPIRES` must be configured explicitly. The UI states that no SMS is sent. Only approved synthetic numbers can sign up, and they cannot claim an existing cloned customer account. Plaintext test codes remain in ignored local handoff files and are never committed or included in source deployment ZIPs.

Accounts created by this verified staging-only flow are marked `_ag_connect_staging_user=1` and allowed into the sandbox until its configured expiry. They have no administrator/backend/native REST access; API stock and dashboard actions stay owner-scoped. The staging browse pages include only designated test accounts and these new synthetic signup accounts. External services/cron remain disabled. Never deploy the staging database, test configuration or MU guard to production.

The staging MU guard also blocks anonymous native form/AJAX mutations and XML-RPC before WordPress handlers run. Administrators retain maintenance access. The isolated signup/OAuth routes and scoped API remain available with their own verification and ownership controls.

## Operator tools

**Tools → Seller onboarding** retains internal dealership search and the historical request queue for administrators only. Those records do not assign identities or grant partner access. Normal partner connection no longer requires account matching or that queue. Account-type changes stay in AutoAgora's existing operator/account-management process.

## Verification

```sh
python3 tests/dealer-integration/connect_fixture.py
python3 -u tests/dealer-integration/connect_checks.py
php tests/dealer-integration/signup_provider_checks.php
python3 tests/dealer-integration/connect_fixture.py restore
```

The loopback HTTP suite covers both existing roles with matching/different/missing hints, real consent/code/PKCE, initial and refreshed identity, name changes, no identity leakage on errors, verified signup for both types, failed/expired/replayed codes, tampered fields, duplicate/concurrent signup, safe returns, rate limits, actual car create/update/withdraw and cross-owner denial. Offline provider checks stub external transport and verify production bot/verification boundaries; they do not prove SMS delivery. Run the existing workspace/reviewer regression suites after restoring the normal private fixture. Hosted checks use only staging7, existing test clients and explicitly approved synthetic phones.

The opt-in `tests/dealer-integration/hosted/signup_checks.py` suite reads the ignored `.private/postaki-setup/signup-qa.json` test accounts and existing QA/client credentials. It creates staging cars, verifies their real pages/photos and owner-only dashboard access, and disconnects only its QA grants. It retains the dealer demo car and withdraws the private demo car. Never substitute real customer accounts or the partner's fresh handoff numbers. Hosted XML-RPC requests may be terminated by the hosting rule before WordPress; the early PHP exit is exercised in the local dashboard adapter instead.
