<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Page_Donate_Items {
    
    public static function render($data = []) {
        gsc_view_start(__CLASS__);
        
        $defaults = [
            'items' => [],
            'total_items' => 0,
            'total_pages' => 1,
            'current_page' => 1,
            'search' => '',
            'status' => 'all'
        ];
        $data = wp_parse_args($data, $defaults);
        ?>
        <div class="wrap">
            <h1>Предметы магазина</h1>
            
            <div class="gsc-stats-cards">
                <div class="gsc-stat-card total">
                    <div class="stat-icon">📦</div>
                    <div class="stat-content">
                        <h3>Всего предметов</h3>
                        <p class="stat-value"><?php echo $data['total_items']; ?></p>
                    </div>
                </div>
                
                <?php
                $status_counts = [
                    'active' => 0,
                    'inactive' => 0,
                    'archived' => 0
                ];
                
                foreach ($data['items'] as $item) {
                    if (isset($status_counts[$item->status])) {
                        $status_counts[$item->status]++;
                    }
                }
                ?>
                
                <div class="gsc-stat-card active">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <h3>Активные</h3>
                        <p class="stat-value"><?php echo $status_counts['active']; ?></p>
                    </div>
                </div>
                
                <div class="gsc-stat-card inactive">
                    <div class="stat-icon">⏸️</div>
                    <div class="stat-content">
                        <h3>Неактивные</h3>
                        <p class="stat-value"><?php echo $status_counts['inactive']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="gsc-controls-section">
                <form method="get" class="gsc-controls-grid">
                    <input type="hidden" name="page" value="gsc-donate-items">
                    
                    <div class="gsc-control-group">
                        <label for="search">Поиск:</label>
                        <input type="search" id="search" name="s" value="<?php echo esc_attr($data['search']); ?>" 
                               placeholder="По названию или ID...">
                    </div>
                    
                    <div class="gsc-control-group">
                        <label for="status">Статус:</label>
                        <select id="status" name="status">
                            <option value="all" <?php selected($data['status'], 'all'); ?>>Все</option>
                            <option value="active" <?php selected($data['status'], 'active'); ?>>Активные</option>
                            <option value="inactive" <?php selected($data['status'], 'inactive'); ?>>Неактивные</option>
                            <option value="archived" <?php selected($data['status'], 'archived'); ?>>Архив</option>
                        </select>
                    </div>
                    
                    <div class="gsc-control-group actions">
                        <button type="submit" class="button button-primary">Применить</button>
                        <a href="<?php echo admin_url('admin.php?page=gsc-donate-items'); ?>" class="button">Сбросить</a>
                    </div>
                </form>
                
                <div class="gsc-actions-row">
                    <a href="<?php echo admin_url('admin.php?page=gsc-donate-items&action=add'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span> Добавить предмет
                    </a>
                    
                    <div class="bulk-actions">
                        <select id="bulk-action-selector">
                            <option value="">Массовые действия</option>
                            <option value="activate">Активировать</option>
                            <option value="deactivate">Деактивировать</option>
                            <option value="delete">Удалить</option>
                            <option value="archive">В архив</option>
                        </select>
                        <button type="button" class="button" id="apply-bulk-action">Применить</button>
                    </div>
                </div>
            </div>
            
            <?php if (empty($data['items'])): ?>
                <div class="gsc-empty-state">
                    <div class="empty-icon">📦</div>
                    <h3>Предметы не найдены</h3>
                    <p>Начните с добавления первого предмета в магазин.</p>
                    <a href="<?php echo admin_url('admin.php?page=gsc-donate-items&action=add'); ?>" class="button button-primary">
                        Добавить предмет
                    </a>
                </div>
            <?php else: ?>
                <div class="gsc-table-container">
                    <table class="gsc-items-table">
                        <thead>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th width="60">ID</th>
                                <th width="120">Game ID</th>
                                <th>Предмет</th>
                                <th width="120">Цена</th>
                                <th width="120">Статус</th>
                                <th width="150">Дата создания</th>
                                <th width="180">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['items'] as $item): ?>
                                <tr>
                                    <td class="check-column">
                                        <input type="checkbox" name="item_ids[]" value="<?php echo esc_attr($item->id); ?>" class="item-checkbox">
                                    </td>
                                    <td><code><?php echo esc_html($item->id); ?></code></td>
                                    <td><code class="game-id"><?php echo esc_html($item->game_id); ?></code></td>
                                    <td class="item-info">
                                        <div class="item-preview">
                                            <?php if ($item->image_url): ?>
                                                <img src="<?php echo esc_url($item->image_url); ?>" alt="<?php echo esc_attr($item->title); ?>" class="item-image">
                                            <?php else: ?>
                                                <div class="item-image-placeholder">
                                                    <span class="dashicons dashicons-format-image"></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="item-details">
                                                <strong class="item-title"><?php echo esc_html($item->title); ?></strong>
                                                <?php if ($item->description): ?>
                                                    <p class="item-description"><?php echo esc_html(substr($item->description, 0, 100)); ?><?php if (strlen($item->description) > 100): ?>...<?php endif; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="item-price">
                                        <?php if ($item->sale_price && $item->sale_price < $item->price): ?>
                                            <div class="price-with-sale">
                                                <span class="original-price"><?php echo number_format($item->price, 2); ?> ₽</span>
                                                <span class="sale-price"><?php echo number_format($item->sale_price, 2); ?> ₽</span>
                                                <span class="discount-badge">-<?php echo round((($item->price - $item->sale_price) / $item->price) * 100); ?>%</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="price"><?php echo number_format($item->price, 2); ?> ₽</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo GSC_View_Shared_Common::render_status_badge($item->status); ?>
                                    </td>
                                    <td class="item-date">
                                        <?php echo GSC_View_Shared_Common::format_date($item->created_at, 'd.m.Y'); ?>
                                    </td>
                                    <td class="item-actions">
                                        <div class="action-buttons">
                                            <a href="<?php echo admin_url('admin.php?page=gsc-donate-items&action=edit&id=' . $item->id); ?>" 
                                               class="button button-small" title="Редактировать">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                            
                                            <button type="button" class="button button-small toggle-status" 
                                                    data-item-id="<?php echo esc_attr($item->id); ?>"
                                                    data-current-status="<?php echo esc_attr($item->status); ?>"
                                                    title="<?php echo $item->status === 'active' ? 'Деактивировать' : 'Активировать'; ?>">
                                                <?php if ($item->status === 'active'): ?>
                                                    <span class="dashicons dashicons-hidden"></span>
                                                <?php else: ?>
                                                    <span class="dashicons dashicons-visibility"></span>
                                                <?php endif; ?>
                                            </button>
                                            
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=gsc-donate-items&action=delete&id=' . $item->id), 'delete_item_' . $item->id); ?>" 
                                               class="button button-small button-delete delete-item"
                                               data-item-name="<?php echo esc_attr($item->title); ?>"
                                               title="Удалить">
                                                <span class="dashicons dashicons-trash"></span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($data['total_pages'] > 1): ?>
                    <div class="gsc-pagination">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo $data['total_items']; ?> предметов</span>
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
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="gsc-import-export">
                <h3>Импорт/Экспорт</h3>
                <div class="import-export-actions">
                    <button type="button" class="button export-all" data-export-type="all">
                        <span class="dashicons dashicons-download"></span> Экспорт всех предметов
                    </button>
                    
                    <div class="import-section">
                        <input type="file" class="import-file" accept=".json" id="import-file">
                        <label for="import-file" class="button">
                            <span class="dashicons dashicons-upload"></span> Импорт из JSON
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
        gsc_view_end();
    }
}
?>
