<?php
/**
 * パスワード再設定ページ（コード入力 + 新パスワード設定）
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Auth\EmailAuth;

requireGuest();

$csrfToken = Session::generateCsrfToken();

// token_id をURLパラメータから取得
$tokenId = $_GET['id'] ?? '';

// トークン情報を取得（メールアドレス表示用）
$tokenInfo = null;
$email = '';
if ($tokenId) {
    $tokenInfo = EmailAuth::getToken($tokenId);
    if ($tokenInfo) {
        $email = $tokenInfo['email'];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワード再設定 - 京都医塾</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/kiweb/login/public/assets/css/style.css">
    <style>
        .code-input-container {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 24px;
        }

        .code-input {
            width: 48px;
            height: 56px;
            text-align: center;
            font-size: 24px;
            font-weight: 500;
            border: 1px solid var(--md-sys-color-outline);
            border-radius: 4px;
            outline: none;
        }

        .code-input:focus {
            border: 2px solid var(--md-sys-color-outline-focus);
        }

        .code-input.error {
            border-color: #d93025;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        .resend-link {
            color: var(--md-sys-color-primary);
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .resend-link.disabled {
            color: #9aa0a6;
            cursor: not-allowed;
        }
    </style>
</head>

<body>

    <div class="login-container">
        <div class="login-card">
            <div class="page-progress" id="page-progress">
                <span class="bar"></span>
            </div>
            <!-- ロゴ & タイトル -->
            <div class="login-header">
                <img src="/kiweb/login/public/京都医塾logo.png" alt="京都医塾" class="logo">
                <h1 id="page-title">再設定コードを入力</h1>
                <?php if ($email): ?>
                    <p class="subtitle" id="page-subtitle">
                        <?= htmlspecialchars($email) ?> に送信したコードを入力してください
                    </p>
                <?php else: ?>
                    <p class="subtitle" id="page-subtitle">メールに送信された6桁のコードを入力してください</p>
                <?php endif; ?>
            </div>

            <!-- ステップ1: コード入力 -->
            <div id="step1" class="step active">
                <form id="verify-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="token_id" id="token_id" value="<?= htmlspecialchars($tokenId) ?>">
                    <input type="hidden" name="code" id="code-hidden">

                    <div class="code-input-container">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            autocomplete="one-time-code" data-index="0">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="1">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="2">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="3">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="4">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="5">
                    </div>
                    <div class="field-error" id="error-code" role="alert" aria-live="polite"></div>

                    <div style="text-align: center; margin-bottom: 24px;">
                        <span id="resend-link" class="resend-link">コードを再送する</span>
                    </div>

                    <div class="action-area">
                        <a href="/login.php" class="text-btn">
                            戻る
                        </a>
                        <button type="submit" class="btn-primary ripple-btn" id="verify-btn">
                            確認
                        </button>
                    </div>
                </form>
            </div>

            <!-- ステップ2: 新パスワード設定 -->
            <div id="step2" class="step">
                <form id="reset-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="token_id" id="token_id2" value="<?= htmlspecialchars($tokenId) ?>">

                    <div class="md-text-field">
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password">
                        <label for="new_password">パスワード</label>
                    </div>
                    <div class="field-error" id="error-new_password" role="alert" aria-live="polite"></div>

                    <div class="md-text-field">
                        <input type="password" id="new_password_confirm" name="new_password_confirm"
                            autocomplete="new-password">
                        <label for="new_password_confirm">確認</label>
                    </div>
                    <div class="field-error" id="error-new_password_confirm" role="alert" aria-live="polite"></div>

                    <p style="font-size: 12px; color: #5f6368; margin-bottom: 24px;">
                        パスワードは任意の文字列を設定できます
                    </p>

                    <div class="action-area">
                        <div></div>
                        <button type="submit" class="btn-primary ripple-btn" id="reset-btn">
                            パスワードを変更
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/kiweb/login/public/assets/js/progress.js"></script>
    <script src="/kiweb/login/public/assets/js/login.js"></script>
    <script>
        const codeInputs = document.querySelectorAll('.code-input');
        const codeHidden = document.getElementById('code-hidden');
        const verifyForm = document.getElementById('verify-form');
        const resetForm = document.getElementById('reset-form');
        const verifyBtn = document.getElementById('verify-btn');
        const resetBtn = document.getElementById('reset-btn');
        const newPasswordInput = document.getElementById('new_password');
        const newPasswordConfirmInput = document.getElementById('new_password_confirm');

        [newPasswordInput, newPasswordConfirmInput].forEach((input) => {
            if (!input) {
                return;
            }
            input.addEventListener('input', () => {
                clearFieldError(input);
            });
        });
        const resendLink = document.getElementById('resend-link');
        const pageTitle = document.getElementById('page-title');
        const pageSubtitle = document.getElementById('page-subtitle');
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        let tokenId = document.getElementById('token_id').value;
        let verifiedCode = '';

        // コード入力のハンドリング
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', function (e) {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;
                clearCodeError();

                if (value && index < 5) {
                    codeInputs[index + 1].focus();
                }

                updateHiddenCode();
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', function (e) {
                e.preventDefault();
                clearCodeError();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);

                pastedData.split('').forEach((char, i) => {
                    if (codeInputs[i]) {
                        codeInputs[i].value = char;
                    }
                });

                updateHiddenCode();
                if (pastedData.length === 6) {
                    codeInputs[5].focus();
                }
            });
        });

        function updateHiddenCode() {
            const code = Array.from(codeInputs).map(input => input.value).join('');
            codeHidden.value = code;
            if (code.length === 6) {
                verifyForm.requestSubmit();
            }
        }

        const clearFieldError = (input) => {
            if (!input) {
                return;
            }
            const wrapper = input.closest('.md-text-field');
            if (wrapper) {
                wrapper.classList.remove('error');
            }
            input.classList.remove('error');
            const errorEl = document.getElementById(`error-${input.id}`);
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
            const errorEl = document.getElementById(`error-${input.id}`);
            if (errorEl) {
                errorEl.innerHTML = `<span class="error-icon">!</span><span>${message}</span>`;
                errorEl.classList.add('active');
            }
        };

        const clearCodeError = () => {
            codeInputs.forEach((input) => input.classList.remove('error'));
            const errorEl = document.getElementById('error-code');
            if (errorEl) {
                errorEl.classList.remove('active');
                errorEl.innerHTML = '';
            }
        };

        const setCodeError = (message) => {
            codeInputs.forEach((input) => input.classList.add('error'));
            const errorEl = document.getElementById('error-code');
            if (errorEl) {
                errorEl.innerHTML = `<span class="error-icon">!</span><span>${message}</span>`;
                errorEl.classList.add('active');
            }
        };

        function goToStep2() {
            step1.classList.remove('active');
            step2.classList.add('active');
            pageTitle.textContent = '新しいパスワードを設定';
            pageSubtitle.textContent = '安全なパスワードを設定してください';
            document.getElementById('new_password').focus();
        }

        // ステップ1: コード検証
        verifyForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearCodeError();

            const code = codeHidden.value;
            if (code.length !== 6) {
                setCodeError('6桁のコードを入力してください');
                return;
            }

            verifyBtn.disabled = true;
            verifyBtn.textContent = '確認中...';

            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $csrfToken ?>');
                formData.append('token_id', tokenId);
                formData.append('code', code);

                const response = await fetch('/login/api/password-reset-verify.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success && data.next_step) {
                    verifiedCode = code;
                    goToStep2();
                } else {
                    setCodeError(data.error || 'エラーが発生しました');
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = '確認';
                }
            } catch (error) {
                setCodeError('通信エラーが発生しました');
                verifyBtn.disabled = false;
                verifyBtn.textContent = '確認';
            }
        });

        // ステップ2: パスワード変更
        resetForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearFieldError(document.getElementById('new_password'));
            clearFieldError(document.getElementById('new_password_confirm'));

            const newPassword = document.getElementById('new_password').value;
            const newPasswordConfirm = document.getElementById('new_password_confirm').value;

            if (!newPassword) {
                setFieldError(document.getElementById('new_password'), 'パスワードを入力してください');
                return;
            }
            if (!newPasswordConfirm) {
                setFieldError(document.getElementById('new_password_confirm'), '確認用パスワードを入力してください');
                return;
            }
            if (newPassword !== newPasswordConfirm) {
                setFieldError(document.getElementById('new_password_confirm'), 'パスワードが一致しませんでした。もう一度お試しください。');
                return;
            }

            resetBtn.disabled = true;
            resetBtn.textContent = '変更中...';

            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $csrfToken ?>');
                formData.append('token_id', tokenId);
                formData.append('new_password', newPassword);
                formData.append('new_password_confirm', newPasswordConfirm);
                if (verifiedCode) {
                    formData.append('code', verifiedCode);
                }

                const response = await fetch('/login/api/password-reset-complete.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    const redirectUrl = data.redirect || '/dashboard.php';
                    if (window.startPageTransition) {
                        window.startPageTransition(redirectUrl);
                    } else {
                        window.location.href = redirectUrl;
                    }
                } else {
                    const errorMessage = data.error || 'エラーが発生しました';
                    if (errorMessage.includes('一致') || errorMessage.includes('確認')) {
                        setFieldError(document.getElementById('new_password_confirm'), errorMessage);
                    } else if (errorMessage.includes('パスワード')) {
                        setFieldError(document.getElementById('new_password'), errorMessage);
                    } else {
                        setFieldError(document.getElementById('new_password'), errorMessage);
                    }
                    resetBtn.disabled = false;
                    resetBtn.textContent = 'パスワードを変更';
                }
            } catch (error) {
                setFieldError(document.getElementById('new_password'), '通信エラーが発生しました');
                resetBtn.disabled = false;
                resetBtn.textContent = 'パスワードを変更';
            }
        });

        // コード再送
        resendLink.addEventListener('click', async function () {
            if (this.classList.contains('disabled')) return;

            this.classList.add('disabled');
            this.textContent = '送信中...';

            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $csrfToken ?>');
                formData.append('token_id', tokenId);

                const response = await fetch('/login/api/resend-verify.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    if (data.token_id) {
                        tokenId = data.token_id;
                        verifiedCode = '';
                        document.getElementById('token_id').value = data.token_id;
                        document.getElementById('token_id2').value = data.token_id;
                        history.replaceState(null, '', '/reset-password.php?id=' + data.token_id);
                    }
                    this.textContent = '送信しました！';
                    setTimeout(() => {
                        this.textContent = 'コードを再送する';
                        this.classList.remove('disabled');
                    }, 3000);
                } else {
                    setCodeError(data.error || '再送に失敗しました');
                    this.textContent = 'コードを再送する';
                    this.classList.remove('disabled');
                }
            } catch (error) {
                setCodeError('通信エラーが発生しました');
                this.textContent = 'コードを再送する';
                this.classList.remove('disabled');
            }
        });

        // 最初の入力欄にフォーカス
        codeInputs[0].focus();
    </script>
</body>

</html>
