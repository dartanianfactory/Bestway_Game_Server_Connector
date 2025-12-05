<?php
class GSC_Model_Payment {
    
    public function get_payments($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => 'all',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'payment_system' => '',
            'date_from' => '',
            'date_to' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        $where = ['1=1'];
        $params = [];
        
        if ($args['status'] !== 'all') {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where[] = '(transaction_id LIKE %s OR payment_data LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if (!empty($args['payment_system'])) {
            $where[] = 'payment_system = %s';
            $params[] = $args['payment_system'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = date('Y-m-d 00:00:00', strtotime($args['date_from']));
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = date('Y-m-d 23:59:59', strtotime($args['date_to']));
        }
        
        $where_clause = implode(' AND ', $where);
        
        $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $payments = $wpdb->get_results($wpdb->prepare($query, $params));
        
        return [
            'payments' => $payments,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $args['per_page'])
        ];
    }
    
    public function get_payment($id) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }
    
    public function get_payment_by_transaction($transaction_id) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE transaction_id = %s", $transaction_id));
    }
    
    public function create_payment($data) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        
        $defaults = [
            'user_id' => 0,
            'item_id' => 0,
            'amount' => 0,
            'payment_system' => '',
            'transaction_id' => '',
            'status' => 'pending',
            'payment_data' => '',
            'ip_address' => '',
            'user_agent' => ''
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        if (is_array($data['payment_data'])) {
            $data['payment_data'] = json_encode($data['payment_data'], JSON_UNESCAPED_UNICODE);
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public function update_payment_status($payment_id, $status, $additional_data = []) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        
        $data = ['status' => $status];
        
        if (!empty($additional_data)) {
            $payment = $this->get_payment($payment_id);
            if ($payment && $payment->payment_data) {
                $payment_data = json_decode($payment->payment_data, true);
                if (is_array($payment_data)) {
                    $additional_data = array_merge($payment_data, $additional_data);
                }
                $data['payment_data'] = json_encode($additional_data, JSON_UNESCAPED_UNICODE);
            }
        }
        
        return $wpdb->update($table, $data, ['id' => $payment_id]);
    }
    
    public function update_payment_status_by_transaction($transaction_id, $status, $additional_data = []) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        
        $payment = $this->get_payment_by_transaction($transaction_id);
        if (!$payment) {
            return false;
        }
        
        return $this->update_payment_status($payment->id, $status, $additional_data);
    }
    
    public function update_payment_delivery($payment_id, $delivery_data) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        
        $payment = $this->get_payment($payment_id);
        if (!$payment || !$payment->payment_data) {
            return false;
        }
        
        $payment_data = json_decode($payment->payment_data, true);
        if (!is_array($payment_data)) {
            $payment_data = [];
        }
        
        $payment_data['delivery'] = $delivery_data;
        
        return $wpdb->update($table, [
            'payment_data' => json_encode($payment_data, JSON_UNESCAPED_UNICODE)
        ], ['id' => $payment_id]);
    }
    
    public function get_total_amount($status = 'completed') {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$table} WHERE status = %s",
            $status
        )) ?: 0;
    }
}
