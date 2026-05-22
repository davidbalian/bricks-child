<?php
/**
 * Daily Deals: Facebook Page publishing.
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarsDailyDealsFacebookPublisher
{
    public const OPTION_LAST_RUN = 'bricks_child_daily_deals_facebook_last_run';
    public const OPTION_POSTED_DATES = 'bricks_child_daily_deals_facebook_posted_dates';

    private const MAX_PHOTOS = 10;
    private const UPLOAD_SUBDIR = 'autoagora-daily-deals/facebook';
    private const RETENTION_DAYS = 14;

    /** @var string|null */
    private $resolved_page_access_token = null;

    /**
     * @return array{configured:bool,missing:array<int,string>,page_id:string,graph_version:string}
     */
    public static function getConfigStatus(): array
    {
        $missing = array();

        if (!defined('AUTOAGORA_FACEBOOK_PAGE_ID') || trim((string) AUTOAGORA_FACEBOOK_PAGE_ID) === '') {
            $missing[] = 'AUTOAGORA_FACEBOOK_PAGE_ID';
        }
        if (!defined('AUTOAGORA_FACEBOOK_PAGE_ACCESS_TOKEN') || trim((string) AUTOAGORA_FACEBOOK_PAGE_ACCESS_TOKEN) === '') {
            $missing[] = 'AUTOAGORA_FACEBOOK_PAGE_ACCESS_TOKEN';
        }
        if (!defined('AUTOAGORA_META_GRAPH_VERSION') || trim((string) AUTOAGORA_META_GRAPH_VERSION) === '') {
            $missing[] = 'AUTOAGORA_META_GRAPH_VERSION';
        }

        return array(
            'configured'    => $missing === array(),
            'missing'       => $missing,
            'page_id'       => defined('AUTOAGORA_FACEBOOK_PAGE_ID') ? trim((string) AUTOAGORA_FACEBOOK_PAGE_ID) : '',
            'graph_version' => defined('AUTOAGORA_META_GRAPH_VERSION') ? self::normalizeGraphVersion(trim((string) AUTOAGORA_META_GRAPH_VERSION)) : '',
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
     * @return array<string,mixed>
     */
    public function publishToday(bool $force = false): array
    {
        $date = $this->currentCyprusDate();

        if (!$force && $this->hasPostedForDate($date)) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'skipped',
                'message' => 'Daily Deals were already posted to Facebook for ' . $date . '.',
                'date'    => $date,
            ));
        }

        $config = self::getConfigStatus();
        if (!$config['configured']) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'config_error',
                'message' => 'Missing Facebook config constants: ' . implode(', ', $config['missing']) . '.',
                'date'    => $date,
            ));
        }

        if (!CarsDailyDealsImageCompositor::canComposite()) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'image_error',
                'message' => 'Facebook image generation requires GD or Imagick on the server.',
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

        if ($items === array()) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'no_items',
                'message' => 'Facebook post needs at least one top deal with a local readable image.',
                'date'    => $date,
            ));
        }

        $items = array_slice($items, 0, self::MAX_PHOTOS);
        $caption = $builder->buildSocialCaption($items);
        $images = $this->createPublicFacebookImages($items, $date);
        if ($images === array()) {
            return $this->recordRun(array(
                'ok'      => false,
                'status'  => 'image_error',
                'message' => 'Could not create public Facebook images in WordPress uploads.',
                'date'    => $date,
            ));
        }

        $photo_ids = array();
        foreach (wp_list_pluck($images, 'url') as $url) {
            $photo = $this->createUnpublishedPhoto((string) $url);
            if (empty($photo['id'])) {
                if ($this->isPageIdentityError($photo)) {
                    return $this->recordRun(array(
                        'ok'      => false,
                        'status'  => 'page_token_error',
                        'message' => 'Facebook requires a Page access token for unpublished photo posts. The configured token could not be resolved to a Page token for page ID ' . $this->pageId() . '.',
                        'date'    => $date,
                    ));
                }

                return $this->recordRun(
                    $this->failedGraphRun(
                        'photo_upload_error',
                        'Facebook did not return an unpublished photo ID for image: ' . $url,
                        $date
                    )
                );
            }
            $photo_ids[] = (string) $photo['id'];
        }

        $post = $this->createMultiPhotoPost($photo_ids, $caption);
        if (empty($post['id'])) {
            return $this->recordRun(
                $this->failedGraphRun(
                    'post_publish_error',
                    'Facebook did not return a Page post ID.',
                    $date
                )
            );
        }

        $post_id = (string) $post['id'];
        $this->markPostedForDate($date, $post_id);

        return $this->recordRun(array(
            'ok'          => true,
            'status'      => 'published',
            'message'     => 'Daily Deals Facebook Page post published.',
            'date'        => $date,
            'post_id'     => $post_id,
            'photo_ids'   => $photo_ids,
            'listing_ids' => array_map('intval', wp_list_pluck($items, 'id')),
            'image_urls'  => wp_list_pluck($images, 'url'),
            'published_at' => current_time('mysql'),
            'post_url'    => 'https://www.facebook.com/' . rawurlencode($post_id),
        ));
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array{path:string,url:string,post_id:int}>
     */
    private function createPublicFacebookImages(array $items, string $date): array
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

            $filename = 'deal-' . $index . '-' . $post_id . '-facebook.jpg';
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
     * @return array<string,mixed>
     */
    private function createUnpublishedPhoto(string $image_url): array
    {
        return $this->graphPost('/' . rawurlencode($this->pageId()) . '/photos', array(
            'url'       => $image_url,
            'published' => 'false',
        ));
    }

    /**
     * @param array<int,string> $photo_ids
     * @return array<string,mixed>
     */
    private function createMultiPhotoPost(array $photo_ids, string $caption): array
    {
        $body = array('message' => $caption);
        $index = 0;
        foreach ($photo_ids as $photo_id) {
            $body['attached_media[' . $index . ']'] = wp_json_encode(array('media_fbid' => $photo_id));
            ++$index;
        }

        return $this->graphPost('/' . rawurlencode($this->pageId()) . '/feed', $body);
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function graphPost(string $path, array $params): array
    {
        $params['access_token'] = $this->pageAccessToken();
        $response = wp_remote_post($this->graphBaseUrl() . $path, array(
            'timeout' => 45,
            'body'    => $params,
        ));

        return $this->decodeGraphResponse($response);
    }

    private function pageAccessToken(): string
    {
        if ($this->resolved_page_access_token !== null) {
            return $this->resolved_page_access_token;
        }

        $configured_token = $this->accessToken();
        $resolved = $this->fetchPageAccessTokenFromAccounts($configured_token);
        if ($resolved === '') {
            $resolved = $this->fetchPageAccessTokenFromPage($configured_token);
        }

        $this->resolved_page_access_token = $resolved !== '' ? $resolved : $configured_token;
        return $this->resolved_page_access_token;
    }

    private function fetchPageAccessTokenFromAccounts(string $token): string
    {
        $url = add_query_arg(
            array(
                'fields'       => 'id,name,access_token',
                'access_token' => $token,
            ),
            $this->graphBaseUrl() . '/me/accounts'
        );

        $response = wp_remote_get($url, array('timeout' => 30));
        $decoded = $this->decodeGraphResponse($response);

        if (empty($decoded['data']) || !is_array($decoded['data'])) {
            return '';
        }

        $page_id = $this->pageId();
        foreach ($decoded['data'] as $page) {
            if (!is_array($page)) {
                continue;
            }
            if ((string) ($page['id'] ?? '') === $page_id && !empty($page['access_token'])) {
                return $this->normalizeAccessToken((string) $page['access_token']);
            }
        }

        return '';
    }

    private function fetchPageAccessTokenFromPage(string $token): string
    {
        $url = add_query_arg(
            array(
                'fields'       => 'access_token',
                'access_token' => $token,
            ),
            $this->graphBaseUrl() . '/' . rawurlencode($this->pageId())
        );

        $response = wp_remote_get($url, array('timeout' => 30));
        $decoded = $this->decodeGraphResponse($response);

        if (!empty($decoded['access_token'])) {
            return $this->normalizeAccessToken((string) $decoded['access_token']);
        }

        return '';
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
    private function isPageIdentityError(array $response): bool
    {
        if (!isset($response['error']) || !is_array($response['error'])) {
            return false;
        }

        $error = $response['error'];
        $code = isset($error['code']) ? (string) $error['code'] : '';
        $message = isset($error['message']) ? strtolower((string) $error['message']) : '';

        return $code === '200' && strpos($message, 'as the page itself') !== false;
    }

    /**
     * @return array<string,mixed>
     */
    private function failedGraphRun(string $status, string $message, string $date): array
    {
        return array(
            'ok'      => false,
            'status'  => $status,
            'message' => $message,
            'date'    => $date,
        );
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

    private function hasPostedForDate(string $date): bool
    {
        $posted = get_option(self::OPTION_POSTED_DATES, array());
        return is_array($posted) && !empty($posted[$date]);
    }

    private function markPostedForDate(string $date, string $post_id): void
    {
        $posted = get_option(self::OPTION_POSTED_DATES, array());
        if (!is_array($posted)) {
            $posted = array();
        }
        $posted[$date] = array(
            'post_id'   => $post_id,
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
        return 'https://graph.facebook.com/' . rawurlencode($this->graphVersion());
    }

    private function pageId(): string
    {
        return trim((string) AUTOAGORA_FACEBOOK_PAGE_ID);
    }

    private function accessToken(): string
    {
        return $this->normalizeAccessToken((string) AUTOAGORA_FACEBOOK_PAGE_ACCESS_TOKEN);
    }

    private function normalizeAccessToken(string $token): string
    {
        $token = trim($token);
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

    private static function normalizeGraphVersion(string $version): string
    {
        $version = trim($version);
        if ($version !== '' && $version[0] !== 'v') {
            $version = 'v' . $version;
        }

        return $version;
    }
}
