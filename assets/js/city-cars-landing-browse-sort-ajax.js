/**
 * Sort dropdown, pagination redirect, and filter AJAX injection for city cars landing.
 */
(function ($) {
    'use strict';

    /**
     * @param {object} ctx
     * @param {jQuery} ctx.$container
     * @param {jQuery} ctx.$wrapper
     * @param {jQuery} ctx.$pagination
     * @param {string} ctx.group
     * @param {object} ctx.locationState
     * @param {function} ctx.syncPostsPerPage
     * @param {function} ctx.isFilterLocked
     * @param {function} ctx.unlockFilter
     */
    window.autoagoraCityCarsBrowseBindSortAjax = function (ctx) {
        var $sort = $('#tcp-sort');
        var $sortBtn = $('#tcp-sort-btn');
        var $sortLabel = $('#tcp-sort-label');

        $sortBtn.on('click', function (e) {
            e.stopPropagation();
            $sort.toggleClass('open');
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#tcp-sort').length) {
                $sort.removeClass('open');
            }
        });

        $sort.on('click', '.tcp-sort-option', function () {
            var $opt = $(this);
            var orderby = $opt.data('orderby');
            var order = $opt.data('order');

            $sort.find('.tcp-sort-option').removeClass('selected');
            $opt.addClass('selected');
            $sortLabel.text($opt.text());
            $sort.removeClass('open');

            var atts = ctx.$container.data('atts') || {};
            atts.orderby = orderby;
            atts.order = order;
            ctx.$container.data('atts', atts);
            ctx.$container.attr('data-atts', JSON.stringify(atts));

            if (window.CarFilters && CarFilters.buildResultsUrl) {
                window.location.href = CarFilters.buildResultsUrl(ctx.group, {
                    orderby: orderby,
                    order: order
                });
            } else {
                loadPage(1, { scroll: false });
            }
        });

        function loadPage(page, opts) {
            opts = opts || {};
            if (window.CarFilters && CarFilters.buildResultsUrl) {
                var listingAtts = ctx.$container.data('atts') || {};
                var extras = { paged: page };
                if (listingAtts.orderby) extras.orderby = listingAtts.orderby;
                if (listingAtts.order) extras.order = listingAtts.order;
                window.location.href = CarFilters.buildResultsUrl(ctx.group, extras);
                return;
            }
            ctx.syncPostsPerPage();
            var filterData = (window.CarFilters && CarFilters.getFilterData)
                ? CarFilters.getFilterData(ctx.group)
                : {};
            var listingAtts2 = ctx.$container.data('atts') || {};

            ctx.$wrapper.addClass('car-listings-loading');

            $.ajax({
                url: carFiltersConfig.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'car_filters_filter_listings',
                    nonce: carFiltersConfig.nonce,
                    response_format: 'json',
                    page: page,
                    listing_atts: JSON.stringify(listingAtts2)
                }, filterData),
                success: function (response) {
                    if (response.success) {
                        if (response.data.cards && window.carListingCardsRender) {
                            window.carListingCardsRender.renderInto(ctx.$wrapper[0], response.data.cards);
                        } else if (response.data.html) {
                            ctx.$wrapper.html(response.data.html);
                        }
                        ctx.$pagination.html(response.data.pagination_html || '');
                        ctx.$container.data('page', response.data.current_page);
                        ctx.$container.data('max-pages', response.data.max_pages);
                        if (opts.scroll !== false) {
                            $('html, body').animate({ scrollTop: ctx.$container.offset().top - 20 }, 300);
                        }
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Pagination AJAX error:', error);
                },
                complete: function () {
                    ctx.$wrapper.removeClass('car-listings-loading');
                }
            });
        }

        ctx.$container.on('click', '.tcp-pagination a.page-numbers', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            var page = parseInt(href.replace('#', ''), 10);
            if (page && page > 0) {
                loadPage(page);
            }
        });

        $(document).on('ajaxSend', function (e, xhr, settings) {
            if (settings.data && typeof settings.data === 'string' &&
                settings.data.indexOf('action=car_filters_filter_listings') !== -1) {
                if (ctx.isFilterLocked()) {
                    xhr.abort();
                    return;
                }
                ctx.syncPostsPerPage();
                var atts = ctx.$container.data('atts') || {};
                settings.data = settings.data.replace(
                    /listing_atts=[^&]*/,
                    'listing_atts=' + encodeURIComponent(JSON.stringify(atts))
                );
                var latParam = 'location_lat=' + encodeURIComponent(ctx.locationState.active ? ctx.locationState.lat : '');
                var lngParam = 'location_lng=' + encodeURIComponent(ctx.locationState.active ? ctx.locationState.lng : '');
                var radiusParam = 'location_radius_km=' + encodeURIComponent(ctx.locationState.active ? ctx.locationState.radiusKm : '');
                settings.data = settings.data.replace(/&location_lat=[^&]*/g, '');
                settings.data = settings.data.replace(/&location_lng=[^&]*/g, '');
                settings.data = settings.data.replace(/&location_radius_km=[^&]*/g, '');
                settings.data += '&' + latParam + '&' + lngParam + '&' + radiusParam;
                if (settings.data.indexOf('response_format=') === -1) {
                    settings.data += '&response_format=json';
                }
            }
        });

        return { loadPage: loadPage };
    };
}(jQuery));
