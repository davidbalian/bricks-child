<?php
/** Private review boundary. Public onboarding requires a deliberate later opt-in. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Review
{
    public const META = '_ag_dealer_sandbox';
    public const USER_META = '_ag_dealer_test_account';
    private static bool $writing = false;
    private static bool $reading = false;

    public static function privateMode(): bool
    {
        return (bool) get_option('autoagora_dealer_admin_release') || !defined('AUTOAGORA_DEALER_PUBLIC_ENABLED') || !AUTOAGORA_DEALER_PUBLIC_ENABLED;
    }
    public static function admin(): bool
    {
        return current_user_can('manage_options') && in_array('administrator', (array) wp_get_current_user()->roles, true);
    }
    public static function canWorkspace(): bool
    {
        return self::admin() || Reviewer::allowed();
    }
    public static function read(callable $work)
    {
        $old = self::$reading;
        self::$reading = self::canWorkspace();
        try { return $work(); } finally { self::$reading = $old; }
    }
    private static function mediaAllowed(string $file): bool
    {
        if (self::admin()) { return true; }
        if (!Reviewer::allowed()) { return false; }
        global $wpdb;
        $relative = substr($file, strlen(self::storage()) + 1);
        $original = preg_replace('/-\d+x\d+(?=\.[a-z]+$)/i', '', $relative);
        $paths = array_unique([$file, $relative, self::storage() . '/' . $original, $original]);
        $placeholders = implode(',', array_fill(0, count($paths), '%s'));
        $ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value IN ($placeholders)", array_values($paths)));
        foreach ($ids as $id) {
            if (!self::hidden((int) $id) || !self::testDealer((int) get_post_field('post_author', $id))) { continue; }
            $attached = get_attached_file((int) $id);
            if (realpath($attached) === $file) { return true; }
            $metadata = wp_get_attachment_metadata((int) $id);
            foreach (($metadata['sizes'] ?? []) as $size) {
                if (!empty($size['file']) && realpath(dirname($attached) . '/' . basename($size['file'])) === $file) { return true; }
            }
        }
        return false;
    }
    public static function testDealer(int $id): bool
    {
        return (bool) get_user_meta($id, self::USER_META, true) && Support::eligible($id);
    }
    public static function sandbox(callable $work)
    {
        update_option('autoagora_dealer_review_data_exists', 1, true);
        $old = self::$writing;
        self::$writing = true;
        try { return $work(); } finally { self::$writing = $old; }
    }
    public static function storage(): string
    {
        return defined('AUTOAGORA_DEALER_REVIEW_STORAGE') ? rtrim(AUTOAGORA_DEALER_REVIEW_STORAGE, '/') : '';
    }
    public static function hidden(int $id): bool
    {
        return (bool) get_post_meta($id, self::META, true);
    }
    public static function bootstrap(): void
    {
        add_filter('wp_insert_post_data', static function ($data, $postarr) {
            $tagged = !empty($postarr['meta_input'][self::META]) || (!empty($postarr['ID']) && self::hidden((int) $postarr['ID']))
                || ((int) ($data['post_author'] ?? 0) > 0 && get_user_meta((int) $data['post_author'], self::USER_META, true));
            if ($tagged && in_array($data['post_type'], ['car', 'dealer_profile'], true) && in_array($data['post_status'], ['publish', 'future'], true)) {
                $data['post_status'] = 'private';
            }
            return $data;
        }, 99, 2);
        add_action('save_post', static function ($id, $post) {
            if (in_array($post->post_type, ['car', 'dealer_profile', 'attachment'], true)
                && (self::$writing || get_user_meta((int) $post->post_author, self::USER_META, true))) {
                update_post_meta($id, self::META, 1);
            }
        }, 1, 2);
        // Attachments use dedicated hooks instead of save_post in WordPress.
        $tagAttachment = static function ($id) {
            if (self::$writing || get_user_meta((int) get_post_field('post_author', $id), self::USER_META, true)) { update_post_meta($id, self::META, 1); }
        };
        add_action('add_attachment', $tagAttachment, 1);
        add_action('edit_attachment', $tagAttachment, 1);
        add_filter('posts_where', static function ($where) {
            if (self::admin() || self::$reading) { return $where; }
            global $wpdb;
            return $where . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} ag_review_private WHERE ag_review_private.post_id = {$wpdb->posts}.ID AND ag_review_private.meta_key = '" . self::META . "' AND ag_review_private.meta_value = '1')";
        }, 99);
        add_action('pre_user_query', static function ($query) {
            if (self::admin() || self::$reading) { return; }
            global $wpdb;
            $query->query_where .= " AND NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} ag_review_user WHERE ag_review_user.user_id = {$wpdb->users}.ID AND ag_review_user.meta_key = '" . self::USER_META . "' AND ag_review_user.meta_value = '1')";
        });
        add_filter('wp_sitemaps_posts_query_args', static function ($args) {
            $args['meta_query'][] = ['key' => self::META, 'compare' => 'NOT EXISTS'];
            return $args;
        });
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', static function ($ids) {
            global $wpdb;
            return array_merge((array) $ids, array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1'", self::META))));
        });
        add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
            if (self::privateMode() && str_starts_with($request->get_route(), '/autoagora-dealer/v1/') && !self::admin()) {
                return Support::error('not_found', 'Not found.', 404);
            }
            if (!self::admin() && preg_match('~/wp/v2/(?:car|cars|dealer_profile|dealer_profiles|media)/(\d+)(?:/|$)~', $request->get_route(), $match) && self::hidden((int) $match[1])) {
                return Support::error('not_found', 'Not found.', 404);
            }
            if (!self::admin() && preg_match('~/wp/v2/users/(\d+)(?:/|$)~', $request->get_route(), $match) && get_user_meta((int) $match[1], self::USER_META, true)) { return Support::error('not_found', 'Not found.', 404); }
            return $result;
        }, PHP_INT_MAX, 3);
        add_action('template_redirect', static function () {
            $action = $_GET['autoagora_connect'] ?? '';
            if (self::privateMode() && is_string($action) && in_array($action, ['authorize', 'connections', 'workspace'], true) && !($action === 'workspace' ? self::canWorkspace() : self::admin())) {
                self::notFound();
            }
            if (!self::admin() && is_singular() && self::hidden((int) get_queried_object_id())) { self::notFound(); }
            if (!self::admin() && is_author() && get_user_meta((int) get_queried_object_id(), self::USER_META, true)) { self::notFound(); }
            $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $base = (wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/') . 'ag-review-media/';
            if (is_string($path) && str_starts_with($path, $base)) {
                if (!self::canWorkspace() || !self::storage()) { self::notFound(); }
                $relative = rawurldecode(substr($path, strlen($base)));
                $root = realpath(self::storage());
                $file = $root ? realpath($root . '/' . $relative) : false;
                if (!$file || !str_starts_with($file, $root . '/') || !is_file($file) || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) { self::notFound(); }
                if (!self::mediaAllowed($file)) { self::notFound(); }
                nocache_headers();
                header('X-Robots-Tag: noindex, nofollow');
                header('X-Content-Type-Options: nosniff');
                header('Content-Type: ' . (wp_get_image_mime($file) ?: 'application/octet-stream'));
                header('Content-Length: ' . filesize($file));
                status_header(200);
                readfile($file);
                exit;
            }
        }, -40);
        add_filter('upload_dir', static function ($upload) {
            if (!self::$writing) { return $upload; }
            $storage = self::storage();
            $webroot = realpath(ABSPATH);
            $parent = $storage ? (realpath($storage) ?: realpath(dirname($storage))) : false;
            if (!$storage || !$parent || ($webroot && str_starts_with($parent . '/', $webroot . '/'))) {
                $upload['error'] = 'Private review media storage must be configured outside the web root.';
                return $upload;
            }
            $upload['basedir'] = $storage;
            $upload['path'] = $storage . $upload['subdir'];
            $upload['baseurl'] = home_url('/ag-review-media');
            $upload['url'] = $upload['baseurl'] . $upload['subdir'];
            return $upload;
        });
        add_filter('get_attached_file', static function ($file, $id) {
            if (!self::hidden((int) $id) || !self::storage()) { return $file; }
            $raw = get_post_meta($id, '_wp_attached_file', true);
            return is_string($raw) && $raw !== '' && $raw[0] !== '/' ? self::storage() . '/' . $raw : $file;
        }, 10, 2);
        add_filter('wp_get_attachment_url', static function ($url, $id) {
            if (!self::hidden((int) $id) || !self::storage()) { return $url; }
            $file = get_attached_file($id);
            $root = self::storage() . '/';
            if (!is_string($file) || !str_starts_with($file, $root)) { return $url; }
            $relative = substr($file, strlen($root));
            return home_url('/ag-review-media/' . implode('/', array_map('rawurlencode', explode('/', $relative))));
        }, 10, 2);
        add_filter('pre_wp_mail', static fn ($pre) => self::$writing ? true : $pre, -50);
        add_filter('pre_http_request', static fn ($pre) => self::$writing ? new \WP_Error('review_outbound_disabled', 'External integrations are disabled for private review actions.') : $pre, -50);
    }
    private static function notFound(): void
    {
        status_header(404);
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        echo 'Not found.';
        exit;
    }
}
