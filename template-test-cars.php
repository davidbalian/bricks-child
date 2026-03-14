<?php
/**
 * Template Name: Test Cars Page
 *
 * @package Bricks Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$listing_atts = array(
    'posts_per_page' => 12,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'card_type'      => 'car_card',
);

$args = array(
    'post_type'      => 'car',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => 1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => array(
        'relation' => 'OR',
        array(
            'key'     => 'is_sold',
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => 'is_sold',
            'value'   => '1',
            'compare' => '!=',
        ),
    ),
);

$cars_query = new WP_Query( $args );
?>

<!-- Filters bar -->
<div class="tcp-filters-bar">
    <div class="tcp-filters-bar-inner">
        <button type="button" class="tcp-filters-btn" id="tcp-filters-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="20" y2="12"/><line x1="12" y1="18" x2="20" y2="18"/><circle cx="4" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="8" cy="18" r="1" fill="currentColor" stroke="none"/></svg>
            Filters
        </button>
        <div class="tcp-active-filters" id="tcp-active-filters"></div>

        <div class="tcp-sort" id="tcp-sort">
            <button type="button" class="tcp-sort-btn" id="tcp-sort-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5h10"/><path d="M11 9h7"/><path d="M11 13h4"/><path d="M3 17l3 3 3-3"/><path d="M6 18V4"/></svg>
                <span id="tcp-sort-label">Newest</span>
            </button>
            <div class="tcp-sort-menu" id="tcp-sort-menu">
                <button type="button" class="tcp-sort-option selected" data-orderby="date" data-order="DESC">Newest</button>
                <button type="button" class="tcp-sort-option" data-orderby="date" data-order="ASC">Oldest</button>
                <button type="button" class="tcp-sort-option" data-orderby="price" data-order="ASC">Price: Low to High</button>
                <button type="button" class="tcp-sort-option" data-orderby="price" data-order="DESC">Price: High to Low</button>
                <button type="button" class="tcp-sort-option" data-orderby="mileage" data-order="ASC">Mileage: Low to High</button>
                <button type="button" class="tcp-sort-option" data-orderby="mileage" data-order="DESC">Mileage: High to Low</button>
                <button type="button" class="tcp-sort-option" data-orderby="year" data-order="DESC">Year: Newest</button>
                <button type="button" class="tcp-sort-option" data-orderby="year" data-order="ASC">Year: Oldest</button>
            </div>
        </div>
    </div>
</div>

<!-- Filters modal -->
<div class="tcp-filters-modal-overlay" id="tcp-filters-modal-overlay">
    <div class="tcp-filters-modal">
        <div class="tcp-filters-modal-header">
            <h2>Filters</h2>
            <button type="button" class="tcp-filters-modal-close" id="tcp-filters-modal-close" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="tcp-filters-modal-body">
            <?php echo do_shortcode( '[car_filters filters="make,model,price,mileage,year,fuel,body" mode="ajax" target="test-cars-listings" layout="vertical" show_button="true" button_text="Search"]' ); ?>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="tcp-main">
    <h1 class="tcp-heading">Used Cars for Sale in Cyprus</h1>

    <div class="car-listings-container"
         id="test-cars-listings"
         data-atts="<?php echo esc_attr( wp_json_encode( $listing_atts ) ); ?>"
         data-page="1"
         data-max-pages="<?php echo esc_attr( $cars_query->max_num_pages ); ?>">

        <div class="car-listings-wrapper tcp-grid">
            <?php
            if ( $cars_query->have_posts() ) :
                $post_ids = wp_list_pluck( $cars_query->posts, 'ID' );
                update_postmeta_cache( $post_ids );

                while ( $cars_query->have_posts() ) : $cars_query->the_post();
                    render_car_card( get_the_ID() );
                endwhile;
            else :
                ?>
                <p class="car-listings-no-results"><?php esc_html_e( 'No car listings found.', 'bricks-child' ); ?></p>
                <?php
            endif;
            wp_reset_postdata();
            ?>
        </div>

        <div class="tcp-pagination">
            <?php
            if ( $cars_query->max_num_pages > 1 ) {
                echo paginate_links( array(
                    'total'     => $cars_query->max_num_pages,
                    'current'   => 1,
                    'prev_text' => '&laquo; Previous',
                    'next_text' => 'Next &raquo;',
                    'type'      => 'list',
                    'base'      => '#%#%',
                    'format'    => '%#%',
                ) );
            }
            ?>
        </div>
    </div>
</div>

<style>
/* ============================================
   Filters Bar
   ============================================ */
