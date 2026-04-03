/**
 * City cars landing: TCP browse UI (mirrors taxonomy-car_make-landing inline script).
 */
(function ($) {
    'use strict';

    var cfg = window.autoagoraCityCarsBrowse || {};
    var listingsId = cfg.listingsId || '';
    var group = cfg.group || '';
    var resultsSuffix = (cfg.strings && cfg.strings.resultsSuffix) ? cfg.strings.resultsSuffix : 'results found';
    var sortNewestLabel = (cfg.strings && cfg.strings.sortNewest) ? cfg.strings.sortNewest : 'Newest';

    var $container = $('#' + listingsId);
    if (!$container.length || !group) {
        return;
    }

    if (typeof window.AutoagoraCityCarsBrowseMap !== 'function') {
        return;
    }

    var $wrapper = $container.find('.car-listings-wrapper');
    var $pagination = $container.find('.tcp-pagination');
    var $overlay = $('#tcp-filters-modal-overlay');
    var $locationOverlay = $('#tcp-location-modal-overlay');
    var $chips = $('#tcp-active-filters');

    var locationState = {
        lat: null,
        lng: null,
        radiusKm: 25,
        label: '',
        active: false
    };

    function syncLocationVisualsMain(shouldAdjustZoom) {
        var locationMap = mapBundle.getMap();
        var locationCircle = mapBundle.getCircle();
        if (!locationMap || !locationCircle || !locationState.lat || !locationState.lng) {
            return;
        }
        var center = { lat: locationState.lat, lng: locationState.lng };
        locationCircle.setCenter(center);
        locationCircle.setRadius(locationState.radiusKm * 1000);
        if (shouldAdjustZoom) {
            locationMap.setZoom(mapBundle.getZoomForRadius(locationState.radiusKm));
        }
    }

    function setLocationPointMain(lat, lng, shouldAdjustZoom) {
        locationState.lat = lat;
        locationState.lng = lng;
        var locationMap = mapBundle.getMap();
        var locationCircle = mapBundle.getCircle();
        if (locationCircle && locationCircle.getMap() === null && locationMap) {
            locationCircle.setMap(locationMap);
        }
        if (locationMap) {
            locationMap.panTo({ lat: lat, lng: lng });
        }
        syncLocationVisualsMain(!!shouldAdjustZoom);
    }

    var mapBundle = window.AutoagoraCityCarsBrowseMap($, cfg, {
        setLocationPoint: function (lat, lng, shouldAdjustZoom, ls) {
            ls.lat = lat;
            ls.lng = lng;
            setLocationPointMain(lat, lng, shouldAdjustZoom);
        },
        syncLocationVisuals: function (shouldAdjustZoom, ls) {
            syncLocationVisualsMain(shouldAdjustZoom);
        }
    });

    var _filterLocked = true;
    function unlockFilter() {
        _filterLocked = false;
    }
    var MIN_ROWS = 4;
    var MIN_PER_PAGE = 12;
    var CARD_MIN_W = 280;
    var GRID_GAP = 24;

    function calcPostsPerPage() {
        var gridW = $wrapper.width() || $container.width();
        if (!gridW) return MIN_PER_PAGE;
        var cols = Math.max(1, Math.floor((gridW + GRID_GAP) / (CARD_MIN_W + GRID_GAP)));
        return Math.max(MIN_PER_PAGE, cols * MIN_ROWS);
    }

    function syncPostsPerPage() {
        var atts = $container.data('atts') || {};
        atts.posts_per_page = calcPostsPerPage();
        $container.data('atts', atts);
        $container.attr('data-atts', JSON.stringify(atts));
    }

    var $results = $('#tcp-results-count');

    function updateResultsCount(total) {
        var count = parseInt(total, 10);
        if (isNaN(count) || count < 0) count = 0;
        $results.text(count.toLocaleString() + ' ' + resultsSuffix);
        updateClearAllButton(count);
    }

    function updateClearAllButton(count) {
        $wrapper.find('.tcp-clear-all-filters-btn').remove();
        if (count === 0) {
            var $noResults = $wrapper.find('.car-listings-no-results');
            if ($noResults.length) {
                $noResults.after('<button type="button" class="tcp-clear-all-filters-btn" id="tcp-no-results-clear-btn">Clear all filters</button>');
            }
        }
    }

    function resetSort() {
        var $sort = $('#tcp-sort');
        $sort.find('.tcp-sort-option').removeClass('selected');
        $sort.find('.tcp-sort-option').first().addClass('selected');
        $('#tcp-sort-label').text(sortNewestLabel);
        var atts = $container.data('atts') || {};
        atts.orderby = 'date';
        atts.order = 'DESC';
        $container.data('atts', atts);
        $container.attr('data-atts', JSON.stringify(atts));
    }

    var filterLabels = {
        price_min: 'Price min',
        price_max: 'Price max',
        mileage_min: 'Mileage min',
        mileage_max: 'Mileage max',
        year_min: 'Year min',
        year_max: 'Year max',
        fuel_type: 'Fuel',
        body_type: 'Body',
        location_radius: 'Location'
    };

    $('#tcp-filters-btn').on('click', function () {
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');
    });
    function closeModal() {
        $overlay.removeClass('open');
        $('body').css('overflow', '');
    }
    $('#tcp-filters-modal-close').on('click', closeModal);
    $overlay.on('click', function (e) {
        if (e.target === this) closeModal();
    });

    function openLocationModal() {
        $locationOverlay.addClass('open');
        $('body').css('overflow', 'hidden');
        function afterMapsReady() {
            mapBundle.initLocationMap(locationState);
            setTimeout(function () {
                var lm = mapBundle.getMap();
                if (!lm || typeof google === 'undefined' || !google.maps) return;
                google.maps.event.trigger(lm, 'resize');
                if (locationState.lat && locationState.lng) {
                    lm.setCenter({ lat: locationState.lat, lng: locationState.lng });
                }
            }, 50);
        }
        if (typeof google !== 'undefined' && google.maps) {
            afterMapsReady();
        } else if (typeof window.autoagoraLoadCarBrowseMaps === 'function') {
            window.autoagoraLoadCarBrowseMaps(afterMapsReady);
        } else {
            afterMapsReady();
        }
    }

    function closeLocationModal() {
        $locationOverlay.removeClass('open');
        $('body').css('overflow', '');
    }

    $('#tcp-location-btn').on('click', openLocationModal);
    $('#tcp-location-modal-close').on('click', closeLocationModal);
    $locationOverlay.on('click', function (e) {
        if (e.target === this) closeLocationModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $overlay.hasClass('open')) closeModal();
        if (e.key === 'Escape' && $locationOverlay.hasClass('open')) closeLocationModal();
    });

    function buildChips() {
        if (!window.CarFilters) return;
        var state = CarFilters.getState(group);
        var html = '';
        var hasAny = false;

        ['price', 'mileage', 'year'].forEach(function (key) {
            var min = state[key + '_min'];
            var max = state[key + '_max'];
            var noComma = (key === 'year');
            if (min) {
                html += chip(key + '_min', filterLabels[key + '_min'] + ': ' + formatNum(min, noComma));
                hasAny = true;
            }
            if (max) {
                html += chip(key + '_max', filterLabels[key + '_max'] + ': ' + formatNum(max, noComma));
                hasAny = true;
            }
        });
        ['fuel_type', 'body_type'].forEach(function (key) {
            if (state[key]) {
                html += chip(key, filterLabels[key] + ': ' + state[key]);
                hasAny = true;
            }
        });
        if (locationState.active && locationState.lat && locationState.lng && locationState.radiusKm > 0) {
            var locationLabel = locationState.label || 'Selected area';
            html += chip('location_radius', filterLabels.location_radius + ': ' + locationLabel);
            hasAny = true;
        }

        if (hasAny) {
            html += '<button type="button" class="tcp-chip-clear" id="tcp-clear-all">Clear all</button>';
        }

        $chips.html(html);
    }

    function chip(key, label) {
        return '<span class="tcp-chip" data-filter="' + key + '">' +
            label +
            '<button type="button" class="tcp-chip-remove" data-filter="' + key + '" aria-label="Remove">&times;</button>' +
            '</span>';
    }

    function formatNum(val, raw) {
        var n = parseInt(String(val).replace(/,/g, ''), 10);
        if (!n) return val;
        return raw ? String(n) : n.toLocaleString();
    }

    $chips.on('click', '.tcp-chip-remove', function (e) {
        e.stopPropagation();
        var key = $(this).data('filter');
        clearFilter(key);
        CarFilters.triggerFilter(group);
    });

    $chips.on('click', '#tcp-clear-all', function () {
        ['price_min', 'price_max', 'mileage_min', 'mileage_max',
            'year_min', 'year_max', 'fuel_type', 'body_type', 'location_radius'].forEach(function (key) {
            clearFilter(key);
        });
        resetSort();
        unlockFilter();
        CarFilters.triggerFilter(group);
    });

    $wrapper.on('click', '#tcp-no-results-clear-btn', function () {
        ['price_min', 'price_max', 'mileage_min', 'mileage_max',
            'year_min', 'year_max', 'fuel_type', 'body_type', 'location_radius'].forEach(function (key) {
            clearFilter(key);
        });
        resetSort();
        unlockFilter();
        CarFilters.triggerFilter(group);
    });

    function clearFilter(key) {
        if (key === 'location_radius') {
            locationState.active = false;
            locationState.radiusKm = parseInt(cfg.presetRadiusKm, 10) || 25;
            locationState.label = '';
            updateLocationRadiusUI(locationState.radiusKm);
            mapBundle.setCircleRadius(locationState.radiusKm);
            syncLocationParamsToUrl();
        } else if (key.match(/_(min|max)$/)) {
            CarFilters.setState(group, key, '');
            var parts = key.split('_');
            var bound = parts.pop();
            var field = parts.join('_');
            var filterCls = field === 'fuel_type' ? 'fuel' : (field === 'body_type' ? 'body' : field);
            $('.car-filter-' + filterCls + ' .car-filter-input-' + bound).val('');
        } else {
            CarFilters.setState(group, key, '');
            var filterCls2 = key === 'fuel_type' ? 'fuel' : (key === 'body_type' ? 'body' : key);
            var $dd = $('.car-filter-' + filterCls2 + ' .car-filter-dropdown');
            $dd.find('.car-filter-dropdown-option').removeClass('selected');
            $dd.find('.car-filter-dropdown-option[data-value=""]').addClass('selected');
            $dd.find('.car-filter-dropdown-text').addClass('placeholder').text($dd.find('select option:first').text());
            $dd.find('select').val('');
        }
    }

    $('#tcp-modal-apply-btn').on('click', function () {
        unlockFilter();
        CarFilters.triggerFilter(group);
    });
    $('#tcp-modal-clear-btn').on('click', function () {
        ['price_min', 'price_max', 'mileage_min', 'mileage_max',
            'year_min', 'year_max', 'fuel_type', 'body_type', 'location_radius'].forEach(function (key) {
            clearFilter(key);
        });
        resetSort();
        unlockFilter();
        CarFilters.triggerFilter(group);
    });

    $(document).on('carFilters:updated', function (e, g, data) {
        if (g !== group) return;
        if (data.pagination_html !== undefined) {
            $pagination.html(data.pagination_html || '');
        }
        $container.data('page', data.current_page || 1);
        $container.data('max-pages', data.max_pages || 1);
        if (data.found_posts !== undefined) {
            updateResultsCount(data.found_posts);
        }
        syncLocationParamsToUrl();
        buildChips();
        closeModal();
    });

    if (window.CarFilters) {
        CarFilters.subscribe(group, function () {
            buildChips();
        });
    }

    function updateLocationRadiusUI(radiusKm) {
        $('#tcp-location-radius-value').text(radiusKm + ' km');
        $('.tcp-radius-preset').removeClass('active');
        $('.tcp-radius-preset[data-radius="' + radiusKm + '"]').addClass('active');
    }

    function syncLocationParamsToUrl() {
        if (typeof window === 'undefined' || !window.history || !window.URLSearchParams) {
            return;
        }
        var url = new URL(window.location.href);
        var params = url.searchParams;

        params.delete('loc_lat');
        params.delete('loc_lng');
        params.delete('loc_radius');
        params.delete('loc_label');

        if (locationState.active && locationState.lat && locationState.lng && locationState.radiusKm > 0) {
            params.set('loc_lat', Number(locationState.lat).toFixed(6));
            params.set('loc_lng', Number(locationState.lng).toFixed(6));
            params.set('loc_radius', String(parseInt(locationState.radiusKm, 10)));
            if (locationState.label) {
                params.set('loc_label', locationState.label);
            }
        }

        window.history.replaceState({}, '', url.toString());
    }

    function hydrateLocationFromUrl() {
        if (typeof window === 'undefined' || !window.URLSearchParams) {
            return false;
        }

        var params = new URLSearchParams(window.location.search);
        var lat = parseFloat(params.get('loc_lat') || '');
        var lng = parseFloat(params.get('loc_lng') || '');
        var radius = parseInt(params.get('loc_radius') || '', 10);
        var label = params.get('loc_label') || '';

        if (isNaN(lat) || isNaN(lng)) {
            return false;
        }

        if (isNaN(radius) || radius < 1) {
            radius = 25;
        } else if (radius > 200) {
            radius = 200;
        }

        locationState.lat = lat;
        locationState.lng = lng;
        locationState.radiusKm = radius;
        locationState.label = label;
        locationState.active = true;

        var searchInput = document.getElementById('tcp-location-search');
        if (searchInput && label) {
            searchInput.value = label;
        }

        return true;
    }

    function applyPresetLocationFromConfig() {
        var lat = parseFloat(cfg.presetLat);
        var lng = parseFloat(cfg.presetLng);
        if (isNaN(lat) || isNaN(lng)) {
            return;
        }
        locationState.lat = lat;
        locationState.lng = lng;
        locationState.radiusKm = parseInt(cfg.presetRadiusKm, 10) || 25;
        locationState.label = cfg.presetLabel || '';
        locationState.active = true;
        var searchInput = document.getElementById('tcp-location-search');
        if (searchInput && locationState.label) {
            searchInput.value = locationState.label;
        }
        syncLocationParamsToUrl();
    }

    $('.tcp-location-radius-presets').on('click', '.tcp-radius-preset', function () {
        var radius = parseInt($(this).data('radius'), 10);
        if (isNaN(radius) || radius <= 0) return;
        locationState.radiusKm = radius;
        updateLocationRadiusUI(radius);
        mapBundle.setCircleRadius(radius);
        mapBundle.setMapZoom(radius);
    });

    $('#tcp-location-apply-btn').on('click', function () {
        if (!locationState.lat || !locationState.lng) {
            closeLocationModal();
            return;
        }
        if (!locationState.label) {
            locationState.label = $('#tcp-location-search').val() || 'Selected area';
        }
        locationState.active = true;
        closeLocationModal();
        buildChips();
        syncLocationParamsToUrl();
        unlockFilter();
        CarFilters.triggerFilter(group);
    });

    $('#tcp-location-clear-btn').on('click', function () {
        clearFilter('location_radius');
        closeLocationModal();
        buildChips();
        syncLocationParamsToUrl();
        unlockFilter();
        CarFilters.triggerFilter(group);
    });

    var sortAjaxApi = null;
    if (typeof window.autoagoraCityCarsBrowseBindSortAjax === 'function') {
        sortAjaxApi = window.autoagoraCityCarsBrowseBindSortAjax({
            $container: $container,
            $wrapper: $wrapper,
            $pagination: $pagination,
            group: group,
            locationState: locationState,
            syncPostsPerPage: syncPostsPerPage,
            isFilterLocked: function () {
                return _filterLocked;
            },
            unlockFilter: unlockFilter
        });
    }

    $(document).ready(function () {
        var hasLocationFromUrl = hydrateLocationFromUrl();
        if (!hasLocationFromUrl) {
            applyPresetLocationFromConfig();
        }
        setTimeout(buildChips, 100);
        updateLocationRadiusUI(locationState.radiusKm);
        var initialCountText = parseInt($results.text(), 10);
        if (!isNaN(initialCountText)) {
            updateClearAllButton(initialCountText);
        }
        if (hasLocationFromUrl && sortAjaxApi && sortAjaxApi.loadPage) {
            unlockFilter();
            sortAjaxApi.loadPage(1, { scroll: false });
        }
    });

    $(document).on('click', '.tcp-filters-modal-body .car-filters-item-fuel .car-filter-dropdown-button, .tcp-filters-modal-body .car-filters-item-body .car-filter-dropdown-button', function () {
        var modalBody = document.querySelector('.tcp-filters-modal-body');
        if (!modalBody) return;
        setTimeout(function () {
            modalBody.scrollTo({ top: modalBody.scrollHeight, behavior: 'smooth' });
        }, 50);
    });
})(jQuery);
