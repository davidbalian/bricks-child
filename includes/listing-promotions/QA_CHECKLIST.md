# AutoAgora Promotions QA Checklist

Run all payment tests in Stripe test mode on staging. Use a disposable seller,
disposable car listings, and Stripe card `4242 4242 4242 4242` with any future
expiry and any three-digit CVC. Do not enable live mode until every required
test below passes.

## Deployment and database

| Action | Expected result |
|---|---|
| Deploy the theme and visit any wp-admin page once. | The schema option becomes `1.3.0`; the promotion table contains `listing_title_snapshot` and `seller_id_snapshot`; the payment-event table exists. |
| Inspect older promotion rows whose cars still exist. | Empty title/seller snapshots are backfilled without changing tier, status, dates, amount, or payment references. |
| Open **Cars > Promotions** as an administrator. | The page loads without PHP errors and shows Stripe readiness, promotion records, and payment events. |
| Try to open the manager as a non-administrator. | Access is denied; no promotion/payment data is displayed. |

## Seller pricing and queue preview

| Action | Expected result |
|---|---|
| Open Promote on an active listing with no queue. | The panel says the promotion starts after Stripe confirms payment. |
| Open a promotion panel without making a duration choice. | No day is preselected; the heading says **Select duration**, the total/schedule remain placeholders, and checkout stays disabled. |
| Select Lift and each duration: 1, 3, 5, 7 days. | The amount equals Lift daily price multiplied by the selected days; the duration label updates; the preview remains immediate. |
| Select Showcase and each duration: 1, 3, 5, 7 days. | The amount equals Showcase daily price multiplied by the selected days; no browser-supplied price is trusted by the server. |
| Open Promote on a listing with an active or scheduled promotion. | The panel shows an expected start after the latest queued end and an expected end exactly the selected duration later. |
| Change tier or duration. | The preview refreshes from the server and the dates/label reflect the new selection. |
| Select a duration in a scrollable promotion panel. | The panel smoothly scrolls to reveal the total, schedule preview, and secure-checkout button without scrolling the underlying page; reduced-motion preferences are respected. |
| Cause the queue to change in another tab, then continue. | Checkout does not immediately redirect; the UI shows the updated schedule and asks the seller to review and click again. |
| Attempt Checkout for another seller's listing by changing the request. | The request is rejected and no Stripe Checkout Session or promotion row is created. |
| Attempt Checkout for a sold, expired, draft, or deleted listing. | The request is rejected before payment and no Checkout Session is created. |

## Initial approval purchases

| Action | Expected result |
|---|---|
| Submit a new car and inspect the success page. | A non-blocking promotion card offers Lift and Showcase for that exact owned listing and states that paid time begins only after approval. |
| Open the success-page promotion options at 320px and 390px viewport widths. | A centered bottom sheet stays fully inside the viewport and above the fixed mobile navigation; the background cannot scroll, tier choices are readable full-width rows, the sheet scrolls internally, and tapping the backdrop, close button, or Escape closes it. |
| Complete Checkout while the new listing is still pending. | One paid row is created with status `awaiting_approval`, full `duration_seconds`, and null `starts_at`/`ends_at`; no marketplace badge appears. |
| Purchase multiple promotions before approval. | Every payment creates one waiting row and the seller timeline shows them waiting in purchase order. |
| Approve and publish the listing for the first time. | The first waiting promotion becomes active at approval time; additional waiting promotions become sequentially scheduled with their full durations. |
| Move that published listing back to pending for an edit and approve it again. | Existing promotion time continues draining; no promotion restarts and no start/end dates are changed. |
| Buy a promotion while a previously published listing is pending after an edit. | It joins the normal queue immediately rather than receiving `awaiting_approval`. |
| Approve the listing between schedule preview and Checkout creation. | The signature changes; the seller reviews the new immediate schedule before continuing. |
| Approve the listing after Checkout is created but before its webhook is fulfilled. | Fulfillment detects the published listing and starts/queues the promotion normally rather than leaving it waiting. |
| Trash a never-published listing with a paid waiting promotion. | The row becomes `refund_required`, never starts, and the central manager tells the administrator to refund it through Stripe. |
| Fully refund that flagged payment in Stripe. | The row becomes `refunded` and the refunded amount is preserved. |

## Successful Stripe payments

Repeat the following for Lift and Showcase, covering 1, 3, 5, and 7 days across
the test run.

