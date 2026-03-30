<?php
/**
 * 認証コード入力ページ
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;

requireGuest();

$tokenId = $_GET['id'] ?? '';
$remember = (($_GET['remember'] ?? '') === '1') ? '1' : '0';
if (empty($tokenId)) {
    header('Location: /kiweb/teacher-auth/public/login.php');
    exit;
}

$csrfToken = Session::generateCsrfToken();
$error = Session::getFlash('error');
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>認証コード入力 - 京都医塾</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/kiweb/teacher-auth/public/assets/css/style.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="page-progress" id="page-progress">
                <span class="bar"></span>
            </div>
            <div class="login-header">
                <img src="/kiweb/teacher-auth/public/京都医塾logo.png" alt="京都医塾" class="logo">
                <h1>認証コード入力</h1>
                <p class="subtitle">メールに送信された6桁のコードを入力してください</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form id="verify-code-form" action="/kiweb/teacher-auth/api/verify-code.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="token_id" value="<?= htmlspecialchars($tokenId) ?>">
                <input type="hidden" name="remember" id="remember" value="<?= $remember ?>">
                <input type="hidden" name="code" id="code-hidden">

                <div class="code-input-container" style="display: flex; gap: 8px; justify-content: center; margin-bottom: 24px;">
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                        autocomplete="one-time-code" data-index="0" style="width: 48px; height: 56px; text-align: center; font-size: 24px; font-weight: 500; border: 1px solid #dadce0; border-radius: 4px; outline: none;">
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                        data-index="1" style="width: 48px; height: 56px; text-align: center; font-size: 24px; font-weight: 500; border: 1px solid #dadce0; border-radius: 4px; outline: none;">
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                        data-index="2" style="width: 48px; height: 56px; text-align: center; font-size: 24px; font-weight: 500; border: 1px solid #dadce0; border-radius: 4px; outline: none;">
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                        data-index="3" style="width: 48px; height: 56px; text-align: center; font-size: 24px; font-weight: 500; border: 1px solid #dadce0; border-radius: 4px; outline: none;">
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                        data-index="4" style="width: 48px; height: 56px; text-align: center; font-size: 24px; font-weight: 500; border: 1px solid #dadce0; border-radius: 4px; outline: none;">
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                        data-index="5" style="width: 48px; height: 56px; text-align: center; font-size: 24px; font-weight: 500; border: 1px solid #dadce0; border-radius: 4px; outline: none;">
                </div>
                <div class="field-error" id="error-code" role="alert" aria-live="polite"></div>

                <div style="text-align: center; margin-bottom: 24px;">
                    <span id="resend-link" class="resend-link" style="color: #0F3568; cursor: pointer; font-size: 14px; text-decoration: none;">コードを再送する</span>
                </div>

                <div class="action-area">
                    <a href="login.php" class="text-btn">戻る</a>
                    <button type="submit" class="btn-primary ripple-btn" id="verify-btn">
                        確認
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="/kiweb/teacher-auth/public/assets/js/progress.js"></script>
    <script>
        const codeInputs = document.querySelectorAll('.code-input');
        const codeHidden = document.getElementById('code-hidden');
        const form = document.getElementById('verify-code-form');
        const verifyBtn = document.getElementById('verify-btn');
        const resendLink = document.getElementById('resend-link');
        let tokenId = '<?= htmlspecialchars($tokenId) ?>';
        const rememberValue = document.getElementById('remember').value;
        const SHARED_TAB_SESSION_KEY = 'kiweb_shared_tab_session';
        const SHARED_TAB_TOKEN_KEY = 'kiweb_shared_tab_token';
        const SHARED_TAB_NAME_PREFIX = 'kiweb-shared-tab:';

        function getTargetWindow() {
            return window.top && window.top !== window ? window.top : window;
        }

        function createSharedTabToken() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }
            return `${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
        }

        function clearSharedTabContext() {
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
        }

        function setSharedTabContext() {
            try {
                const targetWindow = getTargetWindow();
                const token = createSharedTabToken();
                targetWindow.sessionStorage.setItem(SHARED_TAB_SESSION_KEY, '1');
                targetWindow.sessionStorage.setItem(SHARED_TAB_TOKEN_KEY, token);
                targetWindow.name = SHARED_TAB_NAME_PREFIX + token;
            } catch (error) {
                // ignore
            }
        }

        function syncSharedTabMarker() {
            if (rememberValue === '1') {
                clearSharedTabContext();
            } else {
                setSharedTabContext();
            }
        }

        // コード入力のハンドリング
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;
                clearCodeError();

                if (value && index < 5) {
                    codeInputs[index + 1].focus();
                }

                updateHiddenCode();
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });

            input.addEventListener('focus', function() {
                this.select();
            });

            input.addEventListener('paste', function(e) {
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
                form.requestSubmit();
            }
        }

        function clearCodeError() {
            codeInputs.forEach((input) => input.classList.remove('error'));
            const errorEl = document.getElementById('error-code');
            if (errorEl) {
                errorEl.classList.remove('active');
                errorEl.innerHTML = '';
            }
        }

        function setCodeError(message) {
            codeInputs.forEach((input) => input.classList.add('error'));
            const errorEl = document.getElementById('error-code');
            if (errorEl) {
                errorEl.innerHTML = `<span class="error-icon">!</span><span>${message}</span>`;
                errorEl.classList.add('active');
            }
        }

        // フォーム送信
        form.addEventListener('submit', async function(e) {
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
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const redirectUrl = data.redirect || '/kiweb/kiweb2.html';
                    syncSharedTabMarker();
                    if (window.startPageTransition) {
                        window.startPageTransition(redirectUrl);
                    } else {
                        window.location.href = redirectUrl;
                    }
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

        // コード再送
        resendLink.addEventListener('click', async function() {
            if (this.classList.contains('disabled')) return;

            this.classList.add('disabled');
            this.textContent = '送信中...';

            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $csrfToken ?>');
                formData.append('token_id', tokenId);

                const response = await fetch('/kiweb/teacher-auth/api/resend-verify.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    if (data.token_id) {
                        tokenId = data.token_id;
                        const rememberQuery = rememberValue === '1' ? '&remember=1' : '';
                        history.replaceState(null, '', 'verify-code.php?id=' + data.token_id + rememberQuery);
                    }
                    this.textContent = '送信しました';
                    setTimeout(() => {
                        this.textContent = 'コードを再送する';
                        this.classList.remove('disabled');
                    }, 3000);
                } else {
                    setCodeError(data.error || '送信に失敗しました');
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
