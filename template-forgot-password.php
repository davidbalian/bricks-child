<?php
/**
 * Template Name: Forgot Password
 * Custom template for forgot password functionality
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Redirect logged-in users to their account page
if ( is_user_logged_in() ) {
	// Prevent caching of this redirect
	nocache_headers();
	
	wp_redirect( home_url( '/my-account' ) );
	exit;
}

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        
        <?php
        // Include the forgot password form
        include( get_stylesheet_directory() . '/includes/auth/forgot-password.php' );
        ?>
        
    </main>
</div>

<?php
// Enqueue the forgot password scripts and styles
wp_enqueue_script('forgot-password-js', get_stylesheet_directory_uri() . '/includes/auth/forgot-password.js', array('jquery'), '1.0.0', true);
wp_enqueue_style('forgot-password-css', get_stylesheet_directory_uri() . '/includes/auth/forgot-password.css', array(), '1.0.0');

// Localize script for AJAX
wp_localize_script('forgot-password-js', 'ForgotPasswordAjax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'send_otp_nonce' => wp_create_nonce('send_forgot_password_otp_nonce'),
    'verify_otp_nonce' => wp_create_nonce('verify_forgot_password_otp_nonce'),
    'update_password_nonce' => wp_create_nonce('update_forgot_password_nonce')
));

get_footer();
?> 