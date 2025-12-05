<?php
if (!defined('ABSPATH')) exit;

class GSC_View_Admin_Tab_Contacts {
    
    public static function render() {
        gsc_view_start(__CLASS__);
        ?>
        <div class="wrap">
            <div class="card">
                <h2>Контакты и поддержка</h2>
                <p>Связаться с автором плагина для получения поддержки, предложений или сотрудничества.</p>
            </div>
            
            <div class="card">
                <h3>Контактная информация</h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <span class="dashicons dashicons-email" style="color: #0073aa;"></span>
                            Email
                        </th>
                        <td>
                            <a href="mailto:romanwebdev93@gmail.com" class="contact-link">
                                romanwebdev93@gmail.com
                            </a>
                            <p class="description">Основной email для связи по вопросам плагина</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <span class="dashicons dashicons-format-chat" style="color: #0088cc;"></span>
                            Telegram
                        </th>
                        <td>
                            <a href="https://t.me/boontar_mini" target="_blank" class="contact-link">
                                @boontar_mini
                            </a>
                            <p class="description">Быстрая связь через Telegram</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <span class="dashicons dashicons-admin-tools" style="color: #28a745;"></span>
                            GitHub
                        </th>
                        <td>
                            <a href="https://github.com/dartanianfactory/Bestway_Forms_Plugin_Wordpress" target="_blank" class="contact-link">
                                GitHub Repository
                            </a>
                            <p class="description">Исходный код, issues и contributions</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h3>Поддержка проекта</h3>
                <p>Если плагин помог вашему проекту, рассмотрите возможность поддержать его развитие:</p>
                
                <div class="donation-section">
                    <div class="donation-methods">
                        <div class="donation-method">
                            <h4>💳 Банковская карта</h4>
                            <div class="card-number">
                                <code id="card-number">2203 8303 1875 8787</code>
                                <button type="button" class="button button-small copy-btn" data-clipboard-target="#card-number">
                                    Копировать
                                </button>
                            </div>
                            <p class="description">Номер карты для переводов</p>
                        </div>
                        
                        <div class="donation-method">
                            <h4>🤝 Коммерческая поддержка</h4>
                            <p>Нужна кастомизация плагина под ваши задачи? Готов реализовать дополнительные функции и интеграции.</p>
                            <a href="mailto:romanwebdev93@gmail.com?subject=Кастомизация GSConnector" class="button button-primary">
                                Обсудить проект
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h3>Другие проекты</h3>
                <div class="projects-list">
                    <div class="project-item">
                        <h4>🚀 Bestway Forms</h4>
                        <p>Продвинутая система управления формами с интеграциями n8n, AI Manager и WooCommerce.</p>
                        <ul class="project-features">
                            <li>📧 Умные формы с шаблонами</li>
                            <li>🔗 Интеграция с n8n для автоматизации</li>
                            <li>🤖 AI-анализ лидов</li>
                            <li>🛒 Сбор заказов WooCommerce</li>
                            <li>📊 Дашборд и аналитика</li>
                        </ul>
                        <a href="https://github.com/dartanianfactory/Bestway_Forms_Plugin_Wordpress" class="button button-primary">
                            Узнать больше
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h3>О плагине GSConnector</h3>
                <div class="about-plugin">
                    <p><strong>GSConnector</strong> - это мощный плагин для интеграции WordPress с игровыми серверами через базу данных.</p>
                    
                    <div class="features-list">
                        <h4>Основные возможности:</h4>
                        <ul>
                            <li>🎮 Синхронизация регистраций с игровым сервером</li>
                            <li>💰 Полноценная система магазина с платежными системами</li>
                            <li>🔄 Гибкая синхронизация таблиц и полей БД</li>
                            <li>📦 Управление предметами и инвентарем</li>
                            <li>🔒 Безопасное хеширование паролей</li>
                            <li>📊 Детальная статистика платежей</li>
                            <li>⚙️ Настраиваемые вебхуки и интеграции</li>
                        </ul>
                    </div>
                    
                    <div class="version-info">
                        <p><strong>Версия:</strong> <?php echo esc_html(GSC_VERSION); ?></p>
                        <p><strong>Разработчик:</strong> Roman Agafonov</p>
                        <p><strong>Лицензия:</strong> GPL v2 or later</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        gsc_view_end();
    }
}
?>
