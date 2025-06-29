<?php
/**
 * Seller Reviews Display Shortcode
 * 
 * Displays seller rating stars and review summary
 * Usage: [seller_reviews seller_id="123"]
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Seller Reviews Display Shortcode Handler
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function seller_reviews_display_shortcode($atts) {
    // Enqueue CSS only when shortcode is actually used
    if (!wp_style_is('seller-reviews-display', 'enqueued')) {
        $theme_dir = get_stylesheet_directory_uri();
        wp_enqueue_style('seller-reviews-display', 
            $theme_dir . '/includes/shortcodes/seller-reviews/seller-reviews-display.css',
            array(),
            filemtime(get_stylesheet_directory() . '/includes/shortcodes/seller-reviews/seller-reviews-display.css')
        );
    }
    
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'seller_id' => '',
        'show_reviews' => 'true', // Show individual reviews or just stars
        'limit' => '5', // Number of reviews to show
        'show_form' => 'true', // Show review submission form
    ), $atts, 'seller_reviews');
    
    // Get seller ID from attributes or current post author
    $seller_id = !empty($atts['seller_id']) ? intval($atts['seller_id']) : get_the_author_meta('ID');
    
    if (empty($seller_id)) {
        return '<p>No seller specified.</p>';
    }
    
    // Get seller reviews data
    $reviews_db = new SellerReviewsDatabase();
    $reviews_summary = $reviews_db->get_seller_rating_summary($seller_id);
    $reviews_list = ($atts['show_reviews'] === 'true') ? $reviews_db->get_seller_reviews($seller_id, intval($atts['limit'])) : array();
    
    // Start output buffering
    ob_start();
    ?>
    <div class="seller-reviews-container" data-seller-id="<?php echo esc_attr($seller_id); ?>">
        
        <!-- Rating Summary Section -->
        <div class="seller-rating-summary">
            <div class="rating-stars">
                <?php echo generate_star_rating($reviews_summary['average']); ?>
                <span class="rating-text">
                    <?php if ($reviews_summary['count'] > 0): ?>
                        <?php echo number_format($reviews_summary['average'], 1); ?> 
                        (<?php echo $reviews_summary['count']; ?> 
                        <?php echo _n('review', 'reviews', $reviews_summary['count'], 'bricks-child'); ?>)
                    <?php else: ?>
                        No reviews yet
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <?php if ($atts['show_reviews'] === 'true' && !empty($reviews_list)): ?>
        <!-- Individual Reviews Section -->
        <div class="seller-reviews-list">
            <h4>Recent Reviews</h4>
            <?php foreach ($reviews_list as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="review-stars">
                            <?php echo generate_star_rating($review->rating); ?>
                        </div>
                        <div class="review-meta">
                            <span class="reviewer-name">
                                <?php echo esc_html($review->reviewer_name); ?>
                            </span>
                            <span class="review-date">
                                <?php echo date('M j, Y', strtotime($review->review_date)); ?>
                            </span>
                            <?php if ($review->contacted_seller): ?>
                                <span class="contacted-badge">Contacted Seller</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($review->comment)): ?>
                        <div class="review-comment">
                            <?php echo esc_html($review->comment); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['show_form'] === 'true'): ?>
        <!-- Review Submission Form Section -->
        <div class="seller-review-form-container">
            <?php if (is_user_logged_in()): ?>
                <?php if (get_current_user_id() != $seller_id): ?>
                    <div class="review-form-toggle">
                        <button type="button" class="btn-toggle-review-form">
                            See all reviews
                        </button>
                    </div>
                    <div class="seller-review-form" style="display: none;">
                        <!-- Review form will be loaded here via JavaScript -->
                    </div>
                <?php else: ?>
                    <p class="review-notice">You cannot review yourself.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="review-notice">
                    <a href="<?php echo wp_login_url(get_permalink()); ?>">Login</a> to leave a review.
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>
    
    <?php
    return ob_get_clean();
}

/**
 * Generate star rating HTML
 * 
 * @param float $rating Rating value (0-5)
 * @return string HTML for star rating
 */
function generate_star_rating($rating) {
    $rating = floatval($rating);
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5;
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
    
    $html = '<div class="stars-container">';
    
    // Full stars
    for ($i = 0; $i < $full_stars; $i++) {
        $html .= '<span class="star star-full">★</span>';
    }
    
    // Half star
    if ($half_star) {
        $html .= '<span class="star star-half">★</span>';
    }
    
    // Empty stars
    for ($i = 0; $i < $empty_stars; $i++) {
        $html .= '<span class="star star-empty">☆</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// Register the shortcode
add_shortcode('seller_reviews', 'seller_reviews_display_shortcode'); 