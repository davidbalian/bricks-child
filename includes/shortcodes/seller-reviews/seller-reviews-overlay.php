<?php
/**
 * Seller Reviews Overlay Shortcode
 * 
 * Displays full reviews list and review submission form in overlay
 * Usage: [seller_reviews_overlay seller_id="123"]
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Seller Reviews Overlay Shortcode Handler
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function seller_reviews_overlay_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'seller_id' => '',
    ), $atts, 'seller_reviews_overlay');
    
    // Get seller ID from attributes or current post author
    if (!empty($atts['seller_id'])) {
        $seller_id = intval($atts['seller_id']);
    } else {
        // Try to get the post author (seller) from the current post
        global $post;
        if ($post && $post->post_author) {
            $seller_id = intval($post->post_author);
        } else {
            // Fallback to get_the_author_meta if global $post is not available
            $seller_id = get_the_author_meta('ID');
        }
    }
    
    if (empty($seller_id)) {
        return '<p>No seller specified.</p>';
    }
    
    // Get seller info
    $seller_info = get_userdata($seller_id);
    if (!$seller_info) {
        return '<p>Seller not found.</p>';
    }
    
    // Get seller reviews data
    $reviews_db = new SellerReviewsDatabase();
    $reviews_summary = $reviews_db->get_seller_rating_summary($seller_id);
    $all_reviews = $reviews_db->get_seller_reviews($seller_id, 50); // Get more reviews for overlay
    
    // Start output buffering
    ob_start();
    ?>
    <div class="seller-reviews-overlay-content" data-seller-id="<?php echo esc_attr($seller_id); ?>">
        
        <!-- Overlay Header -->
        <div class="overlay-header">
            <h3>Reviews for <?php echo esc_html($seller_info->display_name); ?></h3>
            <button type="button" class="close-overlay">&times;</button>
        </div>
        
        <!-- Rating Summary -->
        <div class="overlay-rating-summary">
            <div class="rating-display">
                <?php echo generate_star_rating($reviews_summary['average']); ?>
                <span class="rating-text">
                    <?php if ($reviews_summary['count'] > 0): ?>
                        <?php echo number_format($reviews_summary['average'], 1); ?> out of 5
                        (<?php echo $reviews_summary['count']; ?> 
                        <?php echo _n('review', 'reviews', $reviews_summary['count'], 'bricks-child'); ?>)
                    <?php else: ?>
                        No reviews yet - be the first to review!
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <!-- Reviews List -->
        <div class="overlay-reviews-section">
            <?php if (!empty($all_reviews)): ?>
                <h4>All Reviews</h4>
                <div class="reviews-list">
                    <?php foreach ($all_reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-stars">
                                    <?php echo generate_star_rating($review->rating); ?>
                                </div>
                                <div class="review-meta">
                                    <span class="reviewer-name">
                                        <?php echo esc_html($review->reviewer_name ?: 'Anonymous'); ?>
                                    </span>
                                    <span class="review-date">
                                        <?php echo date('F j, Y', strtotime($review->review_date)); ?>
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
            <?php else: ?>
                <div class="no-reviews">
                    <p>No reviews yet for this seller.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Review Form Section -->
        <div class="overlay-review-form-section">
            <?php if (is_user_logged_in()): ?>
                <?php if (get_current_user_id() != $seller_id): ?>
                    <?php 
                    $current_user_id = get_current_user_id();
                    $email_verified = get_user_meta($current_user_id, 'email_verified', true);
                    $can_review = ($email_verified === '1');
                    ?>
                    
                    <h4>Leave a Review</h4>
                    <form class="seller-review-form" data-seller-id="<?php echo esc_attr($seller_id); ?>">
                        <?php wp_nonce_field('submit_seller_review_nonce', 'seller_review_nonce'); ?>
                        
                        <div class="form-group">
                            <label>Rating *</label>
                            <div class="star-rating-input <?php echo !$can_review ? 'disabled' : ''; ?>">
                                <input type="radio" name="rating" value="1" id="star1" <?php echo !$can_review ? 'disabled' : ''; ?>><label for="star1">★</label>
                                <input type="radio" name="rating" value="2" id="star2" <?php echo !$can_review ? 'disabled' : ''; ?>><label for="star2">★</label>
                                <input type="radio" name="rating" value="3" id="star3" <?php echo !$can_review ? 'disabled' : ''; ?>><label for="star3">★</label>
                                <input type="radio" name="rating" value="4" id="star4" <?php echo !$can_review ? 'disabled' : ''; ?>><label for="star4">★</label>
                                <input type="radio" name="rating" value="5" id="star5" <?php echo !$can_review ? 'disabled' : ''; ?>><label for="star5">★</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="review-comment">Your Review (optional)</label>
                            <textarea id="review-comment" name="comment" placeholder="Share your experience with this seller..." maxlength="140" <?php echo !$can_review ? 'disabled' : ''; ?>></textarea>
                            <small>140 characters maximum</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="contacted_seller" value="1" <?php echo !$can_review ? 'disabled' : ''; ?>>
                                I contacted this seller
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-submit-review" <?php echo !$can_review ? 'disabled' : ''; ?>>Submit Review</button>
                            
                            <?php if (!$can_review): ?>
                                <p class="email-verification-notice">
                                    <a href="<?php echo home_url('/my-account'); ?>" class="verify-email-link">Verify your email</a> to leave a review.
                                </p>
                            <?php endif; ?>
                            
                            <div class="form-messages"></div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="review-notice">
                        <p>You cannot review yourself.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="login-prompt">
                    <p>
                        <a href="<?php echo wp_login_url(get_permalink()); ?>" class="login-link">Login</a> 
                        to leave a review for this seller.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <?php
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('seller_reviews_overlay', 'seller_reviews_overlay_shortcode'); 