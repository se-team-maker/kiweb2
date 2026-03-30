<?php
/**
 * 管理画面 - メインページ (Apple School Manager風)
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

requireAuth();

$userId = Session::getUserId();
$user = User::findById($userId);
$canManageUsers = $user && $user->hasPermission('manage_users');
$canViewAuditLogs = $user && $user->hasPermission('view_audit_logs');

// 管理者権限チェック
if (!$user || (!$canManageUsers && !$canViewAuditLogs)) {
    header('Location: /kiweb/teacher-auth/public/dashboard.php');
    exit;
}

$csrfToken = Session::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - 京都医塾</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/kiweb/teacher-auth/public/assets/css/admin.css">
</head>

<body>
    <div class="admin-layout">
        <!-- モバイル用: ポータルに戻るボタン -->
        <a href="/kiweb/kiweb2.html" class="mobile-portal-back" aria-label="ポータルに戻る">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>戻る</span>
        </a>
        <!-- サイドバーナビゲーション -->
        <nav class="admin-sidebar no-scrollbar glass-panel">
            <div class="sidebar-section">
                <div class="sidebar-section-title">管理</div>
                <div class="sidebar-nav glass-panel glass-compact">
                    <a href="#" class="nav-item active" data-section="users">ユーザー管理</a>
                </div>
            </div>
            
            <div class="sidebar-divider"></div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">権限</div>
                <div class="sidebar-nav glass-panel glass-compact">
                    <a href="#" class="nav-item" data-section="roles">役職・権限管理</a>
                </div>
            </div>
            
            <div class="sidebar-divider"></div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">組織</div>
                <div class="sidebar-nav glass-panel glass-compact">
                    <a href="#" class="nav-item" data-section="scopes">校舎・部署管理</a>
                </div>
            </div>

            <div class="sidebar-divider"></div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">ログ</div>
                <div class="sidebar-nav glass-panel glass-compact">
                    <a href="#" class="nav-item" data-section="access-logs">アクセスログ</a>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <div class="sidebar-divider"></div>
                <a href="/kiweb/teacher-auth/public/portal/" class="nav-item">← 戻る</a>
            </div>
        </nav>

        <!-- リストパネル -->
        <div class="list-panel no-scrollbar glass-panel">
            <!-- ユーザーリスト -->
            <div id="list-users" class="list-section">
                <div class="list-header">
                    <h2>ユーザー</h2>
                    <div class="list-header-actions">
                        <button class="btn-add" id="btn-add-user" title="新規ユーザー">+</button>
                    </div>
                </div>
                <div class="list-search">
                    <input type="text" id="user-search" placeholder="検索...">
                </div>
                <div class="list-items glass-panel glass-compact" id="users-list">
                    <!-- JavaScriptで動的に生成 -->
                </div>
                <div class="pagination" id="users-pagination"></div>
            </div>

            <!-- 役職リスト -->
            <div id="list-roles" class="list-section">
                <div class="list-header">
                    <h2>役職</h2>
                    <button class="btn-add" id="btn-add-role" title="新規役職">+</button>
                </div>
                <div class="list-items glass-panel glass-compact" id="roles-list">
                    <!-- JavaScriptで動的に生成 -->
                </div>
            </div>

            <!-- スコープリスト -->
            <div id="list-scopes" class="list-section">
                <div class="list-header">
                    <h2>校舎・部署</h2>
                    <button class="btn-add" id="btn-add-scope" title="新規追加">+</button>
                </div>
                <div class="list-items glass-panel glass-compact" id="scopes-list">
                    <!-- JavaScriptで動的に生成 -->
                </div>
            </div>

            <!-- アクセスログフィルタ -->
            <div id="list-access-logs" class="list-section">
                <div class="list-header">
                    <h2>アクセスログ</h2>
                </div>
                <div class="list-search access-log-filter-panel">
                    <div class="form-group">
                        <label for="log-date-from">開始日</label>
                        <input type="date" id="log-date-from">
                    </div>
                    <div class="form-group">
                        <label for="log-date-to">終了日</label>
                        <input type="date" id="log-date-to">
                    </div>
                    <div class="form-group">
                        <label for="log-user-search">ユーザー検索</label>
                        <input type="text" id="log-user-search" placeholder="名前・メール">
                    </div>
                    <div class="form-group">
                        <label for="log-page-path">ページパス</label>
                        <input type="text" id="log-page-path" placeholder="例: kiweb2.html">
                    </div>
                    <div class="access-log-filter-actions">
                        <button type="button" class="btn btn-primary" id="btn-search-access-logs">検索</button>
                        <button type="button" class="btn btn-secondary" id="btn-clear-access-logs">クリア</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 詳細パネル -->
        <main class="detail-panel no-scrollbar glass-panel">
            <div class="mobile-detail-bar">
                <button type="button" class="mobile-back-btn" id="mobile-back-btn" aria-label="一覧に戻る">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M14.8 5.2 L8.2 12 L14.8 18.8"
                              fill="none"
                              stroke="currentColor"
                              stroke-width="4.5"
                              stroke-linecap="round"
                              stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
            <!-- ユーザー詳細 -->
            <div id="detail-users" class="detail-section-container">
                <div class="empty-state" id="user-empty-state">
                    <span class="material-symbols-outlined empty-state-icon">person</span>
                    <p>左のリストからユーザーを選択してください</p>
                </div>
                <div class="detail-content glass-panel" id="user-detail" style="display: none;">
                    <!-- JavaScriptで動的に生成 -->
                </div>
            </div>

            <!-- 役職詳細 -->
            <div id="detail-roles" class="detail-section-container">
                <div class="empty-state" id="role-empty-state">
                    <span class="material-symbols-outlined empty-state-icon">shield_person</span>
                    <p>左のリストから役職を選択してください</p>
                </div>
                <div class="detail-content glass-panel" id="role-detail" style="display: none;">
                    <!-- JavaScriptで動的に生成 -->
                </div>
            </div>

            <!-- スコープ詳細 -->
            <div id="detail-scopes" class="detail-section-container">
                <div class="empty-state" id="scope-empty-state">
                    <span class="material-symbols-outlined empty-state-icon">location_on</span>
                    <p>左のリストからスコープを選択してください</p>
                </div>
                <div class="detail-content glass-panel" id="scope-detail" style="display: none;">
                    <!-- JavaScriptで動的に生成 -->
                </div>
            </div>

            <!-- アクセスログ詳細 -->
            <div id="detail-access-logs" class="detail-section-container">
                <div class="detail-content glass-panel access-log-content">
                    <div class="detail-header access-log-header">
                        <div class="detail-icon" style="background: var(--icon-green); color: white;">
                            <span class="material-symbols-outlined">receipt_long</span>
                        </div>
                        <h1 class="detail-title">アクセスログ</h1>
                        <div class="detail-actions">
                            <span class="role-tag" id="access-logs-total-label">全 0 件</span>
                        </div>
                    </div>

                    <div class="detail-divider"></div>

                    <div class="detail-section">
                        <div class="access-log-table-wrap">
                            <table class="data-table access-log-table">
                                <thead>
                                    <tr>
                                        <th>日時</th>
                                        <th>ユーザー</th>
                                        <th>メール</th>
                                        <th>ページ</th>
                                        <th>IP</th>
                                        <th>User-Agent</th>
                                    </tr>
                                </thead>
                                <tbody id="access-logs-tbody">
                                    <tr>
                                        <td colspan="6" class="access-log-empty-cell">検索条件を指定して「検索」を押してください。</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination" id="access-logs-pagination"></div>
                    </div>
                </div>
            </div>

            <!-- 権限一覧 (役職選択時に下部に表示) -->
            <div id="permissions-section" style="display: none;">
                <div class="detail-content glass-panel">
                    <div class="detail-divider"></div>
                    <div class="detail-section">
                        <div class="detail-section-label">権限一覧</div>
                        <div id="permissions-list">
                            <!-- JavaScriptで動的に生成 -->
                        </div>
                        <button class="btn btn-secondary" id="btn-add-permission" style="margin-top: 16px;">
                            + 新規権限を追加
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ユーザー編集モーダル -->
    <div class="modal" id="user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="user-modal-title">ユーザー編集</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="user-form">
                <input type="hidden" id="user-id">
                <div class="form-group">
                    <label for="user-name">名前</label>
                    <input type="text" id="user-name" placeholder="名前を入力">
                </div>
                <div class="form-group">
                    <label for="user-email">メールアドレス</label>
                    <input type="email" id="user-email" placeholder="メールアドレスを入力" required>
                </div>
                <div class="form-group" id="user-password-group">
                    <label for="user-password">パスワード（新規作成時のみ）</label>
                    <input type="password" id="user-password" placeholder="パスワードを入力">
                </div>
                <div class="form-group">
                    <label for="user-status">ステータス</label>
                    <select id="user-status">
                        <option value="active">有効</option>
                        <option value="locked">ロック</option>
                        <option value="deleted">削除済み</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>役職</label>
                    <div id="user-roles-checkboxes" class="checkbox-group">
                        <!-- JavaScriptで動的に生成 -->
                    </div>
                    <div id="user-role-lock-notice" style="display: none; margin-top: 8px; font-size: 12px; color: var(--text-secondary);">
                        自分自身の役職は変更できません。
                    </div>
                </div>
                <div class="form-group">
                    <label>担当範囲</label>
                    <div id="user-scopes-checkboxes" class="checkbox-group">
                        <!-- JavaScriptで動的に生成 -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">キャンセル</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 役職編集モーダル -->
    <div class="modal" id="role-modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3 id="role-modal-title">役職追加</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="role-form">
                <input type="hidden" id="role-id">
                <div class="form-group">
                    <label for="role-name">役職名（英語）</label>
                    <input type="text" id="role-name" placeholder="例: full_time_teacher" required>
                </div>
                <div class="form-group">
                    <label for="role-description">説明</label>
                    <input type="text" id="role-description" placeholder="例: 専任">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">キャンセル</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 権限編集モーダル -->
    <div class="modal" id="permission-modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3 id="permission-modal-title">権限追加</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="permission-form">
                <input type="hidden" id="permission-id">
                <div class="form-group">
                    <label for="permission-name">権限名</label>
                    <input type="text" id="permission-name" placeholder="例: manage_users" required>
                </div>
                <div class="form-group">
                    <label for="permission-description">説明</label>
                    <input type="text" id="permission-description" placeholder="例: ユーザー管理">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">キャンセル</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- スコープ編集モーダル -->
    <div class="modal" id="scope-modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3 id="scope-modal-title">スコープ追加</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="scope-form">
                <input type="hidden" id="scope-id">
                <div class="form-group">
                    <label for="scope-type">種類</label>
                    <select id="scope-type" required>
                        <!-- JavaScriptで動的に生成 -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="scope-name">コード名（英語）</label>
                    <input type="text" id="scope-name" placeholder="例: shijo_karasuma" required>
                </div>
                <div class="form-group">
                    <label for="scope-display-name">表示名</label>
                    <input type="text" id="scope-display-name" placeholder="例: 四条烏丸" required>
                </div>
                <div class="form-group">
                    <label for="scope-description">説明</label>
                    <input type="text" id="scope-description" placeholder="例: 四条烏丸校舎">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">キャンセル</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 削除確認モーダル -->
    <div class="modal" id="confirm-modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3>確認</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p id="confirm-message">本当に削除しますか？</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close">キャンセル</button>
                <button type="button" class="btn btn-danger" id="confirm-btn">削除</button>
            </div>
        </div>
    </div>

    <script>
        window.csrfToken = '<?= $csrfToken ?>';
        window.currentUserId = '<?= htmlspecialchars($userId, ENT_QUOTES, 'UTF-8') ?>';
        window.adminPermissions = {
            manageUsers: <?= $canManageUsers ? 'true' : 'false' ?>,
            viewAuditLogs: <?= $canViewAuditLogs ? 'true' : 'false' ?>
        };
    </script>
    <script src="/kiweb/teacher-auth/public/assets/js/admin.js"></script>

    <svg style="position: absolute; width: 0; height: 0;" aria-hidden="true" focusable="false">
        <filter id="glass-distortion" x="0%" y="0%" width="100%" height="100%" filterUnits="objectBoundingBox">
            <feTurbulence type="fractalNoise" baseFrequency="0.01 0.01" numOctaves="1" seed="5" result="turbulence" />
            <feComponentTransfer in="turbulence" result="mapped">
                <feFuncR type="gamma" amplitude="1" exponent="10" offset="0.5" />
                <feFuncG type="gamma" amplitude="0" exponent="1" offset="0" />
                <feFuncB type="gamma" amplitude="0" exponent="1" offset="0.5" />
            </feComponentTransfer>
            <feGaussianBlur in="turbulence" stdDeviation="3" result="softMap" />
            <feSpecularLighting in="softMap" surfaceScale="5" specularConstant="1" specularExponent="100" lighting-color="white" result="specLight">
                <fePointLight x="-200" y="-200" z="300" />
            </feSpecularLighting>
            <feComposite in="specLight" operator="arithmetic" k1="0" k2="1" k3="1" k4="0" result="litImage" />
            <feDisplacementMap in="SourceGraphic" in2="softMap" scale="150" xChannelSelector="R" yChannelSelector="G" />
        </filter>
    </svg>
</body>
</html>
