<?php
/**
 * Listing state (ACF choice: active | sold | expired).
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/ListingStateManager.php';

/**
 * Default listing_state for cars created outside the front-end submission flow (e.g. wp-admin).
 */
add_action('save_post_car', 'bricks_child_listing_state_default_on_insert', 5, 3);

/**
 * @param int          $post_id Post ID.
 * @param WP_Post      $post    Post object.
 * @param bool         $update  Whether this is an existing post being updated.
 */
function bricks_child_listing_state_default_on_insert($post_id, $post, $update): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if ($update) {
        return;
    }
    ListingStateManager::assign_active_for_new_car((int) $post_id);
}
