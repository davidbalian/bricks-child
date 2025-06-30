<?php
/**
 * Seller Reviews Database Operations
 * 
 * Handles database table creation, cleanup, and core database operations
 * for the seller reviews system. Users can review sellers (other users)
 * with ratings and comments.
 * 
 * @package Bricks Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SellerReviewsDatabase {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'seller_reviews';
        
        // Hook into user deletion for cleanup
        add_action('delete_user', array($this, 'cleanup_reviews_on_user_delete'));
        
        // NOTE: Table creation handled manually like views counter
        // No ongoing checks needed - table assumed to exist after manual setup
    }
    
    /**
     * Submit a new review for a seller
     * 
     * @param int $seller_id The user ID being reviewed
     * @param int $reviewer_id The user ID leaving the review
     * @param int $rating Rating from 1-5
     * @param string $comment Review comment (optional)
     * @param bool $contacted_seller Whether reviewer contacted the seller
     * @return array Results with success boolean and message
     */
    public function submit_review($seller_id, $reviewer_id, $rating, $comment = '', $contacted_seller = false) {
        global $wpdb;
        
        // Validate inputs
        if (!$seller_id || !$reviewer_id || !$rating) {
            return array('success' => false, 'message' => 'Missing required fields');
        }
        
        if ($seller_id == $reviewer_id) {
            return array('success' => false, 'message' => 'Cannot review yourself');
        }
        
        if ($rating < 1 || $rating > 5) {
            return array('success' => false, 'message' => 'Rating must be between 1 and 5');
        }
        
        // Check for existing review
        $existing_review = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM {$this->table_name} WHERE seller_id = %d AND reviewer_id = %d",
            $seller_id, $reviewer_id
        ));
        
        if ($existing_review) {
            // If existing review is approved or pending, don't allow new submission
            if ($existing_review->status === 'approved' || $existing_review->status === 'pending') {
                $status_text = $existing_review->status === 'approved' ? 'approved' : 'pending approval';
                return array('success' => false, 'message' => "You have already reviewed this seller. Your review is {$status_text}.");
            }
            
            // If existing review was rejected, update it with new data
            if ($existing_review->status === 'rejected') {
                $result = $wpdb->update(
                    $this->table_name,
                    array(
                        'rating' => $rating,
                        'comment' => sanitize_textarea_field($comment),
                        'contacted_seller' => $contacted_seller ? 1 : 0,
                        'status' => 'pending', // Reset to pending for re-approval
                        'review_date' => current_time('mysql'), // Update submission time
                        'admin_notes' => null // Clear any previous admin notes
                    ),
                    array('id' => $existing_review->id),
                    array('%d', '%s', '%d', '%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result === false) {
                    return array('success' => false, 'message' => 'Database error: ' . $wpdb->last_error);
                }
                
                return array('success' => true, 'message' => 'Review resubmitted successfully. It will be visible after admin approval.');
            }
        }
        
        // Insert new review (no existing review found)
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'seller_id' => $seller_id,
                'reviewer_id' => $reviewer_id,
                'rating' => $rating,
                'comment' => sanitize_textarea_field($comment),
                'contacted_seller' => $contacted_seller ? 1 : 0,
                'status' => 'pending', // All reviews start as pending
                'review_date' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            return array('success' => false, 'message' => 'Database error: ' . $wpdb->last_error);
        }
        
        return array('success' => true, 'message' => 'Review submitted successfully. It will be visible after admin approval.');
    }
    
    /**
     * Get approved reviews for a seller
     * 
     * @param int $seller_id The seller's user ID
     * @param int $limit Number of reviews to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of review objects
     */
    public function get_seller_reviews($seller_id, $limit = 10, $offset = 0) {
        global $wpdb;
        
        $reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, 
                    u.display_name, 
                    u.user_login as reviewer_username,
                    COALESCE(
                        NULLIF(u.display_name, ''),
                        CONCAT_WS(' ', 
                            NULLIF(um_first.meta_value, ''), 
                            NULLIF(um_last.meta_value, '')
                        ),
                        u.user_login
                    ) as reviewer_name
             FROM {$this->table_name} r 
             LEFT JOIN {$wpdb->users} u ON r.reviewer_id = u.ID 
             LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
             LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
             WHERE r.seller_id = %d AND r.status = 'approved' 
             ORDER BY r.review_date DESC 
             LIMIT %d OFFSET %d",
            $seller_id, $limit, $offset
        ));
        
        return $reviews ?: array();
    }
    
    /**
     * Get seller's average rating and total review count
     * 
     * @param int $seller_id The seller's user ID
     * @return array Array with 'average' and 'count'
     */
    public function get_seller_rating_summary($seller_id) {
        // First try to get from cache (user meta)
        $cached_average = get_user_meta($seller_id, 'seller_average_rating', true);
        $cached_count = get_user_meta($seller_id, 'seller_review_count', true);
        
        if ($cached_average !== '' && $cached_count !== '') {
            return array(
                'average' => (float) $cached_average,
                'count' => (int) $cached_count
            );
        }
        
        // If not cached, calculate and cache it
        return $this->update_seller_rating_cache($seller_id);
    }
    
    /**
     * Update the cached rating summary for a seller
     * 
     * @param int $seller_id The seller's user ID
     * @return array The updated rating summary
     */
    private function update_seller_rating_cache($seller_id) {
        global $wpdb;
        
        $results = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) as average, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE seller_id = %d AND status = 'approved'",
            $seller_id
        ));
        
        $average = $results->average ? (float) $results->average : 0;
        $count = (int) $results->count;
        
        update_user_meta($seller_id, 'seller_average_rating', $average);
        update_user_meta($seller_id, 'seller_review_count', $count);
        
        return array(
            'average' => $average,
            'count' => $count
        );
    }
    
    /**
     * Approve a review (admin action)
     * 
     * @param int $review_id The review ID
     * @return bool Success status
     */
    public function approve_review($review_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'approved'),
            array('id' => $review_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Update the seller's rating cache
            $review = $wpdb->get_row($wpdb->prepare(
                "SELECT seller_id FROM {$this->table_name} WHERE id = %d",
                $review_id
            ));
            
            if ($review) {
                $this->update_seller_rating_cache($review->seller_id);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Reject a review (admin action)
     * 
     * @param int $review_id The review ID
     * @return bool Success status
     */
    public function reject_review($review_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'rejected'),
            array('id' => $review_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Update the seller's rating cache since this review is no longer approved
            $review = $wpdb->get_row($wpdb->prepare(
                "SELECT seller_id FROM {$this->table_name} WHERE id = %d",
                $review_id
            ));
            
            if ($review) {
                $this->update_seller_rating_cache($review->seller_id);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Reset a review to pending status (admin action)
     * 
     * @param int $review_id The review ID
     * @return bool Success status
     */
    public function reset_review_to_pending($review_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'pending'),
            array('id' => $review_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Update the seller's rating cache since this review is no longer approved
            $review = $wpdb->get_row($wpdb->prepare(
                "SELECT seller_id FROM {$this->table_name} WHERE id = %d",
                $review_id
            ));
            
            if ($review) {
                $this->update_seller_rating_cache($review->seller_id);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get pending reviews for admin moderation
     * 
     * @param int $limit Number of reviews to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of pending review objects
     */
    public function get_pending_reviews($limit = 20, $offset = 0) {
        global $wpdb;
        
        $reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, 
                    COALESCE(
                        NULLIF(u1.display_name, ''),
                        CONCAT_WS(' ', 
                            NULLIF(um1_first.meta_value, ''), 
                            NULLIF(um1_last.meta_value, '')
                        ),
                        u1.user_login
                    ) as seller_name,
                    u2.user_login as reviewer_username,
                    COALESCE(
                        NULLIF(u2.display_name, ''),
                        CONCAT_WS(' ', 
                            NULLIF(um2_first.meta_value, ''), 
                            NULLIF(um2_last.meta_value, '')
                        ),
                        u2.user_login
                    ) as reviewer_name
             FROM {$this->table_name} r 
             LEFT JOIN {$wpdb->users} u1 ON r.seller_id = u1.ID 
             LEFT JOIN {$wpdb->users} u2 ON r.reviewer_id = u2.ID 
             LEFT JOIN {$wpdb->usermeta} um1_first ON u1.ID = um1_first.user_id AND um1_first.meta_key = 'first_name'
             LEFT JOIN {$wpdb->usermeta} um1_last ON u1.ID = um1_last.user_id AND um1_last.meta_key = 'last_name'
             LEFT JOIN {$wpdb->usermeta} um2_first ON u2.ID = um2_first.user_id AND um2_first.meta_key = 'first_name'
             LEFT JOIN {$wpdb->usermeta} um2_last ON u2.ID = um2_last.user_id AND um2_last.meta_key = 'last_name'
             WHERE r.status = 'pending' 
             ORDER BY r.review_date ASC 
             LIMIT %d OFFSET %d",
            $limit, $offset
        ));
        
        return $reviews ?: array();
    }
    
    /**
     * Get total count of pending reviews
     * 
     * @return int Number of pending reviews
     */
    public function get_pending_reviews_count() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'"
        );
        
        return (int) $count;
    }
    
    /**
     * Clean up reviews when a user is deleted
     * 
     * @param int $user_id The user ID being deleted
     */
    public function cleanup_reviews_on_user_delete($user_id) {
        global $wpdb;
        
        // Delete reviews where this user was the seller
        $wpdb->delete(
            $this->table_name,
            array('seller_id' => $user_id),
            array('%d')
        );
        
        // Delete reviews where this user was the reviewer
        $wpdb->delete(
            $this->table_name,
            array('reviewer_id' => $user_id),
            array('%d')
        );
        
        // Clean up cached rating data
        delete_user_meta($user_id, 'seller_average_rating');
        delete_user_meta($user_id, 'seller_review_count');
    }
    
    /**
     * Get review statistics for a seller (for admin/analytics)
     * 
     * @param int $seller_id The seller's user ID
     * @return array Array of review statistics
     */
    public function get_seller_review_stats($seller_id) {
        global $wpdb;
        
        $stats = array();
        
        // Current rating summary
        $summary = $this->get_seller_rating_summary($seller_id);
        $stats['average_rating'] = $summary['average'];
        $stats['total_reviews'] = $summary['count'];
        
        // Pending reviews count
        $stats['pending_reviews'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE seller_id = %d AND status = 'pending'",
            $seller_id
        ));
        
        // Recent activity (last 30 days)
        $stats['recent_reviews'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE seller_id = %d AND status = 'approved' 
             AND review_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $seller_id
        ));
        
        // Rating breakdown
        $rating_breakdown = $wpdb->get_results($wpdb->prepare(
            "SELECT rating, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE seller_id = %d AND status = 'approved' 
             GROUP BY rating 
             ORDER BY rating DESC",
            $seller_id
        ));
        
        $stats['rating_breakdown'] = array();
        foreach ($rating_breakdown as $breakdown) {
            $stats['rating_breakdown'][$breakdown->rating] = (int) $breakdown->count;
        }
        
        return $stats;
    }
    
    /**
     * Get reviews for admin dashboard
     * 
     * @param string $status Filter by status (pending, approved, rejected, or 'all')
     * @param int $limit Number of reviews to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of review objects with additional user info
     */
    public function get_reviews_for_admin($status = 'all', $limit = 20, $offset = 0) {
        global $wpdb;
        
        if ($status === 'all') {
            $reviews = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, 
                        u1.user_login as reviewer_username,
                        COALESCE(
                            NULLIF(u1.display_name, ''),
                            CONCAT_WS(' ', 
                                NULLIF(um1_first.meta_value, ''), 
                                NULLIF(um1_last.meta_value, '')
                            ),
                            u1.user_login
                        ) as reviewer_name,
                        u1.user_email as reviewer_email,
                        COALESCE(
                            NULLIF(u2.display_name, ''),
                            CONCAT_WS(' ', 
                                NULLIF(um2_first.meta_value, ''), 
                                NULLIF(um2_last.meta_value, '')
                            ),
                            u2.user_login
                        ) as seller_name,
                        u2.user_email as seller_email
                 FROM {$this->table_name} r 
                 LEFT JOIN {$wpdb->users} u1 ON r.reviewer_id = u1.ID 
                 LEFT JOIN {$wpdb->users} u2 ON r.seller_id = u2.ID
                 LEFT JOIN {$wpdb->usermeta} um1_first ON u1.ID = um1_first.user_id AND um1_first.meta_key = 'first_name'
                 LEFT JOIN {$wpdb->usermeta} um1_last ON u1.ID = um1_last.user_id AND um1_last.meta_key = 'last_name'
                 LEFT JOIN {$wpdb->usermeta} um2_first ON u2.ID = um2_first.user_id AND um2_first.meta_key = 'first_name'
                 LEFT JOIN {$wpdb->usermeta} um2_last ON u2.ID = um2_last.user_id AND um2_last.meta_key = 'last_name'
                 ORDER BY r.review_date DESC 
                 LIMIT %d OFFSET %d",
                $limit, $offset
            ));
        } else {
            $reviews = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, 
                        u1.user_login as reviewer_username,
                        COALESCE(
                            NULLIF(u1.display_name, ''),
                            CONCAT_WS(' ', 
                                NULLIF(um1_first.meta_value, ''), 
                                NULLIF(um1_last.meta_value, '')
                            ),
                            u1.user_login
                        ) as reviewer_name,
                        u1.user_email as reviewer_email,
                        COALESCE(
                            NULLIF(u2.display_name, ''),
                            CONCAT_WS(' ', 
                                NULLIF(um2_first.meta_value, ''), 
                                NULLIF(um2_last.meta_value, '')
                            ),
                            u2.user_login
                        ) as seller_name,
                        u2.user_email as seller_email
                 FROM {$this->table_name} r 
                 LEFT JOIN {$wpdb->users} u1 ON r.reviewer_id = u1.ID 
                 LEFT JOIN {$wpdb->users} u2 ON r.seller_id = u2.ID
                 LEFT JOIN {$wpdb->usermeta} um1_first ON u1.ID = um1_first.user_id AND um1_first.meta_key = 'first_name'
                 LEFT JOIN {$wpdb->usermeta} um1_last ON u1.ID = um1_last.user_id AND um1_last.meta_key = 'last_name'
                 LEFT JOIN {$wpdb->usermeta} um2_first ON u2.ID = um2_first.user_id AND um2_first.meta_key = 'first_name'
                 LEFT JOIN {$wpdb->usermeta} um2_last ON u2.ID = um2_last.user_id AND um2_last.meta_key = 'last_name'
                 WHERE r.status = %s
                 ORDER BY r.review_date DESC 
                 LIMIT %d OFFSET %d",
                $status, $limit, $offset
            ));
        }
        
        return $reviews ?: array();
    }
    
    /**
     * Get reviews count by status
     * 
     * @param string $status Status to count (pending, approved, rejected, or 'all')
     * @return int Count of reviews
     */
    public function get_reviews_count($status = 'all') {
        global $wpdb;
        
        if ($status === 'all') {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        }
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
            $status
        ));
    }
    
    /**
     * Get overall review statistics for admin dashboard
     * 
     * @return array Global statistics
     */
    public function get_review_statistics() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                AVG(CASE WHEN status = 'approved' THEN rating END) as average_rating
             FROM {$this->table_name}",
            ARRAY_A
        );
        
        return array(
            'total' => (int) $stats['total'],
            'pending' => (int) $stats['pending'],
            'approved' => (int) $stats['approved'],
            'rejected' => (int) $stats['rejected'],
            'average_rating' => $stats['average_rating'] ? (float) $stats['average_rating'] : 0
        );
    }
    
    /**
     * Delete a review (admin action)
     * 
     * @param int $review_id The review ID
     * @return bool Success status
     */
    public function delete_review($review_id) {
        global $wpdb;
        
        // Get seller ID before deletion for cache update
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT seller_id FROM {$this->table_name} WHERE id = %d",
            $review_id
        ));
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $review_id),
            array('%d')
        );
        
        if ($result !== false && $review) {
            $this->update_seller_rating_cache($review->seller_id);
            return true;
        }
        
        return false;
    }
}

// Initialize the database class
new SellerReviewsDatabase(); 