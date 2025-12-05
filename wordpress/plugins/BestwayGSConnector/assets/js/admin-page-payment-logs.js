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

    const GSCPaymentLogs = {
        init: function() {
            this.bindEvents();
            this.initStats();
            this.initFilters();
        },

        bindEvents: function() {
            $(document).on('click', '.view-payment-details', this.showPaymentDetails.bind(this));
            $(document).on('click', '#copy-payment-data', this.copyPaymentData.bind(this));
            $(document).on('click', '.modal-close, #close-payment-modal', this.closeModal.bind(this));
            $(document).on('click', '#payment-details-modal', this.handleModalClick.bind(this));
            $(document).on('keyup', '#search', this.debouncedSearch.bind(this));
            $(document).on('change', '#status, #payment_system, #date_from, #date_to', this.applyFilters.bind(this));
            $(document).on('click', '.button-success', this.confirmManualComplete.bind(this));
            $(document).on('click', '#clear-old-payments', this.clearOldPayments.bind(this));
            $(document).on('click', '#export-payments', this.exportPayments.bind(this));
        },

        initStats: function() {
            $.post(gsc_payment_logs_data.ajax_url, {
                action: 'gsc_get_payment_stats',
                nonce: gsc_payment_logs_data.nonce
            }, (response) => {
                if (response.success) {
                    this.updateStats(response.data);
                }
            });
        },

        initFilters: function() {
            const savedFilters = localStorage.getItem('gsc_payment_logs_filters');
            if (savedFilters) {
                try {
                    const filters = JSON.parse(savedFilters);
                    $.each(filters, (key, value) => {
                        $(`#${key}`).val(value);
                    });
                } catch (e) {
                    localStorage.removeItem('gsc_payment_logs_filters');
                }
            }
        },

        updateStats: function(stats) {
            $('#stat-total').text(stats.total || '0');
            $('#stat-completed').text(stats.completed || '0');
            $('#stat-pending').text(stats.pending || '0');
            $('#stat-failed').text(stats.failed || '0');
            $('#stat-refunded').text(stats.refunded || '0');
            $('#stat-total-amount').text(this.formatPrice(stats.total_amount || '0'));
        },

        showPaymentDetails: function(e) {
            e.preventDefault();
            
            const $button = $(e.target).closest('button');
            const paymentId = $button.data('payment-id');
            const paymentData = $button.data('payment-data');
            const userInfo = $button.data('user-info');
            const itemInfo = $button.data('item-info');
            const row = $button.closest('tr');

            let parsedData;
            try {
                parsedData = JSON.parse(paymentData);
            } catch (error) {
                parsedData = { raw: paymentData };
            }

            $('#payment-id').text(paymentId);
            $('#detail-user').html(userInfo);
            $('#detail-item').html(itemInfo);
            $('#detail-amount').html(row.find('.log-amount').html() || row.find('td:nth-child(4)').text());
            $('#detail-status').html(row.find('.status-badge').clone());
            $('#detail-date').text(row.find('.log-date').text() || row.find('td:nth-child(9)').text());
            $('#detail-ip').text(row.find('td:nth-child(8)').text());

            if (parsedData.user_agent) {
                $('#detail-user-agent').text(parsedData.user_agent);
            } else {
                $('#detail-user-agent').html('<em>Не указан</em>');
            }

            const paymentDataHtml = '<pre id="payment-data-content">' + JSON.stringify(parsedData, null, 2) + '</pre>';
            $('#detail-payment-data').html(paymentDataHtml);

            $('#payment-details-modal').show();
            $('body').addClass('modal-open');
        },

        copyPaymentData: function() {
            const text = $('#payment-data-content').text();
            
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                document.execCommand('copy');
                this.showNotification('Данные скопированы в буфер обмена', 'success');
            } catch (err) {
                this.showNotification('Не удалось скопировать данные: ' + err, 'error');
            }
            
            $temp.remove();
        },

        closeModal: function() {
            $('#payment-details-modal').hide();
            $('body').removeClass('modal-open');
        },

        handleModalClick: function(e) {
            if (e.target === e.currentTarget) {
                this.closeModal();
            }
        },

        debouncedSearch: debounce(function() {
            this.applyFilters();
        }, 500),

        applyFilters: function() {
            const filters = {
                'search': $('#search').val(),
                'status': $('#status').val(),
                'payment_system': $('#payment_system').val(),
                'date_from': $('#date_from').val(),
                'date_to': $('#date_to').val()
            };
            
            localStorage.setItem('gsc_payment_logs_filters', JSON.stringify(filters));
            
            const params = new URLSearchParams(window.location.search);
            $.each(filters, function(key, value) {
                if (value && value !== 'all') {
                    params.set(key, value);
                } else {
                    params.delete(key);
                }
            });
            
            params.delete('paged');
            window.location.href = window.location.pathname + '?' + params.toString();
        },

        confirmManualComplete: function(e) {
            e.preventDefault();
            
            const $link = $(e.target);
            if (!confirm('Вы уверены, что хотите отметить этот платеж как завершенный?')) {
                return false;
            }
            
            return true;
        },

        clearOldPayments: function(e) {
            e.preventDefault();
            
            if (!confirm('Вы уверены, что хотите удалить платежи старше 90 дней?')) {
                return;
            }
            
            const $button = $(e.target);
            $button.prop('disabled', true).html('<span class="spinner is-active"></span> Очистка...');
            
            $.post(gsc_payment_logs_data.ajax_url, {
                action: 'gsc_clear_old_payments',
                nonce: gsc_payment_logs_data.nonce
            }, (response) => {
                if (response.success) {
                    this.showNotification('Удалено ' + response.data.deleted + ' платежей', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showNotification('Ошибка: ' + response.data, 'error');
                    $button.prop('disabled', false).text('Очистить старые платежи');
                }
            }).fail(() => {
                this.showNotification('Ошибка при очистке платежей', 'error');
                $button.prop('disabled', false).text('Очистить старые платежи');
            });
        },

        exportPayments: function() {
            const dateFrom = $('#date_from').val();
            const dateTo = $('#date_to').val();
            const status = $('#status').val();
            
            $.post(gsc_payment_logs_data.ajax_url, {
                action: 'gsc_export_payments',
                nonce: gsc_payment_logs_data.nonce,
                date_from: dateFrom,
                date_to: dateTo,
                status: status
            }, (response) => {
                if (response.success) {
                    const blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `gsc_payments_${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    this.showNotification('Платежи успешно экспортированы', 'success');
                } else {
                    this.showNotification('Ошибка экспорта: ' + response.data, 'error');
                }
            });
        },

        formatPrice: function(price) {
            return parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' руб.';
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
        GSCPaymentLogs.init();
    });

})(jQuery);
