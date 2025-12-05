<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Tab_Sync {
    
    public static function render() {
        gsc_view_start(__CLASS__);
        
        $sync_settings = get_option('gsc_sync_settings', []);
        ?>
        <div class="wrap">
            <div class="gsc-sync-wrapper">
                <h2>Синхронизация баз данных</h2>
                <p class="description">Настройте связь между таблицами и полями сайта и игрового сервера</p>
                
                <div class="gsc-tabs-container">
                    <nav class="nav-tab-wrapper gsc-sync-tabs">
                        <button type="button" class="nav-tab nav-tab-active" data-tab="users-tab">Пользователи</button>
                        <button type="button" class="nav-tab" data-tab="items-tab">Предметы</button>
                        <button type="button" class="nav-tab" data-tab="inventory-tab">Инвентарь</button>
                    </nav>
                    
                    <form method="post" action="options.php" id="gsc-sync-form" class="gsc-sync-form">
                        <?php settings_fields('gsc_settings_sync'); ?>
                        
                        <!-- Вкладка пользователей -->
                        <div id="users-tab" class="gsc-tab-content active">
                            <?php self::render_users_tab($sync_settings); ?>
                        </div>
                        
                        <!-- Вкладка предметов -->
                        <div id="items-tab" class="gsc-tab-content">
                            <?php self::render_items_tab($sync_settings); ?>
                        </div>
                        
                        <!-- Вкладка инвентаря -->
                        <div id="inventory-tab" class="gsc-tab-content">
                            <?php self::render_inventory_tab($sync_settings); ?>
                        </div>
                        
                        <div class="gsc-form-actions">
                            <?php submit_button('Сохранить все настройки синхронизации', 'primary', 'submit', false); ?>
                            <button type="button" id="test-sync-connection" class="button button-secondary">
                                Проверить синхронизацию
                            </button>
                            <span id="test-sync-result" class="test-result"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        gsc_view_end();
    }
    
    private static function render_users_tab($sync_settings) {
        global $wpdb;
        ?>
        <div class="gsc-tab-section">
            <h3>Синхронизация пользователей</h3>
            <p class="description">Сопоставьте таблицы и поля для синхронизации данных пользователей</p>
            
            <div class="gsc-columns-grid">
                <!-- Сайт -->
                <div class="gsc-column gsc-site-column">
                    <div class="gsc-column-header">
                        <h4><span class="dashicons dashicons-admin-site"></span> База данных сайта</h4>
                    </div>
                    
                    <div class="gsc-form-group">
                        <label for="site_user_table">Таблица пользователей на сайте:</label>
                        <select id="site_user_table" name="gsc_sync_settings[site_user_table]" 
                                class="regular-text" data-type="site" data-target="user">
                            <option value="">-- Выберите таблицу --</option>
                            <?php
                            $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
                            $site_tables = [];
                            foreach ($tables as $table) {
                                $table_name = $table[0];
                                if (strpos($table_name, $wpdb->prefix) === 0) {
                                    $site_tables[] = $table_name;
                                }
                            }
                            
                            foreach ($site_tables as $table_name) {
                                $selected = isset($sync_settings['site_user_table']) && $sync_settings['site_user_table'] === $table_name ? 'selected' : '';
                                echo '<option value="' . esc_attr($table_name) . '" ' . $selected . '>' . esc_html($table_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div id="site_user_columns" class="gsc-columns-container">
                        <?php 
                        if (isset($sync_settings['site_user_table']) && !empty($sync_settings['site_user_table'])) {
                            echo self::render_column_fields('site_user', $sync_settings['site_user_table'], $sync_settings);
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Сервер -->
                <div class="gsc-column gsc-server-column">
                    <div class="gsc-column-header">
                        <h4><span class="dashicons dashicons-database"></span> База данных игрового сервера</h4>
                    </div>
                    
                    <div class="gsc-form-group">
                        <label for="server_user_table">Таблица пользователей на сервере:</label>
                        <select id="server_user_table" name="gsc_sync_settings[server_user_table]" 
                                class="regular-text" data-type="server" data-target="user">
                            <option value="">-- Выберите таблицу --</option>
                            <?php
                            if (isset($sync_settings['server_user_table'])) {
                                echo '<option value="' . esc_attr($sync_settings['server_user_table']) . '" selected>' . esc_html($sync_settings['server_user_table']) . '</option>';
                            }
                            ?>
                        </select>
                        <?php if (get_option('gsc_db_enabled') !== '1'): ?>
                            <p class="gsc-warning"><span class="dashicons dashicons-warning"></span> Подключение к БД сервера отключено</p>
                        <?php endif; ?>
                    </div>
                    
                    <div id="server_user_columns" class="gsc-columns-container">
                        <?php 
                        if (isset($sync_settings['server_user_table']) && !empty($sync_settings['server_user_table'])) {
                            echo self::render_column_fields('server_user', $sync_settings['server_user_table'], $sync_settings);
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Сопоставление полей -->
            <div class="gsc-fields-mapping">
                <h4>Связь полей пользователя</h4>
                <div class="gsc-fields-table-wrapper">
                    <table class="wp-list-table widefat fixed striped gsc-fields-table">
                        <thead>
                            <tr>
                                <th width="30%">Тип данных</th>
                                <th width="35%">Поле на сайте</th>
                                <th width="35%">Поле на сервере</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php self::render_user_fields_mapping($sync_settings); ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="gsc-sync-options">
                    <label class="gsc-checkbox">
                        <input type="checkbox" name="gsc_sync_settings[sync_users_on_registration]" value="1" 
                               <?php checked(isset($sync_settings['sync_users_on_registration']) && $sync_settings['sync_users_on_registration'] == '1'); ?>>
                        <span>Синхронизировать пользователей при регистрации</span>
                    </label>
                    
                    <label class="gsc-checkbox">
                        <input type="checkbox" name="gsc_sync_settings[sync_users_on_update]" value="1" 
                               <?php checked(isset($sync_settings['sync_users_on_update']) && $sync_settings['sync_users_on_update'] == '1'); ?>>
                        <span>Синхронизировать при обновлении данных пользователя</span>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }
    
    private static function render_column_fields($type, $table, $settings) {
        if (empty($table)) {
            return '<div class="gsc-columns-list" id="' . $type . '_columns_list">
                        <p class="description">Выберите таблицу для отображения колонок</p>
                    </div>';
        }
        
        $html = '<div class="gsc-columns-list" id="' . $type . '_columns_list">';
        
        if (strpos($type, 'site_') === 0) {
            global $wpdb;
            
            try {
                $columns = $wpdb->get_col("DESCRIBE `{$table}`");
                
                if (empty($columns)) {
                    $html .= '<p class="notice notice-warning">В таблице нет колонок</p>';
                } else {
                    $html .= '<p><strong>Колонки в таблице ' . esc_html($table) . ':</strong></p>';
                    $html .= '<div style="max-height: 200px; overflow-y: auto;">';
                    
                    foreach ($columns as $column) {
                        $html .= '<div class="column-item">' . esc_html($column) . '</div>';
                    }
                    
                    $html .= '</div>';
                    $html .= '<p class="description">Всего колонок: ' . count($columns) . '</p>';
                }
            } catch (Exception $e) {
                $html .= '<p class="notice notice-error">Ошибка при загрузке колонок: ' . esc_html($e->getMessage()) . '</p>';
            }
        } else {
            $html .= '<div class="spinner is-active" style="float: none; margin: 10px 0;"></div>';
            $html .= '<p>Загрузка колонок... (таблица: ' . esc_html($table) . ')</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private static function render_user_fields_mapping($sync_settings) {
        $fields = [
            'user_id' => ['label' => 'ID пользователя', 'required' => true],
            'login' => ['label' => 'Логин', 'required' => true],
            'password' => ['label' => 'Пароль', 'required' => true],
            'email' => ['label' => 'Email', 'required' => false],
            'balance' => ['label' => 'Баланс', 'required' => false],
            'register_date' => ['label' => 'Дата регистрации', 'required' => false],
            'last_login' => ['label' => 'Последний вход', 'required' => false],
            'ip_address' => ['label' => 'IP адрес', 'required' => false],
            'status' => ['label' => 'Статус', 'required' => false],
            'nickname' => ['label' => 'Никнейм', 'required' => false],
            'level' => ['label' => 'Уровень', 'required' => false],
            'experience' => ['label' => 'Опыт', 'required' => false]
        ];
        
        foreach ($fields as $field => $info):
            $required = $info['required'] ? ' <span class="required">*</span>' : '';
            ?>
            <tr>
                <td>
                    <?php echo esc_html($info['label']); ?><?php echo $required; ?>
                    <?php if ($info['required']): ?>
                        <p class="description">Обязательное поле</p>
                    <?php endif; ?>
                </td>
                <td>
                    <select name="gsc_sync_settings[site_user_<?php echo $field; ?>]" 
                            data-depends="site_user_table" data-field="<?php echo $field; ?>">
                        <option value="">-- Не синхронизировать --</option>
                        <?php if (isset($sync_settings['site_user_' . $field])): ?>
                            <option value="<?php echo esc_attr($sync_settings['site_user_' . $field]); ?>" selected>
                                <?php echo esc_html($sync_settings['site_user_' . $field]); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </td>
                <td>
                    <select name="gsc_sync_settings[server_user_<?php echo $field; ?>]" 
                            data-depends="server_user_table" data-field="<?php echo $field; ?>">
                        <option value="">-- Не синхронизировать --</option>
                        <?php if (isset($sync_settings['server_user_' . $field])): ?>
                            <option value="<?php echo esc_attr($sync_settings['server_user_' . $field]); ?>" selected>
                                <?php echo esc_html($sync_settings['server_user_' . $field]); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </td>
            </tr>
            <?php
        endforeach;
    }
    
    private static function render_items_tab($sync_settings) {
        global $wpdb;
        ?>
        <div class="gsc-tab-section">
            <h3>Синхронизация предметов</h3>
            <p class="description">Сопоставьте таблицы и поля для синхронизации предметов магазина</p>
            
            <div class="gsc-columns-grid">
                <!-- Сайт -->
                <div class="gsc-column gsc-site-column">
                    <div class="gsc-column-header">
                        <h4><span class="dashicons dashicons-admin-site"></span> База данных сайта</h4>
                    </div>
                    
                    <div class="gsc-form-group">
                        <label for="site_items_table">Таблица предметов на сайте:</label>
                        <select id="site_items_table" name="gsc_sync_settings[site_items_table]" 
                                class="regular-text" data-type="site" data-target="items">
                            <option value="">-- Выберите таблицу --</option>
                            <?php
                            $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
                            $site_tables = [];
                            foreach ($tables as $table) {
                                $table_name = $table[0];
                                if (strpos($table_name, $wpdb->prefix) === 0) {
                                    $site_tables[] = $table_name;
                                }
                            }
                            
                            foreach ($site_tables as $table_name) {
                                $selected = isset($sync_settings['site_items_table']) && $sync_settings['site_items_table'] === $table_name ? 'selected' : '';
                                echo '<option value="' . esc_attr($table_name) . '" ' . $selected . '>' . esc_html($table_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div id="site_items_columns" class="gsc-columns-container">
                        <?php 
                        if (isset($sync_settings['site_items_table']) && !empty($sync_settings['site_items_table'])) {
                            echo self::render_column_fields('site_items', $sync_settings['site_items_table'], $sync_settings);
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Сервер -->
                <div class="gsc-column gsc-server-column">
                    <div class="gsc-column-header">
                        <h4><span class="dashicons dashicons-database"></span> База данных игрового сервера</h4>
                    </div>
                    
                    <div class="gsc-form-group">
                        <label for="server_items_table">Таблица предметов на сервере:</label>
                        <select id="server_items_table" name="gsc_sync_settings[server_items_table]" 
                                class="regular-text" data-type="server" data-target="items">
                            <option value="">-- Выберите таблицу --</option>
                            <?php
                            if (isset($sync_settings['server_items_table'])) {
                                echo '<option value="' . esc_attr($sync_settings['server_items_table']) . '" selected>' . esc_html($sync_settings['server_items_table']) . '</option>';
                            }
                            ?>
                        </select>
                        <?php if (get_option('gsc_db_enabled') !== '1'): ?>
                            <p class="gsc-warning"><span class="dashicons dashicons-warning"></span> Подключение к БД сервера отключено</p>
                        <?php endif; ?>
                    </div>
                    
                    <div id="server_items_columns" class="gsc-columns-container">
                        <?php 
                        if (isset($sync_settings['server_items_table']) && !empty($sync_settings['server_items_table'])) {
                            echo self::render_column_fields('server_items', $sync_settings['server_items_table'], $sync_settings);
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Сопоставление полей предметов -->
            <div class="gsc-fields-mapping">
                <h4>Связь полей предметов</h4>
                <div class="gsc-fields-table-wrapper">
                    <table class="wp-list-table widefat fixed striped gsc-fields-table">
                        <thead>
                            <tr>
                                <th width="30%">Тип данных</th>
                                <th width="35%">Поле на сайте</th>
                                <th width="35%">Поле на сервере</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php self::render_items_fields_mapping($sync_settings); ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="gsc-sync-options">
                    <label class="gsc-checkbox">
                        <input type="checkbox" name="gsc_sync_settings[sync_items_on_purchase]" value="1" 
                               <?php checked(isset($sync_settings['sync_items_on_purchase']) && $sync_settings['sync_items_on_purchase'] == '1'); ?>>
                        <span>Синхронизировать предметы при покупке</span>
                    </label>
                    
                    <label class="gsc-checkbox">
                        <input type="checkbox" name="gsc_sync_settings[create_missing_items]" value="1" 
                               <?php checked(isset($sync_settings['create_missing_items']) && $sync_settings['create_missing_items'] == '1'); ?>>
                        <span>Создавать отсутствующие предметы на сервере</span>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }
    
    private static function render_items_fields_mapping($sync_settings) {
        $fields = [
            'item_id' => ['label' => 'ID предмета', 'required' => true],
            'name' => ['label' => 'Название', 'required' => false],
            'description' => ['label' => 'Описание', 'required' => false],
            'price' => ['label' => 'Цена', 'required' => false],
            'type' => ['label' => 'Тип', 'required' => false],
            'category' => ['label' => 'Категория', 'required' => false],
            'level_required' => ['label' => 'Требуемый уровень', 'required' => false],
            'weight' => ['label' => 'Вес', 'required' => false],
            'stackable' => ['label' => 'Складываемый', 'required' => false],
            'durability' => ['label' => 'Прочность', 'required' => false],
            'icon' => ['label' => 'Иконка', 'required' => false],
            'rarity' => ['label' => 'Редкость', 'required' => false]
        ];
        
        foreach ($fields as $field => $info):
            $required = $info['required'] ? ' <span class="required">*</span>' : '';
            ?>
            <tr>
                <td>
                    <?php echo esc_html($info['label']); ?><?php echo $required; ?>
                </td>
                <td>
                    <select name="gsc_sync_settings[site_items_<?php echo $field; ?>]" 
                            data-depends="site_items_table" data-field="<?php echo $field; ?>">
                        <option value="">-- Не синхронизировать --</option>
                        <?php if (isset($sync_settings['site_items_' . $field])): ?>
                            <option value="<?php echo esc_attr($sync_settings['site_items_' . $field]); ?>" selected>
                                <?php echo esc_html($sync_settings['site_items_' . $field]); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </td>
                <td>
                    <select name="gsc_sync_settings[server_items_<?php echo $field; ?>]" 
                            data-depends="server_items_table" data-field="<?php echo $field; ?>">
                        <option value="">-- Не синхронизировать --</option>
                        <?php if (isset($sync_settings['server_items_' . $field])): ?>
                            <option value="<?php echo esc_attr($sync_settings['server_items_' . $field]); ?>" selected>
                                <?php echo esc_html($sync_settings['server_items_' . $field]); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </td>
            </tr>
            <?php
        endforeach;
    }
    
    private static function render_inventory_tab($sync_settings) {
        ?>
        <div class="gsc-tab-section">
            <h3>Синхронизация инвентаря</h3>
            <p class="description">Настройте таблицу инвентаря для автоматического добавления купленных предметов</p>
            
            <div class="gsc-columns-grid">
                <!-- Сервер -->
                <div class="gsc-column gsc-server-column">
                    <div class="gsc-column-header">
                        <h4><span class="dashicons dashicons-database"></span> База данных игрового сервера</h4>
                    </div>
                    
                    <div class="gsc-form-group">
                        <label for="server_inventory_table">Таблица инвентаря на сервере:</label>
                        <select id="server_inventory_table" name="gsc_sync_settings[server_inventory_table]" 
                                class="regular-text" data-type="server" data-target="inventory">
                            <option value="">-- Выберите таблицу --</option>
                            <?php
                            if (isset($sync_settings['server_inventory_table'])) {
                                echo '<option value="' . esc_attr($sync_settings['server_inventory_table']) . '" selected>' . esc_html($sync_settings['server_inventory_table']) . '</option>';
                            }
                            ?>
                        </select>
                        <?php if (get_option('gsc_db_enabled') !== '1'): ?>
                            <p class="gsc-warning"><span class="dashicons dashicons-warning"></span> Подключение к БД сервера отключено</p>
                        <?php endif; ?>
                    </div>
                    
                    <div id="server_inventory_columns" class="gsc-columns-container">
                        <?php 
                        if (isset($sync_settings['server_inventory_table']) && !empty($sync_settings['server_inventory_table'])) {
                            echo self::render_column_fields('server_inventory', $sync_settings['server_inventory_table'], $sync_settings);
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Настройки инвентаря -->
            <div class="gsc-inventory-settings">
                <h4>Настройки полей инвентаря</h4>
                <div class="gsc-fields-table-wrapper">
                    <table class="wp-list-table widefat fixed striped gsc-fields-table">
                        <thead>
                            <tr>
                                <th width="30%">Тип данных</th>
                                <th width="70%">Поле в таблице инвентаря</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php self::render_inventory_fields_mapping($sync_settings); ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="gsc-sync-options">
                    <label class="gsc-checkbox">
                        <input type="checkbox" name="gsc_sync_settings[update_inventory_on_purchase]" value="1" 
                               <?php checked(isset($sync_settings['update_inventory_on_purchase']) && $sync_settings['update_inventory_on_purchase'] == '1'); ?>>
                        <span>Автоматически добавлять купленные предметы в инвентарь</span>
                    </label>
                    
                    <label class="gsc-checkbox">
                        <input type="checkbox" name="gsc_sync_settings[check_duplicate_items]" value="1" 
                               <?php checked(isset($sync_settings['check_duplicate_items']) && $sync_settings['check_duplicate_items'] == '1'); ?>>
                        <span>Проверять дубликаты предметов в инвентаре</span>
                    </label>
                    
                    <div class="gsc-form-group" style="margin-top: 15px;">
                        <label for="default_slot">Слот по умолчанию:</label>
                        <input type="number" id="default_slot" name="gsc_sync_settings[default_slot]" 
                               value="<?php echo isset($sync_settings['default_slot']) ? intval($sync_settings['default_slot']) : 0; ?>" 
                               class="small-text" min="0">
                        <p class="description">Слот для добавления предметов по умолчанию (если не используется поле slot)</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private static function render_inventory_fields_mapping($sync_settings) {
        $fields = [
            'user_id' => ['label' => 'ID пользователя', 'required' => true],
            'item_id' => ['label' => 'ID предмета', 'required' => true],
            'quantity' => ['label' => 'Количество', 'required' => false],
            'slot' => ['label' => 'Слот', 'required' => false],
            'durability' => ['label' => 'Прочность', 'required' => false],
            'enchantment' => ['label' => 'Зачарование', 'required' => false],
            'bound' => ['label' => 'Привязка', 'required' => false],
            'acquire_date' => ['label' => 'Дата получения', 'required' => false],
            'expire_date' => ['label' => 'Дата истечения', 'required' => false]
        ];
        
        foreach ($fields as $field => $info):
            $required = $info['required'] ? ' <span class="required">*</span>' : '';
            ?>
            <tr>
                <td>
                    <?php echo esc_html($info['label']); ?><?php echo $required; ?>
                    <?php if ($info['required']): ?>
                        <p class="description">Обязательное поле</p>
                    <?php endif; ?>
                </td>
                <td>
                    <select name="gsc_sync_settings[server_inventory_<?php echo $field; ?>]" 
                            data-depends="server_inventory_table" data-field="<?php echo $field; ?>">
                        <option value="">-- Не использовать --</option>
                        <?php if (isset($sync_settings['server_inventory_' . $field])): ?>
                            <option value="<?php echo esc_attr($sync_settings['server_inventory_' . $field]); ?>" selected>
                                <?php echo esc_html($sync_settings['server_inventory_' . $field]); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </td>
            </tr>
            <?php
        endforeach;
    }
}
?>
