<?php
/**
 * Car Single Call Button Shortcode
 * 
 * Provides [car_single_call_button] shortcode to display call button.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Car Single Call Button Shortcode Handler
 * 
 * Usage: [car_single_call_button]
 * 
 * @param array $atts Shortcode attributes
 * @return string The call button HTML
 */
function car_single_call_button_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(), $atts, 'car_single_call_button');
    
    // Start output buffering
    ob_start();
    
    $post_author_id = get_the_author_meta('ID');
    $user_object = get_user_by('ID', $post_author_id);

    if ($user_object) {
        $author_username = $user_object->user_login;
        $tel_link_number = preg_replace('/[^0-9+]/', '', $author_username);
        $tel_link = 'tel:+' . $tel_link_number;
        $button_display_text = '+' . $author_username;
        ?>
        <a href="<?php echo esc_attr($tel_link); ?>" class="brx-button" id="single-car-call-button" style="
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
            <i class="fas fa-phone" style="color: #ffffff; font-size: 1rem;"></i>
            <span style="color: #ffffff;"><?php echo esc_html($button_display_text); ?></span>
        </a>
        <?php
    }
    
    // Return the buffered content
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('car_single_call_button', 'car_single_call_button_shortcode');
