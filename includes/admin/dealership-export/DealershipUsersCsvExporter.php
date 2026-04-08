<?php
/**
 * Builds a CSV export of all users with the dealership role.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class DealershipUsersCsvExporter
{
    /** Meta keys surfaced as dedicated columns (excluded from other_user_meta_json). */
    private const EXPLICIT_META_KEYS = array(
        'first_name',
        'last_name',
        'phone_number',
        'secondary_phone',
        'dealership_name',
        'email_verified',
        'latest_email_verification_timestamp',
        'autoagora_dealer',
        '_account_logo_attachment_id',
        'notify_activity_milestones',
        'notify_7_day_reminders',
        'seller_average_rating',
        'seller_review_count',
        'favorite_cars',
        'car_form_templates',
    );

    /** Never include these keys in other_user_meta_json. */
    private const OTHER_META_DENYLIST = array(
        'session_tokens',
    );

    /**
     * Sends CSV download headers and writes UTF-8 BOM + rows to php://output.
     */
    public function sendDownloadResponse(): void
    {
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

        fputcsv($out, $this->getHeaderRow());

        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }
            fputcsv($out, $this->buildDataRow($user, $car_counts));
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
     * @return array<int, int> post_author => count
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
     * @return list<string>
     */
    private function getHeaderRow(): array
    {
        return array(
            'user_id',
            'user_login',
            'user_email',
            'user_registered',
            'display_name',
            'user_nicename',
            'user_url',
            'user_status',
            'first_name',
            'last_name',
            'phone_number',
            'secondary_phone',
            'dealership_name',
            'email_verified',
            'latest_email_verification_timestamp',
            'autoagora_dealer',
            '_account_logo_attachment_id',
            'account_logo_url',
            'notify_activity_milestones',
            'notify_7_day_reminders',
            'seller_average_rating',
            'seller_review_count',
            'favorite_cars',
            'car_form_templates',
            'published_car_listings_count',
            'other_user_meta_json',
        );
    }

    /**
     * @param array<int, int> $car_counts
     * @return list<string|int|float>
     */
    private function buildDataRow(WP_User $user, array $car_counts): array
    {
        $uid = (int) $user->ID;

        $logo_id = (int) get_user_meta($uid, '_account_logo_attachment_id', true);
        $logo_url = $logo_id > 0 ? (string) wp_get_attachment_url($logo_id) : '';

        return array(
            $uid,
            $user->user_login,
            $user->user_email,
            $user->user_registered,
            $user->display_name,
            $user->user_nicename,
            $user->user_url,
            (int) $user->user_status,
            $this->scalarMeta($uid, 'first_name'),
            $this->scalarMeta($uid, 'last_name'),
            $this->scalarMeta($uid, 'phone_number'),
            $this->scalarMeta($uid, 'secondary_phone'),
            $this->scalarMeta($uid, 'dealership_name'),
            $this->scalarMeta($uid, 'email_verified'),
            $this->scalarMeta($uid, 'latest_email_verification_timestamp'),
            $this->formatCellValue($this->resolveAutoagoraDealer($uid)),
            $logo_id > 0 ? (string) $logo_id : '',
            $logo_url,
            $this->scalarMeta($uid, 'notify_activity_milestones'),
            $this->scalarMeta($uid, 'notify_7_day_reminders'),
            $this->scalarMeta($uid, 'seller_average_rating'),
            $this->scalarMeta($uid, 'seller_review_count'),
            $this->formatMetaForDedicatedColumn($uid, 'favorite_cars'),
            $this->formatMetaForDedicatedColumn($uid, 'car_form_templates'),
            isset($car_counts[$uid]) ? $car_counts[$uid] : 0,
            $this->buildOtherUserMetaJson($uid),
        );
    }

    private function scalarMeta(int $user_id, string $key): string
    {
        $raw = get_user_meta($user_id, $key, true);
        if ($raw === '' || $raw === null) {
            return '';
        }
        if (is_array($raw) || is_object($raw)) {
            return wp_json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $raw;
    }

    private function formatMetaForDedicatedColumn(int $user_id, string $key): string
    {
        $raw = get_user_meta($user_id, $key, true);
        if ($raw === '' || $raw === null) {
            return '';
        }

        return $this->formatCellValue($raw);
    }

    /**
     * @param mixed $value
     */
    private function formatCellValue($value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            $encoded = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * @return mixed
     */
    private function resolveAutoagoraDealer(int $user_id)
    {
        if (function_exists('get_field')) {
            $acf = get_field('autoagora_dealer', 'user_' . $user_id);
            if ($acf !== null && $acf !== false && $acf !== '') {
                return $acf;
            }
        }

        return get_user_meta($user_id, 'autoagora_dealer', true);
    }

    private function buildOtherUserMetaJson(int $user_id): string
    {
        $all_meta = get_user_meta($user_id);
        if (!is_array($all_meta)) {
            return '{}';
        }

        $exclude = array_merge(self::EXPLICIT_META_KEYS, self::OTHER_META_DENYLIST);
        $exclude_lookup = array_fill_keys($exclude, true);

        $other = array();
        foreach ($all_meta as $meta_key => $values) {
            if (isset($exclude_lookup[$meta_key])) {
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
