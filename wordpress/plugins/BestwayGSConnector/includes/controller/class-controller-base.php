<?php
if (!defined('ABSPATH')) exit;

abstract class GSC_Controller_Base {
    
    protected static $instances = [];
    
    public static function instance() {
        $class = get_called_class();
        
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }
        
        return self::$instances[$class];
    }
    
    protected function verify_ajax_security($nonce_action = 'gsc_admin_nonce', $capability = 'manage_options') {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            if (class_exists('GSC_Controller_Log')) {
                GSC_Controller_Log::error('Security check failed in ' . get_called_class());
            }
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can($capability)) {
            if (class_exists('GSC_Controller_Log')) {
                GSC_Controller_Log::error('Unauthorized access in ' . get_called_class());
            }
            wp_send_json_error('Unauthorized');
        }
        
        return true;
    }
    
    protected function verify_web_security($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_die('Unauthorized access', 403);
        }
        
        check_admin_referer('gsc_admin_nonce');
        return true;
    }
    
    protected function get_model($model_name) {
        $model_class = 'GSC_Model_' . $model_name;
        
        if (class_exists($model_class)) {
            return new $model_class();
        }
        
        $model_file = GSC_PATH . 'includes/model/class-model-' . strtolower($model_name) . '.php';
        if (file_exists($model_file)) {
            require_once $model_file;
            
            if (class_exists($model_class)) {
                return new $model_class();
            }
        }
        
        if (class_exists('GSC_Controller_Log')) {
            GSC_Controller_Log::error('Model not found: ' . $model_class);
        }
        
        return null;
    }
    
    protected function json_response($success, $data = [], $message = '') {
        if ($success) {
            wp_send_json_success(array_merge(['message' => $message], $data));
        } else {
            wp_send_json_error(array_merge(['message' => $message], $data));
        }
    }
    
    protected function log($message, $type = 'info', $context = []) {
        if (!class_exists('GSC_Controller_Log')) {
            return;
        }
        
        $context['controller'] = get_called_class();
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $context['method'] = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        
        switch ($type) {
            case 'error':
                GSC_Controller_Log::error($message, $context);
                break;
            case 'warning':
                GSC_Controller_Log::warning($message, $context);
                break;
            case 'success':
                GSC_Controller_Log::success($message, $context);
                break;
            case 'debug':
                GSC_Controller_Log::debug($message, $context);
                break;
            case 'db':
                GSC_Controller_Log::db($message, $context);
                break;
            case 'payment':
                GSC_Controller_Log::payment($message, $context);
                break;
            case 'sync':
                GSC_Controller_Log::sync($message, $context);
                break;
            default:
                GSC_Controller_Log::info($message, $context);
        }
    }
    
    protected function get_request_param($key, $default = '', $type = 'text') {
        if (!isset($_REQUEST[$key])) {
            return $default;
        }
        
        $value = $_REQUEST[$key];
        
        switch ($type) {
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            case 'bool':
                return (bool)$value;
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'array':
                return is_array($value) ? array_map('sanitize_text_field', $value) : [];
            default:
                return sanitize_text_field($value);
        }
    }
    
    protected function view_exists($view_class, $method = 'render') {
        if (!class_exists($view_class)) {
            $this->log("View class not found: {$view_class}", 'warning');
            return false;
        }
        
        if (!method_exists($view_class, $method)) {
            $this->log("View method not found: {$view_class}::{$method}()", 'warning');
            return false;
        }
        
        return true;
    }
    
    protected function render_fallback($type, $data = []) {
        ob_start();
        
        switch ($type) {
            case 'donate_shop':
                ?>
                <div class="gsc-donate-shop-fallback">
                    <h2>Магазин</h2>
                    <p>Магазин временно недоступен. Всего предметов: <?php echo $data['item_count'] ?? 0; ?></p>
                </div>
                <?php
                break;
                
            case 'registration_form':
                ?>
                <div class="gsc-registration-fallback">
                    <h2>Регистрация</h2>
                    <p>Форма регистрации временно недоступна.</p>
                </div>
                <?php
                break;
                
            case 'admin_page':
                ?>
                <div class="wrap">
                    <h1><?php echo esc_html($data['title'] ?? 'Админ-страница'); ?></h1>
                    <div class="notice notice-warning">
                        <p>Вьюшка для этой страницы не найдена. Отображаем базовый контент.</p>
                    </div>
                </div>
                <?php
                break;
                
            default:
                ?>
                <div class="gsc-fallback-message">
                    <p>Контент временно недоступен.</p>
                </div>
                <?php
        }
        
        return ob_get_clean();
    }
}
?>