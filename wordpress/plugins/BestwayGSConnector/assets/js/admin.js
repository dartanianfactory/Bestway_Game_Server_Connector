(function($) {
    'use strict';

    window.GSCAdmin = {
        init: function() {
            this.bindEvents();
            this.initDatePickers();
            this.initImageUpload();
        },

        bindEvents: function() {
            $(document).on('click', '.gsc-delete-item, .delete', function(e) {
                const $button = $(this);
                if ($button.hasClass('delete') || $button.hasClass('gsc-delete-item')) {
                    if (!confirm(gsc_admin_data.texts.confirm_delete)) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                }
            });

            $(document).on('click', '.gsc-upload-image', this.uploadImage.bind(this));

            $(document).on('click', '.gsc-remove-image', this.removeImage.bind(this));

            $(document).on('click', '.gsc-export-btn', this.exportData.bind(this));
            $(document).on('change', '.gsc-import-file', this.importData.bind(this));
        },

        initDatePickers: function() {
            if (typeof $.fn.datepicker === 'undefined') {
                console.warn('jQuery UI Datepicker не загружен. Используем нативные элементы.');
                
                $('.gsc-datepicker').each(function() {
                    const $input = $(this);
                    const currentValue = $input.val();
                    
                    $input.attr('type', 'date');
                    $input.addClass('gsc-native-datepicker');
                    
                    if (currentValue) {
                        $input.val(currentValue);
                    }
                });
                return;
            }
            
            $('.gsc-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                showButtonPanel: true,
                changeMonth: true,
                changeYear: true,
                yearRange: '-10:+10',
                beforeShow: function(input, inst) {
                    $(inst.dpDiv).addClass('gsc-datepicker-wrapper');
                }
            });
        },

        initImageUpload: function() {
            if (typeof wp !== 'undefined' && wp.media) {
                // Медиа загрузчик уже доступен
            }
        },

        uploadImage: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $input = $button.siblings('input[type="text"], input[type="url"]');
            const $preview = $button.siblings('.gsc-image-preview');
            
            if (typeof wp === 'undefined' || !wp.media) {
                alert('Медиа библиотека WordPress недоступна');
                return;
            }

            const frame = wp.media({
                title: 'Выберите изображение',
                button: {
                    text: 'Выбрать'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                
                if ($preview.length) {
                    $preview.html('<img src="' + attachment.url + '" style="max-width: 150px; max-height: 150px;">');
                    $preview.show();
                }
            });

            frame.open();
        },

        removeImage: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $input = $button.siblings('input[type="text"], input[type="url"]');
            const $preview = $button.siblings('.gsc-image-preview');
            
            $input.val('');
            if ($preview.length) {
                $preview.hide().html('');
            }
        },

        exportData: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const exportType = $button.data('export-type') || 'donate_items';
            const nonce = gsc_admin_data.nonce;
            
            $button.prop('disabled', true).text(gsc_admin_data.texts.loading);
            
            $.post(gsc_admin_data.ajax_url, {
                action: 'gsc_export_data',
                nonce: nonce,
                type: exportType
            }, function(response) {
                if (response.success) {
                    const blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'gsc_' + exportType + '_' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    alert(gsc_admin_data.texts.success);
                } else {
                    alert(gsc_admin_data.texts.error + ': ' + response.data);
                }
            }).fail(function() {
                alert(gsc_admin_data.texts.error);
            }).always(function() {
                $button.prop('disabled', false).text('Экспорт');
            });
        },

        importData: function(e) {
            const $input = $(e.target);
            const file = $input[0].files[0];
            
            if (!file) {
                return;
            }

            if (!file.name.endsWith('.json')) {
                alert('Пожалуйста, выберите JSON файл');
                $input.val('');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                try {
                    const data = JSON.parse(event.target.result);
                    const importType = $input.data('import-type') || 'donate_items';
                    const nonce = gsc_admin_data.nonce;
                    
                    if (!confirm('Вы уверены, что хотите импортировать данные? Это может перезаписать существующие записи.')) {
                        $input.val('');
                        return;
                    }
                    
                    $.post(gsc_admin_data.ajax_url, {
                        action: 'gsc_import_data',
                        nonce: nonce,
                        type: importType,
                        data: data
                    }, function(response) {
                        if (response.success) {
                            alert('Данные успешно импортированы!');
                            window.location.reload();
                        } else {
                            alert('Ошибка импорта: ' + response.data);
                        }
                    }).fail(function() {
                        alert('Ошибка при импорте данных');
                    });
                } catch (error) {
                    alert('Ошибка чтения файла: ' + error.message);
                }
            };
            
            reader.readAsText(file);
        },

        showSpinner: function($element) {
            $element.append('<span class="spinner is-active" style="float: none; margin-left: 5px;"></span>');
        },

        hideSpinner: function($element) {
            $element.find('.spinner').remove();
        },

        showMessage: function(type, message, $container) {
            const $message = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $container.prepend($message);
            
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        formatPrice: function(price) {
            return parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' руб.';
        },

        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    };

    $(document).ready(function() {
        GSCAdmin.init();
        
        if (typeof $.fn.tooltip !== 'undefined') {
            $('.gsc-tooltip').tooltip({
                content: function() {
                    return $(this).attr('title');
                },
                show: {
                    effect: "fadeIn",
                    duration: 200
                },
                hide: {
                    effect: "fadeOut",
                    duration: 200
                }
            });
        } else {
            console.warn('jQuery UI Tooltip не загружен');
        }
        
        $('.gsc-tabs .tab-button').on('click', function(e) {
            e.preventDefault();
            const tabId = $(this).data('tab');
            $('.gsc-tabs .tab-button').removeClass('active');
            $(this).addClass('active');
            $('.gsc-tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
        });
    });

})(jQuery);
