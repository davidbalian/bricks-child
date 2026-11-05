/* Uses the existing Google tag and its consent/session/campaign handling. */
(function () {
    'use strict';
    var receipt = window.autoagoraListingSubmission;
    if (!receipt || !receipt.listingId || !receipt.measurementId) return;
    var attempts = 0;
    function send() {
        if (typeof window.gtag !== 'function') {
            if (++attempts < 60) window.setTimeout(send, 250);
            return;
        }
        window.gtag('event', 'listing_submitted', {
            send_to: receipt.measurementId,
            listing_id: receipt.listingId,
            submission_method: 'website',
            listing_status: 'pending'
        });
        // No names, phone numbers, addresses or listing descriptions are sent.
    }
    send();
}());
