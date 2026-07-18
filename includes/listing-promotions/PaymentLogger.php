<?php
/**
 * Small, private, rotating log for listing-promotion payments.
 *
 * The default directory sits one level above the WordPress document root so
 * the log cannot be downloaded over HTTP. Only explicitly allowed context
 * fields are written; secrets, signatures, payloads, email and card data are
 * never accepted by this logger.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Payment_Logger
{
    const FILE_NAME = 'stripe-payments.log';
    const DEFAULT_MAX_BYTES = 2097152; // 2 MB per file.
    const MAX_ARCHIVES = 2;

    private static $reported_write_failure = false;

    public static function log($event, array $context = array(), $level = 'info')
    {
        if (!self::enabled()) {
            return;
        }

        $path = self::path();
        if (!self::ensure_directory(dirname($path))) {
            self::report_write_failure('The payment log directory could not be created.');
            return;
        }

        self::rotate_if_needed($path);

        $level = in_array($level, array('info', 'warning', 'error'), true) ? $level : 'info';
        $record = array(
            'timestamp_gmt' => gmdate('c'),
            'level' => $level,
            'event' => substr(preg_replace('/[^a-z0-9._-]/', '_', strtolower((string) $event)), 0, 80),
            'context' => self::safe_context($context),
        );
        $line = wp_json_encode($record, JSON_UNESCAPED_SLASHES);
        if (!is_string($line) || file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            self::report_write_failure('The payment log file could not be written.');
        }
    }

    public static function path()
    {
        if (defined('AUTOAGORA_PAYMENT_LOG_DIR') && trim((string) AUTOAGORA_PAYMENT_LOG_DIR) !== '') {
            $directory = rtrim(trim((string) AUTOAGORA_PAYMENT_LOG_DIR), '/\\');
        } else {
            $document_root = isset($_SERVER['DOCUMENT_ROOT']) ? trim((string) $_SERVER['DOCUMENT_ROOT']) : '';
            $public_root = $document_root !== '' ? untrailingslashit($document_root) : untrailingslashit(ABSPATH);
            $directory = dirname($public_root) . DIRECTORY_SEPARATOR . 'autoagora-private-logs';
        }

        return $directory . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }

    public static function max_bytes()
    {
        $configured = defined('AUTOAGORA_PAYMENT_LOG_MAX_BYTES') ? (int) AUTOAGORA_PAYMENT_LOG_MAX_BYTES : self::DEFAULT_MAX_BYTES;
        return max(65536, min(10485760, $configured));
    }

    private static function enabled()
    {
        return !defined('AUTOAGORA_PAYMENT_LOG_ENABLED') || AUTOAGORA_PAYMENT_LOG_ENABLED !== false;
    }

    private static function safe_context(array $context)
    {
        $allowed = array(
            'attempt',
            'listing_id',
            'user_id',
            'tier',
            'mode',
            'amount_minor',
            'currency',
            'duration_seconds',
            'session_id',
            'event_id',
            'event_type',
            'payment_intent',
            'promotion_id',
            'http_status',
            'status',
            'error_code',
            'ignored_reason',
        );
        $safe = array();

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $context) || is_array($context[$key]) || is_object($context[$key])) {
                continue;
            }
            $value = $context[$key];
            if (is_bool($value) || is_int($value) || is_float($value)) {
                $safe[$key] = $value;
                continue;
            }
            $safe[$key] = substr(sanitize_text_field((string) $value), 0, 250);
        }

        return $safe;
    }

    private static function ensure_directory($directory)
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }
        return wp_mkdir_p($directory) && is_writable($directory);
    }

    private static function rotate_if_needed($path)
    {
        clearstatcache(true, $path);
        if (!is_file($path) || filesize($path) < self::max_bytes()) {
            return;
        }

        $oldest = $path . '.' . self::MAX_ARCHIVES;
        if (is_file($oldest)) {
            unlink($oldest);
        }
        for ($archive = self::MAX_ARCHIVES - 1; $archive >= 1; $archive--) {
            $source = $path . '.' . $archive;
            if (is_file($source)) {
                rename($source, $path . '.' . ($archive + 1));
            }
        }
        rename($path, $path . '.1');
    }

    private static function report_write_failure($message)
    {
        if (self::$reported_write_failure) {
            return;
        }
        self::$reported_write_failure = true;
        error_log('AutoAgora payment logger: ' . $message . ' Path: ' . self::path());
    }
}
