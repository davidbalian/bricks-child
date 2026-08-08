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
        'icon_set' => 'fontawesome',
    ), $atts );

    // Build CSS classes based on design parameter
    $base_class = 'share-btn';
    $design_class = 'share-btn-' . esc_attr($atts['design']);
    $size_class = 'share-btn-' . esc_attr($atts['size']);

    $button_class = trim($base_class . ' ' . $design_class . ' ' . $size_class);

    ob_start();
    ?>
    <button class="<?php echo esc_attr($button_class); ?>" title="<?php esc_attr_e('Share this listing', 'bricks-child'); ?>">
        <?php if ($atts['icon_set'] === 'lucide' && function_exists('autoagora_single_car_lucide_icon')) : ?>
            <i class="share-btn-icon" aria-hidden="true"><?php echo autoagora_single_car_lucide_icon('share-2'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded SVG. ?></i>
        <?php else : ?>
            <i class="fas fa-share-alt"></i>
        <?php endif; ?>
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
    // Load on pages where share buttons are likely to be used (Bricks compatible)
    if (is_singular('car') || is_page()) {

        // Enqueue the JavaScript
        wp_enqueue_script(
            'share-button-js',
            get_stylesheet_directory_uri() . '/includes/shortcodes/share-button/share-button.js',
            array(),
            '1.0.0',
            true
        );
        wp_localize_script('share-button-js', 'shareButtonData', array(
            'linkCopied' => __('Link copied to clipboard!', 'bricks-child'),
            'copyFailed' => __('Unable to copy link. Please copy manually:', 'bricks-child'),
        ));

        // Note: No CSS file exists for share button
    }
}
add_action('wp_enqueue_scripts', 'enqueue_share_button_assets');
