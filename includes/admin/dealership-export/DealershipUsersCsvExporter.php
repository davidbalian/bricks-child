<?php
/**
 * Builds a CSV export of dealership users with configurable columns.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DealershipExportColumnCatalog.php';

final class DealershipUsersCsvExporter
{
    /**
     * @param list<array{id:string,header:string,source:string,key:string,group:string}> $columns
     */
    public function sendDownloadResponse(array $columns): void
    {
        if ($columns === array()) {
            return;
        }

        $filename = 'dealerships-export-' . gmdate('Y-m-d-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        fprintf($out, "\xEF\xBB\xBF");

        $users = $this->loadDealershipUsers();
        $author_ids = wp_list_pluck($users, 'ID');
        $car_counts = $this->fetchPublishedCarCountsByAuthor($author_ids);
        $other_exclude = DealershipExportColumnCatalog::keysExcludedFromOtherMetaBlob($columns);

        $headers = array();
        foreach ($columns as $col) {
            $headers[] = $col['header'];
        }
        fputcsv($out, $headers);

        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }
            $row = array();
            foreach ($columns as $col) {
                $row[] = $this->resolveCell($user, $col, $car_counts, $other_exclude);
            }
            fputcsv($out, $row);
        }

        fclose($out);
    }

    /**
     * @return list<WP_User>
     */
    private function loadDealershipUsers(): array
    {
        $users = get_users(
            array(
                'role'   => 'dealership',
                'number' => -1,
                'fields' => 'all',
            )
        );

        return is_array($users) ? $users : array();
    }

    /**
     * @param list<int> $user_ids
     * @return array<int, int>
     */
    private function fetchPublishedCarCountsByAuthor(array $user_ids): array
    {
        $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
        if ($user_ids === array()) {
            return array();
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $query = "SELECT post_author, COUNT(*) AS cnt FROM {$wpdb->posts} 
            WHERE post_type = 'car' AND post_status = 'publish' AND post_author IN ({$placeholders}) 
            GROUP BY post_author";
        $sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($query), $user_ids));

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }

        $map = array();
        foreach ($rows as $row) {
            $aid = isset($row['post_author']) ? (int) $row['post_author'] : 0;
            $map[$aid] = isset($row['cnt']) ? (int) $row['cnt'] : 0;
        }

        return $map;
    }

    /**
     * @param array<int, int> $car_counts
     * @param list<string> $other_exclude_keys
     */
    private function resolveCell(WP_User $user, array $col, array $car_counts, array $other_exclude_keys): string
    {
        $uid = (int) $user->ID;
        $source = $col['source'] ?? '';
        $key = $col['key'] ?? '';

        if ($source === 'core') {
            return $this->formatCoreValue($user, $key);
        }
        if ($source === 'derived') {
            return $this->resolveDerived($uid, $key, $car_counts, $other_exclude_keys);
        }
        if ($source === 'meta') {
            return $this->formatMetaValue(get_user_meta($uid, $key, true));
        }
        if ($source === 'acf') {
            if (function_exists('get_field')) {
                return $this->formatMetaValue(get_field($key, 'user_' . $uid));
            }

            return $this->formatMetaValue(get_user_meta($uid, $key, true));
        }

        return '';
    }

    private function formatCoreValue(WP_User $user, string $key): string
    {
        if ($key === 'ID') {
            return (string) (int) $user->ID;
        }
        if ($key === 'user_status') {
            return (string) (int) $user->user_status;
        }
        if (!property_exists($user, $key)) {
            return '';
        }

        return (string) $user->$key;
    }

    /**
     * @param array<int, int> $car_counts
     * @param list<string> $other_exclude_keys
     */
    private function resolveDerived(
        int $user_id,
        string $key,
        array $car_counts,
        array $other_exclude_keys
    ): string {
        if ($key === 'published_car_listings_count') {
            return (string) (isset($car_counts[$user_id]) ? $car_counts[$user_id] : 0);
        }
        if ($key === 'account_logo_url') {
            $logo_id = (int) get_user_meta($user_id, '_account_logo_attachment_id', true);

            return $logo_id > 0 ? (string) wp_get_attachment_url($logo_id) : '';
        }
        if ($key === 'other_user_meta_json') {
            return $this->buildOtherUserMetaJson($user_id, $other_exclude_keys);
        }

        return '';
    }

    /**
     * @param mixed $raw
     */
    private function formatMetaValue($raw): string
    {
        if ($raw === '' || $raw === null) {
            return '';
        }
        if (is_array($raw) || is_object($raw)) {
            $encoded = wp_json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : '';
        }
        if (is_bool($raw)) {
            return $raw ? '1' : '0';
        }

        return (string) $raw;
    }

    /**
     * @param list<string> $exclude_keys
     */
    private function buildOtherUserMetaJson(int $user_id, array $exclude_keys): string
    {
        $all_meta = get_user_meta($user_id);
        if (!is_array($all_meta)) {
            return '{}';
        }

        $exclude_lookup = array_fill_keys($exclude_keys, true);

        $other = array();
        foreach ($all_meta as $meta_key => $values) {
            if (isset($exclude_lookup[$meta_key])) {
                continue;
            }
            if (preg_match('/^field_[a-f0-9]{8,}$/i', $meta_key)) {
                continue;
            }
            if (!is_array($values)) {
                continue;
            }
            $other[$meta_key] = $this->normalizeMetaValues($values);
        }

        $encoded = wp_json_encode($other, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : '{}';
    }

    /**
     * @param list<mixed> $values
     * @return mixed
     */
    private function normalizeMetaValues(array $values)
    {
        $unpacked = array();
        foreach ($values as $v) {
            $unpacked[] = maybe_unserialize($v);
        }
        if (count($unpacked) === 1) {
            return $unpacked[0];
        }

        return $unpacked;
    }
}
