<?php
/**
 * Maps CSV header columns to canonical dealership import fields.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class DealershipImportColumnMapper
{
    public const TARGET_DEALERSHIP_NAME = 'dealership_name_match';
    public const TARGET_MAPS_ADDRESS = 'dealer_maps_address';
    public const TARGET_MAPS_URL = 'dealer_maps_url';
    public const TARGET_WEBSITE = 'dealer_website';
    public const TARGET_INSTAGRAM = 'dealer_instagram';
    public const TARGET_FACEBOOK = 'dealer_facebook';

    /** @return list<string> */
    public static function targetFields(): array
    {
        return array(
            self::TARGET_DEALERSHIP_NAME,
            self::TARGET_MAPS_ADDRESS,
            self::TARGET_MAPS_URL,
            self::TARGET_WEBSITE,
            self::TARGET_INSTAGRAM,
            self::TARGET_FACEBOOK,
        );
    }

    /**
     * Default CSV header (trimmed) -> target field.
     *
     * @return array<string, string> header => target
     */
    private static function headerAliases(): array
    {
        return array(
            'dealership name'             => self::TARGET_DEALERSHIP_NAME,
            'google maps address'         => self::TARGET_MAPS_ADDRESS,
            'google maps embed tag'       => self::TARGET_MAPS_URL,
            'website url'                 => self::TARGET_WEBSITE,
            'instagram url'               => self::TARGET_INSTAGRAM,
            'facebook url'                => self::TARGET_FACEBOOK,
            'dealership_name_match'       => self::TARGET_DEALERSHIP_NAME,
            'dealer_maps_address'         => self::TARGET_MAPS_ADDRESS,
            'dealer_maps_url'             => self::TARGET_MAPS_URL,
            'dealer_website'              => self::TARGET_WEBSITE,
            'dealer_instagram'            => self::TARGET_INSTAGRAM,
            'dealer_facebook'             => self::TARGET_FACEBOOK,
        );
    }

    /**
     * @param list<string> $headers Trimmed header labels from CSV row 0.
     * @return array<string, string> target_field => header string (or empty if unmapped)
     */
    public static function guessMapping(array $headers): array
    {
        $aliases = self::headerAliases();
        $out = array();
        foreach (self::targetFields() as $target) {
            $out[$target] = '';
        }

        foreach ($headers as $h) {
            $key = mb_strtolower(trim($h), 'UTF-8');
            if ($key === '' || !isset($aliases[$key])) {
                continue;
            }
            $target = $aliases[$key];
            if ($out[$target] === '') {
                $out[$target] = $h;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $headers
     * @param list<string> $row
     * @param array<string, string> $mapping target => source header (empty = none)
     * @return array<string, string> target => cell value
     */
    public static function rowToTargets(array $headers, array $row, array $mapping): array
    {
        $header_to_index = array();
        foreach ($headers as $i => $label) {
            $header_to_index[$label] = $i;
        }

        $values = array();
        foreach (self::targetFields() as $target) {
            $values[$target] = '';
        }

        foreach ($mapping as $target => $source_header) {
            $source_header = trim((string) $source_header);
            if ($source_header === '' || !isset($header_to_index[$source_header])) {
                continue;
            }
            $i = $header_to_index[$source_header];
            $raw = isset($row[$i]) ? (string) $row[$i] : '';
            $raw = trim($raw);
            if (mb_strtolower($raw, 'UTF-8') === 'not listed') {
                $raw = '';
            }
            $values[$target] = $raw;
        }

        return $values;
    }
}
