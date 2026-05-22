<?php
/**
 * Daily Deals: Instagram carousel publishing.
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarsDailyDealsInstagramPublisher
{
    public const OPTION_LAST_RUN = 'bricks_child_daily_deals_instagram_last_run';
    public const OPTION_POSTED_DATES = 'bricks_child_daily_deals_instagram_posted_dates';

    private const MIN_CAROUSEL_ITEMS = 2;
    private const MAX_CAROUSEL_ITEMS = 10;
    private const UPLOAD_SUBDIR = 'autoagora-daily-deals/instagram';
    private const RETENTION_DAYS = 14;

    /**
     * @return array{configured:bool,missing:array<int,string>,account_id:string,graph_version:string,graph_base:string}
     */
    public static function getConfigStatus(): array
    {
        $missing = array();

        if (!defined('AUTOAGORA_INSTAGRAM_ACCOUNT_ID') || trim((string) AUTOAGORA_INSTAGRAM_ACCOUNT_ID) === '') {
            $missing[] = 'AUTOAGORA_INSTAGRAM_ACCOUNT_ID';
        }
        if (!defined('AUTOAGORA_INSTAGRAM_ACCESS_TOKEN') || trim((string) AUTOAGORA_INSTAGRAM_ACCESS_TOKEN) === '') {
            $missing[] = 'AUTOAGORA_INSTAGRAM_ACCESS_TOKEN';
        }
        if (!defined('AUTOAGORA_META_GRAPH_VERSION') || trim((string) AUTOAGORA_META_GRAPH_VERSION) === '') {
            $missing[] = 'AUTOAGORA_META_GRAPH_VERSION';
        }

        return array(
            'configured'    => $missing === array(),
            'missing'       => $missing,
            'account_id'    => defined('AUTOAGORA_INSTAGRAM_ACCOUNT_ID') ? trim((string) AUTOAGORA_INSTAGRAM_ACCOUNT_ID) : '',
            'graph_version' => defined('AUTOAGORA_META_GRAPH_VERSION') ? self::normalizeGraphVersion(trim((string) AUTOAGORA_META_GRAPH_VERSION)) : '',
            'graph_base'    => self::configuredGraphBaseUrl(),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function getLastRun(): array
    {
        $raw = get_option(self::OPTION_LAST_RUN, array());
        return is_array($raw) ? $raw : array();
    }

    /**
     * @param bool $force Allow reposting for today's date.
     * @return array<string,mixed>
     */
    public function publishToday(bool $force = false): array
    {
        $date = $this->currentCyprusDate();

        if (!$force && $this->hasPostedForDate($date)) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'skipped',
                'message' => 'Daily Deals were already posted to Instagram for ' . $date . '.',
                'date'    => $date,
            ));
        }

        $config = self::getConfigStatus();
        if (!$config['configured']) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'config_error',
                'message' => 'Missing Instagram config constants: ' . implode(', ', $config['missing']) . '.',
                'date'    => $date,
            ));
        }

        if (!CarsDailyDealsImageCompositor::canComposite()) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'image_error',
                'message' => 'Instagram image generation requires GD or Imagick on the server.',
                'date'    => $date,
            ));
        }

        $builder = new CarsDailyDealsSnapshotBuilder();
        $items = $builder->fetchFirstCarsFromBrowseQuery(5);
        $items = array_values(array_filter($items, static function ($item): bool {
            return is_array($item)
                && !empty($item['image_path'])
                && is_readable((string) $item['image_path']);
        }));

        if (count($items) < self::MIN_CAROUSEL_ITEMS) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'no_items',
                'message' => 'Instagram carousel needs at least two top deals with local readable images.',
                'date'    => $date,
                'count'   => count($items),
            ));
        }

        $items = array_slice($items, 0, self::MAX_CAROUSEL_ITEMS);
        $caption = $builder->buildSocialCaption($items);

        $images = $this->createPublicInstagramImages($items, $date);
        if ($images === array()) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'image_error',
                'message' => 'Could not create public Instagram images in WordPress uploads.',
                'date'    => $date,
            ));
        }

        $image_urls = wp_list_pluck($images, 'url');
        $child_ids = array();

        foreach ($image_urls as $url) {
            $preflight = $this->preflightImageUrl((string) $url);
            if (empty($preflight['ok'])) {
                return $this->recordRun(array(
                    'ok'       => false,
                    'status'   => 'image_url_error',
                    'message'  => 'Generated Instagram image URL is not publicly fetchable: ' . $url,
                    'date'     => $date,
                    'context'  => array(
                        'image_url' => $url,
                        'preflight' => $preflight,
                    ),
                ));
            }

            $child = $this->createMediaContainer(array(
                'image_url'        => $url,
                'is_carousel_item' => 'true',
            ));
            if (empty($child['id'])) {
                return $this->recordRun(
                    $this->failedGraphRun(
                        'child_container_error',
                        'Instagram did not return a child media container ID for image: ' . $url . $this->graphErrorSuffix($child),
                        $date,
                        $child,
                        array('image_url' => $url)
                    )
                );
            }
            $child_id = (string) $child['id'];
            $child_ids[] = $child_id;
            $child_status = $this->waitForContainer($child_id);
            if (isset($child_status['status_code']) && $child_status['status_code'] === 'ERROR') {
                return $this->recordRun(
                    $this->failedGraphRun(
                        'child_container_error',
                        'Instagram child media container failed processing for image: ' . $url,
                        $date,
                        $child_status,
                        array('image_url' => $url)
                    )
                );
            }
        }

        $parent = $this->createMediaContainer(array(
            'media_type' => 'CAROUSEL',
            'children'   => implode(',', $child_ids),
            'caption'    => $caption,
        ));
        if (empty($parent['id'])) {
            return $this->recordRun($this->failedGraphRun('parent_container_error', 'Instagram did not return a carousel container ID.', $date, $parent));
        }

        $parent_id = (string) $parent['id'];
        $parent_status = $this->waitForContainer($parent_id);
        if (isset($parent_status['status_code']) && $parent_status['status_code'] === 'ERROR') {
            return $this->recordRun($this->failedGraphRun('parent_container_error', 'Instagram carousel container failed processing.', $date, $parent_status));
        }

        $published = $this->publishContainer($parent_id);
        if (empty($published['id'])) {
            return $this->recordRun($this->failedGraphRun('publish_error', 'Instagram did not return a published media ID.', $date, $published));
        }

        $media_id = (string) $published['id'];
        $this->markPostedForDate($date, $media_id);

        return $this->recordRun(array(
            'ok'                   => true,
            'status'               => 'published',
            'message'              => 'Daily Deals Instagram carousel published.',
            'date'                 => $date,
            'media_id'             => $media_id,
            'parent_container_id'  => $parent_id,
            'child_container_ids'  => $child_ids,
            'listing_ids'          => array_map('intval', wp_list_pluck($items, 'id')),
            'image_urls'           => $image_urls,
            'published_at'         => current_time('mysql'),
            'graph_version'        => $config['graph_version'],
        ));
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array{path:string,url:string,post_id:int}>
     */
    private function createPublicInstagramImages(array $items, string $date): array
    {
        $upload = wp_upload_dir();
        if (!empty($upload['error']) || empty($upload['basedir']) || empty($upload['baseurl'])) {
            return array();
        }

        $base_url = (string) $upload['baseurl'];
        if (stripos($base_url, 'https://') !== 0) {
            return array();
        }

        $base_dir = trailingslashit((string) $upload['basedir']) . self::UPLOAD_SUBDIR;
        $target_dir = trailingslashit($base_dir) . $date;
        if (!wp_mkdir_p($target_dir)) {
            return array();
        }

        $this->cleanupOldUploads($base_dir);

        $out = array();
        $index = 1;
        foreach ($items as $item) {
            $post_id = (int) ($item['id'] ?? 0);
            $source = (string) ($item['image_path'] ?? '');
            if ($post_id <= 0 || $source === '' || !is_readable($source)) {
                continue;
            }

            $tmp = CarsDailyDealsImageCompositor::instagramSquareToTempJpeg($source);
            if ($tmp === false || !is_string($tmp) || !is_readable($tmp)) {
                continue;
            }

            $filename = 'deal-' . $index . '-' . $post_id . '-instagram-1080.jpg';
            $target = trailingslashit($target_dir) . $filename;
            if (@copy($tmp, $target)) {
                @chmod($target, 0644);
                $out[] = array(
                    'path'    => $target,
                    'url'     => trailingslashit($base_url) . self::UPLOAD_SUBDIR . '/' . rawurlencode($date) . '/' . rawurlencode($filename),
                    'post_id' => $post_id,
                );
            }
            @unlink($tmp);
            ++$index;
        }

        return $out;
    }

    /**
     * @return array{ok:bool,http_code:int,content_type:string,content_length:string,error:string}
     */
    private function preflightImageUrl(string $url): array
    {
        $result = array(
            'ok'             => false,
            'http_code'      => 0,
            'content_type'   => '',
            'content_length' => '',
            'error'          => '',
        );

        $response = wp_remote_head($url, array(
            'redirection' => 0,
            'timeout'     => 20,
        ));

        if (is_wp_error($response)) {
            $result['error'] = $response->get_error_message();
            return $result;
        }

        $result['http_code'] = (int) wp_remote_retrieve_response_code($response);
        $result['content_type'] = (string) wp_remote_retrieve_header($response, 'content-type');
        $result['content_length'] = (string) wp_remote_retrieve_header($response, 'content-length');

        $content_type = strtolower($result['content_type']);
        $result['ok'] = $result['http_code'] >= 200
            && $result['http_code'] < 300
            && strpos($content_type, 'image/jpeg') !== false;

        if (!$result['ok'] && $result['error'] === '') {
            $result['error'] = 'Expected HTTP 2xx and image/jpeg Content-Type.';
        }

        return $result;
    }

    private function cleanupOldUploads(string $base_dir): void
    {
        $real_base = realpath($base_dir);
        if ($real_base === false || !is_dir($real_base)) {
            return;
        }

        $cutoff = time() - (self::RETENTION_DAYS * DAY_IN_SECONDS);
        $dirs = glob(trailingslashit($real_base) . '*', GLOB_ONLYDIR);
        if (!is_array($dirs)) {
            return;
        }

        foreach ($dirs as $dir) {
            $real_dir = realpath($dir);
            if ($real_dir === false || strpos($real_dir, $real_base) !== 0 || @filemtime($real_dir) >= $cutoff) {
                continue;
            }

            $files = glob(trailingslashit($real_dir) . '*.jpg');
            if (is_array($files)) {
                foreach ($files as $file) {
                    $real_file = realpath($file);
                    if ($real_file !== false && strpos($real_file, $real_base) === 0) {
                        @unlink($real_file);
                    }
                }
            }
            @rmdir($real_dir);
        }
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function createMediaContainer(array $params): array
    {
        return $this->graphPost('/' . $this->instagramAccountId() . '/media', $params);
    }

    /**
     * @return array<string,mixed>
     */
    private function publishContainer(string $container_id): array
    {
        return $this->graphPost('/' . $this->instagramAccountId() . '/media_publish', array(
            'creation_id' => $container_id,
        ));
    }

    /**
     * @return array<string,mixed>
     */
    private function waitForContainer(string $container_id): array
    {
        $last = array();
        for ($i = 0; $i < 6; $i++) {
            if ($i > 0) {
                sleep(2);
            }
            $last = $this->graphGet('/' . rawurlencode($container_id), array(
                'fields' => 'status_code,status',
            ));
            $status_code = isset($last['status_code']) ? (string) $last['status_code'] : '';
            if ($status_code === 'FINISHED' || $status_code === 'ERROR') {
                break;
            }
        }

        return $last;
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function graphPost(string $path, array $params): array
    {
        $params['access_token'] = $this->accessToken();
        $response = wp_remote_post($this->graphBaseUrl() . $path, array(
            'timeout' => 45,
            'body'    => $params,
        ));

        return $this->decodeGraphResponse($response);
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function graphGet(string $path, array $params): array
    {
        $params['access_token'] = $this->accessToken();
        $url = add_query_arg($params, $this->graphBaseUrl() . $path);
        $response = wp_remote_get($url, array('timeout' => 30));

        return $this->decodeGraphResponse($response);
    }

    /**
     * @param array<string,mixed>|\WP_Error $response
     * @return array<string,mixed>
     */
    private function decodeGraphResponse($response): array
    {
        if (is_wp_error($response)) {
            return array(
                'error' => array(
                    'message' => $response->get_error_message(),
                    'code'    => $response->get_error_code(),
                ),
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        $data = is_array($json) ? $json : array('raw_body' => $body);
        $data['http_code'] = $code;

        return $data;
    }

    /**
     * @param array<string,mixed> $response
     */
    private function graphErrorSuffix(array $response): string
    {
        if (!isset($response['error']) || !is_array($response['error'])) {
            return '';
        }

        $error = $response['error'];
        $parts = array();
        if (!empty($error['message'])) {
            $parts[] = (string) $error['message'];
        }
        if (!empty($error['type'])) {
            $parts[] = 'type=' . (string) $error['type'];
        }
        if (!empty($error['code'])) {
            $parts[] = 'code=' . (string) $error['code'];
        }
        if (!empty($error['error_subcode'])) {
            $parts[] = 'subcode=' . (string) $error['error_subcode'];
        }

        return $parts === array() ? '' : ' Meta error: ' . implode(' | ', $parts);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function recordRun(array $payload): array
    {
        $payload['ran_at'] = current_time('mysql');
        update_option(self::OPTION_LAST_RUN, $payload, false);
        return $payload;
    }

    /**
     * @param array<string,mixed> $graph_response
     * @return array<string,mixed>
     */
    private function failedGraphRun(string $status, string $message, string $date, array $graph_response, array $context = array()): array
    {
        $payload = array(
            'ok'             => false,
            'status'         => $status,
            'message'        => $message,
            'date'           => $date,
            'graph_response' => $this->redactGraphResponse($graph_response),
        );

        if ($context !== array()) {
            $payload['context'] = $context;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $response
     * @return array<string,mixed>
     */
    private function redactGraphResponse(array $response): array
    {
        unset($response['access_token']);
        return $response;
    }

    private function hasPostedForDate(string $date): bool
    {
        $posted = get_option(self::OPTION_POSTED_DATES, array());
        return is_array($posted) && !empty($posted[$date]);
    }

    private function markPostedForDate(string $date, string $media_id): void
    {
        $posted = get_option(self::OPTION_POSTED_DATES, array());
        if (!is_array($posted)) {
            $posted = array();
        }
        $posted[$date] = array(
            'media_id'  => $media_id,
            'posted_at' => current_time('mysql'),
        );

        krsort($posted);
        $posted = array_slice($posted, 0, 90, true);
        update_option(self::OPTION_POSTED_DATES, $posted, false);
    }

    private function currentCyprusDate(): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Nicosia'));
        return $now->format('Y-m-d');
    }

    private function graphBaseUrl(): string
    {
        return trailingslashit(self::configuredGraphBaseUrl()) . rawurlencode($this->graphVersion());
    }

    private function instagramAccountId(): string
    {
        return trim((string) AUTOAGORA_INSTAGRAM_ACCOUNT_ID);
    }

    private function accessToken(): string
    {
        $token = trim((string) AUTOAGORA_INSTAGRAM_ACCESS_TOKEN);
        $token = preg_replace('/\s+/', '', $token);
        $token = trim((string) $token, "\"'");

        if (stripos($token, 'Bearer') === 0) {
            $token = trim(substr($token, 6));
        }

        if (stripos($token, 'access_token=') === 0) {
            $token = substr($token, strlen('access_token='));
        }

        return trim($token, "\"'");
    }

    private function graphVersion(): string
    {
        return self::normalizeGraphVersion(trim((string) AUTOAGORA_META_GRAPH_VERSION));
    }

    private static function configuredGraphBaseUrl(): string
    {
        if (defined('AUTOAGORA_META_GRAPH_BASE_URL')) {
            $base = trim((string) AUTOAGORA_META_GRAPH_BASE_URL);
            if ($base !== '') {
                return untrailingslashit($base);
            }
        }

        return 'https://graph.facebook.com';
    }

    private static function normalizeGraphVersion(string $version): string
    {
        $version = trim($version);
        if ($version !== '' && $version[0] !== 'v') {
            $version = 'v' . $version;
        }

        return $version;
    }
}
