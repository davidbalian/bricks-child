<?php
/**
 * Parses CSV, builds preview rows, applies updates to dealership users.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DealershipImportColumnMapper.php';
require_once __DIR__ . '/DealershipImportUserResolver.php';

final class DealershipImportRunner
{
    private const URL_MAX_LEN = 500;
    private const MAPS_ADDRESS_MAX = 500;
    private const MAPS_URL_MAX = 1000;

    /** @var list<string> */
    private static function dataFieldKeys(): array
    {
        return array(
            DealershipImportColumnMapper::TARGET_MAPS_ADDRESS,
            DealershipImportColumnMapper::TARGET_MAPS_URL,
            DealershipImportColumnMapper::TARGET_WEBSITE,
            DealershipImportColumnMapper::TARGET_INSTAGRAM,
            DealershipImportColumnMapper::TARGET_FACEBOOK,
        );
    }

    /**
     * @return array{headers:list<string>,rows:list<list<string>>}|array{error:string}
     */
    public static function parseCsvFile(string $absolute_path): array
    {
        if (!is_readable($absolute_path)) {
            return array('error' => __('Cannot read uploaded file.', 'bricks-child'));
        }
        $h = fopen($absolute_path, 'rb');
        if ($h === false) {
            return array('error' => __('Cannot open uploaded file.', 'bricks-child'));
        }
        $headers = fgetcsv($h);
        if (!is_array($headers) || $headers === array()) {
            fclose($h);

            return array('error' => __('CSV has no header row.', 'bricks-child'));
        }
        $headers = array_map(
            static function ($c) {
                return trim((string) $c);
            },
            $headers
        );
        $rows = array();
        while (($row = fgetcsv($h)) !== false) {
            if (!is_array($row)) {
                continue;
            }
            if (!self::rowHasAnyContent($row)) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($h);

        return array('headers' => $headers, 'rows' => $rows);
    }

    /**
     * @param list<string> $row
     */
    private static function rowHasAnyContent(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $data_rows
     * @param array<string, string> $mapping
     * @return list<array<string,mixed>>
     */
    public static function buildPreview(array $headers, array $data_rows, array $mapping): array
    {
        $resolver = new DealershipImportUserResolver();
        $preview = array();
        foreach ($data_rows as $idx => $row) {
            $targets = DealershipImportColumnMapper::rowToTargets($headers, $row, $mapping);
            $name = $targets[DealershipImportColumnMapper::TARGET_DEALERSHIP_NAME];
            $resolved = $resolver->resolve($name);
            $entry = array(
                'row_index'   => $idx,
                'name_match'  => $name,
                'status'      => $resolved['status'],
                'user_id'     => isset($resolved['user_id']) ? (int) $resolved['user_id'] : 0,
                'ambiguous_ids' => isset($resolved['user_ids']) ? $resolved['user_ids'] : array(),
                'fields'      => array(),
            );
            $uid = $entry['user_id'];
            foreach (self::dataFieldKeys() as $key) {
                $proposed_raw = isset($targets[$key]) ? (string) $targets[$key] : '';
                $current = $uid > 0 ? self::getFieldString($uid, self::acfKeyForTarget($key)) : '';
                $validation = self::validateAndSanitize($key, $proposed_raw);
                $will_update = $uid > 0
                    && $resolved['status'] === 'matched'
                    && $proposed_raw !== ''
                    && $validation['ok']
                    && $validation['sanitized'] !== $current;
                $entry['fields'][$key] = array(
                    'current'     => $current,
                    'proposed_raw'=> $proposed_raw,
                    'sanitized'   => $validation['sanitized'],
                    'ok'          => $validation['ok'],
                    'warning'     => $validation['warning'],
                    'will_update' => $will_update,
                );
            }
            $preview[] = $entry;
        }

        return $preview;
    }

    private static function acfKeyForTarget(string $target): string
    {
        $map = array(
            DealershipImportColumnMapper::TARGET_MAPS_ADDRESS => 'dealer_maps_address',
            DealershipImportColumnMapper::TARGET_MAPS_URL     => 'dealer_maps_url',
            DealershipImportColumnMapper::TARGET_WEBSITE      => 'dealer_website',
            DealershipImportColumnMapper::TARGET_INSTAGRAM    => 'dealer_instagram',
            DealershipImportColumnMapper::TARGET_FACEBOOK     => 'dealer_facebook',
        );

        return isset($map[$target]) ? $map[$target] : $target;
    }

    private static function getFieldString(int $user_id, string $acf_key): string
    {
        if (function_exists('get_field')) {
            $v = get_field($acf_key, 'user_' . $user_id);
            if ($v === null || $v === false) {
                $v = '';
            }
            if (is_array($v) || is_object($v)) {
                $j = wp_json_encode($v);

                return $j !== false ? $j : '';
            }

            return (string) $v;
        }

        return (string) get_user_meta($user_id, $acf_key, true);
    }

    /**
     * @return array{ok:bool,sanitized:string,warning:string}
     */
    private static function validateAndSanitize(string $target, string $value): array
    {
        $warning = '';
        if ($value === '') {
            return array('ok' => true, 'sanitized' => '', 'warning' => '');
        }
        if ($target === DealershipImportColumnMapper::TARGET_MAPS_ADDRESS) {
            $s = sanitize_text_field($value);
            if (strlen($s) > self::MAPS_ADDRESS_MAX) {
                $warning = sprintf(
                    /* translators: %d: max length */
                    __('Address truncated to %d characters.', 'bricks-child'),
                    self::MAPS_ADDRESS_MAX
                );
                $s = substr($s, 0, self::MAPS_ADDRESS_MAX);
            }

            return array('ok' => true, 'sanitized' => $s, 'warning' => $warning);
        }
        if ($target === DealershipImportColumnMapper::TARGET_MAPS_URL) {
            return self::validateMapsUrlValue($value);
        }

        return self::validateUrlField($value);
    }

    /**
     * @return array{ok:bool,sanitized:string,warning:string}
     */
    private static function validateMapsUrlValue(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return array('ok' => true, 'sanitized' => '', 'warning' => '');
        }
        $warning = '';
        if (stripos($value, '<iframe') !== false) {
            $s = self::sanitizeMapsIframe($value);
            if ($s === '') {
                return array(
                    'ok'        => false,
                    'sanitized' => '',
                    'warning'   => __('Maps embed HTML was removed by security rules.', 'bricks-child'),
                );
            }
        } else {
            $url = self::validateUrlField($value);
            if (!$url['ok']) {
                return $url;
            }
            $s = $url['sanitized'];
        }
        if (strlen($s) > self::MAPS_URL_MAX) {
            $warning = sprintf(
                /* translators: %d: max length */
                __('Maps embed/URL truncated to %d characters.', 'bricks-child'),
                self::MAPS_URL_MAX
            );
            $s = substr($s, 0, self::MAPS_URL_MAX);
        }

        return array('ok' => true, 'sanitized' => $s, 'warning' => $warning);
    }

    private static function sanitizeMapsIframe(string $html): string
    {
        $allowed = array(
            'iframe' => array(
                'src'             => true,
                'width'           => true,
                'height'          => true,
                'style'           => true,
                'allowfullscreen' => true,
                'loading'         => true,
                'referrerpolicy'  => true,
                'title'           => true,
                'frameborder'     => true,
                'class'           => true,
            ),
        );

        return wp_kses($html, $allowed);
    }

    /**
     * @return array{ok:bool,sanitized:string,warning:string}
     */
    private static function validateUrlField(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return array('ok' => true, 'sanitized' => '', 'warning' => '');
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return array(
                'ok'        => false,
                'sanitized' => '',
                'warning'   => __('Invalid URL (must be http or https).', 'bricks-child'),
            );
        }
        $p = wp_parse_url($value);
        if (!isset($p['scheme']) || !in_array($p['scheme'], array('http', 'https'), true)) {
            return array(
                'ok'        => false,
                'sanitized' => '',
                'warning'   => __('URL must use http or https.', 'bricks-child'),
            );
        }
        if (strlen($value) > self::URL_MAX_LEN) {
            return array(
                'ok'        => false,
                'sanitized' => '',
                'warning'   => sprintf(
                    /* translators: %d: max length */
                    __('URL exceeds %d characters.', 'bricks-child'),
                    self::URL_MAX_LEN
                ),
            );
        }

        return array('ok' => true, 'sanitized' => esc_url_raw($value), 'warning' => '');
    }

    /**
     * @param array<string, string> $targets from rowToTargets
     * @return array{updated:int,messages:list<string>}
     */
    public static function applyRow(int $user_id, array $targets): array
    {
        $updated = 0;
        $messages = array();
        foreach (self::dataFieldKeys() as $key) {
            $raw = isset($targets[$key]) ? trim((string) $targets[$key]) : '';
            if ($raw === '') {
                continue;
            }
            $acf_key = self::acfKeyForTarget($key);
            $v = self::validateAndSanitize($key, $raw);
            if (!$v['ok']) {
                $messages[] = $acf_key . ': ' . $v['warning'];

                continue;
            }
            $san = $v['sanitized'];
            if (function_exists('update_field')) {
                update_field($acf_key, $san, 'user_' . $user_id);
            } else {
                update_user_meta($user_id, $acf_key, $san);
            }
            ++$updated;
        }

        return array('updated' => $updated, 'messages' => $messages);
    }
}
