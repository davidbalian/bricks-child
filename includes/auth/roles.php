<?php
/**
 * Custom User Roles Definitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add custom user roles.
 */
function add_custom_user_roles() {
    // Add the 'dealership' role if it doesn't exist
    if ( ! get_role( 'dealership' ) ) {
        add_role(
            'dealership',
            __( 'Dealership', 'astra-child' ),
            array(
                'read' => true,
                'edit_posts' => true,
                'edit_published_posts' => true,
                'delete_posts' => true,
                'delete_published_posts' => true,
                'publish_posts' => true,
                'upload_files' => true
            )
        );
    }

    // Add the 'client' role if it doesn't exist
    if ( ! get_role( 'client' ) ) {
        add_role(
            'client',
            __( 'Client', 'astra-child' ),
            array(
                'read' => true,
                'edit_posts' => true,
                'edit_published_posts' => true,
                'delete_posts' => true,
                'delete_published_posts' => true,
                'publish_posts' => true,
                'upload_files' => true
            )
        );
    }

    // Ensure the default 'subscriber' role exists
    if ( ! get_role( 'subscriber' ) ) {
        add_role( 'subscriber', __( 'Subscriber', 'astra-child' ), array( 'read' => true ) );
    }
}
add_action( 'after_switch_theme', 'add_custom_user_roles' );
add_action( 'init', 'add_custom_user_roles' ); 