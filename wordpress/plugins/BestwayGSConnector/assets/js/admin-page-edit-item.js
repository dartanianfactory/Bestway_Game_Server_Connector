(function($) {
    'use strict';

    const GSCEditItem = {
        init: function() {
            this.bindEvents();
            this.initFormValidation();
            this.initMediaUploader();
        },

        bindEvents: function() {
            // Выбор изображения
            $(document).on('click', '.select-image', this.openMediaLibrary.bind(this));
            $(document).on('click', '.remove-image', this.removeImage.bind(this));
            $(document).on('change', '#image_upload', this.handleFileUpload.bind(this));
            
            // Валидация цены
            $(document).on('change', '#sale_price', this.validateSalePrice.bind(this));
            
            // Валидация дат скидок
            $(document).on('change', '#start_sale_at, #end_sale_at', this.validateSaleDates.bind(this));
            
            // Отправка формы
            $(document).on('submit', '.gsc-edit-form', this.validateForm.bind(this));
        },

        initFormValidation: function() {
            // Валидация числовых полей
            $('#price, #sale_price').on('blur', function() {
                let value = $(this).val();
                if (value && (isNaN(value) || parseFloat(value) < 0)) {
                    $(this).val('0.00');
                    alert('Цена должна быть положительным числом');
                }
            });
        },

        initMediaUploader: function() {
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                console.warn('WordPress медиабиблиотека недоступна');
                return;
            }
        },

        openMediaLibrary: function(e) {
            e.preventDefault();
            
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Медиабиблиотека не загружена. Обновите страницу.');
                return;
            }
            
            const frame = wp.media({
                title: 'Выберите изображение',
                button: { text: 'Выбрать' },
                multiple: false,
                library: { type: 'image' }
            });
            
            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                this.setImage(attachment.url);
            });
            
            frame.open();
        },

        setImage: function(url) {
            $('#image_url').val(url);
            
            $('.image-preview').html(`
                <img src="${url}" alt="Изображение предмета">
                <button type="button" class="button remove-image">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            `);
        },

        removeImage: function(e) {
            e.preventDefault();
            
            $('#image_url').val('');
            $('.image-preview').html(`
                <div class="no-image">
                    <span class="dashicons dashicons-format-image"></span>
                    <p>Изображение не выбрано</p>
                </div>
            `);
        },

        handleFileUpload: function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Проверка размера
            if (file.size > 2 * 1024 * 1024) {
                alert('Файл слишком большой. Максимум 2MB.');
                $(e.target).val('');
                return;
            }
            
            // Проверка формата
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            if (!validTypes.includes(file.type)) {
                alert('Допустимые форматы: JPG, PNG, GIF');
                $(e.target).val('');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (event) => {
                this.setImage(event.target.result);
            };
            reader.readAsDataURL(file);
        },

        validateSalePrice: function() {
            const price = parseFloat($('#price').val()) || 0;
            const salePrice = parseFloat($('#sale_price').val()) || 0;
            
            if (salePrice > 0 && salePrice >= price) {
                alert('Цена со скидкой должна быть меньше обычной цены');
                $('#sale_price').val('');
                $('#sale_price').focus();
            }
        },

        validateSaleDates: function() {
            const startDate = $('#start_sale_at').val();
            const endDate = $('#end_sale_at').val();
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (start >= end) {
                    alert('Дата окончания должна быть позже даты начала');
                    $('#end_sale_at').val('');
                }
            }
        },

        validateForm: function(e) {
            const gameId = $('#game_id').val().trim();
            const title = $('#title').val().trim();
            const price = $('#price').val();
            
            let errors = [];
            
            if (!gameId) errors.push('• Введите Game ID');
            if (!title) errors.push('• Введите название предмета');
            if (!price || parseFloat(price) <= 0) errors.push('• Укажите корректную цену');
            
            const salePrice = $('#sale_price').val();
            if (salePrice && parseFloat(salePrice) >= parseFloat(price)) {
                errors.push('• Цена со скидкой должна быть меньше обычной цены');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Исправьте ошибки:\n\n' + errors.join('\n'));
                return false;
            }
            
            return true;
        }
    };

    $(document).ready(function() {
        GSCEditItem.init();
    });

})(jQuery);
