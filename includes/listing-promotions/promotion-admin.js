(function () {
    'use strict';

    function initPromotionGrant() {
        var config = window.autoAgoraPromotionsAdmin;
        var button = document.getElementById('autoagora-grant-promotion');
        var metaBox = document.getElementById('autoagora-listing-promotions');
        var status = document.getElementById('autoagora-promotion-action-status');

        if (!config || !button || !metaBox || !status) {
            return;
        }

        button.addEventListener('click', function () {
            if (button.disabled) {
                return;
            }

            var nonce = metaBox.querySelector('[name="autoagora_promotion_nonce"]');
            var tier = metaBox.querySelector('[name="autoagora_promotion_tier"]');
            var days = metaBox.querySelector('[name="autoagora_promotion_days"]');
            var startsAt = metaBox.querySelector('[name="autoagora_promotion_starts_at"]');
            var notes = metaBox.querySelector('[name="autoagora_promotion_notes"]');

            if (!nonce || !tier || !days || !startsAt || !notes) {
                status.textContent = config.genericError;
                status.style.color = '#b32d2e';
                return;
            }

            var data = new URLSearchParams();
            data.set('action', 'autoagora_grant_listing_promotion_ajax');
            data.set('listing_id', button.getAttribute('data-listing-id'));
            data.set('nonce', nonce.value);
            data.set('autoagora_promotion_tier', tier.value);
            data.set('autoagora_promotion_days', days.value);
            data.set('autoagora_promotion_starts_at', startsAt.value);
            data.set('autoagora_promotion_notes', notes.value);

            button.disabled = true;
            button.textContent = config.workingText;
            status.textContent = '';

            window.fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: data.toString()
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (response) {
                    if (!response.success) {
                        throw new Error(response.data && response.data.message ? response.data.message : config.genericError);
                    }
                    window.location.href = response.data.redirect_url;
                })
                .catch(function (error) {
                    status.textContent = error.message || config.genericError;
                    status.style.color = '#b32d2e';
                    button.disabled = false;
                    button.textContent = config.buttonText;
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPromotionGrant);
    } else {
        initPromotionGrant();
    }
}());

