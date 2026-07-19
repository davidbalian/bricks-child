<?php
/**
 * Prepared database access for listing promotion records.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Promotion_Repository
{
    public function insert(array $data)
    {
        global $wpdb;
        $ok = $wpdb->insert(
            AutoAgora_Promotion_Schema::table_name(),
            $data,
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
        );
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    public function find($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . AutoAgora_Promotion_Schema::table_name() . ' WHERE id = %d',
            (int) $id
        ));
    }

    public function find_payment_event($provider, $reference)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . AutoAgora_Promotion_Schema::table_name() . ' WHERE payment_provider = %s AND payment_reference = %s LIMIT 1',
            $provider,
            $reference
        ));
    }

    public function update_status($id, $status)
    {
        global $wpdb;
        return false !== $wpdb->update(
            AutoAgora_Promotion_Schema::table_name(),
            array('status' => $status),
            array('id' => (int) $id),
            array('%s'),
            array('%d')
        );
    }

    public function mark_refunded($id, $amount_minor)
    {
        global $wpdb;
        return false !== $wpdb->update(
            AutoAgora_Promotion_Schema::table_name(),
            array(
                'status' => AutoAgora_Promotion_Manager::STATUS_REFUNDED,
                'refunded_amount_minor' => max(0, (int) $amount_minor),
            ),
            array('id' => (int) $id),
            array('%s', '%d'),
            array('%d')
        );
    }

    public function list_for_listing($listing_id, $limit = 50)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . AutoAgora_Promotion_Schema::table_name() . ' WHERE listing_id = %d ORDER BY created_at DESC, id DESC LIMIT %d',
            (int) $listing_id,
            max(1, (int) $limit)
        ));
    }

    public function admin_search(array $filters, $limit = 25, $offset = 0)
    {
        global $wpdb;
        $args = array();
        $where = $this->admin_where($filters, $args);
        $args[] = max(1, min(100, (int) $limit));
        $args[] = max(0, (int) $offset);
        $sql = "SELECT promotions.*,
                       listings.ID AS live_listing_id,
                       listings.post_title AS current_listing_title,
                       sellers.ID AS live_seller_id,
                       sellers.display_name AS seller_display_name,
                       sellers.user_login AS seller_user_login
                FROM " . AutoAgora_Promotion_Schema::table_name() . " AS promotions
                LEFT JOIN {$wpdb->posts} AS listings
                    ON listings.ID = promotions.listing_id AND listings.post_type = 'car'
                LEFT JOIN {$wpdb->users} AS sellers
                    ON sellers.ID = promotions.seller_id_snapshot
                WHERE {$where}
                ORDER BY promotions.created_at DESC, promotions.id DESC
                LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, $args));
    }

    public function admin_count(array $filters)
    {
        global $wpdb;
        $args = array();
        $where = $this->admin_where($filters, $args);
        $sql = "SELECT COUNT(*)
                FROM " . AutoAgora_Promotion_Schema::table_name() . " AS promotions
                LEFT JOIN {$wpdb->posts} AS listings
                    ON listings.ID = promotions.listing_id AND listings.post_type = 'car'
                LEFT JOIN {$wpdb->users} AS sellers
                    ON sellers.ID = promotions.seller_id_snapshot
                WHERE {$where}";
        return (int) $wpdb->get_var($args ? $wpdb->prepare($sql, $args) : $sql);
    }

    public function current_and_upcoming_for_listing($listing_id, $now_gmt, $limit = 20)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . AutoAgora_Promotion_Schema::table_name() . "
             WHERE listing_id = %d
             AND status IN ('active','scheduled')
             AND (ends_at IS NULL OR ends_at > %s)
             ORDER BY starts_at ASC, id ASC
             LIMIT %d",
            (int) $listing_id,
            $now_gmt,
            max(1, (int) $limit)
        ));
    }

    public function active_for_listing($listing_id, $now_gmt)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . AutoAgora_Promotion_Schema::table_name() . "
             WHERE listing_id = %d AND status = 'active'
             AND starts_at <= %s AND (ends_at IS NULL OR ends_at > %s)
             ORDER BY starts_at ASC, id ASC",
            (int) $listing_id,
            $now_gmt,
            $now_gmt
        ));
    }

    public function due_scheduled_for_listing($listing_id, $now_gmt)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . AutoAgora_Promotion_Schema::table_name() . "
             WHERE listing_id = %d AND status = 'scheduled' AND starts_at <= %s
             AND (ends_at IS NULL OR ends_at > %s)
             ORDER BY starts_at ASC, id ASC",
            (int) $listing_id,
            $now_gmt,
            $now_gmt
        ));
    }

    public function latest_reserved_end($listing_id, $not_before_gmt)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(ends_at) FROM " . AutoAgora_Promotion_Schema::table_name() . "
             WHERE listing_id = %d AND status IN ('active','scheduled') AND ends_at > %s",
            (int) $listing_id,
            $not_before_gmt
        ));
    }

    public function expire_due_for_listing($listing_id, $now_gmt)
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "UPDATE " . AutoAgora_Promotion_Schema::table_name() . " SET status = 'expired', remaining_seconds = 0
             WHERE listing_id = %d AND status IN ('active','scheduled') AND ends_at IS NOT NULL AND ends_at <= %s",
            (int) $listing_id,
            $now_gmt
        ));
    }

    public function due_listing_ids($now_gmt, $limit = 500)
    {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT listing_id FROM " . AutoAgora_Promotion_Schema::table_name() . "
             WHERE (status = 'active' AND ends_at IS NOT NULL AND ends_at <= %s)
                OR (status = 'scheduled' AND starts_at <= %s)
             LIMIT %d",
            $now_gmt,
            $now_gmt,
            max(1, (int) $limit)
        )));
    }

    public function cancel_for_deleted_listing($listing_id)
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "UPDATE " . AutoAgora_Promotion_Schema::table_name() . " SET status = 'cancelled'
             WHERE listing_id = %d AND status IN ('active','scheduled')",
            (int) $listing_id
        ));
    }

    public function preserve_listing_snapshot($listing_id, $listing_title, $seller_id)
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "UPDATE " . AutoAgora_Promotion_Schema::table_name() . "
             SET listing_title_snapshot = CASE
                     WHEN listing_title_snapshot = '' THEN %s
                     ELSE listing_title_snapshot
                 END,
                 seller_id_snapshot = CASE
                     WHEN seller_id_snapshot = 0 THEN %d
                     ELSE seller_id_snapshot
                 END
             WHERE listing_id = %d
             AND (listing_title_snapshot = '' OR seller_id_snapshot = 0)",
            $listing_title,
            (int) $seller_id,
            (int) $listing_id
        ));
    }

    public function anonymize_deleted_seller($seller_id)
    {
        global $wpdb;
        return $wpdb->update(
            AutoAgora_Promotion_Schema::table_name(),
            array('seller_id_snapshot' => 0),
            array('seller_id_snapshot' => (int) $seller_id),
            array('%d'),
            array('%d')
        );
    }

    private function admin_where(array $filters, array &$args)
    {
        global $wpdb;
        $where = array('1=1');
        if (!empty($filters['status'])) {
            $where[] = 'promotions.status = %s';
            $args[] = $filters['status'];
        }
        if (!empty($filters['tier'])) {
            $where[] = 'promotions.tier = %s';
            $args[] = $filters['tier'];
        }
        if (!empty($filters['source'])) {
            $where[] = 'promotions.source = %s';
            $args[] = $filters['source'];
        }
        if (!empty($filters['search'])) {
            $search = (string) $filters['search'];
            $like = '%' . $wpdb->esc_like($search) . '%';
            $search_where = array(
                'promotions.listing_title_snapshot LIKE %s',
                'listings.post_title LIKE %s',
                'sellers.display_name LIKE %s',
                'sellers.user_login LIKE %s',
                'promotions.payment_reference LIKE %s',
                'promotions.stripe_checkout_session_id LIKE %s',
            );
            $args = array_merge($args, array($like, $like, $like, $like, $like, $like));
            if (ctype_digit($search)) {
                $search_where[] = 'promotions.id = %d';
                $search_where[] = 'promotions.listing_id = %d';
                $search_where[] = 'promotions.seller_id_snapshot = %d';
                $numeric = (int) $search;
                $args[] = $numeric;
                $args[] = $numeric;
                $args[] = $numeric;
            }
            $where[] = '(' . implode(' OR ', $search_where) . ')';
        }

        $date_fields = array('created_at', 'starts_at', 'ends_at');
        $date_field = in_array($filters['date_field'] ?? '', $date_fields, true)
            ? $filters['date_field']
            : 'created_at';
        if (!empty($filters['date_from_gmt'])) {
            $where[] = 'promotions.' . $date_field . ' >= %s';
            $args[] = $filters['date_from_gmt'];
        }
        if (!empty($filters['date_to_gmt'])) {
            $where[] = 'promotions.' . $date_field . ' < %s';
            $args[] = $filters['date_to_gmt'];
        }
        return implode(' AND ', $where);
    }
}
