<?php
/**
 * Bootstraps listing notifications helpers.
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns a shared notification manager instance.
 *
 * @return ListingNotificationManager
 */
function listing_notification_manager(): ListingNotificationManager
{
    static $instance = null;

    if ($instance === null) {
        $instance = new ListingNotificationManager();
    }

    return $instance;
}

