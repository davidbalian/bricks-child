(function () {
    'use strict';

    function initDescriptionToggle() {
        var strings = window.autoagoraSingleCar || {};
        var description = document.querySelector('[data-single-car-description]');
        var button = document.querySelector('[data-single-car-read-more]');
        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var desktopColumns = window.matchMedia('(min-width: 821px)');
        var overviewSection = description && description.closest('.autoagora-single-car__overview-section');
        var specificationColumn = document.querySelector('.autoagora-single-car__spec-column');
        var collapsedHeight = 0;
        var resizeFrame = null;

        if (!description || !button) {
            return;
        }

        function pixelValue(value) {
            var parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function calculateCollapsedHeight() {
            var fallbackHeight = 21 * pixelValue(window.getComputedStyle(document.documentElement).fontSize || '16');

            if (!desktopColumns.matches || !overviewSection || !specificationColumn) {
                description.style.removeProperty('--single-car-overview-collapsed-height');
                return fallbackHeight;
            }

            var heading = overviewSection.querySelector('h2');
            var headingStyle = heading ? window.getComputedStyle(heading) : null;
            var buttonStyle = window.getComputedStyle(button);
            var sectionChrome = (heading ? heading.getBoundingClientRect().height : 0)
                + (headingStyle ? pixelValue(headingStyle.marginBottom) : 0)
                + button.getBoundingClientRect().height
                + pixelValue(buttonStyle.marginTop);
            var availableHeight = Math.max(160, specificationColumn.getBoundingClientRect().height - sectionChrome);

            description.style.setProperty('--single-car-overview-collapsed-height', availableHeight + 'px');
            return availableHeight;
        }

        function refreshCollapsedLayout() {
            button.hidden = false;
            collapsedHeight = calculateCollapsedHeight();

            if (!description.classList.contains('is-expanded')) {
                if (description.scrollHeight <= collapsedHeight + 8) {
                    description.classList.remove('is-collapsible');
                    button.hidden = true;
                } else {
                    description.classList.add('is-collapsible');
                }
            }
        }

        refreshCollapsedLayout();

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(refreshCollapsedLayout);
        }

        button.addEventListener('click', function () {
            var expanded = !description.classList.contains('is-expanded');
            var startHeight = description.getBoundingClientRect().height;

            description.classList.toggle('is-expanded', expanded);
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.textContent = expanded ? (strings.readLess || 'Read Less') : (strings.readMore || 'Read More');

            if (prefersReducedMotion || typeof description.animate !== 'function') {
                return;
            }

            var endHeight = expanded ? description.scrollHeight : collapsedHeight;
            var distance = Math.abs(endHeight - startHeight);
            var duration = Math.min(520, Math.max(300, 260 + (distance * 0.18)));

            description.style.maxHeight = 'none';
            description.style.height = startHeight + 'px';
            button.disabled = true;
            var animation = description.animate(
                [
                    { height: startHeight + 'px' },
                    { height: endHeight + 'px' }
                ],
                {
                    duration: duration,
                    easing: 'cubic-bezier(.22, 1, .36, 1)',
                    fill: 'both'
                }
            );

            animation.onfinish = function () {
                description.style.height = '';
                description.style.maxHeight = '';
                button.disabled = false;
            };
        });

        window.addEventListener('resize', function () {
            if (resizeFrame) {
                window.cancelAnimationFrame(resizeFrame);
            }
            resizeFrame = window.requestAnimationFrame(function () {
                refreshCollapsedLayout();
                resizeFrame = null;
            });
        });
    }

    function initLocationMap() {
        var mapElement = document.getElementById('autoagora-single-car-map');
        var config = window.autoagoraSingleCar && window.autoagoraSingleCar.map;

        if (!mapElement || !config || !window.google || !window.google.maps) {
            return;
        }

        var position = {
            lat: Number(config.latitude),
            lng: Number(config.longitude)
        };

        if (!Number.isFinite(position.lat) || !Number.isFinite(position.lng)) {
            return;
        }

        var map = new window.google.maps.Map(mapElement, {
            center: position,
            zoom: Number(config.zoom) || 15,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            clickableIcons: false
        });

        new window.google.maps.Marker({
            position: position,
            map: map
        });
    }

    function initSingleCarPage() {
        initDescriptionToggle();
        initLocationMap();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSingleCarPage);
    } else {
        initSingleCarPage();
    }
}());
