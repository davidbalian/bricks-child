<?php
/**
 * Hook listener for sending publish notifications when listings go live.
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class ListingNotificationPublishHook
{
    public function __construct()
    {
        add_action('transition_post_status', array($this, 'handlePostStatusTransition'), 10, 3);
    }

    public function handlePostStatusTransition(string $new_status, string $old_status, WP_Post $post): void
    {
        // Only process car posts
        if ($post->post_type !== 'car') {
            return;
        }

        // Only send notification when transitioning from pending to publish
        if ($old_status === 'pending' && $new_status === 'publish') {
            listing_notification_manager()->maybeSendPublishNotification($post->ID);
        }
    }
}

new ListingNotificationPublishHook();
