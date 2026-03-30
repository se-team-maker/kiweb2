<?php
/**
 * 繧ｵ繧､繝ｳ繧｢繝・・繝壹・繧ｸ
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;

requireGuest();

$csrfToken = Session::generateCsrfToken();
$error = Session::getFlash('error');
$success = Session::getFlash('success');

// URLパラメータから名前を取得
$nameParam = trim($_GET['name'] ?? '');
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アカウント作成 - 京都医塾</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
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
                <?php if ($nameParam): ?>
                    <h1><?= htmlspecialchars($nameParam) ?>さん、ようこそ！</h1>
                    <p class="subtitle">メールアドレスを登録してください</p>
                <?php else: ?>
                    <h1>アカウント作成</h1>
                <?php endif; ?>
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

            <form id="signup-form" action="/kiweb/teacher-auth/api/register.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <?php if ($nameParam): ?>
                    <input type="hidden" name="name" value="<?= htmlspecialchars($nameParam) ?>">
                <?php else: ?>
                    <div class="md-text-field">
                        <input type="text" id="name" name="name" autocomplete="name">
                        <label for="name">氏名</label>
                    </div>
                    <div class="field-error" id="error-name" role="alert" aria-live="polite"></div>
                <?php endif; ?>

                <div class="md-text-field">
                    <input type="email" id="email" name="email" autocomplete="email">
                    <label for="email">メールアドレス</label>
                </div>
                <div class="field-error" id="error-email" role="alert" aria-live="polite"></div>

                <div class="md-text-field">
                    <input type="password" id="password" name="password" autocomplete="new-password">
                    <label for="password">パスワード</label>
                </div>
                <div class="field-error" id="error-password" role="alert" aria-live="polite"></div>

                <div class="md-text-field">
                    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password">
                    <label for="password_confirm">パスワード（確認）</label>
                </div>
                <div class="field-error" id="error-password_confirm" role="alert" aria-live="polite"></div>

                <p style="font-size: 12px; color: #5f6368; margin-bottom: 24px;">
                    パスワードは半角英数記号で入力してください
                </p>

                <div class="action-area">
                    <button type="submit" class="btn-primary ripple-btn">次へ</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/kiweb/teacher-auth/public/assets/js/progress.js"></script>
    <script src="/kiweb/teacher-auth/public/assets/js/login.js"></script>
</body>

</html>
