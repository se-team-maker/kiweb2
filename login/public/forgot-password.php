<?php
/**
 * パスワード再設定要求ページ
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;

requireGuest();

$csrfToken = Session::generateCsrfToken();
$error = Session::getFlash('error');
$success = Session::getFlash('success');
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードを忘れた場合 - 京都医塾</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/kiweb/login/public/assets/css/style.css">
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
                <h1>パスワードの再設定</h1>
                <p class="subtitle">アカウントのメールアドレスを入力してください</p>
            </div>

            <!-- パスワード再設定要求フォーム -->
            <form id="forgot-form" action="/kiweb/login/api/password-reset-request.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <p style="margin-bottom: 24px; color: #5f6368; font-size: 14px;">
                    登録されているメールアドレスに、パスワード再設定用のコードを送信します。
                </p>

                <div class="md-text-field">
                    <input type="email" id="email" name="email" autocomplete="email">
                    <label for="email">メールアドレス</label>
                </div>
                <div class="field-error" id="error-email" role="alert" aria-live="polite"></div>

                <!-- アクションエリア -->
                <div class="action-area">
                    <a href="login.php" class="text-btn">
                        ログインに戻る
                    </a>
                    <button type="submit" class="btn-primary ripple-btn" id="submit-btn">
                        次へ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="/kiweb/login/public/assets/js/progress.js"></script>
    <script src="/kiweb/login/public/assets/js/login.js"></script>
</body>

</html>
