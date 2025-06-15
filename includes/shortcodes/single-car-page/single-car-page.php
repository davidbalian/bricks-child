<?php
/**
 * Single Car Page Buttons Shortcode
 * Displays favorite, share, and report buttons for single car pages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function single_car_buttons_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'car_id' => '',
    ), $atts);

    // Get car ID from various sources
    $car_id = '';
    if (!empty($atts['car_id'])) {
        $car_id = intval($atts['car_id']);
    } elseif (isset($_GET['car_id'])) {
        $car_id = intval($_GET['car_id']);
    } else {
        global $post;
        if ($post) {
            $car_id = $post->ID;
        }
    }

    if (empty($car_id)) {
        return '<p>No car listing specified.</p>';
    }

    // Enqueue styles and scripts
    wp_enqueue_style('single-car-page-css', get_stylesheet_directory_uri() . '/includes/shortcodes/single-car-page/single-car-page.css', array(), '1.0.0');
    wp_enqueue_script('single-car-buttons-js', get_stylesheet_directory_uri() . '/includes/shortcodes/single-car-page/single-car-buttons.js', array('jquery'), '1.0.0', true);
    
    // Localize script with AJAX data (same as existing car listings)
    wp_localize_script('single-car-buttons-js', 'carListingsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('toggle_favorite_car')
    ));

    // Get current user info for favorites
    $current_user_id = get_current_user_id();
    $user_favorites = array();
    if ($current_user_id) {
        $user_favorites = get_user_meta($current_user_id, 'favorite_cars', true);
        if (!is_array($user_favorites)) {
            $user_favorites = array();
        }
    }

    $is_favorite = in_array($car_id, $user_favorites);
    $heart_class = $is_favorite ? 'fas active' : 'far';

    // Start output buffering
    ob_start();
    ?>
    
    <div class="action-buttons">
        <!-- Favorite Button -->
        <?php if ($current_user_id): ?>
            <button class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>" 
                    data-car-id="<?php echo esc_attr($car_id); ?>" 
                    title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
                <i class="<?php echo esc_attr($heart_class); ?> fa-heart"></i>
            </button>
        <?php else: ?>
            <button class="favorite-btn" 
                    data-car-id="<?php echo esc_attr($car_id); ?>" 
                    title="Login to add favorites">
                <i class="far fa-heart"></i>
            </button>
        <?php endif; ?>

        <!-- Share Button -->
        <button class="share-btn" 
                data-url="<?php echo esc_url(get_permalink($car_id)); ?>" 
                data-title="<?php echo esc_attr(get_the_title($car_id)); ?>" 
                title="Share this listing">
            <i class="fas fa-share-alt"></i>
        </button>

        <!-- Report Button -->
        <button class="report-btn" 
                data-car-id="<?php echo esc_attr($car_id); ?>" 
                title="Report this listing">
            <i class="fas fa-flag"></i>
        </button>
    </div>

    <!-- Report Modal -->
    <div id="report-modal" class="report-modal" style="display: none;">
        <div class="report-modal-content">
            <div class="report-modal-header">
                <h3>Report Listing</h3>
                <button type="button" class="close-report-modal">&times;</button>
            </div>
            <form id="report-listing-form">
                <?php wp_nonce_field('report_listing_nonce', 'report_nonce'); ?>
                <input type="hidden" name="action" value="submit_listing_report">
                <input type="hidden" name="reported_listing_id" value="<?php echo esc_attr($car_id); ?>">
                
                <div class="report-form-group">
                    <label for="report-reason">Reason for reporting *</label>
                    <select name="report_reason" id="report-reason" required>
                        <option value="">Select a reason</option>
                        <option value="inappropriate_content">Inappropriate Content</option>
                        <option value="spam">Spam</option>
                        <option value="misleading_info">Misleading Information</option>
                        <option value="duplicate_listing">Duplicate Listing</option>
                        <option value="sold_unavailable">Already Sold/Unavailable</option>
                        <option value="wrong_category">Wrong Category</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="report-form-group">
                    <label for="report-details">Additional Details</label>
                    <textarea name="report_details" id="report-details" rows="4" 
                              placeholder="Please provide more details about why you're reporting this listing..."></textarea>
                    <small>Help us understand the issue better (optional)</small>
                </div>

                <div class="report-form-group">
                    <label for="report-email">Your Email</label>
                    <input type="email" name="reporter_email" id="report-email" 
                           value="<?php echo $current_user_id ? esc_attr(wp_get_current_user()->user_email) : ''; ?>"
                           placeholder="your@email.com">
                    <small>We may contact you for follow-up (optional)</small>
                </div>

                <div class="report-form-actions">
                    <button type="button" class="cancel-report-btn">Cancel</button>
                    <button type="submit" class="submit-report-btn">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('single_car_buttons', 'single_car_buttons_shortcode'); 