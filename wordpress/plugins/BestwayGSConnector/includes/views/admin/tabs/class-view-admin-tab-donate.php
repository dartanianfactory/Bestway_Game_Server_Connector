<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Tab_Donate {
    
    public static function render() {
        gsc_view_start(__CLASS__);
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('gsc_settings_donate'); ?>
            
            <div class="card">
                <h2>Настройки магазина</h2>
                
                <table class="form-table">
                    <tr>
                        <th>Включить систему магазина</th>
                        <td>
                            <label>
                                <input type="checkbox" name="gsc_donate_enabled" value="1" 
                                       <?php checked(get_option('gsc_donate_enabled', '1'), '1'); ?>>
                                Включить магазин
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php submit_button('Сохранить настройки магазина'); ?>
        </form>

        <p>
                <a href="<?php echo admin_url('admin.php?page=gsc-donate-items'); ?>" class="button button-primary">
                    Перейти к управлению магазином
                </a>
            </p>
        <?php
        gsc_view_end();
    }
}
?>
