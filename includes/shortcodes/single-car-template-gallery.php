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
	// Ensure we are on a single car page or in the Bricks builder.
	if ( ! is_singular( 'car' ) && ! ( defined( 'BRICKS_IS_BUILDER' ) && BRICKS_IS_BUILDER ) ) {
		return '';
	}

    // Use get_the_ID() for better compatibility with Bricks templates.
    $post_id = get_the_ID();

    if ( ! $post_id ) {
        return '<!-- Single Car Template Gallery: Post ID not found -->';
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

	// Enqueue styles for the single car template gallery.
	single_car_template_gallery_enqueue_styles();

	$image_count = count( $images );

	ob_start();
	?>
	<div class="single-car-template-gallery-wrapper">
		<!-- Main Image Container -->
		<div class="main-image-container">
			<?php if ( isset( $images[0] ) ) : ?>
				<img src="<?php echo esc_url( $images[0]['sizes']['medium_large'] ); ?>" alt="<?php echo esc_attr( get_the_title( $images[0]['ID'] ) ); ?>" class="main-image">
				
				<!-- Photo Count Overlay (Top Left) -->
				<div class="photo-count-overlay">
					<i class="fas fa-camera"></i>
					<?php echo $image_count; ?> photos
				</div>
				
				<!-- View All Images Button (Bottom Right) -->
				<div class="view-all-button-container">
					<button class="view-all-images-btn" type="button">
						<i class="fas fa-images"></i>
						View All Images
					</button>
				</div>
			<?php endif; ?>
		</div>

		<!-- First 3 Images Row -->
		<?php if ( $image_count >= 1 ) : ?>
			<div class="images-row">
				<?php 
				$row_images = array_slice( $images, 0, 3 ); // Get first 3 images
				foreach ( $row_images as $image ) : 
				?>
					<div class="row-image-item">
						<img src="<?php echo esc_url( $image['sizes']['thumbnail'] ); ?>" alt="<?php echo esc_attr( get_the_title( $image['ID'] ) ); ?>">
					</div>
				<?php endforeach; ?>
				
				<!-- Fill empty slots if less than 3 images -->
				<?php 
				$remaining_slots = 3 - count( $row_images );
				for ( $i = 0; $i < $remaining_slots; $i++ ) : 
				?>
					<div class="row-image-item empty"></div>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php
	return ob_get_clean();
}

/**
 * Enqueue styles for the single car template gallery.
 */
function single_car_template_gallery_enqueue_styles() {
	$theme_version = defined('BRICKS_CHILD_THEME_VERSION') ? BRICKS_CHILD_THEME_VERSION : '1.0.0';
	$theme_dir_uri = get_stylesheet_directory_uri();

	// Enqueue Custom CSS
	wp_enqueue_style( 'single-car-template-gallery-css', $theme_dir_uri . '/assets/css/single-car-template-gallery.css', array(), $theme_version );
} 