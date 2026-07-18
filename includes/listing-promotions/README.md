# Listing promotions

Listing promotions are separate from `listing_state`. A car can have many
promotion records, but one record is effective at a time. New grants queue
after an existing active/scheduled grant so no purchased duration is lost.

## Tiers

- `priority`: AutoAgora Lift (priority 10)
- `showcase`: AutoAgora Showcase (priority 20)

## Storage

The authoritative history is stored in
`{$wpdb->prefix}autoagora_listing_promotions`. The module's versioned `dbDelta`
installer accepts the table that was initially created through WP Data Access
and maintains the same columns and indexes. There is intentionally no physical
foreign key to `wp_posts`; `listing_id` is validated by the service, and rows
are retained as audit history when a listing is deleted.

Current marketplace state is mirrored to protected postmeta keys prefixed with
`_autoagora_promotion_`. These are a query snapshot, not the authoritative
record. The old ACF `is_featured` value remains a Lift-equivalent fallback only
until a listing receives its first managed promotion.

## Lifecycle

The implemented states are `scheduled`, `active`, `expired`, `cancelled`, and
`refunded`. A five-minute WP-Cron event expires/starts due records and refreshes
the current snapshot. Manual grants and cancellations are available to
administrators in the car edit screen.

## Payment integration

There is deliberately no public webhook before a payment provider is selected.
After a provider-specific handler has authenticated the webhook and verified a
successful payment, it should call:

```php
$result = autoagora_grant_paid_listing_promotion(
    $listing_id,
    'showcase',
    7 * DAY_IN_SECONDS,
    'provider-slug',
    $provider_event_or_payment_id,
    'Optional internal note'
);
```

The `(payment_provider, payment_reference)` unique index and service checks make
repeat webhook delivery idempotent. A reused reference with different listing
or tier data returns `WP_Error` rather than silently granting the wrong item.
A short per-listing database advisory lock prevents simultaneous grants from
being assigned overlapping time windows.
After an authenticated refund event, call
`autoagora_refund_paid_listing_promotion($provider, $reference)` to remove the
effective promotion and preserve the row with `refunded` status.

## Marketplace behavior

The listing query orders Showcase, Lift, then unpromoted listings before the
existing selected sort. This also applies to Best Match. `[car_listings
featured="true"]` now means any current promotion while retaining the legacy
fallback. Query cache generation is bumped whenever the effective promotion
changes.
