<?php
/**
 * Tunables for price insight cohorts and band labels.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CarPriceInsightConfig {

    const TABLE_BASENAME = 'car_price_cohorts';

    /** Minimum listings in a cohort before we assign a band (not none). */
    const MIN_COHORT_N = 5;

    /** Mileage bucket width in km (listing mileage assumed km). */
    const MILEAGE_BUCKET_KM = 50000;

    /**
     * Years are grouped into bands of this width (e.g. 5 → 2015–2019 share one key).
     * Use 5 for ~±2 years within a band; use 7 for a wider ~±3 year spread.
     */
    const YEAR_BUCKET_WIDTH_YEARS = 5;

    /**
     * Price vs median ratio upper bounds for each band (listing price / median).
     * great <= 0.90, good <= 0.97, fair <= 1.03, above = higher.
     */
    const RATIO_GREAT_MAX = 0.90;
    const RATIO_GOOD_MAX = 0.97;
    const RATIO_FAIR_MAX = 1.03;

    /**
     * @return float[] Engine capacity edges in litres (bins: <e0, e0–e1, …, last open-ended).
     */
    public static function engine_bin_edges_litres() {
        return array(1.4, 2.0, 3.0);
    }
}
