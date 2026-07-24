<?php
/**
 * Validates, previews, and applies dealer-profile workbook rows.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgoraDealerProfileImportRunner
{
    private const MAX_TITLE_LENGTH = 200;
    private const MAX_TEXT_LENGTH = 500;
    private const MAX_TEXTAREA_LENGTH = 5000;
    private const MAX_URL_LENGTH = 1000;

    /** @var list<string> */
    private const EXPECTED_FIELDS = array(
        'import_action',
        'post_type',
        'post_status',
        'qa_status',
        'confidence_score',
        'dealer_name',
        'dealer_city',
        'dealer_city_slug',
        'dealer_district',
        'dealer_address',
        'dealer_maps_address',
        'dealer_maps_url',
        'dealer_website',
        'dealer_instagram',
        'dealer_facebook',
        'dealer_phone',
        'secondary_phone',
        'dealer_whatsapp',
        'dealer_email',
        'dealer_logo_url',
        'dealer_short_description',
        'dealer_opening_hours',
        'dealer_services',
        'dealer_languages',
        'dealer_source_name',
        'dealer_source_url',
        'dealer_import_source_id',
        'dealer_claim_status',
        'dealer_claimed_user_id',
        'dealer_indexable',
        'dealer_last_verified_at',
        'notes',
        'additional_source_urls',
        'dedupe_key',
    );

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @return array{rows:list<array<string,mixed>>,summary:array<string,int>}|WP_Error
     */
    public static function prepareRows(array $headers, array $rows)
    {
        $header_map = self::buildHeaderMap($headers);
        if (is_wp_error($header_map)) {
            return $header_map;
        }

        foreach (array('dealer_name', 'dealer_import_source_id') as $required) {
            if (!isset($header_map[$required])) {
                return new WP_Error(
                    'dealer_profile_import_missing_header',
                    sprintf(
                        /* translators: %s: required column name */
                        __('The worksheet is missing the required "%s" column.', 'bricks-child'),
                        $required
                    )
                );
            }
        }

        $prepared = array();
        $seen_source_ids = array();
        $summary = array(
            'total'        => 0,
            'valid'        => 0,
            'invalid'      => 0,
            'ready'        => 0,
            'needs_review' => 0,
            'with_logos'   => 0,
            'public_quality' => 0,
        );

        foreach ($rows as $row_index => $values) {
            ++$summary['total'];
            $row = array();
            foreach (self::EXPECTED_FIELDS as $field) {
                $column = isset($header_map[$field]) ? $header_map[$field] : null;
                $row[$field] = $column !== null && isset($values[$column])
                    ? self::normalizeEmptyValue((string) $values[$column])
                    : '';
            }

            $errors = array();
            $row['dealer_name'] = self::sanitizeLimitedText($row['dealer_name'], self::MAX_TITLE_LENGTH);
            $row['dealer_import_source_id'] = self::sanitizeLimitedText(
                $row['dealer_import_source_id'],
                self::MAX_TEXT_LENGTH
            );
            $row['dedupe_key'] = self::sanitizeLimitedText($row['dedupe_key'], self::MAX_TEXT_LENGTH);
            $row['qa_status'] = sanitize_key($row['qa_status']);
            $row['confidence_score'] = max(0, min(100, (int) $row['confidence_score']));

            if ($row['dealer_name'] === '') {
                $errors[] = __('Missing dealer name.', 'bricks-child');
            }
            if ($row['dealer_import_source_id'] === '') {
                $errors[] = __('Missing import source ID.', 'bricks-child');
            } elseif (isset($seen_source_ids[$row['dealer_import_source_id']])) {
                $errors[] = sprintf(
                    /* translators: %d: first duplicate row number */
                    __('Duplicate import source ID; first seen on worksheet row %d.', 'bricks-child'),
                    $seen_source_ids[$row['dealer_import_source_id']]
                );
            } else {
                $seen_source_ids[$row['dealer_import_source_id']] = $row_index + 2;
            }

            if ($row['post_type'] !== ''
                && sanitize_key($row['post_type']) !== AUTOAGORA_DEALER_PROFILE_POST_TYPE
            ) {
                $errors[] = __('The post_type value is not dealer_profile.', 'bricks-child');
            }

            $row = self::sanitizePreparedRow($row);
            $row['_worksheet_row'] = $row_index + 2;
            $row['_errors'] = $errors;
            $row['_valid'] = empty($errors);

            if ($row['_valid']) {
                ++$summary['valid'];
            } else {
                ++$summary['invalid'];
            }
            if ($row['qa_status'] === 'ready') {
                ++$summary['ready'];
            } else {
                ++$summary['needs_review'];
            }
            if ($row['dealer_logo_url'] !== '') {
                ++$summary['with_logos'];
            }
            if ($row['_valid'] && self::rowHasPublicQuality($row)) {
                ++$summary['public_quality'];
            }

            $prepared[] = $row;
        }

        return array(
            'rows'    => $prepared,
            'summary' => $summary,
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{create:int,update:int,protected:int,conflict:int,invalid:int}
     */
    public static function classifyRows(array $rows): array
    {
        $counts = array(
            'create'    => 0,
            'update'    => 0,
            'protected' => 0,
            'conflict'  => 0,
            'invalid'   => 0,
        );

        foreach ($rows as $row) {
            $classification = self::classifyRow($row);
            $action = isset($classification['action']) ? $classification['action'] : 'invalid';
            if (isset($counts[$action])) {
                ++$counts[$action];
            } else {
                ++$counts['invalid'];
            }
        }

        return $counts;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{action:string,post_id:int,message:string}
     */
    public static function classifyRow(array $row): array
    {
        if (empty($row['_valid'])) {
            return array(
                'action'  => 'invalid',
                'post_id' => 0,
                'message' => implode(' ', isset($row['_errors']) ? (array) $row['_errors'] : array()),
            );
        }

        $matches = self::findExistingProfileIds(
            (string) $row['dealer_import_source_id'],
            (string) $row['dedupe_key']
        );
        if (count($matches) > 1) {
            return array(
                'action'  => 'conflict',
                'post_id' => 0,
                'message' => __('Multiple existing profiles match this row.', 'bricks-child'),
            );
        }

        if (count($matches) === 1) {
            $post_id = (int) reset($matches);
            $claim_status = function_exists('autoagora_dealer_profile_get_claim_status')
                ? autoagora_dealer_profile_get_claim_status($post_id)
                : sanitize_key((string) get_post_meta($post_id, 'dealer_claim_status', true));
            $claimed_user_id = absint(get_post_meta($post_id, 'dealer_claimed_user_id', true));
            if (($claim_status !== '' && $claim_status !== 'unclaimed') || $claimed_user_id > 0) {
                return array(
                    'action'  => 'protected',
                    'post_id' => $post_id,
                    'message' => __('The existing profile is claimed or has a claim in progress.', 'bricks-child'),
                );
            }

            return array(
                'action'  => 'update',
                'post_id' => $post_id,
                'message' => __('Update existing unclaimed profile.', 'bricks-child'),
            );
        }

        return array(
            'action'  => 'create',
            'post_id' => 0,
            'message' => __('Create new unclaimed profile.', 'bricks-child'),
        );
    }

    /**
     * @param array<string,mixed> $row
     * @param array{publish_mode:string,index_mode:string} $options
     * @return array{action:string,post_id:int,message:string}
     */
    public static function applyRow(array $row, array $options): array
    {
        $classification = self::classifyRow($row);
        if (!in_array($classification['action'], array('create', 'update'), true)) {
            return $classification;
        }

        $post_status = self::resolvePostStatus($row, $options);
        $post_data = array(
            'post_type'   => AUTOAGORA_DEALER_PROFILE_POST_TYPE,
            'post_title'  => (string) $row['dealer_name'],
            'post_status' => $post_status,
        );

        if ($classification['action'] === 'update') {
            $post_data['ID'] = (int) $classification['post_id'];
        }

        $post_id = wp_insert_post(wp_slash($post_data), true);
        if (is_wp_error($post_id)) {
            return array(
                'action'  => 'failed',
                'post_id' => 0,
                'message' => $post_id->get_error_message(),
            );
        }
        $post_id = (int) $post_id;

        self::updatePublicMeta($post_id, $row);
        self::updateAuditMeta($post_id, $row);

        update_post_meta($post_id, 'dealer_city_slug', autoagora_dealer_profile_normalize_city_slug((string) $row['dealer_city']));
        update_post_meta(
            $post_id,
            'dealer_indexable',
            self::resolveIndexable($row, $options, $post_status) ? '1' : '0'
        );

        if ($classification['action'] === 'create') {
            update_post_meta($post_id, 'dealer_claim_status', 'unclaimed');
            delete_post_meta($post_id, 'dealer_claimed_user_id');
        }

        clean_post_cache($post_id);

        return array(
            'action'  => $classification['action'] === 'create' ? 'created' : 'updated',
            'post_id' => $post_id,
            'message' => $classification['action'] === 'create'
                ? __('Profile created.', 'bricks-child')
                : __('Profile updated.', 'bricks-child'),
        );
    }

    /**
     * @param list<string> $headers
     * @return array<string,int>|WP_Error
     */
    private static function buildHeaderMap(array $headers)
    {
        $allowed = array_fill_keys(self::EXPECTED_FIELDS, true);
        $map = array();
        foreach ($headers as $index => $header) {
            $normalized = self::normalizeHeader((string) $header);
            if ($normalized === '' || !isset($allowed[$normalized])) {
                continue;
            }
            if (isset($map[$normalized])) {
                return new WP_Error(
                    'dealer_profile_import_duplicate_header',
                    sprintf(
                        /* translators: %s: duplicate column name */
                        __('The worksheet contains the "%s" column more than once.', 'bricks-child'),
                        $normalized
                    )
                );
            }
            $map[$normalized] = (int) $index;
        }

        return $map;
    }

    private static function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
        $header = self::lower((string) $header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);

        return trim((string) $header, '_');
    }

    private static function normalizeEmptyValue(string $value): string
    {
        $value = trim($value);
        $lower = self::lower($value);

        return in_array($lower, array('', 'not listed', 'n/a', 'null'), true) ? '' : $value;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function sanitizePreparedRow(array $row): array
    {
        foreach (array(
            'dealer_city',
            'dealer_district',
            'dealer_address',
            'dealer_maps_address',
            'dealer_phone',
            'secondary_phone',
            'dealer_whatsapp',
            'dealer_source_name',
            'dealer_last_verified_at',
        ) as $field) {
            $row[$field] = self::sanitizeLimitedText((string) $row[$field], self::MAX_TEXT_LENGTH);
        }

        foreach (array(
            'dealer_short_description',
            'dealer_opening_hours',
            'dealer_services',
            'dealer_languages',
            'notes',
        ) as $field) {
            $row[$field] = self::sanitizeLimitedTextarea((string) $row[$field], self::MAX_TEXTAREA_LENGTH);
        }

        foreach (array(
            'dealer_website',
            'dealer_instagram',
            'dealer_facebook',
            'dealer_source_url',
            'dealer_logo_url',
        ) as $field) {
            $row[$field] = self::sanitizeUrl((string) $row[$field]);
        }

        $row['dealer_maps_url'] = autoagora_sanitize_dealer_profile_maps_value((string) $row['dealer_maps_url']);
        $row['dealer_email'] = autoagora_sanitize_dealer_profile_email((string) $row['dealer_email']);
        $row['additional_source_urls'] = self::sanitizeUrlList((string) $row['additional_source_urls']);
        $row['post_status'] = sanitize_key((string) $row['post_status']);
        $row['post_type'] = sanitize_key((string) $row['post_type']);
        $row['dealer_indexable'] = autoagora_sanitize_dealer_profile_bool($row['dealer_indexable']);

        return $row;
    }

    private static function sanitizeLimitedText(string $value, int $max_length): string
    {
        return self::limitString(sanitize_text_field($value), $max_length);
    }

    private static function sanitizeLimitedTextarea(string $value, int $max_length): string
    {
        return self::limitString(sanitize_textarea_field($value), $max_length);
    }

    private static function sanitizeUrl(string $value): string
    {
        $url = autoagora_sanitize_dealer_profile_url($value);

        return self::limitString($url, self::MAX_URL_LENGTH);
    }

    private static function sanitizeUrlList(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $candidates = $decoded;
        } else {
            $candidates = preg_split('/[\r\n,]+/', $value);
        }
        if (!is_array($candidates)) {
            return '';
        }

        $urls = array();
        foreach (array_slice($candidates, 0, 20) as $candidate) {
            $url = self::sanitizeUrl(is_scalar($candidate) ? (string) $candidate : '');
            if ($url !== '') {
                $urls[$url] = true;
            }
        }

        return self::limitString(implode("\n", array_keys($urls)), self::MAX_TEXTAREA_LENGTH);
    }

    private static function limitString(string $value, int $max_length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max_length, 'UTF-8');
        }

        return substr($value, 0, $max_length);
    }

    private static function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

    /**
     * @return list<int>
     */
    private static function findExistingProfileIds(string $source_id, string $dedupe_key): array
    {
        $matches = self::findByMeta('dealer_import_source_id', $source_id);
        if (empty($matches) && $dedupe_key !== '') {
            $matches = self::findByMeta('dealer_dedupe_key', $dedupe_key);
        }

        return array_values(array_unique(array_map('intval', $matches)));
    }

    /**
     * @return list<int>
     */
    private static function findByMeta(string $key, string $value): array
    {
        if ($value === '') {
            return array();
        }

        $ids = get_posts(
            array(
                'post_type'              => AUTOAGORA_DEALER_PROFILE_POST_TYPE,
                'post_status'            => array('publish', 'draft', 'pending', 'private', 'future', 'trash'),
                'posts_per_page'         => 2,
                'fields'                 => 'ids',
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'     => $key,
                        'value'   => $value,
                        'compare' => '=',
                    ),
                ),
            )
        );

        return is_array($ids) ? array_map('intval', $ids) : array();
    }

    /**
     * @param array<string,mixed> $row
     * @param array{publish_mode:string,index_mode:string} $options
     */
    private static function resolvePostStatus(array $row, array $options): string
    {
        if (isset($options['publish_mode']) && $options['publish_mode'] === 'all') {
            return 'publish';
        }

        return isset($row['post_status']) && $row['post_status'] === 'publish'
            ? 'publish'
            : 'draft';
    }

    /**
     * @param array<string,mixed> $row
     * @param array{publish_mode:string,index_mode:string} $options
     */
    private static function resolveIndexable(array $row, array $options, string $post_status): bool
    {
        if ($post_status !== 'publish') {
            return false;
        }

        $index_mode = isset($options['index_mode']) ? $options['index_mode'] : 'quality';
        if ($index_mode === 'none') {
            return false;
        }
        if ($index_mode === 'workbook') {
            return !empty($row['dealer_indexable']);
        }

        return self::rowHasPublicQuality($row);
    }

    /**
     * Mirrors the public-quality gate used by dealer profile pages.
     *
     * @param array<string,mixed> $row
     */
    private static function rowHasPublicQuality(array $row): bool
    {
        $location = trim(
            (string) $row['dealer_city']
            . (string) $row['dealer_district']
            . (string) $row['dealer_address']
            . (string) $row['dealer_maps_address']
        );
        $presence = trim(
            (string) $row['dealer_website']
            . (string) $row['dealer_maps_url']
            . (string) $row['dealer_phone']
            . (string) $row['secondary_phone']
            . (string) $row['dealer_whatsapp']
            . (string) $row['dealer_email']
            . (string) $row['dealer_instagram']
            . (string) $row['dealer_facebook']
        );

        return trim((string) $row['dealer_name']) !== ''
            && $location !== ''
            && $presence !== '';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function updatePublicMeta(int $post_id, array $row): void
    {
        $fields = array(
            'dealer_city',
            'dealer_district',
            'dealer_address',
            'dealer_maps_address',
            'dealer_maps_url',
            'dealer_website',
            'dealer_instagram',
            'dealer_facebook',
            'dealer_phone',
            'secondary_phone',
            'dealer_whatsapp',
            'dealer_email',
            'dealer_logo_url',
            'dealer_short_description',
            'dealer_opening_hours',
            'dealer_services',
            'dealer_languages',
            'dealer_source_name',
            'dealer_source_url',
            'dealer_last_verified_at',
        );

        foreach ($fields as $field) {
            $value = isset($row[$field]) ? $row[$field] : '';
            if ($value === '' || $value === null) {
                continue;
            }
            update_post_meta($post_id, $field, $value);
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function updateAuditMeta(int $post_id, array $row): void
    {
        update_post_meta($post_id, 'dealer_import_source_id', (string) $row['dealer_import_source_id']);
        update_post_meta($post_id, 'dealer_import_qa_status', (string) $row['qa_status']);
        update_post_meta($post_id, 'dealer_import_confidence_score', (int) $row['confidence_score']);

        $optional = array(
            'dealer_import_notes'          => 'notes',
            'dealer_additional_source_urls'=> 'additional_source_urls',
            'dealer_dedupe_key'            => 'dedupe_key',
        );
        foreach ($optional as $meta_key => $row_key) {
            $value = isset($row[$row_key]) ? (string) $row[$row_key] : '';
            if ($value !== '') {
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }
}
