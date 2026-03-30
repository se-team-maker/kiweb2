<?php
/**
 * ログインページ (Google風)
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
    <title>ログイン - 京都医塾</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/kiweb/login/public/assets/css/style.css">
    <style>
        .fade-enter {
            opacity: 0;
        }

        .fade-enter-active {
            opacity: 1;
            transition: opacity 200ms ease-in;
        }

        .fade-exit {
            opacity: 1;
        }

        .fade-exit-active {
            opacity: 0;
            transition: opacity 200ms ease-in;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="page-progress" id="page-progress">
                <span class="bar"></span>
            </div>
            <div class="login-header">
                <img src="/kiweb/login/public/京都医塾logo.png" alt="京都医塾" class="logo">
                <h1 id="page-title">ログイン</h1>
                <p class="subtitle" id="page-subtitle">京都医塾アカウントを使用</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg style="width:20px;height:20px;margin-right:8px;fill:currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <div id="auth-content">
                <form id="password-form" class="auth-form active" action="/kiweb/login/api/login.php" method="POST"
                    novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="md-text-field">
                        <input type="text" id="email" name="email" autocomplete="username">
                        <label for="email">ID</label>
                    </div>
                    <div class="field-error" id="error-email" role="alert" aria-live="polite"></div>

                    <div class="md-text-field">
                        <input type="password" id="password" name="password" autocomplete="current-password">
                        <label for="password">パスワード</label>
                    </div>
                    <div class="field-error" id="error-password" role="alert" aria-live="polite"></div>

                    <div class="action-area" style="justify-content: flex-end;">
                        <!-- <a href="/kiweb/login/public/choose-login.php" class="text-btn">別の方法を試す</a> -->
                        <button type="submit" class="btn-primary ripple-btn">次へ</button>
                    </div>

                    <!-- アカウント作成・パスワード忘れリンク非表示
                    <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
                        <a href="signup.php" class="text-btn" style="padding-left:0;">
                            アカウントを作成
                        </a>
                        <a href="forgot-password.php" class="text-btn" style="padding-right:0;">
                            パスワードを忘れた場合
                        </a>
                    </div>
                    -->
                </form>

                <form id="email-form" class="auth-form" action="/kiweb/login/api/email-login.php" method="POST"
                    novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <p style="margin-bottom:24px; color:#5f6368; font-size:14px;">
                        登録済みのメールアドレスを入力してください。認証コードを送信します。
                    </p>

                    <div class="md-text-field">
                        <input type="email" id="email-login" name="email" autocomplete="email">
                        <label for="email-login">メールアドレス</label>
                    </div>
                    <div class="field-error" id="error-email-login" role="alert" aria-live="polite"></div>

                    <div class="action-area">
                        <!-- <a href="/kiweb/login/public/choose-login.php" class="text-btn">別の方法を試す</a> -->
                        <button type="submit" class="btn-primary ripple-btn">次へ</button>
                    </div>
                </form>

                <div id="passkey-form" class="auth-form">
                    <p style="margin-bottom:24px; color:#5f6368; font-size:14px;">
                        デバイスの生体認証（指紋、顔）またはPINを使用してログインします。
                    </p>

                    <div class="md-text-field">
                        <input type="email" id="passkey-email" autocomplete="email">
                        <label for="passkey-email">メールアドレス</label>
                    </div>
                    <div class="field-error" id="error-passkey-email" role="alert" aria-live="polite"></div>

                    <button type="button" id="passkey-login-btn" class="passkey-btn ripple-btn">
                        <svg style="width:20px;height:20px;fill:currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                            <path
                                d="M6.75 14c-.69 0-1.25.56-1.25 1.25v4.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-4.5c0-.69-.56-1.25-1.25-1.25H6.75zM12 15.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5.67-1.5 1.5-1.5z"
                                opacity=".3" />
                        </svg>
                        パスキーでログイン
                    </button>

                    <!-- <div class="action-area" style="margin-top:40px;">
                        <a href="/kiweb/login/public/choose-login.php" class="text-btn">別の方法を試す</a>
                    </div> -->
                </div>
            </div>

            <div class="login-footer">
                <div class="lang-select">
                    <button type="button" class="lang-button" id="lang-toggle" aria-expanded="false"
                        aria-haspopup="listbox">
                        <span class="lang-label">日本語</span>
                        <span class="lang-caret" aria-hidden="true"></span>
                    </button>
                    <div class="lang-menu" id="lang-menu" role="listbox" hidden>
                        <button type="button" data-lang="ja">日本語</button>
                        <button type="button" data-lang="en">English</button>
                        <button type="button" data-lang="zh">中文</button>
                        <button type="button" data-lang="ko">한국어</button>
                    </div>
                </div>
                <div class="footer-links">
                    <a href="#">プライバシー</a>
                    <a href="#">利用規約</a>
                    <a href="#">ヘルプ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="/kiweb/login/public/assets/js/progress.js"></script>
    <script src="/kiweb/login/public/assets/js/login.js"></script>
</body>

</html>