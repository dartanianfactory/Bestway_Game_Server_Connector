<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Page_Edit_Item {
    
    public static function render($data = []) {
        gsc_view_start(__CLASS__);
        
        $defaults = [
            'item' => null,
            'action' => 'add',
            'item_id' => 0,
            'errors' => []
        ];
        $data = wp_parse_args($data, $defaults);
        
        $title = $data['action'] === 'add' ? 'Добавить предмет' : 'Редактировать предмет';
        $nonce_action = $data['action'] === 'add' ? 'gsc_add_donate_item' : 'gsc_edit_donate_item_' . $data['item_id'];
        ?>
        <div class="wrap">
            <div class="gsc-edit-header">
                <h1><?php echo $title; ?></h1>
                <a href="<?php echo admin_url('admin.php?page=gsc-donate-items'); ?>" class="button">
                    <span class="dashicons dashicons-arrow-left-alt"></span> Назад к списку
                </a>
            </div>
            
            <?php if (!empty($data['errors'])): ?>
                <div class="notice notice-error">
                    <?php foreach ($data['errors'] as $error): ?>
                        <p><?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo admin_url('admin.php?page=gsc-donate-items'); ?>" enctype="multipart/form-data" class="gsc-edit-form">
                <?php wp_nonce_field($nonce_action); ?>
                <input type="hidden" name="action" value="<?php echo $data['action']; ?>">
                <input type="hidden" name="item_id" value="<?php echo $data['item_id']; ?>">
                
                <div class="gsc-form-grid">
                    <!-- Левая колонка - основная информация -->
                    <div class="form-column main-info">
                        <div class="form-section">
                            <h3>Основная информация</h3>
                            
                            <div class="form-group required">
                                <label for="game_id">Game ID *</label>
                                <input type="text" id="game_id" name="game_id" 
                                       value="<?php echo $data['item'] ? esc_attr($data['item']->game_id) : ''; ?>" 
                                       class="regular-text" required placeholder="Например: item_sword_001">
                                <p class="description">Уникальный идентификатор предмета в игровой базе</p>
                            </div>
                            
                            <div class="form-group required">
                                <label for="title">Название предмета *</label>
                                <input type="text" id="title" name="title" 
                                       value="<?php echo $data['item'] ? esc_attr($data['item']->title) : ''; ?>" 
                                       class="regular-text" required placeholder="Меч огня">
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" rows="4" class="large-text" 
                                          placeholder="Описание предмета, которое увидят игроки"><?php 
                                    echo $data['item'] ? esc_textarea($data['item']->description) : ''; 
                                ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Изображение предмета</h3>
                            <div class="image-uploader">
                                <div class="image-preview">
                                    <?php if ($data['item'] && $data['item']->image_url): ?>
                                        <img src="<?php echo esc_url($data['item']->image_url); ?>" 
                                             alt="<?php echo esc_attr($data['item']->title); ?>">
                                        <button type="button" class="button remove-image">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    <?php else: ?>
                                        <div class="no-image">
                                            <span class="dashicons dashicons-format-image"></span>
                                            <p>Изображение не выбрано</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="image-controls">
                                    <input type="hidden" id="image_url" name="image_url" 
                                           value="<?php echo $data['item'] ? esc_url($data['item']->image_url) : ''; ?>">
                                    
                                    <button type="button" class="button button-primary select-image">
                                        <span class="dashicons dashicons-admin-media"></span> Выбрать изображение
                                    </button>
                                    
                                    <div class="file-upload">
                                        <label for="image_upload" class="button">
                                            <span class="dashicons dashicons-upload"></span> Загрузить файл
                                        </label>
                                        <input type="file" id="image_upload" name="image_upload" accept="image/*" style="display: none;">
                                    </div>
                                    
                                    <p class="description">Рекомендуемый размер: 400x400px. Форматы: JPG, PNG, GIF</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Правая колонка - настройки -->
                    <div class="form-column settings">
                        <div class="form-section">
                            <h3>Цена и скидки</h3>
                            
                            <div class="form-group required">
                                <label for="price">Базовая цена (руб.) *</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" 
                                       value="<?php echo $data['item'] ? number_format($data['item']->price, 2, '.', '') : '0.00'; ?>" 
                                       class="regular-text" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="sale_price">Цена со скидкой</label>
                                <input type="number" id="sale_price" name="sale_price" step="0.01" min="0" 
                                       value="<?php echo $data['item'] && $data['item']->sale_price ? number_format($data['item']->sale_price, 2, '.', '') : ''; ?>" 
                                       class="regular-text" placeholder="Оставьте пустым для отключения">
                            </div>
                            
                            <div class="sale-dates">
                                <div class="form-group">
                                    <label for="start_sale_at">Начало скидки</label>
                                    <input type="datetime-local" id="start_sale_at" name="start_sale_at" 
                                           value="<?php echo $data['item'] && $data['item']->start_sale_at ? date('Y-m-d\TH:i', strtotime($data['item']->start_sale_at)) : ''; ?>" 
                                           class="regular-text">
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_sale_at">Конец скидки</label>
                                    <input type="datetime-local" id="end_sale_at" name="end_sale_at" 
                                           value="<?php echo $data['item'] && $data['item']->end_sale_at ? date('Y-m-d\TH:i', strtotime($data['item']->end_sale_at)) : ''; ?>" 
                                           class="regular-text">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Настройки видимости</h3>
                            
                            <div class="form-group">
                                <label for="status">Статус</label>
                                <select id="status" name="status" class="status-selector">
                                    <option value="active" <?php selected($data['item'] ? $data['item']->status : 'active', 'active'); ?>>✅ Активен</option>
                                    <option value="inactive" <?php selected($data['item'] ? $data['item']->status : 'active', 'inactive'); ?>>⏸️ Неактивен</option>
                                    <option value="archived" <?php selected($data['item'] ? $data['item']->status : 'active', 'archived'); ?>>📁 В архиве</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="sort_order">Порядок сортировки</label>
                                <input type="number" id="sort_order" name="sort_order" 
                                       value="<?php echo $data['item'] ? intval($data['item']->sort_order) : 0; ?>" 
                                       class="small-text" min="0">
                                <p class="description">Чем меньше число, тем выше в списке</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary button-large">
                        <?php echo $data['action'] === 'add' ? 'Добавить предмет' : 'Сохранить изменения'; ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=gsc-donate-items'); ?>" class="button button-large">Отмена</a>
                </div>
            </form>
        </div>
        <?php
        gsc_view_end();
    }
}
?>
