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
                <h3>Report this listing</h3>
                <button class="close-report-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="report-listing-form" method="post">
                <div class="report-form-group">
                    <label for="report-reason">Reason for reporting:</label>
                    <select id="report-reason" name="report_reason" required>
                        <option value="">Select a reason</option>
                        <option value="fake_listing">Fake or fraudulent listing</option>
                        <option value="inappropriate_content">Inappropriate content</option>
                        <option value="spam">Spam</option>
                        <option value="wrong_category">Wrong category</option>
                        <option value="duplicate">Duplicate listing</option>
                        <option value="sold_vehicle">Vehicle already sold</option>
                        <option value="overpriced">Significantly overpriced</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="report-form-group">
                    <label for="report-details">Additional details (optional):</label>
                    <textarea id="report-details" name="report_details" rows="4" placeholder="Please provide any additional information that would help us review this report..."></textarea>
                </div>
                <div class="report-form-group">
                    <label for="reporter-email">Your email (optional):</label>
                    <input type="email" id="reporter-email" name="reporter_email" placeholder="your.email@example.com" value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>">
                    <small>We may contact you if we need more information</small>
                </div>
                <input type="hidden" name="reported_listing_id" value="<?php echo esc_attr($car_id); ?>">
                <input type="hidden" name="action" value="submit_listing_report">
                <?php wp_nonce_field('report_listing_nonce', 'report_nonce'); ?>
                <div class="report-form-actions">
                    <button type="button" class="cancel-report-btn">Cancel</button>
                    <button type="submit" class="submit-report-btn">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Button -->
    <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($car_id); ?>" title="Report this listing">
        <i class="fas fa-flag"></i>
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
    // Load conditionally only when shortcode is present
    global $post;
    if (is_singular() && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'report_button')) {
        
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
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_report_button_assets'); 