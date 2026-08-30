# Car JSON + Images Importer

WordPress administrators can open **Tools → Import Cars (JSON)** and upload a
ZIP containing:

```text
listings.json
images/
  {source-id}/
    01.webp
    02.webp
```

The separate `autoagora-bazaraki-exporter` tool produces this structure. It is
kept outside the WordPress theme repository because its browser runtime,
downloaded images, and generated packages are deployment inputs rather than
website source code.

## Workflow

1. Upload the ZIP and choose the WordPress user who will own the listings.
2. Optionally enter a fallback city, district, address, latitude, and longitude.
3. Review row-level enum, numeric, taxonomy, duplicate, path, and image checks.
4. Confirm the preview. The importer processes one valid car per request.
5. Review the resulting pending listings before publishing.

## Safety and data contracts

- Requires `manage_options` and WordPress nonces for upload, confirmation, and
  each processing request.
- Accepts at most 500 cars, 40 images per car, 25 MB per image, and a package no
  larger than the lower of 512 MB or the WordPress upload limit.
- Reads only `listings.json` and explicitly referenced safe image paths from the
  ZIP; path traversal and unsupported files are rejected.
- Uses AutoAgora's existing enum and numeric validation functions.
- Automatically normalizes known Bazaraki spelling and category variants before
  validation (for example `Grey` to `Gray`, `City` to `Car Derived Van`, and
  `Citroen` to `Citroën`). Unknown values are rejected instead of being guessed.
- Requires make/model values to exist in `simple_jsons`; missing live taxonomy
  terms are created from that approved source and assigned hierarchically.
- Deduplicates on `_autoagora_import_source` plus
  `_autoagora_import_source_id`.
- Creates `car` posts as `pending`, assigns `listing_state = active`, imports
  images into the WordPress media library, and stores attachment IDs in
  `car_images`.
- Automatically advances through the validated rows without requiring the
  administrator to press Continue for each car.
- Crops every imported image by 10% from the top and 10% from the bottom before
  saving it to the WordPress media library; the full width is preserved.
- If an individual car fails, the importer removes the post and attachments it
  created for that row.

Uploaded import sessions expire after 24 hours. Session directories have random
tokens and contain access-denial files; do not expose or share them.
