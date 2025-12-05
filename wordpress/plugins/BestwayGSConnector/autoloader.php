<?php
if (!defined('ABSPATH'))
    exit;

class GSC_Autoloader
{
    private static $instance = null;
    private $prefix = 'GSC_';
    private $base_path;
    private $initialized = false;

    public static function init()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->base_path = plugin_dir_path(__FILE__);
        spl_autoload_register([$this, 'autoload']);
        $this->initialized = true;
    }

    public function autoload($class_name)
    {
        if (strpos($class_name, $this->prefix) !== 0) {
            return;
        }

        if (class_exists($class_name, false)) {
            return;
        }

        $class = substr($class_name, strlen($this->prefix));
        $parts = explode('_', $class);

        if (count($parts) < 2) {
            return;
        }

        $type = $parts[0];
        array_shift($parts);

        $file_name = '';
        $file_path = '';

        switch ($type) {
            case 'Controller':
                $file_name = 'class-controller-' . $this->kebab_case(implode('_', $parts)) . '.php';
                $file_path = $this->base_path . 'includes/controller/' . $file_name;
                break;

            case 'Model':
                $file_name = 'class-model-' . $this->kebab_case(implode('_', $parts)) . '.php';
                $file_path = $this->base_path . 'includes/model/' . $file_name;
                break;

            case 'View':
                $file_path = $this->get_view_path($class_name);
                break;
        }

        if (!empty($file_path) && file_exists($file_path)) {
            require_once $file_path;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('GSC_DEBUG') && GSC_DEBUG) {
                error_log("GSC Autoloader: File not found for class {$class_name} at {$file_path}");
            }
        }
    }

    private function get_view_path($class_name)
    {
        $class_map = [
            // Admin Pages
            'GSC_View_Admin_Page_Donate_Items' => 'includes/views/admin/pages/class-view-admin-page-donate-items.php',
            'GSC_View_Admin_Page_Edit_Item' => 'includes/views/admin/pages/class-view-admin-page-edit-item..php',
            'GSC_View_Admin_Page_Logs' => 'includes/views/admin/pages/class-view-admin-page-logs.php',
            'GSC_View_Admin_Page_Payment_Logs' => 'includes/views/admin/pages/class-view-admin-page-payment-logs.php',
            
            // Admin Tabs
            'GSC_View_Admin_Tab_Contacts' => 'includes/views/admin/tabs/class-view-admin-tab-contacts.php',
            'GSC_View_Admin_Tab_DB' => 'includes/views/admin/tabs/class-view-admin-tab-db.php',
            'GSC_View_Admin_Tab_Donate' => 'includes/views/admin/tabs/class-view-admin-tab-donate.php',
            'GSC_View_Admin_Tab_Payments' => 'includes/views/admin/tabs/class-view-admin-tab-payments.php',
            'GSC_View_Admin_Tab_Sync' => 'includes/views/admin/tabs/class-view-admin-tab-sync.php',
            
            // Frontend
            'GSC_View_Frontend_Donate_Shop' => 'includes/views/frontend/class-view-frontend-donate-shop.php',
            'GSC_View_Frontend_Registration' => 'includes/views/frontend/class-view-frontend-registration.php',
            
            // Frontend Forms
            'GSC_View_Frontend_Form_Registration' => 'includes/views/frontend/forms/class-view-frontend-form-registration.php',
            
            // Shared
            'GSC_View_Shared_Common' => 'includes/views/shared/class-view-shared-common.php',
        ];

        if (isset($class_map[$class_name])) {
            $file_path = $this->base_path . $class_map[$class_name];
            
            if ($class_name === 'GSC_View_Admin_Page_Edit_Item' && !file_exists($file_path)) {
                $alt_path = $this->base_path . 'includes/views/admin/pages/class-view-admin-page-edit-item.php';
                if (file_exists($alt_path)) {
                    return $alt_path;
                }
            }
            
            return $file_path;
        }

        return $this->generate_view_path($class_name);
    }

    private function generate_view_path($class_name)
    {
        $class = substr($class_name, strlen($this->prefix) + 5);
        $parts = explode('_', $class);
        
        if (count($parts) < 2) {
            return '';
        }

        $type = strtolower($parts[0]);
        array_shift($parts);
        
        $subtype = '';
        $filename_parts = $parts;

        if ($type === 'admin') {
            if (isset($parts[0])) {
                if ($parts[0] === 'Page') {
                    $subtype = 'pages';
                    array_shift($filename_parts);
                } elseif ($parts[0] === 'Tab') {
                    $subtype = 'tabs';
                    array_shift($filename_parts);
                }
            }
        }

        elseif ($type === 'frontend') {
            if (isset($parts[0]) && $parts[0] === 'Form') {
                $subtype = 'forms';
                array_shift($filename_parts);
            }
        }

        $filename = 'class-view-' . strtolower($type);
        if ($subtype) {
            $filename .= '-' . strtolower(str_replace('s', '', $subtype));
        }
        $filename .= '-' . $this->kebab_case(implode('_', $filename_parts)) . '.php';

        $path_parts = ['includes', 'views', $type];
        if ($subtype) {
            $path_parts[] = $subtype;
        }
        
        $dir_path = $this->base_path . implode('/', $path_parts) . '/';
        $file_path = $dir_path . $filename;
        
        return $file_path;
    }

    private function kebab_case($string)
    {
        $string = preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '-$0', $string);
        $string = strtolower($string);
        $string = preg_replace('/_/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}
?>
