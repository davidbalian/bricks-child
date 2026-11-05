<?php
// Offline contract checks. No WordPress instance, network or production records.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
$logged = true; $owner = 7; $user = 7; $type = 'car'; $token = 'session-a';
$meta = array(); $enqueued = 0; $payload = null;
function add_action(...$args) {}
function is_user_logged_in() { return $GLOBALS['logged']; }
function get_current_user_id() { return $GLOBALS['user']; }
function get_post_type($id) { return $GLOBALS['type']; }
function get_post_field($field, $id) { return $GLOBALS['owner']; }
function wp_get_session_token() { return $GLOBALS['token']; }
function wp_hash($value) { return hash('sha256', $value); }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id] = $value; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id] ?? ''; }
function delete_post_meta($id, $key, $value) {
    if (($GLOBALS['meta'][$id] ?? null) !== $value) return false;
    unset($GLOBALS['meta'][$id]); return true;
}
function absint($value) { return abs((int) $value); }
function get_stylesheet_directory_uri() { return 'https://example.test/theme'; }
function get_stylesheet_directory() { return dirname(__DIR__, 2); }
function wp_enqueue_script(...$args) { $GLOBALS['enqueued']++; }
function wp_localize_script($handle, $name, $payload) { $GLOBALS['payload'] = $payload; }
require dirname(__DIR__, 2) . '/includes/user-manage-listings/listing-submission-analytics.php';
$count = 0;
function check($ok, $label) { $GLOBALS['count']++; if (!$ok) throw new Exception($label); }
$_GET = array('listing_submitted' => 'success', 'listing_id' => 42);
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 0, 'Forged success URL must not emit');
autoagora_queue_listing_submission_analytics(42);
$token = 'different-session';
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 0 && isset($meta[42]), 'Another login session must not consume');
$token = 'session-a'; $user = 8;
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 0 && isset($meta[42]), 'Another owner must not consume');
$user = 7; $logged = false;
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 0, 'Logged out must not emit');
$logged = true; $type = 'page';
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 0, 'Non-car must not emit');
$type = 'car'; $meta[42]['created'] = time() - DAY_IN_SECONDS - 1;
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 0, 'Expired receipt must not emit');
autoagora_queue_listing_submission_analytics(42);
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 1 && !isset($meta[42]), 'Successful receipt emits and is consumed');
check($payload === array('measurementId' => 'G-06RRVCP0GS', 'listingId' => 42), 'Only approved payload');
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 1, 'Refresh must not emit twice');
$_GET['listing_id'] = array(42);
autoagora_enqueue_listing_submission_analytics();
check($enqueued === 1, 'Malformed query must not emit');
$source = file_get_contents(dirname(__DIR__, 2) . '/includes/user-manage-listings/car-submission.php');
check(strpos($source, 'autoagora_queue_listing_submission_analytics($post_id)') > strpos($source, 'if (is_wp_error($image_ids))'), 'Receipt only after image failure exit');
echo "$count checks passed\n";
