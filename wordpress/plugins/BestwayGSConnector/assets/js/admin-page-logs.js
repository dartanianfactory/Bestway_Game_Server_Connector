(function($) {
    'use strict';

    function debounce(func, wait) {
        let timeout;

        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    const GSCLogs = {
        init: function() {
            this.bindEvents();
            this.initFilters();
            this.initLogExpanding();
        },

        bindEvents: function() {
            $(document).on('click', '.view-log-details', this.showLogDetails.bind(this));
            $(document).on('click', '.expand-log-context', this.toggleLogContext.bind(this));
            $(document).on('click', '#clear-old-logs', this.clearOldLogs.bind(this));
            $(document).on('click', '.modal-close, #close-log-modal', this.closeModal.bind(this));
            $(document).on('click', '#log-context-modal', this.handleModalClick.bind(this));
            $(document).on('click', '#copy-log-details', this.copyLogDetails.bind(this));
            $(document).on('change', '#log-date, #log-limit, #log-type', this.applyFilters.bind(this));
            $(document).on('keyup', '#log-search', this.debouncedSearch.bind(this));

            $(document).on('click', '#export-logs', this.exportLogs.bind(this));
        },

        initFilters: function() {
            const savedFilters = localStorage.getItem('gsc_logs_filters');
            if (savedFilters) {
                try {
                    const filters = JSON.parse(savedFilters);
                    $.each(filters, function(key, value) {
                        $(`#${key}`).val(value);
                    });
                } catch (e) {
                    localStorage.removeItem('gsc_logs_filters');
                }
            }
        },

        initLogExpanding: function() {
            $('.log-message').each(function() {
                const $message = $(this);
                const rawText = $message.data('raw') || '';
                
                if (rawText.includes('[Context:')) {
                    const contextMatch = rawText.match(/\[Context: (.+?)\]/);
                    if (contextMatch && contextMatch[1]) {
                        try {
                            const context = JSON.parse(contextMatch[1]);
                            const contextHtml = '<div class="log-context" style="display: none;">' + 
                                '<strong>Контекст:</strong> ' + 
                                JSON.stringify(context, null, 2) + 
                                '</div>';
                            
                            $message.append(
                                '<button class="button-link expand-log-context" data-expanded="false">' +
                                '<span class="dashicons dashicons-arrow-down-alt2"></span> Показать контекст' +
                                '</button>' +
                                contextHtml
                            );
                        } catch (e) {
                            // Невалидный JSON, пропускаем
                        }
                    }
                }
            });
        },

        toggleLogContext: function(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.expand-log-context');
            const $context = $button.siblings('.log-context');
            const isExpanded = $button.data('expanded') === 'true';
            
            if (isExpanded) {
                $context.slideUp(200);
                $button.html('<span class="dashicons dashicons-arrow-down-alt2"></span> Показать контекст');
                $button.data('expanded', 'false');
            } else {
                $context.slideDown(200);
                $button.html('<span class="dashicons dashicons-arrow-up-alt2"></span> Скрыть контекст');
                $button.data('expanded', 'true');
            }
        },

        applyFilters: function() {
            const filters = {
                'log-date': $('#log-date').val(),
                'log-limit': $('#log-limit').val(),
                'log-type': $('#log-type').val(),
                'log-search': $('#log-search').val()
            };
            
            localStorage.setItem('gsc_logs_filters', JSON.stringify(filters));
            
            const params = new URLSearchParams(window.location.search);
            $.each(filters, function(key, value) {
                if (value) {
                    params.set(key.replace('log-', ''), value);
                } else {
                    params.delete(key.replace('log-', ''));
                }
            });
            
            window.location.href = window.location.pathname + '?' + params.toString();
        },

        debouncedSearch: debounce(function() {
            this.applyFilters();
        }, 500),

        showLogDetails: function(e) {
            e.preventDefault();
            
            const $button = $(e.target).closest('.view-log-details');
            const logId = $button.data('log-id');
            const logData = $button.data('log-data');
            
            let parsedData;
            try {
                parsedData = JSON.parse(logData);
            } catch (error) {
                parsedData = { raw: logData };
            }
            
            $('#detail-timestamp').text(parsedData.timestamp || '');
            $('#detail-type').html($button.closest('tr').find('.log-type-badge').clone());
            $('#detail-ip').text(parsedData.ip || '');
            $('#detail-user-id').html(parsedData.user_id ? 
                `<a href="${ajaxurl.replace('admin-ajax.php', 'user-edit.php?user_id=' + parsedData.user_id)}" target="_blank">${parsedData.user_id}</a>` : 
                '0');
            $('#detail-message').text(parsedData.message || '');

            if (parsedData.context) {
                $('#detail-context').html('<pre>' + JSON.stringify(parsedData.context, null, 2) + '</pre>');
            } else {
                $('#detail-context').html('<em>Нет контекста</em>');
            }

            $('#log-context-content').text(parsedData.raw || logData);
            
            $('#log-context-modal').show();
            $('body').addClass('modal-open');
        },

        copyLogDetails: function() {
            const text = $('#log-context-content').text();
            
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                document.execCommand('copy');
                this.showNotification('Лог скопирован в буфер обмена', 'success');
            } catch (err) {
                this.showNotification('Не удалось скопировать лог: ' + err, 'error');
            }
            
            $temp.remove();
        },

        closeModal: function() {
            $('#log-context-modal').hide();
            $('body').removeClass('modal-open');
        },

        handleModalClick: function(e) {
            if (e.target === e.currentTarget) {
                this.closeModal();
            }
        },

        clearOldLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Вы уверены, что хотите удалить логи старше 30 дней?')) {
                return;
            }
            
            const $button = $(e.target);
            $button.prop('disabled', true).html('<span class="spinner is-active"></span> Очистка...');
            
            $.post(ajaxurl, {
                action: 'gsc_clear_old_logs',
                nonce: gsc_logs_data.nonce
            }, (response) => {
                if (response.success) {
                    this.showNotification('Удалено ' + response.data.deleted + ' файлов логов', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showNotification('Ошибка: ' + response.data, 'error');
                    $button.prop('disabled', false).text('Очистить старые логи');
                }
            }).fail(() => {
                this.showNotification('Ошибка при очистке логов', 'error');
                $button.prop('disabled', false).text('Очистить старые логи');
            });
        },

        exportLogs: function() {
            const date = $('#log-date').val();
            const type = $('#log-type').val();
            
            $.post(ajaxurl, {
                action: 'gsc_export_logs',
                nonce: gsc_logs_data.nonce,
                date: date,
                type: type
            }, (response) => {
                if (response.success) {
                    const blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `gsc_logs_${date}_${type}_${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    this.showNotification('Логи успешно экспортированы', 'success');
                } else {
                    this.showNotification('Ошибка экспорта: ' + response.data, 'error');
                }
            });
        },

        showNotification: function(message, type = 'info') {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap').prepend($notice);

            setTimeout(() => {
                $notice.fadeOut(300, () => $notice.remove());
            }, 5000);
        }
    };

    $(document).ready(function() {
        GSCLogs.init();

        if ($.fn.tooltip) {
            $('[data-tooltip]').tooltip({
                content: function() {
                    return $(this).data('tooltip');
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
        }
    });

})(jQuery);
