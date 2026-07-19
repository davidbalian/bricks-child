<?php
/**
 * Durable, minimal Stripe webhook receipt storage.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Payment_Event_Repository
{
    const STATUS_RECEIVED = 'received';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED = 'failed';

    public function receive($provider, $event_id, $event_type, $object_reference)
    {
        global $wpdb;
        $provider = substr(sanitize_key($provider), 0, 32);
        $event_id = $this->limited_text($event_id, 191);
        $event_type = $this->limited_text($event_type, 64);
        $object_reference = $this->limited_text($object_reference, 191);

        if ($provider === '' || $event_id === '' || $event_type === '') {
            return new WP_Error('payment_event_invalid', 'The payment event identity is incomplete.');
        }

        $existing = $this->find($provider, $event_id);
        if ($existing) {
            return $existing;
        }

        $inserted = $wpdb->insert(
            AutoAgora_Promotion_Schema::payment_events_table_name(),
            array(
                'provider' => $provider,
                'event_id' => $event_id,
                'event_type' => $event_type,
                'object_reference' => $object_reference !== '' ? $object_reference : null,
                'status' => self::STATUS_RECEIVED,
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted) {
            return $this->find_by_id((int) $wpdb->insert_id);
        }

        // A concurrent delivery can win the unique-key race.
        $existing = $this->find($provider, $event_id);
        return $existing ?: new WP_Error('payment_event_insert_failed', 'The payment event could not be recorded.');
    }

    public function find($provider, $event_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . AutoAgora_Promotion_Schema::payment_events_table_name() . ' WHERE provider = %s AND event_id = %s LIMIT 1',
            $provider,
            $event_id
        ));
    }

    public function find_by_id($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . AutoAgora_Promotion_Schema::payment_events_table_name() . ' WHERE id = %d',
            (int) $id
        ));
    }

    public function pending_refunds($provider, $object_reference)
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . AutoAgora_Promotion_Schema::payment_events_table_name() . "
             WHERE provider = %s AND object_reference = %s AND event_type = 'charge.refunded' AND status = %s
             ORDER BY created_at ASC, id ASC",
            $provider,
            $object_reference,
            self::STATUS_PENDING
        ));
        return $wpdb->last_error === '' ? $rows : new WP_Error('payment_event_query_failed', 'Pending refund receipts could not be loaded.');
    }

    public function admin_search(array $filters, $limit = 25, $offset = 0)
    {
        global $wpdb;
        $args = array();
        $where = $this->admin_where($filters, $args);
        $args[] = max(1, min(100, (int) $limit));
        $args[] = max(0, (int) $offset);
        $sql = "SELECT * FROM " . AutoAgora_Promotion_Schema::payment_events_table_name() . "
                WHERE {$where}
                ORDER BY created_at DESC, id DESC
                LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, $args));
    }

    public function admin_count(array $filters)
    {
        global $wpdb;
        $args = array();
        $where = $this->admin_where($filters, $args);
        $sql = 'SELECT COUNT(*) FROM ' . AutoAgora_Promotion_Schema::payment_events_table_name() . " WHERE {$where}";
        return (int) $wpdb->get_var($args ? $wpdb->prepare($sql, $args) : $sql);
    }

    public function admin_attention_count()
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . AutoAgora_Promotion_Schema::payment_events_table_name() . "
             WHERE status IN ('received','processing','pending','failed')"
        );
    }

    public function mark_processing($id)
    {
        global $wpdb;
        return false !== $wpdb->query($wpdb->prepare(
            "UPDATE " . AutoAgora_Promotion_Schema::payment_events_table_name() . "
             SET status = %s, error_code = NULL, attempts = attempts + 1 WHERE id = %d",
            self::STATUS_PROCESSING,
            (int) $id
        ));
    }

    public function mark_pending($id, $error_code)
    {
        return $this->update_outcome((int) $id, self::STATUS_PENDING, $error_code, false);
    }

    public function mark_processed($id)
    {
        return $this->update_outcome((int) $id, self::STATUS_PROCESSED, '', true);
    }

    public function mark_failed($id, $error_code)
    {
        return $this->update_outcome((int) $id, self::STATUS_FAILED, $error_code, false);
    }

    private function update_outcome($id, $status, $error_code, $processed)
    {
        global $wpdb;
        $data = array(
            'status' => $status,
            'error_code' => $error_code !== '' ? substr(sanitize_key($error_code), 0, 64) : null,
            'processed_at' => $processed ? gmdate('Y-m-d H:i:s') : null,
        );
        return false !== $wpdb->update(
            AutoAgora_Promotion_Schema::payment_events_table_name(),
            $data,
            array('id' => (int) $id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }

    private function limited_text($value, $limit)
    {
        $value = sanitize_text_field((string) $value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private function admin_where(array $filters, array &$args)
    {
        global $wpdb;
        $where = array('1=1');
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $args[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
            $where[] = '(event_id LIKE %s OR event_type LIKE %s OR object_reference LIKE %s OR error_code LIKE %s)';
            $args = array_merge($args, array($like, $like, $like, $like));
        }
        if (!empty($filters['date_from_gmt'])) {
            $where[] = 'created_at >= %s';
            $args[] = $filters['date_from_gmt'];
        }
        if (!empty($filters['date_to_gmt'])) {
            $where[] = 'created_at < %s';
            $args[] = $filters['date_to_gmt'];
        }
        return implode(' AND ', $where);
    }
}
