(function($) {
    'use strict';

    const GSCDonateItems = {
        init: function() {
            this.bindEvents();
            this.initSearch();
        },

        bindEvents: function() {
            // Выбор всех элементов
            $(document).on('change', '#select-all', this.toggleSelectAll.bind(this));
            
            // Массовые действия
            $(document).on('click', '#apply-bulk-action', this.handleBulkAction.bind(this));
            
            // Переключение статуса
            $(document).on('click', '.toggle-status', this.toggleItemStatus.bind(this));
            
            // Удаление
            $(document).on('click', '.delete-item', this.confirmDelete.bind(this));
            
            // Экспорт
            $(document).on('click', '.export-all', this.exportItems.bind(this));
            
            // Импорт
            $(document).on('change', '#import-file', this.handleImport.bind(this));
            
            // Поиск
            $(document).on('input', '#search', this.debouncedSearch.bind(this));
        },

        initSearch: function() {
            const $search = $('#search');
            if ($search.val()) {
                $search.addClass('has-value');
            }
        },

        toggleSelectAll: function(e) {
            const isChecked = $(e.target).is(':checked');
            $('.item-checkbox').prop('checked', isChecked);
        },

        handleBulkAction: function() {
            const action = $('#bulk-action-selector').val();
            const selectedItems = $('.item-checkbox:checked');
            
            if (!action) {
                this.showNotice('Выберите действие из списка', 'warning');
                return;
            }
            
            if (selectedItems.length === 0) {
                this.showNotice('Выберите хотя бы один элемент', 'warning');
                return;
            }
            
            const itemIds = [];
            selectedItems.each(function() {
                itemIds.push($(this).val());
            });
            
            const confirmMessages = {
                'delete': 'Вы уверены, что хотите удалить выбранные элементы?',
                'activate': 'Активировать выбранные элементы?',
                'deactivate': 'Деактивировать выбранные элементы?',
                'archive': 'Переместить выбранные элементы в архив?'
            };
            
            if (!confirm(confirmMessages[action] || 'Выполнить действие?')) {
                return;
            }
            
            const $button = $('#apply-bulk-action');
            const originalText = $button.text();
            $button.prop('disabled', true).html('<span class="spinner is-active"></span>');
            
            $.post(gsc_donate_items_data.ajax_url, {
                action: 'gsc_bulk_action_items',
                nonce: gsc_donate_items_data.nonce,
                bulk_action: action,
                item_ids: itemIds
            }, (response) => {
                if (response.success) {
                    this.showNotice('Действие успешно выполнено', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showNotice('Ошибка: ' + response.data, 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            }).fail(() => {
                this.showNotice('Ошибка при выполнении действия', 'error');
                $button.prop('disabled', false).text(originalText);
            });
        },

        toggleItemStatus: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const itemId = $button.data('item-id');
            const currentStatus = $button.data('current-status');
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (!confirm(`Изменить статус предмета?`)) {
                return;
            }
            
            $button.prop('disabled', true).html('<span class="spinner is-active"></span>');
            
            $.post(gsc_donate_items_data.ajax_url, {
                action: 'gsc_toggle_item_status',
                nonce: gsc_donate_items_data.nonce,
                item_id: itemId,
                status: newStatus
            }, (response) => {
                if (response.success) {
                    this.showNotice('Статус предмета изменен', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    this.showNotice('Ошибка: ' + response.data, 'error');
                    $button.prop('disabled', false).html(
                        currentStatus === 'active' ? 
                        '<span class="dashicons dashicons-hidden"></span>' : 
                        '<span class="dashicons dashicons-visibility"></span>'
                    );
                }
            }).fail(() => {
                this.showNotice('Ошибка при изменении статуса', 'error');
                $button.prop('disabled', false).html(
                    currentStatus === 'active' ? 
                    '<span class="dashicons dashicons-hidden"></span>' : 
                    '<span class="dashicons dashicons-visibility"></span>'
                );
            });
        },

        confirmDelete: function(e) {
            const $link = $(e.currentTarget);
            const itemName = $link.data('item-name');
            
            if (!confirm(`Удалить предмет "${itemName}"?`)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        },

        exportItems: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const exportType = $button.data('export-type') || 'all';
            
            let url;
            if (exportType === 'all') {
                url = gsc_donate_items_data.ajax_url + '?action=gsc_export_donate_items&nonce=' + 
                      gsc_donate_items_data.nonce + '&export_all=1';
            } else {
                const selectedItems = $('.item-checkbox:checked');
                if (selectedItems.length === 0) {
                    this.showNotice('Выберите элементы для экспорта', 'warning');
                    return;
                }
                
                const itemIds = [];
                selectedItems.each(function() {
                    itemIds.push($(this).val());
                });
                
                url = gsc_donate_items_data.ajax_url + '?action=gsc_export_donate_items&nonce=' + 
                      gsc_donate_items_data.nonce + '&ids=' + itemIds.join(',');
            }
            
            window.location.href = url;
        },

        handleImport: function(e) {
            const file = e.target.files[0];
            
            if (!file) {
                return;
            }

            if (!file.name.endsWith('.json')) {
                this.showNotice('Выберите JSON файл', 'error');
                $(e.target).val('');
                return;
            }

            if (!confirm('Импортировать предметы из файла?')) {
                $(e.target).val('');
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                try {
                    const data = JSON.parse(event.target.result);
                    
                    $.post(gsc_donate_items_data.ajax_url, {
                        action: 'gsc_import_donate_items',
                        nonce: gsc_donate_items_data.nonce,
                        data: data
                    }, (response) => {
                        if (response.success) {
                            this.showNotice(
                                `Импортировано ${response.data.imported} предметов`, 
                                'success'
                            );
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            this.showNotice('Ошибка импорта: ' + response.data, 'error');
                        }
                    }).fail(() => {
                        this.showNotice('Ошибка при импорте', 'error');
                    });
                } catch (error) {
                    this.showNotice('Ошибка чтения файла', 'error');
                }
            };
            
            reader.readAsText(file);
        },

        debouncedSearch: function(e) {
            const searchTerm = $(e.target).val().trim();
            const params = new URLSearchParams(window.location.search);
            
            if (searchTerm) {
                params.set('s', searchTerm);
            } else {
                params.delete('s');
            }
            
            params.delete('paged');
            
            // Используем debounce для поиска
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                window.location.href = window.location.pathname + '?' + params.toString();
            }, 800);
        },

        showNotice: function(message, type = 'info') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.wrap').prepend($notice);
            
            setTimeout(() => {
                $notice.fadeOut(300, () => $notice.remove());
            }, 5000);
        }
    };

    $(document).ready(function() {
        GSCDonateItems.init();
    });

})(jQuery);
