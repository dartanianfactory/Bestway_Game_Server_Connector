<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Page_Logs {
    
    public static function render() {
        gsc_view_start(__CLASS__);
        
        $log_controller = GSC_Controller_Log::instance();
        
        $dates = $log_controller->get_dates();
        $current_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : (count($dates) > 0 ? $dates[0] : date('Y-m-d'));
        $log_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        
        $logs = $log_controller->read($current_date, $limit);
        
        if ($log_type !== 'all') {
            $logs = array_filter($logs, function($log) use ($log_type) {
                return strtolower($log['type']) === strtolower($log_type);
            });
        }
        
        if (!empty($search)) {
            $logs = array_filter($logs, function($log) use ($search) {
                return stripos($log['message'], $search) !== false || 
                       stripos($log['raw'], $search) !== false;
            });
        }
        
        $stats = $log_controller->get_stats();
        ?>
        <div class="wrap">
            <h1>Логи плагина GSConnector</h1>
            
            <div class="gsc-logs-stats">
                <div class="gsc-stat-card total">
                    <h3>Всего записей</h3>
                    <p class="stat-value"><?php echo $stats['total_logs']; ?></p>
                    <p class="stat-label">За все время</p>
                </div>
                
                <div class="gsc-stat-card today">
                    <h3>За сегодня</h3>
                    <p class="stat-value"><?php echo $stats['today_logs']; ?></p>
                    <p class="stat-label"><?php echo date_i18n('d.m.Y'); ?></p>
                </div>
                
                <div class="gsc-stat-card files">
                    <h3>Файлов логов</h3>
                    <p class="stat-value"><?php echo $stats['log_files']; ?></p>
                    <p class="stat-label">В папке logs</p>
                </div>
                
                <div class="gsc-stat-card oldest">
                    <h3>Самый старый</h3>
                    <p class="stat-value"><?php echo $stats['oldest_log'] ? date_i18n('d.m.Y', strtotime($stats['oldest_log'])) : 'Нет'; ?></p>
                    <p class="stat-label">Дата первого лога</p>
                </div>
                
                <div class="gsc-stat-card newest">
                    <h3>Самый новый</h3>
                    <p class="stat-value"><?php echo $stats['newest_log'] ? date_i18n('d.m.Y', strtotime($stats['newest_log'])) : 'Нет'; ?></p>
                    <p class="stat-label">Дата последнего лога</p>
                </div>
            </div>
            
            <div class="gsc-logs-controls">
                <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                    <input type="hidden" name="page" value="gsc-logs">
                    
                    <div class="gsc-controls-grid">
                        <div class="gsc-control-group">
                            <label for="log-date">Дата лога:</label>
                            <select id="log-date" name="date">
                                <?php if (empty($dates)): ?>
                                    <option value="<?php echo date('Y-m-d'); ?>"><?php echo date_i18n('d.m.Y'); ?></option>
                                <?php else: ?>
                                    <?php foreach ($dates as $date): ?>
                                        <option value="<?php echo $date; ?>" <?php selected($current_date, $date); ?>>
                                            <?php echo date_i18n('d.m.Y', strtotime($date)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="gsc-control-group">
                            <label for="log-type">Тип лога:</label>
                            <select id="log-type" name="type">
                                <option value="all" <?php selected($log_type, 'all'); ?>>Все типы</option>
                                <option value="info" <?php selected($log_type, 'info'); ?>>Инфо</option>
                                <option value="error" <?php selected($log_type, 'error'); ?>>Ошибки</option>
                                <option value="warning" <?php selected($log_type, 'warning'); ?>>Предупреждения</option>
                                <option value="success" <?php selected($log_type, 'success'); ?>>Успех</option>
                                <option value="debug" <?php selected($log_type, 'debug'); ?>>Отладка</option>
                                <option value="database" <?php selected($log_type, 'database'); ?>>База данных</option>
                                <option value="payment" <?php selected($log_type, 'payment'); ?>>Платежи</option>
                                <option value="sync" <?php selected($log_type, 'sync'); ?>>Синхронизация</option>
                            </select>
                        </div>
                        
                        <div class="gsc-control-group">
                            <label for="log-limit">Количество записей:</label>
                            <select id="log-limit" name="limit">
                                <option value="50" <?php selected($limit, 50); ?>>50</option>
                                <option value="100" <?php selected($limit, 100); ?>>100</option>
                                <option value="200" <?php selected($limit, 200); ?>>200</option>
                                <option value="500" <?php selected($limit, 500); ?>>500</option>
                            </select>
                        </div>
                        
                        <div class="gsc-control-group">
                            <label for="log-search">Поиск:</label>
                            <input type="search" id="log-search" name="s" value="<?php echo esc_attr($search); ?>" 
                                   placeholder="Поиск по сообщениям...">
                        </div>
                    </div>
                    
                    <div class="gsc-actions-row">
                        <button type="submit" class="button button-primary">Применить фильтры</button>
                        <a href="<?php echo admin_url('admin.php?page=gsc-logs'); ?>" class="button">Сбросить</a>
                        <button type="button" id="clear-old-logs" class="button button-secondary">
                            Очистить старые логи (30+ дней)
                        </button>
                        <button type="button" id="export-logs" class="button">Экспорт логов</button>
                    </div>
                </form>
            </div>
            
            <?php if (empty($logs)): ?>
                <div class="notice notice-info">
                    <p>Логи не найдены для выбранной даты или фильтра.</p>
                </div>
            <?php else: ?>
                <div class="gsc-logs-table-container">
                    <table class="gsc-logs-table">
                        <thead>
                            <tr>
                                <th width="150">Время</th>
                                <th width="100">Тип</th>
                                <th width="120">IP</th>
                                <th width="100">Пользователь</th>
                                <th>Сообщение</th>
                                <th width="100">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="log-timestamp"><?php echo $log['timestamp']; ?></td>
                                    <td class="log-type-cell">
                                        <span class="log-type-badge log-type-<?php echo strtolower($log['type']); ?>">
                                            <?php echo $log['type']; ?>
                                        </span>
                                    </td>
                                    <td class="log-ip"><?php echo $log['ip']; ?></td>
                                    <td class="log-user-id">
                                        <?php if ($log['user_id']): ?>
                                            <a href="<?php echo get_edit_user_link($log['user_id']); ?>" target="_blank">
                                                #<?php echo $log['user_id']; ?>
                                            </a>
                                        <?php else: ?>
                                            Гость
                                        <?php endif; ?>
                                    </td>
                                    <td class="log-message" data-raw="<?php echo esc_attr($log['raw']); ?>">
                                        <?php echo esc_html($log['message']); ?>
                                    </td>
                                    <td class="log-actions">
                                        <button type="button" class="button button-small view-log-details" 
                                                data-log-id="<?php echo $log['timestamp']; ?>"
                                                data-log-data='<?php echo json_encode($log, JSON_UNESCAPED_UNICODE); ?>'>
                                            Детали
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <p class="description">Показано <?php echo count($logs); ?> записей за <?php echo date_i18n('d.m.Y', strtotime($current_date)); ?></p>
            <?php endif; ?>
        </div>
        
        <div id="log-context-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Детали лога</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="log-detail-grid">
                        <div class="log-detail-card">
                            <h4>Основная информация</h4>
                            <div class="log-detail-row">
                                <span class="log-detail-label">Время:</span>
                                <span class="log-detail-value" id="detail-timestamp"></span>
                            </div>
                            <div class="log-detail-row">
                                <span class="log-detail-label">Тип:</span>
                                <span class="log-detail-value" id="detail-type"></span>
                            </div>
                            <div class="log-detail-row">
                                <span class="log-detail-label">IP:</span>
                                <span class="log-detail-value" id="detail-ip"></span>
                            </div>
                            <div class="log-detail-row">
                                <span class="log-detail-label">ID пользователя:</span>
                                <span class="log-detail-value" id="detail-user-id"></span>
                            </div>
                        </div>
                        
                        <div class="log-detail-card">
                            <h4>Сообщение</h4>
                            <div id="detail-message" class="log-message-detail"></div>
                        </div>
                        
                        <div class="log-detail-card">
                            <h4>Контекст</h4>
                            <div id="detail-context"></div>
                        </div>
                    </div>
                    
                    <div class="log-raw-data">
                        <h4>Исходная строка лога</h4>
                        <pre id="log-context-content"></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button" id="close-log-modal">Закрыть</button>
                    <button type="button" class="button button-primary" id="copy-log-details">Копировать лог</button>
                </div>
            </div>
        </div>
        <?php
        gsc_view_end();
    }
}
?>
