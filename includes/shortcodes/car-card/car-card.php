<?php
/**
 * Reusable Car Card Component
 *
 * Renders a car card with image slider, badges, favorite button, and title.
 * Can be used as a shortcode [car_card post_id="123"] or called directly
 * via render_car_card($post_id) from any PHP context.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register the shortcode
add_shortcode('car_card', 'car_card_shortcode');

/**
 * Prefer smaller card-focused sizes to reduce listing payload.
 *
 * @param int $attachment_id Attachment ID.
 * @return string Registered size slug.
 */
function car_card_best_image_size_for_attachment($attachment_id) {
    foreach (array('car_medium', 'medium', 'car_large', 'large', 'thumbnail') as $size) {
        if (wp_get_attachment_image_url($attachment_id, $size)) {
            return $size;
        }
    }
    return 'full';
}

/**
 * Shortcode handler
 */
function car_card_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_id' => '',
    ), $atts, 'car_card');

    $post_id = !empty($atts['post_id']) ? intval($atts['post_id']) : get_the_ID();

    if (!$post_id) {
        return '<!-- car_card: no valid post ID -->';
    }

    ob_start();
    render_car_card($post_id);
    return ob_get_clean();
}

/**
 * Main reusable render function — call from any PHP context.
 *
 * @param int   $post_id Post ID.
 * @param array $context Optional. Pass array( 'listing_index' => 0 ) for the first card in a
 *                       server-rendered grid so the first slide gets LCP hints (eager + fetchpriority high).
 */
function render_car_card($post_id, $context = array()) {
    // Enqueue assets once
    car_card_enqueue_assets();

    $listing_index      = array_key_exists('listing_index', $context) ? (int) $context['listing_index'] : -1;
    $is_first_grid_card = ($listing_index === 0);

    // Read raw meta once to avoid repeated ACF field lookups per card.
    $make = car_card_get_meta_value($post_id, 'make');
    $model = car_card_get_meta_value($post_id, 'model');
    $price = car_card_get_meta_value($post_id, 'price');
    $mileage = car_card_get_meta_value($post_id, 'mileage');
    $engine_capacity = car_card_get_meta_value($post_id, 'engine_capacity');
    $fuel_type = car_card_get_meta_value($post_id, 'fuel_type');
    $transmission = car_card_get_meta_value($post_id, 'transmission');
    $car_district = car_card_get_meta_value($post_id, 'car_district');
    $car_city = car_card_get_meta_value($post_id, 'car_city');
    $permalink = get_permalink($post_id);

    // Relative date
    $publication_date = get_post_meta($post_id, 'publication_date', true);
    if (empty($publication_date)) {
        $publication_date = get_the_date('Y-m-d H:i:s', $post_id);
    }
    $now = current_time('timestamp');
    $post_time = strtotime($publication_date);
    $time_diff = $now - $post_time;
    $days = floor($time_diff / (60 * 60 * 24));

    if ($days == 0) {
        $relative_date = '<span class="post-date posted-today">Today</span>';
    } elseif ($days == 1) {
        $relative_date = '<span class="post-date">Yesterday</span>';
    } elseif ($days >= 2 && $days <= 6) {
        $relative_date = '<span class="post-date">' . $days . ' days ago</span>';
    } elseif ($days >= 7 && $days <= 13) {
        $relative_date = '<span class="post-date">1 week ago</span>';
    } elseif ($days >= 14 && $days <= 20) {
        $relative_date = '<span class="post-date">2 weeks ago</span>';
    } elseif ($days >= 21 && $days <= 27) {
        $relative_date = '<span class="post-date">3 weeks ago</span>';
    } elseif ($days >= 28 && $days <= 59) {
        $relative_date = '<span class="post-date">1 month ago</span>';
    } else {
        $months = floor($days / 30);
        $relative_date = '<span class="post-date">' . $months . ' months ago</span>';
    }

    // Badges & featured
    $show_full_badge = car_card_get_meta_value($post_id, 'fulldetailsbadge');
    $show_extra_badge = car_card_get_meta_value($post_id, 'extradetailsbadge');
    $is_featured = car_card_get_meta_value($post_id, 'is_featured');

    // Images — handle both ID array and associative array formats
    $raw_images = get_post_meta($post_id, 'car_images', true);
    $image_ids = array();

    if (!empty($raw_images) && is_array($raw_images)) {
        foreach ($raw_images as $img) {
            if (is_array($img) && isset($img['ID'])) {
                $image_ids[] = $img['ID'];
            } elseif (is_numeric($img)) {
                $image_ids[] = intval($img);
            }
        }
    }

    $total_images = count($image_ids);
    $max_slides = 5;
    $slide_ids = array_slice($image_ids, 0, $max_slides);
    $slide_count = count($slide_ids);
    ?>
    <article class="car-card<?php echo $is_featured ? ' car-card-featured' : ''; ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
        <!-- ROW 1: Slider -->
        <div class="car-card-slider" data-total="<?php echo esc_attr($total_images); ?>" data-slides="<?php echo esc_attr($slide_count); ?>">

            <!-- Badges (top-left) -->
            <?php if ($show_full_badge || $show_extra_badge) : ?>
                <div class="car-card-badges">
                    <?php if ($show_full_badge) : ?>
                        <span class="car-card-badge badge-full">Full Details</span>
                    <?php endif; ?>
                    <?php if ($show_extra_badge) : ?>
                        <span class="car-card-badge badge-extra">Extra Details</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Favorite button (top-right) -->
            <?php car_card_render_favorite_button($post_id); ?>

            <!-- Slides track -->
            <?php if ($slide_count > 0) : ?>
                <div class="car-card-slider-track">
                    <?php foreach ($slide_ids as $index => $img_id) :
                        $slide_size     = car_card_best_image_size_for_attachment($img_id);
                        $slide_img_html = wp_get_attachment_image(
                            $img_id,
                            $slide_size,
                            false,
                            array(
                                'alt'         => '',
                                'decoding'    => 'async',
                                'draggable'   => 'false',
                                'sizes'       => '(max-width: 767px) 92vw, (max-width: 1200px) 48vw, 420px',
                                'class'       => 'car-card-slide-img',
                                'loading'     => ($is_first_grid_card && $index === 0) ? 'eager' : 'lazy',
                                'fetchpriority' => ($is_first_grid_card && $index === 0) ? 'high' : 'low',
                            )
                        );
                        if ($slide_img_html === '') {
                            continue;
                        }
                        $is_last = ($index === $slide_count - 1) && ($slide_count === $max_slides);
                    ?>
                        <div class="car-card-slide" data-index="<?php echo $index + 1; ?>">
                            <?php echo $slide_img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image is escaped by core. ?>
                            <?php if ($is_last) : ?>
                                <div class="car-card-slide-overlay">
                                    <a href="<?php echo esc_url($permalink); ?>" class="car-card-view-all-btn">View All Images</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($slide_count > 1) : ?>
                    <button class="car-card-arrow car-card-arrow-left car-card-arrow-hidden" aria-label="Previous image">
                        <svg viewBox="0 0 12 12"><polyline points="8,2 4,6 8,10"/></svg>
                    </button>
                    <button class="car-card-arrow car-card-arrow-right" aria-label="Next image">
                        <svg viewBox="0 0 12 12"><polyline points="4,2 8,6 4,10"/></svg>
                    </button>
                    <div class="car-card-dots">
                        <?php for ($i = 0; $i < $slide_count; $i++) : ?>
                            <span class="car-card-dot<?php echo $i === 0 ? ' car-card-dot-active' : ''; ?>"></span>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

                <span class="car-card-counter">1/<?php echo esc_html($total_images); ?></span>
            <?php else : ?>
                <div class="car-card-no-image">No Image</div>
            <?php endif; ?>
        </div>

        <!-- ROW 2: Body -->
        <a href="<?php echo esc_url($permalink); ?>" class="car-card-body">
            <h3 class="car-card-title"><?php echo esc_html(get_the_title($post_id)); ?></h3>

            <?php if ($mileage) : ?>
                <div class="car-card-mileage"><?php echo esc_html(number_format(floatval(str_replace(',', '', $mileage)))); ?>km</div>
            <?php endif; ?>

            <div class="car-card-specs">
                <?php
                $specs = array();
                if ($engine_capacity) $specs[] = esc_html($engine_capacity) . 'L';
                if ($fuel_type)       $specs[] = esc_html($fuel_type);
                if ($transmission)    $specs[] = esc_html($transmission);
                echo implode(' <span class="car-card-specs-sep">|</span> ', $specs);
                ?>
            </div>

            <?php if ($price) : ?>
                <div class="car-card-price">&euro;<?php echo esc_html(number_format(floatval(str_replace(',', '', $price)))); ?></div>
            <?php endif; ?>

            <div class="car-card-footer">
                <span class="car-card-location">
                    <i class="fas fa-location-dot"></i>
                    <?php
                    $location_parts = array();
                    if ($car_district) $location_parts[] = esc_html($car_district);
                    if ($car_city)     $location_parts[] = esc_html($car_city);
                    echo implode(', ', $location_parts);
                    ?>
                </span>
                <span class="car-card-date"><?php echo $relative_date; ?></span>
            </div>
        </a>
    </article>
    <?php
}

