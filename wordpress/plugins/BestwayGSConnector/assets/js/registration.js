jQuery(document).ready(function($) {
    let form = $('#gsc-registration-form');
    let usernameInput = $('#gsc_username');
    let emailInput = $('#user_email');
    let passwordInput = $('#gsc_game_password');
    let passwordConfirmInput = $('#gsc_game_password_confirm');

    $('.gsc-toggle-password').on('click', function() {
        let targetId = $(this).data('target');
        let input = $('#' + targetId);
        let icon = $(this).find('.dashicons');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    usernameInput.on('blur', function() {
        let username = $(this).val().trim();
        let feedback = $('#username-feedback');
        
        if (username.length === 0) {
            feedback.html('<span class="feedback-error">Введите имя пользователя</span>');
            return;
        }
        
        if (username.length < 3) {
            feedback.html('<span class="feedback-error">Минимум 3 символа</span>');
            return;
        }
        
        if (!/^[a-zA-Z0-9_\-\.]+$/.test(username)) {
            feedback.html('<span class="feedback-error">Только буквы, цифры, ., -, _</span>');
            return;
        }
        
        $.post(gscRegistration.ajax_url, {
            action: 'gsc_check_username',
            username: username,
            nonce: gscRegistration.nonce
        }, function(response) {
            if (response.success) {
                if (response.data.available) {
                    feedback.html('<span class="feedback-success">Имя доступно</span>');
                } else {
                    feedback.html('<span class="feedback-error">Имя уже занято</span>');
                }
            }
        });
    });
    
    emailInput.on('blur', function() {
        let email = $(this).val().trim();
        let feedback = $('#email-feedback');
        
        if (email.length === 0) {
            feedback.html('<span class="feedback-error">Введите email</span>');
            return;
        }
        
        let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            feedback.html('<span class="feedback-error">Некорректный email</span>');
            return;
        }
        
        feedback.html('<span class="feedback-success">Email корректен</span>');
    });
    
    passwordInput.on('keyup', function() {
        let password = $(this).val();
        let strengthBar = $('#password-strength-bar');
        let strengthLabel = $('#password-strength-label');
        
        let score = 0;
        if (password.length >= 6) score += 1;
        if (password.length >= 8) score += 1;
        if (/[A-Z]/.test(password)) score += 1;
        if (/[0-9]/.test(password)) score += 1;
        if (/[^A-Za-z0-9]/.test(password)) score += 1;

        let width = score * 20;
        let colors = ['#dc3545', '#ff6b00', '#ffc107', '#28a745'];
        let labels = ['Очень слабый', 'Слабый', 'Средний', 'Сильный', 'Очень сильный'];
        
        strengthBar.css({
            'width': width + '%',
            'background-color': colors[score - 1] || '#dc3545'
        });
        
        strengthLabel.text(labels[score] || '');

        checkPasswordMatch();
    });

    passwordConfirmInput.on('keyup', checkPasswordMatch);
    
    function checkPasswordMatch() {
        let password = passwordInput.val();
        let confirm = passwordConfirmInput.val();
        let feedback = $('#password-confirm-feedback');
        
        if (confirm.length === 0) {
            feedback.html('');
            return;
        }
        
        if (password !== confirm) {
            feedback.html('<span class="feedback-error">Пароли не совпадают</span>');
        } else {
            feedback.html('<span class="feedback-success">Пароли совпадают</span>');
        }
    }

    form.on('submit', function(e) {
        e.preventDefault();
        
        let submitBtn = $('#gsc-register-submit');
        let btnText = submitBtn.find('.btn-text');
        let btnSpinner = submitBtn.find('.btn-spinner');

        btnText.hide();
        btnSpinner.show();
        submitBtn.prop('disabled', true);

        let formData = form.serialize();
        
        $.post(gscRegistration.ajax_url, {
            action: 'gsc_register_user',
            data: formData
        }, function(response) {
            if (response.success) {
                window.location.href = response.data.redirect_url;
            } else {
                alert(response.data);
                btnText.show();
                btnSpinner.hide();
                submitBtn.prop('disabled', false);
            }
        }).fail(function() {
            form.off('submit').submit();
        });
    });
});
