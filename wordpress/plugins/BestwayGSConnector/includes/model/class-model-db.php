<?php
class GSC_Model_DB
{
    public $remote_conn = null;
    private $errors = [];
    private $in_transaction = false;
    private $transaction_level = 0;

    public function __construct()
    {
        GSC_Controller_Log::db('Инициализация подключения к БД - START');

        if (get_option('gsc_db_enabled') === '1') {
            $this->test_connection();
        } else {
            GSC_Controller_Log::db('Подключение к БД отключено в настройках');
        }

        GSC_Controller_Log::db('Инициализация подключения к БД - END');
    }

    public function test_connection()
    {
        GSC_Controller_Log::db('Тестирование подключения к БД сервера - START');

        if (!extension_loaded('mysqli')) {
            $error_msg = 'Расширение mysqli не установлено на сервере';
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg);
            GSC_Controller_Log::db('Тестирование подключения к БД сервера - END (mysqli не установлено)');
            return false;
        }

        $host = get_option('gsc_db_host');
        $name = get_option('gsc_db_name');
        $user = get_option('gsc_db_user');
        $password = get_option('gsc_db_password');
        $port = get_option('gsc_db_port', '3306');
        $charset = get_option('gsc_db_charset', 'utf8mb4');

        GSC_Controller_Log::db('Настройки подключения к БД', [
            'host' => $host,
            'database' => $name,
            'user' => $user,
            'port' => $port,
            'charset' => $charset,
            'db_enabled' => get_option('gsc_db_enabled')
        ]);

