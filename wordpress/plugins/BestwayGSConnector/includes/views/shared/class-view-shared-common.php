<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Shared_Common {
    
    public static function render_status_badge($status, $with_label = false) {
        $statuses = [
            'active' => ['label' => 'Активен', 'color' => '#28a745'],
            'inactive' => ['label' => 'Неактивен', 'color' => '#6c757d'],
            'archived' => ['label' => 'В архиве', 'color' => '#343a40'],
            'pending' => ['label' => 'В ожидании', 'color' => '#ffc107'],
            'completed' => ['label' => 'Завершен', 'color' => '#28a745'],
            'failed' => ['label' => 'Ошибка', 'color' => '#dc3545'],
            'refunded' => ['label' => 'Возврат', 'color' => '#17a2b8']
        ];
        
        if (!isset($statuses[$status])) {
            return '';
        }
        
        $badge = '<span class="status-badge" style="background: ' . $statuses[$status]['color'] . '; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px;">';
        $badge .= $statuses[$status]['label'];
        $badge .= '</span>';
        
        return $badge;
    }
    
    public static function format_date($date, $format = 'd.m.Y H:i') {
        if (empty($date)) {
            return '';
        }
        
        return date_i18n($format, strtotime($date));
    }
    
    public static function format_price($price) {
        return number_format($price, 2, '.', ' ') . ' руб.';
    }
}