.tcp-filters-bar {
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    position: sticky;
    top: 0;
    z-index: 100;
}
.tcp-filters-bar-inner {
    max-width: var(--max-width);
    margin: 0 auto;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.tcp-filters-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    background: #fff;
    color: #2a3546;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: border-color 0.15s, background 0.15s;
    flex-shrink: 0;
}
.tcp-filters-btn:hover {
    border-color: #bbb;
    background: #f9fafb;
}

/* Sort dropdown */
.tcp-sort {
    position: relative;
    flex-shrink: 0;
    margin-left: auto;
}
.tcp-sort-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    background: #fff;
    color: #2a3546;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: border-color 0.15s, background 0.15s;
}
.tcp-sort-btn:hover {
    border-color: #bbb;
    background: #f9fafb;
}
.tcp-sort-menu {
    display: none;
    position: absolute;
    top: calc(100% + 0.25rem);
    right: 0;
    min-width: 200px;
    background: #fff;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    z-index: 200;
    padding: 0.25rem 0;
}
.tcp-sort.open .tcp-sort-menu {
    display: block;
}
.tcp-sort-option {
    display: block;
    width: 100%;
    padding: 0.6rem 1rem;
    border: none;
    background: none;
    color: #2a3546;
    font-size: 0.875rem;
    text-align: left;
    cursor: pointer;
    transition: background 0.1s;
}
.tcp-sort-option:hover {
    background: rgba(13, 134, 227, 0.08);
}
.tcp-sort-option.selected {
    font-weight: 700;
    background: rgba(13, 134, 227, 0.12);
}

/* Active filter chips */
.tcp-active-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    flex: 1;
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.tcp-active-filters::-webkit-scrollbar {
    display: none;
}
.tcp-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.6rem;
    border-radius: 2rem;
    background: #f0f4f8;
    color: #2a3546;
    font-size: 0.8125rem;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
}
.tcp-chip-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
    border: none;
    border-radius: 50%;
    background: rgba(0,0,0,0.1);
    color: #2a3546;
    font-size: 0.7rem;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    flex-shrink: 0;
}
.tcp-chip-remove:hover {
    background: rgba(0,0,0,0.2);
}
.tcp-chip-clear {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.6rem;
    border: none;
    border-radius: 2rem;
    background: none;
    color: #0d86e3;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
}
.tcp-chip-clear:hover {
    text-decoration: underline;
}

/* ============================================
   Filters Modal
   ============================================ */
.tcp-filters-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: flex-start;
    padding: 2rem 1rem;
    overflow-y: auto;
}
.tcp-filters-modal-overlay.open {
    display: flex;
}
.tcp-filters-modal {
    background: #fff;
    border-radius: 1rem;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.tcp-filters-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
}
.tcp-filters-modal-header h2 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: #2a3546;
}
.tcp-filters-modal-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: none;
    border-radius: 50%;
    background: #f0f0f0;
    color: #333;
    cursor: pointer;
    padding: 0;
}
.tcp-filters-modal-close:hover {
    background: #e0e0e0;
}
.tcp-filters-modal-body {
    padding: 1.25rem;
}
.tcp-filters-modal-body .car-filters-container {
    width: 100%;
}
.tcp-filters-modal-body .car-filters-wrapper {
    gap: 1rem;
}
.tcp-filters-modal-body .car-filters-item {
    width: 100%;
    min-width: 0;
}

/* ============================================
   Main Content
   ============================================ */
.tcp-main {
    max-width: 2000px;
    margin: 0 auto;
    padding: 1.5rem 1rem 3rem;
}
.tcp-heading {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2a3546;
    margin: 0 0 1.25rem;
}
.tcp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

/* ============================================
   Pagination
   ============================================ */
