<?php
/**
 * Discovers exportable columns for dealership users (core, derived, ACF, raw meta).
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class DealershipExportColumnCatalog
{
    /** Meta keys never offered for export. */
    private const META_DENYLIST = array(
        'session_tokens',
    );

    /**
     * @return list<array{id:string,header:string,source:string,key:string,group:string}>
     */
    public static function discover(): array
    {
        $columns = array();
        $columns = array_merge($columns, self::coreColumns());
        $columns = array_merge($columns, self::derivedColumns());
        $columns = array_merge($columns, self::acfColumns());
        $columns = array_merge($columns, self::rawMetaColumns());

        return $columns;
    }

    /**
     * @return array<string, array{id:string,header:string,source:string,key:string,group:string}>
     */
    public static function discoverKeyed(): array
    {
        $map = array();
        foreach (self::discover() as $col) {
            $map[$col['id']] = $col;
        }

        return $map;
    }

    /**
     * @param list<string> $selected_ids
     * @return list<array{id:string,header:string,source:string,key:string,group:string}>
     */
    public static function validateSelection(array $selected_ids): array
    {
        $keyed = self::discoverKeyed();
        $ordered = array();
        foreach ($selected_ids as $id) {
            $id = sanitize_text_field((string) $id);
            if ($id !== '' && isset($keyed[$id])) {
                $ordered[] = $keyed[$id];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string> meta_key values from meta:* columns
     */
    public static function selectedMetaKeys(array $columns): array
    {
        $keys = array();
        foreach ($columns as $col) {
            if (($col['source'] ?? '') === 'meta' && !empty($col['key'])) {
                $keys[] = $col['key'];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<array{id:string,header:string,source:string,key:string,group:string}>
     */
    private static function coreColumns(): array
    {
        $defs = array(
            array('key' => 'ID', 'header' => 'user_id'),
            array('key' => 'user_login', 'header' => 'user_login'),
            array('key' => 'user_email', 'header' => 'user_email'),
            array('key' => 'user_registered', 'header' => 'user_registered'),
            array('key' => 'display_name', 'header' => 'display_name'),
            array('key' => 'user_nicename', 'header' => 'user_nicename'),
            array('key' => 'user_url', 'header' => 'user_url'),
            array('key' => 'user_status', 'header' => 'user_status'),
        );
        $out = array();
        foreach ($defs as $d) {
            $out[] = array(
                'id'     => 'core:' . $d['header'],
                'header' => $d['header'],
                'source' => 'core',
                'key'    => $d['key'],
                'group'  => 'core',
            );
        }

        return $out;
    }

    /**
     * @return list<array{id:string,header:string,source:string,key:string,group:string}>
     */
    private static function derivedColumns(): array
    {
        return array(
            array(
                'id'     => 'derived:account_logo_url',
                'header' => 'account_logo_url',
                'source' => 'derived',
                'key'    => 'account_logo_url',
                'group'  => 'derived',
            ),
            array(
                'id'     => 'derived:published_car_listings_count',
                'header' => 'published_car_listings_count',
                'source' => 'derived',
                'key'    => 'published_car_listings_count',
                'group'  => 'derived',
            ),
            array(
                'id'     => 'derived:other_user_meta_json',
                'header' => 'other_user_meta_json',
                'source' => 'derived',
                'key'    => 'other_user_meta_json',
                'group'  => 'derived',
            ),
        );
    }

    /**
     * @return list<array{id:string,header:string,source:string,key:string,group:string}>
     */
    private static function acfColumns(): array
    {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return array();
        }

        $sample_user_id = self::firstDealershipUserId();
        if ($sample_user_id <= 0) {
            return array();
        }

        $groups = acf_get_field_groups(
            array(
                'user_id' => 'user_' . $sample_user_id,
            )
        );

        if (!is_array($groups)) {
            return array();
        }

        $out = array();
        $seen = array();
        foreach ($groups as $group) {
            $group_title = isset($group['title']) ? (string) $group['title'] : 'ACF';
            $parent = isset($group['key']) ? $group['key'] : (isset($group['ID']) ? $group['ID'] : null);
            if ($parent === null) {
                continue;
            }
            $fields = acf_get_fields($parent);
            if (!is_array($fields)) {
                continue;
            }
            foreach (self::flattenAcfFields($fields) as $field) {
                $name = $field['name'];
                if ($name === '') {
                    continue;
                }
                $id = 'acf:' . $name;
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $label = isset($field['label']) ? (string) $field['label'] : $name;
                $header = 'acf_' . $name;
                $out[] = array(
                    'id'     => $id,
                    'header' => $header,
                    'source' => 'acf',
                    'key'    => $name,
                    'group'  => 'acf — ' . $group_title . ' — ' . $label,
                );
            }
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $fields
     * @return list<array{name:string,label:string}>
     */
    private static function flattenAcfFields(array $fields, string $name_prefix = ''): array
    {
        $out = array();
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }
            $type = isset($field['type']) ? (string) $field['type'] : '';
            if (in_array($type, array('tab', 'message', 'accordion'), true)) {
                continue;
            }

            $name = $name_prefix !== '' ? $name_prefix . '_' . $field['name'] : (string) $field['name'];
            $label = isset($field['label']) ? (string) $field['label'] : $name;

            if ($type === 'group' && !empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                $out = array_merge($out, self::flattenAcfFields($field['sub_fields'], $name));
                continue;
            }

            if (in_array($type, array('repeater', 'flexible_content'), true)) {
                $out[] = array('name' => $name, 'label' => $label);
                continue;
            }

            if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                $out = array_merge($out, self::flattenAcfFields($field['sub_fields'], $name));
                continue;
            }

            $out[] = array('name' => $name, 'label' => $label);
        }

        return $out;
    }

    /**
     * @return list<array{id:string,header:string,source:string,key:string,group:string}>
     */
    private static function rawMetaColumns(): array
    {
        global $wpdb;

        $user_ids = get_users(
            array(
                'role'   => 'dealership',
                'number' => -1,
                'fields' => 'ID',
            )
        );
        if (!is_array($user_ids) || $user_ids === array()) {
            return array();
        }

        $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
        if ($user_ids === array()) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $sql = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                array(
                    "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE user_id IN ({$placeholders}) ORDER BY meta_key ASC",
                ),
                $user_ids
            )
        );

        $keys = $wpdb->get_col($sql);
        if (!is_array($keys)) {
            return array();
        }

        $acf_names = self::acfFieldNamesSet();

        $out = array();
        foreach ($keys as $meta_key) {
            $meta_key = (string) $meta_key;
            if ($meta_key === '' || self::shouldSkipRawMetaKey($meta_key, $acf_names)) {
                continue;
            }
            $out[] = array(
                'id'     => 'meta:' . $meta_key,
                'header' => 'meta_' . $meta_key,
                'source' => 'meta',
                'key'    => $meta_key,
                'group'  => 'user_meta_raw',
            );
        }

        return $out;
    }

    /**
     * Field names from ACF (same discovery as acfColumns) for de-duplicating raw meta keys.
     *
     * @return array<string, true>
     */
    private static function acfFieldNamesSet(): array
    {
        $set = array();
        foreach (self::acfColumns() as $col) {
            if (($col['source'] ?? '') === 'acf' && !empty($col['key'])) {
                $set[$col['key']] = true;
            }
        }

        return $set;
    }

    /**
     * Meta keys to strip from other_user_meta_json when those columns are also selected.
     *
     * @param list<array{id:string,header:string,source:string,key:string,group:string}> $columns
     * @return list<string>
     */
    public static function keysExcludedFromOtherMetaBlob(array $columns): array
    {
        $keys = array('session_tokens');
        foreach ($columns as $col) {
            $source = $col['source'] ?? '';
            $key = $col['key'] ?? '';
            if ($key === '') {
                continue;
            }
            if ($source === 'meta') {
                $keys[] = $key;
                continue;
            }
            if ($source === 'acf') {
                $keys[] = $key;
                $keys[] = '_' . $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string, true> $acf_names
     */
    private static function shouldSkipRawMetaKey(string $meta_key, array $acf_names): bool
    {
        if (in_array($meta_key, self::META_DENYLIST, true)) {
            return true;
        }

        if (preg_match('/^field_[a-f0-9]{8,}$/i', $meta_key)) {
            return true;
        }

        if (isset($acf_names[$meta_key])) {
            return true;
        }

        if (strpos($meta_key, '_') === 0 && isset($acf_names[ltrim($meta_key, '_')])) {
            return true;
        }

        return false;
    }

    private static function firstDealershipUserId(): int
    {
        $users = get_users(
            array(
                'role'   => 'dealership',
                'number' => 1,
                'fields' => 'ID',
            )
        );
        if (!is_array($users) || $users === array()) {
            return 0;
        }

        return (int) $users[0];
    }
}
