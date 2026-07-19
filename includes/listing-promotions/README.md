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

Schema `1.2.0` also stores the amount, currency, refunded amount, and Stripe
Checkout Session ID on each paid promotion. These are immutable purchase
snapshots; later pricing changes do not rewrite historical payments.

Schema `1.3.0` adds immutable listing-title and seller-ID snapshots. Existing
rows are backfilled while their car posts still exist, and missing values are
captured again immediately before permanent deletion. Deleting a car cancels
only its unfinished active/scheduled promotions; all promotion and payment
records remain as audit history and can be identified as a deleted listing by
the central admin manager.

Stripe webhook receipts are stored separately in
`{$wpdb->prefix}autoagora_payment_events`. This small table records event IDs,
types, PaymentIntent references, processing state, attempts, and error codes;
it never stores the raw Stripe payload. The unique provider/event ID key makes
duplicate deliveries safe. Full refunds received before their Checkout
completion remain `pending` and are applied immediately after the matching
promotion is recorded, because Stripe does not guarantee event delivery order.

Current marketplace state is mirrored to protected postmeta keys prefixed with
`_autoagora_promotion_`. These are a query snapshot, not the authoritative
record. The old ACF `is_featured` value remains a Lift-equivalent fallback only
until a listing receives its first managed promotion.

## Lifecycle

The implemented states are `scheduled`, `active`, `expired`, `cancelled`, and
`refunded`. A five-minute WP-Cron event expires/starts due records and refreshes
the current snapshot. Manual grants and cancellations are available to
administrators in the car edit screen.

All tiers share one sequential queue. A new Lift or Showcase grant starts after
the latest unfinished promotion ends, so promotions never overlap and no paid
time is discarded. Promotions do not pause when a listing is sold or expires;
their wall-clock schedule continues while the listing is hidden. New purchases
are accepted only for published, active listings. Permanently deleting a car
cancels its unfinished records but retains their audit history.

## Payment integration

Stripe-hosted Checkout is implemented in `StripeGateway.php`. The first rollout
defaults to Stripe sandbox mode and will not accept live keys. Add these secrets
to `wp-config.php` above the "stop editing" line (never commit them):

```php
define('AUTOAGORA_STRIPE_MODE', 'test');
define('AUTOAGORA_STRIPE_SECRET_KEY', 'sk_test_...');
define('AUTOAGORA_STRIPE_WEBHOOK_SECRET', 'whsec_...');
```

A restricted `rk_test_...` key with permission to create Checkout Sessions can
be used instead of `sk_test_...`. Hosted Checkout does not require a publishable
key in this integration.

Register this Stripe sandbox webhook endpoint:

```text
https://autoagora.cy/wp-json/autoagora/v1/stripe/webhook
```

Subscribe it to `checkout.session.completed` and `charge.refunded`. Use the
signing secret for that exact sandbox webhook endpoint as
`AUTOAGORA_STRIPE_WEBHOOK_SECRET`.

### Payment log

Checkout, verified webhook, fulfillment, and refund outcomes are written as
compact JSON lines to a dedicated payment log inside WordPress `wp-content`:

```text
wp-content/autoagora-payment-logs/stripe-payments.php
```

The exact absolute path is shown in the **AutoAgora Promotions** box when an
administrator edits a car, and the directory is visible to WordPress file
manager plugins. The `.php` extension is intentional: every log and archive
starts with an exit guard, and the directory also receives Apache/IIS guard
files, so direct browser requests cannot expose payment records. The logger
never records Stripe keys, webhook signatures, raw payloads, email addresses,
or card details. It rotates each file at 2 MB and keeps two archives
(`stripe-payments.1.php` and `stripe-payments.2.php`), limiting normal storage
to about 6 MB.

The directory, size limit, or logging can be changed in `wp-config.php`:

```php
define('AUTOAGORA_PAYMENT_LOG_DIR', '/private/server/path/autoagora-logs');
define('AUTOAGORA_PAYMENT_LOG_MAX_BYTES', 2097152); // 64 KB to 10 MB.
define('AUTOAGORA_PAYMENT_LOG_ENABLED', true);
```

The default directory is created after the first Checkout or webhook action.
If WordPress cannot write there, one short fallback message is sent to the
normal PHP/WordPress error log and payment processing continues.

Seller Checkout offers four fixed durations for both tiers: 1, 3, 5, and 7
days. A day is exactly 24 hours. The browser submits only tier and duration;
the server validates the selection and calculates the final amount from the
configured daily price.