| Action | Expected result |
|---|---|
| Continue from the seller panel. | Stripe Checkout displays the tier, duration, listing title and ID, amount, and immediate/expected-start wording. Email is not prefilled by AutoAgora. |
| Cancel from Stripe Checkout. | The seller returns to My Listings; no promotion row is created and the existing queue is unchanged. |
| Complete payment. | Stripe redirects to My Listings, but the redirect itself does not grant anything; the verified webhook performs fulfillment. |
| Inspect Stripe webhook delivery. | `checkout.session.completed` receives HTTP 200 after successful processing. |
| Inspect the promotion table. | Exactly one row exists for the PaymentIntent. It has source `payment`, provider `stripe`, the correct tier/duration/amount/currency/session, and `active` if the queue was empty or `scheduled` if queued. |
| Inspect the payment-event table. | One matching event receipt exists with status `processed`, attempts at least `1`, and no error code. |
| Inspect the payment log. | Compact entries include checkout request/session, verified webhook, and granted promotion; no keys, signatures, email, or card details appear. |
| Inspect My Listings. | The active promotion countdown and exact end appear; queued purchases appear in chronological order with exact expected start/end. |
| Inspect marketplace cards/order. | Only the currently active tier badge appears and promoted ordering is Showcase, Lift, then regular listings. |

## Queue and lifecycle

| Action | Expected result |
|---|---|
| Buy Lift while Lift is active. | The new Lift row starts when the existing Lift ends; no overlap occurs. |
| Buy Showcase while Lift is active. | Showcase is scheduled after Lift; Lift is not paused or shortened. |
| Buy Lift while Showcase is active. | Lift is scheduled after Showcase. |
| Buy Showcase while Showcase is active. | The new Showcase extends the queue after the existing Showcase. |
| Mark a promoted listing sold. | The listing disappears from normal buyer results, but promotion dates/status continue on their original wall-clock schedule. |
| Let a promoted listing expire. | It is hidden from buyers; promotion time is not restored or paused. |
| Reactivate the listing. | Only time still remaining at that moment is effective; elapsed hidden time is not returned. |
| Reach an active promotion's end and run/allow WP-Cron. | The row becomes `expired`, the next due row becomes `active`, and badge/order/seller timeline update. The five-minute reconciliation job is the fallback window. |

## Duplicate, delayed, and refund events

| Action | Expected result |
|---|---|
| Resend the same completed event from Stripe. | HTTP 200 with duplicate handling; no second promotion row is created. |
| Temporarily make the webhook endpoint return an error, then restore it and resend/retry. | The same receipt increments attempts and eventually becomes `processed`; fulfillment remains exactly once. |
| Inspect a failed or pending event in **Cars > Promotions**. | It is visible with status, attempt count, error code, object reference, and a Stripe event link. |
| Fully refund a paid promotion in Stripe. | `charge.refunded` is processed, the promotion becomes `refunded`, refunded amount is stored, and it stops being the effective tier. |
| Resend the same refund event. | No duplicate refund effect occurs; the event is treated idempotently. |
| Deliver a full refund before its completed Checkout event in a controlled Stripe CLI test. | The refund receipt remains `pending`; after Checkout completion arrives, the promotion is created and immediately changed to `refunded`, and both receipts become processed. |
| Issue only a partial refund. | The event is recorded as processed/ignored and the promotion remains effective; current business rules remove promotions only on full refund. |

## Administration and deletion audit

| Action | Expected result |
|---|---|
| Search/filter promotions by ID, listing title, seller, PaymentIntent, session, tier, status, source, and dates. | Only matching records appear; pagination retains the selected filters. |
| Grant a manual promotion from **Cars > Promotions**. | It joins the same queue, records source `manual`, administrator ID and note, and has no paid amount. |
| Cancel an active or scheduled promotion from the manager. | The action requires a valid nonce, warns that cancellation is not a refund, changes the row to `cancelled`, reconciles the current snapshot, and preserves its history. |
| Permanently delete a promoted listing. | Its active/scheduled rows become `cancelled`; title, listing ID, payment data and dates remain; the manager labels it as deleted. |
| Permanently delete the seller account. | Matching `seller_id_snapshot` values become `0`; the manager shows `Anonymized`; no seller name, email, or phone is retained in promotion storage. |
| Permanently delete or transfer a listing after Checkout opens but before payment completes. | Fulfillment does not attach the payment to the missing/new owner's listing. The failed event is visible in the manager and the administrator refunds it from the linked Stripe record. |

## Production launch gate

| Action | Expected result |
|---|---|
| Configure live mode without explicit daily-price constants, HTTPS, valid live key/secret, or the live-enabled flag. | The readiness screen reports the exact blocker and Checkout remains disabled. |
| Add explicit Lift/Showcase daily cents, live key, live endpoint signing secret, HTTPS, and `AUTOAGORA_STRIPE_LIVE_ENABLED=true`. | The readiness screen reports Checkout configuration ready and uses live Stripe Dashboard links. |
| Create the live webhook destination. | It points to the displayed AutoAgora REST URL and subscribes only to `checkout.session.completed` and `charge.refunded`. |
| Make one small real purchase. | The live payment, event receipt, promotion row, seller timeline, badge, ranking, and compact log all match the sandbox behavior. |
| Fully refund that purchase. | Stripe delivers the refund event, the row becomes `refunded`, and the effective promotion is removed/reconciled. |

Go live only when the required staging rows pass, the payment-event attention
count is understood or zero, WP-Cron is running reliably, and the final small
live payment/refund succeeds.
