# Dealer Profile XLSX Import

WordPress admin path:

`Dealer Profiles > Import profiles`

Rollback path:

`Dealer Profiles > Delete imported profiles`

The importer accepts the research workbook's `All Research` or `Migration Ready`
worksheet. It validates and previews the file before changing data, then imports
20 rows per confirmed request.

## Identity And Re-imports

- `dealer_import_source_id` is the primary import identity.
- `dealer_dedupe_key` is a fallback identity when the source ID does not match.
- Re-imports update one matching unclaimed profile instead of creating another.
- Multiple database matches are treated as conflicts and skipped.
- Claimed, pending-claim, and rejected profiles are protected from workbook
  updates.
- New profiles are always created as unclaimed. Spreadsheet claim fields are
  never trusted.

## Publishing Options

- `Publish every valid imported profile` publishes ready and review rows.
- `Respect workbook post_status` leaves workbook draft rows as drafts.
- The default indexing mode sets `dealer_indexable` only when the published row
  has a name, location, and at least one public contact/presence field.

## Upload Safety

- Requires `manage_options` and WordPress nonces.
- Accepts `.xlsx` only, with a 10 MB compressed upload limit.
- Reads the ZIP archive in memory and never extracts archive paths.
- Enforces archive entry, expanded-size, XML, row, column, and cell limits.
- Rejects external worksheet relationships.
- Stores only validated JSON rows in a random, access-blocked temporary folder.
- Deletes completed sessions and removes abandoned session files after one day.

## Rollback

- Permanently deletes only profiles carrying a non-empty `dealer_import_source_id`.
- Runs in small automatic batches so the full research import can be removed.
- Protects claimed and pending-claim profiles.
- Requires `manage_options`, a WordPress nonce, a checkbox, and typed `DELETE`
  confirmation.
- Uses permanent deletion because trashed profiles are intentionally still found
  by the importer and would block a clean re-import.
