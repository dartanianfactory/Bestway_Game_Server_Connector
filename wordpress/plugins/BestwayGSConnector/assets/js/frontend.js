(function($) {
    'use strict';

    const GSCFrontend = {
        init: function() {
            this.initModals();
            this.initForms();
            this.initTooltips();
            this.initNotifications();
        },

        initModals: function() {
            $(document).on('click', '.gsc-modal', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });

            $(document).on('click', '.gsc-modal-close', function() {
                $(this).closest('.gsc-modal').hide();
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.gsc-modal').hide();
                }
            });
        },

        initForms: function() {
            $(document).on('submit', '.gsc-form', function(e) {
                const $form = $(this);
                let isValid = true;
                let firstErrorField = null;

                $form.find('[required]').each(function() {
                    const $field = $(this);
                    const value = $field.val().trim();
                    
                    if (!value) {
                        isValid = false;
                        if (!firstErrorField) {
                            firstErrorField = $field;
                        }
                        
                        $field.addClass('gsc-field-error');
                        $field.siblings('.gsc-field-error-message').remove();
                        $field.after('<span class="gsc-field-error-message">Это поле обязательно</span>');
                    } else {
                        $field.removeClass('gsc-field-error');
                        $field.siblings('.gsc-field-error-message').remove();
                    }
                });

                $form.find('input[type="email"]').each(function() {
                    const $field = $(this);
                    const value = $field.val().trim();
                    
                    if (value) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(value)) {
                            isValid = false;
                            if (!firstErrorField) {
                                firstErrorField = $field;
                            }
                            
                            $field.addClass('gsc-field-error');
                            $field.siblings('.gsc-field-error-message').remove();
                            $field.after('<span class="gsc-field-error-message">Некорректный email</span>');
                        }
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    if (firstErrorField) {
                        firstErrorField.focus();
                    }
                    this.showNotification('Пожалуйста, исправьте ошибки в форме', 'error');
                    return false;
                }

                const $submitBtn = $form.find('button[type="submit"]');
                const originalText = $submitBtn.text();
                $submitBtn.prop('disabled', true).html('<span class="spinner"></span> Отправка...');

                setTimeout(() => {
                    $submitBtn.prop('disabled', false).text(originalText);
                }, 10000);
            });

            $(document).on('input', '.gsc-form input, .gsc-form textarea', function() {
                $(this).removeClass('gsc-field-error');
                $(this).siblings('.gsc-field-error-message').remove();
            });
        },

        initTooltips: function() {
            $(document).on('mouseenter', '[data-tooltip]', function() {
                const tooltipText = $(this).data('tooltip');
                if (!tooltipText) return;
                
                const $tooltip = $('<div class="gsc-tooltip"></div>')
                    .text(tooltipText)
                    .appendTo('body');
                
                const rect = this.getBoundingClientRect();
                $tooltip.css({
                    position: 'fixed',
                    top: rect.top - $tooltip.outerHeight() - 10 + 'px',
                    left: rect.left + (rect.width - $tooltip.outerWidth()) / 2 + 'px',
                    zIndex: 99999
                });
            });
            
            $(document).on('mouseleave', '[data-tooltip]', function() {
                $('.gsc-tooltip').remove();
            });
        },

        initNotifications: function() {
            setInterval(() => {
                $('.gsc-notification').each(function() {
                    const $notification = $(this);
                    const createdAt = $notification.data('created-at') || 0;
                    const now = Date.now();
                    
                    if (now - createdAt > 5000) {
                        $notification.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                });
            }, 1000);
        },

        showModal: function(modalId) {
            $('#' + modalId).show();
            $('body').addClass('gsc-modal-open');
        },

        hideModal: function(modalId) {
            $('#' + modalId).hide();
            $('body').removeClass('gsc-modal-open');
        },

        showNotification: function(message, type = 'info') {
            const types = {
                'success': 'gsc-notification-success',
                'error': 'gsc-notification-error',
                'warning': 'gsc-notification-warning',
                'info': 'gsc-notification-info'
            };
            
            const $notification = $('<div class="gsc-notification ' + (types[type] || types.info) + '"></div>')
                .text(message)
                .data('created-at', Date.now())
                .appendTo('body');

            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            $notification.on('click', function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
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
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    };

    $(document).ready(function() {
        GSCFrontend.init();
    });

    window.GSCFrontend = GSCFrontend;

})(jQuery);
