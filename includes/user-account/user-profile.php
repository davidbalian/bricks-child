<?php
/**
 * Custom User Profile Fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add custom user profile fields.
 */
function custom_user_profile_fields( $user ) {
    ?>
    <h3><?php _e( 'Dealership Information', 'astra-child' ); ?></h3>

    <table class="form-table">
        <tr>
            <th><label for="dealership_name"><?php _e( 'Dealership Name', 'astra-child' ); ?></label></th>
            <td>
                <input type="text" name="dealership_name" id="dealership_name" value="<?php echo esc_attr( get_user_meta( $user->ID, 'dealership_name', true ) ); ?>" class="regular-text">
                <span class="description"><?php _e( 'The name of the dealership (if applicable).', 'astra-child' ); ?></span>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'custom_user_profile_fields' );
add_action( 'edit_user_profile', 'custom_user_profile_fields' );

/**
 * Save custom user profile fields.
 *
 * @param int $user_id The ID of the user being saved.
 */
function save_custom_user_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    if ( isset( $_POST['dealership_name'] ) ) {
        update_user_meta( $user_id, 'dealership_name', sanitize_text_field( $_POST['dealership_name'] ) );
    } else {
        delete_user_meta( $user_id, 'dealership_name' ); // Remove if the field is cleared
    }
}
add_action( 'personal_options_update', 'save_custom_user_profile_fields' );
add_action( 'edit_user_profile_update', 'save_custom_user_profile_fields' ); 