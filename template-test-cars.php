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

get_header(); ?>

<div class="bricks-container">
    <div class="bricks-content test-cars-page-wrapper">
        <h1><?php esc_html_e( 'Test Cars Page', 'bricks-child' ); ?></h1>

        <?php echo do_shortcode( '[car_filters filters="make,model,price,mileage,year,fuel,body" mode="ajax" target="test-cars-listings" layout="horizontal" show_button="true" button_text="Search Cars"]' ); ?>

        <?php
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

        <div class="car-listings-container"
             id="test-cars-listings"
             data-atts="<?php echo esc_attr( wp_json_encode( $listing_atts ) ); ?>"
             data-page="1"
             data-max-pages="<?php echo esc_attr( $cars_query->max_num_pages ); ?>">

            <div class="car-listings-wrapper test-cars-grid">
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

            <div class="test-cars-pagination">
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
</div>

<style>
    .test-cars-page-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }
    .test-cars-page-wrapper > h1 {
        margin-bottom: 1.5rem;
    }
    .test-cars-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    .test-cars-pagination {
        margin-top: 2rem;
        text-align: center;
    }
    .test-cars-pagination .page-numbers {
        list-style: none;
        display: flex;
        justify-content: center;
        gap: 0.25rem;
        padding: 0;
        margin: 0;
        flex-wrap: wrap;
    }
    .test-cars-pagination .page-numbers li {
        list-style: none;
    }
    .test-cars-pagination .page-numbers a,
    .test-cars-pagination .page-numbers span {
        display: inline-block;
        padding: 0.5rem 0.75rem;
        border-radius: 0.25rem;
        text-decoration: none;
        color: #333;
        background: #f0f0f0;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }
    .test-cars-pagination .page-numbers a:hover {
        background: #ddd;
    }
    .test-cars-pagination .page-numbers .current {
        background: #333;
        color: #fff;
        pointer-events: none;
    }
    .car-listings-wrapper.car-listings-loading {
        opacity: 0.5;
        pointer-events: none;
        transition: opacity 0.15s;
    }
</style>

<script>
(function($) {
    'use strict';

    var $container = $('#test-cars-listings');
    var $wrapper   = $container.find('.car-listings-wrapper');
    var $pagination = $container.find('.test-cars-pagination');

    function loadPage(page) {
        var group = 'default';
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

                    // Scroll to top of listings
                    $('html, body').animate({
                        scrollTop: $container.offset().top - 20
                    }, 300);
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

    // Intercept pagination clicks
    $container.on('click', '.test-cars-pagination a.page-numbers', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var page = parseInt(href.replace('#', ''), 10);
        if (page && page > 0) {
            loadPage(page);
        }
    });

    // After filter AJAX completes, also update pagination
    $(document).on('carFilters:updated', function(e, group, data) {
        if (data.pagination_html !== undefined) {
            $pagination.html(data.pagination_html || '');
        }
        $container.data('page', data.current_page || 1);
        $container.data('max-pages', data.max_pages || 1);
    });

})(jQuery);
</script>

<?php get_footer(); ?>
