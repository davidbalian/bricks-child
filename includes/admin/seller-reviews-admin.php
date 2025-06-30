<?php
/**
 * Seller Reviews Admin Dashboard
 * 
 * Provides admin interface for managing seller reviews including:
 * - View all reviews (pending, approved, rejected)
 * - Approve/reject reviews
 * - Statistics and analytics
 * - Bulk actions
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Seller Reviews admin menu
 */
function add_seller_reviews_admin_menu() {
    add_menu_page(
        'Seller Reviews',
        'Seller Reviews',
        'manage_options',
        'seller-reviews',
        'seller_reviews_admin_page',
        'dashicons-star-filled',
        25
    );
}
add_action('admin_menu', 'add_seller_reviews_admin_menu');

/**
 * Main admin page callback
 */
function seller_reviews_admin_page() {
    // Get current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pending';
    
    ?>
    <div class="wrap">
        <h1>Seller Reviews Management</h1>
        
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo admin_url('admin.php?page=seller-reviews&tab=pending'); ?>" 
               class="nav-tab <?php echo $current_tab === 'pending' ? 'nav-tab-active' : ''; ?>">
                Pending Reviews
            </a>
            <a href="<?php echo admin_url('admin.php?page=seller-reviews&tab=approved'); ?>" 
               class="nav-tab <?php echo $current_tab === 'approved' ? 'nav-tab-active' : ''; ?>">
                Approved Reviews
            </a>
            <a href="<?php echo admin_url('admin.php?page=seller-reviews&tab=rejected'); ?>" 
               class="nav-tab <?php echo $current_tab === 'rejected' ? 'nav-tab-active' : ''; ?>">
                Rejected Reviews
            </a>
        </h2>
        
        <!-- Reviews Table -->
        <div class="tab-content">
            <?php display_reviews_table($current_tab); ?>
        </div>
    </div>
    <?php
}

/**
 * Display reviews table
 */
function display_reviews_table($status) {
    global $seller_reviews_database;
    if (!$seller_reviews_database) {
        $seller_reviews_database = new SellerReviewsDatabase();
    }
    
    // Get reviews
    $reviews = $seller_reviews_database->get_reviews_for_admin($status);
    
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Reviewer</th>
                <th>Seller</th>
                <th>Rating</th>
                <th>Comment</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reviews)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">
                        No reviews found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <tr>
                        <td><?php echo esc_html($review->reviewer_name ?: 'Unknown'); ?></td>
                        <td><?php echo esc_html($review->seller_name ?: 'Unknown'); ?></td>
                        <td><?php echo str_repeat('★', $review->rating); ?></td>
                        <td><?php echo esc_html($review->comment ?: 'No comment'); ?></td>
                        <td><?php echo date('M j, Y', strtotime($review->review_date)); ?></td>
                        <td>
                            <?php if ($review->status === 'pending'): ?>
                                <button class="button button-primary" onclick="approveReview(<?php echo $review->id; ?>)">Approve</button>
                                <button class="button" onclick="rejectReview(<?php echo $review->id; ?>)">Reject</button>
                            <?php elseif ($review->status === 'approved'): ?>
                                <button class="button" onclick="rejectReview(<?php echo $review->id; ?>)">Reject</button>
                                <button class="button" onclick="resetToPending(<?php echo $review->id; ?>)">Reset to Pending</button>
                                <button class="button button-link-delete" onclick="deleteReview(<?php echo $review->id; ?>)">Delete</button>
                            <?php elseif ($review->status === 'rejected'): ?>
                                <button class="button button-primary" onclick="approveReview(<?php echo $review->id; ?>)">Approve</button>
                                <button class="button" onclick="resetToPending(<?php echo $review->id; ?>)">Reset to Pending</button>
                                <button class="button button-link-delete" onclick="deleteReview(<?php echo $review->id; ?>)">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <script>
    function approveReview(reviewId) {
        if (confirm('Approve this review?')) {
            jQuery.post(ajaxurl, {
                action: 'approve_seller_review',
                review_id: reviewId,
                nonce: '<?php echo wp_create_nonce('admin_review_action_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
    }
    
    function rejectReview(reviewId) {
        if (confirm('Reject this review?')) {
            jQuery.post(ajaxurl, {
                action: 'reject_seller_review',
                review_id: reviewId,
                nonce: '<?php echo wp_create_nonce('admin_review_action_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
    }
    
    function resetToPending(reviewId) {
        if (confirm('Reset this review to pending status?')) {
            jQuery.post(ajaxurl, {
                action: 'reset_seller_review_to_pending',
                review_id: reviewId,
                nonce: '<?php echo wp_create_nonce('admin_review_action_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
    }
    
    function deleteReview(reviewId) {
        if (confirm('Are you sure you want to permanently delete this review? This action cannot be undone.')) {
            jQuery.post(ajaxurl, {
                action: 'delete_seller_review',
                review_id: reviewId,
                nonce: '<?php echo wp_create_nonce('admin_review_action_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
    }
    </script>
    <?php
}

/**
 * Display review statistics cards
 */
function display_review_statistics() {
    global $seller_reviews_database;
    
    // Get statistics
    $stats = $seller_reviews_database->get_review_statistics();
    
    ?>
    <div class="review-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Reviews</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['average_rating'], 1); ?></div>
            <div class="stat-label">Average Rating</div>
        </div>
    </div>
    <?php
}

/**
 * Get count badge for tab
 */
function get_reviews_count_badge($status) {
    global $seller_reviews_database;
    $count = $seller_reviews_database->get_reviews_count($status);
    return $count > 0 ? '<span class="awaiting-mod">' . $count . '</span>' : '';
}

/**
 * Handle admin form submissions
 */
function handle_admin_form_submission() {
    global $seller_reviews_database;
    
    $action = sanitize_text_field($_POST['action']);
    
    if ($action === 'bulk_action') {
        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        $review_ids = array_map('intval', $_POST['review_ids']);
        
        $success_count = 0;
        foreach ($review_ids as $review_id) {
            if ($bulk_action === 'approve') {
                if ($seller_reviews_database->approve_review($review_id)) {
                    $success_count++;
                }
            } elseif ($bulk_action === 'reject') {
                if ($seller_reviews_database->reject_review($review_id)) {
                    $success_count++;
                }
            } elseif ($bulk_action === 'delete') {
                if ($seller_reviews_database->delete_review($review_id)) {
                    $success_count++;
                }
            }
        }
        
        add_settings_error(
            'seller_reviews_admin',
            'bulk_action_success',
            sprintf('%d reviews were successfully %s.', $success_count, $bulk_action === 'approve' ? 'approved' : ($bulk_action === 'reject' ? 'rejected' : 'deleted')),
            'updated'
        );
    }
}

/**
 * Enqueue admin scripts and styles
 */
function enqueue_seller_reviews_admin_assets($hook) {
    if ($hook !== 'toplevel_page_seller-reviews') {
        return;
    }
    
    // Enqueue WordPress admin scripts
    wp_enqueue_script('jquery');
    
    // Add custom admin styles
    wp_add_inline_style('wp-admin', '
        .seller-reviews-admin .review-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .seller-reviews-admin .stat-card {
            background: white;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
            min-width: 150px;
            text-align: center;
        }
    ');
}
add_action('admin_enqueue_scripts', 'enqueue_seller_reviews_admin_assets'); 