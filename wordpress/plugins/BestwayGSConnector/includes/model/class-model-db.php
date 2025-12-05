<?php
class GSC_Model_DB
{
    public $remote_conn = null;
    private $errors = [];

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

        // Проверяем mysqli вместо PDO
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
            $this->remote_conn = new mysqli($host, $user, $password, $name, $port);

            if ($this->remote_conn->connect_error) {
                throw new Exception($this->remote_conn->connect_error, $this->remote_conn->connect_errno);
            }

            if (!$this->remote_conn->set_charset($charset)) {
                GSC_Controller_Log::db('Не удалось установить кодировку: ' . $charset);
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
            $table = $this->escape_identifier($table);
            $field_username = $this->escape_identifier($field_username);
            $field_password = $this->escape_identifier($field_password);

            $sql = "INSERT INTO {$table} ({$field_username}, {$field_password}";
            $values = "VALUES (:username, :password";

            $params = [
                ':username' => $username,
                ':password' => $hashed_password
            ];

            if (!empty($field_email) && !empty($email)) {
                $field_email = $this->escape_identifier($field_email);
                $sql .= ", {$field_email}";
                $values .= ", :email";
                $params[':email'] = $email;
            }

            $sql .= ") " . $values . ")";

            GSC_Controller_Log::db('Выполнение SQL для создания аккаунта', [
                'sql' => $sql,
                'params' => $params
            ]);

            $stmt = $this->remote_conn->prepare($sql);
            $result = $stmt->execute($params);

            if ($result) {
                $account_id = $this->remote_conn->lastInsertId();
                GSC_Controller_Log::success('Создан игровой аккаунт: ' . $username . ' (ID: ' . $account_id . ')', [
                    'user_id' => $account_id,
                    'email' => $email
                ]);
                return $account_id;
            }

            GSC_Controller_Log::error('Не удалось создать игровой аккаунт: ' . $username);
            return false;

        } catch (PDOException $e) {
            $error_msg = 'Ошибка создания аккаунта: ' . $e->getMessage();
            $this->errors[] = $error_msg;
            GSC_Controller_Log::error($error_msg, [
                'username' => $username,
                'email' => $email
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
            $table = $this->escape_identifier($table);
            $stmt = $this->remote_conn->query("DESCRIBE {$table}");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = [];
            foreach ($results as $column) {
                $columns[] = $column['Field'];
            }

            GSC_Controller_Log::db('Получены колонки таблицы: ' . $table . ' (' . count($columns) . ' колонок)');
            return $columns;
        } catch (PDOException $e) {
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

    private function escape_identifier($identifier)
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
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
            $table = $this->escape_identifier($table);
            $field_username = $this->escape_identifier($field_username);

            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$field_username} = :username";
            $params = [':username' => $username];

            if (!empty($field_email) && !empty($email)) {
                $field_email = $this->escape_identifier($field_email);
                $sql .= " OR {$field_email} = :email";
                $params[':email'] = $email;
            }

            GSC_Controller_Log::db('Проверка существования пользователя SQL', [
                'sql' => $sql,
                'params' => $params
            ]);

            $stmt = $this->remote_conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();

            $exists = $result['count'] > 0;
            GSC_Controller_Log::db('Пользователь ' . $username . ($exists ? ' существует' : ' не существует'));

            return $exists;

        } catch (PDOException $e) {
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