(function () {
    'use strict';

    function attemptId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    function init() {
        var config = window.autoAgoraStripeCheckout;
        var container = document.querySelector('.my-listings-container');
        if (!config || !container) {
            return;
        }

        container.addEventListener('click', function (event) {
            var button = event.target.closest('.autoagora-buy-promotion');
            if (!button || button.disabled) {
                return;
            }
            var panel = button.closest('.autoagora-promotion-purchase-panel');
            var status = panel ? panel.querySelector('.autoagora-promotion-checkout-status') : null;
            var buttons = panel ? panel.querySelectorAll('.autoagora-buy-promotion') : [button];
            var originalText = status ? status.textContent : '';
            var data = new URLSearchParams();
            data.set('action', config.action);
            data.set('nonce', config.nonce);
            data.set('listing_id', button.getAttribute('data-listing-id'));
            data.set('tier', button.getAttribute('data-tier'));
            data.set('attempt', attemptId());

            buttons.forEach(function (item) {
                item.disabled = true;
            });
            if (status) {
                status.textContent = config.workingText;
                status.classList.remove('is-error');
            }

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
                    if (!response.success || !response.data || !response.data.checkout_url) {
                        throw new Error(response.data && response.data.message ? response.data.message : config.genericError);
                    }
                    window.location.assign(response.data.checkout_url);
                })
                .catch(function (error) {
                    buttons.forEach(function (item) {
                        item.disabled = false;
                    });
                    if (status) {
                        status.textContent = error.message || originalText || config.genericError;
                        status.classList.add('is-error');
                    }
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());

