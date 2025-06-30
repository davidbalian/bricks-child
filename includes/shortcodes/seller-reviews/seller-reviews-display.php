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
    // Enqueue CSS and JS only when shortcode is actually used
    if (!wp_style_is('seller-reviews-display', 'enqueued')) {
        $theme_dir = get_stylesheet_directory_uri();
        wp_enqueue_style('seller-reviews-display', 
            $theme_dir . '/includes/shortcodes/seller-reviews/seller-reviews-display.css',
            array(),
            filemtime(get_stylesheet_directory() . '/includes/shortcodes/seller-reviews/seller-reviews-display.css')
        );
        
        // Also enqueue overlay CSS and JS since the button will open overlay
        wp_enqueue_style('seller-reviews-overlay', 
            $theme_dir . '/includes/shortcodes/seller-reviews/seller-reviews-overlay.css',
            array(),
            filemtime(get_stylesheet_directory() . '/includes/shortcodes/seller-reviews/seller-reviews-overlay.css')
        );
        
        wp_enqueue_script('seller-reviews-overlay', 
            $theme_dir . '/includes/shortcodes/seller-reviews/seller-reviews-overlay.js',
            array('jquery'),
            filemtime(get_stylesheet_directory() . '/includes/shortcodes/seller-reviews/seller-reviews-overlay.js'),
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('seller-reviews-overlay', 'sellerReviewsData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('submit_seller_review_nonce'),
        ));
    }
    
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'seller_id' => '',
        'show_reviews' => 'true', // Show individual reviews or just stars
        'limit' => '5', // Number of reviews to show
        'show_form' => 'true', // Show review submission form
    ), $atts, 'seller_reviews');
    
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
    
    <!-- Seller Reviews Overlay (hidden by default) -->
    <div class="seller-reviews-overlay" style="display: none;">
        <div class="seller-reviews-overlay-content" data-seller-id="<?php echo esc_attr($seller_id); ?>">
            
            <!-- Overlay Header -->
            <div class="overlay-header">
                <h3>Reviews for <?php echo esc_html(get_userdata($seller_id)->display_name); ?></h3>
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
                <?php 
                $all_reviews = $reviews_db->get_seller_reviews($seller_id, 50); // Get more reviews for overlay
                if (!empty($all_reviews)): ?>
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
                        <h4>Leave a Review</h4>
                        <form class="seller-review-form" data-seller-id="<?php echo esc_attr($seller_id); ?>">
                            <?php wp_nonce_field('submit_seller_review', 'seller_review_nonce'); ?>
                            
                            <div class="form-group">
                                <label>Rating *</label>
                                <div class="star-rating-input">
                                    <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                                    <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                                    <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                                    <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                                    <input type="radio" name="rating" value="5" id="star5"><label for="star5">★</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="review-comment">Your Review (optional)</label>
                                <textarea id="review-comment" name="comment" placeholder="Share your experience with this seller..." maxlength="140"></textarea>
                                <small>140 characters remaining</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="contacted_seller" value="1">
                                    I contacted this seller
                                </label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn-submit-review">Submit Review</button>
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