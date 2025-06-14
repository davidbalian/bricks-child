<?php
/**
 * Car Gallery Shortcode
 * 
 * Displays a slick slider gallery with full-screen popup for car images
 * 
 * Usage: [car_gallery_slider]
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function car_gallery_slider_shortcode() {
    // Get the current post ID
    $post_id = get_the_ID();
    
    // Get gallery images
    $gallery_images = get_post_meta($post_id, 'gallery_images', true);
    if (empty($gallery_images)) {
        return '';
    }

    // Enqueue required scripts and styles
    wp_enqueue_style('slick', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
    wp_enqueue_style('slick-theme', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css');
    wp_enqueue_script('slick', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), null, true);
    
    // Enqueue our custom styles and scripts
    wp_enqueue_style('car-gallery', get_stylesheet_directory_uri() . '/assets/css/car-gallery.css');
    wp_enqueue_script('car-gallery', get_stylesheet_directory_uri() . '/assets/js/car-gallery.js', array('jquery', 'slick'), null, true);

    ob_start();
    ?>
    <div class="car-gallery-container">
        <!-- Hero Slider -->
        <div class="hero-slider">
            <?php foreach ($gallery_images as $image) : ?>
                <div class="hero-slide">
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="hero-image">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Thumbnail Slider -->
        <div class="thumbnail-slider">
            <?php foreach ($gallery_images as $image) : ?>
                <div class="thumbnail-slide">
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="thumbnail-image">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- View Gallery Button -->
        <button class="view-gallery-btn">View Gallery</button>

        <!-- Full Page Gallery -->
        <div class="fullpage-gallery">
            <div class="fullpage-slider">
                <?php foreach ($gallery_images as $image) : ?>
                    <div class="fullpage-slide">
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="fullpage-image">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="fullpage-counter">
                <span class="current-slide">1</span> / <span class="total-slides"><?php echo count($gallery_images); ?></span>
            </div>
            <button class="fullpage-close">&times;</button>
            <button class="fullpage-prev">&lt;</button>
            <button class="fullpage-next">&gt;</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('car_gallery_slider', 'car_gallery_slider_shortcode'); 