<?php
class GSC_Controller_Payment extends GSC_Controller_Base {
    
    public function __construct() {
        add_action('init', [$this, 'handle_payment_webhook']);
    }
    
    public function handle_payment_webhook() {
        if (!isset($_GET['gsc_payment_webhook'])) {
            return;
        }

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $payment_system = $this->get_request_param('system');
        
        $this->log('Получен вебхук от платежной системы: ' . $payment_system, 'payment', $_GET);
        
        if (empty($payment_system)) {
            $this->log_error('Payment system not specified in webhook');
            wp_die('Invalid request', 400);
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }
        
        $this->log('Данные вебхука', 'payment', $data);
        
        $this->process_webhook($payment_system, $data);
        
        http_response_code(200);
        echo 'OK';
        exit;
    }
    
    private function process_webhook($system, $data) {
        $model = $this->get_model('Payment');
        if (!$model) {
            $this->log_error('Payment model not found');
            return;
        }
        
        $this->log('Обработка вебхука от системы: ' . $system, 'payment');
        
        switch ($system) {
            case 'yookassa':
                $this->process_yookassa_webhook($model, $data);
                break;
            case 'cloudpayments':
                $this->process_cloudpayments_webhook($model, $data);
                break;
            case 'robokassa':
                $this->process_robokassa_webhook($model, $data);
                break;
            case 'paykeeper':
                $this->process_paykeeper_webhook($model, $data);
                break;
            case 'unitpay':
                $this->process_unitpay_webhook($model, $data);
                break;
            default:
                $this->process_custom_webhook($model, $data);
        }
    }
    
    private function process_yookassa_webhook($model, $data) {
        $this->log('Обработка вебхука YooKassa', 'payment');
        
        if (!isset($data['object']['id']) || !isset($data['event'])) {
            $this->log_error('Invalid YooKassa webhook data', $data);
            return;
        }
        
        $payment_id = sanitize_text_field($data['object']['id']);
        $status = $this->map_yookassa_status($data['object']['status']);
        
        $this->log('Обновление статуса платежа YooKassa', 'payment', [
            'payment_id' => $payment_id,
            'status' => $status,
            'event' => $data['event']
        ]);
        
        $result = $model->update_payment_status_by_transaction($payment_id, $status, [
            'webhook_data' => $data,
            'processed_at' => current_time('mysql'),
            'webhook_event' => $data['event']
        ]);
        
        if ($result) {
            $this->log_success('Статус платежа YooKassa обновлен', [
                'payment_id' => $payment_id,
                'status' => $status
            ]);
            
            if ($status === 'completed') {
                $this->deliver_item($payment_id);
            }
        } else {
            $this->log_error('Не удалось обновить статус платежа YooKassa', [
                'payment_id' => $payment_id,
                'status' => $status
            ]);
        }
    }
    
    private function map_yookassa_status($status) {
        $map = [
            'pending' => 'pending',
            'waiting_for_capture' => 'pending',
            'succeeded' => 'completed',
            'canceled' => 'failed'
        ];
        
        return $map[$status] ?? 'failed';
    }
    
    private function process_cloudpayments_webhook($model, $data) {
        $this->log('Обработка вебхука CloudPayments', 'payment');
        
        if (!isset($data['InvoiceId']) || !isset($data['Status'])) {
            $this->log_error('Invalid CloudPayments webhook data', $data);
            return;
        }
        
        $payment_id = intval($data['InvoiceId']);
        $status = $data['Status'] === 'Completed' ? 'completed' : 'failed';
        
        $this->log('Обновление статуса платежа CloudPayments', 'payment', [
            'payment_id' => $payment_id,
            'status' => $status
        ]);
        
        $result = $model->update_payment_status($payment_id, $status, [
            'webhook_data' => $data,
            'processed_at' => current_time('mysql')
        ]);
        
        if ($result) {
            $this->log_success('Статус платежа CloudPayments обновлен', [
                'payment_id' => $payment_id,
                'status' => $status
            ]);
            
            if ($status === 'completed') {
                $this->deliver_item_by_payment_id($payment_id);
            }
        } else {
            $this->log_error('Не удалось обновить статус платежа CloudPayments', [
                'payment_id' => $payment_id,
                'status' => $status
            ]);
        }
    }
    
