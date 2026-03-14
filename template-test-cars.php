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

        <?php echo do_shortcode( '[car_filters filters="make,model,price,mileage,year,fuel_type,body_type" mode="ajax" target="test-cars-listings" layout="horizontal" show_button="true" button_text="Search Cars"]' ); ?>

        <?php
        $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

        $args = array(
            'post_type'      => 'car',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'paged'          => $paged,
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

        if ( $cars_query->have_posts() ) :
            // Pre-fetch meta
            $post_ids = wp_list_pluck( $cars_query->posts, 'ID' );
            update_postmeta_cache( $post_ids );
            ?>
            <div class="test-cars-grid" id="test-cars-listings">
                <?php
                while ( $cars_query->have_posts() ) : $cars_query->the_post();
                    render_car_card( get_the_ID() );
                endwhile;
                ?>
            </div>

            <?php
            if ( $cars_query->max_num_pages > 1 ) :
                ?>
                <div class="test-cars-pagination">
                    <?php
                    echo paginate_links( array(
                        'total'     => $cars_query->max_num_pages,
                        'current'   => $paged,
                        'prev_text' => __( '&laquo; Previous', 'bricks-child' ),
                        'next_text' => __( 'Next &raquo;', 'bricks-child' ),
                    ) );
                    ?>
                </div>
                <?php
            endif;

            wp_reset_postdata();
        else :
            ?>
            <p class="test-cars-no-results"><?php esc_html_e( 'No car listings found.', 'bricks-child' ); ?></p>
            <?php
        endif;
        ?>
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
        display: inline-block;
        padding: 0.5rem 0.75rem;
        margin: 0 0.25rem;
        border-radius: 0.25rem;
        text-decoration: none;
        color: #333;
        background: #f0f0f0;
    }
    .test-cars-pagination .page-numbers.current {
        background: #333;
        color: #fff;
    }
    .test-cars-no-results {
        text-align: center;
        padding: 3rem 1rem;
        color: #666;
    }
</style>

<?php get_footer(); ?>
