<?php
/**
 * Car Single WhatsApp Button Shortcode
 *
 * Provides [car_single_whatsapp_button] shortcode to display WhatsApp contact button.
 *
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Car Single WhatsApp Button Shortcode Handler
 *
 * Usage: [car_single_whatsapp_button]
 *
 * @param array $atts Shortcode attributes
 * @return string The WhatsApp button HTML
 */
function car_single_whatsapp_button_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(), $atts, 'car_single_whatsapp_button');
    
    // Start output buffering
    ob_start();
    
    $post_author_id = get_the_author_meta('ID');
    $user_object = get_user_by('ID', $post_author_id);
    $post_id = get_the_ID(); // Get current post ID for tracking

    if ($user_object) {
        $author_username = $user_object->user_login;
        $tel_link_number = preg_replace('/[^0-9+]/', '', $author_username);
        $car_year = get_field('year', $post_id); // Assumes ACF fields for car details
        $car_make = get_field('make', $post_id);
        $car_model = get_field('model', $post_id);
        $message_text = urlencode("Hi, I'm interested in your $car_year $car_make $car_model on AutoAgora.cy.");
        $wa_link = "https://wa.me/" . $tel_link_number . "?text=" . $message_text;
        ?>
        <a href="<?php echo esc_url($wa_link); ?>" 
           class="brx-button car-whatsapp-button" 
           id="single-car-whatsapp-button" 
           data-post-id="<?php echo esc_attr($post_id); ?>"
           style="
            padding: .75rem 1.5rem;
            background-color: #25D366;
            border-radius: var(--radius-sm);
            display: flex;
            justify-content: center;
            align-items: center;
            column-gap: .5rem;
            text-decoration: none;
            color: #ffffff;
        ">
            <i class="fab fa-whatsapp" style="color: #000000; font-size: 1rem;"></i>
            <span style="color: #000000;">WhatsApp</span>
        </a>
        <?php
    }
    
    // Return the buffered content
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('car_single_whatsapp_button', 'car_single_whatsapp_button_shortcode');
