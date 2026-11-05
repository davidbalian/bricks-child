<?php
/** Successful website submissions only; imports, edits and API writes do not count. */
if (!defined('ABSPATH')) {
    exit;
}

function autoagora_queue_listing_submission_analytics($post_id) {
    if (!is_user_logged_in() || get_post_type($post_id) !== 'car'
        || (int) get_post_field('post_author', $post_id) !== get_current_user_id()) {
        return;
    }
    update_post_meta($post_id, '_autoagora_submission_analytics', array(
        'session' => wp_hash(wp_get_session_token()),
        'created' => time(),
    ));
}

function autoagora_enqueue_listing_submission_analytics() {
    if (!is_user_logged_in() || !isset($_GET['listing_submitted'], $_GET['listing_id'])
        || $_GET['listing_submitted'] !== 'success' || !is_scalar($_GET['listing_id'])) {
        return;
    }
    $post_id = absint($_GET['listing_id']);
    if (!$post_id || get_post_type($post_id) !== 'car'
        || (int) get_post_field('post_author', $post_id) !== get_current_user_id()) {
        return;
    }
    $receipt = get_post_meta($post_id, '_autoagora_submission_analytics', true);
    if (!is_array($receipt) || empty($receipt['session']) || empty($receipt['created'])
        || time() - (int) $receipt['created'] > DAY_IN_SECONDS
        || !hash_equals($receipt['session'], wp_hash(wp_get_session_token()))) {
        return;
    }
    // Value-matched deletion is atomic: only one concurrent page load can emit.
    if (!delete_post_meta($post_id, '_autoagora_submission_analytics', $receipt)) {
        return;
    }
    $relative = '/includes/user-manage-listings/listing-submission-analytics.js';
    wp_enqueue_script('autoagora-listing-submission-analytics', get_stylesheet_directory_uri() . $relative,
        array(), filemtime(get_stylesheet_directory() . $relative), true);
    wp_localize_script('autoagora-listing-submission-analytics', 'autoagoraListingSubmission', array(
        'measurementId' => 'G-06RRVCP0GS',
        'listingId' => $post_id,
    ));
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_listing_submission_analytics', 30);
