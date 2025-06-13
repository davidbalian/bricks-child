<?php
/**
 * Account Display Shortcode [account_display].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generates account display shortcode [account_display].
 *
 * Shows Login/Register links when logged out.
 * Shows User Name + dropdown with My Account, My Listings, Logout when logged in.
 *
 * @return string HTML for the account display.
 */
function account_display_shortcode() {
    ob_start();

    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        $first_name = $user->first_name;
        $last_name = $user->last_name;
        $display_name = trim( $first_name . ' ' . $last_name );
        if ( empty( $display_name ) ) {
            $display_name = $user->display_name; // Fallback
        }

        $my_account_page = get_page_by_path('my-account');
        $my_listings_page = get_page_by_path('my-listings');
        $logout_url = wp_logout_url( home_url() );
        ?>
        <div class="account-display account-display-logged-in">
            <span class="user-name-display">
                <?php echo esc_html( $display_name ); ?> 
                <span class="chevron">›</span>
            </span>
            <div class="account-dropdown">
                <div class="dropdown-header"><strong><?php echo esc_html( $display_name ); ?></strong></div>
                <?php if ( $my_account_page ) : ?>
                    <a href="<?php echo esc_url( get_permalink( $my_account_page->ID ) ); ?>"><?php esc_html_e( 'My Account', 'astra-child' ); ?></a>
                <?php endif; ?>
                <?php if ( $my_listings_page ) : ?>
                     <a href="<?php echo esc_url( get_permalink( $my_listings_page->ID ) ); ?>"><?php esc_html_e( 'My Listings', 'astra-child' ); ?></a>
                <?php endif; ?>
                <a href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Log Out', 'astra-child' ); ?></a>
            </div>
        </div>
        <?php
    } else {
        $login_page = get_page_by_path('signin');
        $register_page = get_page_by_path('register');
        ?>
        <div class="account-display account-display-logged-out">
            <?php if ( $login_page ) : ?>
                <a href="<?php echo esc_url( get_permalink( $login_page->ID ) ); ?>"><?php esc_html_e( 'Log In', 'astra-child' ); ?></a>
            <?php endif; ?>
            <?php if ( $login_page && $register_page ) : ?>
                <span class="separator">|</span>
            <?php endif; ?>
             <?php if ( $register_page ) : ?>
                <a href="<?php echo esc_url( get_permalink( $register_page->ID ) ); ?>"><?php esc_html_e( 'Register', 'astra-child' ); ?></a>
            <?php endif; ?>
        </div>
        <?php
    }

    return ob_get_clean();
}
add_shortcode( 'account_display', 'account_display_shortcode' ); 