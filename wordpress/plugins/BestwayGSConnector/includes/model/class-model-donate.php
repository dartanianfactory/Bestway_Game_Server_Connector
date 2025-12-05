<?php
class GSC_Model_Donate {
    public function get_items($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => 'active',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'sort_order',
            'order' => 'ASC',
            'search' => '',
            'game_id' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        $where = ['1=1'];
        $params = [];
        
        if ($args['status'] !== 'all') {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where[] = '(title LIKE %s OR description LIKE %s OR game_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if (!empty($args['game_id'])) {
            $where[] = 'game_id = %s';
            $params[] = $args['game_id'];
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
        
        $items = $wpdb->get_results($wpdb->prepare($query, $params));
        
        return [
            'items' => $items,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $args['per_page'])
        ];
    }
    
    public function get_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public function get_item_by_game_id($game_id) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE game_id = %s",
            $game_id
        ));
        
        return $item;
    }
    
    public function add_item($data) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        
        $defaults = [
            'game_id' => '',
            'title' => '',
            'description' => '',
            'image_url' => '',
            'price' => 0,
            'sale_price' => null,
            'start_sale_at' => null,
            'end_sale_at' => null,
            'status' => 'active',
            'sort_order' => 0
        ];
        
        $data = wp_parse_args($data, $defaults);

        $errors = $this->validate_item_data($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        if (!empty($data['start_sale_at'])) {
            $data['start_sale_at'] = date('Y-m-d H:i:s', strtotime($data['start_sale_at']));
        }
        
        if (!empty($data['end_sale_at'])) {
            $data['end_sale_at'] = date('Y-m-d H:i:s', strtotime($data['end_sale_at']));
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            return ['success' => true, 'id' => $wpdb->insert_id];
        }
        
        return ['success' => false, 'errors' => [$wpdb->last_error]];
    }
    
    public function update_item($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        
        $errors = $this->validate_item_data($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        if (isset($data['start_sale_at']) && !empty($data['start_sale_at'])) {
            $data['start_sale_at'] = date('Y-m-d H:i:s', strtotime($data['start_sale_at']));
        }
        
        if (isset($data['end_sale_at']) && !empty($data['end_sale_at'])) {
            $data['end_sale_at'] = date('Y-m-d H:i:s', strtotime($data['end_sale_at']));
        }
        
        $result = $wpdb->update($table, $data, ['id' => $id]);
        
        if ($result !== false) {
            return ['success' => true];
        }
        
        return ['success' => false, 'errors' => [$wpdb->last_error]];
    }
    
    public function delete_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        return $wpdb->delete($table, ['id' => $id]);
    }
    
    private function validate_item_data($data, $id = null) {
        $errors = [];
        
        if (empty($data['game_id'])) {
            $errors[] = 'Game ID обязателен';
        }
        
        if (empty($data['title'])) {
            $errors[] = 'Название обязательно';
        }
        
        if (!is_numeric($data['price']) || $data['price'] < 0) {
            $errors[] = 'Цена должна быть положительным числом';
        }
        
        if (isset($data['sale_price']) && !empty($data['sale_price'])) {
            if (!is_numeric($data['sale_price']) || $data['sale_price'] < 0) {
                $errors[] = 'Цена со скидкой должна быть положительным числом';
            }
            
            if ($data['sale_price'] >= $data['price']) {
                $errors[] = 'Цена со скидкой должна быть меньше обычной цены';
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        $query = "SELECT COUNT(*) FROM {$table} WHERE game_id = %s";
        $params = [$data['game_id']];
        
        if ($id) {
            $query .= " AND id != %d";
            $params[] = $id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, $params));
        if ($count > 0) {
            $errors[] = 'Предмет с таким Game ID уже существует';
        }
        
        return $errors;
    }
    
    public function get_active_items() {
        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        
        $now = current_time('mysql');
        $query = $wpdb->prepare("
            SELECT * FROM {$table} 
            WHERE status = 'active' 
            AND (start_sale_at IS NULL OR start_sale_at <= %s)
            AND (end_sale_at IS NULL OR end_sale_at >= %s)
            ORDER BY sort_order ASC, title ASC
        ", $now, $now);
        
        return $wpdb->get_results($query);
    }
}
