<?php
/**
 * Listing Details Badge Manager
 *
 * Responsible for calculating and updating the "Extra details" and
 * "Full details" badges for car listings based on optional fields.
 *
 * Badges are determined solely from the following optional details:
 * - Horsepower (ACF key: hp)
 * - Vehicle history (ACF key: vehiclehistory)
 * - Extras (ACF key: extras)
 * - Description (ACF key: description)
 * - MOT (ACF key: motuntil)
 * - Number of owners (ACF key: numowners)
 *
 * Explicitly DOES NOT consider any "registered as antique" / isantique field.
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class Listing_Details_Badge_Manager {
    /**
    * Recalculate and update badges for a given car listing.
    *
    * @param int $post_id Car listing post ID.
    * @return void
    */
    public static function update_badges_for_listing($post_id) {
        if (!$post_id || get_post_type($post_id) !== 'car') {
            return;
        }

        $present_count = self::count_present_optional_details($post_id);

        $extra_details_badge = 0;
        $full_details_badge  = 0;

        // If less than 4: no badge.
        if ($present_count >= 4 && $present_count < 6) {
            // 4 or 5 out of 6 → Extra details badge.
            $extra_details_badge = 1;
        } elseif ($present_count === 6) {
            // 6 out of 6 → Full details badge.
            $full_details_badge = 1;
        }

        // Always set both badges explicitly to avoid stale values.
        update_field('extradetailsbadge', $extra_details_badge, $post_id);
        update_field('fulldetailsbadge', $full_details_badge, $post_id);
    }

    /**
    * Count how many of the six optional details are present for a listing.
    *
    * @param int $post_id Car listing post ID.
    * @return int Value between 0 and 6.
    */
    private static function count_present_optional_details($post_id) {
        $count = 0;

        // Horsepower (hp) - integer or empty.
        $hp = get_field('hp', $post_id);
        if (!empty($hp)) {
            $count++;
        }

        // Vehicle history - array of selected options.
        $vehicle_history = get_field('vehiclehistory', $post_id);
        if (is_array($vehicle_history) && !empty($vehicle_history)) {
            $count++;
        }

        // Extras - array of selected extras.
        $extras = get_field('extras', $post_id);
        if (is_array($extras) && !empty($extras)) {
            $count++;
        }

        // Description - text, not required but counts if non-empty.
        $description = get_field('description', $post_id);
        if (is_string($description) && strlen(trim($description)) > 0) {
            $count++;
        }

        // MOT - motuntil date/string.
        $mot_until = get_field('motuntil', $post_id);
        if (!empty($mot_until)) {
            $count++;
        }

        // Number of owners - integer or empty.
        $num_owners = get_field('numowners', $post_id);
        if ($num_owners !== '' && $num_owners !== null) {
            $count++;
        }

        return $count;
    }
}


