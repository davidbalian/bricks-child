/**
 * Single Car Template Gallery — vanilla JS slider (same engine as car-card slider)
 */
var scgActiveContainer = null;
var scgGlobalKeydownBound = false;

/**
 * Scroll a thumb horizontally inside its strip only (updates strip.scrollLeft).
 * Avoids scrollIntoView, which also scrolls the window/document to bring the element into view.
 */
function scgScrollThumbIntoStrip(strip, thumb) {
    if (!strip || !thumb || !strip.contains(thumb)) return;
    var pad = 6;
    var s = strip.getBoundingClientRect();
    var t = thumb.getBoundingClientRect();
    if (t.left < s.left + pad) {
        strip.scrollLeft += t.left - s.left - pad;
    } else if (t.right > s.right - pad) {
        strip.scrollLeft += t.right - s.right + pad;
    }
}

function scgPreventNavButtonFocusScroll(btn) {
    if (!btn) return;
    btn.addEventListener('mousedown', function (e) {
        if (e.button === 0) e.preventDefault();
    });
}

function scgBindGlobalKeydown() {
    if (scgGlobalKeydownBound) return;
    scgGlobalKeydownBound = true;
    document.addEventListener('keydown', function (e) {
        if (document.querySelector('.gallery-lightbox')) return;
        var c = scgActiveContainer;
        if (!c || typeof c._scgGoToRelative !== 'function') return;
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            c._scgGoToRelative(-1);
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            c._scgGoToRelative(1);
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var containers = document.querySelectorAll('.single-car-gallery-container');
    if (!containers.length) return;
    containers.forEach(function (container) {
        initGallery(container);
    });
    scgActiveContainer = containers[0];
    scgBindGlobalKeydown();
});

function initGallery(container) {
    var track      = container.querySelector('.scg-track');
    var slides     = container.querySelectorAll('.scg-slide');
    var total      = slides.length;
    var current    = 0;

    var btnPrev    = container.querySelector('.custom-prev-btn');
    var btnNext    = container.querySelector('.custom-next-btn');
    var counterEl  = container.querySelector('.current-slide');
    var thumbs       = container.querySelectorAll('.scg-thumb');
    var thumbsStrip  = container.querySelector('.scg-thumbs');

    if (!track || total === 0) return;

    container.addEventListener('pointerdown', function () {
        scgActiveContainer = container;
    }, true);

    // ── Core goTo ──
    function goTo(i) {
        if (i < 0) i = 0;
        if (i >= total) i = total - 1;
        current = i;

        track.style.transform = 'translateX(-' + (current * 100) + '%)';

        if (counterEl) counterEl.textContent = current + 1;

        if (btnPrev) btnPrev.classList.toggle('scg-arrow-hidden', current === 0);
        if (btnNext) btnNext.classList.toggle('scg-arrow-hidden', current === total - 1);

        thumbs.forEach(function (th, idx) {
            var isActive = idx === current;
            th.classList.toggle('scg-thumb-active', isActive);
            if (isActive && thumbsStrip) scgScrollThumbIntoStrip(thumbsStrip, th);
        });
    }

    // ── Arrows ──
    scgPreventNavButtonFocusScroll(btnPrev);
    scgPreventNavButtonFocusScroll(btnNext);
    if (btnPrev) {
        btnPrev.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            goTo(current - 1);
        });
    }
    if (btnNext) {
        btnNext.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            goTo(current + 1);
        });
    }

    // ── Thumbnails ──
    thumbs.forEach(function (th) {
        th.addEventListener('click', function () {
            goTo(parseInt(th.dataset.index, 10));
        });
    });

    container._scgGoToRelative = function (delta) {
        goTo(current + delta);
    };

    // ── Pointer / swipe (identical to car-card) ──
    var startX = 0, startY = 0, dx = 0, dragging = false, locked = false, isHorizontal = false;

    track.addEventListener('pointerdown', function (e) {
        if (e.button !== 0) return;
        if (e.target.closest('button')) return;
        dragging = true; locked = false; isHorizontal = false;
        startX = e.clientX; startY = e.clientY; dx = 0;
        track.style.transition = 'none';
        track.setPointerCapture(e.pointerId);
    });

    track.addEventListener('pointermove', function (e) {
        if (!dragging) return;
        var moveX = e.clientX - startX;
        var moveY = e.clientY - startY;
        if (!locked) {
            if (Math.abs(moveX) > 10 || Math.abs(moveY) > 10) {
                locked = true;
                isHorizontal = Math.abs(moveX) >= Math.abs(moveY);
                if (!isHorizontal) {
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
        var resistance = ((current === 0 && dx > 0) || (current === total - 1 && dx < 0)) ? 0.3 : 1;
        var offset = -(current * track.parentElement.offsetWidth) + (dx * resistance);
        track.style.transform = 'translateX(' + offset + 'px)';
    });

    function endDrag() {
        if (!dragging) return;
        dragging = false;
        track.style.transition = '';
        var threshold = track.parentElement.offsetWidth * 0.15;
        if (dx < -threshold)     goTo(current + 1);
        else if (dx > threshold) goTo(current - 1);
        else                     goTo(current);
        setTimeout(function () { dx = 0; }, 300);
    }

    track.addEventListener('pointerup', endDrag);
    track.addEventListener('pointercancel', endDrag);

    track.addEventListener('click', function (e) {
        if (Math.abs(dx) > 10) { e.preventDefault(); e.stopPropagation(); }
    }, true);

    // ── View all / lightbox ──
    var viewAllBtn = container.querySelector('.view-all-button button');
    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', function () {
            openLightbox(current);
        });
    }

    function openLightbox(startIndex) {
        var images = Array.from(slides).map(function (slide) {
            var img = slide.querySelector('img');
            return { src: img ? img.src : '', alt: img ? (img.alt || '') : '' };
        });
        var lbTotal   = images.length;
        var lbCurrent = startIndex;

        var slidesHTML = images.map(function (img) {
            return '<div class="lb-slide"><img src="' + img.src + '" alt="' + img.alt + '" /></div>';
        }).join('');

        var thumbsHTML = images.map(function (img, idx) {
            return '<div class="lb-thumb' + (idx === lbCurrent ? ' lb-thumb-active' : '') + '" data-index="' + idx + '">' +
                       '<img src="' + img.src + '" alt="' + img.alt + '" />' +
                   '</div>';
        }).join('');

        var lbHTML =
            '<div class="gallery-lightbox">' +
                '<div class="lightbox-overlay"></div>' +
                '<div class="lightbox-content">' +
                    '<div class="lightbox-header">' +
                        '<div class="lightbox-counter">' +
                            '<span class="lightbox-current">' + (lbCurrent + 1) + '</span> / ' +
                            '<span class="lightbox-total">' + lbTotal + '</span>' +
                        '</div>' +
                        '<button class="lightbox-close" type="button" aria-label="Close gallery"><i class="fas fa-times"></i></button>' +
                    '</div>' +
                    '<div class="lightbox-main-slider-wrapper">' +
                        '<div class="lb-track-wrap">' +
                            '<div class="lb-track">' + slidesHTML + '</div>' +
                        '</div>' +
                        '<div class="lightbox-arrows">' +
                            '<button type="button" class="lightbox-prev-btn" aria-label="Previous image"><i class="fas fa-chevron-left"></i></button>' +
                            '<button type="button" class="lightbox-next-btn" aria-label="Next image"><i class="fas fa-chevron-right"></i></button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="lightbox-thumbnail-wrapper">' +
                        '<div class="lb-thumbs">' + thumbsHTML + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', lbHTML);
        document.body.classList.add('lightbox-open');

        var lb       = document.querySelector('.gallery-lightbox');
        var lbTrack  = lb.querySelector('.lb-track');
        var lbThumbs = lb.querySelectorAll('.lb-thumb');
        var lbCurEl  = lb.querySelector('.lightbox-current');
        var lbPrev        = lb.querySelector('.lightbox-prev-btn');
        var lbNext        = lb.querySelector('.lightbox-next-btn');
        var lbThumbsStrip = lb.querySelector('.lb-thumbs');

        function lbGoTo(i) {
            if (i < 0) i = 0;
            if (i >= lbTotal) i = lbTotal - 1;
            lbCurrent = i;
            lbTrack.style.transform = 'translateX(-' + (lbCurrent * 100) + '%)';
            if (lbCurEl) lbCurEl.textContent = lbCurrent + 1;
            lbThumbs.forEach(function (th, idx) {
                var isActive = idx === lbCurrent;
                th.classList.toggle('lb-thumb-active', isActive);
                if (isActive && lbThumbsStrip) scgScrollThumbIntoStrip(lbThumbsStrip, th);
            });
            if (lbPrev) lbPrev.classList.toggle('scg-arrow-hidden', lbCurrent === 0);
            if (lbNext) lbNext.classList.toggle('scg-arrow-hidden', lbCurrent === lbTotal - 1);
        }

        scgPreventNavButtonFocusScroll(lbPrev);
        scgPreventNavButtonFocusScroll(lbNext);
        if (lbPrev) lbPrev.addEventListener('click', function (e) { e.preventDefault(); lbGoTo(lbCurrent - 1); });
        if (lbNext) lbNext.addEventListener('click', function (e) { e.preventDefault(); lbGoTo(lbCurrent + 1); });

        lbThumbs.forEach(function (th) {
            th.addEventListener('click', function () { lbGoTo(parseInt(th.dataset.index, 10)); });
        });

        // Pointer swipe on lightbox
        var lbStartX = 0, lbStartY = 0, lbDx = 0, lbDragging = false, lbLocked = false, lbIsH = false;

        lbTrack.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            if (e.target.closest('button')) return;
            lbDragging = true; lbLocked = false; lbIsH = false;
            lbStartX = e.clientX; lbStartY = e.clientY; lbDx = 0;
            lbTrack.style.transition = 'none';
            lbTrack.setPointerCapture(e.pointerId);
        });

        lbTrack.addEventListener('pointermove', function (e) {
            if (!lbDragging) return;
            var mx = e.clientX - lbStartX, my = e.clientY - lbStartY;
            if (!lbLocked) {
                if (Math.abs(mx) > 10 || Math.abs(my) > 10) {
                    lbLocked = true; lbIsH = Math.abs(mx) >= Math.abs(my);
                    if (!lbIsH) {
                        lbDragging = false;
                        try { lbTrack.releasePointerCapture(e.pointerId); } catch (err) {}
                        lbTrack.style.transition = '';
                        lbGoTo(lbCurrent);
                        return;
                    }
                } else { return; }
            }
            if (!lbIsH) return;
            e.preventDefault(); lbDx = mx;
            var res = ((lbCurrent === 0 && lbDx > 0) || (lbCurrent === lbTotal - 1 && lbDx < 0)) ? 0.3 : 1;
            lbTrack.style.transform = 'translateX(' + (-(lbCurrent * lbTrack.parentElement.offsetWidth) + lbDx * res) + 'px)';
        });

        function lbEndDrag() {
            if (!lbDragging) return;
            lbDragging = false; lbTrack.style.transition = '';
            var thr = lbTrack.parentElement.offsetWidth * 0.15;
            if (lbDx < -thr)     lbGoTo(lbCurrent + 1);
            else if (lbDx > thr) lbGoTo(lbCurrent - 1);
            else                  lbGoTo(lbCurrent);
            setTimeout(function () { lbDx = 0; }, 300);
        }

        lbTrack.addEventListener('pointerup', lbEndDrag);
        lbTrack.addEventListener('pointercancel', lbEndDrag);
        lbTrack.addEventListener('click', function (e) {
            if (Math.abs(lbDx) > 10) { e.preventDefault(); e.stopPropagation(); }
        }, true);

        function closeLightbox() {
            var lbEl = document.querySelector('.gallery-lightbox');
            if (lbEl) { lbEl.remove(); document.body.classList.remove('lightbox-open'); }
            document.removeEventListener('keydown', handleLbKey);
        }

        var closeBtn = lb.querySelector('.lightbox-close');
        var overlay  = lb.querySelector('.lightbox-overlay');
        if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
        if (overlay)  overlay.addEventListener('click', closeLightbox);

        function handleLbKey(e) {
            if (e.key === 'Escape')      { closeLightbox(); }
            else if (e.key === 'ArrowLeft')  { e.preventDefault(); lbGoTo(lbCurrent - 1); }
            else if (e.key === 'ArrowRight') { e.preventDefault(); lbGoTo(lbCurrent + 1); }
        }
        document.addEventListener('keydown', handleLbKey);

        lbGoTo(startIndex);
    }

    // Init
    goTo(0);
}
