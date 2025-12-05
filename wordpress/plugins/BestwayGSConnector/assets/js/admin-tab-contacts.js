(function($) {
    'use strict';

    const GSCContactsTab = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.copy-btn', this.copyToClipboard.bind(this));
        },

        copyToClipboard: function(e) {
            const $button = $(e.target);
            const target = $button.data('clipboard-target');
            const text = $(target).text().trim();
            
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    const originalText = $button.text();
                    $button.text('Скопировано!');
                    
                    setTimeout(function() {
                        $button.text(originalText);
                    }, 2000);
                }
            } catch (err) {
                console.log('Ошибка копирования: ', err);
            }
            
            $temp.remove();
        }
    };

    $(document).ready(function() {
        GSCContactsTab.init();
    });

})(jQuery);
