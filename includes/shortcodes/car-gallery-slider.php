<?php
/**
 * Shortcode for a static car gallery with hero image and thumbnail row.
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

	// Enqueue styles for the static gallery.
	car_gallery_static_enqueue_styles();

	$image_count = count( $images );

	ob_start();
	?>
	<div class="car-gallery-static-wrapper">
		<!-- Hero Image -->
		<div class="hero-image-container">
			<?php if ( isset( $images[0] ) ) : ?>
				<img src="<?php echo esc_url( $images[0]['sizes']['medium_large'] ); ?>" alt="<?php echo esc_attr( get_the_title( $images[0]['ID'] ) ); ?>" class="hero-image">
				<div class="image-count-overlay">
					<i class="fas fa-camera"></i>
					<?php echo $image_count; ?> photos
				</div>
			<?php endif; ?>
		</div>

		<!-- Thumbnail Row (3 images) -->
		<?php if ( $image_count > 1 ) : ?>
			<div class="thumbnail-row">
				<?php 
				$thumbnail_images = array_slice( $images, 1, 3 ); // Get next 3 images after hero
				foreach ( $thumbnail_images as $image ) : 
				?>
					<div class="thumbnail-item">
						<img src="<?php echo esc_url( $image['sizes']['thumbnail'] ); ?>" alt="<?php echo esc_attr( get_the_title( $image['ID'] ) ); ?>">
					</div>
				<?php endforeach; ?>
				
				<!-- Fill empty slots if less than 3 images -->
				<?php 
				$remaining_slots = 3 - count( $thumbnail_images );
				for ( $i = 0; $i < $remaining_slots; $i++ ) : 
				?>
					<div class="thumbnail-item empty"></div>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php
	return ob_get_clean();
}

/**
 * Enqueue styles for the static gallery.
 */
function car_gallery_static_enqueue_styles() {
	$theme_version = defined('BRICKS_CHILD_THEME_VERSION') ? BRICKS_CHILD_THEME_VERSION : '1.0.0';
	$theme_dir_uri = get_stylesheet_directory_uri();

	// Enqueue Custom CSS
	wp_enqueue_style( 'car-gallery-static-css', $theme_dir_uri . '/assets/css/car-gallery-static.css', array(), $theme_version );
} 