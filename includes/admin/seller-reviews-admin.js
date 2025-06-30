/**
 * Seller Reviews Admin JavaScript
 * Handles admin dashboard actions for review management
 */

function approveReview(reviewId) {
    if (confirm('Approve this review?')) {
        jQuery.post(ajaxurl, {
            action: 'approve_seller_review',
            review_id: reviewId,
            nonce: sellerReviewsAdmin.nonce
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
            nonce: sellerReviewsAdmin.nonce
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
            nonce: sellerReviewsAdmin.nonce
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
            nonce: sellerReviewsAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    }
} 