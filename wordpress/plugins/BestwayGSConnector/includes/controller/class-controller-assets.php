<?php
if (!defined('ABSPATH')) exit;

class GSC_Controller_Assets extends GSC_Controller_Base {

    private $current_view = '';
    private $view_assets = [];
    private $assets_registered = false;

    protected function __construct() {
        add_action('init', [$this, 'init_assets'], 1);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        add_action('gsc_view_render_start', [$this, 'set_current_view'], 10, 1);
        add_action('gsc_view_render_end', [$this, 'clear_current_view'], 10);
    }

    public function init_assets() {
        if ($this->assets_registered) {
            return;
        }

        try {
            $this->register_jquery_ui_assets();
            $this->register_base_assets();
            $this->register_all_admin_assets();
            $this->register_all_frontend_assets();

            add_filter('gsc_register_view_assets', [$this, 'register_view_assets_lazy'], 10, 2);

            $this->assets_registered = true;
        } catch (Exception $e) {
            error_log('GSC Assets Error: ' . $e->getMessage());
        }
    }

    private function register_jquery_ui_assets() {
        wp_register_script(
            'jquery-ui-datepicker',
            includes_url('js/jquery-ui/datepicker.min.js'),
            ['jquery', 'jquery-ui-core'],
            '1.13.2',
            true
        );
        
        wp_register_style(
            'jquery-ui-css',
            includes_url('css/jquery-ui.min.css'),
            [],
            '1.13.2'
        );
        
        wp_register_script(
            'jquery-ui-tooltip',
            includes_url('js/jquery-ui/tooltip.min.js'),
            ['jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position'],
            '1.13.2',
            true
        );
    }

    private function register_base_assets() {
        if (file_exists(GSC_PATH . 'assets/css/admin.css')) {
            wp_register_style('gsc-admin', GSC_URL . 'assets/css/admin.css', ['jquery-ui-css'], GSC_VERSION);
        } else {
            wp_register_style('gsc-admin', false, ['jquery-ui-css'], GSC_VERSION);
        }

        if (file_exists(GSC_PATH . 'assets/js/admin.js')) {
            wp_register_script('gsc-admin', GSC_URL . 'assets/js/admin.js', 
                ['jquery', 'jquery-ui-datepicker', 'jquery-ui-tooltip'],
                GSC_VERSION, true);
            
            wp_localize_script('gsc-admin', 'gsc_admin_data', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gsc_admin_nonce'),
                'texts' => [
                    'confirm_delete' => 'Вы уверены, что хотите удалить этот элемент?',
                    'loading' => 'Загрузка...',
                    'success' => 'Успешно!',
                    'error' => 'Ошибка!'
                ]
            ]);
        } else {
            wp_register_script('gsc-admin', false, 
                ['jquery', 'jquery-ui-datepicker', 'jquery-ui-tooltip'],
                GSC_VERSION, true);
        }

        if (file_exists(GSC_PATH . 'assets/css/frontend.css')) {
            wp_register_style('gsc-frontend', GSC_URL . 'assets/css/frontend.css', [], GSC_VERSION);
        } else {
            wp_register_style('gsc-frontend', false, [], GSC_VERSION);
        }

