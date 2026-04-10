<?php
/**
 * Builds copy-paste social text for daily deals posts.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class DailyDealsSocialCopyBuilder
{
    /**
     * @param list<array{title:string,price_display:string,deal_suffix:string}> $rows
     */
    public function build(array $rows, string $view_deals_url): string
    {
        if ($rows === array()) {
            return '';
        }

        $lines = array();
        $lines[] = '🔥 ' . __('Top 5 Car Deals in Cyprus Today', 'bricks-child');
        $lines[] = '';

        $n = 1;
        foreach ($rows as $row) {
            $suffix = (string) ($row['deal_suffix'] ?? '');
            $lines[] = sprintf(
                '%d. %s – %s%s',
                $n,
                (string) ($row['title'] ?? ''),
                (string) ($row['price_display'] ?? ''),
                $suffix
            );
            ++$n;
        }

        $lines[] = '';
        $host = wp_parse_url($view_deals_url, PHP_URL_HOST);
        $display = is_string($host) && $host !== '' ? $host : untrailingslashit($view_deals_url);
        $lines[] = '👉 ' . sprintf(
            /* translators: %s: site host or URL for “view all deals” */
            __('View all deals: %s', 'bricks-child'),
            $display
        );

        $tags = apply_filters(
            'bricks_child_daily_deals_social_hashtags',
            array(
                'usedcarscyprus',
                'carsincyprus',
                'cardealscyprus',
                'cypruscars',
                'autoagoracy',
                'forsalecyprus',
            )
        );

        if (is_array($tags) && $tags !== array()) {
            $lines[] = '';
            $hash = array();
            foreach ($tags as $t) {
                $t = is_string($t) ? trim(str_replace('#', '', $t)) : '';
                if ($t !== '') {
                    $hash[] = '#' . $t;
                }
            }
            if ($hash !== array()) {
                $lines[] = implode(' ', $hash);
            }
        }

        return implode("\n", $lines);
    }
}
