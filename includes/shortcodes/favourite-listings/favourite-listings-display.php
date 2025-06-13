<?php
/**
 * Favorite Listings Display - Main HTML and PHP Logic
 * Separated from favourite-listings.php for better organization
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get the current page number
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

// Check if user is logged in
$user_id = get_current_user_id();

// If user is not logged in, show the sign-in prompt
if ( ! $user_id ) {
    ?>
    <div class="favorite-listings-container">
        <div class="favorites-guest-message">
            <div class="favorites-icon">
                <i class="fas fa-heart"></i>
            </div>
            <h1>Save your favourite adverts</h1>
            <p>Save an advert, and view across all your devices.</p>
            
            <h2>Manage your saved adverts</h2>
            <p>Simply sign in or register to manage your saved adverts.</p>
            
            <div class="favorites-auth-buttons">
                <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="favorites-signin-btn">Sign in</a>
                <span class="auth-separator">/</span>
                <a href="<?php echo esc_url( ($register_page = get_page_by_path('register')) ? get_permalink($register_page->ID) : wp_registration_url() ); ?>" class="favorites-register-btn">Register</a>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Get user's favorite car IDs from user meta
$favorite_car_ids = get_user_meta($user_id, 'favorite_cars', true);

// If no favorites, show a message for logged-in users
if (empty($favorite_car_ids)) {
    ?>
    <div class="favorite-listings-container">
        <div class="no-favorites-message">
            <h2>No Favorite Cars Yet</h2>
            <p>You haven't added any cars to your favorites yet.</p>
            <a href="<?php echo esc_url(get_permalink(get_page_by_path('car-listings'))); ?>" class="browse-cars-button">Browse Cars</a>
        </div>
    </div>
    <?php
    return;
}

// Query arguments for favorite car listings
$args = array(
    'post_type' => 'car',
    'posts_per_page' => $atts['per_page'],
    'paged' => $paged,
    'orderby' => $atts['orderby'],
    'order' => $atts['order'],
    'post_status' => 'publish',
    'post__in' => $favorite_car_ids, // Only get favorite cars
    'no_found_rows' => false, // We need pagination
    'meta_query' => array(
        'relation' => 'OR',
        array(
            'key' => 'is_sold',
            'compare' => 'NOT EXISTS'
        ),
        array(
            'key' => 'is_sold',
            'value' => '1',
            'compare' => '!='
        )
    )
);

// Get car listings
$car_query = new WP_Query($args);
?>

<div class="favorite-listings-container">
    <h1 class="favorite-listings-title">My Favorite Cars</h1>
    <?php if ($car_query->found_posts > 0): ?>
    <div class="results-counter">Showing <span class="count"><?php echo esc_html($car_query->found_posts); ?></span> results</div>
    <?php endif; ?>
    
    <!-- Listings Grid -->
    <div class="car-listings-grid">
        <?php
        if ($car_query->have_posts()) :
            while ($car_query->have_posts()) : $car_query->the_post();
                $post_id = get_the_ID();
                // Get car details
                $make = get_field('make', $post_id);
                $model = get_field('model', $post_id);
                $variant = get_field('variant', $post_id);
                $year = get_field('year', $post_id);
                $price = get_field('price', $post_id);
                $mileage = get_field('mileage', $post_id);
                $fav_car_city = get_field('car_city', $post_id);
                $fav_car_district = get_field('car_district', $post_id);
                $fav_display_location = '';
                if (!empty($fav_car_city) && !empty($fav_car_district)) {
                    $fav_display_location = $fav_car_city . ' - ' . $fav_car_district;
                } elseif (!empty($fav_car_city)) {
                    $fav_display_location = $fav_car_city;
                } elseif (!empty($fav_car_district)) {
                    $fav_display_location = $fav_car_district;
                }
                $engine_capacity = get_field('engine_capacity', $post_id);
                $fuel_type = get_field('fuel_type', $post_id);
                $transmission = get_field('transmission', $post_id);
                $exterior_color = get_field('exterior_color', $post_id);
                $interior_color = get_field('interior_color', $post_id);
                $description = get_field('description', $post_id);
                $body_type = get_field('body_type', $post_id);
                $drive_type = get_field('drive_type', $post_id);
                $number_of_doors = get_field('number_of_doors', $post_id);
                $number_of_seats = get_field('number_of_seats', $post_id);
                $motuntil = get_field('motuntil', $post_id);
                $extras = get_field('extras', $post_id);
                $vehiclehistory = get_field('vehiclehistory', $post_id);
                $publication_date = get_field('publication_date', $post_id);
                ?>
                <div class="car-listing-card">
                    <?php 
                    // Get all car images
                    $featured_image = get_post_thumbnail_id(get_the_ID());
                    $additional_images = get_field('car_images', get_the_ID());
                    $all_images = array();
                    
                    if ($featured_image) {
                        $all_images[] = $featured_image;
                    }
                    
                    if (is_array($additional_images)) {
                        $all_images = array_merge($all_images, $additional_images);
                    }
                    
                    if (!empty($all_images)) {
                        echo '<div class="car-listing-image-container">';
                        echo '<div class="car-listing-image-carousel" data-post-id="' . get_the_ID() . '">';
                        
                        foreach ($all_images as $index => $image_id) {
                            $image_url = wp_get_attachment_image_url($image_id, 'medium');
                            if ($image_url) {
                                echo '<div class="car-listing-image' . ($index === 0 ? ' active' : '') . '" data-index="' . $index . '">';
                                echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($year . ' ' . $make . ' ' . $model) . '">';
                                if ($index === count($all_images) - 1 && count($all_images) > 1) {
                                    echo '<a href="' . get_permalink() . '" class="see-all-images" style="display: none;">See All Images</a>';
                                }
                                echo '</div>';
                            }
                        }
                        
                        echo '<button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>';
                        echo '<button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>';
                        echo '<button class="favorite-btn active" data-car-id="' . get_the_ID() . '"><i class="fas fa-heart"></i></button>';
                        
                        echo '</div>';
                        echo '</div>';
                    }
                    ?>
                    
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="car-listing-link">
                        <div class="car-listing-details">
                            <h2 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h2>
                            <div class="car-specs">
                                <?php echo esc_html($engine_capacity); ?>L
                                <?php echo !empty($variant) ? ' ' . esc_html($variant) : ''; ?>
                                <?php 
                                    echo !empty($body_type) ? ' ' . esc_html($body_type) : '';
                                ?>
                                <?php echo !empty($transmission) ? ' ' . esc_html($transmission) : ''; ?>
                                <?php 
                                    echo !empty($drive_type) ? ' ' . esc_html($drive_type) : '';
                                ?>
                            </div>
                            <div class="car-info-boxes">
                                <div class="info-box">
                                    <span class="info-value"><?php echo number_format($mileage); ?> km</span>
                                </div>
                                <div class="info-box">
                                    <span class="info-value"><?php echo esc_html($year); ?></span>
                                </div>
                            </div>
                            <div class="car-price">€<?php echo number_format($price); ?></div>
                            <?php 
                            if (!$publication_date) {
                                $publication_date = get_the_date('Y-m-d H:i:s');
                                update_post_meta($post_id, 'publication_date', $publication_date);
                            }
                            $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                            echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                            ?>
                            <div class="car-location"><i class="fas fa-map-marker-alt"></i> <span class="location-text"><?php echo esc_html($fav_display_location); ?></span></div>
                        </div>
                    </a>
                </div>
            <?php
            endwhile;
        else :
            echo '<p class="no-listings">No favorite car listings found.</p>';
        endif;
        wp_reset_postdata();
        ?>
    </div>

    <!-- Pagination -->
    <div class="car-listings-pagination">
        <?php
        echo paginate_links(array(
            'total' => $car_query->max_num_pages,
            'current' => $paged,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;'
        ));
        ?>
    </div>
</div> 