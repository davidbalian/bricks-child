(function () {
    'use strict';

    var KEY = 'autoagora_compare_cars';
    var MAX = 3;

    function readItems() {
        try {
            var parsed = JSON.parse(localStorage.getItem(KEY) || '[]');
            return Array.isArray(parsed) ? parsed.slice(0, MAX) : [];
        } catch (e) {
            return [];
        }
    }

    function writeItems(items) {
        try {
            localStorage.setItem(KEY, JSON.stringify(items.slice(0, MAX)));
        } catch (e) {
            return;
        }
    }

    function itemFromButton(btn) {
        return {
            id: parseInt(btn.getAttribute('data-car-id'), 10) || 0,
            title: btn.getAttribute('data-title') || '',
            url: btn.getAttribute('data-url') || '',
            price: btn.getAttribute('data-price') || '',
            mileage: btn.getAttribute('data-mileage') || '',
            year: btn.getAttribute('data-year') || '',
            power: btn.getAttribute('data-power') || '',
            fuel: btn.getAttribute('data-fuel') || '',
            transmission: btn.getAttribute('data-transmission') || ''
        };
    }

    function ensureTray() {
        var tray = document.querySelector('.autoagora-compare-tray');
        if (tray) return tray;
        if (!document.body) return null;

        tray = document.createElement('section');
        tray.className = 'autoagora-compare-tray';
        tray.setAttribute('aria-live', 'polite');
        document.body.appendChild(tray);
        return tray;
    }

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderTray() {
        var items = readItems();
        var tray = ensureTray();
        if (!tray) {
            return;
        }
        if (!items.length) {
            tray.classList.remove('is-open');
            tray.innerHTML = '';
            updateButtons();
            return;
        }

        var rows = [
            ['Price', 'price'],
            ['Mileage', 'mileage'],
            ['Year', 'year'],
            ['Power', 'power'],
            ['Fuel', 'fuel'],
            ['Transmission', 'transmission']
        ];

        var html = '<div class="autoagora-compare-tray__header"><strong>Compare cars (' + items.length + '/3)</strong><button type="button" class="autoagora-compare-clear">Clear</button></div>';
        html += '<table class="autoagora-compare-table"><thead><tr><th>Spec</th>';
        items.forEach(function (item) {
            html += '<th><a href="' + esc(item.url) + '">' + esc(item.title) + '</a><br><button type="button" class="autoagora-compare-remove" data-car-id="' + esc(item.id) + '">Remove</button></th>';
        });
        html += '</tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr><th>' + esc(row[0]) + '</th>';
            items.forEach(function (item) {
                html += '<td>' + esc(item[row[1]] || '-') + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        tray.innerHTML = html;
        tray.classList.add('is-open');
        updateButtons();
    }

    function updateButtons() {
        var ids = readItems().map(function (item) { return String(item.id); });
        document.querySelectorAll('[data-autoagora-compare]').forEach(function (btn) {
            var selected = ids.indexOf(String(btn.getAttribute('data-car-id'))) !== -1;
            var label = selected ? 'Added to compare' : 'Compare';
            if (btn.classList.contains('is-selected') !== selected) {
                btn.classList.toggle('is-selected', selected);
            }
            if (btn.textContent !== label) {
                btn.textContent = label;
            }
        });
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-autoagora-compare]');
        if (btn) {
            event.preventDefault();
            event.stopPropagation();
            var item = itemFromButton(btn);
            if (!item.id) return;

            var items = readItems();
            var existing = items.findIndex(function (stored) { return stored.id === item.id; });
            if (existing >= 0) {
                items.splice(existing, 1);
            } else {
                if (items.length >= MAX) {
                    items.shift();
                }
                items.push(item);
            }
            writeItems(items);
            renderTray();
            return;
        }

        var remove = event.target.closest('.autoagora-compare-remove');
        if (remove) {
            var id = parseInt(remove.getAttribute('data-car-id'), 10) || 0;
            writeItems(readItems().filter(function (item) { return item.id !== id; }));
            renderTray();
            return;
        }

        if (event.target.closest('.autoagora-compare-clear')) {
            writeItems([]);
            renderTray();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderTray);
    } else {
        renderTray();
    }

    if ('MutationObserver' in window && document.body) {
        var updateQueued = false;
        var mo = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length) {
                    if (!updateQueued) {
                        updateQueued = true;
                        window.requestAnimationFrame(function () {
                            updateQueued = false;
                            updateButtons();
                        });
                    }
                    break;
                }
            }
        });
        mo.observe(document.body, { childList: true, subtree: true });
    }

    window.autoagoraCompareRender = renderTray;
})();
