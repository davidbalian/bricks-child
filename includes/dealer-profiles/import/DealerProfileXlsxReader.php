<?php
/**
 * Minimal, guarded XLSX reader for dealer-profile imports.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgoraDealerProfileXlsxReader
{
    private const MAX_ARCHIVE_ENTRIES = 500;
    private const MAX_TOTAL_UNCOMPRESSED_BYTES = 67108864;
    private const MAX_XML_BYTES = 33554432;
    private const MAX_ROWS = 2000;
    private const MAX_COLUMNS = 100;
    private const MAX_CELL_LENGTH = 10000;

    /**
     * @return array{headers:list<string>,rows:list<list<string>>}|WP_Error
     */
    public static function readSheet(string $path, string $sheet_name)
    {
        if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) {
            return new WP_Error(
                'dealer_profile_xlsx_requirements',
                __('The server needs the PHP ZIP and DOM extensions to read Excel files.', 'bricks-child')
            );
        }

        if (!is_readable($path) || !is_file($path)) {
            return new WP_Error(
                'dealer_profile_xlsx_unreadable',
                __('The uploaded Excel file cannot be read.', 'bricks-child')
            );
        }

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::RDONLY);
        if ($opened !== true) {
            return new WP_Error(
                'dealer_profile_xlsx_invalid_zip',
                __('The uploaded file is not a valid XLSX workbook.', 'bricks-child')
            );
        }

        try {
            $archive_error = self::validateArchive($zip);
            if (is_wp_error($archive_error)) {
                return $archive_error;
            }

            $workbook_xml = self::readArchiveEntry($zip, 'xl/workbook.xml');
            $relationships_xml = self::readArchiveEntry($zip, 'xl/_rels/workbook.xml.rels');
            if (is_wp_error($workbook_xml) || is_wp_error($relationships_xml)) {
                return new WP_Error(
                    'dealer_profile_xlsx_structure',
                    __('The Excel workbook structure is incomplete.', 'bricks-child')
                );
            }

            $worksheet_path = self::resolveWorksheetPath(
                $workbook_xml,
                $relationships_xml,
                $sheet_name
            );
            if (is_wp_error($worksheet_path)) {
                return $worksheet_path;
            }

            $worksheet_xml = self::readArchiveEntry($zip, $worksheet_path);
            if (is_wp_error($worksheet_xml)) {
                return new WP_Error(
                    'dealer_profile_xlsx_sheet_missing',
                    __('The selected worksheet could not be read.', 'bricks-child')
                );
            }

            $shared_strings = self::readSharedStrings($zip);
            if (is_wp_error($shared_strings)) {
                return $shared_strings;
            }

            return self::parseWorksheet($worksheet_xml, $shared_strings);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return true|WP_Error
     */
    private static function validateArchive(ZipArchive $zip)
    {
        if ($zip->numFiles <= 0 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            return new WP_Error(
                'dealer_profile_xlsx_archive_size',
                __('The Excel archive contains an unexpected number of files.', 'bricks-child')
            );
        }

        $total_uncompressed = 0;
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if (!is_array($stat)) {
                return new WP_Error(
                    'dealer_profile_xlsx_archive_entry',
                    __('The Excel archive contains an unreadable entry.', 'bricks-child')
                );
            }

            $name = isset($stat['name']) ? (string) $stat['name'] : '';
            $size = isset($stat['size']) ? (int) $stat['size'] : 0;
            if ($name === '' || strpos($name, "\0") !== false || $size < 0) {
                return new WP_Error(
                    'dealer_profile_xlsx_archive_entry',
                    __('The Excel archive contains an invalid entry.', 'bricks-child')
                );
            }

            if ($size > self::MAX_XML_BYTES && substr($name, -1) !== '/') {
                return new WP_Error(
                    'dealer_profile_xlsx_entry_too_large',
                    __('The Excel workbook contains an oversized internal file.', 'bricks-child')
                );
            }

            $total_uncompressed += $size;
            if ($total_uncompressed > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
                return new WP_Error(
                    'dealer_profile_xlsx_uncompressed_size',
                    __('The Excel workbook expands beyond the safe import limit.', 'bricks-child')
                );
            }
        }

        if ($zip->locateName('[Content_Types].xml', ZipArchive::FL_NOCASE) === false
            || $zip->locateName('xl/workbook.xml', ZipArchive::FL_NOCASE) === false
        ) {
            return new WP_Error(
                'dealer_profile_xlsx_structure',
                __('The file does not contain the required XLSX workbook files.', 'bricks-child')
            );
        }

        return true;
    }

    /**
     * @return string|WP_Error
     */
    private static function readArchiveEntry(ZipArchive $zip, string $path)
    {
        $stat = $zip->statName($path, ZipArchive::FL_NOCASE);
        if (!is_array($stat)) {
            return new WP_Error('dealer_profile_xlsx_missing_entry', __('A required workbook file is missing.', 'bricks-child'));
        }

        $size = isset($stat['size']) ? (int) $stat['size'] : 0;
        if ($size < 0 || $size > self::MAX_XML_BYTES) {
            return new WP_Error('dealer_profile_xlsx_entry_size', __('A workbook XML file exceeds the safe size limit.', 'bricks-child'));
        }

        $contents = $zip->getFromName((string) $stat['name']);
        if (!is_string($contents) || strlen($contents) > self::MAX_XML_BYTES) {
            return new WP_Error('dealer_profile_xlsx_entry_read', __('A workbook XML file could not be read safely.', 'bricks-child'));
        }

        return $contents;
    }

    /**
     * @return DOMDocument|WP_Error
     */
    private static function loadXml(string $xml)
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return new WP_Error(
                'dealer_profile_xlsx_invalid_xml',
                __('The Excel workbook contains invalid XML.', 'bricks-child')
            );
        }

        return $document;
    }

    /**
     * @return string|WP_Error
     */
    private static function resolveWorksheetPath(
        string $workbook_xml,
        string $relationships_xml,
        string $sheet_name
    ) {
        $workbook = self::loadXml($workbook_xml);
        $relationships = self::loadXml($relationships_xml);
        if (is_wp_error($workbook) || is_wp_error($relationships)) {
            return new WP_Error(
                'dealer_profile_xlsx_invalid_workbook',
                __('The workbook index could not be parsed.', 'bricks-child')
            );
        }

        $workbook_xpath = new DOMXPath($workbook);
        $workbook_xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook_xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $relationship_id = '';
        $available = array();
        $sheet_nodes = $workbook_xpath->query('//x:sheets/x:sheet');
        if ($sheet_nodes instanceof DOMNodeList) {
            foreach ($sheet_nodes as $sheet) {
                if (!$sheet instanceof DOMElement) {
                    continue;
                }
                $name = trim($sheet->getAttribute('name'));
                if ($name !== '') {
                    $available[] = $name;
                }
                if ($name === $sheet_name) {
                    $relationship_id = $sheet->getAttributeNS(
                        'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                        'id'
                    );
                }
            }
        }

        if ($relationship_id === '') {
            return new WP_Error(
                'dealer_profile_xlsx_sheet_not_found',
                sprintf(
                    /* translators: 1: requested sheet, 2: available sheet names */
                    __('Worksheet "%1$s" was not found. Available worksheets: %2$s.', 'bricks-child'),
                    $sheet_name,
                    implode(', ', $available)
                )
            );
        }

        $relationships_xpath = new DOMXPath($relationships);
        $relationships_xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $target = '';
        $relationship_nodes = $relationships_xpath->query('//r:Relationship');
        if ($relationship_nodes instanceof DOMNodeList) {
            foreach ($relationship_nodes as $relationship) {
                if (!$relationship instanceof DOMElement
                    || $relationship->getAttribute('Id') !== $relationship_id
                ) {
                    continue;
                }
                if (strtolower($relationship->getAttribute('TargetMode')) === 'external') {
                    return new WP_Error(
                        'dealer_profile_xlsx_external_sheet',
                        __('External worksheet relationships are not allowed.', 'bricks-child')
                    );
                }
                $target = $relationship->getAttribute('Target');
                break;
            }
        }

        if ($target === '') {
            return new WP_Error(
                'dealer_profile_xlsx_sheet_relationship',
                __('The selected worksheet relationship is missing.', 'bricks-child')
            );
        }

        $normalized = self::normalizeArchivePath(
            strpos($target, '/') === 0 ? ltrim($target, '/') : 'xl/' . $target
        );
        if ($normalized === '' || strpos($normalized, 'xl/') !== 0) {
            return new WP_Error(
                'dealer_profile_xlsx_sheet_path',
                __('The selected worksheet path is invalid.', 'bricks-child')
            );
        }

        return $normalized;
    }

    private static function normalizeArchivePath(string $path): string
    {
        $parts = preg_split('#[/\\\\]+#', $path);
        if (!is_array($parts)) {
            return '';
        }

        $normalized = array();
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (empty($normalized)) {
                    return '';
                }
                array_pop($normalized);
                continue;
            }
            $normalized[] = $part;
        }

        return implode('/', $normalized);
    }

    /**
     * @return list<string>|WP_Error
     */
    private static function readSharedStrings(ZipArchive $zip)
    {
        if ($zip->locateName('xl/sharedStrings.xml', ZipArchive::FL_NOCASE) === false) {
            return array();
        }

        $xml = self::readArchiveEntry($zip, 'xl/sharedStrings.xml');
        if (is_wp_error($xml)) {
            return $xml;
        }

        $document = self::loadXml($xml);
        if (is_wp_error($document)) {
            return $document;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = array();
        $nodes = $xpath->query('//x:si');
        if ($nodes instanceof DOMNodeList) {
            foreach ($nodes as $node) {
                $strings[] = self::limitCellValue($node->textContent);
            }
        }

        return $strings;
    }

    /**
     * @param list<string> $shared_strings
     * @return array{headers:list<string>,rows:list<list<string>>}|WP_Error
     */
    private static function parseWorksheet(string $worksheet_xml, array $shared_strings)
    {
        $document = self::loadXml($worksheet_xml);
        if (is_wp_error($document)) {
            return $document;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $row_nodes = $xpath->query('//x:sheetData/x:row');
        if (!$row_nodes instanceof DOMNodeList || $row_nodes->length === 0) {
            return new WP_Error(
                'dealer_profile_xlsx_empty_sheet',
                __('The selected worksheet is empty.', 'bricks-child')
            );
        }
        if ($row_nodes->length > self::MAX_ROWS + 1) {
            return new WP_Error(
                'dealer_profile_xlsx_too_many_rows',
                sprintf(
                    /* translators: %d: maximum number of data rows */
                    __('The worksheet exceeds the %d-row import limit.', 'bricks-child'),
                    self::MAX_ROWS
                )
            );
        }

        $table = array();
        foreach ($row_nodes as $row_node) {
            if (!$row_node instanceof DOMElement) {
                continue;
            }

            $row = array();
            $fallback_column = 0;
            foreach ($row_node->childNodes as $cell) {
                if (!$cell instanceof DOMElement || $cell->localName !== 'c') {
                    continue;
                }

                $reference = $cell->getAttribute('r');
                $column = $reference !== ''
                    ? self::columnIndexFromReference($reference)
                    : $fallback_column;
                if ($column < 0 || $column >= self::MAX_COLUMNS) {
                    return new WP_Error(
                        'dealer_profile_xlsx_too_many_columns',
                        sprintf(
                            /* translators: %d: maximum number of columns */
                            __('The worksheet exceeds the %d-column import limit.', 'bricks-child'),
                            self::MAX_COLUMNS
                        )
                    );
                }

                $row[$column] = self::cellValue($cell, $shared_strings);
                $fallback_column = $column + 1;
            }

            if (empty($row)) {
                continue;
            }

            $max_column = max(array_keys($row));
            $dense = array();
            for ($column = 0; $column <= $max_column; ++$column) {
                $dense[] = isset($row[$column]) ? $row[$column] : '';
            }
            if (self::rowHasContent($dense)) {
                $table[] = $dense;
            }
        }

        if (count($table) < 2) {
            return new WP_Error(
                'dealer_profile_xlsx_no_data',
                __('The selected worksheet has no dealer rows.', 'bricks-child')
            );
        }

        $headers = array_map(
            static function ($header): string {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

                return trim((string) $header);
            },
            array_shift($table)
        );

        return array(
            'headers' => array_values($headers),
            'rows'    => array_values($table),
        );
    }

    /**
     * @param list<string> $shared_strings
     */
    private static function cellValue(DOMElement $cell, array $shared_strings): string
    {
        $type = $cell->getAttribute('t');
        $value = '';

        if ($type === 'inlineStr') {
            foreach ($cell->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'is') {
                    $value = $child->textContent;
                    break;
                }
            }
        } else {
            foreach ($cell->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'v') {
                    $value = $child->textContent;
                    break;
                }
            }

            if ($type === 's') {
                $index = (int) $value;
                $value = isset($shared_strings[$index]) ? $shared_strings[$index] : '';
            } elseif ($type === 'b') {
                $value = $value === '1' ? '1' : '0';
            }
        }

        return self::limitCellValue($value);
    }

    private static function columnIndexFromReference(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)\d+$/i', $reference, $matches)) {
            return -1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        $length = strlen($letters);
        for ($position = 0; $position < $length; ++$position) {
            $index = ($index * 26) + (ord($letters[$position]) - 64);
        }

        return $index - 1;
    }

    /**
     * @param list<string> $row
     */
    private static function rowHasContent(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function limitCellValue(string $value): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, self::MAX_CELL_LENGTH, 'UTF-8');
        }

        return substr($value, 0, self::MAX_CELL_LENGTH);
    }
}
