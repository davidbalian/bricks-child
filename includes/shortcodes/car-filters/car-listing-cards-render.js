/**
 * Renders car_card listing markup from compact JSON (matches PHP render_car_card).
 */
(function (window) {
    'use strict';

    var PI_LABELS = {
        great: 'Great price',
        good: 'Good price',
        fair: 'Fair price',
        above: 'Above typical'
    };

    function escapeHtml(s) {
        if (s == null || s === '') {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function appendSpecs(el, specs) {
        if (!specs || !specs.length) {
            return;
        }
        for (var i = 0; i < specs.length; i++) {
            if (i > 0) {
                el.appendChild(document.createTextNode(' '));
                var sep = document.createElement('span');
                sep.className = 'car-card-specs-sep';
                sep.textContent = '|';
                el.appendChild(sep);
                el.appendChild(document.createTextNode(' '));
            }
            el.appendChild(document.createTextNode(specs[i]));
        }
    }

    function renderFavoriteButton(postId, isFav) {
        var btn = document.createElement('button');
        btn.className = 'favorite-btn favorite-btn-listing favorite-btn-small' + (isFav ? ' active' : '');
        btn.setAttribute('data-car-id', String(postId));
        btn.setAttribute('title', isFav ? 'Remove from favorites' : 'Add to favorites');
        var i = document.createElement('i');
        i.className = isFav ? 'fas fa-heart' : 'far fa-heart';
        btn.appendChild(i);
        return btn;
    }

    /**
     * @param {object} c — payload from car_card_build_listing_json_payload
     * @returns {HTMLElement}
     */
    function renderOne(c) {
        var article = document.createElement('article');
        article.className = 'car-card' + (c.feat ? ' car-card-featured' : '');
        article.setAttribute('data-post-id', String(c.id));

        var slider = document.createElement('div');
        slider.className = 'car-card-slider';
        slider.setAttribute('data-total', String(c.ti));
        slider.setAttribute('data-slides', String(c.sc));

        if (c.bf || c.be) {
            var badges = document.createElement('div');
            badges.className = 'car-card-badges';
            if (c.bf) {
                var b1 = document.createElement('span');
                b1.className = 'car-card-badge badge-full';
                b1.textContent = 'Full Details';
                badges.appendChild(b1);
            }
            if (c.be) {
                var b2 = document.createElement('span');
                b2.className = 'car-card-badge badge-extra';
                b2.textContent = 'Extra Details';
                badges.appendChild(b2);
            }
            slider.appendChild(badges);
        }

        slider.appendChild(renderFavoriteButton(c.id, !!c.fav));

        var slides = c.slides || [];
        if (slides.length > 0) {
            var track = document.createElement('div');
            track.className = 'car-card-slider-track';

            for (var si = 0; si < slides.length; si++) {
                var s = slides[si];
                var slide = document.createElement('div');
                slide.className = 'car-card-slide';
                slide.setAttribute('data-index', String(si + 1));

                var img = document.createElement('img');
                img.className = 'car-card-slide-img';
                img.setAttribute('alt', '');
                img.setAttribute('decoding', 'async');
                img.setAttribute('draggable', 'false');
                img.setAttribute('sizes', s.sizes || '');
                img.setAttribute('src', s.src);
                if (s.srcset) {
                    img.setAttribute('srcset', s.srcset);
                }
                img.setAttribute('loading', s.eager ? 'eager' : 'lazy');
                if (s.eager) {
                    img.setAttribute('fetchpriority', 'high');
                } else {
                    img.setAttribute('fetchpriority', 'low');
                }
                slide.appendChild(img);

                if (s.overlay_view_all) {
                    var ov = document.createElement('div');
                    ov.className = 'car-card-slide-overlay';
                    var a = document.createElement('a');
                    a.href = c.u;
                    a.className = 'car-card-view-all-btn';
                    a.textContent = 'View All Images';
                    ov.appendChild(a);
                    slide.appendChild(ov);
                }
                track.appendChild(slide);
            }
            slider.appendChild(track);

            if (slides.length > 1) {
                var left = document.createElement('button');
                left.className = 'car-card-arrow car-card-arrow-left car-card-arrow-hidden';
                left.setAttribute('aria-label', 'Previous image');
                left.innerHTML = '<svg viewBox="0 0 12 12"><polyline points="8,2 4,6 8,10"/></svg>';
                slider.appendChild(left);

                var right = document.createElement('button');
                right.className = 'car-card-arrow car-card-arrow-right';
                right.setAttribute('aria-label', 'Next image');
                right.innerHTML = '<svg viewBox="0 0 12 12"><polyline points="4,2 8,6 4,10"/></svg>';
                slider.appendChild(right);

                var dots = document.createElement('div');
                dots.className = 'car-card-dots';
                for (var d = 0; d < slides.length; d++) {
                    var dot = document.createElement('span');
                    dot.className = 'car-card-dot' + (d === 0 ? ' car-card-dot-active' : '');
                    dots.appendChild(dot);
                }
                slider.appendChild(dots);
            }

            var counter = document.createElement('span');
            counter.className = 'car-card-counter';
            counter.textContent = '1/' + String(c.ti);
            slider.appendChild(counter);
        } else {
            var noImg = document.createElement('div');
            noImg.className = 'car-card-no-image';
            noImg.textContent = 'No Image';
            slider.appendChild(noImg);
        }

        article.appendChild(slider);

        var body = document.createElement('a');
        body.className = 'car-card-body';
        body.href = c.u;

        var h3 = document.createElement('h3');
        h3.className = 'car-card-title';
        h3.textContent = c.t || '';
        body.appendChild(h3);

        if (c.mileage) {
            var mil = document.createElement('div');
            mil.className = 'car-card-mileage';
            mil.textContent = c.mileage + 'km';
            body.appendChild(mil);
        }

        var specsEl = document.createElement('div');
        specsEl.className = 'car-card-specs';
        appendSpecs(specsEl, c.specs);
        body.appendChild(specsEl);

        if (c.price) {
            var price = document.createElement('div');
            price.className = 'car-card-price';
            price.innerHTML = '&euro;' + escapeHtml(c.price);
            body.appendChild(price);
        }

        if (c.pi && PI_LABELS[c.pi]) {
            var pi = document.createElement('span');
            pi.className = 'car-card-price-insight car-card-price-insight--' + c.pi;
            pi.textContent = PI_LABELS[c.pi];
            body.appendChild(pi);
        }

        var foot = document.createElement('div');
        foot.className = 'car-card-footer';

        var loc = document.createElement('span');
        loc.className = 'car-card-location';
        loc.innerHTML = '<img src="https://autoagora.cy/wp-content/uploads/2026/04/location-pill-filled.svg" alt="" class="car-card-location-icon"> ' + escapeHtml(c.loc || '');
        foot.appendChild(loc);

        var dateSpan = document.createElement('span');
        dateSpan.className = 'car-card-date';
        if (c.date_html) {
            dateSpan.innerHTML = c.date_html;
        }
        foot.appendChild(dateSpan);

        body.appendChild(foot);
        article.appendChild(body);

        return article;
    }

    function renderInto(container, cards) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        if (!cards || !cards.length) {
            var empty = document.createElement('p');
            empty.className = 'car-listings-no-results';
            empty.textContent = 'No car listings found matching your criteria.';
            container.appendChild(empty);
            return;
        }
        var frag = document.createDocumentFragment();
        for (var i = 0; i < cards.length; i++) {
            frag.appendChild(renderOne(cards[i]));
        }
        container.appendChild(frag);
    }

    window.carListingCardsRender = {
        renderInto: renderInto
    };
})(window);
