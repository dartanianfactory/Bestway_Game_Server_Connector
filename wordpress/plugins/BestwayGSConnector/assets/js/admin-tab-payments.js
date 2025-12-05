(function($) {
    'use strict';

    const GSCPaymentsTab = {
        init: function() {
            this.bindEvents();
            this.initPaymentToggle();
        },

        bindEvents: function() {
            $(document).on('change', '#gsc_payments_enabled', this.togglePaymentSettings.bind(this));
            $(document).on('click', '#test-payment-connection', this.testPaymentConnection.bind(this));
        },

        initPaymentToggle: function() {
            this.togglePaymentSettings();
        },

        togglePaymentSettings: function() {
            const enabled = $('#gsc_payments_enabled').is(':checked');
            $('#payments_settings_row').toggle(enabled);
            $('#payments_offline_row').toggle(!enabled);
        },

        testPaymentConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#test-payment-result');
            
            $button.prop('disabled', true).text('Проверка...');
            $result.html('<span class="spinner is-active"></span>');
            
            $.post(gsc_payments_data.ajax_url, {
                action: 'gsc_test_payment_connection',
                nonce: gsc_payments_data.nonce
            }, function(response) {
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            }).always(function() {
                $button.prop('disabled', false).text('Проверить подключение');
            });
        }
    };

    $(document).ready(function() {
        GSCPaymentsTab.init();
    });

})(jQuery);
