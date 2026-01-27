<!-- <div style="text-align: center; padding: 40px 20px; max-width: 600px; margin: 0 auto;">
    <h2 style="margin-bottom: 20px;">Registration is currently under maintenance.</h2>
    <p style="font-size: 16px; margin-bottom: 10px;">To register, or for other questions, contact:</p>
    <p style="font-size: 20px; font-weight: bold;"><a href="tel:+35797839738">+357 97839738</a></p>
</div> -->

<!-- Cloudflare bot protection -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<form id="custom-registration-form" method="post">

    <div id="registration-messages"></div>

    <div id="step-phone">
        <h2><?php _e( 'Step 1: Enter Phone Number', 'bricks-child' ); ?></h2>
        <p>
            <label for="reg_phone_number_display"><?php _e( 'Phone Number', 'bricks-child' ); ?>:</label>
            <input type="tel" name="reg_phone_number_display" id="reg_phone_number_display" required>
        </p>

        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(TURNSTILE_SITE_KEY); ?>"></div>


        <p class="button-stack">
            <button type="button" id="send-otp-button" class="btn btn-primary"><?php esc_html_e( 'Send Verification Code', 'bricks-child' ); ?></button>
        </p>
    </div>

    <div id="step-otp" style="display: none;">
        <h2><?php _e( 'Step 2: Enter Verification Code', 'bricks-child' ); ?></h2>
        <p><?php _e( 'Please enter the code sent to your phone.', 'bricks-child' ); ?></p>
        <p>
            <label for="verification_code"><?php _e( 'Verification Code', 'bricks-child' ); ?>:</label>
            <input type="text" name="verification_code" id="verification_code" required>
        </p>
        <p class="button-stack">
            <button type="button" id="verify-otp-button" class="btn btn-primary"><?php esc_html_e( 'Verify Code & Continue', 'bricks-child' ); ?></button>
            <button type="button" id="change-phone-button" class="btn btn-secondary"><?php esc_html_e( 'Change Phone Number', 'bricks-child' ); ?></button>
        </p>
    </div>

    <div id="step-details" style="display: none;">
        <h2><?php _e( 'Step 3: Complete Registration', 'bricks-child' ); ?></h2>
        <p>
            <label for="reg_first_name"><?php _e( 'First Name', 'bricks-child' ); ?>:</label>
            <input type="text" name="reg_first_name" id="reg_first_name" required>
        </p>
        <p>
            <label for="reg_last_name"><?php _e( 'Last Name', 'bricks-child' ); ?>:</label>
            <input type="text" name="reg_last_name" id="reg_last_name" required>
        </p>
        <p>
            <label for="reg_password"><?php _e( 'Password', 'bricks-child' ); ?>:</label>
            <input type="password" name="reg_password" id="reg_password" required aria-describedby="password-strength-text password-remaining-reqs">
            <div id="password-strength-text" aria-live="polite" style="font-size: 0.9em; height: 1.2em;"></div>
            <div id="password-remaining-reqs" style="font-size: 0.9em; margin-top: 3px;">
            </div>
        </p>
        <p>
            <label for="reg_password_confirm"><?php _e( 'Confirm Password', 'bricks-child' ); ?>:</label>
            <input type="password" name="reg_password_confirm" id="reg_password_confirm" required>
        </p>
        <p class="button-stack">
            <input type="hidden" name="action" value="custom_register_user">
            <input type="hidden" name="reg_phone" id="reg_phone" value="">
            <?php wp_nonce_field( 'custom_registration_nonce', 'custom_registration_nonce' ); ?>
            <button type="submit" id="complete-registration-button" class="btn btn-primary"><?php esc_attr_e( 'Complete Registration', 'bricks-child' ); ?></button>
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

        const phoneInput = document.querySelector("#reg_phone_number_display");
        let iti = null;
        if (phoneInput) {
             iti = window.intlTelInput(phoneInput, {
                initialCountry: "auto",
                geoIpLookup: function(callback) {
                    fetch('https://ipinfo.io/json', { headers: { 'Accept': 'application/json' } })
                    .then(response => response.json())
                    .then(data => callback(data.country))
                    .catch(() => callback('cy'));
                },
                separateDialCode: true,
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js" 
            });
        } else {
            if (isDevelopment) console.error("Registration form: Phone input #reg_phone_number_display not found.");
        }

        function showMessage(message, isError = false) {
            messagesDiv.html('<p class="' + (isError ? 'error' : 'success') + '">' + message + '</p>').show();
            
            if (isError) {
                $('html, body').animate({
                    scrollTop: 0
                }, 500);
            }
        }

        $('#send-otp-button').on('click', function() {
            const button = $(this);
            
            if (!iti) {
                showMessage('Phone input failed to initialize.', true);
                return;
            }
            const fullPhoneNumber = iti.getNumber();

            messagesDiv.hide();
            button.prop('disabled', true).text('Sending...');

            if (!iti.isValidNumber()) {
                 showMessage('Please enter a valid phone number.', true);
                 button.prop('disabled', false).text('Send Verification Code');
                 return;
            }

            //JUST FOR TEST UNCOMMENT LATER
            // const tsToken =
            //     document.querySelector('input[name="cf-turnstile-response"]')?.value || '';

            if (!tsToken) {
                showMessage('Please complete the verification and try again.', true);
                button.prop('disabled', false).text('Send Verification Code');
                if (window.turnstile) turnstile.reset(); // refresh token
                return;
            }


            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'send_otp',
                    phone: fullPhoneNumber,
                    turnstile_token: tsToken,
                    nonce: $('#custom_registration_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message);
                        verifiedPhoneNumber = fullPhoneNumber;
                        stepPhone.hide();
                        stepOtp.show();
                    } else {
                        showMessage(response.data.message, true);
                        if (window.turnstile) turnstile.reset();
                        button.prop('disabled', false).text('Send Verification Code');
                    }
                },
                error: function() {
                    showMessage('An error occurred sending the code. Please try again.', true);
                    if (window.turnstile) turnstile.reset();
                    button.prop('disabled', false).text('Send Verification Code');
                }
            });
        });

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
                        $('#reg_phone').val(verifiedPhoneNumber);
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

        $('#change-phone-button').on('click', function() {
            if (window.turnstile) turnstile.reset();
            messagesDiv.hide();
            stepOtp.hide();
            stepPhone.show();
            $('#send-otp-button').prop('disabled', false).text('Send Verification Code');
            $('#reg_phone_number_display').val('');
            $('#verification_code').val('');
            verifiedPhoneNumber = '';
        });

        <?php
        $final_submission_errors = false;
        ?>

        const pwdInput = $('#reg_password');
        const strengthText = $('#password-strength-text');
        const requirementsDiv = $('#password-remaining-reqs');

        const requirements = {
            length: { text: '<?php esc_html_e( "8-25 characters", "bricks-child" ); ?>', regex: /.{8,25}/ },
            lowercase: { text: '<?php esc_html_e( "At least one lowercase letter", "bricks-child" ); ?>', regex: /[a-z]/ },
            uppercase: { text: '<?php esc_html_e( "At least one uppercase letter", "bricks-child" ); ?>', regex: /[A-Z]/ },
            number: { text: '<?php esc_html_e( "At least one number", "bricks-child" ); ?>', regex: /[0-9]/ },
            symbol: { text: '<?php esc_html_e( "At least one symbol (iOS: hyphen, Regular: !@#$%^&*)", "bricks-child" ); ?>', regex: /[!@#$%^&*(),.?":{}|<>\-_=+;\[\]~`]/ }
        };

        function updatePasswordUI(password) {
            let score = 0;
            let requirementsMetCount = 0;

            for (const key in requirements) {
                const requirementMet = requirements[key].regex.test(password);
                const reqElement = $('#req-' + key);
                if (requirementMet) {
                    reqElement.addClass('met');
                    requirementsMetCount++;
                } else {
                    reqElement.removeClass('met');
                }
            }
            score = requirementsMetCount;

            let strengthLevel = '';
            let strengthLabel = '';

            if (password.length === 0) {
                strengthLevel = '';
                strengthLabel = '';
                strengthText.text(strengthLabel).removeClass('weak medium strong');
                return;
            } 
            else if (!requirements.length.regex.test(password) || score <= 2) { 
                strengthLevel = 'weak';
                strengthLabel = '<?php esc_html_e( "Weak", "bricks-child" ); ?>';
            } else if (score <= 4) {
                strengthLevel = 'medium';
                strengthLabel = '<?php esc_html_e( "Moderate", "bricks-child" ); ?>';
            } else {
                strengthLevel = 'strong';
                strengthLabel = '<?php esc_html_e( "Safe", "bricks-child" ); ?>';
            }

            strengthText.text(strengthLabel).removeClass('weak medium strong').addClass(strengthLevel);
        }

        let initialReqsHtml = '<ul>';
        for (const key in requirements) {
            initialReqsHtml += '<li id="req-' + key + '">' + requirements[key].text + '</li>';
        }
        initialReqsHtml += '</ul>';
        requirementsDiv.html(initialReqsHtml);

        updatePasswordUI(pwdInput.val());

        pwdInput.on('input', function() {
            updatePasswordUI($(this).val());
        });

        $('#custom-registration-form').on('submit', function(event) {
            if ($('#step-details').is(':visible')) {
                messagesDiv.hide();
                const password = $('#reg_password').val();
                const confirmPassword = $('#reg_password_confirm').val();
                const firstName = $('#reg_first_name').val();
                const lastName = $('#reg_last_name').val();
                let errors = [];

                if (password.length < 8 || password.length > 25) {
                    errors.push('Password must be between 8 and 25 characters long.');
                }

                if (!/[a-z]/.test(password)) {
                    errors.push('Password must contain at least one lowercase letter.');
                }
                if (!/[A-Z]/.test(password)) {
                    errors.push('Password must contain at least one uppercase letter.');
                }
                if (!/[0-9]/.test(password)) {
                    errors.push('Password must contain at least one number.');
                }
                if (!/[!@#$%^&*(),.?":{}|<>\-_=+;\[\]~`]/ .test(password)) {
                     errors.push('Password must contain at least one symbol (e.g., !@#$%^&*).');
                }

                if (password.length >= 8 && password.length <= 25 && password !== confirmPassword) {
                    errors.push('Passwords do not match. Please re-enter.');
                }

                const nameRegex = /^[a-zA-Z -]+$/;
                if (!nameRegex.test(firstName)) {
                    errors.push('First Name can only contain letters, spaces, and hyphens (-).');
                } else if (firstName.length < 1 || firstName.length > 50) {
                     errors.push('First Name must be between 1 and 50 characters.');
                }

                if (!nameRegex.test(lastName)) {
                    errors.push('Last Name can only contain letters, spaces, and hyphens (-).');
                } else if (lastName.length < 1 || lastName.length > 50) {
                    errors.push('Last Name must be between 1 and 50 characters.');
                }

                if (errors.length > 0) {
                    showMessage(errors.join('<br>'), true);
                    event.preventDefault();
                }
            }
        });

    });
</script>