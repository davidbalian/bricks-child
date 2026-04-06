<?php
/**
 * Dedicated performance logging for car_filters_filter_listings AJAX.
 *
 * Enable in wp-config.php:
 *   define( 'CAR_FILTERS_PERF_LOG', true );
 *
 * Or: add_filter( 'car_filters_perf_log_enabled', '__return_true' );
 *
 * Log file (created automatically):
 *   wp-content/uploads/car-filters-perf.log
 * Fallback if uploads unavailable:
 *   wp-content/car-filters-perf.log
 *
 * @package bricks-child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether perf logging is active for this request.
 *
 * @return bool
 */
function car_filters_perf_log_is_enabled() {
    if (defined('CAR_FILTERS_PERF_LOG') && CAR_FILTERS_PERF_LOG) {
        return true;
    }
    return (bool) apply_filters('car_filters_perf_log_enabled', false);
}

/**
 * Absolute path to the dedicated log file.
 *
 * @return string
 */
function car_filters_perf_log_file_path() {
    $upload = wp_upload_dir();
    if (empty($upload['error']) && !empty($upload['basedir'])) {
        return trailingslashit($upload['basedir']) . 'car-filters-perf.log';
    }
    return WP_CONTENT_DIR . '/car-filters-perf.log';
}

/**
 * High-resolution timer for one AJAX request.
 */
final class Car_Filters_Perf_Logger {

    /** @var float */
    private $started_at;

    /** @var float */
    private $last_at;

    /** @var array<int, array{label:string, ms:float}> */
    private $segments = array();

    /**
     * @return self|null
     */
    public static function maybe_start() {
        if (!car_filters_perf_log_is_enabled()) {
            return null;
        }
        return new self();
    }

    private function __construct() {
        $t = microtime(true);
        $this->started_at = $t;
        $this->last_at = $t;
    }

    /**
     * Record elapsed time since the previous mark (or start) under $label.
     *
     * @param string $label Phase name (use stable identifiers for grep).
     */
    public function mark($label) {
        $now = microtime(true);
        $ms = ($now - $this->last_at) * 1000.0;
        $this->segments[] = array(
            'label' => (string) $label,
            'ms'    => round($ms, 2),
        );
        $this->last_at = $now;
    }

    /**
     * Write a block to the log file and return the same summary (for debugging).
     *
     * @param array<string, mixed> $context Request summary (filters, counts, flags).
     * @return string Human-readable block.
     */
    public function commit(array $context) {
        $total_ms = (microtime(true) - $this->started_at) * 1000.0;
        $lines = array();

        $lines[] = str_repeat('=', 72);
        $lines[] = sprintf(
            '[%s] car_filters_filter_listings  total_wall_ms=%.2f  peak_mem_mb=%.2f',
            gmdate('Y-m-d H:i:s'),
            $total_ms,
            round(memory_get_peak_usage(true) / 1048576, 2)
        );

        foreach ($context as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $lines[] = sprintf('  %s: %s', $k, $v === null ? 'null' : (string) $v);
            } elseif (is_array($v)) {
                $lines[] = sprintf('  %s: %s', $k, wp_json_encode($v));
            }
        }

        $lines[] = '';
        $lines[] = 'Phase breakdown (each row = time since previous mark):';
        $lines[] = sprintf('  %-36s %10s', 'phase', 'delta_ms');

        $max_ms = 0.0;
        $max_label = '';
        foreach ($this->segments as $seg) {
            $lines[] = sprintf('  %-36s %10.2f', $seg['label'], $seg['ms']);
            if ($seg['ms'] > $max_ms) {
                $max_ms = $seg['ms'];
                $max_label = $seg['label'];
            }
        }

        $lines[] = '';
        if ($max_label !== '' && $total_ms > 0) {
            $pct = round(100.0 * $max_ms / $total_ms, 1);
            $lines[] = sprintf(
                '>>> LIKELY BOTTLENECK: "%s" = %.2f ms (~%s%% of total wall time)',
                $max_label,
                $max_ms,
                $pct
            );
            if ($max_label === 'car_listings_execute_query') {
                $lines[] = '    Hint: optimize WP_Query / meta_query / tax_query / SQL (indexes, fewer JOINs).';
            } elseif ($max_label === 'update_meta_and_thumbnail_cache') {
                $lines[] = '    Hint: many meta keys or large batches — check priming and attachment usage.';
            } elseif ($max_label === 'build_json_cards_payload' || $max_label === 'render_cards_html') {
                $lines[] = '    Hint: per-card work — reduce fields or cache computed values.';
            }
        }

        $lines[] = str_repeat('=', 72);
        $lines[] = '';

        $block = implode("\n", $lines);
        $path = car_filters_perf_log_file_path();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- dedicated audit log.
        @file_put_contents($path, $block, FILE_APPEND | LOCK_EX);

        return $block;
    }
}
