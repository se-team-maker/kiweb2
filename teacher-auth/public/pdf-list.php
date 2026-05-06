<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Config\Database;
use App\Model\User;

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function requirePdfUser(): array
{
    if (!Session::isLoggedIn()) {
        http_response_code(401);
        exit('Unauthorized');
    }

    $userId = Session::getUserId();
    $user = $userId ? User::findById($userId) : null;

    if (!$user || !$user->isActive()) {
        Session::destroy();
        http_response_code(401);
        exit('Unauthorized');
    }

    return [$user, (string) $userId];
}

function normalizeRoleNames(mixed $rawRoles): array
{
    if (!is_array($rawRoles)) {
        return [];
    }

    $roles = [];
    foreach ($rawRoles as $role) {
        if (is_string($role)) {
            $roles[] = $role;
            continue;
        }
        if (is_array($role)) {
            foreach (['name', 'role_name', 'code', 'slug', 'key'] as $key) {
                if (!empty($role[$key]) && is_string($role[$key])) {
                    $roles[] = $role[$key];
                    break;
                }
            }
        }
    }

    return array_values(array_unique(array_filter(array_map('strval', $roles))));
}

function getUserRoleNames(User $user, PDO $db, string $userId): array
{
    foreach (['getRoles', 'getRoleNames'] as $method) {
        if (method_exists($user, $method)) {
            $roles = normalizeRoleNames($user->{$method}());
            if ($roles !== []) {
                return $roles;
            }
        }
    }

    $queries = [
        'SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.role_name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT ur.role FROM user_roles ur WHERE ur.user_id = ?',
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($roles) {
                return array_values(array_unique(array_map('strval', $roles)));
            }
        } catch (Throwable $e) {
            // 既存DBのカラム名差異に備えて次の候補を試す
        }
    }

    return [];
}

function userHasPermission(User $user, string $permission): bool
{
    if (method_exists($user, 'hasPermission')) {
        return (bool) $user->hasPermission($permission);
    }
    return false;
}

function getAllowedTargetScopes(User $user, PDO $db, string $userId): array
{
    $roles = array_map('strtolower', getUserRoleNames($user, $db, $userId));

    $isManager = userHasPermission($user, 'manage_users')
        || userHasPermission($user, 'manage_pdf_documents')
        || in_array('admin', $roles, true)
        || in_array('administrator', $roles, true);

    if ($isManager) {
        return ['all', 'parttime', 'fulltime'];
    }

    $scopes = ['all'];

    foreach ($roles as $role) {
        if (str_contains($role, 'part_time') || str_contains($role, 'parttime')) {
            $scopes[] = 'parttime';
        }
        if (str_contains($role, 'full_time') || str_contains($role, 'fulltime')) {
            $scopes[] = 'fulltime';
        }
    }

    return array_values(array_unique($scopes));
}

function getScopeLabel(string $scope): string
{
    return match ($scope) {
        'all' => '全員',
        'parttime' => '非常勤',
        'fulltime' => '専任・社員',
        default => $scope,
    };
}

function getVisibleDocuments(PDO $db, string $userId, array $allowedScopes): array
{
    if ($allowedScopes === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($allowedScopes), '?'));
    $sql = "
        SELECT
            d.id,
            d.title,
            d.original_name,
            d.target_scope,
            d.requires_ack,
            d.uploaded_at,
            a.acknowledged_at
        FROM pdf_documents d
        LEFT JOIN pdf_acknowledgements a
            ON a.document_id = d.id
            AND a.user_id = ?
        WHERE d.is_active = 1
          AND d.target_scope IN ({$placeholders})
        ORDER BY d.uploaded_at DESC, d.id DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$userId], $allowedScopes));
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

