<?php
/**
 * Maps listing price vs cohort median to a band slug.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarPriceInsightBandResolver {

    /**
     * @param float $listing_price   Asking price.
     * @param float $median_price    Cohort median.
     * @param int   $cohort_n        Number of listings in cohort.
     * @return array{ band: string, pct_vs_median: float|null }
     */
    public static function resolve($listing_price, $median_price, $cohort_n) {
        if ($cohort_n < CarPriceInsightConfig::MIN_COHORT_N) {
            return array('band' => 'none', 'pct_vs_median' => null);
        }
        if ($listing_price <= 0 || $median_price <= 0) {
            return array('band' => 'none', 'pct_vs_median' => null);
        }

        $ratio = $listing_price / $median_price;
        $pct = ($ratio - 1.0) * 100.0;

        if ($ratio <= CarPriceInsightConfig::RATIO_GREAT_MAX) {
            $band = 'great';
        } elseif ($ratio <= CarPriceInsightConfig::RATIO_GOOD_MAX) {
            $band = 'good';
        } elseif ($ratio <= CarPriceInsightConfig::RATIO_FAIR_MAX) {
            $band = 'fair';
        } else {
            $band = 'above';
        }

        return array(
            'band' => $band,
            'pct_vs_median' => round($pct, 2),
        );
    }
}
