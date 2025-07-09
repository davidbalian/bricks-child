<?php
/**
 * Single Car Template Gallery Shortcode [single_car_template_gallery post_id="{post_id}"]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Generates single car template gallery shortcode.
 * Uses Swiper slider with main slider and thumbnail navigation.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML for the gallery
 */
function single_car_template_gallery_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'post_id' => get_the_ID()
    ), $atts );

    $post_id = intval( $atts['post_id'] );
    
    if ( ! $post_id ) {
        return '<p>No post ID provided for gallery.</p>';
    }

    // Get car images from ACF field
    $car_images = get_field( 'car_images', $post_id );
    
    if ( ! $car_images || ! is_array( $car_images ) ) {
        return '<p>No images found for this car.</p>';
    }

    // Enqueue Swiper CSS and JS
    wp_enqueue_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css' );
    wp_enqueue_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.0', true );
    
    // Enqueue custom CSS and JS
    $theme_dir = get_stylesheet_directory_uri();
    wp_enqueue_style( 'single-car-gallery-css', $theme_dir . '/assets/css/single-car-template-gallery.css', array(), filemtime( get_stylesheet_directory() . '/assets/css/single-car-template-gallery.css' ) );
    wp_enqueue_script( 'single-car-gallery-js', $theme_dir . '/assets/js/single-car-template-gallery.js', array('swiper-js'), filemtime( get_stylesheet_directory() . '/assets/js/single-car-template-gallery.js' ), true );

    ob_start();
    ?>
    <div class="single-car-gallery-wrapper">
        <div class="single-car-gallery-container" data-post-id="<?php echo esc_attr( $post_id ); ?>">
        <!-- Main slider -->
        <div class="main-slider-wrapper">
            <div class="swiper single-car-main-slider">
                <div class="swiper-wrapper">
                    <?php foreach ( $car_images as $image_id ) : 
                        $image_url = wp_get_attachment_image_url( $image_id, 'large' );
                        $image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                        ?>
                        <div class="swiper-slide">
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Navigation arrows -->
            <div class="slider-arrows">
                <button class="custom-prev-btn" aria-label="Previous image">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="custom-next-btn" aria-label="Next image">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <!-- Photo counter -->
            <div class="photo-counter">
                <span class="current-slide">1</span> / <span class="total-slides"><?php echo count( $car_images ); ?></span>
            </div>
            
            <!-- View all images button -->
            <div class="view-all-button">
                <button type="button" aria-label="View all images">
                    View all images
                </button>
            </div>
        </div>

        <!-- Thumbnail slider -->
        <div class="thumbnail-slider-wrapper">
            <div class="swiper single-car-thumbnail-slider">
                <div class="swiper-wrapper">
                    <?php foreach ( $car_images as $image_id ) : 
                        $thumbnail_url = wp_get_attachment_image_url( $image_id, 'medium' );
                        $image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                        ?>
                        <div class="swiper-slide">
                            <div class="thumbnail">
                                <img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
    <?php
    
    return ob_get_clean();
}
add_shortcode( 'single_car_template_gallery', 'single_car_template_gallery_shortcode' ); 