The default sandbox daily prices are intentionally small:

- AutoAgora Lift: EUR 1.00 per day
- AutoAgora Showcase: EUR 2.00 per day

Set production daily prices without editing feature code:

```php
define('AUTOAGORA_STRIPE_LIFT_DAILY_CENTS', 300);
define('AUTOAGORA_STRIPE_SHOWCASE_DAILY_CENTS', 500);
```

The older `AUTOAGORA_STRIPE_LIFT_AMOUNT_CENTS` and
`AUTOAGORA_STRIPE_SHOWCASE_AMOUNT_CENTS` constants remain temporary fallbacks
for a safe deployment. The old duration constants are no longer used.

Sellers start Checkout from `/my-listings/`. The browser supplies only listing
ID, tier, and one of the allowed day values. The server controls the daily
price and total amount, validates ownership and active listing state, then
redirects to `checkout.stripe.com`.

Each My Listings card shows the current promotion, a live remaining-time
countdown, the exact end time in the WordPress site timezone, and the number of
queued promotions. Opening **Manage promotion** shows every active/scheduled
record in start order with its exact start and end. These views read the custom
promotion table rather than the postmeta marketplace snapshot.

The Stripe line item includes the listing title and ID, for example `3 days
promotion for 2021 BMW X1, listing #23745`. AutoAgora does not prefill
`customer_email`; Stripe asks the payer to enter the email they want associated
with that Checkout Session.

The success redirect never grants a promotion. Only a raw-body, timestamped,
HMAC-SHA256 verified Stripe webhook can call:

```php
$result = autoagora_grant_paid_listing_promotion(
    $listing_id,
    'showcase',
    7 * DAY_IN_SECONDS,
    'stripe',
    $stripe_payment_intent_id,
    'Optional internal note',
    array(
        'amount_minor' => 2500,
        'currency' => 'eur',
        'stripe_checkout_session_id' => $stripe_checkout_session_id,
    )
);
```

The Stripe PaymentIntent ID is stored as `payment_reference`. The
`(payment_provider, payment_reference)` unique index and service checks make
repeat webhook delivery idempotent. A reused reference with different listing
or tier data returns `WP_Error` rather than silently granting the wrong item.
A short per-listing database advisory lock prevents simultaneous grants from
being assigned overlapping time windows. A separate per-PaymentIntent lock
serializes completion and refund webhooks. Existing payment rows are reconciled
again on every retry so a prior postmeta/snapshot failure can recover.
After an authenticated refund event, call
`autoagora_refund_paid_listing_promotion($provider, $reference)` to remove the
effective promotion and preserve the row with `refunded` status.

Live mode is deliberately locked. Going live requires explicit production
amounts/durations, live keys, a separate live webhook signing secret, and:

```php
define('AUTOAGORA_STRIPE_MODE', 'live');
define('AUTOAGORA_STRIPE_LIVE_ENABLED', true);
```

Do not enable that flag until the sandbox checklist passes.

### Sandbox checklist

1. Deploy the module, visit wp-admin once so schema `1.3.0` creates the payment
   event table and promotion snapshot columns, then add sandbox keys and register
   the webhook above.
2. In wp-admin, edit a car and confirm Stripe Checkout says `Ready`.
3. Log in as the listing owner and open `/my-listings/`.
4. Choose Promote, select Lift or Showcase, and select 1, 3, 5, or 7 days.
5. Pay in Stripe Checkout with card `4242 4242 4242 4242`, any future
   expiration date, and any three-digit CVC.
6. Confirm Stripe shows a successful `checkout.session.completed` delivery.
7. Confirm the promotion table contains an active row with source `payment`,
   provider `stripe`, and a `pi_...` payment reference, and the payment event
   table contains the matching `evt_...` receipt with status `processed`.
8. Confirm the listing badge and marketplace ordering changed.
9. Fully refund the test payment in Stripe and confirm `charge.refunded` changes
   the row to `refunded` and removes the effective promotion.

## Marketplace behavior

The listing query orders Showcase, Lift, then unpromoted listings before the
existing selected sort. This also applies to Best Match. `[car_listings
featured="true"]` now means any current promotion while retaining the legacy
fallback. Query cache generation is bumped whenever the effective promotion
changes. A deferred WP Rocket domain purge also runs so cached marketplace pages
do not keep stale badges or ordering.
