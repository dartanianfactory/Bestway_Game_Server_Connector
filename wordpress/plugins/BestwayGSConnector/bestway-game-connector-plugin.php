<?php
/**
 * Plugin Name: Bestway Game Connector
 * Plugin URI: https://github.com/dartanianfactory/Bestway_Game_Server_Connector
 * Description: Интеграция регистрации на сайте с игровым сервером и система магазина
 * Version: 0.1.1
 * Author: Roman Agafonov
 * License: GPL v2 or later
 */

if (!defined('ABSPATH'))
    exit;

define('GSC_VERSION', '0.1.1');
define('GSC_PATH', plugin_dir_path(__FILE__));
define('GSC_URL', plugin_dir_url(__FILE__));
define('GSC_TABLE_DONATE_ITEMS', 'gsc_donate_items');
define('GSC_TABLE_PAYMENT_LOGS', 'gsc_payment_logs');

// Временный дебаг
error_log('GSC Plugin: Plugin loaded - ' . date('Y-m-d H:i:s'));
error_log('GSC Plugin: ABSPATH = ' . ABSPATH);
error_log('GSC Plugin: GSC_PATH = ' . GSC_PATH);

require_once GSC_PATH . 'autoloader.php';
$autoloader_initialized = false;

try {
    if (class_exists('GSC_Autoloader')) {
        GSC_Autoloader::init();
        $autoloader_initialized = true;

        if (class_exists('GSC_Controller_Log')) {
            GSC_Controller_Log::info('Автозагрузчик инициализирован');
        }
    }

    add_action('plugins_loaded', function () {
        $required_classes = [
            'GSC_Controller_Log',
            'GSC_Controller_Assets',
            'GSC_Controller_Admin',
            'GSC_Controller_AJAX'
        ];

        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                error_log("GSC Plugin Error: Required class {$class} not found");
            }
        }
    }, 1);
} catch (Exception $e) {
    error_log('GSC Plugin Error: Failed to initialize autoloader - ' . $e->getMessage());
}

