/**
 * Forgot Password JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    console.log('Forgot password functionality loaded');
    
    // Variables to store state
    let verifiedPhoneNumber = '';
    let userIdForReset = '';
    
    // DOM elements
    const messagesDiv = $('#forgot-password-messages');
    const stepPhone = $('#step-phone');
    const stepOtp = $('#step-otp');
    const stepPassword = $('#step-password');
    const stepSuccess = $('#step-success');
    
    // --- Initialize intl-tel-input --- 
    const phoneInput = document.querySelector("#forgot-phone-number-display");
    let iti = null; // Variable to store the instance
    if (phoneInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: "auto",
            geoIpLookup: function(callback) {
                fetch('https://ipinfo.io/json', { headers: { 'Accept': 'application/json' } })
                .then(response => response.json())
                .then(data => callback(data.country))
                .catch(() => callback('cy')); // Default to Cyprus on error
            },
            separateDialCode: true,
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js" 
        });
    } else {
        console.error("Forgot password form: Phone input #forgot-phone-number-display not found.");
    }
    
    // Password requirements (same as registration)
    const requirements = {
        length: { text: '8-16 characters', regex: /.{8,16}/ },
        lowercase: { text: 'At least one lowercase letter', regex: /[a-z]/ },
        uppercase: { text: 'At least one uppercase letter', regex: /[A-Z]/ },
        number: { text: 'At least one number', regex: /[0-9]/ },
        symbol: { text: 'At least one symbol (e.g., !@#$%^&*)', regex: /[!@#$%^&*(),.?":{}|<>\-_=+;\[\]~`]/ }
    };

    // Function to display messages
    function showMessage(message, isError = false) {
        messagesDiv.html('<div class="message ' + (isError ? 'error' : 'success') + '">' + message + '</div>').show();
        // Scroll to message
        $('html, body').animate({
            scrollTop: messagesDiv.offset().top - 100
        }, 300);
    }

    // Function to hide all steps
    function hideAllSteps() {
        stepPhone.hide();
        stepOtp.hide();
        stepPassword.hide();
        stepSuccess.hide();
    }

    // --- Step 1: Send OTP to Phone Number ---
    $('#send-forgot-otp-button').on('click', function() {
        const button = $(this);
        
        messagesDiv.hide();
        button.prop('disabled', true).text('Sending...');

        // Get number from intl-tel-input instance
        if (!iti) {
            showMessage('Phone input failed to initialize.', true);
            button.prop('disabled', false).text('Send Verification Code');
            return;
        }
        const fullPhoneNumber = iti.getNumber(); // Includes country code

        // Basic validation from library
        if (!iti.isValidNumber()) {
            showMessage('Please enter a valid phone number.', true);
            button.prop('disabled', false).text('Send Verification Code');
            return;
        }

        $.ajax({
            url: ForgotPasswordAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'send_forgot_password_otp',
                phone: fullPhoneNumber,
                nonce: ForgotPasswordAjax.send_otp_nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message);
                    verifiedPhoneNumber = fullPhoneNumber;
                    hideAllSteps();
                    stepOtp.show();
                } else {
                    showMessage(response.data.message || 'Failed to send verification code.', true);
                    button.prop('disabled', false).text('Send Verification Code');
                }
            },
            error: function() {
                showMessage('An error occurred. Please try again.', true);
                button.prop('disabled', false).text('Send Verification Code');
            }
        });
    });

    // --- Step 2: Verify OTP ---
    $('#verify-forgot-otp-button').on('click', function() {
        const button = $(this);
        const otp = $('#forgot-verification-code').val().trim();
        
        messagesDiv.hide();
        button.prop('disabled', true).text('Verifying...');
        $('#change-forgot-phone-button, #resend-forgot-otp-button').prop('disabled', true);

        if (!otp || otp.length !== 6) {
            showMessage('Please enter a valid 6-digit verification code.', true);
            button.prop('disabled', false).text('Verify Code');
            $('#change-forgot-phone-button, #resend-forgot-otp-button').prop('disabled', false);
            return;
        }

        $.ajax({
            url: ForgotPasswordAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_forgot_password_otp',
                phone: verifiedPhoneNumber,
                otp: otp,
                nonce: ForgotPasswordAjax.verify_otp_nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message);
                    userIdForReset = response.data.user_id;
                    $('#user-id-for-reset').val(userIdForReset);
                    $('#verified-phone-number').val(verifiedPhoneNumber);
                    
                    // Initialize password requirements display
                    initializePasswordRequirements();
                    
                    hideAllSteps();
                    stepPassword.show();
                } else {
                    showMessage(response.data.message || 'Invalid verification code.', true);
                    button.prop('disabled', false).text('Verify Code');
                    $('#change-forgot-phone-button, #resend-forgot-otp-button').prop('disabled', false);
                }
            },
            error: function() {
                showMessage('An error occurred verifying the code. Please try again.', true);
                button.prop('disabled', false).text('Verify Code');
                $('#change-forgot-phone-button, #resend-forgot-otp-button').prop('disabled', false);
            }
        });
    });

    // --- Change Phone Number ---
    $('#change-forgot-phone-button').on('click', function() {
        messagesDiv.hide();
        
        // Reset the intl-tel-input field
        if (iti) {
            iti.setNumber('');
        }
        
        $('#forgot-verification-code').val('');
        verifiedPhoneNumber = '';
        hideAllSteps();
        stepPhone.show();
        $('#send-forgot-otp-button').prop('disabled', false).text('Send Verification Code');
    });

    // --- Resend OTP ---
    $('#resend-forgot-otp-button').on('click', function() {
        const button = $(this);
        
        messagesDiv.hide();
        button.prop('disabled', true).text('Resending...');

        $.ajax({
            url: ForgotPasswordAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'send_forgot_password_otp',
                phone: verifiedPhoneNumber,
                nonce: ForgotPasswordAjax.send_otp_nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Verification code sent again to your phone number.');
                } else {
                    showMessage(response.data.message || 'Failed to resend code.', true);
                }
                button.prop('disabled', false).text('Resend Code');
            },
            error: function() {
                showMessage('An error occurred. Please try again.', true);
                button.prop('disabled', false).text('Resend Code');
            }
        });
    });

    // --- Initialize Password Requirements ---
    function initializePasswordRequirements() {
        const passwordInput = $('#forgot-new-password');
        const confirmPasswordInput = $('#forgot-confirm-password');
        const strengthDiv = $('#forgot-password-strength');
        const requirementsDiv = $('#forgot-password-remaining-reqs');
        const updateButton = $('#update-forgot-password-button');

        // Build initial requirements list HTML
        let initialReqsHtml = '<ul>';
        for (const key in requirements) {
            initialReqsHtml += '<li id="forgot-req-' + key + '">' + requirements[key].text + '</li>';
        }
        initialReqsHtml += '</ul>';
        requirementsDiv.html(initialReqsHtml);

        // Function to update password strength UI
        function updatePasswordUI(password) {
            let score = 0;
            let requirementsMetCount = 0;

            // Update individual requirements list item classes
            for (const key in requirements) {
                const requirementMet = requirements[key].regex.test(password);
                const reqElement = $('#forgot-req-' + key);
                if (requirementMet) {
                    reqElement.addClass('met');
                    requirementsMetCount++;
                } else {
                    reqElement.removeClass('met');
                }
            }
            score = requirementsMetCount;

            // Determine strength level
            let strengthLevel = '';
            let strengthLabel = '';

            // Reset if empty
            if (password.length === 0) {
                strengthLevel = '';
                strengthLabel = '';
                strengthDiv.text(strengthLabel).removeClass('strength-weak strength-medium strength-strong');
                return;
            } 
            // Check levels - require length met for medium/strong
            else if (!requirements.length.regex.test(password) || score <= 2) { 
                strengthLevel = 'strength-weak';
                strengthLabel = '⚠️ Weak';
            } else if (score <= 4) {
                strengthLevel = 'strength-medium';
                strengthLabel = '⚡ Moderate';
            } else { // Score is 5 and length is met
                strengthLevel = 'strength-strong';
                strengthLabel = '✅ Safe';
            }

            // Update indicator and text classes/content
            strengthDiv.text(strengthLabel).removeClass('strength-weak strength-medium strength-strong').addClass(strengthLevel);
        }

        // Function to validate passwords
        function validatePasswords() {
            const newPassword = passwordInput.val();
            const confirmPassword = confirmPasswordInput.val();
            
            // Reset input styles
            passwordInput.removeClass('valid invalid');
            confirmPasswordInput.removeClass('valid invalid');
            
            let isValid = true;
            
            // Check all requirements are met
            let allRequirementsMet = true;
            for (const key in requirements) {
                if (!requirements[key].regex.test(newPassword)) {
                    allRequirementsMet = false;
                    break;
                }
            }
            
            // Check password strength
            if (!allRequirementsMet) {
                passwordInput.addClass('invalid');
                isValid = false;
            } else {
                passwordInput.addClass('valid');
            }
            
            // Check password match
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword && allRequirementsMet) {
                    confirmPasswordInput.addClass('valid');
                } else {
                    confirmPasswordInput.addClass('invalid');
                    isValid = false;
                }
            }
            
            updateButton.prop('disabled', !isValid || !allRequirementsMet || newPassword !== confirmPassword);
        }

        // Attach event listeners
        passwordInput.on('input', function() {
            updatePasswordUI($(this).val());
            validatePasswords();
        });

        confirmPasswordInput.on('input', function() {
            validatePasswords();
        });

        // Initial UI update
        updatePasswordUI(passwordInput.val());
        validatePasswords();
    }

    // --- Step 3: Update Password ---
    $('#update-forgot-password-button').on('click', function() {
        const button = $(this);
        const newPassword = $('#forgot-new-password').val();
        const confirmPassword = $('#forgot-confirm-password').val();
        
        messagesDiv.hide();
        button.prop('disabled', true).text('Updating...');

        // Client-side validation (same as registration)
        let errors = [];

        // Password Length Check (8-16 characters)
        if (newPassword.length < 8 || newPassword.length > 16) {
            errors.push('Password must be between 8 and 16 characters long.');
        }

        // Password Complexity Checks
        if (!/[a-z]/.test(newPassword)) {
            errors.push('Password must contain at least one lowercase letter.');
        }
        if (!/[A-Z]/.test(newPassword)) {
            errors.push('Password must contain at least one uppercase letter.');
        }
        if (!/[0-9]/.test(newPassword)) {
            errors.push('Password must contain at least one number.');
        }
        if (!/[!@#$%^&*(),.?":{}|<>\-_=+;\[\]~`]/.test(newPassword)) {
            errors.push('Password must contain at least one symbol (e.g., !@#$%^&*).');
        }

        // Password match check
        if (newPassword !== confirmPassword) {
            errors.push('Passwords do not match. Please re-enter.');
        }

        // Check if any errors occurred
        if (errors.length > 0) {
            showMessage(errors.join('<br>'), true);
            button.prop('disabled', false).text('Update Password');
            return;
        }

        $.ajax({
            url: ForgotPasswordAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_forgot_password',
                phone: verifiedPhoneNumber,
                new_password: newPassword,
                nonce: ForgotPasswordAjax.update_password_nonce
            },
            success: function(response) {
                if (response.success) {
                    hideAllSteps();
                    stepSuccess.show();
                    messagesDiv.hide();
                } else {
                    showMessage(response.data.message || 'Failed to update password.', true);
                    button.prop('disabled', false).text('Update Password');
                }
            },
            error: function() {
                showMessage('An error occurred updating the password. Please try again.', true);
                button.prop('disabled', false).text('Update Password');
            }
        });
    });

    // --- Cancel Password Reset ---
    $('#cancel-forgot-password-button').on('click', function() {
        if (confirm('Are you sure you want to cancel the password reset?')) {
            window.location.href = ForgotPasswordAjax.login_url || '/wp-login.php';
        }
    });

    // --- Auto-format verification code input ---
    $('#forgot-verification-code').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // --- Handle Enter key for forms ---
    $('#forgot-phone-number').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#send-forgot-otp-button').click();
        }
    });

    $('#forgot-verification-code').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#verify-forgot-otp-button').click();
        }
    });

    $('#forgot-new-password, #forgot-confirm-password').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            if (!$('#update-forgot-password-button').prop('disabled')) {
                $('#update-forgot-password-button').click();
            }
        }
    });
}); 