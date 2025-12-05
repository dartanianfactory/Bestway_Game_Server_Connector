<?php
class GSC_Controller_AJAX extends GSC_Controller_Base
{
    public function __construct()
    {
        add_action('wp_ajax_gsc_test_db_connection', [$this, 'test_db_connection']);
        add_action('wp_ajax_gsc_test_payment_connection', [$this, 'test_payment_connection']);
        add_action('wp_ajax_gsc_check_username', [$this, 'check_username']);
        add_action('wp_ajax_gsc_process_payment', [$this, 'process_payment']);
        add_action('wp_ajax_nopriv_gsc_process_payment', [$this, 'process_payment']);
        add_action('wp_ajax_gsc_register_user', [$this, 'register_user']);
        add_action('wp_ajax_nopriv_gsc_register_user', [$this, 'register_user']);
        add_action('wp_ajax_gsc_save_donate_item', [$this, 'save_donate_item']);
        add_action('wp_ajax_gsc_delete_donate_item', [$this, 'delete_donate_item']);
        add_action('wp_ajax_gsc_toggle_item_status', [$this, 'toggle_item_status']);
        add_action('wp_ajax_gsc_export_donate_items', [$this, 'export_donate_items']);
        add_action('wp_ajax_gsc_import_donate_items', [$this, 'import_donate_items']);
        add_action('wp_ajax_gsc_get_server_tables', [$this, 'get_server_tables']);
        add_action('wp_ajax_gsc_get_table_columns', [$this, 'get_table_columns']);
        add_action('wp_ajax_gsc_test_sync_connection', [$this, 'test_sync_connection']);
        add_action('wp_ajax_gsc_bulk_action_items', [$this, 'bulk_action_items']);
    }

