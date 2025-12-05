<?php
if (!defined('ABSPATH')) exit;

class GSC_Controller_Donate extends GSC_Controller_Base {
    
    public function __construct() {
        if (get_option('gsc_donate_enabled') === '1') {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('gsc-donate', GSC_URL . 'assets/css/donate.css', [], GSC_VERSION);
        wp_enqueue_script('gsc-donate', GSC_URL . 'assets/js/donate.js', ['jquery'], GSC_VERSION, true);
    }
    
    public function handle_shortcode($atts = []) {
        $model = $this->get_model('Donate');
        if (!$model) {
            return $this->render_fallback('donate_shop', ['item_count' => 0]);
        }

        $items = $model->get_active_items();
        
        $data = [
            'items' => $items,
            'user_id' => get_current_user_id(),
            'user_email' => wp_get_current_user()->user_email,
            'payments_enabled' => get_option('gsc_payments_enabled'),
            'payments_offline_text' => get_option('gsc_payments_offline_text'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gsc_donate_nonce'),
            'login_url' => wp_login_url(get_permalink()),
            'register_url' => wp_registration_url()
        ];
        
        if ($this->view_exists('GSC_View_Frontend_Donate_Shop', 'render_shop')) {
            return GSC_View_Frontend_Donate_Shop::render_shop($data);
        } else {
            return $this->render_donate_shop_fallback($data);
        }
    }
    
    private function render_donate_shop_fallback($data) {
        return $this->render_fallback('donate_shop', ['item_count' => count($data['items'])]);
    }
}
?>
