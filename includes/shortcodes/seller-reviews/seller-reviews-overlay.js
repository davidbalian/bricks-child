/**
 * Seller Reviews Overlay JavaScript
 * Handles overlay show/hide and form submission
 */

jQuery(document).ready(function($) {
    
    // Store original parent and position for restoration
    var originalParent = null;
    var originalNextSibling = null;
    
    // Show overlay when "See all reviews" button is clicked (built-in overlay)
    $(document).on('click', '.btn-toggle-review-form', function(e) {
        e.preventDefault();
        
        var $overlay = $('.seller-reviews-overlay');
        
        // Store original position
        originalParent = $overlay.parent();
        originalNextSibling = $overlay.next();
        
        // Move overlay to body to ensure highest stacking context
        $('body').append($overlay);
        
        // Show overlay background first
        $overlay.show();
        
        // Force browser reflow then add animation class
        setTimeout(function() {
            $overlay.addClass('show');
            $('body').addClass('overlay-open').css('overflow', 'hidden');
        }, 10);
    });
    
    // Hide overlay when close button or background is clicked
    $(document).on('click', '.close-overlay, .seller-reviews-overlay', function(e) {
        if (e.target === this) {
            var $overlay = $('.seller-reviews-overlay');
            $overlay.removeClass('show');
            $('body').removeClass('overlay-open').css('overflow', '');
            
            // Hide overlay completely after animation and restore position
            setTimeout(function() {
                $overlay.hide();
                
                // Restore overlay to original position
                if (originalParent && originalParent.length) {
                    if (originalNextSibling && originalNextSibling.length) {
                        originalNextSibling.before($overlay);
                    } else {
                        originalParent.append($overlay);
                    }
                }
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
            var $overlay = $('.seller-reviews-overlay');
            $overlay.removeClass('show');
            $('body').removeClass('overlay-open').css('overflow', '');
            
            // Hide overlay completely after animation and restore position
            setTimeout(function() {
                $overlay.hide();
                
                // Restore overlay to original position
                if (originalParent && originalParent.length) {
                    if (originalNextSibling && originalNextSibling.length) {
                        originalNextSibling.before($overlay);
                    } else {
                        originalParent.append($overlay);
                    }
                }
            }, 400); // Match the CSS transition duration
        }
    });
    
        // Handle review form submission
    $(document).on('submit', '.seller-review-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('.btn-submit-review');
        var $messages = $form.find('.form-messages');
        
        // Check if form is disabled (user email not verified)
        if ($button.is(':disabled')) {
            $messages.html('<div class="error">Please verify your email before leaving a review.</div>');
            return;
        }
        
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
        var $container = $label.closest('.star-rating-input');
        var $input = $container.find('#' + $label.attr('for'));
        var rating = parseInt($input.val()); // Get the VALUE, not the ID
        
        // Check the radio button
        $input.prop('checked', true);
        
        // Reset all stars to gray
        $container.find('label').css('color', '#ddd');
        
        // Get all labels in DOM order and highlight the first N
        var $allLabels = $container.find('label');
        $allLabels.each(function(index) {
            if (index < rating) { // index is 0-based, so < rating gives us the first N stars
                $(this).css('color', '#ffa500');
            }
        });
    });
    
    // Hover effects
    $(document).on('mouseenter', '.star-rating-input label', function() {
        var $label = $(this);
        var $container = $label.closest('.star-rating-input');
        var $input = $container.find('#' + $label.attr('for'));
        var rating = parseInt($input.val());
        
        // Reset all stars to gray
        $container.find('label').css('color', '#ddd');
        
        // Highlight the first N stars based on hovered value
        var $allLabels = $container.find('label');
        $allLabels.each(function(index) {
            if (index < rating) {
                $(this).css('color', '#ffa500');
            }
        });
    });
    
    $(document).on('mouseleave', '.star-rating-input', function() {
        var $container = $(this);
        var $checkedInput = $container.find('input[type="radio"]:checked');
        
        // Reset all colors first
        $container.find('label').css('color', '#ddd');
        
        // If there's a checked input, restore its selection
        if ($checkedInput.length > 0) {
            var checkedRating = parseInt($checkedInput.val());
            var $allLabels = $container.find('label');
            $allLabels.each(function(index) {
                if (index < checkedRating) {
                    $(this).css('color', '#ffa500');
                }
            });
        }
    });
    
}); 