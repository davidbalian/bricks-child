<?php
/**
 * Central listing_state ACF field (active | sold | expired).
 */
if (!defined('ABSPATH')) {
    exit;
}

final class ListingStateManager
{
    public const FIELD_NAME = 'listing_state';

    public const STATE_ACTIVE = 'active';

    public const STATE_SOLD = 'sold';

    public const STATE_EXPIRED = 'expired';

    /**
     * True when listing_state is sold.
     */
    public static function is_marked_sold(int $post_id): bool
    {
        return self::read_state_value($post_id) === self::STATE_SOLD;
    }

    public static function is_marked_expired(int $post_id): bool
    {
        return self::read_state_value($post_id) === self::STATE_EXPIRED;
    }

    /**
     * @return self::STATE_*
     */
    public static function resolve_state(int $post_id): string
    {
        $state = self::read_state_value($post_id);
        if ($state !== '') {
            return $state;
        }

        return self::STATE_ACTIVE;
    }

    public static function assign_state(int $post_id, string $state): bool
    {
        $allowed = array(self::STATE_ACTIVE, self::STATE_SOLD, self::STATE_EXPIRED);
        if (! in_array($state, $allowed, true)) {
            return false;
        }

        if (function_exists('update_field')) {
            update_field(self::FIELD_NAME, $state, $post_id);
        } else {
            update_post_meta($post_id, self::FIELD_NAME, $state);
        }

        return true;
    }

    public static function assign_active_for_new_car(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }
        if (self::read_state_value($post_id) !== '') {
            return;
        }
        self::assign_state($post_id, self::STATE_ACTIVE);
    }

    /**
     * Single meta_query clause: listing_state must equal active (marketplace-style lists).
     *
     * @return array<string,mixed>
     */
    public static function meta_query_active_clause(): array
    {
        return array(
            'key'     => self::FIELD_NAME,
            'value'   => self::STATE_ACTIVE,
            'compare' => '=',
        );
    }

    /**
     * WP_Query meta_query: only posts with listing_state = active.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function meta_query_active_only(): array
    {
        return array( self::meta_query_active_clause() );
    }

    /**
     * WP_Query meta_query: sold only.
     *
     * @return array<string,mixed>
     */
    public static function meta_query_sold_only(): array
    {
        return array(
            'key'     => self::FIELD_NAME,
            'value'   => self::STATE_SOLD,
            'compare' => '=',
        );
    }

    /**
     * @return string Normalized state slug or '' if unset/invalid in meta.
     */
    private static function read_state_value(int $post_id): string
    {
        if (function_exists('get_field')) {
            $raw = get_field(self::FIELD_NAME, $post_id);
        } else {
            $raw = get_post_meta($post_id, self::FIELD_NAME, true);
        }
        if ($raw === null || $raw === '' || $raw === false) {
            return '';
        }
        $s = is_string($raw) ? strtolower(trim($raw)) : (string) $raw;
        $allowed = array(self::STATE_ACTIVE, self::STATE_SOLD, self::STATE_EXPIRED);
        if (in_array($s, $allowed, true)) {
            return $s;
        }

        return '';
    }
}
