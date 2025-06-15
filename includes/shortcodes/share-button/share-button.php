<?php
/**
 * Shared Share Button Component
 * Can be used on single car pages, blog posts, or anywhere else
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Register the shortcode
add_shortcode( 'share_button', 'share_button_shortcode' );

/**
 * Share Button Shortcode Function
 */
function share_button_shortcode( $atts ) {
    // Parse shortcode attributes
    $atts = shortcode_atts( array(
        'design' => 'default', // 'default', 'single', 'minimal'
        'size' => 'normal', // 'small', 'normal', 'large'
        'text' => '', // Optional text next to icon
    ), $atts );

    // Build CSS classes based on design parameter
    $base_class = 'share-btn';
    $design_class = 'share-btn-' . esc_attr($atts['design']);
    $size_class = 'share-btn-' . esc_attr($atts['size']);
    
    $button_class = trim($base_class . ' ' . $design_class . ' ' . $size_class);

    ob_start();
    ?>
    <button class="<?php echo esc_attr($button_class); ?>" title="Share this listing">
        <i class="fas fa-share-alt"></i>
        <?php if (!empty($atts['text'])): ?>
            <span class="share-btn-text"><?php echo esc_html($atts['text']); ?></span>
        <?php endif; ?>
    </button>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue the share button scripts and styles
 */
function enqueue_share_button_assets() {
    // Only enqueue on pages that might have the shortcode
    if (is_singular('car') || is_page() || is_archive() || has_shortcode(get_post()->post_content ?? '', 'share_button')) {
        
        // Enqueue the JavaScript
        wp_enqueue_script(
            'share-button-js',
            get_stylesheet_directory_uri() . '/includes/shortcodes/share-button/share-button.js',
            array(),
            '1.0.0',
            true
        );
        
        // Enqueue the CSS
        wp_enqueue_style(
            'share-button-css',
            get_stylesheet_directory_uri() . '/includes/shortcodes/share-button/share-button.css',
            array(),
            '1.0.0'
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_share_button_assets'); 