/**
 * Email Verification Notification JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    const t = window.autoagoraTranslate || ((source) => source);
    
    // Send verification email (only for notification context)
    $('#email-verification-notification .send-verification-btn').on('click', function() {
        const button = $(this);
        const email = button.data('email');
        
        // PRODUCTION SAFETY: Only log in development environments
window.isDevelopment = window.isDevelopment || (window.location.hostname === 'localhost' || 
                                               window.location.hostname.includes('staging') ||
                                               window.location.search.includes('debug=true'));
        
        if (isDevelopment) console.log('Notification: Send verification clicked, email:', email);
        
        // Basic validation
        if (!email) {
            alert(t('Email address not found'));
            return;
        }
        
        // Prevent multiple rapid requests
        if (button.prop('disabled')) {
            return;
        }
        
        // Disable button and show loading
        const originalText = button.text();
        button.prop('disabled', true).text(t('Sending...'));
        
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
                if (isDevelopment) console.log('Notification AJAX Success:', response);
                
                if (response.success) {
                    // Show success state
                    $('#email-verification-notification')
                        .addClass('success')
                        .find('.notice-text')
                        .html('✅ ' + t('Verification email sent to <strong>%s</strong>! Check your inbox and click the verification link.', email));
                    
                    // Hide the buttons
                    $('.send-verification-btn, .dismiss-notice-btn').hide();
                    
                    // Auto-hide notification after 10 seconds
                    setTimeout(function() {
                        dismissNotification();
                    }, 10000);
                    
                } else {
                    if (isDevelopment) console.log('Notification AJAX Error:', response.data);
                    alert('❌ ' + t('Error: %s', response.data) + '\n\n' + t('Please try again.'));
                    
                    // Re-enable button
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                if (isDevelopment) console.log('Notification AJAX Failed:', {xhr, status, error});
                
                alert('❌ ' + t('Connection error occurred. Please try again.'));
                
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
                if (isDevelopment) console.log('Notification dismissed:', response);
            },
            error: function(xhr, status, error) {
                if (isDevelopment) console.log('Dismiss notification failed:', {xhr, status, error});
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
