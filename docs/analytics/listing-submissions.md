# Successful listing submissions

Event: `listing_submitted`, sent only to AutoAgora GA4 `G-06RRVCP0GS`.

The website add-listing handler queues a receipt only after the new pending car,
fields and photos have been saved. On the success redirect the theme validates
ownership, login session and receipt age, then atomically consumes the receipt.
Reloads, forged URLs, edits, imports and dealer API writes do not emit this event.
The small footer script uses the existing Google tag (including its consent and
session handling), waits up to 15 seconds for it, and sends listing_id,
submission_method=website and listing_status=pending. It installs no second tag
and sends no seller contact information. Listing approval/publication is a
separate action and is not measured by this event.

## GA4 configuration and reporting

On 5 November 2026, `listing_submitted` was registered in property 494211318 as a
code-based key event, once per event, with no default monetary value.
Existing key events were preserved; `ads_conversion_PAGE_VIEW_1` is not a count
of completed listings. Always select `listing_submitted` when assessing results.

Use Reports > Traffic acquisition, select `listing_submitted` in the Key events
column, and use Session source / medium or Session campaign as the dimension.
GA4 associates the event with the existing browser session; this code does not
replace session attribution with a last-page referrer or arbitrary stored UTMs.
For manually distributed campaign links, provide `utm_source`, `utm_medium` and
`utm_campaign` on the original landing URL. Preserve Google Ads auto-tagging.
Campaign names are available only where campaigns were tagged or linked.
No custom dimension is needed for standard source/medium/campaign reporting.

This starts collecting after deployment and does not backfill historical
submissions. Browser blocking, consent, abandoning the redirect, or a network
failure can prevent collection; it is analytics, not an accounting ledger.
The one-time server receipt intentionally favours avoiding duplicates over
retrying delivery across page loads. The database records remain authoritative.

## Verification

- `php -l` on the two PHP runtime files.
- `php tests/listing-submission-analytics/checks.php`: 11 offline contract checks.
- `node --check includes/user-manage-listings/listing-submission-analytics.js`.
- `node tests/listing-submission-analytics/checks.js`: immediate/delayed tag checks.
- On the next real successful website submission, verify the event in Realtime;
  processed acquisition reporting can take 24–48 hours. Do not submit a fake
  production listing merely to seed Analytics.

Deployment and live delivery evidence must be recorded separately from these
local checks. Rollback: revert the tracking commit; the unused GA4 key event can
remain registered without receiving data.