[$user, $userId] = requirePdfUser();
$db = Database::getConnection();
$allowedScopes = getAllowedTargetScopes($user, $db, $userId);
$pdfs = getVisibleDocuments($db, $userId, $allowedScopes);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>資料一覧</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --primary: #0f3568;
      --primary-soft: #d6e3ff;
      --surface: #ffffff;
      --background: #f3f7ff;
      --text: #141d2c;
      --muted: #667085;
      --border: #c7d0df;
      --ok: #067647;
      --ok-bg: #dcfae6;
      --warn: #b54708;
      --warn-bg: #fef0c7;
      --neutral-bg: #eef2f7;
    }

    * { box-sizing: border-box; }

    html,
    body {
      margin: 0;
      min-height: 100%;
      background: var(--background);
      color: var(--text);
      font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
    }

    .page {
      width: 100%;
      min-height: 100vh;
      padding: 24px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 16px;
      margin-bottom: 18px;
    }

    h1 {
      margin: 0;
      font-size: 1.5rem;
      line-height: 1.3;
      color: var(--primary);
    }

    .description {
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 0.95rem;
    }

    .count {
      color: var(--muted);
      font-size: 0.9rem;
      white-space: nowrap;
    }

    .list {
      display: grid;
      gap: 12px;
    }

    .pdf-link {
      display: grid;
      grid-template-columns: 44px 1fr auto;
      align-items: center;
      gap: 14px;
      padding: 16px;
      border: 1px solid var(--border);
      border-radius: 16px;
      background: var(--surface);
      color: inherit;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(15, 53, 104, 0.08);
    }

    .pdf-link:hover,
    .pdf-link:focus-visible {
      border-color: var(--primary);
      outline: none;
      background: #fbfdff;
    }

    .icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      background: var(--primary-soft);
      color: var(--primary);
      font-weight: 700;
      letter-spacing: 0.02em;
    }

    .title-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }

    .title {
      font-weight: 700;
      font-size: 1rem;
      overflow-wrap: anywhere;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      min-height: 24px;
      padding: 2px 9px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
      white-space: nowrap;
    }

    .badge-ok { color: var(--ok); background: var(--ok-bg); }
    .badge-warn { color: var(--warn); background: var(--warn-bg); }
    .badge-neutral { color: var(--muted); background: var(--neutral-bg); }

    .meta {
      margin-top: 4px;
      color: var(--muted);
      font-size: 0.85rem;
    }

    .open {
      color: var(--primary);
      font-weight: 700;
      white-space: nowrap;
    }

    .empty {
      padding: 24px;
      border: 1px dashed var(--border);
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.65);
      color: var(--muted);
    }

    @media (max-width: 640px) {
      .page { padding: 16px; }
      .header { display: block; }
      .count { margin-top: 8px; }
      .pdf-link { grid-template-columns: 40px 1fr; }
      .open { grid-column: 2; }
    }
  </style>
</head>
<body>
  <main class="page">
    <div class="header">
      <div>
        <h1>資料一覧</h1>
        <p class="description">配信対象になっている資料のみ表示しています。</p>
      </div>
      <div class="count"><?= count($pdfs) ?> 件</div>
    </div>

    <?php if ($pdfs === []): ?>
      <div class="empty">
        現在、表示できる資料はありません。<br>
        資料が登録済みの場合は、公開状態・配信対象・ログインユーザーの役職を確認してください。
      </div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($pdfs as $pdf): ?>
          <?php
          $href = 'pdf-viewer.php?id=' . rawurlencode((string) $pdf['id']);
          $uploaded = $pdf['uploaded_at'] ? date('Y/m/d H:i', strtotime((string) $pdf['uploaded_at'])) : '-';
          $requiresAck = (int) $pdf['requires_ack'] === 1;
          $acknowledgedAt = (string) ($pdf['acknowledged_at'] ?? '');
          $statusClass = 'badge-neutral';
          $statusText = '確認不要';
          if ($requiresAck && $acknowledgedAt !== '') {
              $statusClass = 'badge-ok';
              $statusText = '確認済み';
          } elseif ($requiresAck) {
              $statusClass = 'badge-warn';
              $statusText = '未確認';
          }
          ?>
          <a class="pdf-link" href="<?= h($href) ?>">
            <span class="icon">PDF</span>
            <span>
              <span class="title-row">
                <span class="title"><?= h($pdf['title']) ?></span>
                <span class="badge <?= h($statusClass) ?>"><?= h($statusText) ?></span>
              </span>
              <span class="meta">
                対象: <?= h(getScopeLabel((string) $pdf['target_scope'])) ?> / 登録: <?= h($uploaded) ?>
                <?php if (!empty($pdf['original_name'])): ?> / <?= h($pdf['original_name']) ?><?php endif; ?>
              </span>
            </span>
            <span class="open">開く</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