    public function bulk_action_items()
    {
        $this->verify_ajax_security();

        $bulk_action = $this->get_request_param('bulk_action');
        $item_ids = $this->get_request_param('item_ids', [], 'array');

        $this->log('Массовое действие над предметами магазина', 'info', [
            'action' => $bulk_action,
            'item_ids' => $item_ids,
            'count' => count($item_ids)
        ]);

        $model = $this->get_model('Donate');
        if (!$model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $processed = 0;
        $errors = [];

        foreach ($item_ids as $item_id) {
            switch ($bulk_action) {
                case 'delete':
                    $result = $model->delete_item($item_id);
                    if ($result) {
                        $processed++;
                    } else {
                        $errors[] = 'Ошибка удаления предмета ID ' . $item_id;
                    }
                    break;

                case 'activate':
                case 'deactivate':
                case 'archive':
                    $status = str_replace(['activate', 'deactivate'], ['active', 'inactive'], $bulk_action);
                    $result = $model->update_item($item_id, ['status' => $status]);
                    if ($result['success']) {
                        $processed++;
                    } else {
                        $errors[] = 'Ошибка изменения статуса предмета ID ' . $item_id;
                    }
                    break;
            }
        }

        if ($processed > 0) {
            $this->log('Массовое действие выполнено: ' . $processed . ' предметов обработано', 'success');
            $this->json_response(true, ['processed' => $processed, 'errors' => $errors]);
        } else {
            $this->log('Массовое действие не выполнено', 'error', $errors);
            $this->json_response(false, [], implode('<br>', $errors));
        }
    }

    public function toggle_item_status()
    {
        $this->verify_ajax_security();

        $item_id = $this->get_request_param('item_id', 0, 'int');
        $status = $this->get_request_param('status', 'active');

        $this->log('Смена статуса предмета магазина', 'info', [
            'item_id' => $item_id,
            'new_status' => $status
        ]);

        $model = $this->get_model('Donate');
        if (!$model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $result = $model->update_item($item_id, ['status' => $status]);

        if ($result['success']) {
            $this->log('Статус предмета изменен: ID ' . $item_id . ' -> ' . $status, 'success');
            $this->json_response(true, [], 'Статус изменен');
        } else {
            $this->log('Ошибка изменения статуса предмета', 'error', $result['errors']);
            $this->json_response(false, $result['errors']);
        }
    }

    public function export_donate_items()
    {
        $nonce = $_GET['nonce'] ?? '';
        if (!check_ajax_referer('gsc_admin_nonce', 'nonce', false)) {
            $this->log('Security check failed in export_donate_items', 'error');
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            $this->log('Unauthorized access to export_donate_items', 'error');
            wp_send_json_error('Unauthorized');
        }

        $item_ids = isset($_GET['ids']) ? array_map('intval', explode(',', $_GET['ids'])) : [];

        $model = $this->get_model('Donate');
        if (!$model) {
            wp_send_json_error('Model not found');
        }

        $items = [];

        if (!empty($item_ids)) {
            foreach ($item_ids as $item_id) {
                $item = $model->get_item($item_id);
                if ($item) {
                    $items[] = $item;
                }
            }
        } else {
            $all_items = $model->get_items(['per_page' => 1000]);
            $items = $all_items['items'];
        }

        if (empty($items)) {
            wp_send_json_error('Нет данных для экспорта');
        }

        $export_data = [];
        foreach ($items as $item) {
            $export_data[] = [
                'id' => $item->id,
                'game_id' => $item->game_id,
                'title' => $item->title,
                'description' => $item->description,
                'image_url' => $item->image_url,
                'price' => $item->price,
                'sale_price' => $item->sale_price,
                'start_sale_at' => $item->start_sale_at,
                'end_sale_at' => $item->end_sale_at,
                'status' => $item->status,
                'sort_order' => $item->sort_order,
                'created_at' => $item->created_at
            ];
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="gsc_donate_items_' . date('Y-m-d') . '.json"');
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function import_donate_items()
    {
        $this->verify_ajax_security();

        $data = $this->get_request_param('data', [], 'array');

        $this->log('Импорт предметов магазина', 'info', [
            'count' => count($data)
        ]);

        $model = $this->get_model('Donate');
        if (!$model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $imported = 0;
        $errors = [];

        foreach ($data as $item_data) {
            $item_data = array_map('sanitize_text_field', $item_data);

            if (empty($item_data['game_id']) || empty($item_data['title'])) {
                $errors[] = 'Пропущен предмет без Game ID или названия';
                continue;
            }

            $existing = $model->get_item_by_game_id($item_data['game_id']);
            if ($existing) {
                $result = $model->update_item($existing->id, $item_data);
            } else {
                $result = $model->add_item($item_data);
            }

            if ($result['success']) {
                $imported++;
            } else {
                $errors[] = $result['errors'] ?? 'Ошибка сохранения предмета: ' . $item_data['game_id'];
            }
        }

        if ($imported > 0) {
            $this->log('Успешно импортировано ' . $imported . ' предметов магазина', 'success');
            $this->json_response(true, ['imported' => $imported, 'errors' => $errors]);
        } else {
            $this->log('Ошибки при импорте предметов', 'error', $errors);
            $this->json_response(false, $errors);
        }
    }

    public function get_server_tables()
    {
        $this->verify_ajax_security();

        if (get_option('gsc_db_enabled') !== '1') {
            $this->log('Database connection is disabled when trying to get server tables', 'error');
            $this->json_response(false, [], 'Database connection is disabled');
            return;
        }

        $db_model = $this->get_model('DB');
        if (!$db_model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $tables = $db_model->get_tables();

        $this->log('Получен список таблиц с сервера: ' . count($tables) . ' таблиц', 'db');
        $this->json_response(true, ['tables' => $tables]);
    }

    public function get_table_columns()
    {
        $this->verify_ajax_security();

        $table = $this->get_request_param('table');
        $type = $this->get_request_param('type', 'site');
        $target = $this->get_request_param('target', 'user');

        if (empty($table)) {
            $this->log('Table name is empty in get_table_columns', 'error');
            $this->json_response(false, [], 'Table name is required');
            return;
        }

        if ($type === 'site') {
            global $wpdb;

            $this->log('Получение колонок таблицы сайта: ' . $table, 'db');

            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$table_exists) {
                $this->log('Таблица не найдена: ' . $table, 'error');
                $this->json_response(false, [], 'Таблица не найдена: ' . $table);
                return;
            }

            try {
                $results = $wpdb->get_results("DESCRIBE `{$table}`");
                $columns = [];
                foreach ($results as $column) {
                    $columns[] = $column->Field;
                }

                if (empty($columns)) {
                    $this->log('В таблице нет колонок: ' . $table, 'error');
                    $this->json_response(false, [], 'В таблице нет колонок');
                    return;
                }

                $html = '<div class="gsc-columns-list" id="' . $type . '_' . $target . '_columns_list">';
                $html .= '<p><strong>Колонки в таблице ' . esc_html($table) . ':</strong></p>';
                $html .= '<div style="max-height: 200px; overflow-y: auto;">';

                foreach ($columns as $column) {
                    $html .= '<div class="column-item">' . esc_html($column) . '</div>';
                }

                $html .= '</div>';
                $html .= '<p class="description">Всего колонок: ' . count($columns) . '</p>';
                $html .= '</div>';

                $this->log('Получены колонки таблицы сайта: ' . $table . ' (' . count($columns) . ' колонок)', 'db');
                $this->json_response(true, [
                    'columns' => $columns,
                    'html' => $html
                ]);
            } catch (Exception $e) {
                $error_msg = 'Ошибка при получении колонок: ' . $e->getMessage();
                $this->log($error_msg, 'error');
                $this->json_response(false, [], $error_msg);
            }
        } else {
            $this->log('Получение колонок таблицы сервера: ' . $table, 'db');

            if (get_option('gsc_db_enabled') !== '1') {
                $this->log('Database connection is disabled when trying to get server table columns', 'error');
                $this->json_response(false, [], 'Database connection is disabled');
                return;
            }

            $db_model = $this->get_model('DB');
            if (!$db_model) {
                $this->json_response(false, [], 'Model not found');
                return;
            }

            $columns = $db_model->get_columns($table);

            if (empty($columns)) {
                $this->log('Таблица не найдена или пуста: ' . $table, 'error');
                $this->json_response(false, [], 'Таблица не найдена или пуста: ' . $table);
                return;
            }

            $html = '<div class="gsc-columns-list" id="' . $type . '_' . $target . '_columns_list">';
            $html .= '<p><strong>Колонки в таблице ' . esc_html($table) . ':</strong></p>';
            $html .= '<div style="max-height: 200px; overflow-y: auto;">';

            foreach ($columns as $column) {
                $html .= '<div class="column-item">' . esc_html($column) . '</div>';
            }

            $html .= '</div>';
            $html .= '<p class="description">Всего колонок: ' . count($columns) . '</p>';
            $html .= '</div>';

            $this->log('Получены колонки таблицы сервера: ' . $table . ' (' . count($columns) . ' колонок)', 'db');
            $this->json_response(true, [
                'columns' => $columns,
                'html' => $html
            ]);
        }
    }

    public function test_sync_connection()
    {
        $this->verify_ajax_security();

        $this->log('Начало тестирования синхронизации БД', 'sync');

        $form_data = wp_unslash($_POST['form_data'] ?? '');
        parse_str($form_data, $sync_settings);
        $sync_settings = $sync_settings['gsc_sync_settings'] ?? [];

        if (empty($sync_settings)) {
            $this->log('Настройки синхронизации не заданы в test_sync_connection', 'error');
            $this->json_response(false, [], 'Настройки синхронизации не заданы');
            return;
        }

        $required_user_fields = ['login', 'password', 'email'];
        $missing_user_fields = [];

        foreach ($required_user_fields as $field) {
            if (empty($sync_settings['site_user_' . $field]) || empty($sync_settings['server_user_' . $field])) {
                $missing_user_fields[] = $field;
            }
        }

        if (!empty($missing_user_fields)) {
            $error_msg = 'Не заданы обязательные поля пользователя: ' . implode(', ', $missing_user_fields);
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
            return;
        }

        if (get_option('gsc_db_enabled') !== '1') {
            $error_msg = 'Подключение к БД сервера отключено';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
            return;
        }

        $db_model = $this->get_model('DB');
        if (!$db_model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $connection_test = $db_model->test_connection();

        if (!$connection_test) {
            $errors = $db_model->get_errors();
            $error_msg = 'Ошибка подключения к серверу: ' . implode(', ', $errors);
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
            return;
        }

        $server_tables_to_check = [];

        if (!empty($sync_settings['server_user_table'])) {
            $server_tables_to_check[] = ['type' => 'пользователей', 'table' => $sync_settings['server_user_table']];
        }

        if (!empty($sync_settings['server_items_table'])) {
            $server_tables_to_check[] = ['type' => 'предметов', 'table' => $sync_settings['server_items_table']];
        }

        if (!empty($sync_settings['server_inventory_table'])) {
            $server_tables_to_check[] = ['type' => 'инвентаря', 'table' => $sync_settings['server_inventory_table']];
        }

        $errors = [];
        foreach ($server_tables_to_check as $table_info) {
            $this->log('Проверка таблицы ' . $table_info['type'] . ': ' . $table_info['table'], 'sync');
            $columns = $db_model->get_columns($table_info['table']);
            if (empty($columns)) {
                $error_msg = 'Таблица ' . $table_info['type'] . ' не найдена или пуста: ' . $table_info['table'];
                $errors[] = $error_msg;
                $this->log($error_msg, 'error');
            } else {
                $this->log('Таблица ' . $table_info['type'] . ' проверена: ' . count($columns) . ' колонок', 'sync');
            }
        }

        if (!empty($errors)) {
            $this->json_response(false, [], implode('<br>', $errors));
            return;
        }

        global $wpdb;
        if (!empty($sync_settings['site_user_table'])) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$sync_settings['site_user_table']}'");
            if (!$table_exists) {
                $error_msg = 'Таблица пользователей на сайте не найдена: ' . $sync_settings['site_user_table'];
                $this->log($error_msg, 'error');
                $this->json_response(false, [], $error_msg);
                return;
            }
        }

        if (!empty($sync_settings['server_items_table']) && empty($sync_settings['server_items_item_id'])) {
            $error_msg = 'Не задано поле ID предмета в таблице предметов на сервере';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
            return;
        }

        if (!empty($sync_settings['server_inventory_table'])) {
            if (empty($sync_settings['server_inventory_user_id'])) {
                $error_msg = 'Не задано поле ID пользователя в таблице инвентаря';
                $this->log($error_msg, 'error');
                $this->json_response(false, [], $error_msg);
                return;
            }
            if (empty($sync_settings['server_inventory_item_id'])) {
                $error_msg = 'Не задано поле ID предмета в таблице инвентаря';
                $this->log($error_msg, 'error');
                $this->json_response(false, [], $error_msg);
                return;
            }
        }

        $this->log('Синхронизация настроена корректно. Все таблицы проверены.', 'success');
        $this->json_response(true, [], 'Синхронизация настроена корректно. Все таблицы проверены.');
    }

    public function check_username()
    {
        $nonce = $this->get_request_param('nonce');
        if (!wp_verify_nonce($nonce, 'gsc_check_username')) {
            $this->log('Ошибка безопасности в check_username', 'error');
            $this->json_response(false, [], 'Ошибка безопасности');
            return;
        }

        $username = $this->get_request_param('username');

        if (empty($username)) {
            $this->log('Имя пользователя не указано в check_username', 'error');
            $this->json_response(false, [], 'Имя пользователя не указано');
            return;
        }

        $this->log('Проверка доступности имени пользователя: ' . $username, 'info');

        $db_model = $this->get_model('DB');
        if (!$db_model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $exists = $db_model->check_user_exists($username);

        $this->log('Результат проверки имени пользователя: ' . $username . ' - ' . ($exists ? 'занято' : 'свободно'), 'info');

        $this->json_response(true, [
            'available' => !$exists,
            'username' => $username
        ]);
    }

    public function process_payment()
    {
        $nonce = $this->get_request_param('nonce');
        if (!wp_verify_nonce($nonce, 'gsc_donate_nonce')) {
            $this->log('Ошибка безопасности при обработке платежа', 'error');
            $this->json_response(false, [], 'Ошибка безопасности');
            return;
        }

        $user_id = get_current_user_id();
        $item_id = $this->get_request_param('item_id', 0, 'int');
        $amount = $this->get_request_param('amount', 0, 'float');
        $payment_method = $this->get_request_param('payment_method');
        $game_username = $this->get_request_param('game_username');
        $user_email = $this->get_request_param('user_email', '', 'email');

        $this->log('Данные платежа', 'payment', [
            'user_id' => $user_id,
            'item_id' => $item_id,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'game_username' => $game_username,
            'user_email' => $user_email
        ]);

        if (!$user_id) {
            $error_msg = 'Требуется авторизация';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
            return;
        }

        if ($item_id <= 0 || $amount <= 0) {
            $error_msg = 'Неверные данные товара';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
            return;
        }

        if (empty($game_username)) {
            $error_msg = 'Укажите игровое имя';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
            return;
        }

        if (!is_email($user_email)) {
            $error_msg = 'Некорректный email';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
            return;
        }

        $donate_model = $this->get_model('Donate');
        if (!$donate_model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $item = $donate_model->get_item($item_id);

        if (!$item || $item->status !== 'active') {
            $error_msg = 'Товар недоступен для покупки';
            $this->log($error_msg, 'error', ['item_id' => $item_id]);
            $this->json_response(false, [], $error_msg);
            return;
        }

        $actual_price = $item->sale_price && $item->sale_price < $item->price ? $item->sale_price : $item->price;
        if (abs($actual_price - $amount) > 0.01) {
            $error_msg = 'Цена товара изменилась. Обновите страницу.';
            $this->log($error_msg, 'error', [
                'actual_price' => $actual_price,
                'requested_amount' => $amount
            ]);
            $this->json_response(false, [], $error_msg);
            return;
        }

        $payment_data = [
            'item_id' => $item_id,
            'item_title' => $item->title,
            'game_id' => $item->game_id,
            'payment_method' => $payment_method,
            'game_username' => $game_username,
            'user_email' => $user_email,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql')
        ];

        if ($payment_method === 'card') {
            $card_number = $this->get_request_param('card_number');
            $payment_data['card_last4'] = substr($card_number, -4);
            $payment_data['card_expiry'] = $this->get_request_param('card_expiry');
        }

        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;

        $result = $wpdb->insert($table, [
            'user_id' => $user_id,
            'item_id' => $item_id,
            'amount' => $amount,
            'payment_system' => get_option('gsc_payment_system'),
            'status' => 'pending',
            'payment_data' => wp_json_encode($payment_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => $payment_data['user_ip'],
            'user_agent' => $payment_data['user_agent']
        ]);

        if (!$result) {
            $error_msg = 'Ошибка создания платежа: ' . $wpdb->last_error;
            $this->log($error_msg, 'error');
            $this->json_response(false, [], 'Ошибка создания платежа');
            return;
        }

        $payment_id = $wpdb->insert_id;

        $redirect_url = $this->generate_payment_url($payment_id, $amount, $item->title);

        $this->log('Платеж создан успешно', 'payment', [
            'payment_id' => $payment_id,
            'redirect_url' => $redirect_url,
            'item_title' => $item->title
        ]);

        $this->json_response(true, [
            'payment_id' => $payment_id,
            'redirect_url' => $redirect_url,
            'message' => 'Платеж создан успешно. Сейчас вы будете перенаправлены на страницу оплаты.'
        ]);
    }

    private function generate_payment_url($payment_id, $amount, $description)
    {
        $payment_system = get_option('gsc_payment_system');
        $shop_id = get_option('gsc_payment_shop_id');

        if (!$payment_system || !$shop_id) {
            return '';
        }

        $params = [
            'payment_id' => $payment_id,
            'amount' => $amount,
            'description' => urlencode($description),
            'shop_id' => $shop_id
        ];

        switch ($payment_system) {
            case 'yookassa':
                $secret_key = get_option('gsc_payment_secret_key');
                $params['signature'] = md5($shop_id . ':' . $amount . ':' . $payment_id . ':' . $secret_key);
                return 'https://yookassa.ru/payments?' . http_build_query($params);

            case 'cloudpayments':
                $public_key = get_option('gsc_payment_public_key');
                $params['public_id'] = $public_key;
                return 'https://cloudpayments.ru/payments?' . http_build_query($params);

            case 'robokassa':
                $secret_key = get_option('gsc_payment_secret_key');
                $params['signature'] = md5($shop_id . ':' . $amount . ':' . $payment_id . ':' . $secret_key);
                return 'https://auth.robokassa.ru/Merchant/Index.aspx?' . http_build_query($params);

            default:
                return add_query_arg($params, get_option('gsc_payment_success_url', home_url('/donate-success/')));
        }
    }

    public function register_user()
    {
        $nonce = $this->get_request_param('gsc_register_nonce');
        if (!wp_verify_nonce($nonce, 'gsc_register')) {
            $this->log('Ошибка безопасности при регистрации', 'error');
            $this->json_response(false, [], 'Ошибка безопасности');
            return;
        }

        $username = $this->get_request_param('gsc_username');
        $email = $this->get_request_param('user_email', '', 'email');
        $password = $this->get_request_param('gsc_game_password');
        $password_confirm = $this->get_request_param('gsc_game_password_confirm');

        $this->log('Регистрация пользователя', 'info', [
            'username' => $username,
            'email' => $email
        ]);

        $errors = [];

        if (empty($username) || strlen($username) < 3 || strlen($username) > 32) {
            $errors[] = 'Имя пользователя должно быть от 3 до 32 символов';
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
            $errors[] = 'Имя пользователя может содержать только буквы, цифры, точки, дефисы и подчеркивания';
        }

        if (!is_email($email)) {
            $errors[] = 'Некорректный email адрес';
        }

        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать минимум 6 символов';
        }

        if ($password !== $password_confirm) {
            $errors[] = 'Пароли не совпадают';
        }

        if (!empty($errors)) {
            $this->log('Ошибки валидации при регистрации', 'error', $errors);
            $this->json_response(false, [], implode('<br>', $errors));
            return;
        }

        if (username_exists($username) || email_exists($email)) {
            $error_msg = 'Пользователь с такими данными уже существует';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], 'Пользователь с такими данными уже существует');
            return;
        }

        $db_model = $this->get_model('DB');
        if (!$db_model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $exists_in_game = $db_model->check_user_exists($username, $email);

        if ($exists_in_game) {
            $error_msg = 'Имя пользователя или email уже заняты в игровой системе';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], 'Имя пользователя или email уже заняты в игровой системе');
            return;
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            $this->log('Ошибка создания пользователя в WordPress: ' . $user_id->get_error_message(), 'error');
            $this->json_response(false, [], $user_id->get_error_message());
            return;
        }

        $game_account_id = $db_model->create_game_account($username, $password, $email);

        if ($game_account_id) {
            update_user_meta($user_id, 'gsc_game_account_id', $game_account_id);
            update_user_meta($user_id, 'gsc_game_username', $username);

            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            $this->log('Регистрация пользователя успешна', 'success', [
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
                'game_account_id' => $game_account_id
            ]);

            $this->json_response(true, [
                'redirect_url' => get_permalink(),
                'message' => 'Регистрация успешна! Создан игровой аккаунт.'
            ]);
        } else {
            wp_delete_user($user_id);

            $db_errors = $db_model->get_errors();
            $error_msg = 'Ошибка создания игрового аккаунта: ' . implode(', ', $db_errors);
            $this->log($error_msg, 'error');
            $this->json_response(false, [], 'Ошибка создания игрового аккаунта: ' . implode(', ', $db_errors));
        }
    }

    public function test_db_connection()
    {
        $this->log('AJAX: Тестирование подключения к БД - START', 'db', [
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'db_enabled' => get_option('gsc_db_enabled')
        ]);

        $this->verify_ajax_security();

        $this->log('Тестирование подключения к БД через AJAX', 'db');
 
        if (!class_exists('GSC_Model_DB')) {
            $model_path = GSC_PATH . 'includes/model/class-model-db.php';
            if (file_exists($model_path)) {
                require_once $model_path;
            } else {
                $this->log('Файл модели БД не найден: ' . $model_path, 'error');
                $this->json_response(false, [], 'Файл модели БД не найден');
                return;
            }
        }

        try {
            $db_model = new GSC_Model_DB();
        } catch (Exception $e) {
            $this->log('Ошибка при создании модели БД: ' . $e->getMessage(), 'error');
            $this->json_response(false, [], 'Ошибка при создании модели БД: ' . $e->getMessage());
            return;
        }

        try {
            $result = $db_model->test_connection();
        } catch (Exception $e) {
            $this->log('Ошибка в методе test_connection: ' . $e->getMessage(), 'error');
            $this->json_response(false, [], 'Ошибка в методе test_connection: ' . $e->getMessage());
            return;
        }

        if ($result) {
            try {
                $tables = $db_model->get_tables();
                $table_count = count($tables);

                if ($table_count > 0) {
                    $success_msg = "Подключение к БД успешно установлено. Найдено {$table_count} таблиц.";
                    $this->log($success_msg, 'success');
                    $this->json_response(true, [], $success_msg);
                } else {
                    $warning_msg = "Подключение установлено, но в базе данных нет таблиц. Проверьте имя базы данных.";
                    $this->log($warning_msg, 'warning');
                    $this->json_response(true, [], $warning_msg);
                }
            } catch (Exception $e) {
                $this->log('Ошибка при получении таблиц: ' . $e->getMessage(), 'error');
                $this->json_response(false, [], 'Ошибка при получении таблиц: ' . $e->getMessage());
            }
        } else {
            $errors = $db_model->get_errors();
            $error_msg = !empty($errors) ? implode('<br>', $errors) : 'Неизвестная ошибка подключения';
            $this->log($error_msg, 'error');
            $this->json_response(false, [], $error_msg);
        }

        $this->log('AJAX: Тестирование подключения к БД - END', 'db', [
            'result' => $result,
            'errors' => $errors ?? []
        ]);
    }

    public function test_payment_connection()
    {
        $this->verify_ajax_security();

        $system = get_option('gsc_payment_system');

        if (empty($system)) {
            $this->log('Платежная система не выбрана в тесте подключения', 'error');
            $this->json_response(false, [], 'Платежная система не выбрана');
            return;
        }

        $shop_id = get_option('gsc_payment_shop_id');
        $secret_key = get_option('gsc_payment_secret_key');
        $public_key = get_option('gsc_payment_public_key');

        if (empty($shop_id) || empty($secret_key)) {
            $this->log('Не заполнены ID магазина или секретный ключ для платежной системы: ' . $system, 'error');
            $this->json_response(false, [], 'Не заполнены ID магазина или секретный ключ');
            return;
        }

        $this->log('Тестирование подключения к платежной системе: ' . $system, 'payment');

        switch ($system) {
            case 'yookassa':
                $test_url = 'https://api.yookassa.ru/v3/payments';
                $auth = base64_encode($shop_id . ':' . $secret_key);
                $args = [
                    'headers' => [
                        'Authorization' => 'Basic ' . $auth,
                        'Idempotence-Key' => wp_generate_uuid4(),
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'amount' => [
                            'value' => '1.00',
                            'currency' => 'RUB'
                        ],
                        'capture' => false,
                        'description' => 'Тестовый платеж'
                    ]),
                    'timeout' => 10
                ];
                break;

            case 'cloudpayments':
                $test_url = 'https://api.cloudpayments.ru/test';
                if (empty($public_key)) {
                    $this->log('Не заполнен публичный ключ для CloudPayments', 'error');
                    $this->json_response(false, [], 'Не заполнен публичный ключ для CloudPayments');
                    return;
                }
                $args = [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($public_key . ':' . $secret_key)
                    ],
                    'timeout' => 10
                ];
                break;

            case 'robokassa':
                $test_url = 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/GetCurrencies';
                $args = [
                    'body' => [
                        'MerchantLogin' => $shop_id,
                        'Language' => 'ru'
                    ],
                    'timeout' => 10
                ];
                break;

            case 'paykeeper':
                $test_url = get_option('gsc_payment_webhook_url', '') . '/info/settings/';
                $args = [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($shop_id . ':' . $secret_key)
                    ],
                    'timeout' => 10
                ];
                break;

            case 'unitpay':
                $test_url = 'https://unitpay.ru/api';
                $params = [
                    'method' => 'ping',
                    'params[login]' => $shop_id,
                    'params[secretKey]' => $secret_key
                ];
                $args = [
                    'body' => $params,
                    'timeout' => 10
                ];
                break;

            default:
                $this->log('Платежная система ' . $system . ' настроена', 'success');
                $this->json_response(true, [], 'Платежная система ' . $system . ' настроена');
                return;
        }

        $response = wp_remote_get($test_url, $args);

        if (is_wp_error($response)) {
            $error_msg = 'Ошибка подключения: ' . $response->get_error_message();
            $this->log($error_msg, 'error');
            $this->json_response(false, [], 'Ошибка подключения: ' . $response->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200 || $code === 201) {
            $this->log('Подключение к платежной системе успешно: ' . $system, 'success');
            $this->json_response(true, [], 'Подключение к платежной системе успешно');
        } else {
            $body = wp_remote_retrieve_body($response);
            $error_msg = 'Ошибка платежной системы: код ' . $code . ' - ' . $body;
            $this->log($error_msg, 'error');
            $this->json_response(false, [], 'Ошибка платежной системы: код ' . $code . ' - ' . $body);
        }
    }

    public function save_donate_item()
    {
        $this->verify_ajax_security();

        $model = $this->get_model('Donate');
        if (!$model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $action = $this->get_request_param('item_action');
        $item_id = $this->get_request_param('item_id', 0, 'int');

        $data = [
            'game_id' => $this->get_request_param('game_id'),
            'title' => $this->get_request_param('title'),
            'description' => $this->get_request_param('description', '', 'textarea'),
            'image_url' => $this->get_request_param('image_url', '', 'url'),
            'price' => $this->get_request_param('price', 0, 'float'),
            'sale_price' => $this->get_request_param('sale_price', null, 'float'),
            'start_sale_at' => $this->get_request_param('start_sale_at'),
            'end_sale_at' => $this->get_request_param('end_sale_at'),
            'status' => $this->get_request_param('status', 'active'),
            'sort_order' => $this->get_request_param('sort_order', 0, 'int')
        ];

        $this->log('Сохранение предмета магазина', 'info', [
            'action' => $action,
            'item_id' => $item_id,
            'data' => $data
        ]);

        if ($action === 'add') {
            $result = $model->add_item($data);
        } else {
            $result = $model->update_item($item_id, $data);
        }

        if ($result['success']) {
            $this->log('Предмет магазина успешно сохранен', 'success');
            $this->json_response(true, ['redirect' => admin_url('admin.php?page=gsc-donate-items')]);
        } else {
            $this->log('Ошибка сохранения предмета магазина', 'error', $result['errors']);
            $this->json_response(false, $result['errors']);
        }
    }

    public function delete_donate_item()
    {
        $this->verify_ajax_security();

        $item_id = $this->get_request_param('item_id', 0, 'int');

        $this->log('Удаление предмета магазина', 'info', ['item_id' => $item_id]);

        $model = $this->get_model('Donate');
        if (!$model) {
            $this->json_response(false, [], 'Model not found');
            return;
        }

        $result = $model->delete_item($item_id);

        if ($result) {
            $this->log('Предмет магазина удален: ID ' . $item_id, 'success');
            $this->json_response(true, [], 'Предмет удален');
        } else {
            $this->log('Ошибка при удалении предмета магазина: ID ' . $item_id, 'error');
            $this->json_response(false, [], 'Ошибка при удалении');
        }
    }
}
?>