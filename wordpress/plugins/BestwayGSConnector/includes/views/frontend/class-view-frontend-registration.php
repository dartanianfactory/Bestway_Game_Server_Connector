<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Frontend_Registration {
    
    public static function render_success($username) {
        ob_start();
        ?>
        <div class="gsc-registration-success">
            <h3>Регистрация успешна!</h3>
            <p>Ваш игровой аккаунт <strong><?php echo esc_html($username); ?></strong> создан.</p>
            <p>Теперь вы можете войти в игру с указанными данными.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}
