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
 * Main reusable render function — call from any PHP context
 */
function render_car_card($post_id) {
    // Enqueue assets once
    car_card_enqueue_assets();

    // Get ACF fields
    $make = get_field('make', $post_id);
    $model = get_field('model', $post_id);
    $permalink = get_permalink($post_id);

    // Badges
    $show_full_badge = get_field('fulldetailsbadge', $post_id);
    $show_extra_badge = get_field('extradetailsbadge', $post_id);

    // Images — handle both ID array and associative array formats
    $raw_images = get_field('car_images', $post_id, false);
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
    <article class="car-card" data-post-id="<?php echo esc_attr($post_id); ?>">
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
                        $img_url = wp_get_attachment_image_url($img_id, 'large');
                        if (!$img_url) continue;
                        $is_last = ($index === $slide_count - 1) && ($slide_count === $max_slides);
                    ?>
                        <div class="car-card-slide" data-index="<?php echo $index + 1; ?>">
                            <img src="<?php echo esc_url($img_url); ?>" alt="" draggable="false" loading="lazy">
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
            <h3 class="car-card-title"><?php echo esc_html($make . ' ' . $model); ?></h3>
        </a>
    </article>
    <?php
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