    private function process_robokassa_webhook($model, $data) {
        $this->log('Обработка вебхука Robokassa', 'payment');
        
        if (!isset($data['InvId']) || !isset($data['OutSum'])) {
            $this->log_error('Invalid Robokassa webhook data', $data);
            return;
        }
        
        $payment_id = intval($data['InvId']);
        $signature = strtoupper(md5("{$data['OutSum']}:{$payment_id}:" . get_option('gsc_payment_secret_key')));
        
        if ($signature !== strtoupper($data['SignatureValue'])) {
            $this->log_error('Invalid Robokassa signature', [
                'expected' => $signature,
                'received' => $data['SignatureValue'],
                'data' => $data
            ]);
            return;
        }
        
        $this->log('Обновление статуса платежа Robokassa', 'payment', [
            'payment_id' => $payment_id,
            'signature_valid' => true
        ]);
        
        $result = $model->update_payment_status($payment_id, 'completed', [
            'webhook_data' => $data,
            'processed_at' => current_time('mysql')
        ]);
        
        if ($result) {
            $this->log_success('Статус платежа Robokassa обновлен', [
                'payment_id' => $payment_id,
                'status' => 'completed'
            ]);
            
            $this->deliver_item_by_payment_id($payment_id);
        } else {
            $this->log_error('Не удалось обновить статус платежа Robokassa', [
                'payment_id' => $payment_id
            ]);
        }
    }
    
    private function process_paykeeper_webhook($model, $data) {
        $this->log('Обработка вебхука PayKeeper', 'payment');
        
        if (!isset($data['id']) || !isset($data['status'])) {
            $this->log_error('Invalid PayKeeper webhook data', $data);
            return;
        }
        
        $payment_id = sanitize_text_field($data['id']);
        $status = $data['status'] === 'paid' ? 'completed' : 'failed';
        
        $this->log('Обновление статуса платежа PayKeeper', 'payment', [
            'payment_id' => $payment_id,
            'status' => $status
        ]);
        
        $result = $model->update_payment_status_by_transaction($payment_id, $status, [
            'webhook_data' => $data,
            'processed_at' => current_time('mysql')
        ]);
        
        if ($result) {
            $this->log_success('Статус платежа PayKeeper обновлен', [
                'payment_id' => $payment_id,
                'status' => $status
            ]);
            
            if ($status === 'completed') {
                $this->deliver_item_by_transaction_id($payment_id);
            }
        } else {
            $this->log_error('Не удалось обновить статус платежа PayKeeper', [
                'payment_id' => $payment_id,
                'status' => $status
            ]);
        }
    }
    
    private function process_unitpay_webhook($model, $data) {
        $this->log('Обработка вебхука UnitPay', 'payment');
        
        if (!isset($data['method']) || !isset($data['params'])) {
            $this->log_error('Invalid UnitPay webhook data', $data);
            return;
        }
        
        $params = $data['params'];
        
        if (!isset($params['account']) || !isset($params['orderSum'])) {
            $this->log_error('Invalid UnitPay webhook params', $params);
            return;
        }
        
        $payment_id = intval($params['account']);
        $signature = hash('sha256', $params['account'] . $params['orderSum'] . get_option('gsc_payment_secret_key'));
        
        if ($signature !== $params['signature']) {
            $this->log_error('Invalid UnitPay signature', [
                'expected' => $signature,
                'received' => $params['signature'],
                'data' => $data
            ]);
            return;
        }
        
        $status = $data['method'] === 'pay' ? 'completed' : 'failed';
        
        $this->log('Обновление статуса платежа UnitPay', 'payment', [
            'payment_id' => $payment_id,
            'status' => $status,
            'method' => $data['method']
        ]);
        
        $result = $model->update_payment_status($payment_id, $status, [
            'webhook_data' => $data,
            'processed_at' => current_time('mysql')
        ]);
        
        if ($result) {
            $this->log_success('Статус платежа UnitPay обновлен', [
                'payment_id' => $payment_id,
                'status' => $status
            ]);
            
            if ($status === 'completed') {
                $this->deliver_item_by_payment_id($payment_id);
            }
        } else {
            $this->log_error('Не удалось обновить статус платежа UnitPay', [
                'payment_id' => $payment_id,
                'status' => $status
            ]);
        }
    }
    
