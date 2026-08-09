<?php
/**
 * Favourites Button Shortcode [favourites_button]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function favourites_button_shortcode() {
    ob_start();
    
    $favourites_url = autoagora_localized_page_url('favourite-listings');
    if ($favourites_url) {
        ?>
        <div class="favourites-button">
            <a href="<?php echo esc_url($favourites_url); ?>">
                <i class="fas fa-heart"></i>
                <span><?php esc_html_e('Saved', 'bricks-child'); ?></span>
            </a>
        </div>
        <?php
    }
    
    return ob_get_clean();
}
add_shortcode('favourites_button', 'favourites_button_shortcode');
