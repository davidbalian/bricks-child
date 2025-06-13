/**
 * Email Verification Notification JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    
    // Send verification email (reuse logic from my-account page)
    $('.send-verification-btn').on('click', function() {
        const button = $(this);
        const email = button.data('email');
        
        console.log('Notification: Send verification clicked, email:', email);
        
        // Basic validation
        if (!email) {
            alert('Email address not found');
            return;
        }
        
        // Prevent multiple rapid requests
        if (button.prop('disabled')) {
            return;
        }
        
        // Disable button and show loading
        const originalText = button.text();
        button.prop('disabled', true).text('Sending...');
        
        // Send AJAX request (same as my-account page)
        $.ajax({
            url: EmailNotificationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'send_email_verification',
                email: email,
                nonce: EmailNotificationAjax.email_verification_nonce
            },
            success: function(response) {
                console.log('Notification AJAX Success:', response);
                
                if (response.success) {
                    // Show success state
                    $('#email-verification-notification')
                        .addClass('success')
                        .find('.notice-text')
                        .html('✅ Verification email sent to <strong>' + email + '</strong>! Check your inbox and click the verification link.');
                    
                    // Hide the buttons
                    $('.send-verification-btn, .dismiss-notice-btn').hide();
                    
                    // Auto-hide notification after 10 seconds
                    setTimeout(function() {
                        dismissNotification();
                    }, 10000);
                    
                } else {
                    console.log('Notification AJAX Error:', response.data);
                    alert('❌ Error: ' + response.data + '\n\nPlease try again.');
                    
                    // Re-enable button
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.log('Notification AJAX Failed:', {xhr, status, error});
                
                alert('❌ Connection error occurred. Please try again.');
                
                // Re-enable button
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Dismiss notification
    $('.dismiss-notice-btn').on('click', function() {
        dismissNotification();
    });
    
    // Function to dismiss notification with animation
    function dismissNotification() {
        const notification = $('#email-verification-notification');
        
        // Add hiding class for animation
        notification.addClass('hiding');
        
        // Send AJAX to set session flag
        $.ajax({
            url: EmailNotificationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'dismiss_email_notification',
                nonce: EmailNotificationAjax.dismiss_notification_nonce
            },
            success: function(response) {
                console.log('Notification dismissed:', response);
            },
            error: function(xhr, status, error) {
                console.log('Dismiss notification failed:', {xhr, status, error});
            }
        });
        
        // Remove from DOM after animation
        setTimeout(function() {
            notification.remove();
        }, 300);
    }
    
    // Email validation function (reused from my-account)
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
}); 