<?php
/**
 * Shortcode for a Slick-based car gallery with thumbnails and lightbox.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Register the shortcode
add_shortcode( 'car_gallery_slider', 'car_gallery_slider_shortcode' );

/**
 * The shortcode function.
 *
 * @param array $atts Shortcode attributes.
 * @return string The gallery HTML.
 */
function car_gallery_slider_shortcode( $atts ) {
	// Ensure we are on a single car page or in the Bricks builder.
	if ( ! is_singular( 'car' ) && ! ( defined( 'BRICKS_IS_BUILDER' ) && BRICKS_IS_BUILDER ) ) {
		return '';
	}

    // Use get_the_ID() for better compatibility with Bricks templates.
    $post_id = get_the_ID();

    if ( ! $post_id ) {
        return '<!-- Car Gallery: Post ID not found -->';
    }

    // Get images from ACF gallery field 'car_images'.
    $images = get_field( 'car_images', $post_id );

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

	// Enqueue scripts and styles for the gallery.
	car_gallery_slider_enqueue_scripts();

	$image_count = count( $images );

	ob_start();
	?>
	<div class="car-gallery-slider-wrapper">
		<div class="main-gallery-slider">
			<?php foreach ( $images as $image ) : ?>
				<div>
					<img src="<?php echo esc_url( $image['sizes']['medium_large'] ); ?>" alt="<?php echo esc_attr( get_the_title( $image['ID'] ) ); ?>">
				</div>
			<?php endforeach; ?>
		</div>
		<div class="gallery-controls">
			<div class="photo-counter">
				<span class="current-slide">1</span>/<?php echo $image_count; ?> Photos
			</div>
		</div>
        <button class="view-all-images">View all images</button>

		<div class="thumbnail-gallery-slider">
			<?php foreach ( $images as $image ) : ?>
				<div>
					<img src="<?php echo esc_url( $image['sizes']['thumbnail'] ); ?>" alt="<?php echo esc_attr( get_the_title( $image['ID'] ) ); ?>">
				</div>
			<?php endforeach; ?>
		</div>
	</div>

    <div class="cgs-lightbox" style="display:none;">
        <div class="cgs-lightbox-content">
            <button class="cgs-close">&times;</button>
            <div class="cgs-lightbox-slider">
                <?php foreach ( $images as $image ) : ?>
                    <div>
                        <img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( get_the_title( $image['ID'] ) ); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

	<?php
	return ob_get_clean();
}

/**
 * Enqueue scripts and styles for the gallery.
 */
function car_gallery_slider_enqueue_scripts() {
	$theme_version = defined('BRICKS_CHILD_THEME_VERSION') ? BRICKS_CHILD_THEME_VERSION : '1.0.0';
	$theme_dir_uri = get_stylesheet_directory_uri();

	// Enqueue Slick CSS
	wp_enqueue_style( 'slick-css', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css', array(), '1.8.1' );
	wp_enqueue_style( 'slick-theme-css', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css', array( 'slick-css' ), '1.8.1' );

	// Enqueue Custom CSS
	wp_enqueue_style( 'car-gallery-slider-css', $theme_dir_uri . '/assets/css/car-gallery-slider.css', array(), $theme_version );

	// Enqueue Slick JS
	wp_enqueue_script( 'slick-js', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array( 'jquery' ), '1.8.1', true );

	// Enqueue Custom JS
	wp_enqueue_script( 'car-gallery-slider-js', $theme_dir_uri . '/assets/js/car-gallery-slider.js', array( 'jquery', 'slick-js' ), $theme_version, true );
} 