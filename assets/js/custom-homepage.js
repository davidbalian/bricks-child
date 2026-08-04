(function () {
    'use strict';

    var scrollRows = Array.prototype.slice.call(document.querySelectorAll('.custom-homepage-cars-section:not(.is-latest) .custom-homepage-car-row'));

    function updateScrollShadows(row) {
        var shell = row.closest('.custom-homepage-car-row-shell');
        if (!shell) {
            return;
        }

        var maxScrollLeft = Math.max(row.scrollWidth - row.clientWidth, 0);
        shell.classList.toggle('can-scroll-left', row.scrollLeft > 2);
        shell.classList.toggle('can-scroll-right', row.scrollLeft < maxScrollLeft - 2);
    }

    scrollRows.forEach(function (row) {
        updateScrollShadows(row);
        row.addEventListener('scroll', function () {
            updateScrollShadows(row);
        }, { passive: true });
    });

    window.addEventListener('resize', function () {
        scrollRows.forEach(updateScrollShadows);
    });

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
