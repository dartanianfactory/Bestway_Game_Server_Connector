<?php
class GSC_Controller_Registration extends GSC_Controller_Base
{
    public function __construct()
    {
        if (get_option('gsc_db_enabled') === '1') {
            add_action('user_register', [$this, 'sync_with_game_server'], 10, 2);
            add_action('register_form', [$this, 'add_registration_fields']);
            add_filter('registration_errors', [$this, 'validate_registration'], 10, 3);
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts()
    {
        if (is_user_logged_in())
            return;

        wp_enqueue_style('gsc-registration', GSC_URL . 'assets/css/registration.css', [], GSC_VERSION);
        wp_enqueue_script('gsc-registration', GSC_URL . 'assets/js/registration.js', ['jquery'], GSC_VERSION, true);

        wp_localize_script('gsc-registration', 'gscRegistration', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gsc_check_username')
        ]);
    }

    public function handle_shortcode($atts = [])
    {
        if (is_user_logged_in()) {
            $username = get_user_meta(get_current_user_id(), 'gsc_game_username', true);
            if ($username) {
                if ($this->view_exists('GSC_View_Frontend_Registration', 'render_success')) {
                    return GSC_View_Frontend_Registration::render_success($username);
                } else {
                    return $this->render_success_fallback($username);
                }
            }
        }

        if ($this->view_exists('GSC_View_Frontend_Form_Registration', 'render')) {
            return GSC_View_Frontend_Form_Registration::render();
        } else {
            return $this->render_registration_form_fallback();
        }
    }

    public function add_registration_fields()
    {
        if ($this->view_exists('GSC_View_Frontend_Form_Registration', 'render')) {
            echo GSC_View_Frontend_Form_Registration::render(['is_wp_form' => true]);
        }
    }

    public function validate_registration($errors, $sanitized_user_login, $user_email)
    {
        $username = $this->get_request_param('gsc_username');
        $password = $this->get_request_param('gsc_game_password');
        $password_confirm = $this->get_request_param('gsc_game_password_confirm');

        if (empty($username)) {
            $errors->add('gsc_username_error', '<strong>Ошибка</strong>: Введите имя пользователя для игры.');
        } elseif (strlen($username) < 3 || strlen($username) > 32) {
            $errors->add('gsc_username_length', '<strong>Ошибка</strong>: Имя пользователя должно быть от 3 до 32 символов.');
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
            $errors->add('gsc_username_invalid', '<strong>Ошибка</strong>: Имя пользователя может содержать только буквы, цифры, точки, дефисы и подчеркивания.');
        }

        if (empty($password)) {
            $errors->add('gsc_password_empty', '<strong>Ошибка</strong>: Введите пароль для игры.');
        } elseif (strlen($password) < 6) {
            $errors->add('gsc_password_length', '<strong>Ошибка</strong>: Пароль должен содержать минимум 6 символов.');
        } elseif ($password !== $password_confirm) {
            $errors->add('gsc_password_mismatch', '<strong>Ошибка</strong>: Пароли не совпадают.');
        }

        return $errors;
    }

    public function sync_with_game_server($user_id, $userdata)
    {
        $username = $this->get_request_param('gsc_username');
        $password = $this->get_request_param('gsc_game_password');

        if (empty($username) || empty($password)) {
            return;
        }

        $db_model = $this->get_model('DB');
        if (!$db_model) {
            $this->log('Model not found in sync_with_game_server', 'error');
            return;
        }

        $result = $db_model->create_game_account($username, $password, $userdata['user_email']);

        if ($result) {
            update_user_meta($user_id, 'gsc_game_account_id', $result);
            update_user_meta($user_id, 'gsc_game_username', $username);
        } else {
            $errors = $db_model->get_errors();
            $this->log('Ошибка создания игрового аккаунта для user_id ' . $user_id . ': ' . implode(', ', $errors), 'error');

            wp_mail(
                $userdata['user_email'],
                'Ошибка создания игрового аккаунта',
                'При создании вашего игрового аккаунта возникла ошибка. Пожалуйста, свяжитесь с администрацией.'
            );
        }
    }

    private function render_success_fallback($username)
    {
        ob_start();
        ?>
        <div class="gsc-registration-success">
            <h2>Регистрация успешна!</h2>
            <p>Добро пожаловать, <?php echo esc_html($username); ?>!</p>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_registration_form_fallback()
    {
        return $this->render_fallback('registration_form');
    }

}
?>
