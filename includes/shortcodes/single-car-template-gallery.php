<?php
/**
 * Single Car Template Gallery Shortcode [single_car_template_gallery post_id="{post_id}"]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function single_car_template_gallery_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'post_id' => get_the_ID()
    ), $atts );

    $post_id = intval( $atts['post_id'] );

    if ( ! $post_id ) {
        return '<p>No post ID provided for gallery.</p>';
    }

    $car_images = get_field( 'car_images', $post_id );

    if ( ! $car_images || ! is_array( $car_images ) ) {
        return '<p>No images found for this car.</p>';
    }

    $total = count( $car_images );

    $theme_dir = get_stylesheet_directory_uri();
    wp_enqueue_style( 'single-car-gallery-css', $theme_dir . '/assets/css/single-car-template-gallery.css', array(), filemtime( get_stylesheet_directory() . '/assets/css/single-car-template-gallery.css' ) );
    wp_enqueue_script( 'single-car-gallery-js', $theme_dir . '/assets/js/single-car-template-gallery.js', array(), filemtime( get_stylesheet_directory() . '/assets/js/single-car-template-gallery.js' ), true );

    ob_start();
    ?>
    <div class="single-car-gallery-wrapper">
        <div class="single-car-gallery-container" data-post-id="<?php echo esc_attr( $post_id ); ?>">

            <!-- Main slider -->
            <div class="main-slider-wrapper">
                <div class="scg-main-slider" data-total="<?php echo esc_attr( $total ); ?>">
                    <div class="scg-track">
                        <?php foreach ( $car_images as $image_id ) :
                            $image_url = wp_get_attachment_image_url( $image_id, 'large' );
                            $image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                            ?>
                            <div class="scg-slide">
                                <div class="scg-slide-bg" style="background-image:url('<?php echo esc_url( $image_url ); ?>')"></div>
                                <div class="scg-main-image-frame">
                                    <img class="scg-main-image" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" />
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Navigation arrows -->
                    <button type="button" class="custom-prev-btn" aria-label="Previous image">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="custom-next-btn" aria-label="Next image">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <!-- Photo counter -->
                    <div class="photo-counter">
                        <span class="current-slide">1</span> / <span class="total-slides"><?php echo $total; ?></span>
                    </div>

                    <!-- View all images button -->
                    <div class="view-all-button">
                        <button type="button" aria-label="View all images">View all images</button>
                    </div>
                </div>
            </div>

            <!-- Thumbnail strip -->
            <div class="thumbnail-slider-wrapper">
                <div class="scg-thumbs">
                    <?php foreach ( $car_images as $idx => $image_id ) :
                        $thumb_url = wp_get_attachment_image_url( $image_id, 'medium' );
                        $image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                        ?>
                        <div class="scg-thumb<?php echo $idx === 0 ? ' scg-thumb-active' : ''; ?>" data-index="<?php echo esc_attr( $idx ); ?>">
                            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" />
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode( 'single_car_template_gallery', 'single_car_template_gallery_shortcode' );
