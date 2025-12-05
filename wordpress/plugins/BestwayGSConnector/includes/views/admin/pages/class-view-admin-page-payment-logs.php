<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Page_Payment_Logs {

    public static function render($data = []) {
        gsc_view_start(__CLASS__);
        
        $defaults = [
            'logs' => [],
            'total_items' => 0,
            'total_pages' => 1,
            'current_page' => 1,
            'search' => '',
            'status' => 'all',
            'date_from' => '',
            'date_to' => '',
            'payment_system' => ''
        ];
        $data = wp_parse_args($data, $defaults);
        ?>
        <div class="wrap">
            <h1>Логи платежей</h1>

            <div class="gsc-payment-logs-stats">
                <div class="gsc-stat-card total">
                    <h3>Всего платежей</h3>
                    <p class="stat-value" id="stat-total"><?php echo $data['total_items']; ?></p>
                    <p class="stat-label">За все время</p>
                </div>
                
                <div class="gsc-stat-card completed">
                    <h3>Завершено</h3>
                    <p class="stat-value" id="stat-completed">0</p>
                    <p class="stat-label">Успешные платежи</p>
                </div>
                
                <div class="gsc-stat-card pending">
                    <h3>В ожидании</h3>
                    <p class="stat-value" id="stat-pending">0</p>
                    <p class="stat-label">Ожидают оплаты</p>
                </div>
                
                <div class="gsc-stat-card failed">
                    <h3>Ошибки</h3>
                    <p class="stat-value" id="stat-failed">0</p>
                    <p class="stat-label">Неудачные платежи</p>
                </div>
                
                <div class="gsc-stat-card refunded">
                    <h3>Возвраты</h3>
                    <p class="stat-value" id="stat-refunded">0</p>
                    <p class="stat-label">Возвращенные платежи</p>
                </div>
            </div>

            <div class="gsc-payment-logs-controls">
                <div class="gsc-controls-grid">
                    <div class="gsc-control-group">
                        <label for="search">Поиск:</label>
                        <input type="search" id="search" name="s" value="<?php echo esc_attr($data['search']); ?>"
                            placeholder="ID, email, транзакция...">
                    </div>

                    <div class="gsc-control-group">
                        <label for="status">Статус:</label>
                        <select id="status" name="status">
                            <option value="all" <?php selected($data['status'], 'all'); ?>>Все статусы</option>
                            <option value="pending" <?php selected($data['status'], 'pending'); ?>>В ожидании</option>
                            <option value="completed" <?php selected($data['status'], 'completed'); ?>>Завершен</option>
                            <option value="failed" <?php selected($data['status'], 'failed'); ?>>Ошибка</option>
                            <option value="refunded" <?php selected($data['status'], 'refunded'); ?>>Возврат</option>
                        </select>
                    </div>

                    <div class="gsc-control-group">
                        <label for="payment_system">Платежная система:</label>
                        <select id="payment_system" name="payment_system">
                            <option value="">Все системы</option>
                            <option value="yookassa" <?php selected($data['payment_system'], 'yookassa'); ?>>ЮKassa</option>
                            <option value="cloudpayments" <?php selected($data['payment_system'], 'cloudpayments'); ?>>CloudPayments</option>
                            <option value="paykeeper" <?php selected($data['payment_system'], 'paykeeper'); ?>>PayKeeper</option>
                            <option value="robokassa" <?php selected($data['payment_system'], 'robokassa'); ?>>Robokassa</option>
                            <option value="unitpay" <?php selected($data['payment_system'], 'unitpay'); ?>>UnitPay</option>
                        </select>
                    </div>

                    <div class="gsc-control-group">
                        <label for="date_from">Дата с:</label>
                        <input type="date" id="date_from" name="date_from"
                            value="<?php echo esc_attr($data['date_from']); ?>">
                    </div>

                    <div class="gsc-control-group">
                        <label for="date_to">Дата по:</label>
                        <input type="date" id="date_to" name="date_to"
                            value="<?php echo esc_attr($data['date_to']); ?>">
                    </div>
                </div>

                <div class="gsc-actions-row">
                    <button type="button" class="button button-primary" onclick="this.closest('form').submit()">
                        Фильтровать
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=gsc-payment-logs'); ?>"
                        class="button button-secondary">Сбросить</a>
                    <button type="button" id="clear-old-payments" class="button button-secondary">
                        Очистить старые платежи (90+ дней)
                    </button>
                    <button type="button" id="export-payments" class="button">
                        Экспорт платежей
                    </button>
                </div>
            </div>

            <?php if (empty($data['logs'])): ?>
                <div class="notice notice-info">
                    <p>Платежные записи не найдены</p>
                </div>
            <?php else: ?>
                <div class="gsc-payment-logs-table-container">
                    <table class="gsc-payment-logs-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Товар</th>
                                <th>Сумма</th>
                                <th>Платежная система</th>
                                <th>Транзакция</th>
                                <th>Статус</th>
                                <th>IP</th>
                                <th>Дата</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $donate_model = new GSC_Model_Donate();
                            foreach ($data['logs'] as $log):
                                $user = $log->user_id ? get_userdata($log->user_id) : null;
                                $item = $donate_model->get_item($log->item_id);
                                $payment_data = json_decode($log->payment_data, true);
                                ?>
                                <tr>
                                    <td><?php echo esc_html($log->id); ?></td>
                                    <td class="log-user-info">
                                        <?php if ($user): ?>
                                            <a href="<?php echo get_edit_user_link($log->user_id); ?>">
                                                <?php echo esc_html($user->display_name); ?>
                                            </a>
                                            <br>
                                            <small><?php echo esc_html($user->user_email); ?></small>
                                        <?php else: ?>
                                            <em>Гость</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item): ?>
                                            <strong><?php echo esc_html($item->title); ?></strong>
                                            <br>
                                            <small>ID: <?php echo esc_html($item->game_id); ?></small>
                                        <?php else: ?>
                                            <em>Товар #<?php echo esc_html($log->item_id); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td class="log-amount">
                                        <?php echo GSC_View_Shared_Common::format_price($log->amount); ?>
                                    </td>
                                    <td>
                                        <?php if ($log->payment_system): ?>
                                            <?php 
                                            $system_names = [
                                                'yookassa' => 'ЮKassa',
                                                'cloudpayments' => 'Cloud',
                                                'paykeeper' => 'PayKeeper', 
                                                'robokassa' => 'Robokassa',
                                                'unitpay' => 'UnitPay',
                                                'card' => 'Карта',
                                                'yoomoney' => 'ЮMoney',
                                                'qiwi' => 'QIWI',
                                                'mobile' => 'Моб.'
                                            ];
                                            $system_name = $system_names[$log->payment_system] ?? ucfirst($log->payment_system);
                                            ?>
                                            <span class="payment-system-badge payment-<?php echo esc_attr($log->payment_system); ?>">
                                                <?php echo esc_html($system_name); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="payment-system-badge">Не указана</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log->transaction_id): ?>
                                            <code class="log-transaction"><?php echo esc_html(substr($log->transaction_id, 0, 15) . '...'); ?></code>
                                        <?php else: ?>
                                            <em>Нет</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo GSC_View_Shared_Common::render_status_badge($log->status, true); ?>
                                    </td>
                                    <td>
                                        <?php if ($log->ip_address): ?>
                                            <code><?php echo esc_html($log->ip_address); ?></code>
                                        <?php else: ?>
                                            <em>Неизвестно</em>
                                        <?php endif; ?>
                                    </td>
                                    <td class="log-date">
                                        <?php echo GSC_View_Shared_Common::format_date($log->created_at, 'd.m.Y H:i'); ?>
                                    </td>
                                    <td class="log-actions">
                                        <button type="button" class="button button-small view-payment-details"
                                            data-payment-id="<?php echo esc_attr($log->id); ?>"
                                            data-payment-data='<?php echo esc_attr(wp_json_encode($payment_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>'
                                            data-user-info="<?php echo esc_attr($user ? $user->display_name . ' (' . $user->user_email . ')' : 'Гость'); ?>"
                                            data-item-info="<?php echo esc_attr($item ? $item->title . ' (ID: ' . $item->game_id . ')' : 'Товар #' . $log->item_id); ?>">
                                            Детали
                                        </button>

                                        <?php if ($log->status === 'pending' && current_user_can('manage_options')): ?>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=gsc-payment-logs&action=manual_complete&id=' . $log->id), 'manual_complete_' . $log->id); ?>"
                                                class="button button-small button-success"
                                                onclick="return confirm('Отметить платеж как завершенный?')">
                                                Завершить
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($data['total_pages'] > 1): ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $data['total_items']; ?> записей</span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $data['total_pages'],
                                'current' => $data['current_page']
                            ]);
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div id="payment-details-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Детали платежа #<span id="payment-id"></span></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="payment-detail-grid">
                        <div class="payment-detail-card">
                            <h4>Основная информация</h4>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Пользователь:</span>
                                <span class="payment-detail-value" id="detail-user"></span>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Товар:</span>
                                <span class="payment-detail-value" id="detail-item"></span>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Сумма:</span>
                                <span class="payment-detail-value" id="detail-amount"></span>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Статус:</span>
                                <span class="payment-detail-value" id="detail-status"></span>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Дата:</span>
                                <span class="payment-detail-value" id="detail-date"></span>
                            </div>
                        </div>

                        <div class="payment-detail-card">
                            <h4>Дополнительно</h4>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">IP адрес:</span>
                                <span class="payment-detail-value" id="detail-ip"></span>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">User Agent:</span>
                                <span class="payment-detail-value" id="detail-user-agent"></span>
                            </div>
                        </div>
                    </div>

                    <div class="payment-detail-card">
                        <h4>Платежные данные</h4>
                        <div id="detail-payment-data"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button" id="close-payment-modal">Закрыть</button>
                    <button type="button" class="button button-primary" id="copy-payment-data">
                        Копировать данные
                    </button>
                </div>
            </div>
        </div>
        <?php
        gsc_view_end();
    }
}
?>
