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
    // Parse shortcode attributes first
    $atts = shortcode_atts(array(
        'seller_id' => '',
        'show_reviews' => 'true', // Show individual reviews or just stars
        'limit' => '5', // Number of reviews to show
        'show_form' => 'true', // Show review submission form
    ), $atts, 'seller_reviews');
    
    // Enqueue CSS and JS for seller reviews
    if (!wp_style_is('seller-reviews-display', 'enqueued')) {
        $theme_dir = get_stylesheet_directory_uri();
        
        wp_enqueue_style('seller-reviews-display', 
            $theme_dir . '/includes/shortcodes/seller-reviews/seller-reviews-display.css',
            array(),
            filemtime(get_stylesheet_directory() . '/includes/shortcodes/seller-reviews/seller-reviews-display.css')
        );
        
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
        $strict_reviews = function_exists('seller_reviews_is_strict_mode') && seller_reviews_is_strict_mode();
        wp_localize_script('seller-reviews-overlay', 'sellerReviewsData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('submit_seller_review_nonce'),
            'strictMode' => $strict_reviews,
        ));
    }
    
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
    <?php
    $has_reviews = (int) $reviews_summary['count'] > 0;
    $container_mod = $has_reviews ? 'seller-reviews-container--has-reviews' : 'seller-reviews-container--empty';
    ?>
    <div class="seller-reviews-container <?php echo esc_attr($container_mod); ?>" data-seller-id="<?php echo esc_attr($seller_id); ?>">
        
        <?php if ($has_reviews): ?>
        <!-- Rating Summary Section -->
        <div class="seller-rating-summary">
            <div class="rating-stars">
                <?php echo generate_star_rating($reviews_summary['average']); ?>
                <span class="rating-text">
                    <?php echo number_format($reviews_summary['average'], 1); ?>
                    (<?php echo $reviews_summary['count']; ?>
                    <?php echo _n('review', 'reviews', $reviews_summary['count'], 'bricks-child'); ?>)
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
        <div class="seller-review-form-container">
            <div class="review-form-toggle">
                <button type="button" class="btn btn-primary btn-toggle-review-form">
                    See all reviews
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- No reviews: inline message + link-style control (opens same overlay) -->
        <div class="seller-reviews-empty-inline">
            <span class="seller-reviews-empty-message"><?php esc_html_e('No reviews yet', 'bricks-child'); ?></span>
            <?php if ($atts['show_form'] === 'true'): ?>
            <button type="button" class="seller-reviews-see-all-link btn-toggle-review-form">
                <?php esc_html_e('See all reviews', 'bricks-child'); ?>
            </button>
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
                <?php
                $strict_reviews = function_exists('seller_reviews_is_strict_mode') && seller_reviews_is_strict_mode();
                $rid_suffix = (int) $seller_id;
                ?>
                <?php if (get_current_user_id() == $seller_id): ?>
                    <div class="review-notice">
                        <p>You cannot review yourself.</p>
                    </div>
                <?php elseif ($strict_reviews && ! is_user_logged_in()): ?>
                    <div class="login-prompt">
                        <p>
                            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="login-link">Log in</a>
                            to leave a review for this seller.
                        </p>
                    </div>
                <?php elseif ($strict_reviews): ?>
                        <?php
                        /* Legacy: login + verified account only. Enable with SELLER_REVIEWS_STRICT_MODE or seller_reviews_strict_mode filter. */
                        $current_user_id = get_current_user_id();
                        $current_user = wp_get_current_user();
                        $email_verified = get_user_meta($current_user_id, 'email_verified', true);
                        $can_review = ($email_verified === '1');
                        ?>
                        <h4><?php esc_html_e('Leave a review', 'bricks-child'); ?></h4>
                        <form class="seller-review-form" data-seller-id="<?php echo esc_attr($seller_id); ?>" data-review-mode="strict">
                            <?php wp_nonce_field('submit_seller_review_nonce', 'seller_review_nonce'); ?>
                            <div class="form-group">
                                <label><?php esc_html_e('Rating', 'bricks-child'); ?> *</label>
                                <div class="star-rating-input <?php echo ! $can_review ? 'disabled' : ''; ?>">
                                    <input type="radio" name="rating" value="1" id="star1-<?php echo esc_attr($rid_suffix); ?>" <?php echo ! $can_review ? 'disabled' : ''; ?>><label for="star1-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                    <input type="radio" name="rating" value="2" id="star2-<?php echo esc_attr($rid_suffix); ?>" <?php echo ! $can_review ? 'disabled' : ''; ?>><label for="star2-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                    <input type="radio" name="rating" value="3" id="star3-<?php echo esc_attr($rid_suffix); ?>" <?php echo ! $can_review ? 'disabled' : ''; ?>><label for="star3-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                    <input type="radio" name="rating" value="4" id="star4-<?php echo esc_attr($rid_suffix); ?>" <?php echo ! $can_review ? 'disabled' : ''; ?>><label for="star4-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                    <input type="radio" name="rating" value="5" id="star5-<?php echo esc_attr($rid_suffix); ?>" <?php echo ! $can_review ? 'disabled' : ''; ?>><label for="star5-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="review-comment-<?php echo esc_attr($rid_suffix); ?>"><?php esc_html_e('Your review (optional)', 'bricks-child'); ?></label>
                                <textarea id="review-comment-<?php echo esc_attr($rid_suffix); ?>" name="comment" placeholder="<?php echo esc_attr__('Share your experience…', 'bricks-child'); ?>" maxlength="140" <?php echo ! $can_review ? 'disabled' : ''; ?>></textarea>
                                <small><?php esc_html_e('140 characters maximum', 'bricks-child'); ?></small>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="contacted_seller" value="1" <?php echo ! $can_review ? 'disabled' : ''; ?>>
                                    <?php esc_html_e('I contacted this seller', 'bricks-child'); ?>
                                </label>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-submit-review" <?php echo ! $can_review ? 'disabled' : ''; ?>><?php esc_html_e('Submit', 'bricks-child'); ?></button>
                                <?php if (! $can_review && function_exists('get_email_verification_banner_html')): ?>
                                    <?php
                                    echo get_email_verification_banner_html($current_user->user_email, array(
                                        'id' => '',
                                        'message_html' => '<a href="' . esc_url(home_url('/my-account')) . '" class="verify-email-link">' . esc_html__('Verify your email', 'bricks-child') . '</a> ' . esc_html__('to leave a review.', 'bricks-child'),
                                        'show_send_button' => false,
                                        'show_dismiss_button' => false,
                                        'wrapper_style' => 'margin-top: 10px; width: 100%;',
                                        'container_style' => 'justify-content: flex-start;',
                                    ));
                                    ?>
                                <?php endif; ?>
                                <div class="form-messages"></div>
                            </div>
                        </form>
                <?php else: ?>
                    <h4><?php esc_html_e('Leave a quick review', 'bricks-child'); ?></h4>
                    <form class="seller-review-form" data-seller-id="<?php echo esc_attr($seller_id); ?>" data-review-mode="open">
                        <?php wp_nonce_field('submit_seller_review_nonce', 'seller_review_nonce'); ?>
                        <div class="form-group">
                            <label for="reviewer-email-<?php echo esc_attr($rid_suffix); ?>"><?php esc_html_e('Your email', 'bricks-child'); ?> *</label>
                            <input
                                type="email"
                                id="reviewer-email-<?php echo esc_attr($rid_suffix); ?>"
                                name="reviewer_email"
                                required
                                autocomplete="email"
                                placeholder="<?php echo esc_attr__('you@example.com', 'bricks-child'); ?>"
                                value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>"
                            >
                            <small><?php esc_html_e('For moderation only; it is not shown on your public review.', 'bricks-child'); ?></small>
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Rating', 'bricks-child'); ?> *</label>
                            <div class="star-rating-input">
                                <input type="radio" name="rating" value="1" id="open-star1-<?php echo esc_attr($rid_suffix); ?>"><label for="open-star1-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                <input type="radio" name="rating" value="2" id="open-star2-<?php echo esc_attr($rid_suffix); ?>"><label for="open-star2-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                <input type="radio" name="rating" value="3" id="open-star3-<?php echo esc_attr($rid_suffix); ?>"><label for="open-star3-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                <input type="radio" name="rating" value="4" id="open-star4-<?php echo esc_attr($rid_suffix); ?>"><label for="open-star4-<?php echo esc_attr($rid_suffix); ?>">★</label>
                                <input type="radio" name="rating" value="5" id="open-star5-<?php echo esc_attr($rid_suffix); ?>"><label for="open-star5-<?php echo esc_attr($rid_suffix); ?>">★</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="review-comment-open-<?php echo esc_attr($rid_suffix); ?>"><?php esc_html_e('Comment (optional)', 'bricks-child'); ?></label>
                            <textarea id="review-comment-open-<?php echo esc_attr($rid_suffix); ?>" name="comment" placeholder="<?php echo esc_attr__('Share your experience…', 'bricks-child'); ?>" maxlength="140"></textarea>
                            <small><?php esc_html_e('140 characters maximum', 'bricks-child'); ?></small>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="contacted_seller" value="1">
                                <?php esc_html_e('I contacted this seller', 'bricks-child'); ?>
                            </label>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-submit-review"><?php esc_html_e('Submit', 'bricks-child'); ?></button>
                            <div class="form-messages"></div>
                        </div>
                    </form>
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