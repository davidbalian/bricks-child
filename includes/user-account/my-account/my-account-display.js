/**
 * My Account Display JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    // PRODUCTION SAFETY: Only log in development environments
window.isDevelopment = window.isDevelopment || (window.location.hostname === 'localhost' || 
                                               window.location.hostname.includes('staging') ||
                                               window.location.search.includes('debug=true'));
    
    if (isDevelopment) console.log('My Account page loaded');
    
    // Only run if we're on the main account page (not password reset steps)
    if (window.location.search.includes('password_reset_step')) {
        return;
    }
    
    // Store original name values
    var originalFirstName = document.getElementById('first-name').value;
    var originalLastName = document.getElementById('last-name').value;
    var displayName = document.getElementById('display-name');
    
    if (isDevelopment) console.log('Initial values:', {originalFirstName, originalLastName});

    // Name editing functionality
    document.querySelector('.edit-name-btn').addEventListener('click', function(e) {
        if (isDevelopment) console.log('Edit button clicked');
        e.preventDefault();
        
        // Don't overwrite the original values here - we need them for comparison later
        // The input fields already have the correct values from the PHP variables
        if (isDevelopment) console.log('Original values for comparison:', {originalFirstName, originalLastName});
        
        // Show edit fields
        document.querySelector('.name-row').style.display = 'none';
        document.querySelectorAll('.name-edit-row').forEach(function(row) {
            row.style.display = 'flex';
        });
    });

    document.querySelector('.cancel-name-btn').addEventListener('click', function(e) {
        if (isDevelopment) console.log('Cancel button clicked');
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
        if (isDevelopment) console.log('Save button clicked');
        e.preventDefault();
        
        var firstName = document.getElementById('first-name').value.trim();
        var lastName = document.getElementById('last-name').value.trim();
        
        if (firstName === '' && lastName === '') {
            alert('Please enter at least a first name or last name');
            return;
        }

        // Check if anything actually changed
        if (firstName === originalFirstName && lastName === originalLastName) {
            if (isDevelopment) console.log('No changes detected, just hiding edit form');
            // No changes, just hide the edit form
            document.querySelector('.name-row').style.display = 'flex';
            document.querySelectorAll('.name-edit-row').forEach(function(row) {
                row.style.display = 'none';
            });
            return;
        }

        if (isDevelopment) console.log('Changes detected, sending to server');
        
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
            if (isDevelopment) console.error('Error:', error);
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

    // Secondary phone editing functionality
    (function () {
        var secondaryPhoneDisplay = document.getElementById('display-secondary-phone');
        var secondaryPhoneRow = document.querySelector('.secondary-phone-row');
        var secondaryPhoneEditRows = document.querySelectorAll('.secondary-phone-edit-row');
        var secondaryPhoneInput = document.getElementById('secondary-phone-local');
        var editSecondaryPhoneBtn = document.querySelector('.edit-secondary-phone-btn');
        var saveSecondaryPhoneBtn = document.querySelector('.save-secondary-phone-btn');
        var cancelSecondaryPhoneBtn = document.querySelector('.cancel-secondary-phone-btn');
        var COUNTRY_CODE = '357';

        if (!secondaryPhoneDisplay || !secondaryPhoneRow || !secondaryPhoneInput || !editSecondaryPhoneBtn || !saveSecondaryPhoneBtn || !cancelSecondaryPhoneBtn) {
            return;
        }

        var originalSecondaryPhone = (secondaryPhoneDisplay.dataset.fullPhone || '').trim();
        var originalSecondaryPhoneLocal = originalSecondaryPhone;

        if (originalSecondaryPhone && originalSecondaryPhone.indexOf(COUNTRY_CODE) === 0) {
            originalSecondaryPhoneLocal = originalSecondaryPhone.slice(COUNTRY_CODE.length);
        }

        // Initialize input with the local part
        secondaryPhoneInput.value = originalSecondaryPhoneLocal;

        editSecondaryPhoneBtn.addEventListener('click', function (e) {
            e.preventDefault();

            secondaryPhoneRow.style.display = 'none';
            secondaryPhoneEditRows.forEach(function (row) {
                row.style.display = 'flex';
            });

            secondaryPhoneInput.focus();
        });

        cancelSecondaryPhoneBtn.addEventListener('click', function (e) {
            e.preventDefault();

            // Restore original value
            secondaryPhoneInput.value = originalSecondaryPhoneLocal;

            secondaryPhoneRow.style.display = 'flex';
            secondaryPhoneEditRows.forEach(function (row) {
                row.style.display = 'none';
            });
        });

        saveSecondaryPhoneBtn.addEventListener('click', function (e) {
            e.preventDefault();

            var localPart = (secondaryPhoneInput.value || '').replace(/\D+/g, '');

            // Require exactly 8 digits for the local part on the client side
            if (localPart.length !== 8) {
                alert('Please enter a valid 8-digit phone number (without country code).');
                return;
            }

            var newFullPhone = COUNTRY_CODE + localPart;

            // If nothing changed, just hide the edit UI without AJAX
            if (newFullPhone === originalSecondaryPhone) {
                secondaryPhoneRow.style.display = 'flex';
                secondaryPhoneEditRows.forEach(function (row) {
                    row.style.display = 'none';
                });
                return;
            }

            var formData = new FormData();
            formData.append('action', 'update_secondary_phone');
            formData.append('secondary_phone', newFullPhone);
            formData.append('nonce', MyAccountAjax.update_secondary_phone_nonce);

            fetch(MyAccountAjax.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        // Update the display value and internal state
                        var displayText = '+' + COUNTRY_CODE + ' ' + localPart;
                        secondaryPhoneDisplay.textContent = displayText;
                        secondaryPhoneDisplay.dataset.fullPhone = newFullPhone;
                        originalSecondaryPhone = newFullPhone;
                        originalSecondaryPhoneLocal = localPart;

                        // Once a valid secondary phone is saved, the action becomes "Edit"
                        if (editSecondaryPhoneBtn) {
                            editSecondaryPhoneBtn.textContent = 'Edit';
                        }

                        secondaryPhoneRow.style.display = 'flex';
                        secondaryPhoneEditRows.forEach(function (row) {
                            row.style.display = 'none';
                        });
                    } else {
                        var errorMsg = (data && data.data) ? data.data : 'Error updating secondary phone number. Please try again.';
                        alert(errorMsg);
                    }
                })
                .catch(function () {
                    alert('Error updating secondary phone number. Please try again.');
                });
        });

        // Handle Enter key for secondary phone input
        secondaryPhoneInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveSecondaryPhoneBtn.click();
            }
        });
    })();

    // Password reset functionality
    document.querySelector('.reset-password-btn').addEventListener('click', function(e) {
        e.preventDefault();
        if (isDevelopment) console.log('Reset password clicked');
        
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
            if (isDevelopment) console.error('Error:', error);
            alert('Error sending verification code. Please try again.');
        });
    }

    // Notification preference toggles
    var activityToggle = document.getElementById('activity-notifications-toggle');
    var reminderToggle = document.getElementById('reminder-notifications-toggle');
    var notificationFeedback = document.getElementById('notification-preferences-feedback');
    var notificationRequestInFlight = false;

    function updateNotificationPreferences(activityValue, reminderValue) {
        if (notificationRequestInFlight) {
            return;
        }

        if (!activityToggle || !reminderToggle) {
            return;
        }

        notificationRequestInFlight = true;

        if (notificationFeedback) {
            notificationFeedback.textContent = 'Saving preferences...';
            notificationFeedback.classList.remove('success', 'error');
        }

        var formData = new FormData();
        formData.append('action', 'update_listing_notification_preferences');
        formData.append('activity_notifications', activityValue ? '1' : '0');
        formData.append('reminder_notifications', reminderValue ? '1' : '0');
        formData.append('nonce', MyAccountAjax.notification_preferences_nonce);

        fetch(MyAccountAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            notificationRequestInFlight = false;

            if (!notificationFeedback) {
                return;
            }

            if (data.success) {
                notificationFeedback.textContent = 'Preferences saved.';
                notificationFeedback.classList.add('success');
            } else {
                notificationFeedback.textContent = data.data || 'Unable to save preferences.';
                notificationFeedback.classList.add('error');
            }
        })
        .catch(error => {
            notificationRequestInFlight = false;
            if (!notificationFeedback) {
                return;
            }

            notificationFeedback.textContent = 'Connection error. Please try again.';
            notificationFeedback.classList.add('error');
        });
    }

    if (activityToggle && reminderToggle) {
        activityToggle.addEventListener('change', function() {
            updateNotificationPreferences(activityToggle.checked, reminderToggle.checked);
        });

        reminderToggle.addEventListener('change', function() {
            updateNotificationPreferences(activityToggle.checked, reminderToggle.checked);
        });
    }

    // Account logo upload/remove handling
    var uploadLogoBtn = document.getElementById('upload-account-logo-btn');
    var removeLogoBtn = document.getElementById('remove-account-logo-btn');
    var logoInput = document.getElementById('account-logo-input');
    var logoImage = document.getElementById('account-logo-image');
    var logoPlaceholder = document.getElementById('account-logo-placeholder');
    var logoFeedback = document.getElementById('account-logo-feedback');
    var logoRequestInFlight = false;

    // Hide feedback element initially if empty
    if (logoFeedback && !logoFeedback.textContent.trim()) {
        logoFeedback.style.display = 'none';
    }

    function setLogoFeedback(message, type) {
        if (!logoFeedback) {
            return;
        }
        logoFeedback.textContent = message || '';
        logoFeedback.classList.remove('success', 'error');
        // Hide if empty, show if has message
        logoFeedback.style.display = message ? '' : 'none';
        if (type === 'success') {
            logoFeedback.classList.add('success');
        } else if (type === 'error') {
            logoFeedback.classList.add('error');
        }
    }

    function setLogoLoadingState(isLoading) {
        logoRequestInFlight = isLoading;
        if (uploadLogoBtn) {
            uploadLogoBtn.disabled = isLoading;
            uploadLogoBtn.textContent = isLoading ? 'Uploading...' : (logoImage && logoImage.src ? 'Change Logo' : 'Upload Logo');
        }
        if (removeLogoBtn) {
            removeLogoBtn.disabled = isLoading;
        }
    }

    if (uploadLogoBtn && logoInput) {
        uploadLogoBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (logoRequestInFlight) {
                return;
            }
            logoInput.click();
        });

        logoInput.addEventListener('change', function () {
            if (!logoInput.files || !logoInput.files[0]) {
                return;
            }

            var file = logoInput.files[0];

            // Basic front-end validation (2MB, image type)
            var maxSizeBytes = 2 * 1024 * 1024;
            if (file.size > maxSizeBytes) {
                setLogoFeedback('Image is too large. Max size is 2 MB.', 'error');
                logoInput.value = '';
                return;
            }

            if (!file.type || !file.type.startsWith('image/')) {
                setLogoFeedback('Please choose a valid image file.', 'error');
                logoInput.value = '';
                return;
            }

            var formData = new FormData();
            formData.append('action', 'upload_account_logo');
            formData.append('account_logo', file);
            formData.append('nonce', MyAccountAjax.upload_account_logo_nonce);

            setLogoLoadingState(true);
            setLogoFeedback('Uploading logo...', null);

            fetch(MyAccountAjax.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.success && data.data && data.data.logoUrl) {
                        var url = data.data.logoUrl;
                        if (!logoImage) {
                            logoImage = document.createElement('img');
                            logoImage.id = 'account-logo-image';
                            logoImage.alt = 'Account logo';
                            var preview = document.querySelector('.account-logo-preview');
                            if (preview) {
                                preview.innerHTML = '';
                                preview.appendChild(logoImage);
                            }
                        }
                        logoImage.src = url;
                        if (logoPlaceholder && logoPlaceholder.parentNode) {
                            logoPlaceholder.parentNode.removeChild(logoPlaceholder);
                            logoPlaceholder = null;
                        }
                        if (!removeLogoBtn) {
                            var actions = document.querySelector('.account-logo-actions');
                            if (actions) {
                                removeLogoBtn = document.createElement('button');
                                removeLogoBtn.type = 'button';
                                removeLogoBtn.className = 'btn btn-secondary';
                                removeLogoBtn.id = 'remove-account-logo-btn';
                                removeLogoBtn.textContent = 'Remove Logo';
                                actions.insertBefore(removeLogoBtn, actions.querySelector('.account-logo-help-text'));
                                removeLogoBtn.addEventListener('click', handleRemoveLogo);
                            }
                        }
                        if (uploadLogoBtn) {
                            uploadLogoBtn.textContent = 'Change Logo';
                        }
                        setLogoFeedback(data.data.message || 'Logo updated successfully.', 'success');
                    } else {
                        var errorMsg = data && data.data ? data.data : 'Failed to upload logo.';
                        setLogoFeedback(errorMsg, 'error');
                    }
                })
                .catch(function () {
                    setLogoFeedback('Connection error while uploading logo.', 'error');
                })
                .finally(function () {
                    setLogoLoadingState(false);
                    logoInput.value = '';
                });
        });
    }

    function handleRemoveLogo(e) {
        if (e) {
            e.preventDefault();
        }
        if (logoRequestInFlight) {
            return;
        }

        if (!confirm('Remove your account logo? This cannot be undone.')) {
            return;
        }

        var formData = new FormData();
        formData.append('action', 'remove_account_logo');
        formData.append('nonce', MyAccountAjax.remove_account_logo_nonce);

        setLogoLoadingState(true);
        setLogoFeedback('Removing logo...', null);

        fetch(MyAccountAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data && data.success) {
                    if (logoImage && logoImage.parentNode) {
                        var previewContainer = logoImage.parentNode;
                        previewContainer.removeChild(logoImage);
                        logoImage = null;
                        if (!logoPlaceholder) {
                            logoPlaceholder = document.createElement('div');
                            logoPlaceholder.id = 'account-logo-placeholder';
                            logoPlaceholder.className = 'account-logo-placeholder';
                            logoPlaceholder.innerHTML = '<span>No logo uploaded</span>';
                            previewContainer.appendChild(logoPlaceholder);
                        }
                    }
                    if (removeLogoBtn && removeLogoBtn.parentNode) {
                        removeLogoBtn.parentNode.removeChild(removeLogoBtn);
                        removeLogoBtn = null;
                    }
                    if (uploadLogoBtn) {
                        uploadLogoBtn.textContent = 'Upload Logo';
                    }
                    setLogoFeedback('Logo removed successfully.', 'success');
                } else {
                    var errorMsg = data && data.data ? data.data : 'Failed to remove logo.';
                    setLogoFeedback(errorMsg, 'error');
                }
            })
            .catch(function () {
                setLogoFeedback('Connection error while removing logo.', 'error');
            })
            .finally(function () {
                setLogoLoadingState(false);
            });
    }

    if (removeLogoBtn) {
        removeLogoBtn.addEventListener('click', handleRemoveLogo);
    }

    // Dealer information fields handling
    (function() {
        // Helper function to handle dealer field edit/save/cancel
        function setupDealerField(fieldName, displayId, inputId, editBtnClass, saveBtnClass, cancelBtnClass, displayRowClass, editRowClass, ajaxAction, nonceKey) {
            var editBtn = document.querySelector('.' + editBtnClass);
            var saveBtn = document.querySelector('.' + saveBtnClass);
            var cancelBtn = document.querySelector('.' + cancelBtnClass);
            var displayRow = document.querySelector('.' + displayRowClass);
            var editRows = document.querySelectorAll('.' + editRowClass);
            var displayElement = document.getElementById(displayId);
            var inputElement = document.getElementById(inputId);
            
            if (!editBtn || !saveBtn || !cancelBtn || !displayRow || !inputElement) {
                return; // Field doesn't exist on this page
            }

            var originalValue = inputElement.value || '';

            editBtn.addEventListener('click', function(e) {
                e.preventDefault();
                displayRow.style.display = 'none';
                editRows.forEach(function(row) {
                    row.style.display = 'flex';
                });
                inputElement.focus();
            });

            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                inputElement.value = originalValue;
                displayRow.style.display = 'flex';
                editRows.forEach(function(row) {
                    row.style.display = 'none';
                });
            });

            saveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                var newValue = inputElement.value.trim();
                
                // Check if value changed
                if (newValue === originalValue) {
                    displayRow.style.display = 'flex';
                    editRows.forEach(function(row) {
                        row.style.display = 'none';
                    });
                    return;
                }

                // Validate URL fields
                if (fieldName.includes('website') || fieldName.includes('instagram') || fieldName.includes('facebook')) {
                    if (newValue && !isValidUrl(newValue)) {
                        alert('Please enter a valid URL (e.g., https://example.com)');
                        return;
                    }
                }

                var formData = new FormData();
                formData.append('action', ajaxAction);
                formData.append(fieldName, newValue);
                formData.append('nonce', MyAccountAjax[nonceKey]);

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                fetch(MyAccountAjax.ajax_url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        originalValue = newValue;
                        if (displayElement) {
                            displayElement.textContent = newValue || 'Not set';
                        }
                        displayRow.style.display = 'flex';
                        editRows.forEach(function(row) {
                            row.style.display = 'none';
                        });
                        showDealerFeedback('Updated successfully', 'success');
                    } else {
                        var errorMsg = (data && data.data) ? data.data : 'Error updating ' + fieldName + '. Please try again.';
                        showDealerFeedback(errorMsg, 'error');
                    }
                })
                .catch(function() {
                    showDealerFeedback('Connection error. Please try again.', 'error');
                })
                .finally(function() {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                });
            });

            // Handle Enter key
            inputElement.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveBtn.click();
                }
            });
        }

        function isValidUrl(string) {
            try {
                var url = new URL(string);
                return url.protocol === 'http:' || url.protocol === 'https:';
            } catch (_) {
                return false;
            }
        }

        function showDealerFeedback(message, type) {
            var feedback = document.getElementById('dealer-info-feedback');
            if (!feedback) return;
            
            feedback.textContent = message;
            feedback.className = 'dealer-info-feedback ' + (type || '');
            
            if (type === 'success') {
                setTimeout(function() {
                    feedback.textContent = '';
                    feedback.className = 'dealer-info-feedback';
                }, 3000);
            }
        }

        // Setup each dealer field
        setupDealerField('dealer_website', 'display-dealer-website', 'dealer-website', 
            'edit-dealer-website-btn', 'save-dealer-website-btn', 'cancel-dealer-website-btn',
            'dealer-website-row', 'dealer-website-edit-row', 'update_dealer_website', 'update_dealer_website_nonce');
        
        setupDealerField('dealer_instagram', 'display-dealer-instagram', 'dealer-instagram',
            'edit-dealer-instagram-btn', 'save-dealer-instagram-btn', 'cancel-dealer-instagram-btn',
            'dealer-instagram-row', 'dealer-instagram-edit-row', 'update_dealer_instagram', 'update_dealer_instagram_nonce');
        
        setupDealerField('dealer_facebook', 'display-dealer-facebook', 'dealer-facebook',
            'edit-dealer-facebook-btn', 'save-dealer-facebook-btn', 'cancel-dealer-facebook-btn',
            'dealer-facebook-row', 'dealer-facebook-edit-row', 'update_dealer_facebook', 'update_dealer_facebook_nonce');
        
        setupDealerField('dealer_maps_url', 'display-dealer-maps-url', 'dealer-maps-url',
            'edit-dealer-maps-url-btn', 'save-dealer-maps-url-btn', 'cancel-dealer-maps-url-btn',
            'dealer-maps-url-row', 'dealer-maps-url-edit-row', 'update_dealer_maps_url', 'update_dealer_maps_url_nonce');
        
        setupDealerField('dealer_maps_address', 'display-dealer-maps-address', 'dealer-maps-address',
            'edit-dealer-maps-address-btn', 'save-dealer-maps-address-btn', 'cancel-dealer-maps-address-btn',
            'dealer-maps-address-row', 'dealer-maps-address-edit-row', 'update_dealer_maps_address', 'update_dealer_maps_address_nonce');
    })();
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
        
        if (isDevelopment) console.log('Send verification clicked, email:', email);
        if (isDevelopment) console.log('Current email:', currentEmail);
        if (isDevelopment) console.log('Is email change:', isEmailChange);
        if (isDevelopment) console.log('Is current email verified:', isCurrentEmailVerified);
        if (isDevelopment) console.log('AJAX URL:', MyAccountAjax.ajax_url);
        if (isDevelopment) console.log('Nonce:', MyAccountAjax.email_verification_nonce);
        
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
                if (isDevelopment) console.log('AJAX Success Response:', response);
                
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
                    if (isDevelopment) console.log('AJAX Error Response:', response.data);
                    alert('❌ Error: ' + response.data + '\n\nPlease try again in a moment.');
                    // DON'T hide the edit form on error - let user try again
                }
            },
            error: function(xhr, status, error) {
                if (isDevelopment) console.log('AJAX Request Failed:', {xhr, status, error});
                if (isDevelopment) console.log('Response Text:', xhr.responseText);
                
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