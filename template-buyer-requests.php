<?php
/**
 * Template Name: Buyer Requests
 *
 * @package Bricks Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header(); ?>

<div class="bricks-container">
    <div class="bricks-content">
        <div class="buyer-requests-header">
            <h1><?php esc_html_e( 'Buyer Requests', 'bricks-child' ); ?></h1>
            <a href="<?php echo esc_url( home_url( '/create-buyer-request' ) ); ?>" class="btn btn-primary create-buyer-request-btn">
                <?php esc_html_e( 'Create your post', 'bricks-child' ); ?>
            </a>
        </div>

        <?php
        // Display success message if request was submitted
        if ( isset( $_GET['request_submitted'] ) && $_GET['request_submitted'] == 'success' ) {
            ?>
            <div class="buyer-request-success-message">
                <p><?php esc_html_e( 'Your buyer request has been submitted successfully!', 'bricks-child' ); ?></p>
            </div>
            <?php
        }

        // Query buyer requests
        $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
        $buyer_requests_query = new WP_Query( array(
            'post_type'      => 'buyer_request',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( $buyer_requests_query->have_posts() ) :
            ?>
            <div class="buyer-requests-grid">
                <?php
                while ( $buyer_requests_query->have_posts() ) : $buyer_requests_query->the_post();
                    $post_id = get_the_ID();
                    $make = get_field( 'buyer_make', $post_id );
                    $model = get_field( 'buyer_model', $post_id );
                    $year = get_field( 'buyer_year', $post_id );
                    $price = get_field( 'buyer_price', $post_id );
                    $description = get_field( 'buyer_description', $post_id );
                    $author = get_the_author();
                    $date = get_the_date();
                    ?>
                    <article class="buyer-request-card">
                        <div class="buyer-request-card-header">
                            <h3 class="buyer-request-title">
                                <?php
                                echo esc_html( $year . ' ' . $make );
                                if ( ! empty( $model ) ) {
                                    echo ' ' . esc_html( $model );
                                }
                                ?>
                            </h3>
                            <div class="buyer-request-price">
                                <?php esc_html_e( 'Up to', 'bricks-child' ); ?> 
                                <strong>€<?php echo number_format( $price, 0, ',', '.' ); ?></strong>
                            </div>
                        </div>
                        
                        <?php if ( ! empty( $description ) ) : ?>
                            <div class="buyer-request-description">
                                <?php echo wp_kses_post( $description ); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="buyer-request-meta">
                            <span class="buyer-request-author">
                                <?php echo esc_html( $author ); ?>
                            </span>
                            <span class="buyer-request-date">
                                <?php echo esc_html( $date ); ?>
                            </span>
                        </div>
                    </article>
                    <?php
                endwhile;
                ?>
            </div>

            <?php
            // Pagination
            if ( $buyer_requests_query->max_num_pages > 1 ) {
                ?>
                <div class="buyer-requests-pagination">
                    <?php
                    echo paginate_links( array(
                        'total'     => $buyer_requests_query->max_num_pages,
                        'current'   => $paged,
                        'prev_text' => __( '&laquo; Previous', 'bricks-child' ),
                        'next_text' => __( 'Next &raquo;', 'bricks-child' ),
                    ) );
                    ?>
                </div>
                <?php
            }

            wp_reset_postdata();
        else :
            ?>
            <div class="buyer-requests-empty">
                <p><?php esc_html_e( 'No buyer requests found. Be the first to create one!', 'bricks-child' ); ?></p>
                <a href="<?php echo esc_url( home_url( '/create-buyer-request' ) ); ?>" class="btn btn-primary">
                    <?php esc_html_e( 'Create your post', 'bricks-child' ); ?>
                </a>
            </div>
            <?php
        endif;
        ?>
    </div>
</div>

<?php get_footer(); ?>