.tcp-pagination {
    margin-top: 2rem;
    text-align: center;
}
.tcp-pagination .page-numbers {
    list-style: none;
    display: flex;
    justify-content: center;
    gap: 0.25rem;
    padding: 0;
    margin: 0;
    flex-wrap: wrap;
}
.tcp-pagination .page-numbers li {
    list-style: none;
}
.tcp-pagination .page-numbers a,
.tcp-pagination .page-numbers span {
    display: inline-block;
    padding: 0.5rem 0.75rem;
    border-radius: 0.25rem;
    text-decoration: none;
    color: #333;
    background: #f0f0f0;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.tcp-pagination .page-numbers a:hover {
    background: #ddd;
}
.tcp-pagination .page-numbers .current {
    background: #333;
    color: #fff;
    pointer-events: none;
}

/* ============================================
   Loading state
   ============================================ */
.car-listings-wrapper.car-listings-loading {
    opacity: 0.5;
    pointer-events: none;
    transition: opacity 0.15s;
}
</style>

<script>
(function($) {
    'use strict';

    var $container  = $('#test-cars-listings');
    var $wrapper    = $container.find('.car-listings-wrapper');
    var $pagination = $container.find('.tcp-pagination');
    var $overlay    = $('#tcp-filters-modal-overlay');
    var $chips      = $('#tcp-active-filters');
    var group       = 'default';

    // Filter label map for chips
    var filterLabels = {
        make: 'Brand',
        model: 'Model',
        price_min: 'Price min',
        price_max: 'Price max',
        mileage_min: 'Mileage min',
        mileage_max: 'Mileage max',
        year_min: 'Year min',
        year_max: 'Year max',
        fuel_type: 'Fuel',
        body_type: 'Body'
    };

    /* ── Modal open/close ── */
    $('#tcp-filters-btn').on('click', function() {
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');
    });
    function closeModal() {
        $overlay.removeClass('open');
        $('body').css('overflow', '');
    }
    $('#tcp-filters-modal-close').on('click', closeModal);
    $overlay.on('click', function(e) {
        if (e.target === this) closeModal();
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $overlay.hasClass('open')) closeModal();
    });

    /* ── Active filter chips ── */
    function buildChips() {
        if (!window.CarFilters) return;
        var state = CarFilters.getState(group);
        var html = '';
        var hasAny = false;

        // Make
        if (state.make && state.make.value) {
            var makeLabel = getMakeLabel(state.make.value);
            html += chip('make', makeLabel);
            hasAny = true;
        }
        // Model
        if (state.model && state.model.value) {
            var modelLabel = getModelLabel(state.model.value);
            html += chip('model', modelLabel);
            hasAny = true;
        }
        // Range filters
        ['price', 'mileage', 'year'].forEach(function(key) {
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
        // Simple selects
        ['fuel_type', 'body_type'].forEach(function(key) {
            if (state[key]) {
                html += chip(key, filterLabels[key] + ': ' + state[key]);
                hasAny = true;
            }
        });

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

    function getMakeLabel(termId) {
        var $opt = $('.car-filter-make .car-filter-dropdown-option[data-value="' + termId + '"]').first();
        if ($opt.length) {
            return $opt.clone().children('.car-filter-count').remove().end().text().trim();
        }
        return 'Brand: ' + termId;
    }

    function getModelLabel(termId) {
        var $opt = $('.car-filter-model .car-filter-dropdown-option[data-value="' + termId + '"]').first();
        if ($opt.length) {
            return $opt.clone().children('.car-filter-count').remove().end().text().trim();
        }
        return 'Model: ' + termId;
    }

    // Remove single filter chip
    $chips.on('click', '.tcp-chip-remove', function(e) {
        e.stopPropagation();
        var key = $(this).data('filter');
        clearFilter(key);
        CarFilters.triggerFilter(group);
    });

    // Clear all
    $chips.on('click', '#tcp-clear-all', function() {
        ['make', 'model', 'price_min', 'price_max', 'mileage_min', 'mileage_max',
         'year_min', 'year_max', 'fuel_type', 'body_type'].forEach(function(key) {
            clearFilter(key);
        });
        CarFilters.triggerFilter(group);
    });

    function clearFilter(key) {
        if (key === 'make' || key === 'model') {
            CarFilters.setState(group, key, '', '');
            // Reset dropdown UI
            var cls = key === 'make' ? '.car-filter-make' : '.car-filter-model';
            $(cls + ' .car-filter-dropdown-option').removeClass('selected');
            $(cls + ' .car-filter-dropdown-option[data-value=""]').addClass('selected');
            $(cls + ' .car-filter-dropdown-text').addClass('placeholder').text(key === 'make' ? 'All Brands' : 'All Models');
            $(cls + ' select').val('');
            if (key === 'make') {
                $(document).trigger('carFilters:makeChanged', [group, '']);
            }
        } else if (key.match(/_(min|max)$/)) {
            CarFilters.setState(group, key, '');
            // Clear input
            var parts = key.split('_');
            var bound = parts.pop(); // min or max
            var field = parts.join('_');
            var filterCls = field === 'fuel_type' ? 'fuel' : (field === 'body_type' ? 'body' : field);
            $('.car-filter-' + filterCls + ' .car-filter-input-' + bound).val('');
        } else {
            CarFilters.setState(group, key, '');
            var filterCls = key === 'fuel_type' ? 'fuel' : (key === 'body_type' ? 'body' : key);
            var $dd = $('.car-filter-' + filterCls + ' .car-filter-dropdown');
            $dd.find('.car-filter-dropdown-option').removeClass('selected');
            $dd.find('.car-filter-dropdown-option[data-value=""]').addClass('selected');
            $dd.find('.car-filter-dropdown-text').addClass('placeholder').text($dd.find('select option:first').text());
            $dd.find('select').val('');
        }
    }

    // Rebuild chips whenever filters update
    $(document).on('carFilters:updated', function(e, g, data) {
        if (data.pagination_html !== undefined) {
            $pagination.html(data.pagination_html || '');
        }
        $container.data('page', data.current_page || 1);
        $container.data('max-pages', data.max_pages || 1);
        buildChips();
        closeModal();
    });

    // Also rebuild chips when state changes (before AJAX completes)
    if (window.CarFilters) {
        CarFilters.subscribe(group, function() {
            buildChips();
        });
    }

    /* ── Sort dropdown ── */
    var $sort = $('#tcp-sort');
    var $sortBtn = $('#tcp-sort-btn');
    var $sortLabel = $('#tcp-sort-label');

    $sortBtn.on('click', function(e) {
        e.stopPropagation();
        $sort.toggleClass('open');
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#tcp-sort').length) {
            $sort.removeClass('open');
        }
    });

    $sort.on('click', '.tcp-sort-option', function() {
        var $opt = $(this);
        var orderby = $opt.data('orderby');
        var order = $opt.data('order');

        // Update UI
        $sort.find('.tcp-sort-option').removeClass('selected');
        $opt.addClass('selected');
        $sortLabel.text($opt.text());
        $sort.removeClass('open');

        // Update listing_atts and reload page 1
        var atts = $container.data('atts') || {};
        atts.orderby = orderby;
        atts.order = order;
        $container.data('atts', atts);
        // Also update the data attribute for future reads
        $container.attr('data-atts', JSON.stringify(atts));

        loadPage(1);
    });

    /* ── AJAX pagination ── */
    function loadPage(page) {
        var filterData = (window.CarFilters && CarFilters.getFilterData)
            ? CarFilters.getFilterData(group)
            : {};
        var listingAtts = $container.data('atts') || {};

        $wrapper.addClass('car-listings-loading');

        $.ajax({
            url: carFiltersConfig.ajaxUrl,
            type: 'POST',
            data: $.extend({
                action: 'car_filters_filter_listings',
                nonce: carFiltersConfig.nonce,
                page: page,
                listing_atts: JSON.stringify(listingAtts)
            }, filterData),
            success: function(response) {
                if (response.success) {
                    $wrapper.html(response.data.html);
                    $pagination.html(response.data.pagination_html || '');
                    $container.data('page', response.data.current_page);
                    $container.data('max-pages', response.data.max_pages);
                    $('html, body').animate({ scrollTop: $container.offset().top - 20 }, 300);
                }
            },
            error: function(xhr, status, error) {
                console.error('Pagination AJAX error:', error);
            },
            complete: function() {
                $wrapper.removeClass('car-listings-loading');
            }
        });
    }

    $container.on('click', '.tcp-pagination a.page-numbers', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var page = parseInt(href.replace('#', ''), 10);
        if (page && page > 0) {
            loadPage(page);
        }
    });

    // Build initial chips on load
    $(document).ready(function() {
        setTimeout(buildChips, 100);
    });

})(jQuery);
</script>

<?php get_footer(); ?>
