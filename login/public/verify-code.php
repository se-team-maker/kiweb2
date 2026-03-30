<?php
/**
 * 認証コード入力ページ
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;

requireGuest();

$tokenId = $_GET['id'] ?? '';
if (empty($tokenId)) {
    header('Location: /login.php');
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
    <link rel="stylesheet" href="/kiweb/login/public/assets/css/style.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="page-progress" id="page-progress">
                <span class="bar"></span>
            </div>
            <div class="login-header">
                <img src="/kiweb/login/public/京都医塾logo.png" alt="京都医塾" class="logo">
                <h1>認証コードを入力</h1>
                <p class="subtitle">メールに送信された6桁のコードを入力してください</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form id="verify-form" action="/kiweb/login/api/verify-code.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="token_id" value="<?= htmlspecialchars($tokenId) ?>">

                <div class="code-input-container">
                    <input type="text" class="code-input" maxlength="1" data-index="0" inputmode="numeric"
                        autocomplete="one-time-code">
                    <input type="text" class="code-input" maxlength="1" data-index="1" inputmode="numeric">
                    <input type="text" class="code-input" maxlength="1" data-index="2" inputmode="numeric">
                    <input type="text" class="code-input" maxlength="1" data-index="3" inputmode="numeric">
                    <input type="text" class="code-input" maxlength="1" data-index="4" inputmode="numeric">
                    <input type="text" class="code-input" maxlength="1" data-index="5" inputmode="numeric">
                </div>
                <input type="hidden" name="code" id="full-code">

                <div class="timer" id="timer">
                    有効期限: <span id="countdown">10:00</span>
                </div>

                <button type="submit" class="btn btn-primary btn-full" id="verify-btn">
                    確認
                </button>
            </form>

            <div class="login-footer">
                <p class="form-hint">
                    コードが届かない場合は、迷惑メールフォルダをご確認ください。
                </p>
                <p style="margin-top: 16px;">
                    <a href="/login.php">ログイン画面に戻る</a>
                </p>
            </div>
        </div>
    </div>

    <script src="/kiweb/login/public/assets/js/progress.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputs = document.querySelectorAll('.code-input');
            const fullCodeInput = document.getElementById('full-code');
            const verifyBtn = document.getElementById('verify-btn');
            const form = document.getElementById('verify-form');
            const countdownEl = document.getElementById('countdown');
            const timerEl = document.getElementById('timer');

            // カウントダウン（10分）
            let remainingSeconds = 10 * 60;

            const countdownInterval = setInterval(() => {
                remainingSeconds--;

                if (remainingSeconds <= 0) {
                    clearInterval(countdownInterval);
                    timerEl.classList.add('expired');
                    countdownEl.textContent = '期限切れ';
                    verifyBtn.disabled = true;
                    return;
                }

                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = remainingSeconds % 60;
                countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                if (remainingSeconds <= 60) {
                    timerEl.classList.add('warning');
                }
            }, 1000);

            // 入力処理
            inputs.forEach((input, index) => {
                input.addEventListener('input', (e) => {
                    const value = e.target.value;

                    // 数字のみ許可
                    if (!/^\d*$/.test(value)) {
                        e.target.value = '';
                        return;
                    }

                    // 次の入力欄にフォーカス
                    if (value && index < 5) {
                        inputs[index + 1].focus();
                    }

                    updateFullCode();
                });

                input.addEventListener('keydown', (e) => {
                    // Backspace で前の入力欄に戻る
                    if (e.key === 'Backspace' && !input.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });

                // ペースト対応
                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');

                    for (let i = 0; i < Math.min(pastedData.length, 6); i++) {
                        inputs[i].value = pastedData[i];
                    }

                    const focusIndex = Math.min(pastedData.length, 5);
                    inputs[focusIndex].focus();
                    updateFullCode();
                });
            });

            function updateFullCode() {
                const code = Array.from(inputs).map(i => i.value).join('');
                fullCodeInput.value = code;
                if (code.length === 6) {
                    form.requestSubmit();
                }
            }

            // フォーム送信
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const code = fullCodeInput.value;
                if (code.length !== 6) {
                    showError('6桁のコードを入力してください');
                    return;
                }

                verifyBtn.disabled = true;
                verifyBtn.innerHTML = '<span class="loading"></span> 確認中...';

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        const redirectUrl = result.redirect || '/dashboard.php';
                        if (window.startPageTransition) {
                            window.startPageTransition(redirectUrl);
                        } else {
                            window.location.href = redirectUrl;
                        }
                    } else {
                        showError(result.error || '認証に失敗しました');
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = '確認';
                    }
                } catch (error) {
                    showError('通信エラーが発生しました');
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = '確認';
                }
            });

            function showError(message) {
                const existingAlert = document.querySelector('.alert-error');
                if (existingAlert) existingAlert.remove();

                const alert = document.createElement('div');
                alert.className = 'alert alert-error';
                alert.textContent = message;

                const header = document.querySelector('.login-header');
                header.after(alert);
            }

            // 最初の入力欄にフォーカス
            inputs[0].focus();
        });
    </script>
</body>

</html>
