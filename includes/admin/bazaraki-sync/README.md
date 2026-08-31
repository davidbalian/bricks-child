# Bazaraki dealer synchronization

AutoAgora receives signed ZIP change packages from the separate Node worker. It
does not scrape Bazaraki during a page request or through WordPress cron.

## Production setup

1. Deploy the exporter outside the public WordPress/theme directory on the same
   server (or another private worker host) and run `npm install`.
2. Generate a random secret containing at least 32 characters. Define the same
   value as `AUTOAGORA_BAZARAKI_SYNC_SECRET` in `wp-config.php` and in the
   worker service environment. Never commit it.
3. In WordPress, open **Tools > Bazaraki Sync**, save the dealer profile, and
   start with **Dry run** enabled.
4. Set `autoagora.enabled` to `true` in the worker's `sync-config.json`.
5. Run the worker once. Confirm the run and queue counts in WordPress, turn off
   Dry run, then run it again.
6. Configure a real operating-system cron/service to run `node sync.cjs --all`
   daily. Do not use visitor-triggered WP-Cron for the browser worker. The
   recommended production batch size is one car with a short pause between
   processor calls.

The endpoints use HMAC-SHA256 over the timestamp, REST route, nonce, and exact
request body. Requests expire after five minutes and nonces cannot be replayed.
Packages are stored in a protected uploads directory and removed after the run
finishes.

Changed rows pass through the existing JSON importer validator, so AutoAgora's
field enums and make/model taxonomy are authoritative. New cars are pending.
Existing cars are matched by `_autoagora_import_source` and
`_autoagora_import_source_id` and are adopted without duplication.

Image bytes are included only for created or updated cars. The worker compares
ordered source URLs and SHA-256 content hashes; HTTP validators are used when
the image host supplies them. AutoAgora replaces a gallery only after every new
image has imported successfully, then removes old attachments owned by that
car. The importer crop (10% top and 10% bottom) applies to synced images too.

A missing advert is marked for review first and expires only after the configured
number of consecutive complete snapshots (three by default). Sync never changes
a manually sold listing back to active. One completion summary is sent to the
site administrator; per-car mail is suppressed only while that sync job runs.
Scraper/CAPTCHA/access failures are also reported through the signed API, shown
in the run history, and emailed to the site administrator.
