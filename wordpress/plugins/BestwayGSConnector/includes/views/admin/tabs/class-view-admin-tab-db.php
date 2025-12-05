<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Tab_DB {
    
    public static function render() {
        gsc_view_start(__CLASS__);
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('gsc_settings_db'); ?>
            
            <div class="card">
                <h2>Настройки подключения к базе данных игрового сервера</h2>
                <p class="description">
                    Настройте подключение к базе данных игрового сервера. 
                    <strong>Настройки таблиц и полей находятся во вкладке "Синхронизация БД".</strong>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th>Включить подключение</th>
                        <td>
                            <label>
                                <input type="checkbox" name="gsc_db_enabled" value="1" 
                                       <?php checked(get_option('gsc_db_enabled'), '1'); ?>>
                                Включить подключение к БД игрового сервера
                            </label>
                            <p class="description">При отключении регистрация на сайте будет работать без создания аккаунтов на игровом сервере</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="gsc_db_host">Хост БД</label></th>
                        <td>
                            <input type="text" id="gsc_db_host" name="gsc_db_host" 
                                   value="<?php echo esc_attr(get_option('gsc_db_host')); ?>" 
                                   class="regular-text" placeholder="localhost">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="gsc_db_port">Порт</label></th>
                        <td>
                            <input type="number" id="gsc_db_port" name="gsc_db_port" 
                                   value="<?php echo esc_attr(get_option('gsc_db_port', '3306')); ?>" 
                                   class="small-text" placeholder="3306">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="gsc_db_name">Имя базы данных</label></th>
                        <td>
                            <input type="text" id="gsc_db_name" name="gsc_db_name" 
                                   value="<?php echo esc_attr(get_option('gsc_db_name')); ?>" 
                                   class="regular-text" placeholder="game_db">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="gsc_db_user">Пользователь БД</label></th>
                        <td>
                            <input type="text" id="gsc_db_user" name="gsc_db_user" 
                                   value="<?php echo esc_attr(get_option('gsc_db_user')); ?>" 
                                   class="regular-text" placeholder="game_user">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="gsc_db_password">Пароль БД</label></th>
                        <td>
                            <input type="password" id="gsc_db_password" name="gsc_db_password" 
                                   value="<?php echo esc_attr(get_option('gsc_db_password')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="gsc_db_charset">Кодировка</label></th>
                        <td>
                            <select id="gsc_db_charset" name="gsc_db_charset">
                                <option value="utf8" <?php selected(get_option('gsc_db_charset'), 'utf8'); ?>>UTF-8</option>
                                <option value="utf8mb4" <?php selected(get_option('gsc_db_charset', 'utf8mb4'), 'utf8mb4'); ?>>UTF-8 MB4</option>
                                <option value="cp1251" <?php selected(get_option('gsc_db_charset'), 'cp1251'); ?>>CP1251</option>
                                <option value="latin1" <?php selected(get_option('gsc_db_charset'), 'latin1'); ?>>Latin1</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h3>Настройки хеширования паролей</h3>
                <p class="description">Настройте хеширование паролей для соответствия вашей игровой системе</p>
                
                <table class="form-table">
                    <tr>
                        <th><label for="gsc_password_hash_type">Тип хеширования</label></th>
                        <td>
                            <select id="gsc_password_hash_type" name="gsc_password_hash_type">
                                <option value="bcrypt" <?php selected(get_option('gsc_password_hash_type', 'bcrypt'), 'bcrypt'); ?>>BCrypt</option>
                                <option value="md5" <?php selected(get_option('gsc_password_hash_type'), 'md5'); ?>>MD5</option>
                                <option value="sha1" <?php selected(get_option('gsc_password_hash_type'), 'sha1'); ?>>SHA1</option>
                                <option value="sha256" <?php selected(get_option('gsc_password_hash_type'), 'sha256'); ?>>SHA256</option>
                                <option value="argon2i" <?php selected(get_option('gsc_password_hash_type'), 'argon2i'); ?>>Argon2i</option>
                                <option value="custom" <?php selected(get_option('gsc_password_hash_type'), 'custom'); ?>>Кастомный</option>
                            </select>
                            <p class="description">BCrypt рекомендуется для новых проектов</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="gsc_password_salt">Соль для хеширования</label></th>
                        <td>
                            <input type="text" id="gsc_password_salt" name="gsc_password_salt" 
                                   value="<?php echo esc_attr(get_option('gsc_password_salt')); ?>" 
                                   class="large-text" placeholder="Случайная строка">
                            <p class="description">Используется для MD5, SHA1, SHA256 хешей</p>
                            <button type="button" class="button button-small" id="generate-salt">Сгенерировать соль</button>
                        </td>
                    </tr>
                    
                    <tr id="custom_hash_row" style="display: none;">
                        <th><label for="gsc_custom_hash_function">Кастомная функция</label></th>
                        <td>
                            <input type="text" id="gsc_custom_hash_function" name="gsc_custom_hash_function" 
                                   value="<?php echo esc_attr(get_option('gsc_custom_hash_function')); ?>" 
                                   class="regular-text" placeholder="my_custom_hash_function">
                            <p class="description">Имя PHP функции для хеширования: <code>function my_custom_hash_function($password, $salt) { return ...; }</code></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h3>Тестирование подключения</h3>
                <p>
                    <button type="button" id="test-db-connection" class="button button-secondary">
                        Проверить подключение к БД
                    </button>
                    <span id="test-db-result"></span>
                </p>
                <p class="description">
                    Проверяет возможность подключения к указанной базе данных игрового сервера.<br>
                    <strong>Внимание:</strong> Настройки таблиц находятся во вкладке "Синхронизация БД".
                </p>
            </div>
            
            <?php submit_button('Сохранить настройки БД'); ?>
        </form>
        <?php
        gsc_view_end();
    }
}
?>
