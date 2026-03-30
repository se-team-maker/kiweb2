<?php
/**
 * ログインページ (Google風)
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;

requireGuest();

$nameParam = trim($_GET['name'] ?? '');
if ($nameParam !== '') {
    $query = 'name=' . urlencode($nameParam);
    header('Location: /kiweb/teacher-auth/public/signup.php?' . $query);
    exit;
}

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
    <link rel="stylesheet" href="/kiweb/teacher-auth/public/assets/css/style.css">
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
                <img src="/kiweb/teacher-auth/public/京都医塾logo.png" alt="京都医塾" class="logo">
                <h1 id="page-title">ログイン</h1>
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
                <form id="password-form" class="auth-form active" action="/kiweb/teacher-auth/api/login.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="md-text-field">
                        <input type="email" id="email" name="email" autocomplete="username" inputmode="email" autocapitalize="none" autocorrect="off" spellcheck="false">
                        <label for="email">メールアドレス</label>
                    </div>
                    <div class="field-error" id="error-email" role="alert" aria-live="polite"></div>

                    <div class="md-text-field">
                        <input type="password" id="password" name="password" autocomplete="current-password">
                        <label for="password">パスワード</label>
                    </div>
                    <div class="field-error" id="error-password" role="alert" aria-live="polite"></div>

                    <div class="remember-row">
                        <input type="hidden" name="remember" value="0">
                        <div class="remember-control">
                            <label class="remember-label" for="remember-login">
                                <input type="checkbox" id="remember-login" name="remember" value="1" aria-describedby="remember-support">
                                <span class="remember-text">ログイン状態を保持する（個人端末のみ）</span>
                            </label>
                            <p class="remember-support" id="remember-support">共用端末ではチェックしないでください。</p>
                        </div>
                    </div>

                    <div class="action-area">
                        <div></div>
                        <button type="submit" class="btn-primary ripple-btn">次へ</button>
                    </div>

                    <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
                        <a href="forgot-password.php" class="text-btn" style="padding-right:0;">
                            パスワードを忘れた場合
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/kiweb/teacher-auth/public/assets/js/progress.js"></script>
    <script src="/kiweb/teacher-auth/public/assets/js/login.js"></script>
</body>

</html>
