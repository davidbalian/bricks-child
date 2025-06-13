<form id="custom-registration-form" method="post">

    <div id="registration-messages"></div> <!-- Area for success/error messages -->

    <!-- Step 1: Phone Input -->
    <div id="step-phone">
        <h2><?php _e( 'Step 1: Enter Phone Number', 'astra-child' ); ?></h2>
        <p>
            <label for="reg_phone_number_display"><?php _e( 'Phone Number', 'astra-child' ); ?>:</label>
            <input type="tel" name="reg_phone_number_display" id="reg_phone_number_display" required> <!-- Input for intl-tel-input -->
        </p>
        <p>
            <button type="button" id="send-otp-button"><?php esc_html_e( 'Send Verification Code', 'astra-child' ); ?></button>
        </p>
    </div>

    <!-- Step 2: OTP Input (Initially Hidden) -->
    <div id="step-otp" style="display: none;">
        <h2><?php _e( 'Step 2: Enter Verification Code', 'astra-child' ); ?></h2>
        <p><?php _e( 'Please enter the code sent to your phone.', 'astra-child' ); ?></p>
        <p>
            <label for="verification_code"><?php _e( 'Verification Code', 'astra-child' ); ?>:</label>
            <input type="text" name="verification_code" id="verification_code" required>
        </p>
        <p>
            <button type="button" id="verify-otp-button"><?php esc_html_e( 'Verify Code & Continue', 'astra-child' ); ?></button>
            <button type="button" id="change-phone-button" style="margin-left: 10px;"><?php esc_html_e( 'Change Phone Number', 'astra-child' ); ?></button>
        </p>
    </div>

    <!-- Step 3: User Details (Initially Hidden) -->
    <div id="step-details" style="display: none;">
        <h2><?php _e( 'Step 3: Complete Registration', 'astra-child' ); ?></h2>
        <p>
            <label for="reg_first_name"><?php _e( 'First Name', 'astra-child' ); ?>:</label>
            <input type="text" name="reg_first_name" id="reg_first_name" required>
        </p>
        <p>
            <label for="reg_last_name"><?php _e( 'Last Name', 'astra-child' ); ?>:</label>
            <input type="text" name="reg_last_name" id="reg_last_name" required>
        </p>
        <p>
            <label for="reg_password"><?php _e( 'Password', 'astra-child' ); ?>:</label>
            <input type="password" name="reg_password" id="reg_password" required aria-describedby="password-strength-text password-remaining-reqs">
            <div id="password-strength-text" aria-live="polite" style="font-size: 0.9em; height: 1.2em;"></div>
            <div id="password-remaining-reqs" style="font-size: 0.9em; margin-top: 3px;">
                <!-- Requirements list will be populated by JS -->
            </div>
        </p>
        <p>
            <label for="reg_password_confirm"><?php _e( 'Confirm Password', 'astra-child' ); ?>:</label>
            <input type="password" name="reg_password_confirm" id="reg_password_confirm" required>
        </p>
        <p>
            <input type="hidden" name="action" value="custom_register_user">
            <input type="hidden" name="reg_phone" id="reg_phone" value=""> <!-- Populated by JS -->
            <?php wp_nonce_field( 'custom_registration_nonce', 'custom_registration_nonce' ); ?>
            <input type="submit" id="complete-registration-button" value="<?php esc_attr_e( 'Complete Registration', 'astra-child' ); ?>">
            </p>
        </div>

