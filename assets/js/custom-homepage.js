(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.custom-homepage-scroll-button');
        if (!button) {
            return;
        }

        var targetId = button.getAttribute('data-scroll-target');
        var direction = button.getAttribute('data-scroll-direction');
        var row = targetId ? document.getElementById(targetId) : null;

        if (!row) {
            return;
        }

        var amount = Math.max(row.clientWidth * 0.85, 280);
        row.scrollBy({
            left: direction === 'previous' ? -amount : amount,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
        });
    });
}());
