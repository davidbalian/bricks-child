<?php
/**
 * Single Car Page Buttons Shortcode [single_car_buttons]
 * Contains: Favorite, Share, and Report buttons with modal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Register the shortcode
add_shortcode( 'single_car_buttons', 'single_car_buttons_shortcode' );

/**
 * Single Car Buttons Shortcode Function
 */
function single_car_buttons_shortcode( $atts ) {
    // Parse shortcode attributes
    $atts = shortcode_atts( array(
        'post_id' => null,
    ), $atts );

    // Get post ID - prioritize URL parameter, then attribute, then current post ID
    if (isset($_GET['car_id'])) {
        $car_id = intval($_GET['car_id']);
    } elseif ($atts['post_id']) {
        $car_id = intval($atts['post_id']);
    } else {
        $car_id = get_the_ID();
    }

    if ( ! $car_id || get_post_type( $car_id ) !== 'car' ) {
        return '<!-- Single Car Buttons: Not available for this post type -->';
    }

    // Favorite button logic (EXACT from old single-car.php)
    $user_id = get_current_user_id();
    $favorite_cars = $user_id ? get_user_meta($user_id, 'favorite_cars', true) : array();
    $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
    $is_favorite = $user_id ? in_array($car_id, $favorite_cars) : false;
    $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
    $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';

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

    <!-- Action Buttons (EXACT from old single-car.php) -->
    <div class="car-listing-detailed-container">
        <div class="car-listing-header">
            <div class="action-buttons">
                <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($car_id); ?>">
                    <i class="<?php echo esc_attr($heart_class); ?>"></i>
                </button>
                <button class="share-btn">
                    <i class="fas fa-share-alt"></i>
                </button>
                <button class="report-btn" data-car-id="<?php echo esc_attr($car_id); ?>" title="Report this listing">
                    <i class="fas fa-flag"></i>
                </button>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
