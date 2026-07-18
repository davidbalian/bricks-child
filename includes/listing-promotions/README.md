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

The default sandbox packages are intentionally small and short:

- AutoAgora Lift: EUR 1.00 for 1 hour
- AutoAgora Showcase: EUR 2.00 for 1 hour

They can be overridden without editing feature code:

```php
define('AUTOAGORA_STRIPE_LIFT_AMOUNT_CENTS', 100);
define('AUTOAGORA_STRIPE_LIFT_DURATION_SECONDS', 3600);
define('AUTOAGORA_STRIPE_SHOWCASE_AMOUNT_CENTS', 200);
define('AUTOAGORA_STRIPE_SHOWCASE_DURATION_SECONDS', 3600);
```

Sellers start Checkout from `/my-listings/`. The browser supplies only listing
ID and tier. The server controls amount/duration, validates ownership and active
listing state, then redirects to `checkout.stripe.com`.

The success redirect never grants a promotion. Only a raw-body, timestamped,
HMAC-SHA256 verified Stripe webhook can call:

```php
$result = autoagora_grant_paid_listing_promotion(
    $listing_id,
    'showcase',
    7 * DAY_IN_SECONDS,
    'stripe',
    $stripe_payment_intent_id,
    'Optional internal note'
);
```

The Stripe PaymentIntent ID is stored as `payment_reference`. The
`(payment_provider, payment_reference)` unique index and service checks make
repeat webhook delivery idempotent. A reused reference with different listing
or tier data returns `WP_Error` rather than silently granting the wrong item.
A short per-listing database advisory lock prevents simultaneous grants from
being assigned overlapping time windows.
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

1. Add sandbox keys and register the webhook above.
2. In wp-admin, edit a car and confirm Stripe Checkout says `Ready`.
3. Log in as the listing owner and open `/my-listings/`.
4. Choose Promote, then Lift or Showcase.
5. Pay in Stripe Checkout with card `4242 4242 4242 4242`, any future
   expiration date, and any three-digit CVC.
6. Confirm Stripe shows a successful `checkout.session.completed` delivery.
7. Confirm the promotion table contains an active row with source `payment`,
   provider `stripe`, and a `pi_...` payment reference.
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
