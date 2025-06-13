<?php
/**
 * Custom User Registration Functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Twilio\Rest\Client; // Needed for registration handling

/**
 * Function to display the custom registration form using a shortcode.
 *
 * @return string HTML output of the registration form.
 */
function custom_registration_form_shortcode() {
    ob_start();
    // Include the form structure (consider moving this file to includes/ too)
    include( get_stylesheet_directory() . '/includes/auth/registration-form.php' ); 
    return ob_get_clean();
}
add_shortcode( 'custom_registration', 'custom_registration_form_shortcode' );

/**
 * Redirect logged-in users away from registration pages
 */
function redirect_logged_in_users_from_registration() {
    // Only run on front-end
    if ( is_admin() ) {
        return;
    }
    
    // Check if user is logged in
    if ( is_user_logged_in() ) {
        global $post;
        
        // Check if current page/post contains the registration shortcode
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'custom_registration' ) ) {
            // Prevent caching of this redirect
            nocache_headers();
            
            wp_redirect( home_url( '/my-account' ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'redirect_logged_in_users_from_registration' );

/**
 * Function to generate a random verification code.
 */
function generate_verification_code($length = 6) {
    $characters = '0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

/**
 * Handles the final custom user registration submission (after phone verification).
 */
function custom_handle_registration() {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'custom_register_user' && isset( $_POST['reg_phone'] ) ) {
        // Verify the nonce for the final submission
        if ( ! isset( $_POST['custom_registration_nonce'] ) || ! wp_verify_nonce( $_POST['custom_registration_nonce'], 'custom_registration_nonce' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'astra-child' ), esc_html__( 'Error', 'astra-child' ), array( 'response' => 403 ) );
        }

        $errors = new WP_Error();

        // Get data from the final form submission
        $first_name      = sanitize_text_field( $_POST['reg_first_name'] );
        $last_name       = sanitize_text_field( $_POST['reg_last_name'] );
        $phone           = sanitize_text_field( $_POST['reg_phone'] ); // Assumed verified
        $password        = $_POST['reg_password'];
        $password_confirm = isset( $_POST['reg_password_confirm'] ) ? $_POST['reg_password_confirm'] : ''; // Get confirm password

        // --- Final Validation ---
        if ( empty( $first_name ) ) {
            $errors->add( 'required', esc_html__( 'Please enter your first name.', 'astra-child' ) );
        }
        if ( empty( $last_name ) ) {
            $errors->add( 'required', esc_html__( 'Please enter your last name.', 'astra-child' ) );
        }
        if ( empty( $phone ) ) { // Should not happen if flow is correct, but check anyway
            $errors->add( 'required', esc_html__( 'Phone number is missing.', 'astra-child' ) );
        }
        if ( strlen( $password ) < 6 ) {
            $errors->add( 'password_length', esc_html__( 'Password must be at least 6 characters long.', 'astra-child' ) );
        }
        // Add password confirmation check
        if ( $password !== $password_confirm ) {
            $errors->add( 'password_mismatch', esc_html__( 'Passwords do not match.', 'astra-child' ) );
        }

        // Check again if phone exists (edge case: verified but registered between steps)
        $user_by_phone = get_users(array(
            'meta_key' => 'phone_number',
            'meta_value' => $phone,
            'number' => 1,
            'count_total' => false,
        ));
        if (!empty($user_by_phone)) {
            $errors->add('phone_exists', esc_html__('This phone number is already registered.', 'astra-child'));
        }

        if ( $errors->get_error_codes() ) {
            // Store errors to display on the same page (will require modification in registration-form.php to show)
            set_transient( 'registration_errors_' . md5($phone), $errors, 30 ); // Use a specific key
            // Redirect back to the registration page (which should ideally retain form values)
            wp_safe_redirect( get_permalink( get_the_ID() ) ); // Consider adding query args if needed
            exit;
        }

        // --- Create the User ---
        $user_type = 'client'; // Assign all new users as client
        $username = sanitize_user( $phone );
        if ( username_exists( $username ) ) {
            $username = sanitize_user( 'user_' . $phone . '_' . wp_rand( 100, 999 ) );
        }
        $email = 'phone_user_' . time() . '@example.com'; // Placeholder email

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            $errors->add( 'registration_failed', $user_id->get_error_message() );
            set_transient( 'registration_errors_' . md5($phone), $errors, 30 );
            wp_safe_redirect( get_permalink( get_the_ID() ) );
            exit;
        } else {
            // Update user meta
            update_user_meta( $user_id, 'first_name', $first_name );
            update_user_meta( $user_id, 'last_name', $last_name );
            update_user_meta( $user_id, 'phone_number', $phone );
            wp_update_user( array( 'ID' => $user_id, 'role' => $user_type ) );

            // Clear any leftover error transient
            delete_transient( 'registration_errors_' . md5($phone) );

            // Redirect to login page with success message
            $redirect_url = add_query_arg( 'registration', 'success', wp_login_url() );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }
    // Note: Removed the global error handling part, needs integration into the form display
}
add_action( 'template_redirect', 'custom_handle_registration' ); // Changed hook to run later 