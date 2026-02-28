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
                    $author_id = get_the_author_meta( 'ID' );
                    $author_user = get_userdata( $author_id );
                    $first_name = get_user_meta( $author_id, 'first_name', true );
                    $last_name = get_user_meta( $author_id, 'last_name', true );
                    $username = $author_user ? $author_user->user_login : '';
                    
                    // Build display name from first and last name
                    $display_name = '';
                    if ( ! empty( $first_name ) || ! empty( $last_name ) ) {
                        $display_name = trim( $first_name . ' ' . $last_name );
                    }
                    
                    // Format phone number for display
                    $display_phone = '';
                    $tel_link_number = '';
                    if ( ! empty( $username ) ) {
                        $tel_link_number = preg_replace( '/[^0-9+]/', '', $username );
                        $display_phone = preg_replace( '/[^0-9+]/', '', $username );
                        $display_phone = preg_replace( '/^(.{3})(.+)/', '$1 $2', $display_phone );
                    }
                    
                    $date = get_the_date();
                    $permalink = get_permalink( $post_id );
                    
                    // Truncate description for card view
                    $description_preview = '';
                    if ( ! empty( $description ) ) {
                        $description_text = wp_strip_all_tags( $description );
                        $description_preview = mb_strlen( $description_text ) > 120 
                            ? mb_substr( $description_text, 0, 120 ) . '...' 
                            : $description_text;
                    }
                    ?>
                    <article class="buyer-request-card">
                        <a href="<?php echo esc_url( $permalink ); ?>" class="buyer-request-card-link">
                            <div class="buyer-request-card-header">
                                <div class="buyer-request-title-section">
                                    <h3 class="buyer-request-title">
                                        <?php
                                        echo esc_html( $year . ' ' . $make );
                                        if ( ! empty( $model ) ) {
                                            echo ' ' . esc_html( $model );
                                        }
                                        ?>
                                    </h3>
                                    <div class="buyer-request-badge">
                                        <?php echo get_svg_icon('magnifying-glass'); ?>
                                        <?php esc_html_e( 'Buyer Request', 'bricks-child' ); ?>
                                    </div>
                                </div>
                                <div class="buyer-request-price">
                                    <span class="buyer-request-price-label"><?php esc_html_e( 'Up to', 'bricks-child' ); ?></span>
                                    <strong class="buyer-request-price-amount">€<?php echo number_format( $price, 0, ',', '.' ); ?></strong>
                                </div>
                            </div>
                            
                            <?php if ( ! empty( $description_preview ) ) : ?>
                                <div class="buyer-request-description">
                                    <p><?php echo esc_html( $description_preview ); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="buyer-request-card-footer">
                                <div class="buyer-request-meta">
                                    <?php if ( ! empty( $display_name ) ) : ?>
                                        <div class="buyer-request-meta-item buyer-request-author-name">
                                            <?php echo get_svg_icon('user'); ?>
                                            <span class="buyer-request-author"><?php echo esc_html( $display_name ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $display_phone ) ) : ?>
                                        <div class="buyer-request-meta-item buyer-request-phone">
                                            <i class="fas fa-phone"></i>
                                            <span class="buyer-request-username"><?php echo esc_html( '+' . $display_phone ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="buyer-request-meta-item">
                                        <?php echo get_svg_icon('calendar'); ?>
                                        <span class="buyer-request-date"><?php echo esc_html( $date ); ?></span>
                                    </div>
                                </div>
                                <div class="buyer-request-view-link">
                                    <?php esc_html_e( 'View Details', 'bricks-child' ); ?>
                                    <?php echo get_svg_icon('arrow-right'); ?>
                                </div>
                            </div>
                        </a>
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

