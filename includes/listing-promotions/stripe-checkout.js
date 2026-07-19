(function () {
    'use strict';

    function attemptId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    function money(amountMinor, config) {
        var amount = Math.max(0, Number(amountMinor) || 0) / 100;
        if (window.Intl && typeof window.Intl.NumberFormat === 'function') {
            return new Intl.NumberFormat(config.locale || 'en', {
                style: 'currency',
                currency: config.currency || 'EUR'
            }).format(amount);
        }
        return '€' + amount.toFixed(2);
    }

    function selected(panel, selector) {
        return panel.querySelector(selector + '.is-selected');
    }

    function updateSummary(panel, config) {
        var tier = selected(panel, '.autoagora-promotion-tier-option');
        var duration = selected(panel, '.autoagora-promotion-day-option');
        if (!tier || !duration) {
            return;
        }

        var days = Number(duration.getAttribute('data-days')) || 1;
        var dailyAmount = Number(tier.getAttribute('data-daily-amount')) || 0;
        var amount = panel.querySelector('.autoagora-promotion-total-amount');
        var label = panel.querySelector('.autoagora-promotion-total-label');
        if (amount) {
            amount.textContent = money(dailyAmount * days, config);
        }
        if (label) {
            label.textContent = tier.getAttribute('data-label') + ' for ' + days + ' ' + (days === 1 ? 'day' : 'days');
        }
    }

    function selectOption(panel, option, selector, config) {
        panel.querySelectorAll(selector).forEach(function (item) {
            var isSelected = item === option;
            item.classList.toggle('is-selected', isSelected);
            item.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        delete panel.dataset.checkoutAttempt;
        updateSummary(panel, config);
    }

    function remainingTime(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        if (seconds < 60) {
            return 'Less than 1 minute';
        }

        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var parts = [];
        if (days > 0) {
            parts.push(days + ' ' + (days === 1 ? 'day' : 'days'));
        }
        if (hours > 0 && parts.length < 2) {
            parts.push(hours + ' ' + (hours === 1 ? 'hour' : 'hours'));
        }
        if (minutes > 0 && parts.length < 2) {
            parts.push(minutes + ' ' + (minutes === 1 ? 'minute' : 'minutes'));
        }
        return parts.join(' ');
    }

    function updatePromotionCountdowns(container) {
        var now = Math.floor(Date.now() / 1000);
        container.querySelectorAll('[data-promotion-end-timestamp]').forEach(function (element) {
            var end = Number(element.getAttribute('data-promotion-end-timestamp')) || 0;
            if (end > 0) {
                element.textContent = remainingTime(end - now) + ' left';
            }
        });
    }

    function init() {
        var config = window.autoAgoraStripeCheckout;
        var container = document.querySelector('.my-listings-container');
        if (!config || !container) {
            return;
        }

        container.querySelectorAll('.autoagora-promotion-purchase-panel').forEach(function (panel) {
            updateSummary(panel, config);
        });
        updatePromotionCountdowns(container);
        window.setInterval(function () {
            updatePromotionCountdowns(container);
        }, 60000);

        container.addEventListener('click', function (event) {
            var tierOption = event.target.closest('.autoagora-promotion-tier-option');
            if (tierOption && !tierOption.disabled) {
                selectOption(
                    tierOption.closest('.autoagora-promotion-purchase-panel'),
                    tierOption,
                    '.autoagora-promotion-tier-option',
                    config
                );
                return;
            }

            var dayOption = event.target.closest('.autoagora-promotion-day-option');
            if (dayOption && !dayOption.disabled) {
                selectOption(
                    dayOption.closest('.autoagora-promotion-purchase-panel'),
                    dayOption,
                    '.autoagora-promotion-day-option',
                    config
                );
                return;
            }

            var button = event.target.closest('.autoagora-buy-promotion');
            if (!button || button.disabled) {
                return;
            }

            var panel = button.closest('.autoagora-promotion-purchase-panel');
            var tier = panel ? selected(panel, '.autoagora-promotion-tier-option') : null;
            var duration = panel ? selected(panel, '.autoagora-promotion-day-option') : null;
            var status = panel ? panel.querySelector('.autoagora-promotion-checkout-status') : null;
            if (!panel || !tier || !duration) {
                if (status) {
                    status.textContent = config.genericError;
                    status.classList.add('is-error');
                }
                return;
            }

            var controls = panel.querySelectorAll('button');
            var data = new URLSearchParams();
            if (!panel.dataset.checkoutAttempt) {
                panel.dataset.checkoutAttempt = attemptId();
            }
            data.set('action', config.action);
            data.set('nonce', config.nonce);
            data.set('listing_id', button.getAttribute('data-listing-id'));
            data.set('tier', tier.getAttribute('data-tier'));
            data.set('days', duration.getAttribute('data-days'));
            data.set('attempt', panel.dataset.checkoutAttempt);

            controls.forEach(function (item) {
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
                    controls.forEach(function (item) {
                        item.disabled = false;
                    });
                    if (status) {
                        status.textContent = error.message || config.genericError;
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
