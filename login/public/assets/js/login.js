document.addEventListener('DOMContentLoaded', () => {
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

    inputs.forEach(input => {
        // 初期状態チェック
        updateLabelState(input);

        // 入力時チェック
        input.addEventListener('input', (event) => {
            if (input.type === 'email' && !(event && event.isComposing)) {
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

    // --- タブ切り替え制御 ---
    const tabButtons = document.querySelectorAll('.js-toggle-tab');
    const forms = document.querySelectorAll('.auth-form');
    const pageTitle = document.getElementById('page-title');
    const pageSubtitle = document.getElementById('page-subtitle');

    const switchTab = (targetId) => {
        // すべて非表示
        forms.forEach(form => form.classList.remove('active'));

        // ターゲットを表示
        const targetForm = document.getElementById(targetId + '-form');
        if (targetForm) {
            targetForm.classList.add('active');
        }

        // タイトル切り替え（簡易実装）
        if (targetId === 'password') {
            pageTitle.textContent = 'ログイン';
            pageSubtitle.textContent = '京都医塾アカウントを使用';
        } else if (targetId === 'email') {
            pageTitle.textContent = 'メール認証';
            pageSubtitle.textContent = '認証コードを送信します';
        } else if (targetId === 'passkey') {
            pageTitle.textContent = 'パスキー認証';
            pageSubtitle.textContent = '生体認証でログイン';
        }
    };

    const params = new URLSearchParams(window.location.search);
    const mode = params.get('mode');
    if (mode === 'password' || mode === 'email' || mode === 'passkey') {
        switchTab(mode);
    }

    tabButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const target = btn.dataset.target;
            switchTab(target);
        });
    });

    // --- リップルエフェクト ---
    const rippleButtons = document.querySelectorAll('.ripple-btn');

    rippleButtons.forEach(btn => {
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
    const emailForm = document.getElementById('email-form');
    const forgotForm = document.getElementById('forgot-form');
    const signupForm = document.getElementById('signup-form');
    const hasLoginForms = passwordForm || emailForm;

    const loginHeader = document.querySelector('.login-header');
    let errorBox = document.querySelector('.alert.alert-error');
    let errorText = errorBox ? errorBox.querySelector('span') : null;

    if (hasLoginForms && !errorBox && loginHeader) {
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

    const applyServerFieldError = () => {
        if (!errorBox || !errorText) {
            return;
        }
        const message = (errorText.textContent || '').trim();
        if (!message) {
            return;
        }
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        if (!emailInput || !passwordInput) {
            return;
        }
        const isFieldError = ['パスワード', 'メールアドレス', 'ID'].some((token) => message.includes(token));
        if (!isFieldError) {
            return;
        }
        if (message.includes('パスワード')) {
            setFieldError(passwordInput, message);
        } else {
            setFieldError(emailInput, message);
        }
        errorBox.style.display = 'none';
    };

    applyServerFieldError();

    // Use native submit on password form to let browsers prompt to save passwords.
    const shouldSubmitNatively = (form) => form && form.id === 'password-form';

    const attachAjaxForm = (form) => {
        if (!form) {
            return;
        }

        form.addEventListener('submit', async (e) => {
            const useAjax = !shouldSubmitNatively(form);
            if (useAjax) {
                e.preventDefault();
            }
            hideError();

            const formInputs = Array.from(form.querySelectorAll('input'));
            formInputs.forEach(clearFieldError);

            if (form.id === 'password-form') {
                const emailInput = form.querySelector('#email');
                const passwordInput = form.querySelector('#password');
                const emailValue = emailInput ? emailInput.value.trim() : '';
                const passwordValue = passwordInput ? passwordInput.value : '';

                if (!emailValue) {
                    e.preventDefault();
                    setFieldError(emailInput, 'IDを入力してください');
                    return;
                }
                if (emailInput) {
                    emailInput.value = emailValue;
                }
                if (!passwordValue) {
                    e.preventDefault();
                    setFieldError(passwordInput, 'パスワードを入力してください');
                    return;
                }

            } else if (form.id === 'email-form') {
                const emailInput = form.querySelector('#email-login');
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

            if (!useAjax) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                // ネイティブ送信時もプログレスバーを表示
                if (window.startPageTransition) {
                    window.startPageTransition();
                }
                return;
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
                const csrfInput = form.querySelector('input[name="csrf_token"]');
                const csrfToken = csrfInput ? csrfInput.value : '';
                if (csrfToken) {
                    formData.set('csrf_token', csrfToken);
                }
                const headers = { 'X-Requested-With': 'XMLHttpRequest' };
                if (csrfToken) {
                    headers['X-CSRF-Token'] = csrfToken;
                }
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers
                });
                const data = await response.json();

                if (response.status === 403 && data && data.error === '不正なリクエストです') {
                    showError('セッションが切れました。ページを再読み込みしてください。');
                    return;
                }

                if (!data.success) {
                    if (data.redirect) {
                        if (window.startPageTransition) {
                            window.startPageTransition(data.redirect);
                        } else {
                            window.location.href = data.redirect;
                        }
                        return;
                    }

                    const errorMessage = data.error || 'エラーが発生しました';
                    if (form.id === 'password-form') {
                        const emailInput = form.querySelector('#email');
                        const passwordInput = form.querySelector('#password');
                        if (errorMessage.includes('パスワード')) {
                            setFieldError(passwordInput, errorMessage);
                        } else {
                            setFieldError(emailInput, errorMessage);
                        }
                    } else if (form.id === 'email-form') {
                        const emailInput = form.querySelector('#email-login');
                        setFieldError(emailInput, errorMessage);
                    } else if (form.id === 'forgot-form') {
                        const emailInput = form.querySelector('#email');
                        setFieldError(emailInput, errorMessage);
                    } else if (form.id === 'signup-form') {
                        const emailInput = form.querySelector('#email');
                        const passwordInput = form.querySelector('#password');
                        const confirmInput = form.querySelector('#password_confirm');
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

                if (data.redirect) {
                    if (window.startPageTransition) {
                        window.startPageTransition(data.redirect);
                    } else {
                        window.location.href = data.redirect;
                    }
                    return;
                }
            } catch (error) {
                if (form.id === 'password-form') {
                    setFieldError(form.querySelector('#email'), '通信エラーが発生しました');
                } else if (form.id === 'email-form') {
                    setFieldError(form.querySelector('#email-login'), '通信エラーが発生しました');
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
    attachAjaxForm(emailForm);
    attachAjaxForm(forgotForm);
    attachAjaxForm(signupForm);

    const passkeyBtn = document.getElementById('passkey-login-btn');
    const passkeyEmail = document.getElementById('passkey-email');

    if (!passkeyBtn) {
        return;
    }

    passkeyBtn.addEventListener('click', async () => {
        hideError();

        if (!window.PublicKeyCredential) {
            setFieldError(passkeyEmail, 'このブラウザはパスキーに対応していません');
            return;
        }

        const email = passkeyEmail ? passkeyEmail.value.trim() : '';
        if (!email) {
            setFieldError(passkeyEmail, 'メールアドレスを入力してください');
            if (passkeyEmail) {
                passkeyEmail.focus();
            }
            return;
        }

        const normalizedEmail = normalizeEmail(email);
        if (!isValidEmail(normalizedEmail)) {
            setFieldError(passkeyEmail, '有効なメールアドレスを入力してください');
            if (passkeyEmail) {
                passkeyEmail.focus();
            }
            return;
        }
        if (passkeyEmail) {
            passkeyEmail.value = normalizedEmail;
        }

        const originalHtml = passkeyBtn.innerHTML;
        passkeyBtn.disabled = true;
        passkeyBtn.innerHTML = '<span class="loading-spinner"></span>認証中...';

        try {
            const optionsResponse = await fetch('/kiweb/login/api/webauthn/login-options.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ email })
            });

            const optionsResult = await optionsResponse.json();
            if (!optionsResult.success) {
                throw new Error(optionsResult.error || 'ログインオプションの取得に失敗しました');
            }

            const options = optionsResult.options;
            options.challenge = base64ToBuffer(options.challenge);
            if (options.allowCredentials) {
                options.allowCredentials = options.allowCredentials.map((cred) => ({
                    ...cred,
                    id: base64ToBuffer(cred.id)
                }));
            }

            const assertion = await navigator.credentials.get({
                publicKey: options
            });

            const verifyResponse = await fetch('/kiweb/login/api/webauthn/login-verify.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    id: assertion.id,
                    rawId: bufferToBase64(assertion.rawId),
                    response: {
                        clientDataJSON: bufferToBase64(assertion.response.clientDataJSON),
                        authenticatorData: bufferToBase64(assertion.response.authenticatorData),
                        signature: bufferToBase64(assertion.response.signature),
                        userHandle: assertion.response.userHandle
                            ? bufferToBase64(assertion.response.userHandle)
                            : null
                    },
                    type: assertion.type
                })
            });

            const verifyResult = await verifyResponse.json();
            if (verifyResult.success && verifyResult.redirect) {
                if (window.startPageTransition) {
                    window.startPageTransition(verifyResult.redirect);
                } else {
                    window.location.href = verifyResult.redirect;
                }
                return;
            }

            throw new Error(verifyResult.error || 'パスキー認証に失敗しました');
        } catch (error) {
            if (error.name === 'NotAllowedError') {
                setFieldError(passkeyEmail, 'パスキー認証がキャンセルされました');
            } else {
                setFieldError(passkeyEmail, error.message || 'パスキー認証に失敗しました');
            }
        } finally {
            passkeyBtn.disabled = false;
            passkeyBtn.innerHTML = originalHtml;
        }
    });

    const langToggle = document.getElementById('lang-toggle');
    const langMenu = document.getElementById('lang-menu');

    if (langToggle && langMenu) {
        const langLabel = langToggle.querySelector('.lang-label');
        const closeMenu = () => {
            langMenu.hidden = true;
            langToggle.setAttribute('aria-expanded', 'false');
        };

        langToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = !langMenu.hidden;
            langMenu.hidden = isOpen;
            langToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        });

        langMenu.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (target.matches('button[data-lang]')) {
                if (langLabel) {
                    langLabel.textContent = target.textContent;
                }
                closeMenu();
            }
        });

        document.addEventListener('click', () => {
            closeMenu();
        });
    }
});

function base64ToBuffer(base64) {
    const normalized = base64.replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function bufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}
