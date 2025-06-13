/**
 * My Account Display JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('My Account page loaded');
    
    // Only run if we're on the main account page (not password reset steps)
    if (window.location.search.includes('password_reset_step')) {
        return;
    }
    
    // Store original name values
    var originalFirstName = document.getElementById('first-name').value;
    var originalLastName = document.getElementById('last-name').value;
    var displayName = document.getElementById('display-name');
    
    console.log('Initial values:', {originalFirstName, originalLastName});

    // Name editing functionality
    document.querySelector('.edit-name-btn').addEventListener('click', function(e) {
        console.log('Edit button clicked');
        e.preventDefault();
        
        // Don't overwrite the original values here - we need them for comparison later
        // The input fields already have the correct values from the PHP variables
        console.log('Original values for comparison:', {originalFirstName, originalLastName});
        
        // Show edit fields
        document.querySelector('.name-row').style.display = 'none';
        document.querySelectorAll('.name-edit-row').forEach(function(row) {
            row.style.display = 'flex';
        });
    });

    document.querySelector('.cancel-name-btn').addEventListener('click', function(e) {
        console.log('Cancel button clicked');
        e.preventDefault();
        
        // Restore original values
        document.getElementById('first-name').value = originalFirstName;
        document.getElementById('last-name').value = originalLastName;
        
        document.querySelector('.name-row').style.display = 'flex';
        document.querySelectorAll('.name-edit-row').forEach(function(row) {
            row.style.display = 'none';
        });
    });

    document.querySelector('.save-name-btn').addEventListener('click', function(e) {
        console.log('Save button clicked');
        e.preventDefault();
        
        var firstName = document.getElementById('first-name').value.trim();
        var lastName = document.getElementById('last-name').value.trim();
        
        if (firstName === '' && lastName === '') {
            alert('Please enter at least a first name or last name');
            return;
        }

        // Check if anything actually changed
        if (firstName === originalFirstName && lastName === originalLastName) {
            console.log('No changes detected, just hiding edit form');
            // No changes, just hide the edit form
            document.querySelector('.name-row').style.display = 'flex';
            document.querySelectorAll('.name-edit-row').forEach(function(row) {
                row.style.display = 'none';
            });
            return;
        }

        console.log('Changes detected, sending to server');
        
        // Create form data for AJAX request
        var formData = new FormData();
        formData.append('action', 'update_user_name');
        formData.append('first_name', firstName);
        formData.append('last_name', lastName);
        formData.append('nonce', MyAccountAjax.update_user_name_nonce);

        // Send AJAX request
        fetch(MyAccountAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh the page with success parameter
                window.location.href = window.location.pathname + '?name_updated=1';
            } else {
                alert('Error updating name: ' + (data.data || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating name. Please try again.');
        });
    });

    // Handle Enter key in name inputs
    document.querySelectorAll('.name-input').forEach(function(input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('.save-name-btn').click();
            }
        });
    });

    // Password reset functionality
    document.querySelector('.reset-password-btn').addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Reset password clicked');
        
        if (confirm('Are you sure you want to reset your password? A verification code will be sent to your phone number.')) {
            initiatePasswordReset();
        }
    });

    function initiatePasswordReset() {
        var formData = new FormData();
        formData.append('action', 'initiate_password_reset');
        formData.append('nonce', MyAccountAjax.password_reset_nonce);

        fetch(MyAccountAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Verification code sent to your phone number. Please check your messages.');
                window.location.href = window.location.pathname + '?password_reset_step=verify';
            } else {
                alert('Error: ' + (data.data || 'Unable to send verification code'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error sending verification code. Please try again.');
        });
    }
});

/**
 * My Account Email Verification JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    
    // Email editing functionality
    $('.edit-email-btn').on('click', function() {
        $('.email-row').hide();
        $('.email-edit-row').show();
        $('#new-email').focus();
    });
    
    $('.cancel-email-btn').on('click', function() {
        $('.email-edit-row').hide();
        $('.email-row').show();
        // Reset email input to current value
        $('#new-email').val($('#display-email').text());
    });
    
    // Send verification email
    $('.send-verification-btn').on('click', function() {
        const button = $(this);
        const email = $('#new-email').val().trim();
        const currentEmail = $('#display-email').text().trim();
        const isEmailChange = email !== currentEmail;
        const isCurrentEmailVerified = $('.email-status.verified').length > 0;
        
        console.log('Send verification clicked, email:', email);
        console.log('Current email:', currentEmail);
        console.log('Is email change:', isEmailChange);
        console.log('Is current email verified:', isCurrentEmailVerified);
        console.log('AJAX URL:', MyAccountAjax.ajax_url);
        console.log('Nonce:', MyAccountAjax.email_verification_nonce);
        
        // Basic validation
        if (!email) {
            alert('Please enter an email address');
            return;
        }
        
        if (!isValidEmail(email)) {
            alert('Please enter a valid email address');
            return;
        }
        
        // Check if user is trying to verify the same email that's already verified
        if (isCurrentEmailVerified && !isEmailChange) {
            alert('✅ This email address is already verified!\n\n💡 If you want to use a different email, please enter a new email address.');
            return;
        }
        
        // Prevent multiple rapid requests
        if (button.prop('disabled')) {
            return;
        }
        
        // Disable button and show loading with better messaging
        button.prop('disabled', true).text('Sending Email...');
        $('.cancel-email-btn').prop('disabled', true);
        
        // Remove any existing progress messages first
        $('.email-progress-message').remove();
        
        // Add a progress indicator with context-aware messaging (above the buttons)
        const actionText = isEmailChange ? 'Sending email change verification' : 'Sending verification email';
        const progressMsg = $('<div class="email-progress-message" style="background: #e7f3ff; color: #0073aa; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3d9ed;">📧 ' + actionText + '... This may take up to 2 minutes to arrive.</div>');
        $('.email-edit-row:last').before(progressMsg);
        
        // Send AJAX request
        $.ajax({
            url: MyAccountAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'send_email_verification',
                email: email,
                nonce: MyAccountAjax.email_verification_nonce
            },
            success: function(response) {
                console.log('AJAX Success Response:', response);
                
                // Remove progress message
                $('.email-progress-message').remove();
                
                if (response.success) {
                    // Create context-aware success message
                    const actionType = isEmailChange ? 'email change' : 'verification';
                    const actionVerb = isEmailChange ? 'Email change verification' : 'Verification email';
                    
                    alert('✅ ' + actionVerb + ' sent successfully!\n\n📧 Please check your inbox (and spam folder) in the next 1-2 minutes.\n\n⏱️ Note: Email delivery can sometimes take up to 5 minutes depending on your email provider.\n\n🔄 If you don\'t receive it within 5 minutes, you can try sending another ' + actionType + ' email.');
                    
                    // Update the displayed email to the new email
                    $('#display-email').text(email);
                    
                    // Update verification status - reset to not verified for email changes
                    if (isEmailChange) {
                        $('.email-status').removeClass('verified').addClass('not-verified')
                                          .html('❌ Not Verified');
                        $('.edit-email-btn').text('Edit & Verify');
                    }
                    
                    // Hide edit form and show main row
                    $('.email-edit-row').hide();
                    $('.email-row').show();
                    
                    // Show context-aware persistent success message
                    const successText = isEmailChange ? 'Email change verification sent to ' + email + '!' : 'Verification email sent to ' + email + '!';
                    const successMsg = $('<div class="email-success-message" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; font-weight: 600;">📧 ' + successText + '<br><small style="font-weight: normal; margin-top: 5px; display: block;">⏱️ Allow up to 5 minutes for delivery. Check your spam folder if needed.</small></div>');
                    $('.email-row').after(successMsg);
                    
                    // Remove success message after 30 seconds
                    setTimeout(function() {
                        successMsg.fadeOut(500, function() {
                            $(this).remove();
                        });
                    }, 30000);
                    
                } else {
                    console.log('AJAX Error Response:', response.data);
                    alert('❌ Error: ' + response.data + '\n\nPlease try again in a moment.');
                    // DON'T hide the edit form on error - let user try again
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Request Failed:', {xhr, status, error});
                console.log('Response Text:', xhr.responseText);
                
                // Remove progress message
                $('.email-progress-message').remove();
                
                alert('❌ Connection error occurred. Please check your internet connection and try again.\n\nTechnical details logged to console.');
                // DON'T hide the edit form on error - let user try again
            },
            complete: function() {
                // Re-enable buttons after a 5-second delay to prevent rapid clicking
                setTimeout(function() {
                    button.prop('disabled', false).text('Send Verification Email');
                    $('.cancel-email-btn').prop('disabled', false);
                }, 5000);
            }
        });
    });
    
    // Email validation function
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Show success/error messages from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const emailVerified = urlParams.get('email_verified');
    
    if (emailVerified === 'success') {
        const successMsg = $('<div class="email-success-message" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #c3e6cb; font-weight: 600;">✅ Email verified successfully! Your email notifications are now active.</div>');
        $('.my-account-container h2').after(successMsg);
        
        // Remove URL parameter and reload to show updated status
        setTimeout(function() {
            window.location.href = window.location.pathname;
        }, 3000);
        
    } else if (emailVerified === 'error') {
        const errorMsg = $('<div class="email-error-message" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #f5c6cb; font-weight: 600;">❌ Email verification failed. The link may be expired or invalid.</div>');
        $('.my-account-container h2').after(errorMsg);
        
        // Remove URL parameter
        setTimeout(function() {
            window.location.href = window.location.pathname;
        }, 5000);
    }
}); 