class Game_Server_Connector
{
    private static $instance = null;
    private $controllers = [];
    private $initialized = false;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init_plugin'], 5);
    }

    public function init_plugin()
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
        
        $logs_dir = GSC_PATH . 'logs/';
        if (!file_exists($logs_dir)) {
            if (!wp_mkdir_p($logs_dir)) {
                error_log('GSC: Failed to create log directory: ' . $logs_dir);
            } else {
                @chmod($logs_dir, 0755);
                $htaccess_content = "Order Deny,Allow\nDeny from all";
                $index_content = "<?php\n// Silence is golden";

                @file_put_contents($logs_dir . '.htaccess', $htaccess_content);
                @file_put_contents($logs_dir . 'index.php', $index_content);
                
                error_log('GSC: Log directory created: ' . $logs_dir);
            }
        }

        if (!class_exists('GSC_Controller_Log')) {
            $this->log_error('Autoloader failed to load GSC_Controller_Log');
            return;
        }

        GSC_Controller_Log::info('Инициализация плагина GSConnector');

        try {
            $this->load_components();
            GSC_Controller_Log::success('Плагин GSConnector успешно инициализирован');
        } catch (Exception $e) {
            $this->log_error('Ошибка инициализации плагина: ' . $e->getMessage());
        }
    }

    public function load_components()
    {
        GSC_Controller_Log::info('Загрузка компонентов плагина');

        try {
            $this->controllers['log'] = GSC_Controller_Log::instance();
            GSC_Controller_Log::info('Контроллер логов загружен');

            if (class_exists('GSC_Controller_Assets')) {
                $this->controllers['assets'] = GSC_Controller_Assets::instance();
                GSC_Controller_Log::info('Контроллер ассетов загружен');
            } else {
                $this->log_error('Класс GSC_Controller_Assets не найден');
            }

            if (class_exists('GSC_Controller_Admin')) {
                $this->controllers['admin'] = GSC_Controller_Admin::instance();
                GSC_Controller_Log::info('Контроллер админки загружен');
            }

            if (class_exists('GSC_Controller_AJAX')) {
                $this->controllers['ajax'] = new GSC_Controller_AJAX();
                GSC_Controller_Log::info('Контроллер AJAX загружен');
            } else {
                $this->log_error('Класс GSC_Controller_AJAX не найден');
            }

            if (get_option('gsc_db_enabled') === '1' && class_exists('GSC_Controller_Registration')) {
                $this->controllers['registration'] = new GSC_Controller_Registration();
                GSC_Controller_Log::info('Контроллер регистрации загружен');
            }

            if (get_option('gsc_donate_enabled') === '1' && class_exists('GSC_Controller_Donate')) {
                $this->controllers['donate'] = new GSC_Controller_Donate();
                GSC_Controller_Log::info('Контроллер магазина загружен');
            }

            if (class_exists('GSC_Controller_Payment')) {
                $this->controllers['payment'] = new GSC_Controller_Payment();
                GSC_Controller_Log::info('Контроллер платежей загружен');
            }

            $this->register_shortcodes();
            GSC_Controller_Log::info('Шорткоды зарегистрированы');

        } catch (Exception $e) {
            $this->log_error('Ошибка загрузки компонентов: ' . $e->getMessage());
            throw $e;
        }
    }

    private function log_error($message)
    {
        if (class_exists('GSC_Controller_Log')) {
            GSC_Controller_Log::error($message);
        } else {
            error_log('GSC Plugin Error: ' . $message);
        }
    }

    private function register_shortcodes()
    {
        add_shortcode('gsc_donate_shop', [$this, 'donate_shop_shortcode']);
        add_shortcode('gsc_register_form', [$this, 'register_form_shortcode']);
    }

    public function donate_shop_shortcode($atts)
    {
        if (isset($this->controllers['donate'])) {
            return $this->controllers['donate']->handle_shortcode($atts);
        }
        return '';
    }

    public function register_form_shortcode($atts)
    {
        if (isset($this->controllers['registration'])) {
            return $this->controllers['registration']->handle_shortcode($atts);
        }
        return '';
    }

    public function activate()
    {
        $this->log_activation('Начало активации плагина');

        try {
            $logs_dir = GSC_PATH . 'logs/';
            if (!file_exists($logs_dir)) {
                wp_mkdir_p($logs_dir);

                @chmod($logs_dir, 0777);

                $htaccess_content = "Order Deny,Allow\nDeny from all";
                $index_content = "<?php\n// Silence is golden";

                @file_put_contents($logs_dir . '.htaccess', $htaccess_content);
                @file_put_contents($logs_dir . 'index.php', $index_content);

                if (function_exists('posix_getuid')) {
                    $current_uid = posix_getuid();
                    $dir_uid = fileowner($logs_dir);

                    if ($current_uid === 0)
                        @chown($logs_dir, get_current_user());
                }
            }

            $this->create_tables();
            $this->setup_default_options();

            if (class_exists('GSC_Controller_Log')) {
                GSC_Controller_Log::success('Плагин активирован');
            } else {
                $this->log_activation('Плагин активирован (логгер еще не доступен)');
            }
        } catch (Exception $e) {
            $this->log_activation('Ошибка активации: ' . $e->getMessage());
        }
    }


    private function log_activation($message)
    {
        error_log('GSC Activation: ' . $message);
    }

    private function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Таблица предметов магазина
        $table_name = $wpdb->prefix . GSC_TABLE_DONATE_ITEMS;
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id INT NOT NULL AUTO_INCREMENT,
            game_id VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            image_url VARCHAR(500),
            price DECIMAL(10,2) NOT NULL,
            sale_price DECIMAL(10,2),
            start_sale_at DATETIME,
            end_sale_at DATETIME,
            status VARCHAR(20) DEFAULT 'active',
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY game_id (game_id),
            KEY status (status),
            KEY sort_order (sort_order)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Таблица логов платежей
        $table_name = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            item_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_system VARCHAR(50),
            transaction_id VARCHAR(255),
            status VARCHAR(20) DEFAULT 'pending',
            payment_data LONGTEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY item_id (item_id),
            KEY status (status),
            KEY payment_system (payment_system),
            KEY transaction_id (transaction_id(100)),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);

        $this->log_activation('Таблицы созданы успешно');
    }

    private function setup_default_options()
    {
        $defaults = [
            'gsc_db_enabled' => '0',
            'gsc_db_port' => '3306',
            'gsc_db_charset' => 'utf8mb4',
            'gsc_password_hash_type' => 'bcrypt',
            'gsc_donate_enabled' => '1',
            'gsc_payments_enabled' => '0',
            'gsc_payment_success_url' => home_url('/donate-success/'),
            'gsc_payment_fail_url' => home_url('/donate-fail/'),
            'gsc_payment_webhook_url' => home_url('/gsc-payment-webhook/')
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }

        $this->log_activation('Настройки по умолчанию установлены');
    }

    public function deactivate()
    {
        if (class_exists('GSC_Controller_Log')) {
            GSC_Controller_Log::info('Плагин деактивирован');
        }
    }
}

Game_Server_Connector::instance();

function gsc_view_start($class_name)
{
    $assets_controller = GSC_Controller_Assets::instance();
    $view_key = $assets_controller->get_view_key_from_class($class_name);
    do_action('gsc_view_render_start', $view_key);
}

function gsc_view_end()
{
    do_action('gsc_view_render_end');
}
?>
