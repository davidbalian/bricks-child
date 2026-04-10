<?php
/**
 * Resolves the first display image for a car listing (featured, else first gallery image).
 */
if (!defined('ABSPATH')) {
    exit;
}

final class DailyDealsFirstImageResolver
{
    public static function resolveAttachmentId(int $post_id): int
    {
        if ($post_id <= 0) {
            return 0;
        }

        $thumb = (int) get_post_thumbnail_id($post_id);
        if ($thumb > 0) {
            return $thumb;
        }

        $raw = get_post_meta($post_id, 'car_images', true);
        if (empty($raw) || ! is_array($raw)) {
            return 0;
        }

        foreach ($raw as $img) {
            if (is_array($img) && isset($img['ID'])) {
                $id = (int) $img['ID'];
                if ($id > 0) {
                    return $id;
                }
            } elseif (is_numeric($img)) {
                $id = (int) $img;
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return 0;
    }
}