        if (file_exists(GSC_PATH . 'assets/js/frontend.js')) {
            wp_register_script('gsc-frontend', GSC_URL . 'assets/js/frontend.js', ['jquery'], GSC_VERSION, true);
        } else {
            wp_register_script('gsc-frontend', false, ['jquery'], GSC_VERSION, true);
        }
    }

    private function register_all_admin_assets() {
        $admin_pages_css = [
            'donate-items' => 'admin-page-donate-items.css',
            'edit-item' => 'admin-page-edit-item.css',
            'logs' => 'admin-page-logs.css',
            'payment-logs' => 'admin-page-payment-logs.css'
        ];

        foreach ($admin_pages_css as $page => $css_file) {
            if (file_exists(GSC_PATH . 'assets/css/' . $css_file)) {
                $handle = 'gsc-admin-page-' . $page . '-css';
                $url = GSC_URL . 'assets/css/' . $css_file;
                wp_register_style($handle, $url, ['gsc-admin'], GSC_VERSION);
                $this->add_to_view_assets('admin_page_' . str_replace('-', '_', $page), 'css', $handle);
            }
        }

        $admin_pages_js = [
            'donate-items' => 'admin-page-donate-items.js',
            'edit-item' => 'admin-page-edit-item.js',
            'logs' => 'admin-page-logs.js',
            'payment-logs' => 'admin-page-payment-logs.js'
        ];

        foreach ($admin_pages_js as $page => $js_file) {
            if (file_exists(GSC_PATH . 'assets/js/' . $js_file)) {
                $handle = 'gsc-admin-page-' . $page . '-js';
                $url = GSC_URL . 'assets/js/' . $js_file;
                wp_register_script($handle, $url, 
                    ['jquery', 'gsc-admin'],
                    GSC_VERSION, true);

                $this->localize_admin_page_script($handle, $page);
                
                $this->add_to_view_assets('admin_page_' . str_replace('-', '_', $page), 'js', $handle);
            }
        }

        $admin_tabs_css = [
            'sync' => 'admin-tab-sync.css',
            'contacts' => 'admin-tab-contacts.css'
        ];

        foreach ($admin_tabs_css as $tab => $css_file) {
            if (file_exists(GSC_PATH . 'assets/css/' . $css_file)) {
                $handle = 'gsc-admin-tab-' . $tab . '-css';
                $url = GSC_URL . 'assets/css/' . $css_file;
                wp_register_style($handle, $url, ['gsc-admin'], GSC_VERSION);
                $this->add_to_view_assets('admin_tab_' . $tab, 'css', $handle);
            }
        }

        $admin_tabs_js = [
            'db' => 'admin-tab-db.js',
            'sync' => 'admin-tab-sync.js',
            'payments' => 'admin-tab-payments.js',
            'contacts' => 'admin-tab-contacts.js'
        ];

        foreach ($admin_tabs_js as $tab => $js_file) {
            if (file_exists(GSC_PATH . 'assets/js/' . $js_file)) {
                $handle = 'gsc-admin-tab-' . $tab . '-js';
                $url = GSC_URL . 'assets/js/' . $js_file;
                wp_register_script($handle, $url, 
                    ['jquery', 'gsc-admin'],
                    GSC_VERSION, true);
                
                $this->localize_admin_tab_script($handle, $tab);
                
                $this->add_to_view_assets('admin_tab_' . $tab, 'js', $handle);
            }
        }
    }

    private function register_all_frontend_assets() {
        $frontend_css = [
            'donate' => 'donate.css',
            'registration' => 'registration.css'
        ];

        foreach ($frontend_css as $component => $css_file) {
            if (file_exists(GSC_PATH . 'assets/css/' . $css_file)) {
                $handle = 'gsc-frontend-' . $component . '-css';
                $url = GSC_URL . 'assets/css/' . $css_file;
                wp_register_style($handle, $url, ['gsc-frontend'], GSC_VERSION);
                $this->add_to_view_assets('frontend_' . $component, 'css', $handle);
            }
        }

        $frontend_js = [
            'donate' => 'donate.js',
            'registration' => 'registration.js'
        ];

        foreach ($frontend_js as $component => $js_file) {
            if (file_exists(GSC_PATH . 'assets/js/' . $js_file)) {
                $handle = 'gsc-frontend-' . $component . '-js';
                $url = GSC_URL . 'assets/js/' . $js_file;
                wp_register_script($handle, $url, ['jquery', 'gsc-frontend'], GSC_VERSION, true);
                $this->add_to_view_assets('frontend_' . $component, 'js', $handle);
            }
        }
    }

    private function localize_admin_page_script($handle, $page_name) {
        $data = [];

        switch ($page_name) {
            case 'donate-items':
                $data = [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('gsc_admin_nonce')
                ];
                wp_localize_script($handle, 'gsc_donate_items_data', $data);
                break;

            case 'edit-item':
                $data = [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('gsc_admin_nonce'),
                    'media_title' => 'Выберите изображение',
                    'media_button_text' => 'Выбрать'
                ];
                wp_localize_script($handle, 'gsc_edit_item_data', $data);
                break;

            case 'logs':
                $data = [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('gsc_admin_nonce')
                ];
                wp_localize_script($handle, 'gsc_logs_data', $data);
                break;

            case 'payment-logs':
                $data = [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('gsc_admin_nonce')
                ];
                wp_localize_script($handle, 'gsc_payment_logs_data', $data);
                break;
        }
    }

    private function localize_admin_tab_script($handle, $tab_name) {
        $data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gsc_admin_nonce')
        ];

        switch ($tab_name) {
            case 'sync':
                wp_localize_script($handle, 'gsc_sync_data', $data);
                break;
                
            case 'db':
                wp_localize_script($handle, 'gsc_db_data', $data);
                break;
                
            case 'payments':
                wp_localize_script($handle, 'gsc_payments_data', $data);
                break;
                
            case 'contacts':
                wp_localize_script($handle, 'gsc_contacts_data', $data);
                break;
        }
    }

    private function add_to_view_assets($view_key, $type, $handle) {
        if (!isset($this->view_assets[$view_key])) {
            $this->view_assets[$view_key] = [
                'css' => [],
                'js' => []
            ];
        }

        if ($type === 'css') {
            $this->view_assets[$view_key]['css'][] = $handle;
        } else {
            $this->view_assets[$view_key]['js'][] = $handle;
        }
    }

    public function register_view_assets_lazy($view_key, $force = false) {
        if (!$force && isset($this->view_assets[$view_key])) {
            return $this->view_assets[$view_key];
        }

        return isset($this->view_assets[$view_key]) ? $this->view_assets[$view_key] : ['css' => [], 'js' => []];
    }

    public function set_current_view($view_name) {
        $this->current_view = $view_name;

        if (!is_admin()) {
            $this->enqueue_view_assets($view_name);
        }
    }

    public function clear_current_view() {
        $this->current_view = '';
    }

    public function enqueue_admin_assets($hook) {
        wp_enqueue_style('jquery-ui-css');
        wp_enqueue_style('gsc-admin');
        wp_enqueue_script('gsc-admin');

        $view_key = $this->get_admin_view_from_hook($hook);
        
        if ($view_key) {
            $assets = apply_filters('gsc_register_view_assets', $view_key, false);
            $this->enqueue_view_assets($view_key);
        }

        if (strpos($hook, 'gsc-settings') !== false) {
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'db';
            $tab_view_key = 'admin_tab_' . $tab;

            $assets = apply_filters('gsc_register_view_assets', $tab_view_key, false);
            $this->enqueue_view_assets($tab_view_key);
        }
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style('gsc-frontend');
        wp_enqueue_script('gsc-frontend');

        if ($this->current_view) {
            $assets = apply_filters('gsc_register_view_assets', $this->current_view, false);
            $this->enqueue_view_assets($this->current_view);
        }
    }

    private function enqueue_view_assets($view_key) {
        if (isset($this->view_assets[$view_key])) {
            foreach ($this->view_assets[$view_key]['css'] as $handle) {
                if (wp_style_is($handle, 'registered')) {
                    wp_enqueue_style($handle);
                }
            }

            foreach ($this->view_assets[$view_key]['js'] as $handle) {
                if (wp_script_is($handle, 'registered')) {
                    wp_enqueue_script($handle);
                }
            }
        }
    }

    private function get_admin_view_from_hook($hook) {
        if (strpos($hook, 'gsc-donate-items') !== false) {
            return 'admin_page_donate_items';
        }

        if (strpos($hook, 'gsc-payment-logs') !== false) {
            return 'admin_page_payment_logs';
        }

        if (strpos($hook, 'gsc-logs') !== false) {
            return 'admin_page_logs';
        }

        return '';
    }

    public function get_view_key_from_class($class_name) {
        if (strpos($class_name, 'GSC_View_') !== 0) {
            return '';
        }

        $parts = substr($class_name, 9);
        $parts = explode('_', $parts);

        $result = [];
        foreach ($parts as $part) {
            $result[] = strtolower($part);
        }

        return implode('_', $result);
    }
}
?>
