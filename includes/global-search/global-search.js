(function () {
    'use strict';

    var config = window.autoagoraGlobalSearch || {};
    var overlay = document.querySelector('[data-aag-search-overlay]');
    var overlayInput = overlay ? overlay.querySelector('.aag-global-search__input') : null;
    var lastFocused = null;
    var recentKey = 'autoagora_recent_searches_v1';

    function createElement(tag, className, text) {
        var element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (typeof text === 'string') {
            element.textContent = text;
        }
        return element;
    }

    function searchIcon() {
        var span = createElement('span', 'aag-global-search__action-icon');
        span.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>';
        return span;
    }

    function getRecent() {
        try {
            var value = JSON.parse(window.localStorage.getItem(recentKey) || '[]');
            return Array.isArray(value) ? value.slice(0, 5) : [];
        } catch (error) {
            return [];
        }
    }

    function saveRecent(query, url) {
        if (!query || !url) {
            return;
        }
        var entries = getRecent().filter(function (entry) {
            return entry && entry.query.toLowerCase() !== query.toLowerCase();
        });
        entries.unshift({ query: query, url: url });
        try {
            window.localStorage.setItem(recentKey, JSON.stringify(entries.slice(0, 5)));
        } catch (error) {
            // Search remains fully functional when storage is unavailable.
        }
    }

    function openOverlay(seed) {
        if (!overlay || !overlayInput) {
            return;
        }
        lastFocused = document.activeElement;
        overlay.hidden = false;
        document.documentElement.classList.add('aag-global-search-open');
        if (seed) {
            overlayInput.value = seed;
            overlayInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        window.requestAnimationFrame(function () {
            overlayInput.focus();
            overlayInput.select();
        });
    }

    function closeOverlay() {
        if (!overlay || overlay.hidden) {
            return;
        }
        overlay.hidden = true;
        document.documentElement.classList.remove('aag-global-search-open');
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    document.querySelectorAll('[data-aag-search-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            openOverlay(button.getAttribute('data-search-seed') || '');
        });
    });
    document.querySelectorAll('[data-aag-search-close]').forEach(function (button) {
        button.addEventListener('click', closeOverlay);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && overlay && !overlay.hidden) {
            closeOverlay();
        }
        if (event.key === 'Tab' && overlay && !overlay.hidden) {
            var focusable = Array.prototype.slice.call(overlay.querySelectorAll('button:not([disabled]), input:not([disabled]), a[href]')).filter(function (element) {
                return element.offsetParent !== null;
            });
            if (focusable.length) {
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            openOverlay('');
        }
    });

    document.querySelectorAll('[data-aag-global-search]').forEach(function (form) {
        var input = form.querySelector('.aag-global-search__input');
        var clear = form.querySelector('.aag-global-search__clear');
        var results = form.querySelector('.aag-global-search__results');
        var content = form.querySelector('.aag-global-search__results-content');
        var status = form.querySelector('.aag-global-search__status');
        var debounceTimer = null;
        var controller = null;
        var latestPayload = null;
        var activeIndex = -1;

        function selectableItems() {
            return Array.prototype.slice.call(results.querySelectorAll('[role="option"]'));
        }

        function setExpanded(expanded) {
            results.hidden = !expanded;
            input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            if (!expanded) {
                activeIndex = -1;
                input.removeAttribute('aria-activedescendant');
            }
        }

        function setActive(index) {
            var items = selectableItems();
            if (!items.length) {
                return;
            }
            activeIndex = (index + items.length) % items.length;
            items.forEach(function (item, itemIndex) {
                item.classList.toggle('is-active', itemIndex === activeIndex);
            });
            input.setAttribute('aria-activedescendant', items[activeIndex].id);
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        function resultIcon(item) {
            var icon = createElement('span', 'aag-global-search__result-icon');
            if (item.image) {
                var image = document.createElement('img');
                image.src = item.image;
                image.alt = '';
                image.loading = 'lazy';
                icon.appendChild(image);
            } else {
                icon.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>';
            }
            return icon;
        }

        function addCopy(target, title, subtitle, chips) {
            var copy = createElement('span', 'aag-global-search__copy');
            copy.appendChild(createElement('span', 'aag-global-search__title', title));
            if (subtitle) {
                copy.appendChild(createElement('span', 'aag-global-search__subtitle', subtitle));
            }
            if (chips && chips.length) {
                var chipRow = createElement('span', 'aag-global-search__chips');
                chips.forEach(function (chip) {
                    chipRow.appendChild(createElement('span', 'aag-global-search__chip', chip.label));
                });
                copy.appendChild(chipRow);
            }
            target.appendChild(copy);
        }

        function makeLink(className, url, id) {
            var link = createElement('a', className);
            link.href = url;
            link.id = id;
            link.setAttribute('role', 'option');
            link.setAttribute('tabindex', '-1');
            link.addEventListener('click', function () {
                saveRecent(input.value.trim(), url);
            });
            return link;
        }

        function renderRecent() {
            var entries = getRecent();
            content.replaceChildren();
            status.textContent = '';
            if (!entries.length) {
                setExpanded(false);
                return;
            }
            var group = createElement('section', 'aag-global-search__group');
            group.appendChild(createElement('h3', 'aag-global-search__group-title', config.recentLabel || 'Recent searches'));
            entries.forEach(function (entry, index) {
                var link = makeLink('aag-global-search__recent', entry.url, input.id + '-recent-' + index);
                link.appendChild(searchIcon());
                addCopy(link, entry.query, '');
                group.appendChild(link);
            });
            content.appendChild(group);
            setExpanded(true);
        }

        function render(payload) {
            latestPayload = payload;
            content.replaceChildren();
            status.textContent = '';
            var resultCount = 0;

            if (payload.search_url) {
                var action = makeLink('aag-global-search__action', payload.search_url, input.id + '-action');
                action.appendChild(searchIcon());
                addCopy(action, (config.suggestedLabel || 'Search cars') + ' for “' + payload.query + '”', '', payload.chips || []);
                content.appendChild(action);
                resultCount += 1;
            }

            Object.keys(payload.groups || {}).forEach(function (groupName, groupIndex) {
                var items = payload.groups[groupName];
                if (!items || !items.length) {
                    return;
                }
                var group = createElement('section', 'aag-global-search__group');
                group.appendChild(createElement('h3', 'aag-global-search__group-title', groupName));
                items.forEach(function (item, itemIndex) {
                    var link = makeLink('aag-global-search__result', item.url, input.id + '-result-' + groupIndex + '-' + itemIndex);
                    link.appendChild(resultIcon(item));
                    addCopy(link, item.title, item.subtitle || '');
                    group.appendChild(link);
                    resultCount += 1;
                });
                content.appendChild(group);
            });

            if (!resultCount) {
                status.textContent = config.noResults || 'No direct matches. Press Enter to search all cars.';
            }
            setExpanded(true);
        }

        function fetchResults(query) {
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            status.textContent = 'Searching…';
            content.replaceChildren();
            setExpanded(true);
            var url = new URL(config.ajaxUrl, window.location.origin);
            url.searchParams.set('action', 'autoagora_global_search');
            url.searchParams.set('nonce', config.nonce || '');
            url.searchParams.set('q', query);
            return window.fetch(url.toString(), { credentials: 'same-origin', signal: controller.signal })
                .then(function (response) { return response.json(); })
                .then(function (response) {
                    if (response && response.success && input.value.trim() === query) {
                        render(response.data);
                        return response.data;
                    }
                    return null;
                })
                .catch(function (error) {
                    if (error.name !== 'AbortError') {
                        status.textContent = config.noResults || 'Press Enter to search all cars.';
                    }
                    return null;
                });
        }

        input.addEventListener('input', function () {
            var query = input.value.trim();
            clear.hidden = !query;
            latestPayload = null;
            window.clearTimeout(debounceTimer);
            if (query.length < (config.minChars || 2)) {
                renderRecent();
                return;
            }
            debounceTimer = window.setTimeout(function () { fetchResults(query); }, 220);
        });

        input.addEventListener('focus', function () {
            var query = input.value.trim();
            if (query.length >= (config.minChars || 2)) {
                if (!latestPayload || latestPayload.query !== query) {
                    fetchResults(query);
                } else {
                    setExpanded(true);
                }
            } else {
                renderRecent();
            }
        });

        input.addEventListener('keydown', function (event) {
            var items = selectableItems();
            if (event.key === 'ArrowDown' && items.length) {
                event.preventDefault();
                setActive(activeIndex + 1);
            } else if (event.key === 'ArrowUp' && items.length) {
                event.preventDefault();
                setActive(activeIndex - 1);
            } else if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                event.preventDefault();
                items[activeIndex].click();
            } else if (event.key === 'Escape') {
                setExpanded(false);
                input.blur();
            }
        });

        clear.addEventListener('click', function () {
            input.value = '';
            clear.hidden = true;
            latestPayload = null;
            input.focus();
            renderRecent();
        });

        form.addEventListener('submit', function (event) {
            var query = input.value.trim();
            if (!query) {
                event.preventDefault();
                input.focus();
                return;
            }
            if (latestPayload && latestPayload.query === query && latestPayload.search_url) {
                event.preventDefault();
                var destination = latestPayload.search_url;
                saveRecent(query, destination);
                window.location.assign(destination);
                return;
            }
            event.preventDefault();
            fetchResults(query).then(function (payload) {
                var fallback = new URL(form.action, window.location.origin);
                fallback.searchParams.set(input.name || 'car_search', query);
                var url = payload && payload.search_url ? payload.search_url : fallback.toString();
                saveRecent(query, url);
                window.location.assign(url);
            });
        });

        document.addEventListener('pointerdown', function (event) {
            if (!form.contains(event.target)) {
                setExpanded(false);
            }
        });
    });
}());