/**
 * Fetch a single post meta value for card rendering.
 *
 * Uses the WP meta cache primed by listing queries.
 *
 * @param int    $post_id  Listing post ID.
 * @param string $meta_key Meta key.
 * @return mixed
 */
function car_card_get_meta_value($post_id, $meta_key) {
    return get_post_meta($post_id, $meta_key, true);
}

/**
 * Render favorite button inline with static cache (same pattern as car-listings)
 */
function car_card_render_favorite_button($post_id) {
    static $user_id = null;
    static $favorite_cars = null;

    if ($user_id === null) {
        $user_id = get_current_user_id();
        $favorite_cars = $user_id ? get_user_meta($user_id, 'favorite_cars', true) : array();
        $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
    }

    $is_favorite = $user_id && in_array($post_id, $favorite_cars);
    $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
    $button_class = 'favorite-btn favorite-btn-listing favorite-btn-small' . ($is_favorite ? ' active' : '');
    ?>
    <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($post_id); ?>" title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
        <i class="<?php echo esc_attr($heart_class); ?>"></i>
    </button>
    <?php
}

/**
 * Enqueue CSS and JS assets (once per page load)
 */
function car_card_enqueue_assets() {
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;

    $base_path = get_stylesheet_directory() . '/includes/shortcodes/car-card/';
    $base_url = get_stylesheet_directory_uri() . '/includes/shortcodes/car-card/';

    wp_enqueue_style(
        'car-card',
        $base_url . 'car-card.css',
        array(),
        file_exists($base_path . 'car-card.css') ? filemtime($base_path . 'car-card.css') : '1.0'
    );

    wp_enqueue_script(
        'car-card',
        $base_url . 'car-card.js',
        array(),
        file_exists($base_path . 'car-card.js') ? filemtime($base_path . 'car-card.js') : '1.0',
        true
    );
}
