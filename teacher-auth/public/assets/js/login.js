document.addEventListener('DOMContentLoaded', () => {
    const SHARED_TAB_SESSION_KEY = 'kiweb_shared_tab_session';
    const SHARED_TAB_TOKEN_KEY = 'kiweb_shared_tab_token';
    const SHARED_TAB_NAME_PREFIX = 'kiweb-shared-tab:';

    // --- フローティングラベル制御 ---
    const inputs = document.querySelectorAll('.md-text-field input');

    const updateLabelState = (input) => {
        if (input.value) {
            input.classList.add('has-value');
        } else {
            input.classList.remove('has-value');
        }
    };

    const getErrorElement = (input) => {
        if (!input || !input.id) {
            return null;
        }
        return document.getElementById(`error-${input.id}`);
    };

    const clearFieldError = (input) => {
        if (!input) {
            return;
        }
        const wrapper = input.closest('.md-text-field');
        if (wrapper) {
            wrapper.classList.remove('error');
        }
        input.classList.remove('error');
        const errorEl = getErrorElement(input);
        if (errorEl) {
            errorEl.classList.remove('active');
            errorEl.innerHTML = '';
        }
    };

    const setFieldError = (input, message) => {
        if (!input) {
            return;
        }
        const wrapper = input.closest('.md-text-field');
        if (wrapper) {
            wrapper.classList.add('error');
        }
        input.classList.add('error');
        const errorEl = getErrorElement(input);
        if (errorEl) {
            errorEl.innerHTML = `<span class="error-icon">!</span><span>${message}</span>`;
            errorEl.classList.add('active');
        }
    };

    const sanitizeHalfWidth = (value) => {
        return value.replace(/[^\x21-\x7E]/g, '');
    };

    const normalizeEmail = (value) => {
        return value.replace(/[\s\u200b\uFEFF]/g, '').trim();
    };

    const isHalfWidthAscii = (value) => {
        return /^[\x21-\x7E]+$/.test(value);
    };

    const isValidEmail = (value) => {
        return isHalfWidthAscii(value) && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value);
    };

// ====== 変更後 ======
    const isValidNamePart = (value) => {
        return /^[^\x00-\x40\x5B-\x60\x7B-\x7E\s\u3000\uFF01-\uFF0F\uFF1A-\uFF20\uFF3B-\uFF40\uFF5B-\uFF65\uFFE0-\uFFEF]+$/.test(value);
    };

    inputs.forEach((input) => {
        // 初期状態チェック
        updateLabelState(input);

        // 入力時チェック
        input.addEventListener('input', (event) => {
            if ((input.type === 'email' || input.type === 'password') && !(event && event.isComposing)) {
                const sanitized = sanitizeHalfWidth(input.value);
                if (sanitized !== input.value) {
                    const cursor = input.selectionStart;
                    input.value = sanitized;
                    if (cursor !== null) {
                        const nextPos = Math.min(cursor, sanitized.length);
                        input.setSelectionRange(nextPos, nextPos);
                    }
                }
            }
            updateLabelState(input);
            clearFieldError(input);
        });
        input.addEventListener('change', () => {
            updateLabelState(input);
            clearFieldError(input);
        });
        input.addEventListener('blur', () => {
            updateLabelState(input);
            if (input.type === 'email') {
                const emailValue = normalizeEmail(input.value);
                if (emailValue && isValidEmail(emailValue)) {
                    input.value = emailValue;
                    clearFieldError(input);
                }
            }
        });

        // オートフィル対策（少し遅延してチェック）
        setTimeout(() => {
            updateLabelState(input);
            if (input.type === 'email') {
                const emailValue = normalizeEmail(input.value);
                if (emailValue && isValidEmail(emailValue)) {
                    input.value = emailValue;
                    clearFieldError(input);
                }
            }
        }, 100);
    });

    // --- リップルエフェクト ---
    const rippleButtons = document.querySelectorAll('.ripple-btn');

    rippleButtons.forEach((btn) => {
        btn.addEventListener('click', function (e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;

            this.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    const passwordForm = document.getElementById('password-form');
    const forgotForm = document.getElementById('forgot-form');
    const signupForm = document.getElementById('signup-form');

    const loginHeader = document.querySelector('.login-header');
    let errorBox = document.querySelector('.alert.alert-error');
    let errorText = errorBox ? errorBox.querySelector('span') : null;

    if (passwordForm && !errorBox && loginHeader) {
        errorBox = document.createElement('div');
        errorBox.className = 'alert alert-error';
        errorBox.style.display = 'none';
        errorBox.innerHTML = [
            '<svg style="width:20px;height:20px;margin-right:8px;fill:currentColor" viewBox="0 0 24 24">',
            '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />',
            '</svg>',
            '<span></span>'
        ].join('');
        loginHeader.insertAdjacentElement('afterend', errorBox);
        errorText = errorBox.querySelector('span');
    }

    const showError = (message) => {
        if (!errorBox || !errorText) {
            return;
        }
        errorText.textContent = message;
        errorBox.style.display = 'flex';
    };

    const hideError = () => {
        if (errorBox) {
            errorBox.style.display = 'none';
        }
    };

    const redirectTo = (url) => {
        const targetWindow = window.top && window.top !== window ? window.top : window;
        if (targetWindow.startPageTransition) {
            targetWindow.startPageTransition(url);
        } else {
            targetWindow.location.href = url;
        }
    };

    const getTargetWindow = () => {
        return window.top && window.top !== window ? window.top : window;
    };

    const createSharedTabToken = () => {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return `${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
    };

    const clearSharedTabContext = () => {
        try {
            const targetWindow = getTargetWindow();
            targetWindow.sessionStorage.removeItem(SHARED_TAB_SESSION_KEY);
            targetWindow.sessionStorage.removeItem(SHARED_TAB_TOKEN_KEY);
            if ((targetWindow.name || '').startsWith(SHARED_TAB_NAME_PREFIX)) {
                targetWindow.name = '';
            }
        } catch (error) {
            // ignore
        }
    };

    const setSharedTabContext = () => {
        try {
            const targetWindow = getTargetWindow();
            const token = createSharedTabToken();
            targetWindow.sessionStorage.setItem(SHARED_TAB_SESSION_KEY, '1');
            targetWindow.sessionStorage.setItem(SHARED_TAB_TOKEN_KEY, token);
            targetWindow.name = SHARED_TAB_NAME_PREFIX + token;
        } catch (error) {
            // ignore
        }
    };

    const syncSharedTabMarker = (form, redirectUrl) => {
        if (!form || form.id !== 'password-form' || !redirectUrl || !redirectUrl.includes('/kiweb/kiweb2.html')) {
            return;
        }

        const rememberCheckbox = form.querySelector('#remember-login');
        const shouldRemember = !!(rememberCheckbox && rememberCheckbox.checked);
        if (shouldRemember) {
            clearSharedTabContext();
        } else {
            setSharedTabContext();
        }
    };

    const attachAjaxForm = (form) => {
        if (!form) {
            return;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideError();

            const formInputs = Array.from(form.querySelectorAll('input'));
            formInputs.forEach(clearFieldError);

            if (form.id === 'password-form') {
                const emailInput = form.querySelector('#email');
                const passwordInput = form.querySelector('#password');
                const emailValue = emailInput ? normalizeEmail(emailInput.value) : '';
                const passwordValue = passwordInput ? passwordInput.value : '';

                if (!emailValue) {
                    setFieldError(emailInput, 'メールアドレスを入力してください');
                    return;
                }
                if (!isValidEmail(emailValue)) {
                    setFieldError(emailInput, '有効なメールアドレスを入力してください');
                    return;
                }
                if (emailInput) {
                    emailInput.value = emailValue;
                }
                if (!passwordValue) {
                    setFieldError(passwordInput, 'パスワードを入力してください');
                    return;
                }
            } else if (form.id === 'forgot-form') {
                const emailInput = form.querySelector('#email');
                const emailValue = emailInput ? normalizeEmail(emailInput.value) : '';
                if (!emailValue) {
                    setFieldError(emailInput, 'メールアドレスを入力してください');
                    return;
                }
                if (!isValidEmail(emailValue)) {
                    setFieldError(emailInput, '有効なメールアドレスを入力してください');
                    return;
                }
                if (emailInput) {
                    emailInput.value = emailValue;
                }
            } else if (form.id === 'signup-form') {
                const emailInput = form.querySelector('#email');
                const passwordInput = form.querySelector('#password');
                const confirmInput = form.querySelector('#password_confirm');
                const emailValue = emailInput ? normalizeEmail(emailInput.value) : '';
                const passwordValue = passwordInput ? passwordInput.value : '';
                const confirmValue = confirmInput ? confirmInput.value : '';

                // ↓↓↓ 追加 ↓↓↓
                const lastNameInput  = form.querySelector('#last_name');
                const firstNameInput = form.querySelector('#first_name');
                const nameInput      = form.querySelector('#name');
                if (lastNameInput && firstNameInput && nameInput) {
                    const lastName  = lastNameInput.value.trim();
                    const firstName = firstNameInput.value.trim();
                    if (!lastName) {
                        setFieldError(lastNameInput, '氏を入力してください');
                        return;
                    }
                    if (!isValidNamePart(lastName)) {
                        setFieldError(lastNameInput, '氏に使用できない文字が含まれています');
                        return;
                    }
                    if (!firstName) {
                        setFieldError(firstNameInput, '名を入力してください');
                        return;
                    }
                    if (!isValidNamePart(firstName)) {
                        setFieldError(firstNameInput, '名に使用できない文字が含まれています');
                        return;
                    }
                    nameInput.value = lastName + firstName;
                }
                // ↑↑↑ 追加ここまで ↑↑↑

                if (!emailValue) {
                    setFieldError(emailInput, 'メールアドレスを入力してください');
                    return;
                }
                if (!isValidEmail(emailValue)) {
                    setFieldError(emailInput, '有効なメールアドレスを入力してください');
                    return;
                }
                if (emailInput) {
                    emailInput.value = emailValue;
                }
                if (!passwordValue) {
                    setFieldError(passwordInput, 'パスワードを入力してください');
                    return;
                }
                if (!confirmValue) {
                    setFieldError(confirmInput, '確認用パスワードを入力してください');
                    return;
                }
                if (passwordValue !== confirmValue) {
                    setFieldError(confirmInput, 'パスワードが一致しませんでした。もう一度お試しください。');
                    return;
                }
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHtml = submitBtn ? submitBtn.innerHTML : '';
            const showLoading = form.id !== 'forgot-form';

            if (submitBtn) {
                submitBtn.disabled = true;
                if (showLoading) {
                    submitBtn.innerHTML = '<span class="loading-spinner"></span>処理中...';
                }
            }

            try {
                const formData = new FormData(form);
                if (form.id === 'password-form') {
                    const rememberCheckbox = form.querySelector('#remember-login');
                    formData.set('remember', rememberCheckbox && rememberCheckbox.checked ? '1' : '0');
                }
                const csrfInput = form.querySelector('input[name="csrf_token"]');
                const headers = csrfInput ? { 'X-CSRF-Token': csrfInput.value } : undefined;
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers
                });
                const data = await response.json();

                if (data.redirect) {
                    syncSharedTabMarker(form, data.redirect);
                    redirectTo(data.redirect);
                    return;
                }

                if (!data.success) {
                    const errorMessage = data.error || 'エラーが発生しました';
                    if (form.id === 'password-form') {
                        const emailInput = form.querySelector('#email');
                        const passwordInput = form.querySelector('#password');
                        if (errorMessage.includes('パスワード')) {
                            setFieldError(passwordInput, errorMessage);
                        } else {
                            setFieldError(emailInput, errorMessage);
                        }
                    } else if (form.id === 'forgot-form') {
                        const emailInput = form.querySelector('#email');
                        setFieldError(emailInput, errorMessage);
                    } else if (form.id === 'signup-form') {
                        const emailInput = form.querySelector('#email');
                        const passwordInput = form.querySelector('#password');
                        const confirmInput = form.querySelector('#password_confirm');
                        const lastNameInput = form.querySelector('#last_name');
                        const firstNameInput = form.querySelector('#first_name');
                        if (errorMessage.includes('確認') || errorMessage.includes('一致')) {
                            setFieldError(confirmInput, errorMessage);
                        } else if (errorMessage.includes('パスワード')) {
                            setFieldError(passwordInput, errorMessage);
                        } else {
                            setFieldError(emailInput, errorMessage);
                        }
                    } else {
                        showError(errorMessage);
                    }
                    return;
                }
            } catch (error) {
                if (form.id === 'password-form') {
                    setFieldError(form.querySelector('#email'), '通信エラーが発生しました');
                } else if (form.id === 'forgot-form') {
                    setFieldError(form.querySelector('#email'), '通信エラーが発生しました');
                } else if (form.id === 'signup-form') {
                    setFieldError(form.querySelector('#email'), '通信エラーが発生しました');
                } else {
                    showError('通信エラーが発生しました');
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (showLoading) {
                        submitBtn.innerHTML = originalHtml || '次へ';
                    }
                }
            }
        });
    };

    attachAjaxForm(passwordForm);
    attachAjaxForm(forgotForm);
    attachAjaxForm(signupForm);
});
