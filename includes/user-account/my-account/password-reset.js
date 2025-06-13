/**
 * Password Reset JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Password reset functionality loaded');
    
    // Only run password reset scripts if we're on a password reset step
    if (!window.location.search.includes('password_reset_step')) {
        return;
    }
    
    // Get the current step
    const urlParams = new URLSearchParams(window.location.search);
    const currentStep = urlParams.get('password_reset_step');
    
    if (currentStep === 'verify') {
        initializeVerificationForm();
    } else if (currentStep === 'new_password') {
        initializePasswordForm();
    }
    
    /**
     * Initialize verification form functionality
     */
    function initializeVerificationForm() {
        console.log('Initializing verification form');
        
        const verificationInput = document.getElementById('verification-code');
        const verifyBtn = document.querySelector('.verify-code-btn');
        const cancelBtn = document.querySelector('.cancel-reset-btn');
        const resendBtn = document.querySelector('.resend-code-btn');

        if (!verificationInput || !verifyBtn) {
            console.log('Verification form elements not found');
            return;
        }

        // Auto-format verification code input
        verificationInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Verify code
        verifyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const code = verificationInput.value.trim();
            
            if (code.length !== 6) {
                alert('Please enter a 6-digit verification code');
                return;
            }
            
            verifyPasswordResetCode(code);
        });

        // Cancel reset
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = window.location.pathname;
            });
        }

        // Resend code
        if (resendBtn) {
            resendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                resendPasswordResetCode();
            });
        }

        // Handle Enter key
        verificationInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyBtn.click();
            }
        });
    }
    
    /**
     * Initialize password form functionality
     */
    function initializePasswordForm() {
        console.log('Initializing password form');
        
        const newPasswordInput = document.getElementById('new-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const updateBtn = document.querySelector('.update-password-btn');
        const cancelBtn = document.querySelector('.cancel-reset-btn');
        const strengthDiv = document.getElementById('password-strength');
        const requirementsDiv = document.getElementById('password-remaining-reqs');

        if (!newPasswordInput || !confirmPasswordInput || !updateBtn) {
            console.log('Password form elements not found');
            return;
        }

        // Define the same strict requirements as registration
        const requirements = {
            length: { text: '8-16 characters', regex: /.{8,16}/ },
            lowercase: { text: 'At least one lowercase letter', regex: /[a-z]/ },
            uppercase: { text: 'At least one uppercase letter', regex: /[A-Z]/ },
            number: { text: 'At least one number', regex: /[0-9]/ },
            symbol: { text: 'At least one symbol (e.g., !@#$%^&*)', regex: /[!@#$%^&*(),.?":{}|<>\-_=+;\[\]~`]/ }
        };

        // Build initial requirements list HTML
        let initialReqsHtml = '<ul>';
        for (const key in requirements) {
            initialReqsHtml += '<li id="req-' + key + '">' + requirements[key].text + '</li>';
        }
        initialReqsHtml += '</ul>';
        if (requirementsDiv) {
            requirementsDiv.innerHTML = initialReqsHtml;
        }

        // Function to update password strength UI
        function updatePasswordUI(password) {
            let score = 0;
            let requirementsMetCount = 0;

            // Update individual requirements list item classes
            for (const key in requirements) {
                const requirementMet = requirements[key].regex.test(password);
                const reqElement = document.getElementById('req-' + key);
                if (reqElement) {
                    if (requirementMet) {
                        reqElement.classList.add('met');
                        requirementsMetCount++;
                    } else {
                        reqElement.classList.remove('met');
                    }
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
                if (strengthDiv) {
                    strengthDiv.textContent = strengthLabel;
                    strengthDiv.className = 'password-strength';
                }
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
            if (strengthDiv) {
                strengthDiv.textContent = strengthLabel;
                strengthDiv.className = 'password-strength ' + strengthLevel;
            }
        }

        // Password strength checker
        newPasswordInput.addEventListener('input', function() {
            updatePasswordUI(this.value);
            validatePasswords();
        });

        confirmPasswordInput.addEventListener('input', function() {
            validatePasswords();
        });

        // Initial UI update
        updatePasswordUI(newPasswordInput.value);

        // Update password
        updateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            let errors = [];

            // Apply the same strict validation as registration
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
                alert(errors.join('\n'));
                return;
            }
            
            updatePassword(newPassword);
        });

        // Cancel reset
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = window.location.pathname;
            });
        }

        /**
         * Validate password fields
         */
        function validatePasswords() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Reset input styles
            newPasswordInput.classList.remove('valid', 'invalid');
            confirmPasswordInput.classList.remove('valid', 'invalid');
            
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
                newPasswordInput.classList.add('invalid');
                isValid = false;
            } else {
                newPasswordInput.classList.add('valid');
            }
            
            // Check password match
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword && allRequirementsMet) {
                    confirmPasswordInput.classList.add('valid');
                } else {
                    confirmPasswordInput.classList.add('invalid');
                    isValid = false;
                }
            }
            
            updateBtn.disabled = !isValid || !allRequirementsMet || newPassword !== confirmPassword;
        }
    }
    
    /**
     * Verify password reset code
     */
    function verifyPasswordResetCode(code) {
        var formData = new FormData();
        formData.append('action', 'verify_password_reset_code');
        formData.append('code', code);
        formData.append('nonce', PasswordResetAjax.verify_password_reset_nonce);

        fetch(PasswordResetAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = window.location.pathname + '?password_reset_step=new_password';
            } else {
                alert('Error: ' + (data.data || 'Invalid verification code'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error verifying code. Please try again.');
        });
    }
    
    /**
     * Resend password reset code
     */
    function resendPasswordResetCode() {
        var formData = new FormData();
        formData.append('action', 'initiate_password_reset');
        formData.append('nonce', PasswordResetAjax.password_reset_nonce);

        fetch(PasswordResetAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Verification code sent again to your phone number.');
            } else {
                alert('Error: ' + (data.data || 'Unable to resend code'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error resending code. Please try again.');
        });
    }
    
    /**
     * Update password
     */
    function updatePassword(newPassword) {
        const updateBtn = document.querySelector('.update-password-btn');
        if (updateBtn) {
            updateBtn.disabled = true;
            updateBtn.textContent = 'Updating...';
        }
        
        var formData = new FormData();
        formData.append('action', 'update_password_reset');
        formData.append('new_password', newPassword);
        formData.append('nonce', PasswordResetAjax.update_password_reset_nonce);

        fetch(PasswordResetAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = window.location.pathname + '?password_reset_step=success';
            } else {
                alert('Error: ' + (data.data || 'Unable to update password'));
                if (updateBtn) {
                    updateBtn.disabled = false;
                    updateBtn.textContent = 'Update Password';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating password. Please try again.');
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.textContent = 'Update Password';
            }
        });
    }
}); 