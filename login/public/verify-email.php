<?php
/**
 * メール確認ページ（6桁コード入力）
 * デザイン更新版 - identity-verificationスタイル適用
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Auth\EmailAuth;

requireGuest();

$csrfToken = Session::generateCsrfToken();
$error = Session::getFlash('error');
$success = Session::getFlash('success');

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
    <title>メール確認 - 京都医塾</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans JP', 'Inter', sans-serif;
            background-color: #f5f7f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .verification-card {
            width: 100%;
            max-width: 480px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            padding: 48px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        @media (max-width: 520px) {
            .verification-card {
                padding: 32px 24px;
            }
        }

        .logo-section {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
        }

        .logo-section img {
            height: 48px;
            object-fit: contain;
        }

        .heading {
            text-align: center;
            margin-bottom: 12px;
        }

        .heading h1 {
            color: #111827;
            font-size: 24px;
            font-weight: 700;
            line-height: 1.3;
        }

        .description {
            text-align: center;
            margin-bottom: 40px;
            padding: 0 8px;
        }

        .description p {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
        }

        .description .email-highlight {
            color: #374151;
            font-weight: 600;
        }

        .code-input-container {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 32px;
        }

        @media (min-width: 400px) {
            .code-input-container {
                gap: 12px;
            }
        }

        .code-input {
            width: 40px;
            height: 48px;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        @media (min-width: 400px) {
            .code-input {
                width: 48px;
                height: 56px;
                font-size: 24px;
            }
        }

        .code-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .code-input.error {
            border-color: #dc2626;
            color: #dc2626;
        }

        .code-input.error:focus {
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .code-input.filled {
            background-color: #f9fafb;
        }

        .secondary-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 40px;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2563eb;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 12px;
            margin-left: -12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            background: transparent;
            text-decoration: none;
        }

        .action-link:hover {
            background-color: #eff6ff;
            color: #0F3568;
        }

        .action-link.disabled {
            color: #9ca3af;
            cursor: default;
            background: transparent;
        }

        .action-link svg {
            width: 16px;
            height: 16px;
            transition: transform 0.5s ease;
        }

        .action-link:hover svg.rotate-on-hover {
            transform: rotate(180deg);
        }

        .footer-actions {
            display: flex;
            justify-content: flex-end;
        }

        .btn-primary {
            background-color: #0F3568;
            color: white;
            font-weight: 500;
            font-size: 14px;
            padding: 0 24px;
            height: 36px;
            border: none;
            border-radius: 18px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            transition: background-color 0.2s, box-shadow 0.2s, color 0.2s;
            box-shadow: none;
        }

        .btn-primary:hover:not(:disabled) {
            background-color: #0d2f5c;
            box-shadow: 0 1px 2px rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
        }

        .btn-primary:active:not(:disabled) {
            background-color: #0b284f;
            box-shadow: none;
        }

        .btn-primary:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(15, 53, 104, 0.25);
        }

        .btn-primary:disabled {
            background-color: #dadce0;
            color: #5f6368;
            cursor: not-allowed;
            box-shadow: none;
            opacity: 1;
        }

        .error-message {
            display: none;
            align-items: center;
            gap: 8px;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .error-message svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .footer {
            margin-top: 32px;
            text-align: center;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #6b7280;
            font-size: 12px;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #374151;
        }

        .footer-copyright {
            color: #9ca3af;
            font-size: 12px;
        }

        .page-progress {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: transparent;
            pointer-events: none;
            z-index: 5;
        }

        .page-progress .bar {
            display: block;
            width: 100%;
            height: 100%;
            background: #AF1E2B;
            transform: scaleX(0);
            transform-origin: left;
            opacity: 0;
        }

        .kPY6ve {
            position: fixed;
            inset: 0;
            background-color: #ffffff;
            opacity: 0.5;
            z-index: 5;
            pointer-events: none;
        }

        .page-progress.active .bar {
            animation: progress-sweep 0.8s ease-out forwards;
        }

        @keyframes progress-sweep {
            0% {
                transform: scaleX(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            80% {
                transform: scaleX(1);
                opacity: 1;
            }
            100% {
                transform: scaleX(1);
                opacity: 0;
            }
        }
    </style>
</head>

<body>

    <div class="verification-card">
        <div class="page-progress" id="page-progress">
            <span class="bar"></span>
        </div>
        <!-- ロゴ -->
        <div class="logo-section">
            <img src="/kiweb/login/public/京都医塾logo.png" alt="京都医塾" onerror="this.style.display='none'">
        </div>

        <!-- 見出し -->
        <div class="heading">
            <h1>本人確認</h1>
        </div>

        <!-- 説明文 -->
        <div class="description">
            <?php if ($email): ?>
                <p>
                    <span class="email-highlight"><?= htmlspecialchars($email) ?></span> に<br>
                    6桁の確認コードを送信しました。届くまで数分かかる場合があります。
                </p>
            <?php else: ?>
                <p>メールに送信された6桁の確認コードを入力してください。</p>
            <?php endif; ?>
        </div>

        <!-- エラーメッセージ -->
        <div id="error-message" class="error-message">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
            </svg>
            <span id="error-text"></span>
        </div>

        <!-- コード入力フォーム -->
        <form id="verify-form" action="/kiweb/login/api/verify-email.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="token_id" id="token_id" value="<?= htmlspecialchars($tokenId) ?>">
            <input type="hidden" name="code" id="code-hidden">

            <div class="code-input-container">
                <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                    autocomplete="one-time-code" data-index="0">
                <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" data-index="1">
                <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" data-index="2">
                <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" data-index="3">
                <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" data-index="4">
                <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" data-index="5">
            </div>

            <!-- セカンダリアクション -->
            <div class="secondary-actions">
                <button type="button" id="resend-link" class="action-link">
                    <svg class="rotate-on-hover" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 4v6h-6"></path>
                        <path d="M1 20v-6h6"></path>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    コードを再送する
                </button>
                <a href="login.php" class="action-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    別の方法を試す
                </a>
            </div>

            <!-- 送信ボタン -->
            <div class="footer-actions">
                <button type="submit" class="btn-primary" id="submit-btn">
                    次へ
                </button>
            </div>
        </form>
    </div>

    <!-- フッター -->
    <div class="footer">
        <div class="footer-links">
            <a href="#">プライバシー</a>
            <a href="#">利用規約</a>
            <a href="#">ヘルプ</a>
        </div>
        <p class="footer-copyright">© 2024 京都医塾. All rights reserved.</p>
    </div>

    <script src="/kiweb/login/public/assets/js/progress.js"></script>
    <script>
        const codeInputs = document.querySelectorAll('.code-input');
        const codeHidden = document.getElementById('code-hidden');
        const form = document.getElementById('verify-form');
        const submitBtn = document.getElementById('submit-btn');
        const errorMessage = document.getElementById('error-message');
        const errorText = document.getElementById('error-text');
        const resendLink = document.getElementById('resend-link');
        let tokenId = document.getElementById('token_id').value;

        // エラー表示のリセット
        function resetInputError() {
            codeInputs.forEach(input => {
                input.classList.remove('error');
                if (input.value) {
                    input.classList.add('filled');
                } else {
                    input.classList.remove('filled');
                }
            });
        }

        // コード入力のハンドリング
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', function (e) {
                resetInputError();
                hideError();

                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;

                if (value) {
                    e.target.classList.add('filled');
                    if (index < 5) {
                        codeInputs[index + 1].focus();
                    }
                }

                updateHiddenCode();
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });

            input.addEventListener('focus', function () {
                this.select();
            });

            input.addEventListener('paste', function (e) {
                e.preventDefault();
                resetInputError();
                hideError();

                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);

                pastedData.split('').forEach((char, i) => {
                    if (codeInputs[i]) {
                        codeInputs[i].value = char;
                        codeInputs[i].classList.add('filled');
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

        function showError(message) {
            errorText.textContent = message;
            errorMessage.style.display = 'flex';
            codeInputs.forEach(input => input.classList.add('error'));
        }

        function hideError() {
            errorMessage.style.display = 'none';
        }

        // フォーム送信
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            hideError();

            const code = codeHidden.value;
            if (code.length !== 6) {
                showError('6桁のコードを入力してください');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = '確認中...';

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const redirectUrl = data.redirect || 'portal/';
                    if (window.startPageTransition) {
                        window.startPageTransition(redirectUrl);
                    } else {
                        window.location.href = redirectUrl;
                    }
                } else {
                    showError(data.error || 'エラーが発生しました');
                    submitBtn.disabled = false;
                    submitBtn.textContent = '次へ';
                }
            } catch (error) {
                showError('通信エラーが発生しました');
                submitBtn.disabled = false;
                submitBtn.textContent = '次へ';
            }
        });

        // コード再送
        resendLink.addEventListener('click', async function () {
            if (this.classList.contains('disabled')) return;

            this.classList.add('disabled');
            const originalText = this.innerHTML;
            this.innerHTML = '<svg class="rotate-on-hover" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>送信中...';

            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $csrfToken ?>');
                formData.append('token_id', tokenId);

                const response = await fetch('/api/resend-verify.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    if (data.token_id) {
                        tokenId = data.token_id;
                        document.getElementById('token_id').value = data.token_id;
                        history.replaceState(null, '', 'verify-email.php?id=' + data.token_id);
                    }
                    this.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg>送信しました！';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('disabled');
                    }, 3000);
                } else {
                    showError(data.error || '再送に失敗しました');
                    this.innerHTML = originalText;
                    this.classList.remove('disabled');
                }
            } catch (error) {
                showError('通信エラーが発生しました');
                this.innerHTML = originalText;
                this.classList.remove('disabled');
            }
        });

        // 最初の入力欄にフォーカス
        codeInputs[0].focus();
    </script>
</body>

</html>
