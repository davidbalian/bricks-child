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
    $atts = shortcode_atts(array(), $atts, 'car_single_whatsapp_button');
    ob_start();

    $post_id = get_the_ID();
    if (!$post_id) return '';

    $post_author_id = get_post_field('post_author', $post_id);
    $user_object = get_user_by('ID', $post_author_id);

    if ($user_object) {
        // Try to get phone from user_meta instead of username
        $tel_link_number = get_user_meta($post_author_id, 'phone_number', true);
        if (!$tel_link_number) {
            $tel_link_number = preg_replace('/[^0-9]/', '', $user_object->user_login);
        }

        // ACF-safe fields
        $car_year = function_exists('get_field') ? get_field('year', $post_id) : '';
        $car_make = function_exists('get_field') ? get_field('make', $post_id) : '';
        $car_model = function_exists('get_field') ? get_field('model', $post_id) : '';

        $message_text = urlencode("Hi, I'm interested in your $car_year $car_make $car_model on AutoAgora.cy.");
        $wa_link = "https://wa.me/" . $tel_link_number . "?text=" . $message_text;
        ?>
        <a href="<?php echo esc_url($wa_link); ?>" 
           class="brx-button car-whatsapp-button" 
           id="single-car-whatsapp-button" 
           data-post-id="<?php echo esc_attr($post_id); ?>"
           style="
            padding: .75rem 1.5rem;
            background-color: var(--bricks-color-iztoge);
            border-radius: var(--radius-sm);
            display: flex;
            justify-content: center;
            align-items: center;
            column-gap: .5rem;
            text-decoration: none;
            color: #ffffff;
        ">
            <i class="fab fa-whatsapp" style="color: #ffffff; font-size: 1rem;"></i>
            <span style="color: #ffffff;">WhatsApp</span>
        </a>
        <?php
    }

    return ob_get_clean();
}
add_shortcode('car_single_whatsapp_button', 'car_single_whatsapp_button_shortcode');
