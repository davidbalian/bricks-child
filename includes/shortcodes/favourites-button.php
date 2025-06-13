<?php
/**
 * Favourites Button Shortcode [favourites_button]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function favourites_button_shortcode() {
    ob_start();
    
    $favourites_page = get_page_by_path('favourite-listings');
    if ($favourites_page) {
        ?>
        <div class="favourites-button">
            <a href="<?php echo esc_url(get_permalink($favourites_page->ID)); ?>">
                <i class="fas fa-heart"></i>
                <span>Saved</span>
            </a>
        </div>
        <?php
    }
    
    return ob_get_clean();
}
add_shortcode('favourites_button', 'favourites_button_shortcode'); 