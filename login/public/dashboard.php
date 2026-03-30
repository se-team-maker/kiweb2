<?php
/**
 * ダッシュボード
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Model\User;
use App\Config\Database;

requireAuth();

$userId = Session::getUserId();
$user = User::findById($userId);

if (!$user || !$user->isActive()) {
    Session::destroy();
    header('Location: /login.php');
    exit;
}

$roles = $user->getRoles();
$csrfToken = Session::generateCsrfToken();

// パスキー一覧を取得
$db = Database::getConnection();
$stmt = $db->prepare('
    SELECT id, device_name, created_at, last_used_at
    FROM webauthn_credentials
    WHERE user_id = ?
    ORDER BY created_at DESC
');
$stmt->execute([$userId]);
$passkeys = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード - 京都医塾</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/kiweb/login/public/assets/css/style.css">
    <style>
        body {
            background: var(--bg-secondary);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>ダッシュボード</h1>
            <div class="user-info">
                <span class="user-email">
                    <?= htmlspecialchars($user->email) ?>
                </span>
                <a href="/logout.php" class="btn btn-secondary">ログアウト</a>
            </div>
        </header>

        <div class="dashboard-card">
            <h2>
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                    <path
                        d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                </svg>
                アカウント情報
            </h2>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: var(--text-secondary);">メールアドレス</td>
                    <td style="padding: 8px 0;">
                        <?= htmlspecialchars($user->email) ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: var(--text-secondary);">ロール</td>
                    <td style="padding: 8px 0;">
                        <?= htmlspecialchars(implode(', ', $roles) ?: 'なし') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: var(--text-secondary);">登録日</td>
                    <td style="padding: 8px 0;">
                        <?= date('Y年n月j日', strtotime($user->createdAt)) ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dashboard-card">
            <h2>
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                    <path
                        d="M12 1C8.14 1 5 4.14 5 8a7 7 0 004.5 6.53V21a1 1 0 001 1h3a1 1 0 001-1v-6.47A7 7 0 0019 8c0-3.86-3.14-7-7-7zm0 11a4 4 0 110-8 4 4 0 010 8z" />
                </svg>
                パスキー
            </h2>

            <?php if (empty($passkeys)): ?>
                <p style="color: var(--text-secondary); margin-bottom: 16px;">
                    パスキーが登録されていません。パスキーを登録すると、指紋やFace IDで安全にログインできます。
                </p>
            <?php else: ?>
                <ul class="passkey-list">
                    <?php foreach ($passkeys as $passkey): ?>
                        <li class="passkey-item">
                            <div>
                                <div class="passkey-name">
                                    <?= htmlspecialchars($passkey['device_name'] ?: 'パスキー') ?>
                                </div>
                                <div class="passkey-date">
                                    登録:
                                    <?= date('Y/n/j', strtotime($passkey['created_at'])) ?>
                                    <?php if ($passkey['last_used_at']): ?>
                                        ・最終使用:
                                        <?= date('Y/n/j', strtotime($passkey['last_used_at'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm delete-passkey"
                                data-id="<?= htmlspecialchars($passkey['id']) ?>">
                                削除
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <button type="button" id="register-passkey-btn" class="btn btn-primary" style="margin-top: 16px;">
                パスキーを登録
            </button>
        </div>
    </div>

    <script src="/kiweb/login/public/assets/js/progress.js"></script>
    <script src="/kiweb/login/public/assets/js/dashboard.js"></script>
    <script>
        window.csrfToken = '<?= $csrfToken ?>';
    </script>
</body>

</html>
