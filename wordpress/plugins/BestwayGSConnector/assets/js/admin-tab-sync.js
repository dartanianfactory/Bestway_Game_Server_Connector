(function($) {
    'use strict';

    const GSCSync = {
        init: function() {
            this.bindEvents();
            this.initTabNavigation();
            this.loadAllServerTables();
        },

        bindEvents: function() {
            $(document).on('click', '#test-sync-connection', this.testSyncConnection.bind(this));
            $(document).on('change', 'select[data-type][data-target]', this.handleTableSelectChange);
            $(document).on('click', '.nav-tab', this.switchTab.bind(this));
        },

        initTabNavigation: function() {
            $('.nav-tab').each(function() {
                if ($(this).hasClass('nav-tab-active')) {
                    const tabId = $(this).data('tab');
                    $('#' + tabId).addClass('active');
                }
            });
        },

        switchTab: function(e) {
            const tabId = $(e.target).data('tab');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $('.gsc-tab-content').removeClass('active');
            
            $(e.target).addClass('nav-tab-active');
            $('#' + tabId).addClass('active');
        },

        loadAllServerTables: function() {
            $.post(gsc_sync_data.ajax_url, {
                action: 'gsc_get_server_tables',
                nonce: gsc_sync_data.nonce
            }, function(response) {
                if (response.success) {
                    ['server_user_table', 'server_items_table', 'server_inventory_table'].forEach(function(selectId) {
                        var select = $('#' + selectId);
                        if (select.length) {
                            var currentValue = select.val();
                            select.find('option:not(:first)').remove();
                            
                            response.data.tables.forEach(function(table) {
                                var selected = currentValue === table ? 'selected' : '';
                                select.append('<option value="' + table + '" ' + selected + '>' + table + '</option>');
                            });
                            
                            if (currentValue) {
                                GSCSync.loadTableColumns(select[0]);
                            }
                        }
                    });
                }
            });
        },

        handleTableSelectChange: function(e) {
            GSCSync.updateDependentSelects(e.target);
        },

        updateDependentSelects: function(tableSelect) {
            const tableId = tableSelect.id;
            const tableValue = tableSelect.value;
            
            const dependentSelects = $('[data-depends="' + tableId + '"]');
            
            if (!tableValue) {
                dependentSelects.each(function() {
                    $(this).html('<option value="">-- Не синхронизировать --</option>');
                });
                return;
            }
            
            GSCSync.loadTableColumns(tableSelect);
        },

        loadTableColumns: function(select) {
            const table = select.value;
            const type = select.getAttribute('data-type');
            const target = select.getAttribute('data-target');
            
            if (!table) {
                $('#' + type + '_' + target + '_columns').html('<p class="description">Выберите таблицу</p>');
                GSCSync.clearFieldSelects(type, target);
                return;
            }
            
            const columnsContainer = $('#' + type + '_' + target + '_columns');
            columnsContainer.html('<div class="spinner is-active" style="float: none; margin: 10px 0;"></div> Загрузка колонок...');
            
            $.post(gsc_sync_data.ajax_url, {
                action: 'gsc_get_table_columns',
                nonce: gsc_sync_data.nonce,
                table: table,
                type: type,
                target: target
            }, function(response) {
                if (response.success) {
                    columnsContainer.html(response.data.html);
                    
                    GSCSync.updateFieldSelects(type, target, response.data.columns);
                    GSCSync.saveColumnsToSettings(type, target, response.data.columns);
                } else {
                    columnsContainer.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                    GSCSync.clearFieldSelects(type, target);
                }
            }).fail(function() {
                columnsContainer.html('<div class="notice notice-error inline"><p>Ошибка загрузки колонок</p></div>');
                GSCSync.clearFieldSelects(type, target);
            });
        },

        clearFieldSelects: function(type, target) {
            const selects = $('[data-depends="' + type + '_' + target + '_table"]');
            selects.each(function() {
                const select = $(this);
                select.html('<option value="">-- Не синхронизировать --</option>');
            });
        },

        saveColumnsToSettings: function(type, target, columns) {
            const inputName = 'gsc_sync_settings[' + type + '_' + target + '_columns]';
            let existingInput = $('input[name="' + inputName + '"]');
            
            if (existingInput.length === 0) {
                $('<input type="hidden" name="' + inputName + '" value="">').appendTo('#gsc-sync-form');
                existingInput = $('input[name="' + inputName + '"]');
            }
            
            existingInput.val(JSON.stringify(columns));
        },

        updateFieldSelects: function(type, target, columns) {
            const selects = $('[data-depends="' + type + '_' + target + '_table"]');
            
            selects.each(function() {
                const select = $(this);
                const currentValue = select.val();
                const field = select.data('field');
                
                let options = '<option value="">-- Не синхронизировать --</option>';
                
                $.each(columns, function(index, column) {
                    const selected = currentValue === column ? 'selected' : '';
                    options += '<option value="' + column + '" ' + selected + '>' + column + '</option>';
                });
                
                select.html(options);
                
                if (currentValue === '' && field === 'item_id') {
                    const itemIdColumns = columns.filter(function(col) {
                        return col.toLowerCase().includes('item') || 
                               col.toLowerCase().includes('id') || 
                               col.toLowerCase().includes('game');
                    });
                    
                    if (itemIdColumns.length > 0) {
                        select.val(itemIdColumns[0]);
                    }
                }
            });
        },

        testSyncConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#test-sync-result');
            
            $button.prop('disabled', true).text('Проверка...');
            $result.html('<span class="spinner is-active"></span>');
            
            const formData = $('#gsc-sync-form').serialize();
            
            $.post(gsc_sync_data.ajax_url, {
                action: 'gsc_test_sync_connection',
                nonce: gsc_sync_data.nonce,
                form_data: formData
            }, function(response) {
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + responseresponse?.data?.message + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + response?.data?.message  + '</span>');
                }
            }).always(function() {
                $button.prop('disabled', false).text('Проверить синхронизацию');
            });
        }
    };

    $(document).ready(function() {
        GSCSync.init();

        $('select[data-type][data-target]').each(function() {
            if ($(this).val()) {
                GSCSync.updateDependentSelects(this);
            }
        });
    });

    window.gscLoadTableColumns = GSCSync.loadTableColumns;
    window.updateDependentSelects = GSCSync.updateDependentSelects;

})(jQuery);
