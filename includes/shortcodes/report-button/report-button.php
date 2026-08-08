<?php
/**
 * Shared Report Button Component
 * Can be used on single car pages or anywhere else
 * Includes modal for report submission
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Register the shortcode
add_shortcode( 'report_button', 'report_button_shortcode' );

/**
 * Report Button Shortcode Function
 */
function report_button_shortcode( $atts ) {
    // Parse shortcode attributes
    $atts = shortcode_atts( array(
        'car_id' => null,
        'design' => 'default', // 'default', 'single', 'minimal'
        'size' => 'normal', // 'small', 'normal', 'large'
        'text' => '', // Optional text next to icon
        'icon_set' => 'fontawesome',
    ), $atts );

    // Get car ID - prioritize attribute, then URL parameter, then current post ID
    if ($atts['car_id']) {
        $car_id = intval($atts['car_id']);
    } elseif (isset($_GET['car_id'])) {
        $car_id = intval($_GET['car_id']);
    } else {
        $car_id = get_the_ID();
    }

    if ( ! $car_id || get_post_type( $car_id ) !== 'car' ) {
        return '<!-- Report Button: Not available for this post type -->';
    }

    // Build CSS classes based on design parameter
    $base_class = 'report-btn';
    $design_class = 'report-btn-' . esc_attr($atts['design']);
    $size_class = 'report-btn-' . esc_attr($atts['size']);

    $button_class = trim($base_class . ' ' . $design_class . ' ' . $size_class);

    ob_start();
    ?>

    <!-- Report Listing Modal (EXACT from old single-car.php) -->
    <div class="report-modal" style="display: none;">
        <div class="report-modal-content">
            <div class="report-modal-header">
                <h3><?php esc_html_e('Report this listing', 'bricks-child'); ?></h3>
                <button class="close-report-modal">
                    <?php if ($atts['icon_set'] === 'hugeicons' && function_exists('autoagora_single_car_hugeicon')) : ?>
                        <i class="report-btn-close-icon" aria-hidden="true"><?php echo autoagora_single_car_hugeicon('cancel'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded SVG. ?></i>
                    <?php else : ?>
                        <i class="fas fa-times"></i>
                    <?php endif; ?>
                </button>
            </div>
            <form id="report-listing-form" method="post">
                <div class="report-form-group">
                    <label for="report-reason"><?php esc_html_e('Reason for reporting:', 'bricks-child'); ?></label>
                    <select id="report-reason" name="report_reason" required>
                        <option value=""><?php esc_html_e('Select a reason', 'bricks-child'); ?></option>
                        <option value="fake_listing"><?php esc_html_e('Fake or fraudulent listing', 'bricks-child'); ?></option>
                        <option value="inappropriate_content"><?php esc_html_e('Inappropriate content', 'bricks-child'); ?></option>
                        <option value="spam"><?php esc_html_e('Spam', 'bricks-child'); ?></option>
                        <option value="wrong_category"><?php esc_html_e('Wrong category', 'bricks-child'); ?></option>
                        <option value="duplicate"><?php esc_html_e('Duplicate listing', 'bricks-child'); ?></option>
                        <option value="sold_vehicle"><?php esc_html_e('Vehicle already sold', 'bricks-child'); ?></option>
                        <option value="overpriced"><?php esc_html_e('Significantly overpriced', 'bricks-child'); ?></option>
                        <option value="other"><?php esc_html_e('Other', 'bricks-child'); ?></option>
                    </select>
                </div>
                <div class="report-form-group">
                    <label for="report-details"><?php esc_html_e('Additional details (optional):', 'bricks-child'); ?></label>
                    <textarea id="report-details" name="report_details" rows="4" placeholder="<?php esc_attr_e('Please provide any additional information that would help us review this report...', 'bricks-child'); ?>"></textarea>
                </div>
                <div class="report-form-group">
                    <label for="reporter-email"><?php esc_html_e('Your email (optional):', 'bricks-child'); ?></label>
                    <input type="email" id="reporter-email" name="reporter_email" placeholder="your.email@example.com" value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>">
                    <small><?php esc_html_e('We may contact you if we need more information', 'bricks-child'); ?></small>
                </div>
                <input type="hidden" name="reported_listing_id" value="<?php echo esc_attr($car_id); ?>">
                <input type="hidden" name="action" value="submit_listing_report">
                <?php wp_nonce_field('report_listing_nonce', 'report_nonce'); ?>
                <div class="report-form-actions">
                    <button type="button" class="cancel-report-btn"><?php esc_html_e('Cancel', 'bricks-child'); ?></button>
                    <button type="submit" class="submit-report-btn"><?php esc_html_e('Submit Report', 'bricks-child'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Button -->
    <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($car_id); ?>" title="<?php esc_attr_e('Report this listing', 'bricks-child'); ?>">
        <?php if ($atts['icon_set'] === 'hugeicons' && function_exists('autoagora_single_car_hugeicon')) : ?>
            <i class="report-btn-icon" aria-hidden="true"><?php echo autoagora_single_car_hugeicon('flag'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded SVG. ?></i>
        <?php else : ?>
            <i class="fas fa-flag"></i>
        <?php endif; ?>
        <?php if (!empty($atts['text'])): ?>
            <span class="report-btn-text"><?php echo esc_html($atts['text']); ?></span>
        <?php endif; ?>
    </button>

    <?php
    return ob_get_clean();
}

/**
 * Enqueue the report button scripts and styles
 */
function enqueue_report_button_assets() {
    // Load on pages where report buttons are likely to be used (Bricks compatible)
    if (is_singular('car') || is_page()) {

        // Enqueue the JavaScript
        wp_enqueue_script(
            'report-button-js',
            get_stylesheet_directory_uri() . '/includes/shortcodes/report-button/report-button.js',
            array(),
            '1.0.0',
            true
        );

        // Note: No CSS file exists for report button

        // Localize script with AJAX data for report submission
        wp_localize_script('report-button-js', 'reportButtonData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'strings' => array(
                'unavailable' => __('Error: Unable to submit report. Please refresh the page and try again.', 'bricks-child'),
                'submitting' => __('Submitting...', 'bricks-child'),
                'success' => __('Thank you for your report. We will review it and take appropriate action if necessary.', 'bricks-child'),
                'errorPrefix' => __('Error submitting report:', 'bricks-child'),
                'unknownError' => __('Unknown error occurred', 'bricks-child'),
                'failed' => __('Failed to submit report. Please try again later.', 'bricks-child'),
            ),
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_report_button_assets');
