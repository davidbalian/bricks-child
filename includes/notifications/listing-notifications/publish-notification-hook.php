<?php
/**
 * Hooks listing publish transitions to send seller notification.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notify seller when a car listing moves from pending to published.
 */
add_action('transition_post_status', function ($new_status, $old_status, $post) {
    if ($new_status !== 'publish' || $old_status === 'publish') {
        return;
    }

    if (!$post || $post->post_type !== 'car') {
        return;
    }

    listing_notification_manager()->maybeSendListingPublishedNotification($post->ID);
}, 10, 3);

