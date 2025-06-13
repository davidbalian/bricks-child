<?php
/**
 * Template part for displaying a single car listing card.
 *
 * Expected to be called via get_template_part() and passed a 'post_id' in the $args array.
 *
 * @package Astra Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Get the post ID from the arguments passed to get_template_part
$car_post_id = isset( $args['post_id'] ) ? $args['post_id'] : get_the_ID();

if ( ! $car_post_id ) {
    return;
}

// --- Start Car Card HTML (Adapted from car-listings.php AJAX handler) ---
$car_detail_url = esc_url( get_permalink( $car_post_id ) );

// Get car details
$make             = get_field( 'make', $car_post_id );
$model            = get_field( 'model', $car_post_id );
$variant          = get_field( 'variant', $car_post_id ); // Make sure this field exists and is correct
$year             = get_field( 'year', $car_post_id );
$price            = get_field( 'price', $car_post_id );
$mileage          = get_field( 'mileage', $car_post_id );
$car_city         = get_field( 'car_city', $car_post_id );
$car_district     = get_field( 'car_district', $car_post_id );
$display_location = '';
if ( ! empty( $car_city ) && ! empty( $car_district ) ) {
    $display_location = $car_city . ' - ' . $car_district;
} elseif ( ! empty( $car_city ) ) {
    $display_location = $car_city;
} elseif ( ! empty( $car_district ) ) {
    $display_location = $car_district;
}

$engine_capacity  = get_field( 'engine_capacity', $car_post_id );
$fuel_type        = get_field( 'fuel_type', $car_post_id );
$transmission     = get_field( 'transmission', $car_post_id );
$body_type        = get_field( 'body_type', $car_post_id );
$publication_date = get_field( 'publication_date', $car_post_id );
$latitude         = get_field( 'car_latitude', $car_post_id );
$longitude        = get_field( 'car_longitude', $car_post_id );

?>
<div class="car-listing-card"
     data-city="<?php echo esc_attr( $car_city ); ?>"
     data-district="<?php echo esc_attr( $car_district ); ?>"
     data-latitude="<?php echo esc_attr( $latitude ); ?>"
     data-longitude="<?php echo esc_attr( $longitude ); ?>"
     data-post-id="<?php echo esc_attr( $car_post_id ); ?>">
    <?php
    $featured_image    = get_post_thumbnail_id( $car_post_id );
    $additional_images = get_field( 'car_images', $car_post_id );
    $all_images        = array();

    if ( $featured_image ) {
        $all_images[] = $featured_image;
    }

    if ( is_array( $additional_images ) ) {
        $all_images = array_merge( $all_images, $additional_images );
    }

    if ( ! empty( $all_images ) ) {
        echo '<div class="car-listing-image-container">';
        echo '<div class="car-listing-image-carousel" data-post-id="' . esc_attr( $car_post_id ) . '">';

        foreach ( $all_images as $index => $image_id ) {
            $image_url = wp_get_attachment_image_url( $image_id, 'medium' );
            if ( $image_url ) {
                $clean_year = str_replace( ',', '', $year );
                echo '<div class="car-listing-image' . ( $index === 0 ? ' active' : '' ) . '" data-index="' . esc_attr( $index ) . '">';
                echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $clean_year . ' ' . $make . ' ' . $model ) . '">';
                if ( $index === count( $all_images ) - 1 && count( $all_images ) > 1 ) {
                    echo '<a href="' . $car_detail_url . '" class="see-all-images" style="display: none;">See All Images</a>';
                }
                echo '</div>';
            }
        }

        echo '<button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>';
        echo '<button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>';

        $user_id       = get_current_user_id();
        $favorite_cars = get_user_meta( $user_id, 'favorite_cars', true );
        $favorite_cars = is_array( $favorite_cars ) ? $favorite_cars : array();
        $is_favorite   = in_array( $car_post_id, $favorite_cars );
        $button_class  = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
        $heart_class   = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
        echo '<button class="' . esc_attr( $button_class ) . '" data-car-id="' . esc_attr( $car_post_id ) . '"><i class="' . esc_attr( $heart_class ) . '"></i></button>';

        echo '</div>'; // .car-listing-image-carousel
        echo '</div>'; // .car-listing-image-container
    }
    ?>

    <a href="<?php echo $car_detail_url; ?>" class="car-listing-link">
        <div class="car-listing-details">
            <h2 class="car-title"><?php echo esc_html( $make . ' ' . $model ); ?></h2>
            <div class="car-specs">
                <?php
                $specs_array = array();
                if ( ! empty( $engine_capacity ) ) {
                    $specs_array[] = esc_html( $engine_capacity ) . 'L';
                }
                if ( ! empty( $fuel_type ) ) {
                    $specs_array[] = esc_html( $fuel_type );
                }
                if ( ! empty( $body_type ) ) {
                    $specs_array[] = esc_html( $body_type );
                }
                if ( ! empty( $transmission ) ) {
                    $specs_array[] = esc_html( $transmission );
                }

                echo implode( ' | ', $specs_array );
                ?>
            </div>
            <div class="car-info-boxes">
            <div class="info-box">
                    <span class="info-value"><?php echo esc_html( str_replace( ',', '', $year ?? '' ) ); ?></span>
                </div>
                <div class="info-box">
                    <span class="info-value"><?php echo number_format( floatval( str_replace( ',', '', $mileage ?? '0' ) ) ); ?> km</span>
                </div>
            </div>
            <div class="car-price">€<?php echo number_format( floatval( str_replace( ',', '', $price ?? '0' ) ) ); ?></div>
            <div class="car-listing-additional-info">
                <?php
                if ( ! $publication_date ) {
                    // Fallback if publication_date is not set, though it should be set on save
                    $publication_date = get_the_date( 'Y-m-d H:i:s', $car_post_id );
                    // Consider if you need to update_post_meta here or handle it elsewhere
                }
                $formatted_date = date_i18n( 'F j, Y', strtotime( $publication_date ) );
                echo '<div class="car-publication-date">Listed on ' . esc_html( $formatted_date ) . '</div>';
                ?>
                <p class="car-location"><i class="fas fa-map-marker-alt"></i> <span class="location-text"><?php echo esc_html( $display_location ); ?></span></p>
            </div>
        </div>
    </a>
</div>
<?php // --- End Car Card HTML --- ?> 