<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Tab_Payments {
    
    public static function render() {
        gsc_view_start(__CLASS__);
        
        $payments_enabled = get_option('gsc_payments_enabled');
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('gsc_settings_payments'); ?>
            
            <div class="card">
                <h2>Настройки платежей</h2>
                
                <table class="form-table">
                    <tr>
                        <th>Включить платежи</th>
                        <td>
                            <label>
                                <input type="checkbox" id="gsc_payments_enabled" name="gsc_payments_enabled" value="1" 
                                       <?php checked($payments_enabled, '1'); ?>>
                                Включить прием платежей
                            </label>
                        </td>
                    </tr>
                    
                    <tr id="payments_offline_row" style="display: <?php echo $payments_enabled ? 'none' : 'table-row'; ?>;">
                        <th><label for="gsc_payments_offline_text">Текст при отключенных платежах</label></th>
                        <td>
                            <textarea id="gsc_payments_offline_text" name="gsc_payments_offline_text" 
                                      rows="4" class="large-text"><?php echo esc_textarea(get_option('gsc_payments_offline_text')); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="payments_settings_row" style="display: <?php echo $payments_enabled ? 'block' : 'none'; ?>;">
                <div class="card">
                    <h3>Настройки платежной системы</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="gsc_payment_system">Платежная система</label></th>
                            <td>
                                <select id="gsc_payment_system" name="gsc_payment_system">
                                    <option value="">-- Выберите систему --</option>
                                    <option value="yookassa" <?php selected(get_option('gsc_payment_system'), 'yookassa'); ?>>ЮKassa</option>
                                    <option value="cloudpayments" <?php selected(get_option('gsc_payment_system'), 'cloudpayments'); ?>>CloudPayments</option>
                                    <option value="paykeeper" <?php selected(get_option('gsc_payment_system'), 'paykeeper'); ?>>PayKeeper</option>
                                    <option value="robokassa" <?php selected(get_option('gsc_payment_system'), 'robokassa'); ?>>Robokassa</option>
                                    <option value="unitpay" <?php selected(get_option('gsc_payment_system'), 'unitpay'); ?>>UnitPay</option>
                                    <option value="custom" <?php selected(get_option('gsc_payment_system'), 'custom'); ?>>Кастомная</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="gsc_payment_shop_id">ID магазина</label></th>
                            <td>
                                <input type="text" id="gsc_payment_shop_id" name="gsc_payment_shop_id" 
                                       value="<?php echo esc_attr(get_option('gsc_payment_shop_id')); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="gsc_payment_secret_key">Секретный ключ</label></th>
                            <td>
                                <input type="password" id="gsc_payment_secret_key" name="gsc_payment_secret_key" 
                                       value="<?php echo esc_attr(get_option('gsc_payment_secret_key')); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="gsc_payment_public_key">Публичный ключ</label></th>
                            <td>
                                <input type="text" id="gsc_payment_public_key" name="gsc_payment_public_key" 
                                       value="<?php echo esc_attr(get_option('gsc_payment_public_key')); ?>" 
                                       class="regular-text">
                                <p class="description">Для некоторых платежных систем</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="gsc_payment_webhook_url">Webhook URL</label></th>
                            <td>
                                <input type="url" id="gsc_payment_webhook_url" name="gsc_payment_webhook_url" 
                                       value="<?php echo esc_attr(get_option('gsc_payment_webhook_url')); ?>" 
                                       class="regular-text">
                                <p class="description">URL для уведомлений о платежах: <?php echo home_url('/gsc-payment-webhook/'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="gsc_payment_success_url">URL успешной оплаты</label></th>
                            <td>
                                <input type="url" id="gsc_payment_success_url" name="gsc_payment_success_url" 
                                       value="<?php echo esc_attr(get_option('gsc_payment_success_url', home_url('/donate-success/'))); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="gsc_payment_fail_url">URL неудачной оплаты</label></th>
                            <td>
                                <input type="url" id="gsc_payment_fail_url" name="gsc_payment_fail_url" 
                                       value="<?php echo esc_attr(get_option('gsc_payment_fail_url', home_url('/donate-fail/'))); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="card">
                    <h3>Тестирование платежей</h3>
                    <p>
                        <button type="button" id="test-payment-connection" class="button button-secondary">
                            Проверить подключение
                        </button>
                        <span id="test-payment-result"></span>
                    </p>
                </div>
            </div>
            
            <?php submit_button('Сохранить настройки платежей'); ?>
        </form>
        <?php
        gsc_view_end();
    }
}
?>
