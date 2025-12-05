(function($) {
    'use strict';

    const GSCDBTab = {
        init: function() {
            this.bindEvents();
            this.initHashTypeToggle();
        },

        bindEvents: function() {
            $(document).on('click', '#generate-salt', this.generateSalt.bind(this));
            $(document).on('click', '#test-db-connection', this.testDBConnection.bind(this));
            $(document).on('change', '#gsc_password_hash_type', this.toggleCustomHashRow.bind(this));
        },

        initHashTypeToggle: function() {
            this.toggleCustomHashRow();
        },

        generateSalt: function(e) {
            e.preventDefault();
            
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
            let salt = '';
            for (let i = 0; i < 64; i++) {
                salt += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            $('#gsc_password_salt').val(salt);
        },

        toggleCustomHashRow: function() {
            const hashType = $('#gsc_password_hash_type').val();
            $('#custom_hash_row').toggle(hashType === 'custom');
        },

        testDBConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#test-db-result');
            
            $button.prop('disabled', true).text('Проверка...');
            $result.html('<span class="spinner is-active"></span> Проверка подключения...');
            
            $.post(gsc_db_data.ajax_url, {
                action: 'gsc_test_db_connection',
                nonce: gsc_db_data.nonce
            }, function(response) {
                console.log(response.data);
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response?.data?.message + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + response?.data?.message + '</span>');
                }
            }).always(function() {
                $button.prop('disabled', false).text('Проверить подключение к БД');
            });
        }
    };

    $(document).ready(function() {
        GSCDBTab.init();
    });

})(jQuery);
