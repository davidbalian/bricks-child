<?php
/** Bounded HTTPS downloads with per-hop DNS validation and address pinning. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Media
{
    public const MAX_BYTES = 12582912;
    public const MAX_PIXELS = 40000000;

    public static function destination(string $url): array
    {
        $parts = wp_parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (strlen($url) > 2048 || ($parts['scheme'] ?? '') !== 'https' || !$host
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || filter_var($host, FILTER_VALIDATE_IP) || !preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/D', $host)
            || !str_contains($host, '.') || preg_match('/\.(?:local|localhost|internal|test|invalid)$/D', $host)) {
            throw new \RuntimeException('Image URL must use a public HTTPS hostname on port 443.');
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach ($records ?: [] as $record) {
            $ip = $record['ip'] ?? ($record['ipv6'] ?? null);
            if (!$ip) { continue; }
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) || str_contains(strtolower($ip), '::ffff:')) {
                throw new \RuntimeException('Image hostname resolves to a private or reserved address.');
            }
            $addresses[] = $ip;
        }
        if (!$addresses) { throw new \RuntimeException('Image hostname could not be resolved.'); }
        return [$host, $addresses[0]];
    }

    private static function download(string $url, float $deadline): string
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $path = wp_tempnam('ag-image');
        if (!$path) { throw new \RuntimeException('Temporary image storage is unavailable.'); }
        try {
            // The loopback-only test fixture can supply bytes for exact synthetic URLs.
            // This hook never runs in development/staging/production environments.
            if (wp_get_environment_type() === 'local' && in_array(wp_parse_url(home_url('/'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
                $fixture = apply_filters('autoagora_dealer_local_image_fixture', null, $url);
                if (is_string($fixture)) {
                    if (strlen($fixture) > self::MAX_BYTES || file_put_contents($path, $fixture) === false) { throw new \RuntimeException('Fixture image exceeds the size limit or could not be stored.'); }
                    self::validateFile($path);
                    return $path;
                }
            }
            for ($hop = 0; $hop <= 3; $hop++) {
                if (microtime(true) >= $deadline) { throw new \RuntimeException('Image batch exceeded the time limit.'); }
                [$host, $ip] = self::destination($url);
                $stream = fopen($path, 'wb');
                if (!$stream) { throw new \RuntimeException('Temporary image storage is unavailable.'); }
                $bytes = 0;
                $location = '';
                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_PROXY => '', CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => max(1, min(15, (int) ($deadline - microtime(true)))),
                    CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
                    CURLOPT_USERAGENT => 'AutoAgora-Dealer-Images/1.0',
                    CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$location): int {
                        if (stripos($header, 'location:') === 0) { $location = trim(substr($header, 9)); }
                        return strlen($header);
                    },
                    CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($stream, &$bytes): int {
                        $bytes += strlen($chunk);
                        if ($bytes > self::MAX_BYTES) { return 0; }
                        return fwrite($stream, $chunk);
                    },
                ]);
                try {
                    $ok = curl_exec($curl);
                    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                } finally {
                    curl_close($curl);
                    fclose($stream);
                }
                if ($bytes > self::MAX_BYTES) { throw new \RuntimeException('Image exceeds the 12 MiB limit.'); }
                if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== '' && $hop < 3) {
                    $url = \WP_Http::make_absolute_url($location, $url);
                    continue;
                }
                if (!$ok || $status !== 200 || $bytes === 0) { throw new \RuntimeException('Image download failed or exceeded its timeout.'); }
                self::validateFile($path);
                return $path;
            }
            throw new \RuntimeException('Image has too many redirects.');
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        }
    }

    private static function validateFile(string $path): void
    {
        $info = @getimagesize($path);
        if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new \RuntimeException('Downloaded file is not a supported image.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS || max($info[0], $info[1]) > 12000) {
            throw new \RuntimeException('Image exceeds the API decoding safety limit (40 MP / 12000 pixels per side).');
        }
        $editor = wp_get_image_editor($path);
        if (is_wp_error($editor) || !$editor->get_size()) {
            throw new \RuntimeException('Image data could not be decoded completely.');
        }
    }

    /** Stage a complete ordered gallery; do not mutate the listing on partial failure. */
    public static function stage(array $images, int $dealer, int $listing, string $operation): array
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $created = [];
        $ordered = [];
        $current = $listing ? (array) get_post_meta($listing, 'car_images', true) : [];
        $deadline = microtime(true) + 90;
        try {
            foreach ($images as $index => $image) {
                if (isset($image['id'])) {
                    $id = $image['id'];
                    if (!in_array($id, array_map('intval', $current), true) || get_post_type($id) !== 'attachment') {
                        throw new \RuntimeException('An image ID must already belong to this listing.');
                    }
                } else {
                    $path = self::download($image['url'], $deadline);
                    try {
                        $mime = wp_get_image_mime($path);
                        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                        $id = media_handle_sideload(['name' => 'stock-' . wp_generate_uuid4() . '.' . $extensions[$mime], 'tmp_name' => $path], 0);
                    } finally { if (is_file($path)) { @unlink($path); } }
                    if (is_wp_error($id)) { throw new \RuntimeException('Image storage failed.'); }
                    $id = (int) $id;
                    $created[] = $id;
                    wp_update_post(['ID' => $id, 'post_author' => $dealer]);
                    update_post_meta($id, '_ag_dealer_upload', $operation);
                    // Reuse AutoAgora's optimizer, including its WebP fallback and derivative cleanup.
                    if ($mime !== 'image/webp' && function_exists('convert_to_webp_with_fallback')) {
                        convert_to_webp_with_fallback($id, wp_get_attachment_metadata($id));
                    }
                }
                $file = get_attached_file($id);
                $metadata = wp_get_attachment_metadata($id);
                if (!$file || !is_file($file) || empty($metadata['width']) || empty($metadata['height'])) {
                    throw new \RuntimeException('Image processing did not produce a complete image.');
                }
                $ordered[] = $id;
            }
            return ['ordered' => $ordered, 'created' => $created];
        } catch (\Throwable $error) {
            self::discard($created);
            throw new \RuntimeException('Photo ' . (($index ?? 0) + 1) . ': ' . $error->getMessage());
        }
    }
    public static function discard(array $ids): void
    {
        foreach ($ids as $id) {
            if (get_post_meta($id, '_ag_dealer_upload', true) && (int) get_post_field('post_parent', $id) === 0) {
                wp_delete_attachment($id, true);
            }
        }
    }
    public static function cleanup(): void
    {
        $query = new \WP_Query(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_parent' => 0,
            'meta_key' => '_ag_dealer_upload', 'posts_per_page' => 100, 'fields' => 'ids',
            'date_query' => [['before' => gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS), 'column' => 'post_date_gmt']]]);
        self::discard($query->posts);
    }
}
