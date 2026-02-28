<?php
/**
 * Template for displaying single buyer request posts
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
        <?php
        while ( have_posts() ) : the_post();
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
            
            // Format phone number for display and links
            $tel_link_number = '';
            $display_phone = '';
            $tel_link = '';
            if ( ! empty( $username ) ) {
                $tel_link_number = preg_replace( '/[^0-9+]/', '', $username );
                $display_phone = preg_replace( '/[^0-9+]/', '', $username );
                $display_phone = preg_replace( '/^(.{3})(.+)/', '$1 $2', $display_phone );
                $tel_link = 'tel:+' . $tel_link_number;
            }
            
            // WhatsApp link
            $wa_link = '';
            if ( ! empty( $tel_link_number ) ) {
                $buyer_year = get_field( 'buyer_year', $post_id );
                $buyer_make = get_field( 'buyer_make', $post_id );
                $buyer_model = get_field( 'buyer_model', $post_id );
                $car_info = $buyer_year . ' ' . $buyer_make;
                if ( ! empty( $buyer_model ) ) {
                    $car_info .= ' ' . $buyer_model;
                }
                $message_text = urlencode( "Hi, I saw your buyer request for a $car_info on AutoAgora.cy." );
                $wa_link = "https://wa.me/" . $tel_link_number . "?text=" . $message_text;
            }
            
            $date = get_the_date();
            ?>
            
            <div class="single-buyer-request">
                <!-- Back Button -->
                <div class="buyer-request-back">
                    <a href="<?php echo esc_url( home_url( '/buyer-requests' ) ); ?>" class="buyer-request-back-link">
                        <?php echo get_svg_icon('arrow-left'); ?>
                        <?php esc_html_e( 'Back to Buyer Requests', 'bricks-child' ); ?>
                    </a>
                </div>

                <!-- Main Content -->
                <div class="single-buyer-request-content">
                    <!-- Header Section -->
                    <div class="single-buyer-request-header">
                        <div class="buyer-request-badge-large">
                            <?php echo get_svg_icon('magnifying-glass'); ?>
                            <?php esc_html_e( 'Buyer Request', 'bricks-child' ); ?>
                        </div>
                        <h1 class="single-buyer-request-title">
                            <?php
                            echo esc_html( $year . ' ' . $make );
                            if ( ! empty( $model ) ) {
                                echo ' ' . esc_html( $model );
                            }
                            ?>
                        </h1>
                        <div class="single-buyer-request-price">
                            <span class="price-label"><?php esc_html_e( 'Maximum Price', 'bricks-child' ); ?></span>
                            <span class="price-amount">€<?php echo number_format( $price, 0, ',', '.' ); ?></span>
                        </div>
                    </div>

                    <!-- Details Section -->
                    <div class="single-buyer-request-details">
                        <div class="buyer-request-details-grid">
                            <div class="buyer-request-detail-item">
                                <div class="detail-icon">
                                    <?php echo get_svg_icon('car-side'); ?>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label"><?php esc_html_e( 'Make', 'bricks-child' ); ?></span>
                                    <span class="detail-value"><?php echo esc_html( $make ); ?></span>
                                </div>
                            </div>

                            <?php if ( ! empty( $model ) ) : ?>
                                <div class="buyer-request-detail-item">
                                    <div class="detail-icon">
                                        <?php echo get_svg_icon('car'); ?>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label"><?php esc_html_e( 'Model', 'bricks-child' ); ?></span>
                                        <span class="detail-value"><?php echo esc_html( $model ); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="buyer-request-detail-item">
                                <div class="detail-icon">
                                    <?php echo get_svg_icon('calendar'); ?>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label"><?php esc_html_e( 'Year', 'bricks-child' ); ?></span>
                                    <span class="detail-value"><?php echo esc_html( $year ); ?></span>
                                </div>
                            </div>

                            <div class="buyer-request-detail-item">
                                <div class="detail-icon">
                                    <?php echo get_svg_icon('euro-sign'); ?>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label"><?php esc_html_e( 'Maximum Price', 'bricks-child' ); ?></span>
                                    <span class="detail-value">€<?php echo number_format( $price, 0, ',', '.' ); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description Section -->
                    <?php if ( ! empty( $description ) ) : ?>
                        <div class="single-buyer-request-description">
                            <h2><?php echo get_svg_icon('align-left'); ?> <?php esc_html_e( 'Description', 'bricks-child' ); ?></h2>
                            <div class="description-content">
                                <?php echo wp_kses_post( $description ); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Author & Date Section -->
                    <div class="single-buyer-request-meta">
                        <?php if ( ! empty( $display_name ) ) : ?>
                            <div class="buyer-request-meta-item">
                                <div class="meta-icon">
                                    <?php echo get_svg_icon('user'); ?>
                                </div>
                                <div class="meta-content">
                                    <span class="meta-label"><?php esc_html_e( 'Posted by', 'bricks-child' ); ?></span>
                                    <span class="meta-value"><?php echo esc_html( $display_name ); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $display_phone ) && ! empty( $tel_link ) ) : ?>
                            <div class="buyer-request-meta-item">
                                <div class="meta-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="meta-content">
                                    <span class="meta-label"><?php esc_html_e( 'Phone', 'bricks-child' ); ?></span>
                                    <a href="<?php echo esc_attr( $tel_link ); ?>" class="meta-value meta-phone-link">
                                        <?php echo esc_html( '+' . $display_phone ); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $wa_link ) ) : ?>
                            <div class="buyer-request-meta-item buyer-request-whatsapp">
                                <a href="<?php echo esc_url( $wa_link ); ?>" 
                                   class="buyer-request-whatsapp-button"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <i class="fab fa-whatsapp"></i>
                                    <span><?php esc_html_e( 'WhatsApp', 'bricks-child' ); ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="buyer-request-meta-item">
                            <div class="meta-icon">
                                <?php echo get_svg_icon('calendar'); ?>
                            </div>
                            <div class="meta-content">
                                <span class="meta-label"><?php esc_html_e( 'Posted on', 'bricks-child' ); ?></span>
                                <span class="meta-value"><?php echo esc_html( $date ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        endwhile;
        ?>
    </div>
</div>

<?php get_footer(); ?>

