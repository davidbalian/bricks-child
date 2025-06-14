<?php
/**
 * Shortcode for single car template gallery with main image and thumbnail row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Register the shortcode
add_shortcode( 'single_car_template_gallery', 'single_car_template_gallery_shortcode' );

/**
 * The shortcode function.
 *
 * @param array $atts Shortcode attributes.
 * @return string The gallery HTML.
 */
function single_car_template_gallery_shortcode( $atts ) {
    // Parse shortcode attributes
    $atts = shortcode_atts( array(
        'post_id' => null,
        'debug' => false,
    ), $atts );

    // Get post ID - use attribute if provided, otherwise get current post ID
    $post_id = $atts['post_id'] ? (int) $atts['post_id'] : get_the_ID();

    // Debug mode for troubleshooting
    if ( $atts['debug'] && current_user_can( 'edit_posts' ) ) {
        $debug_info = array(
            'post_id' => $post_id,
            'is_singular_car' => is_singular( 'car' ),
            'is_bricks_builder' => defined( 'BRICKS_IS_BUILDER' ) && BRICKS_IS_BUILDER,
            'post_type' => get_post_type( $post_id ),
        );
        return '<pre>' . print_r( $debug_info, true ) . '</pre>';
    }

    if ( ! $post_id ) {
        return '<!-- Single Car Template Gallery: Post ID not found -->';
    }

    // Check if post is a car post type
    if ( get_post_type( $post_id ) !== 'car' ) {
        if ( current_user_can( 'edit_posts' ) ) {
            return '<p>Single Car Template Gallery: This shortcode only works with car post type. Current post type: ' . esc_html( get_post_type( $post_id ) ) . '</p>';
        }
        return '<!-- Single Car Template Gallery: Not a car post type -->';
    }

    // Get image IDs from ACF gallery field 'car_images'.
    $image_ids = get_field( 'car_images', $post_id );
    $images = [];

    if ( ! empty( $image_ids ) && is_array($image_ids) ) {
        foreach ( $image_ids as $image_id ) {
            $image_id = (int) $image_id;
            if ( ! $image_id ) continue;

            $thumb_src = wp_get_attachment_image_src( $image_id, 'thumbnail' );
            $medium_large_src = wp_get_attachment_image_src( $image_id, 'medium_large' );
            $full_src = wp_get_attachment_url( $image_id );

            if ( $thumb_src && $medium_large_src ) {
                $images[] = [
                   'ID' => $image_id,
                   'url' => $full_src,
                   'sizes' => [
                       'thumbnail' => $thumb_src[0],
                       'medium_large' => $medium_large_src[0],
                   ]
               ];
            }
        }
    }

    // Fallback if ACF field is empty
    if ( empty( $images ) ) {
        // Try to get attached images as a fallback
        $attached_images = get_attached_media('image', $post_id);
        if (empty($attached_images)) {
            if ( current_user_can( 'edit_posts' ) ) {
                return '<p>No images found for this listing (ID: ' . esc_html($post_id) . '). Please add images to the "car_images" gallery field or attach them to the post.</p>';
            }
            return '<!-- No images found for this car listing -->';
        }
        // format to match ACF
        $images = [];
        foreach($attached_images as $image_post) {
            $image_id = $image_post->ID;
            $thumb_src = wp_get_attachment_image_src($image_id, 'thumbnail');
            $medium_large_src = wp_get_attachment_image_src($image_id, 'medium_large');
            $full_src = wp_get_attachment_url($image_id);

            if ($thumb_src && $medium_large_src) {
                 $images[] = [
                    'ID' => $image_id,
                    'url' => $full_src,
                    'sizes' => [
                        'thumbnail' => $thumb_src[0],
                        'medium_large' => $medium_large_src[0],
                    ]
                ];
            }
        }
    }

	if ( empty( $images ) ) {
		 return '<!-- No valid images found to display in gallery -->';
	}

	// Enqueue styles and scripts for the single car template gallery.
	single_car_template_gallery_enqueue_assets();

	$image_count = count( $images );

	ob_start();
	?>
	<div class="single-car-template-gallery-wrapper" data-total-images="<?php echo $image_count; ?>">
		<!-- Main Image Container with Slider -->
		<div class="main-image-container">
			<div class="main-image-slider">
				<?php foreach ( $images as $index => $image ) : ?>
					<div class="slide">
						<img src="<?php echo esc_url( $image['sizes']['medium_large'] ); ?>" alt="<?php echo esc_attr( get_the_title( $image['ID'] ) ); ?>" class="main-image">
					</div>
				<?php endforeach; ?>
			</div>
			
			<!-- Photo Count Overlay (Top Left) -->
			<div class="photo-count-overlay">
				<i class="fas fa-camera"></i>
				<span class="current-photo">1</span>/<span class="total-photos"><?php echo $image_count; ?> </span>photos
			</div>
			
			<!-- View All Images Button (Bottom Right) -->
			<div class="view-all-button-container">
				<button class="view-all-images-btn" type="button">
					<i class="fas fa-images"></i>
					View All Images
				</button>
			</div>
			
			<!-- Navigation Arrows -->
			<div class="slider-nav">
				<button class="slider-arrow slider-prev" type="button">
					<i class="fas fa-chevron-left"></i>
				</button>
				<button class="slider-arrow slider-next" type="button">
					<i class="fas fa-chevron-right"></i>
				</button>
			</div>
		</div>

		<!-- Thumbnail Navigation Row -->
		<?php if ( $image_count >= 1 ) : ?>
			<div class="images-row thumbnail-nav">
				<?php foreach ( $images as $index => $image ) : ?>
					<div class="row-image-item" data-slide="<?php echo $index; ?>">
						<img src="<?php echo esc_url( $image['sizes']['thumbnail'] ); ?>" alt="<?php echo esc_attr( get_the_title( $image['ID'] ) ); ?>">
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php
	return ob_get_clean();
}

/**
 * Enqueue styles and scripts for the single car template gallery.
 */
function single_car_template_gallery_enqueue_assets() {
	$theme_version = defined('BRICKS_CHILD_THEME_VERSION') ? BRICKS_CHILD_THEME_VERSION : '1.0.0';
	$theme_dir_uri = get_stylesheet_directory_uri();

	// Enqueue Slick Slider CSS
	wp_enqueue_style( 'slick-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css', array(), '1.8.1' );
	wp_enqueue_style( 'slick-theme-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css', array(), '1.8.1' );
	
	// Enqueue Custom CSS
	wp_enqueue_style( 'single-car-template-gallery-css', $theme_dir_uri . '/assets/css/single-car-template-gallery.css', array(), $theme_version );
	
	// Enqueue Slick Slider JS
	wp_enqueue_script( 'slick-js', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js', array('jquery'), '1.8.1', true );
	
	// Enqueue Custom JS
	wp_enqueue_script( 'single-car-template-gallery-js', $theme_dir_uri . '/assets/js/single-car-template-gallery.js', array('jquery', 'slick-js'), $theme_version, true );
} 