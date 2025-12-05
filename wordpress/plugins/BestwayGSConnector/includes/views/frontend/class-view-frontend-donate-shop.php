<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Frontend_Donate_Shop {
    
    public static function render_shop($data) {
        ob_start();
        ?>
        <div class="gsc-donate-shop">
            <h2>магазин</h2>
            
            <?php if (empty($data['items'])): ?>
                <p>Товаров пока нет.</p>
            <?php else: ?>
                <div class="gsc-items-grid">
                    <?php foreach ($data['items'] as $item): ?>
                        <div class="gsc-item-card">
                            <?php if ($item->image_url): ?>
                                <div class="gsc-item-image">
                                    <img src="<?php echo esc_url($item->image_url); ?>" alt="<?php echo esc_attr($item->title); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="gsc-item-content">
                                <h3 class="gsc-item-title"><?php echo esc_html($item->title); ?></h3>
                                
                                <?php if ($item->description): ?>
                                    <p class="gsc-item-description"><?php echo esc_html($item->description); ?></p>
                                <?php endif; ?>
                                
                                <div class="gsc-item-price">
                                    <?php if ($item->sale_price && $item->sale_price < $item->price): ?>
                                        <span class="gsc-item-price-old"><?php echo number_format($item->price, 2); ?> руб.</span>
                                        <span class="gsc-item-price-new"><?php echo number_format($item->sale_price, 2); ?> руб.</span>
                                    <?php else: ?>
                                        <span class="gsc-item-price-normal"><?php echo number_format($item->price, 2); ?> руб.</span>
                                    <?php endif; ?>
                                </div>
                                
                                <button class="gsc-buy-btn button button-primary" 
                                        data-item-id="<?php echo $item->id; ?>"
                                        data-item-title="<?php echo esc_attr($item->title); ?>"
                                        data-item-price="<?php echo $item->sale_price && $item->sale_price < $item->price ? $item->sale_price : $item->price; ?>">
                                    Купить
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
