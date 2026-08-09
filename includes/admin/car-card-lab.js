(function () {
    'use strict';

    function init() {
        var filters = document.querySelectorAll('[data-card-lab-filter]');
        var variants = document.querySelectorAll('.autoagora-card-lab__variant');
        var visibleLabel = document.querySelector('.autoagora-card-lab__visible');

        if (!filters.length || !variants.length) {
            return;
        }

        function applyFilters() {
            var values = {};
            var visible = 0;

            filters.forEach(function (filter) {
                values[filter.getAttribute('data-card-lab-filter')] = filter.value;
            });

            variants.forEach(function (variant) {
                var matches = Object.keys(values).every(function (key) {
                    return values[key] === 'all' || variant.getAttribute('data-' + key) === values[key];
                });
                variant.hidden = !matches;
                if (matches) {
                    visible += 1;
                }
            });

            if (visibleLabel) {
                visibleLabel.textContent = visible + ' combination' + (visible === 1 ? '' : 's') + ' shown';
            }
        }

        filters.forEach(function (filter) {
            filter.addEventListener('change', applyFilters);
        });
        applyFilters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