        if (empty($host) || empty($name) || empty($user)) {
            $error_msg = 'Не заполнены настройки подключения к БД';
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg, [
                'host_set' => !empty($host),
                'db_set' => !empty($name),
                'user_set' => !empty($user)
            ]);
            GSC_Controller_Log::db('Тестирование подключения к БД сервера - END (не заполнены настройки)');
            return false;
        }

        try {
            if ($host === 'localhost' || $host === '127.0.0.1') {
                $host = ini_get("mysqli.default_host");
            }
            
            $port = !empty($port) ? $port : 3306;
            
            $this->remote_conn = new mysqli($host, $user, $password, $name, $port);

            if ($this->remote_conn->connect_error) {
                throw new Exception($this->remote_conn->connect_error, $this->remote_conn->connect_errno);
            }

            if (!$this->remote_conn->set_charset($charset)) {
                GSC_Controller_Log::db('Не удалось установить кодировку: ' . $charset . ', ошибка: ' . $this->remote_conn->error);
            } else {
                GSC_Controller_Log::db('Установлена кодировка: ' . $charset);
            }

            GSC_Controller_Log::success('Подключение к БД успешно установлено');

            $version = $this->remote_conn->server_version;
            GSC_Controller_Log::db('Версия MySQL сервера: ' . $version);

            $tables = $this->get_tables();
            $tables_count = count($tables);
            GSC_Controller_Log::db('Количество таблиц в БД: ' . $tables_count);

            if ($tables_count > 0) {
                GSC_Controller_Log::db('Первые 5 таблиц: ' . implode(', ', array_slice($tables, 0, 5)));
            }

            GSC_Controller_Log::db('Тестирование подключения к БД сервера - END (успешно)');
            return true;
        } catch (Exception $e) {
            $error_msg = 'Ошибка подключения к БД: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg, [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'host' => $host,
                'db' => $name,
                'user' => $user,
                'port' => $port
            ]);
            $this->remote_conn = null;
            GSC_Controller_Log::db('Тестирование подключения к БД сервера - END (ошибка)');
            return false;
        }
    }

    protected function get_tables()
    {
        $tables = [];

        if ($this->remote_conn instanceof mysqli) {
            $result = $this->remote_conn->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }
                $result->free();
            } else {
                GSC_Controller_Log::db('Ошибка получения таблиц: ' . $this->remote_conn->error);
            }
        }

        return $tables;
    }

    public function create_game_account($username, $password, $email = '')
    {
        GSC_Controller_Log::db('Начало создания игрового аккаунта для: ' . $username, [
            'email' => $email,
            'has_connection' => ($this->remote_conn !== null)
        ]);

        if (!$this->remote_conn) {
            $this->errors[] = 'Нет подключения к БД игрового сервера';
            GSC_Controller_Log::error('Нет подключения к БД при создании аккаунта: ' . $username);
            return false;
        }

        if (!$this->validate_registration_data($username, $password, $email)) {
            GSC_Controller_Log::error('Валидация данных провалена', [
                'username' => $username,
                'email' => $email
            ]);
            return false;
        }

        $sync_settings = get_option('gsc_sync_settings', []);

        $table = $sync_settings['server_user_table'] ?? get_option('gsc_db_table', '');
        $field_username = $sync_settings['server_user_login'] ?? get_option('gsc_db_field_username', 'login');
        $field_password = $sync_settings['server_user_password'] ?? get_option('gsc_db_field_password', 'password');
        $field_email = $sync_settings['server_user_email'] ?? get_option('gsc_db_field_email', 'email');

        if (empty($table)) {
            $error_msg = 'Не настроена таблица пользователей. Перейдите во вкладку "Синхронизация БД"';
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg);
            return false;
        }

        if ($this->user_exists($username, $email, $table, $field_username, $field_email)) {
            $error_msg = 'Пользователь с такими данными уже существует';
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg, [
                'username' => $username,
                'email' => $email
            ]);
            return false;
        }

        $hashed_password = $this->hash_password($password);

        try {
            $safe_table = $this->escape_identifier($table);
            $safe_field_username = $this->escape_identifier($field_username);
            $safe_field_password = $this->escape_identifier($field_password);

            $sql = "INSERT INTO {$safe_table} ({$safe_field_username}, {$safe_field_password}";
            $placeholders = "VALUES (?, ?";
            
            $types = "ss";
            $params = [$username, $hashed_password];

            if (!empty($field_email) && !empty($email)) {
                $safe_field_email = $this->escape_identifier($field_email);
                $sql .= ", {$safe_field_email}";
                $placeholders .= ", ?";
                $types .= "s";
                $params[] = $email;
            }

            $sql .= ") " . $placeholders . ")";

            GSC_Controller_Log::db('Выполнение SQL для создания аккаунта', [
                'sql' => $sql,
                'params' => $params,
                'types' => $types
            ]);

            $stmt = $this->remote_conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса: ' . $this->remote_conn->error);
            }

            $stmt->bind_param($types, ...$params);
            
            $result = $stmt->execute();
            
            if ($result) {
                $account_id = $stmt->insert_id;
                $stmt->close();
                
                GSC_Controller_Log::success('Создан игровой аккаунт: ' . $username . ' (ID: ' . $account_id . ')', [
                    'user_id' => $account_id,
                    'email' => $email
                ]);
                return $account_id;
            } else {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Ошибка выполнения запроса: ' . $error);
            }

        } catch (Exception $e) {
            $error_msg = 'Ошибка создания аккаунта: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg, [
                'username' => $username,
                'email' => $email,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function check_user_exists($username, $email = '')
    {
        GSC_Controller_Log::db('Проверка существования пользователя: ' . $username . (empty($email) ? '' : ', email: ' . $email));
        return $this->user_exists($username, $email);
    }

    public function get_errors()
    {
        return $this->errors;
    }

    public function get_last_error()
    {
        return end($this->errors);
    }

    public function get_columns($table)
    {
        if (!$this->remote_conn) {
            $this->errors[] = 'Нет подключения к БД игрового сервера';
            GSC_Controller_Log::error('Нет подключения к БД при получении колонок таблицы: ' . $table);
            return [];
        }

        try {
            $safe_table = $this->escape_identifier($table);
            $result = $this->remote_conn->query("DESCRIBE {$safe_table}");
            
            if (!$result) {
                throw new Exception('Ошибка получения колонок: ' . $this->remote_conn->error);
            }
            
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            $result->free();

            GSC_Controller_Log::db('Получены колонки таблицы: ' . $table . ' (' . count($columns) . ' колонок)');
            return $columns;
        } catch (Exception $e) {
            $error_msg = 'Ошибка получения колонок таблицы: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg);
            return [];
        }
    }

    public function get_connection_status()
    {
        $status = $this->remote_conn !== null;
        GSC_Controller_Log::db('Статус подключения к БД: ' . ($status ? 'установлено' : 'отсутствует'));
        return $status;
    }


    public function execute_query($sql, $params = [], $types = '')
    {
        if (!$this->remote_conn) {
            $this->errors[] = 'Нет подключения к БД';
            GSC_Controller_Log::error('Нет подключения к БД при выполнении запроса');
            return false;
        }

        try {
            GSC_Controller_Log::db('Выполнение SQL запроса', [
                'sql' => $sql,
                'params' => $params,
                'in_transaction' => $this->in_transaction
            ]);

            $stmt = $this->remote_conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса: ' . $this->remote_conn->error);
            }

            if (!empty($params)) {
                if (empty($types)) {
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_double($param)) {
                            $types .= 'd';
                        } else {
                            $types .= 's';
                        }
                    }
                }
                $stmt->bind_param($types, ...$params);
            }

            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception('Ошибка выполнения запроса: ' . $stmt->error);
            }

            if (stripos($sql, 'SELECT') === 0) {
                $result = $stmt->get_result();
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $stmt->close();
                return $rows;
            }

            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            return $affected_rows;

        } catch (Exception $e) {
            $error_msg = 'Ошибка выполнения запроса: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg, [
                'sql' => $sql,
                'params' => $params,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Выполняет транзакцию с несколькими запросами (ACID)
     * 
     * @param array $queries Массив запросов в формате:
     * [
     *     ['sql' => 'INSERT ...', 'params' => [...], 'types' => 'ssi'],
     *     ['sql' => 'UPDATE ...', 'params' => [...], 'types' => 'di'],
     *     // или просто строки SQL
     *     'DELETE FROM table WHERE id = 1'
     * ]
     * @param bool $auto_commit Автоматически коммитить транзакцию
     * @return bool|array Успех выполнения или массив результатов для SELECT запросов
     */
    public function execute_query_transaction($queries, $auto_commit = true)
    {
        if (!$this->remote_conn) {
            $this->errors[] = 'Нет подключения к БД';
            GSC_Controller_Log::error('Нет подключения к БД при выполнении транзакции');
            return false;
        }

        $is_nested = $this->in_transaction;
        
        if (!$is_nested) {
            $this->begin_transaction();
        }

        GSC_Controller_Log::db('Начало выполнения транзакции', [
            'query_count' => count($queries),
            'auto_commit' => $auto_commit,
            'is_nested' => $is_nested,
            'transaction_level' => $this->transaction_level
        ]);

        $results = [];
        $has_select = false;

        try {
            foreach ($queries as $index => $query) {
                if (is_string($query)) {
                    $sql = $query;
                    $params = [];
                    $types = '';
                } elseif (is_array($query) && isset($query['sql'])) {
                    $sql = $query['sql'];
                    $params = $query['params'] ?? [];
                    $types = $query['types'] ?? '';
                } else {
                    throw new Exception("Некорректный формат запроса №{$index}");
                }

                GSC_Controller_Log::db('Выполнение запроса в транзакции', [
                    'index' => $index,
                    'sql' => $sql,
                    'has_params' => !empty($params),
                    'in_transaction' => true
                ]);

                $stmt = $this->remote_conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Ошибка подготовки запроса №' . $index . ': ' . $this->remote_conn->error);
                }

                if (!empty($params)) {
                    if (empty($types)) {
                        $types = '';
                        foreach ($params as $param) {
                            if (is_int($param)) {
                                $types .= 'i';
                            } elseif (is_double($param)) {
                                $types .= 'd';
                            } else {
                                $types .= 's';
                            }
                        }
                    }
                    $stmt->bind_param($types, ...$params);
                }

                $result = $stmt->execute();
                
                if (!$result) {
                    throw new Exception('Ошибка выполнения запроса №' . $index . ': ' . $stmt->error);
                }

                if (stripos($sql, 'SELECT') === 0) {
                    $has_select = true;
                    $result_set = $stmt->get_result();
                    $rows = [];
                    while ($row = $result_set->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    $results[$index] = $rows;
                } else {
                    $results[$index] = $stmt->affected_rows;
                }

                $stmt->close();
            }

            if ($auto_commit && !$is_nested) {
                $this->commit_transaction();
                GSC_Controller_Log::success('Транзакция успешно завершена', [
                    'query_count' => count($queries),
                    'has_select' => $has_select
                ]);
            } else {
                GSC_Controller_Log::db('Транзакция выполнена, ожидает коммита', [
                    'query_count' => count($queries),
                    'auto_commit' => $auto_commit,
                    'is_nested' => $is_nested
                ]);
            }

            if ($has_select) {
                return $results;
            }

            return true;

        } catch (Exception $e) {
            if (!$is_nested) {
                $this->rollback_transaction();
            }
            
            $error_msg = 'Ошибка выполнения транзакции: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg, [
                'query_count' => count($queries),
                'failed_index' => $index ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function begin_transaction()
    {
        if (!$this->remote_conn) {
            $this->errors[] = 'Нет подключения к БД';
            GSC_Controller_Log::error('Нет подключения к БД для начала транзакции');
            return false;
        }

        try {
            if ($this->in_transaction) {
                $this->transaction_level++;
                $savepoint_name = 'sp_' . $this->transaction_level;
                $this->remote_conn->query("SAVEPOINT {$savepoint_name}");
                GSC_Controller_Log::db('Создан SAVEPOINT для вложенной транзакции: ' . $savepoint_name, [
                    'level' => $this->transaction_level
                ]);
            } else {
                $this->remote_conn->begin_transaction();
                $this->in_transaction = true;
                $this->transaction_level = 1;
                GSC_Controller_Log::db('Транзакция начата', [
                    'isolation_level' => $this->get_transaction_isolation()
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            $error_msg = 'Ошибка начала транзакции: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg);
            return false;
        }
    }

    public function commit_transaction()
    {
        if (!$this->remote_conn || !$this->in_transaction) {
            GSC_Controller_Log::db('Нет активной транзакции для коммита');
            return false;
        }

        try {
            if ($this->transaction_level > 1) {
                $this->transaction_level--;
                GSC_Controller_Log::db('Вложенная транзакция завершена (уровень: ' . $this->transaction_level . ')');
                return true;
            }

            $this->remote_conn->commit();
            $this->in_transaction = false;
            $this->transaction_level = 0;
            
            GSC_Controller_Log::success('Транзакция успешно закоммичена');
            return true;
        } catch (Exception $e) {
            $error_msg = 'Ошибка коммита транзакции: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg);

            $this->rollback_transaction();
            return false;
        }
    }

    public function rollback_transaction()
    {
        if (!$this->remote_conn || !$this->in_transaction) {
            GSC_Controller_Log::db('Нет активной транзакции для отката');
            return false;
        }

        try {
            if ($this->transaction_level > 1) {
                $savepoint_name = 'sp_' . $this->transaction_level;
                $this->remote_conn->query("ROLLBACK TO SAVEPOINT {$savepoint_name}");
                $this->transaction_level--;
                GSC_Controller_Log::db('Вложенная транзакция откачена до SAVEPOINT: ' . $savepoint_name, [
                    'new_level' => $this->transaction_level
                ]);
                return true;
            }

            $this->remote_conn->rollback();
            $this->in_transaction = false;
            $this->transaction_level = 0;
            
            GSC_Controller_Log::db('Транзакция откачена (rollback)');
            return true;
        } catch (Exception $e) {
            $error_msg = 'Ошибка отката транзакции: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg);
            return false;
        }
    }

    public function get_transaction_isolation()
    {
        if (!$this->remote_conn) {
            return false;
        }

        try {
            $result = $this->remote_conn->query("SELECT @@transaction_isolation as isolation");
            if ($result) {
                $row = $result->fetch_assoc();
                $result->free();
                return $row['isolation'] ?? 'REPEATABLE READ';
            }
            return 'REPEATABLE READ';
        } catch (Exception $e) {
            GSC_Controller_Log::db('Ошибка получения уровня изоляции: ' . $e->getMessage());
            return 'REPEATABLE READ';
        }
    }

    public function set_transaction_isolation($isolation = 'REPEATABLE READ')
    {
        if (!$this->remote_conn) {
            return false;
        }

        $allowed = ['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'];
        if (!in_array($isolation, $allowed)) {
            $isolation = 'REPEATABLE READ';
        }

        try {
            $this->remote_conn->query("SET TRANSACTION ISOLATION LEVEL {$isolation}");
            GSC_Controller_Log::db('Установлен уровень изоляции транзакций: ' . $isolation);
            return true;
        } catch (Exception $e) {
            GSC_Controller_Log::db('Ошибка установки уровня изоляции: ' . $e->getMessage());
            return false;
        }
    }

    public function is_in_transaction()
    {
        return $this->in_transaction;
    }

    public function purchase_item($user_id, $item_id, $quantity = 1)
    {
        try {
            $this->begin_transaction();
 
            $item = $this->execute_query(
                "SELECT price, max_stack FROM items WHERE id = ?",
                [$item_id],
                'i'
            );
            
            if (empty($item)) {
                throw new Exception('Предмет не найден');
            }

            $user_balance = $this->execute_query(
                "SELECT balance FROM users WHERE id = ?",
                [$user_id],
                'i'
            );
            
            $total_price = $item[0]['price'] * $quantity;
            if ($user_balance[0]['balance'] < $total_price) {
                throw new Exception('Недостаточно средств');
            }

            $this->execute_query(
                "UPDATE users SET balance = balance - ? WHERE id = ?",
                [$total_price, $user_id],
                'di'
            );

            $this->execute_query(
                "INSERT INTO user_items (user_id, item_id, quantity) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)",
                [$user_id, $item_id, $quantity],
                'iii'
            );

            $this->commit_transaction();
            
            GSC_Controller_Log::success('Покупка предмета успешно завершена', [
                'user_id' => $user_id,
                'item_id' => $item_id,
                'quantity' => $quantity,
                'total_price' => $total_price
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback_transaction();
            GSC_Controller_Log::error('Ошибка покупки предмета: ' . $e->getMessage());
            return false;
        }
    }

    public function get_user_by_id($user_id, $table = null, $id_field = 'id')
    {
        if (!$this->remote_conn) {
            $this->errors[] = 'Нет подключения к БД';
            return false;
        }

        if (!$table) {
            $sync_settings = get_option('gsc_sync_settings', []);
            $table = $sync_settings['server_user_table'] ?? get_option('gsc_db_table', '');
        }

        if (empty($table)) {
            $this->errors[] = 'Не указана таблица пользователей';
            return false;
        }

        try {
            $safe_table = $this->escape_identifier($table);
            $safe_id_field = $this->escape_identifier($id_field);
            
            $sql = "SELECT * FROM {$safe_table} WHERE {$safe_id_field} = ?";
            $result = $this->execute_query($sql, [$user_id], 'i');
            
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
            
            return false;
        } catch (Exception $e) {
            $error_msg = 'Ошибка получения пользователя: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            return false;
        }
    }

    public function update_user_balance($user_id, $amount, $table = null, $balance_field = 'balance', $id_field = 'id')
    {
        if (!$this->remote_conn) {
            $this->errors[] = 'Нет подключения к БД';
            return false;
        }

        if (!$table) {
            $sync_settings = get_option('gsc_sync_settings', []);
            $table = $sync_settings['server_user_table'] ?? get_option('gsc_db_table', '');
        }

        if (empty($table)) {
            $this->errors[] = 'Не указана таблица пользователей';
            return false;
        }

        try {
            $safe_table = $this->escape_identifier($table);
            $safe_balance_field = $this->escape_identifier($balance_field);
            $safe_id_field = $this->escape_identifier($id_field);
            
            $sql = "UPDATE {$safe_table} SET {$safe_balance_field} = {$safe_balance_field} + ? WHERE {$safe_id_field} = ?";
            $result = $this->execute_query($sql, [$amount, $user_id], 'di');
            
            if ($result !== false) {
                GSC_Controller_Log::success('Баланс пользователя ID ' . $user_id . ' обновлен на ' . $amount);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            $error_msg = 'Ошибка обновления баланса: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            return false;
        }
    }

    public function close_connection()
    {
        if ($this->remote_conn instanceof mysqli) {
            if ($this->in_transaction) {
                $this->rollback_transaction();
                GSC_Controller_Log::db('Активная транзакция откачена при закрытии соединения');
            }
            
            $this->remote_conn->close();
            $this->remote_conn = null;
            $this->in_transaction = false;
            $this->transaction_level = 0;
            GSC_Controller_Log::db('Подключение к БД закрыто');
        }
    }

    public function __destruct()
    {
        $this->close_connection();
    }

    private function escape_identifier($identifier)
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function escape_value($value)
    {
        if (!$this->remote_conn) {
            return $value;
        }
        return $this->remote_conn->real_escape_string($value);
    }

    private function validate_registration_data($username, $password, $email)
    {
        $errors = [];

        if (empty($username) || strlen($username) < 3 || strlen($username) > 32) {
            $errors[] = 'Имя пользователя должно быть от 3 до 32 символов';
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
            $errors[] = 'Имя пользователя может содержать только буквы, цифры, точки, дефисы и подчеркивания';
        }

        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать минимум 6 символов';
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email адрес';
        }

        if (!empty($errors)) {
            $this->errors = array_merge($this->errors, $errors);
            GSC_Controller_Log::db('Ошибки валидации данных регистрации', $errors);
            return false;
        }

        GSC_Controller_Log::db('Данные регистрации прошли валидацию');
        return true;
    }

    private function user_exists($username, $email = '', $table = null, $field_username = null, $field_email = null)
    {
        if (!$this->remote_conn) {
            GSC_Controller_Log::db('Нет подключения к БД при проверке пользователя');
            return false;
        }

        if (!$table) {
            $sync_settings = get_option('gsc_sync_settings', []);
            $table = $sync_settings['server_user_table'] ?? get_option('gsc_db_table', '');
            $field_username = $sync_settings['server_user_login'] ?? get_option('gsc_db_field_username', 'login');
            $field_email = $sync_settings['server_user_email'] ?? get_option('gsc_db_field_email', 'email');
        }

        if (empty($table) || empty($field_username)) {
            GSC_Controller_Log::db('Не настроена таблица или поле для проверки пользователя');
            return false;
        }

        try {
            $safe_table = $this->escape_identifier($table);
            $safe_field_username = $this->escape_identifier($field_username);

            $sql = "SELECT COUNT(*) as count FROM {$safe_table} WHERE {$safe_field_username} = ?";
            $params = [$username];
            $types = "s";

            if (!empty($field_email) && !empty($email)) {
                $safe_field_email = $this->escape_identifier($field_email);
                $sql .= " OR {$safe_field_email} = ?";
                $params[] = $email;
                $types .= "s";
            }

            GSC_Controller_Log::db('Проверка существования пользователя SQL', [
                'sql' => $sql,
                'params' => $params
            ]);

            $stmt = $this->remote_conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса: ' . $this->remote_conn->error);
            }

            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $exists = $row['count'] > 0;
            GSC_Controller_Log::db('Пользователь ' . $username . ($exists ? ' существует' : ' не существует'));

            return $exists;

        } catch (Exception $e) {
            $error_msg = 'Ошибка проверки пользователя: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg);
            return false;
        }
    }

    private function hash_password($password)
    {
        $hash_type = get_option('gsc_password_hash_type', 'bcrypt');
        $salt = get_option('gsc_password_salt', '');

        GSC_Controller_Log::db('Хэширование пароля (тип: ' . $hash_type . ')');

        switch ($hash_type) {
            case 'md5':
                return md5($password . $salt);
            case 'sha1':
                return sha1($password . $salt);
            case 'sha256':
                return hash('sha256', $password . $salt);
            case 'bcrypt':
                return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            case 'argon2i':
                return password_hash($password, PASSWORD_ARGON2I);
            case 'custom':
                $custom_func = get_option('gsc_custom_hash_function');
                if (function_exists($custom_func)) {
                    return call_user_func($custom_func, $password, $salt);
                }
                return password_hash($password, PASSWORD_BCRYPT);
            default:
                return password_hash($password, PASSWORD_BCRYPT);
        }
    }
}
?>
