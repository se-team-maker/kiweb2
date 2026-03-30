<?php
/**
 * ログイン方法選択ページ
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;

requireGuest();

$csrfToken = Session::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン方法を選択 - 京都医塾</title>
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

            <div class="login-header">
                <img src="/kiweb/login/public/京都医塾logo.png" alt="京都医塾" class="logo">
                <h1>ログイン方法を選択</h1>
                <p class="subtitle">京都医塾アカウントを使用</p>
            </div>

            <ul class="method-list" aria-label="ログイン方法">
                <li class="method-item">
                    <a href="login.php?mode=passkey">
                        <svg class="method-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path
                                d="M12 1C8.14 1 5 4.14 5 8a7 7 0 004.5 6.53V21a1 1 0 001 1h3a1 1 0 001-1v-6.47A7 7 0 0019 8c0-3.86-3.14-7-7-7zm0 11a4 4 0 110-8 4 4 0 010 8z" />
                            <path
                                d="M6.75 14c-.69 0-1.25.56-1.25 1.25v4.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-4.5c0-.69-.56-1.25-1.25-1.25H6.75zM12 15.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5.67-1.5 1.5-1.5z"
                                opacity=".3" />
                        </svg>
                        <span class="method-text">パスキーを使用</span>
                    </a>
                </li>
                <li class="method-item">
                    <a href="login.php?mode=email">
                        <svg class="method-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path
                                d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z" />
                        </svg>
                        <span class="method-text">メールでログイン（コード認証）</span>
                    </a>
                </li>
            </ul>

            <div class="action-area" style="margin-top: 32px;">
                <a href="login.php" class="text-btn">戻る</a>
                <div></div>
            </div>
        </div>
    </div>

    <script src="/kiweb/login/public/assets/js/progress.js"></script>
</body>

</html>
