<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Frontend_Form_Registration {
    
    public static function render() {
        ob_start();
        ?>
        <div class="gsc-registration-form">
            <h3>Регистрация на сайте и в игре</h3>
            
            <form id="gsc-registration-form" method="post">
                <input type="hidden" name="gsc_register_nonce" value="<?php echo wp_create_nonce('gsc_register'); ?>">
                
                <div class="form-group">
                    <label for="gsc_username">Имя пользователя в игре *</label>
                    <input type="text" id="gsc_username" name="gsc_username" required 
                           placeholder="Введите имя для игры" minlength="3" maxlength="32">
                    <div id="username-feedback" class="feedback"></div>
                    <p class="description">Имя пользователя в игре. От 3 до 32 символов. Только буквы, цифры, точки, дефисы и подчеркивания.</p>
                </div>
                
                <div class="form-group">
                    <label for="user_email">Email *</label>
                    <input type="email" id="user_email" name="user_email" required 
                           placeholder="your@email.com">
                    <div id="email-feedback" class="feedback"></div>
                </div>
                
                <div class="form-group">
                    <label for="gsc_game_password">Пароль для игры *</label>
                    <div class="password-field">
                        <input type="password" id="gsc_game_password" name="gsc_game_password" required 
                               placeholder="Не менее 6 символов" minlength="6">
                        <button type="button" class="gsc-toggle-password" data-target="gsc_game_password">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div id="password-strength-bar" style="width: 0%; background-color: #dc3545; height: 5px; margin: 5px 0;"></div>
                        <div id="password-strength-label"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="gsc_game_password_confirm">Подтверждение пароля *</label>
                    <div class="password-field">
                        <input type="password" id="gsc_game_password_confirm" name="gsc_game_password_confirm" required 
                               placeholder="Повторите пароль">
                        <button type="button" class="gsc-toggle-password" data-target="gsc_game_password_confirm">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <div id="password-confirm-feedback" class="feedback"></div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="agree_terms" required>
                        Я согласен с правилами игры и обработкой персональных данных
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" id="gsc-register-submit" class="button button-primary">
                        <span class="btn-text">Зарегистрироваться</span>
                        <span class="btn-spinner" style="display: none;">
                            <span class="dashicons dashicons-update spin"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
