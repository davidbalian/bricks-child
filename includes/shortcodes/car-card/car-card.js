(function () {
    'use strict';

    function initSlider(el) {
        var track      = el.querySelector('.car-card-slider-track');
        var slides     = el.querySelectorAll('.car-card-slide');
        var counter    = el.querySelector('.car-card-counter');
        var btnLeft    = el.querySelector('.car-card-arrow-left');
        var btnRight   = el.querySelector('.car-card-arrow-right');
        var dots       = el.querySelectorAll('.car-card-dot');
        var total      = parseInt(el.dataset.total, 10) || slides.length;
        var slideCount = slides.length;
        var current    = 0;

        if (!track || slideCount === 0) return;

        function goTo(i) {
            if (i < 0) i = 0;
            if (i >= slideCount) i = slideCount - 1;
            current = i;
            track.style.transform = 'translateX(-' + (current * 100) + '%)';

            if (counter) counter.textContent = (current + 1) + '/' + total;

            // Arrow visibility
            if (btnLeft) {
                if (current === 0) btnLeft.classList.add('car-card-arrow-hidden');
                else               btnLeft.classList.remove('car-card-arrow-hidden');
            }
            if (btnRight) {
                if (current === slideCount - 1) btnRight.classList.add('car-card-arrow-hidden');
                else                             btnRight.classList.remove('car-card-arrow-hidden');
            }

            // Dot active state
            dots.forEach(function (d, idx) {
                if (idx === current) d.classList.add('car-card-dot-active');
                else                 d.classList.remove('car-card-dot-active');
            });
        }

        // Arrow click handlers
        if (btnLeft) {
            btnLeft.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                goTo(current - 1);
            });
        }
        if (btnRight) {
            btnRight.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                goTo(current + 1);
            });
        }

        // ── Touch / pointer swipe ──
        var startX = 0, startY = 0, dx = 0, dragging = false, locked = false, isHorizontal = false;

        track.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            // Don't capture pointer on interactive elements (e.g. "View All Images" link)
            if (e.target.closest('a, button:not(.car-card-arrow)')) return;
            dragging     = true;
            locked       = false;
            isHorizontal = false;
            startX       = e.clientX;
            startY       = e.clientY;
            dx           = 0;
            track.style.transition = 'none';
            track.setPointerCapture(e.pointerId);
        });

        track.addEventListener('pointermove', function (e) {
            if (!dragging) return;

            var moveX = e.clientX - startX;
            var moveY = e.clientY - startY;

            // Decide direction on first significant move
            if (!locked) {
                if (Math.abs(moveX) > 10 || Math.abs(moveY) > 10) {
                    locked = true;
                    isHorizontal = Math.abs(moveX) >= Math.abs(moveY);
                    if (!isHorizontal) {
                        // Vertical scroll — release immediately
                        dragging = false;
                        try { track.releasePointerCapture(e.pointerId); } catch (err) {}
                        track.style.transition = '';
                        goTo(current);
                        return;
                    }
                } else {
                    return;
                }
            }

            if (!isHorizontal) return;

            e.preventDefault();
            dx = moveX;

            // Rubber-band resistance at edges
            var resistance = 1;
            if ((current === 0 && dx > 0) || (current === slideCount - 1 && dx < 0)) {
                resistance = 0.3;
            }

            var offset = -(current * track.parentElement.offsetWidth) + (dx * resistance);
            track.style.transform = 'translateX(' + offset + 'px)';
        });

        function endDrag() {
            if (!dragging) return;
            dragging = false;
            track.style.transition = '';

            var swipeDx = dx;
            var threshold = track.parentElement.offsetWidth * 0.15;

            if (swipeDx < -threshold)      goTo(current + 1);
            else if (swipeDx > threshold)  goTo(current - 1);
            else                           goTo(current);

            // Reset dx after delay so ghost-click prevention still works
            setTimeout(function () { dx = 0; }, 300);
        }

        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);

        // Prevent ghost click on links after swipe
        track.addEventListener('click', function (e) {
            if (Math.abs(dx) > 10) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        goTo(0);
    }

    // ── Init all car-card sliders on page ──
    function initAll() {
        document.querySelectorAll('.car-card-slider:not([data-init])').forEach(function (el) {
            el.setAttribute('data-init', '1');
            initSlider(el);
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // ── Re-init after Jet Smart Filters AJAX / infinite scroll ──
    document.addEventListener('jet-smart-filters/inited', function () {
        if (typeof JetSmartFilters !== 'undefined' && JetSmartFilters.events) {
            JetSmartFilters.events.subscribe('ajaxFilters/updated', initAll);
        }
    });

    // Fallback: MutationObserver catches dynamically injected cards
    var mo = new MutationObserver(function (mutations) {
        var hasNew = false;
        for (var i = 0; i < mutations.length; i++) {
            if (mutations[i].addedNodes.length) { hasNew = true; break; }
        }
        if (hasNew) initAll();
    });
    mo.observe(document.body, { childList: true, subtree: true });
})();