</form>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        let verifiedPhoneNumber = '';
        const messagesDiv = $('#registration-messages');
        const stepPhone = $('#step-phone');
        const stepOtp = $('#step-otp');
        const stepDetails = $('#step-details');

        // --- Initialize intl-tel-input --- 
        const phoneInput = document.querySelector("#reg_phone_number_display");
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
            console.error("Registration form: Phone input #reg_phone_number_display not found.");
        }

        // Function to display messages
        function showMessage(message, isError = false) {
            messagesDiv.html('<p class="' + (isError ? 'error' : 'success') + '">' + message + '</p>').show();
        }

        // --- Step 1: Send OTP --- 
        $('#send-otp-button').on('click', function() {
            const button = $(this);
            // const countryCode = $('#reg_country_code').val(); // Removed
            // const phoneNumber = $('#reg_phone_number').val().replace(/\D/g, ''); // Removed
            // const fullPhoneNumber = countryCode + phoneNumber; // Removed
            
            // Get number from intl-tel-input instance
            if (!iti) {
                showMessage('Phone input failed to initialize.', true);
                return;
            }
            const fullPhoneNumber = iti.getNumber(); // Includes country code

            messagesDiv.hide();
            button.prop('disabled', true).text('Sending...');

            // Basic validation from library (optional)
            if (!iti.isValidNumber()) {
                 showMessage('Please enter a valid phone number.', true);
                 button.prop('disabled', false).text('Send Verification Code');
                 return;
            }

            /* Removed basic check as library handles it
            if (!phoneNumber) {
                showMessage('Please enter your phone number.', true);
                button.prop('disabled', false).text('Send Verification Code');
                return;
            }*/

            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'send_otp',
                    phone: fullPhoneNumber,
                    nonce: $('#custom_registration_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message);
                        verifiedPhoneNumber = fullPhoneNumber; // Store number for next steps
                        stepPhone.hide();
                        stepOtp.show();
                    } else {
                        showMessage(response.data.message, true);
                        button.prop('disabled', false).text('Send Verification Code');
                    }
                },
                error: function() {
                    showMessage('An error occurred sending the code. Please try again.', true);
                    button.prop('disabled', false).text('Send Verification Code');
                }
            });
        });

        // --- Step 2: Verify OTP --- 
        $('#verify-otp-button').on('click', function() {
            const button = $(this);
            const otp = $('#verification_code').val();
            messagesDiv.hide();
            button.prop('disabled', true).text('Verifying...');
            $('#change-phone-button').prop('disabled', true);

            if (!otp) {
                showMessage('Please enter the verification code.', true);
                button.prop('disabled', false).text('Verify Code & Continue');
                $('#change-phone-button').prop('disabled', false);
                return;
            }

            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'verify_otp',
                    phone: verifiedPhoneNumber,
                    otp: otp,
                    nonce: $('#custom_registration_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message);
                        $('#reg_phone').val(verifiedPhoneNumber); // Set hidden field
                        stepOtp.hide();
                        stepDetails.show();
                    } else {
                        showMessage(response.data.message, true);
                        button.prop('disabled', false).text('Verify Code & Continue');
                        $('#change-phone-button').prop('disabled', false);
                    }
                },
                error: function() {
                    showMessage('An error occurred verifying the code. Please try again.', true);
                    button.prop('disabled', false).text('Verify Code & Continue');
                    $('#change-phone-button').prop('disabled', false);
                }
            });
        });

        // --- Change Phone Button ---
        $('#change-phone-button').on('click', function() {
            messagesDiv.hide();
            stepOtp.hide();
            stepPhone.show();
            $('#send-otp-button').prop('disabled', false).text('Send Verification Code');
            $('#reg_phone_number_display').val(''); // Clear previous number
            $('#verification_code').val(''); // Clear OTP field
            verifiedPhoneNumber = '';
        });

        // --- Display any errors from final submission redirect ---
        <?php
        // We need a way to get errors passed back from the PHP redirect.
        // Using a transient is one way, checking it here.
        $final_submission_errors = false; // Assume no errors
        // A transient key needs to be set consistently in custom_handle_registration on error
        // Example key: 'registration_errors_' . md5($_POST['reg_phone'])
        // This part needs refinement depending on how you pass errors back from the redirect
        /*
        $error_transient_key = 'some_key_maybe_based_on_nonce_or_temp_id'; 
        $errors = get_transient($error_transient_key);
        if ($errors instanceof WP_Error && $errors->get_error_codes()) {
            $error_messages = $errors->get_error_messages();
            echo 'showMessage("' . esc_js(implode("<br>", $error_messages)) . '", true);';
            delete_transient($error_transient_key);
            // Decide which step to show if there was a final submission error
            echo 'stepPhone.hide(); stepOtp.hide(); stepDetails.show();'; 
        }
        */
        ?>

        // --- New Refactored Password Strength Logic --- 
        const pwdInput = $('#reg_password');
        const strengthText = $('#password-strength-text');
        const requirementsDiv = $('#password-remaining-reqs'); // Target the div

        const requirements = {
            length: { text: '<?php esc_html_e( "8-16 characters", "astra-child" ); ?>', regex: /.{8,16}/ },
            lowercase: { text: '<?php esc_html_e( "At least one lowercase letter", "astra-child" ); ?>', regex: /[a-z]/ },
            uppercase: { text: '<?php esc_html_e( "At least one uppercase letter", "astra-child" ); ?>', regex: /[A-Z]/ },
            number: { text: '<?php esc_html_e( "At least one number", "astra-child" ); ?>', regex: /[0-9]/ },
            symbol: { text: '<?php esc_html_e( "At least one symbol (e.g., !@#$%^&*)", "astra-child" ); ?>', regex: /[!@#$%^&*(),.?":{}|<>\-_=+;\[\]~`]/ }
        };

        // Function to update password strength UI
        function updatePasswordUI(password) {
            let score = 0;
            let requirementsMetCount = 0; // Separate count for met requirements

            // Update individual requirements list item classes
            for (const key in requirements) {
                const requirementMet = requirements[key].regex.test(password);
                const reqElement = $('#req-' + key); // Find the specific LI
                if (requirementMet) {
                    reqElement.addClass('met');
                    requirementsMetCount++;
                } else {
                    reqElement.removeClass('met');
                }
            }
            score = requirementsMetCount; // Use met count as base score

            // Determine strength level
            let strengthLevel = '';
            let strengthLabel = '';

            // Reset if empty
            if (password.length === 0) {
                strengthLevel = '';
                strengthLabel = '';
                strengthText.text(strengthLabel).removeClass('weak medium strong');
                return; // Exit early if password is empty
            } 
            // Check levels - require length met for medium/strong
            else if (!requirements.length.regex.test(password) || score <= 2) { 
                strengthLevel = 'weak';
                strengthLabel = '<?php esc_html_e( "Weak", "astra-child" ); ?>';
            } else if (score <= 4) {
                strengthLevel = 'medium';
                strengthLabel = '<?php esc_html_e( "Moderate", "astra-child" ); ?>';
            } else { // Score is 5 and length is met
                strengthLevel = 'strong';
                strengthLabel = '<?php esc_html_e( "Safe", "astra-child" ); ?>';
            }

            // Update indicator and text classes/content
            strengthText.text(strengthLabel).removeClass('weak medium strong').addClass(strengthLevel);
            
            // Keep the full list visible, only classes change
            // No need to update requirementsDiv HTML here anymore
        }

        // Build initial requirements list HTML on page load
        let initialReqsHtml = '<ul>';
        for (const key in requirements) {
            initialReqsHtml += '<li id="req-' + key + '">' + requirements[key].text + '</li>';
        }
        initialReqsHtml += '</ul>';
        requirementsDiv.html(initialReqsHtml);

        // Initial UI update based on current password value (e.g., if field is pre-filled)
        updatePasswordUI(pwdInput.val());

        // Attach listener for subsequent updates
        pwdInput.on('input', function() {
            updatePasswordUI($(this).val());
        });

        // --- Step 3: Final Form Submission Validation ---
        $('#custom-registration-form').on('submit', function(event) {
            // This validation runs only when the final submit button is clicked,
            // after the OTP has been verified and #step-details is visible.
            if ($('#step-details').is(':visible')) {
                messagesDiv.hide(); // Hide previous messages
                const password = $('#reg_password').val();
                const confirmPassword = $('#reg_password_confirm').val();
                const firstName = $('#reg_first_name').val();
                const lastName = $('#reg_last_name').val();
                let errors = []; // Array to hold validation errors

                // Password Length Check (8-16 characters)
                if (password.length < 8 || password.length > 16) {
                    errors.push('Password must be between 8 and 16 characters long.');
                }

                // Password Complexity Checks
                if (!/[a-z]/.test(password)) {
                    errors.push('Password must contain at least one lowercase letter.');
                }
                if (!/[A-Z]/.test(password)) {
                    errors.push('Password must contain at least one uppercase letter.');
                }
                if (!/[0-9]/.test(password)) {
                    errors.push('Password must contain at least one number.');
                }
                // Define allowed symbols or use a general non-alphanumeric check
                // Using a common set here: !@#$%^&*(),.?":{}|<>
                if (!/[!@#$%^&*(),.?":{}|<>\-_=+;\[\]~`]/ .test(password)) {
                     errors.push('Password must contain at least one symbol (e.g., !@#$%^&*).');
                     // Alternative: Check for any non-alphanumeric character (excluding space)
                     // if (!/[^a-zA-Z0-9\s]/.test(password)) { ... }
                }

                // Password match check (only if length/complexity seem okay, otherwise redundant)
                if (password.length >= 8 && password.length <= 16 && password !== confirmPassword) {
                    errors.push('Passwords do not match. Please re-enter.');
                }

                // First Name validation (letters, spaces, hyphen only)
                const nameRegex = /^[a-zA-Z -]+$/;
                if (!nameRegex.test(firstName)) {
                    errors.push('First Name can only contain letters, spaces, and hyphens (-).');
                } else if (firstName.length < 1 || firstName.length > 50) {
                     errors.push('First Name must be between 1 and 50 characters.');
                }

                // Last Name validation (letters, spaces, hyphen only)
                if (!nameRegex.test(lastName)) {
                    errors.push('Last Name can only contain letters, spaces, and hyphens (-).');
                } else if (lastName.length < 1 || lastName.length > 50) {
                    errors.push('Last Name must be between 1 and 50 characters.');
                }

                // Check if any errors occurred
                if (errors.length > 0) {
                    showMessage(errors.join('<br>'), true); // Show all errors
                    event.preventDefault(); // Prevent form submission
                }
                // Add any other client-side validation for step 3 here if needed
            }
            // If validation passes, the form submits normally to custom_handle_registration
        });

    });
</script>