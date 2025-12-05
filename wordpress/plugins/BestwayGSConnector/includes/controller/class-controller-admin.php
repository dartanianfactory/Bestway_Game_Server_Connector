<?php
class GSC_Controller_Admin extends GSC_Controller_Base
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function admin_menu()
    {
        add_menu_page(
            'GSConnector',
            'GSConnector',
            'manage_options',
            'gsc-settings',
            [$this, 'settings_page'],
            'dashicons-games',
            30
        );

        add_submenu_page(
            'gsc-settings',
            'Настройки',
            'Настройки',
            'manage_options',
            'gsc-settings',
            [$this, 'settings_page']
        );

        add_submenu_page(
            'gsc-settings',
            'Предметы магазина',
            'Предметы магазина',
            'manage_options',
            'gsc-donate-items',
            [$this, 'donate_items_page']
        );

        add_submenu_page(
            'gsc-settings',
            'Логи платежей',
            'Логи платежей',
            'manage_options',
            'gsc-payment-logs',
            [$this, 'payment_logs_page']
        );

        add_submenu_page(
            'gsc-settings',
            'Логи плагина',
            'Логи плагина',
            'manage_options',
            'gsc-logs',
            [$this, 'logs_page']
        );

    }

    public function register_settings()
    {
        register_setting('gsc_settings_db', 'gsc_db_enabled');
        register_setting('gsc_settings_db', 'gsc_db_host');
        register_setting('gsc_settings_db', 'gsc_db_name');
        register_setting('gsc_settings_db', 'gsc_db_user');
        register_setting('gsc_settings_db', 'gsc_db_password');
        register_setting('gsc_settings_db', 'gsc_db_port');
        register_setting('gsc_settings_db', 'gsc_db_charset');

        register_setting('gsc_settings_db', 'gsc_db_table');
        register_setting('gsc_settings_db', 'gsc_db_field_username');
        register_setting('gsc_settings_db', 'gsc_db_field_password');
        register_setting('gsc_settings_db', 'gsc_db_field_email');

        register_setting('gsc_settings_db', 'gsc_password_hash_type');
        register_setting('gsc_settings_db', 'gsc_password_salt');
        register_setting('gsc_settings_db', 'gsc_custom_hash_function');

        register_setting('gsc_settings_sync', 'gsc_sync_settings', [$this, 'sanitize_sync_settings']);

        register_setting('gsc_settings_donate', 'gsc_donate_enabled');

        register_setting('gsc_settings_payments', 'gsc_payments_enabled');
        register_setting('gsc_settings_payments', 'gsc_payments_offline_text');
        register_setting('gsc_settings_payments', 'gsc_payment_system');
        register_setting('gsc_settings_payments', 'gsc_payment_shop_id');
        register_setting('gsc_settings_payments', 'gsc_payment_secret_key');
        register_setting('gsc_settings_payments', 'gsc_payment_public_key');
        register_setting('gsc_settings_payments', 'gsc_payment_webhook_url');
        register_setting('gsc_settings_payments', 'gsc_payment_success_url');
        register_setting('gsc_settings_payments', 'gsc_payment_fail_url');

        add_filter('sanitize_option_gsc_db_port', 'absint');
        add_filter('sanitize_option_gsc_password_salt', 'sanitize_text_field');

        add_action('wp_ajax_gsc_clear_old_logs', [$this, 'clear_old_logs']);
        add_action('wp_ajax_gsc_get_payment_stats', [$this, 'get_payment_stats']);
        add_action('wp_ajax_gsc_clear_old_payments', [$this, 'clear_old_payments']);
        add_action('wp_ajax_gsc_export_payments', [$this, 'export_payments']);
    }

    public function get_payment_stats()
    {
        $nonce = $_POST['nonce'] ?? '';
        if (!check_ajax_referer('gsc_admin_nonce', 'nonce', false)) {
            $this->log('Security check failed in get_payment_stats', 'error');
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            $this->log('Unauthorized access to get_payment_stats', 'error');
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;

        $stats = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'completed' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'completed')),
            'pending' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'pending')),
            'failed' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'failed')),
            'refunded' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'refunded')),
            'total_amount' => $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$table} WHERE status = %s", 'completed'))
        ];

        $this->log('Получение статистики платежей', 'info', $stats);
        wp_send_json_success($stats);
    }

    public function clear_old_payments()
    {
        $nonce = $_POST['nonce'] ?? '';
        if (!check_ajax_referer('gsc_admin_nonce', 'nonce', false)) {
            $this->log('Security check failed in clear_old_payments', 'error');
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            $this->log('Unauthorized access to clear_old_payments', 'error');
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;
        $cutoff_date = date('Y-m-d', strtotime('-90 days'));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s AND status IN (%s, %s)",
            $cutoff_date,
            'completed',
            'failed'
        ));

        $this->log('Удаление старых платежей', 'info', ['deleted' => $deleted]);
        wp_send_json_success(['deleted' => $deleted]);
    }

    public function export_payments()
    {
        $nonce = $_POST['nonce'] ?? '';
        if (!check_ajax_referer('gsc_admin_nonce', 'nonce', false)) {
            $this->log('Security check failed in export_payments', 'error');
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            $this->log('Unauthorized access to export_payments', 'error');
            wp_send_json_error('Unauthorized');
        }

        $date_from = $this->get_request_param('date_from');
        $date_to = $this->get_request_param('date_to');
        $status = $this->get_request_param('status');

        global $wpdb;
        $table = $wpdb->prefix . GSC_TABLE_PAYMENT_LOGS;

        $where = [];
        $params = [];

        if ($date_from) {
            $where[] = "created_at >= %s";
            $params[] = $date_from;
        }

        if ($date_to) {
            $where[] = "created_at <= %s";
            $params[] = $date_to;
        }

        if ($status && $status !== 'all') {
            $where[] = "status = %s";
            $params[] = $status;
        }

        $query = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        $query .= " ORDER BY created_at DESC LIMIT 1000";

        if (!empty($params)) {
            $payments = $wpdb->get_results($wpdb->prepare($query, ...$params));
        } else {
            $payments = $wpdb->get_results($query);
        }

        $this->log('Экспорт платежей', 'info', ['count' => count($payments)]);
        wp_send_json_success($payments);
    }

    public function clear_old_logs()
    {
        $nonce = $_POST['nonce'] ?? '';
        if (!check_ajax_referer('gsc_admin_nonce', 'nonce', false)) {
            $this->log('Security check failed in clear_old_logs', 'error');
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            $this->log('Unauthorized access to clear_old_logs', 'error');
            wp_send_json_error('Unauthorized');
        }

        $log_controller = GSC_Controller_Log::instance();
        $deleted = $log_controller->clear_old_logs(30);

        $this->log('Очистка старых логов', 'info', ['deleted' => $deleted]);
        wp_send_json_success(['deleted' => $deleted]);
    }

    public function logs_page()
    {
        if (method_exists('GSC_View_Admin_Page_Logs', 'render')) {
            GSC_View_Admin_Page_Logs::render();
        } else {
            $this->render_logs_fallback();
        }
    }

    public function settings_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'db';

        $this->log("Открыта вкладка настроек: {$active_tab}", 'info');

        ?>
        <div class="wrap gsc-settings">
            <h1>Настройки GSConnector</h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=gsc-settings&tab=db'); ?>"
                    class="nav-tab <?php echo $active_tab === 'db' ? 'nav-tab-active' : ''; ?>">
                    Подключение к БД
                </a>
                <a href="<?php echo admin_url('admin.php?page=gsc-settings&tab=donate'); ?>"
                    class="nav-tab <?php echo $active_tab === 'donate' ? 'nav-tab-active' : ''; ?>">
                    Настройки магазина
                </a>
                <a href="<?php echo admin_url('admin.php?page=gsc-settings&tab=payments'); ?>"
                    class="nav-tab <?php echo $active_tab === 'payments' ? 'nav-tab-active' : ''; ?>">
                    Настройки платежей
                </a>
                <a href="<?php echo admin_url('admin.php?page=gsc-settings&tab=sync'); ?>"
                    class="nav-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>">
                    Синхронизация БД
                </a>
                <a href="<?php echo admin_url('admin.php?page=gsc-settings&tab=contacts'); ?>"
                    class="nav-tab <?php echo $active_tab === 'contacts' ? 'nav-tab-active' : ''; ?>">
                    Контакты и проекты
                </a>
            </nav>

            <div class="gsc-settings-content">
                <?php
                $view_classes = [
                    'donate' => 'GSC_View_Admin_Tab_Donate',
                    'payments' => 'GSC_View_Admin_Tab_Payments',
                    'sync' => 'GSC_View_Admin_Tab_Sync',
                    'contacts' => 'GSC_View_Admin_Tab_Contacts',
                    'db' => 'GSC_View_Admin_Tab_DB',
                ];

                $this->log("Попытка загрузить класс для вкладки {$active_tab}: " . ($view_classes[$active_tab] ?? 'не найден'), 'info');

                if (isset($view_classes[$active_tab])) {
                    $class_name = $view_classes[$active_tab];
                    $this->log("Класс: {$class_name}", 'info');

                    if (class_exists($class_name)) {
                        $this->log("Класс {$class_name} существует", 'info');
                        if (method_exists($class_name, 'render')) {
                            $this->log("Метод render существует в классе {$class_name}", 'info');
                            call_user_func([$class_name, 'render']);
                        } else {
                            $this->log("Метод render не существует в классе {$class_name}", 'error');
                            $this->render_settings_fallback($active_tab);
                        }
                    } else {
                        $this->log("Класс {$class_name} не существует", 'error');
                        $this->render_settings_fallback($active_tab);
                    }
                } else {
                    $this->log("Вкладка {$active_tab} не найдена в массиве view_classes", 'error');
                    $this->render_settings_fallback($active_tab);
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function donate_items_page()
    {
        $model = $this->get_model('Donate');
        if (!$model) {
            echo '<div class="notice notice-error"><p>Модель не найдена</p></div>';
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'add':
            case 'edit':
                $item = $item_id ? $model->get_item($item_id) : null;

                if ($action === 'edit' && !$item) {
                    echo '<div class="notice notice-error"><p>Предмет не найден</p></div>';
                    $action = 'list';
                } else {
                    if (method_exists('GSC_View_Admin_Page_Edit_Item', 'render')) {
                        GSC_View_Admin_Page_Edit_Item::render([
                            'item' => $item,
                            'action' => $action,
                            'item_id' => $item_id
                        ]);
                    } else {
                        $this->render_edit_item_fallback($item, $action, $item_id);
                    }
                    return;
                }
                break;
        }

        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

        $args = [
            'page' => $current_page,
            'per_page' => 20,
            'search' => $search,
            'status' => $status
        ];

        $result = $model->get_items($args);

        if (method_exists('GSC_View_Admin_Page_Donate_Items', 'render')) {
            GSC_View_Admin_Page_Donate_Items::render([
                'items' => $result['items'],
                'total_items' => $result['total_items'],
                'total_pages' => $result['total_pages'],
                'current_page' => $current_page,
                'search' => $search,
                'status' => $status
            ]);
        } else {
            $this->render_donate_items_fallback($result, $current_page, $search, $status);
        }
    }

    public function payment_logs_page()
    {
        $model = $this->get_model('Payment');
        if (!$model) {
            echo '<div class="notice notice-error"><p>Модель не найдена</p></div>';
            return;
        }

        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $payment_system = isset($_GET['payment_system']) ? sanitize_text_field($_GET['payment_system']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

        $args = [
            'page' => $current_page,
            'per_page' => 20,
            'search' => $search,
            'status' => $status,
            'payment_system' => $payment_system,
            'date_from' => $date_from,
            'date_to' => $date_to
        ];

        $result = $model->get_payments($args);

        if (method_exists('GSC_View_Admin_Page_Payment_Logs', 'render')) {
            GSC_View_Admin_Page_Payment_Logs::render([
                'logs' => $result['payments'],
                'total_items' => $result['total_items'],
                'total_pages' => $result['total_pages'],
                'current_page' => $current_page,
                'search' => $search,
                'status' => $status,
                'payment_system' => $payment_system,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]);
        } else {
            $this->render_payment_logs_fallback($result, $current_page);
        }
    }

    public function sanitize_sync_settings($input)
    {
        $sanitized = [];

        if (!is_array($input)) {
            return $sanitized;
        }

        $sanitized['site_user_table'] = sanitize_text_field($input['site_user_table'] ?? '');
        $sanitized['server_user_table'] = sanitize_text_field($input['server_user_table'] ?? '');

        $user_fields = [
            'user_id',
            'login',
            'password',
            'email',
            'balance',
            'register_date',
            'last_login',
            'ip_address',
            'status',
            'nickname',
            'level',
            'experience'
        ];

        foreach ($user_fields as $field) {
            $sanitized['site_user_' . $field] = sanitize_text_field($input['site_user_' . $field] ?? '');
            $sanitized['server_user_' . $field] = sanitize_text_field($input['server_user_' . $field] ?? '');
        }

        $sanitized['server_items_table'] = sanitize_text_field($input['server_items_table'] ?? '');

        $items_fields = [
            'item_id',
            'name',
            'description',
            'price',
            'type',
            'category',
            'level_required',
            'weight',
            'stackable',
            'durability',
            'icon',
            'rarity'
        ];

        foreach ($items_fields as $field) {
            $sanitized['site_items_' . $field] = sanitize_text_field($input['site_items_' . $field] ?? '');
            $sanitized['server_items_' . $field] = sanitize_text_field($input['server_items_' . $field] ?? '');
        }

        $sanitized['server_inventory_table'] = sanitize_text_field($input['server_inventory_table'] ?? '');

        $inventory_fields = [
            'user_id',
            'item_id',
            'quantity',
            'slot',
            'durability',
            'enchantment',
            'bound',
            'acquire_date',
            'expire_date'
        ];

        foreach ($inventory_fields as $field) {
            $sanitized['server_inventory_' . $field] = sanitize_text_field($input['server_inventory_' . $field] ?? '');
        }

        $sanitized['sync_users_on_registration'] = isset($input['sync_users_on_registration']) ? '1' : '0';
        $sanitized['sync_users_on_update'] = isset($input['sync_users_on_update']) ? '1' : '0';
        $sanitized['sync_items_on_purchase'] = isset($input['sync_items_on_purchase']) ? '1' : '0';
        $sanitized['create_missing_items'] = isset($input['create_missing_items']) ? '1' : '0';
        $sanitized['update_inventory_on_purchase'] = isset($input['update_inventory_on_purchase']) ? '1' : '0';
        $sanitized['check_duplicate_items'] = isset($input['check_duplicate_items']) ? '1' : '0';
        $sanitized['default_slot'] = isset($input['default_slot']) ? absint($input['default_slot']) : 0;

        return $sanitized;
    }

    private function render_payment_logs_fallback($result, $current_page)
    {
        ?>
        <div class="wrap">
            <h1>Логи платежей</h1>
            <p>Вьюшка логов платежей не найдена.</p>
            <p>Всего записей: <?php echo $result['total_items']; ?>, Страница: <?php echo $current_page; ?></p>
        </div>
        <?php
    }

    private function render_donate_items_fallback($result, $current_page, $search, $status)
    {
        ?>
        <div class="wrap">
            <h1>Предметы магазина</h1>
            <p>Вьюшка предметов магазина не найдена.</p>
            <p>Всего предметов: <?php echo $result['total_items']; ?></p>
        </div>
        <?php
    }

    private function render_edit_item_fallback($item, $action, $item_id)
    {
        ?>
        <div class="wrap">
            <h1><?php echo $action === 'edit' ? 'Редактирование предмета' : 'Добавление предмета'; ?></h1>
            <p>Вьюшка редактирования предмета не найдена.</p>
        </div>
        <?php
    }

    private function render_logs_fallback()
    {
        ?>
        <div class="wrap">
            <h1>Логи плагина</h1>
            <p>Вьюшка логов не найдена. Проверьте наличие файла вьюшки.</p>
        </div>
        <?php
    }

    private function render_settings_fallback($tab)
    {
        $this->log("Рендеринг fallback для вкладки {$tab}", 'warning');
        ?>
        <div class="notice notice-warning">
            <p>Вьюшка для вкладки "<?php echo $tab; ?>" не найдена. Отображаем базовую форму.</p>
        </div>

        <form method="post" action="options.php">
            <?php
            switch ($tab) {
                case 'db':
                    settings_fields('gsc_settings_db');
                    do_settings_sections('gsc_settings_db');
                    break;
                case 'donate':
                    settings_fields('gsc_settings_donate');
                    do_settings_sections('gsc_settings_donate');
                    break;
                case 'payments':
                    settings_fields('gsc_settings_payments');
                    do_settings_sections('gsc_settings_payments');
                    break;
                default:
                    echo '<p>Настройки для этой вкладки не найдены.</p>';
            }
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Включить подключение к БД</th>
                    <td>
                        <input type="checkbox" name="gsc_db_enabled" value="1" <?php checked('1', get_option('gsc_db_enabled')); ?> />
                        <p class="description">Включить подключение к игровой БД</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }
}
?>
