<?php
/**
 * Login/Logout Handling, URL Modifications, and Phone Authentication.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Redirect users based on their role after login.
 */
function custom_login_redirect( $redirect_to, $request, $user ) {
    // Check if the user is logged in
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        // Check if the user is an administrator
        if ( in_array( 'administrator', $user->roles ) ) {
            // Redirect administrators to the dashboard
            return admin_url();
        } else {
            // Redirect all other users to the homepage
            return home_url();
        }
    } else {
        // If there's an issue with the user object, use the original redirect
        return $redirect_to;
    }
}
add_filter( 'login_redirect', 'custom_login_redirect', 10, 3 );

/**
 * Redirect users to the homepage after logout.
 */
function custom_logout_redirect( $redirect_to, $requested_redirect_to, $logout_url, $blog_id ) {
    return home_url();
}
add_filter( 'wp_logout_redirect', 'custom_logout_redirect', 10, 4 );

/**
 * Replace the default login URL.
 */
function custom_login_page_url( $login_url, $redirect, $force_reauth ) {
    $custom_login_page_id = get_page_by_path( 'signin' )->ID;
    if ( $custom_login_page_id ) {
        $login_url = get_permalink( $custom_login_page_id );
    }
    return $login_url;
}
add_filter( 'login_url', 'custom_login_page_url', 10, 3 );

/**
 * Redirect to the custom login page if someone tries to access wp-login.php directly.
 * Also redirect logged-in users to their account page.
 */
function redirect_login_page() {
    // First check: If user is already logged in, redirect to my-account
    if (is_user_logged_in()) {
        // Check if we're on login-related pages
        $page_viewed = isset($_SERVER['REQUEST_URI']) ? basename($_SERVER['REQUEST_URI']) : '';
        $is_login_page = ($page_viewed == 'wp-login.php') || 
                        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/signin') !== false);
        
        if ($is_login_page) {
            wp_redirect(home_url('/my-account'));
            exit;
        }
    }
    
    // Second check: Redirect wp-login.php to custom signin page for non-logged-in users
    $page_viewed = isset($_SERVER['REQUEST_URI']) ? basename($_SERVER['REQUEST_URI']) : '';
    if ( $page_viewed == 'wp-login.php' && $_SERVER['REQUEST_METHOD'] == 'GET' ) {
        $custom_login_page_id = get_page_by_path( 'signin' )->ID;
        if ( $custom_login_page_id ) {
            wp_redirect( get_permalink( $custom_login_page_id ) );
            exit;
        }
    }
}
add_action('init','redirect_login_page');

/**
 * Allow authentication via phone number.
 *
 * @param WP_User|null|WP_Error $user     WP_User object or null if not found.
 * @param string                $username The username or email address or phone number.
 * @param string                $password The password.
 * @return WP_User|WP_Error WP_User object if login is successful, WP_Error otherwise.
 */
function allow_phone_number_login( $user, $username, $password ) {
    // If already authenticated by a previous filter, return the user.
    if ( $user instanceof WP_User ) {
        return $user;
    }
    
    // Remove the email check as we are only allowing phone numbers now
    // if ( $user instanceof WP_User || is_email( $username ) ) {
    //    return $user;
    // }

    // Check if the $username looks like a phone number (starts with +)
    // This $username comes from the hidden 'log' field populated by our JS
    if ( preg_match( '/^\+[0-9]+$/', $username ) ) {
        // Try to find a user with this phone number in the meta field
        $users_found = get_users( array(
            'meta_key'   => 'phone_number',
            'meta_value' => $username,
            'number'     => 1,
            'count_total' => false,
            'fields'     => 'ID', // Get only the ID
        ) );

        if ( ! empty( $users_found ) ) {
            $user_id = $users_found[0];
            $found_user = get_user_by( 'ID', $user_id );

            // Now, authenticate this user with the provided password
            if ( $found_user && wp_check_password( $password, $found_user->user_pass, $found_user->ID ) ) {
                return $found_user; // Return the user object on successful password check
            }
        }
    }

    // If no user was found by phone, return a generic error.
    if ( ! $user && ! empty( $username ) && ! empty( $password ) ) {
        // Simulate the error structure WP expects to avoid revealing whether the phone number exists
        return new WP_Error( 'invalid_phone_or_password', __( '<strong>Error</strong>: Invalid phone number or password.', 'astra-child' ) ); // Modified error key/message
    }

    return $user; // Fallback
}
add_filter( 'authenticate', 'allow_phone_number_login', 30, 3 );

/**
 * Override the "Lost your password?" link in login form
 */
function redirect_lost_password_to_custom_page() {
    // Get the forgot password page URL
    $forgot_password_page = get_page_by_path('forgot-password');
    
    if ($forgot_password_page) {
        $custom_url = get_permalink($forgot_password_page->ID);
        
        // Use JavaScript to replace the lost password link
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Find the lost password link and update it
            const lostPasswordLinks = document.querySelectorAll('a[href*="wp-login.php?action=lostpassword"]');
            lostPasswordLinks.forEach(function(link) {
                link.href = '<?php echo esc_url($custom_url); ?>';
            });
            
            // Also check for other common selectors
            const navLinks = document.querySelectorAll('a[href*="action=lostpassword"]');
            navLinks.forEach(function(link) {
                link.href = '<?php echo esc_url($custom_url); ?>';
            });
        });
        </script>
        <?php
    }
}

// Hook into login page and any page that might show login forms
add_action('login_footer', 'redirect_lost_password_to_custom_page');
add_action('wp_footer', 'redirect_lost_password_to_custom_page');

/**
 * Filter the lost password URL to use our custom page
 */
function custom_lost_password_url($url) {
    $forgot_password_page = get_page_by_path('forgot-password');
    
    if ($forgot_password_page) {
        return get_permalink($forgot_password_page->ID);
    }
    
    return $url; // Fallback to default if page not found
}
add_filter('lostpassword_url', 'custom_lost_password_url', 10, 1); 