    private function process_custom_webhook($model, $data) {
        $this->log('Обработка кастомного вебхука', 'payment', $data);
        $this->log_error('Custom webhook processing not implemented: ' . json_encode($data));
    }
    
    private function deliver_item_by_payment_id($payment_id) {
        $this->log('Доставка предмета по ID платежа: ' . $payment_id, 'payment');
        
        $model = $this->get_model('Payment');
        if (!$model) {
            $this->log_error("Payment model not found for payment #{$payment_id}");
            return;
        }

        $payment = $model->get_payment($payment_id);
        
        if (!$payment) {
            $this->log_error("Payment #{$payment_id} not found");
            return;
        }
        
        $this->deliver_item_to_game($payment);
    }
    
    private function deliver_item_by_transaction_id($transaction_id) {
        $this->log('Доставка предмета по ID транзакции: ' . $transaction_id, 'payment');
        
        $model = $this->get_model('Payment');
        if (!$model) {
            $this->log_error("Payment model not found for transaction {$transaction_id}");
            return;
        }

        $payment = $model->get_payment_by_transaction($transaction_id);
        
        if (!$payment) {
            $this->log_error("Transaction {$transaction_id} not found");
            return;
        }
        
        $this->deliver_item_to_game($payment);
    }
    
    private function deliver_item_to_game($payment) {
        $this->log('Начало доставки предмета в игру для платежа #' . $payment->id, 'payment');
        
        $payment_data = json_decode($payment->payment_data, true);
        
        if (!$payment_data) {
            $error_msg = "Не удалось декодировать данные платежа #{$payment->id}";
            $this->log_error($error_msg);
            return;
        }
        
        if (!isset($payment_data['game_username']) || !isset($payment_data['game_id'])) {
            $error_msg = "Отсутствуют обязательные данные для доставки в платеже #{$payment->id}";
            $this->log_error($error_msg, $payment_data);
            return;
        }
        
        $game_username = $payment_data['game_username'];
        $game_id = $payment_data['game_id'];
        
        $this->log('Доставка предмета в игру', 'sync', [
            'payment_id' => $payment->id,
            'game_username' => $game_username,
            'game_id' => $game_id
        ]);
        
        $sync_settings = get_option('gsc_sync_settings', []);
        
        if (empty($sync_settings['server_inventory_table']) || 
            empty($sync_settings['server_inventory_user_id']) ||
            empty($sync_settings['server_inventory_item_id'])) {
            
            $error_msg = "Не настроена синхронизация инвентаря для платежа #{$payment->id}";
            $this->log_error($error_msg);
            return;
        }
        
        try {
            $db_model = $this->get_model('DB');
            if (!$db_model) {
                $this->log_error("DB model not found for payment #{$payment->id}");
                return;
            }

            $server_user_id = $this->get_server_user_id($game_username, $sync_settings);
            
            if (!$server_user_id) {
                $error_msg = "Пользователь {$game_username} не найден на сервере для платежа #{$payment->id}";
                $this->log_error($error_msg);
                return;
            }

            $result = $this->add_to_inventory($server_user_id, $game_id, $sync_settings);
            
            if ($result) {
                $success_msg = "Предмет {$game_id} доставлен игроку {$game_username} (серверный ID: {$server_user_id})";
                $this->log_success($success_msg);
                
                $model = $this->get_model('Payment');
                if ($model) {
                    $model->update_payment_delivery($payment->id, [
                        'delivered_at' => current_time('mysql'),
                        'delivery_status' => 'success',
                        'server_user_id' => $server_user_id,
                        'server_inventory_id' => $result
                    ]);
                }
                
                $this->send_delivery_notification($payment, $success_msg);
            } else {
                throw new Exception('Не удалось добавить предмет в инвентарь');
            }
            
        } catch (Exception $e) {
            $error_msg = "Ошибка доставки предмета для платежа #{$payment->id}: " . $e->getMessage();
            $this->log_error($error_msg);
            
            $model = $this->get_model('Payment');
            if ($model) {
                $model->update_payment_delivery($payment->id, [
                    'delivered_at' => current_time('mysql'),
                    'delivery_status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }
        }
    }
    
    private function get_server_user_id($username, $sync_settings) {
        if (empty($sync_settings['server_user_table']) || empty($sync_settings['server_user_login'])) {
            return false;
        }
        
        $db_model = $this->get_model('DB');
        if (!$db_model) {
            return false;
        }
        
        $table = $sync_settings['server_user_table'];
        $login_field = $sync_settings['server_user_login'];
        $id_field = $sync_settings['server_user_user_id'] ?? 'id';
        
        try {
            $sql = "SELECT {$id_field} FROM {$table} WHERE {$login_field} = :username LIMIT 1";
            $stmt = $db_model->remote_conn->prepare($sql);
            $stmt->execute([':username' => $username]);
            $result = $stmt->fetch();
            
            return $result ? $result[$id_field] : false;
        } catch (Exception $e) {
            $this->log_error('Ошибка получения ID пользователя на сервере: ' . $e->getMessage());
            return false;
        }
    }
    
    private function add_to_inventory($server_user_id, $game_id, $sync_settings) {
        $table = $sync_settings['server_inventory_table'];
        $user_id_field = $sync_settings['server_inventory_user_id'];
        $item_id_field = $sync_settings['server_inventory_item_id'];
        $quantity_field = $sync_settings['server_inventory_quantity'] ?? 'quantity';
        $slot_field = $sync_settings['server_inventory_slot'] ?? 'slot';
        $default_slot = $sync_settings['default_slot'] ?? 0;
        
        $db_model = $this->get_model('DB');
        if (!$db_model) {
            throw new Exception('DB model not found');
        }
        
        $data = [
            $user_id_field => $server_user_id,
            $item_id_field => $game_id
        ];
        
        if ($quantity_field) {
            $data[$quantity_field] = 1;
        }
        
        if ($slot_field) {
            $data[$slot_field] = $default_slot;
        }

        if (!empty($sync_settings['server_inventory_acquire_date'])) {
            $data[$sync_settings['server_inventory_acquire_date']] = date('Y-m-d H:i:s');
        }
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        try {
            $stmt = $db_model->remote_conn->prepare($sql);
            $stmt->execute($data);
            
            return $db_model->remote_conn->lastInsertId();
        } catch (Exception $e) {
            $this->log_error('Ошибка добавления предмета в инвентарь: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function send_delivery_notification($payment, $message) {
        $payment_data = json_decode($payment->payment_data, true);
        
        if (!empty($payment_data['user_email'])) {
            $subject = 'Ваш предмет доставлен';
            $body = "Здравствуйте!\n\n" .
                   "Ваш предмет был успешно доставлен в игру.\n" .
                   "Детали доставки: {$message}\n\n" .
                   "Спасибо за покупку!\n" .
                   "Команда проекта";
            
            wp_mail($payment_data['user_email'], $subject, $body);
            
            $this->log('Уведомление о доставке отправлено на email: ' . $payment_data['user_email'], 'info');
        }
    }
    
    private function log_error($message, $context = []) {
        $this->log($message, 'error', $context);
    }
    
    private function log_success($message, $context = []) {
        $this->log($message, 'success', $context);
    }
}
?>
