/**
 * Seller Reviews Overlay JavaScript
 * Handles overlay show/hide and form submission
 */

jQuery(document).ready(function($) {
    
    // Show overlay when "See all reviews" button is clicked
    $(document).on('click', '.btn-toggle-review-form', function(e) {
        e.preventDefault();
        
        // Show overlay background first
        $('.seller-reviews-overlay').show();
        
        // Force browser reflow then add animation class
        setTimeout(function() {
            $('.seller-reviews-overlay').addClass('show');
            $('body').addClass('overlay-open').css('overflow', 'hidden');
        }, 10);
    });
    
    // Hide overlay when close button or background is clicked
    $(document).on('click', '.close-overlay, .seller-reviews-overlay', function(e) {
        if (e.target === this) {
            $('.seller-reviews-overlay').removeClass('show');
            $('body').removeClass('overlay-open').css('overflow', '');
            
            // Hide overlay completely after animation
            setTimeout(function() {
                $('.seller-reviews-overlay').hide();
            }, 400); // Match the CSS transition duration
        }
    });
    
    // Prevent closing when clicking inside overlay content
    $(document).on('click', '.seller-reviews-overlay-content', function(e) {
        e.stopPropagation();
    });
    
    // Close overlay with Escape key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('.seller-reviews-overlay').hasClass('show')) {
            $('.seller-reviews-overlay').removeClass('show');
            $('body').removeClass('overlay-open').css('overflow', '');
            
            // Hide overlay completely after animation
            setTimeout(function() {
                $('.seller-reviews-overlay').hide();
            }, 400); // Match the CSS transition duration
        }
    });
    
    // Handle review form submission
    $(document).on('submit', '.seller-review-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('.btn-submit-review');
        var $messages = $form.find('.form-messages');
        
        // Get form data
        var sellerId = $form.data('seller-id');
        var rating = $form.find('input[name="rating"]:checked').val();
        var comment = $form.find('textarea[name="comment"]').val();
        var contactedSeller = $form.find('input[name="contacted_seller"]').is(':checked') ? 1 : 0;
        var nonce = $form.find('input[name="seller_review_nonce"]').val();
        
        // Validate rating
        if (!rating) {
            $messages.html('<div class="error">Please select a rating.</div>');
            return;
        }
        
        // Disable submit button
        $button.prop('disabled', true).text('Submitting...');
        $messages.html('');
        
        // Submit via AJAX
        $.ajax({
            url: sellerReviewsData.ajaxurl,
            type: 'POST',
            data: {
                action: 'submit_seller_review',
                seller_id: sellerId,
                rating: rating,
                comment: comment,
                contacted_seller: contactedSeller,
                seller_review_nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $messages.html('<div class="success">' + response.data.message + '</div>');
                    $form[0].reset();
                    
                    // Close overlay after 2 seconds
                    setTimeout(function() {
                        $('.seller-reviews-overlay').removeClass('show');
                        $('body').removeClass('overlay-open').css('overflow', '');
                        
                        // Reload the page to show updated reviews
                        location.reload();
                    }, 2000);
                } else {
                    $messages.html('<div class="error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                $messages.html('<div class="error">An error occurred. Please try again.</div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Submit Review');
            }
        });
    });
    
    // Character counter for textarea
    $(document).on('input', 'textarea[name="comment"]', function() {
        var maxLength = 140;
        var currentLength = $(this).val().length;
        var remaining = maxLength - currentLength;
        
        var $small = $(this).siblings('small');
        if (remaining < 0) {
            $small.text('Character limit exceeded by ' + Math.abs(remaining) + ' characters').css('color', '#dc3545');
        } else {
            $small.text(remaining + ' characters remaining').css('color', '#666');
        }
    });
    
    // Simple star rating functionality
    $(document).on('click', '.star-rating-input label', function() {
        var $label = $(this);
        var rating = $label.attr('for').replace('star', '');
        var $container = $label.closest('.star-rating-input');
        var $input = $container.find('#' + $label.attr('for'));
        
        // Check the radio button
        $input.prop('checked', true);
        
        // Update visual state
        $container.find('label').removeClass('active');
        for (var i = 1; i <= rating; i++) {
            $container.find('label[for="star' + i + '"]').addClass('active');
        }
    });
    
    // Hover effects
    $(document).on('mouseenter', '.star-rating-input label', function() {
        var $label = $(this);
        var rating = $label.attr('for').replace('star', '');
        var $container = $label.closest('.star-rating-input');
        
        // Temporarily highlight up to hovered star
        $container.find('label').removeClass('hover-active');
        for (var i = 1; i <= rating; i++) {
            $container.find('label[for="star' + i + '"]').addClass('hover-active').css('color', '#ffa500');
        }
    });
    
    $(document).on('mouseleave', '.star-rating-input', function() {
        var $container = $(this);
        // Remove hover effect and restore active state
        $container.find('label').removeClass('hover-active').css('color', '');
    });
    
}); 