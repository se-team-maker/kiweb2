<?php
// 資料配信管理画面 v1
// PDF登録、登録済み資料一覧、公開/非公開切替まで。
// PHP 7.4+ 互換を意識して、mixed / never / match / str_contains は使わない。

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function pdfAdminH($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function pdfAdminContains($haystack, $needle)
{
    return strpos((string)$haystack, (string)$needle) !== false;
}

function pdfAdminRequireUser()
{
    if (!\App\Auth\Session::isLoggedIn()) {
        http_response_code(401);
        exit('Unauthorized');
    }

    $userId = \App\Auth\Session::getUserId();
    $user = $userId ? \App\Model\User::findById($userId) : null;

    if (!$user || !method_exists($user, 'isActive') || !$user->isActive()) {
        \App\Auth\Session::destroy();
        http_response_code(401);
        exit('Unauthorized');
    }

    return array($user, (string)$userId);
}

function pdfAdminNormalizeRoleNames($rawRoles)
{
    if (!is_array($rawRoles)) {
        return array();
    }

    $roles = array();
    foreach ($rawRoles as $role) {
        if (is_string($role)) {
            $roles[] = $role;
            continue;
        }
        if (is_array($role)) {
            foreach (array('name', 'role_name', 'code', 'slug', 'key') as $key) {
                if (!empty($role[$key]) && is_string($role[$key])) {
                    $roles[] = $role[$key];
                    break;
                }
            }
        }
    }

    $roles = array_map('strval', $roles);
    $roles = array_filter($roles);
    return array_values(array_unique($roles));
}

function pdfAdminGetUserRoleNames($user, $db, $userId)
{
    foreach (array('getRoles', 'getRoleNames') as $method) {
        if (method_exists($user, $method)) {
            $roles = pdfAdminNormalizeRoleNames($user->{$method}());
            if ($roles !== array()) {
                return $roles;
            }
        }
    }

    $queries = array(
        'SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.role_name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT ur.role FROM user_roles ur WHERE ur.user_id = ?'
    );

    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute(array($userId));
            $roles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if ($roles) {
                $roles = array_map('strval', $roles);
                return array_values(array_unique($roles));
            }
        } catch (\Throwable $e) {
            // 既存DBのカラム名差異に備えて次の候補を試す
        }
    }

    return array();
}

function pdfAdminUserHasPermission($user, $permission)
{
    if (method_exists($user, 'hasPermission')) {
        return (bool)$user->hasPermission($permission);
    }
    return false;
}

function pdfAdminCanManage($user, $db, $userId)
{
    $roles = array_map('strtolower', pdfAdminGetUserRoleNames($user, $db, $userId));

    return pdfAdminUserHasPermission($user, 'manage_users')
        || pdfAdminUserHasPermission($user, 'manage_pdf_documents')
        || in_array('admin', $roles, true)
        || in_array('administrator', $roles, true);
}

function pdfAdminRequireManager($user, $db, $userId)
{
    if (!pdfAdminCanManage($user, $db, $userId)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function pdfAdminCsrfToken()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (empty($_SESSION['pdf_admin_csrf_token'])) {
        $_SESSION['pdf_admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['pdf_admin_csrf_token'];
}

function pdfAdminScopeLabel($scope)
{
    if ($scope === 'all') {
        return '全員';
    }
    if ($scope === 'parttime') {
        return '非常勤';
    }
    if ($scope === 'fulltime') {
        return '専任・社員';
    }
    return (string)$scope;
}

function pdfAdminFormatDate($value)
{
    if (!$value) {
        return '-';
    }
    $timestamp = strtotime((string)$value);
    if (!$timestamp) {
        return '-';
    }
    return date('Y/m/d H:i', $timestamp);
}

function pdfAdminGetDocuments($db)
{
    $sql = '
        SELECT
            d.id,
            d.title,
            d.file_name,
            d.original_name,
            d.target_scope,
            d.requires_ack,
            d.is_active,
            d.uploaded_by,
            d.uploaded_at,
            d.updated_at,
            COUNT(a.id) AS acknowledged_count
        FROM pdf_documents d
        LEFT JOIN pdf_acknowledgements a
            ON a.document_id = d.id
        GROUP BY
            d.id,
            d.title,
            d.file_name,
            d.original_name,
            d.target_scope,
            d.requires_ack,
            d.is_active,
            d.uploaded_by,
            d.uploaded_at,
            d.updated_at
        ORDER BY d.uploaded_at DESC, d.id DESC
    ';

    $stmt = $db->query($sql);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: array();
}

try {
    list($user, $userId) = pdfAdminRequireUser();
    $db = \App\Config\Database::getConnection();
    pdfAdminRequireManager($user, $db, $userId);

    $csrfToken = pdfAdminCsrfToken();
    $documents = pdfAdminGetDocuments($db);
    $message = isset($_GET['message']) ? (string)$_GET['message'] : '';
    $type = isset($_GET['type']) ? (string)$_GET['type'] : '';
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><pre>' . pdfAdminH($e->getMessage()) . '</pre>';
    exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>資料配信管理</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --primary: #0f3568;
      --primary-hover: #0b2a53;
      --primary-soft: #d6e3ff;
      --background: #f3f7ff;
      --surface: #ffffff;
      --surface-soft: #f8faff;
      --text: #141d2c;
      --muted: #667085;
      --border: #c7d0df;
      --danger: #b42318;
      --danger-soft: #fee4e2;
      --ok: #067647;
      --ok-soft: #dcfae6;
      --warn: #b54708;
      --warn-soft: #fef0c7;
    }

    * { box-sizing: border-box; }

    html,
    body {
      margin: 0;
      min-height: 100%;
      background: var(--background);
      color: var(--text);
      font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
      line-height: 1.6;
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
      color: var(--primary);
      font-size: 1.55rem;
      line-height: 1.3;
    }

    h2 {
      margin: 0 0 14px;
      color: var(--primary);
      font-size: 1.15rem;
    }

    .description {
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 0.94rem;
    }

    .top-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .link-button,
    .button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 40px;
      padding: 0 16px;
      border-radius: 999px;
      border: 1px solid var(--primary);
      background: var(--primary);
      color: #fff;
      text-decoration: none;
      font-weight: 700;
      cursor: pointer;
      font-size: 0.92rem;
    }

    .link-button.secondary,
    .button.secondary {
      background: #fff;
      color: var(--primary);
    }

    .button.danger {
      border-color: var(--danger);
      background: var(--danger);
    }

    .button:hover,
    .link-button:hover {
      filter: brightness(0.98);
    }

    .grid {
      display: grid;
      grid-template-columns: minmax(320px, 420px) 1fr;
      gap: 18px;
      align-items: start;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: 0 2px 8px rgba(15, 53, 104, 0.08);
      padding: 18px;
    }

    .message {
      margin-bottom: 16px;
      padding: 12px 14px;
      border-radius: 14px;
      font-weight: 700;
      border: 1px solid transparent;
    }

    .message.success {
      color: var(--ok);
      background: var(--ok-soft);
      border-color: #abefc6;
    }

    .message.error {
      color: var(--danger);
      background: var(--danger-soft);
      border-color: #fecdca;
    }

    .form-row {
      display: grid;
      gap: 7px;
      margin-bottom: 14px;
    }

    label {
      font-size: 0.88rem;
      color: var(--text);
      font-weight: 700;
    }

    input[type="text"],
    input[type="file"],
    select {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: #fff;
      padding: 10px 12px;
      font: inherit;
      color: var(--text);
    }

    input[type="file"] {
      padding: 9px;
    }

    .check-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 10px 0 16px;
      color: var(--text);
      font-weight: 700;
      font-size: 0.92rem;
    }

    .hint {
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 0.82rem;
    }

    .table-wrap {
      overflow-x: auto;
      border: 1px solid var(--border);
      border-radius: 14px;
      background: #fff;
    }

    table {
      width: 100%;
      min-width: 900px;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    th,
    td {
      padding: 12px 10px;
      border-bottom: 1px solid #e6ecf5;
      vertical-align: middle;
      text-align: left;
    }

    th {
      color: var(--muted);
      font-size: 0.78rem;
      background: var(--surface-soft);
      white-space: nowrap;
    }

    tr:last-child td { border-bottom: none; }

    .title-cell {
      min-width: 220px;
    }

    .title-main {
      font-weight: 800;
      overflow-wrap: anywhere;
    }

    .file-name {
      margin-top: 2px;
      color: var(--muted);
      font-size: 0.78rem;
      overflow-wrap: anywhere;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      min-height: 24px;
      padding: 2px 9px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 800;
      white-space: nowrap;
    }

    .badge.ok { color: var(--ok); background: var(--ok-soft); }
    .badge.warn { color: var(--warn); background: var(--warn-soft); }
    .badge.off { color: var(--muted); background: #eef2f7; }

    .actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }

    .mini-button {
      min-height: 34px;
      padding: 0 12px;
      border-radius: 999px;
      border: 1px solid var(--primary);
      background: #fff;
      color: var(--primary);
      font-weight: 800;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      white-space: nowrap;
      font-size: 0.84rem;
    }

    .mini-button.danger {
      border-color: var(--danger);
      color: var(--danger);
    }

    .empty {
      padding: 22px;
      border: 1px dashed var(--border);
      border-radius: 14px;
      color: var(--muted);
      background: rgba(255, 255, 255, 0.65);
    }

    @media (max-width: 900px) {
      .page { padding: 16px; }
      .header { display: block; }
      .top-actions { justify-content: flex-start; margin-top: 12px; }
      .grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <main class="page">
    <div class="header">
      <div>
        <h1>資料配信管理</h1>
        <p class="description">PDF資料の登録、公開状態、確認ボタン要否を管理します。</p>
      </div>
      <div class="top-actions">
        <a class="link-button secondary" href="pdf-list.php">利用者側の資料一覧</a>
      </div>
    </div>

    <?php if ($message !== ''): ?>
      <div class="message <?= $type === 'error' ? 'error' : 'success' ?>">
        <?= pdfAdminH($message) ?>
      </div>
    <?php endif; ?>

    <div class="grid">
      <section class="card">
        <h2>新規登録</h2>
        <form method="post" action="pdf-admin-action.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= pdfAdminH($csrfToken) ?>">
          <input type="hidden" name="action" value="create">

          <div class="form-row">
            <label for="title">資料タイトル</label>
            <input type="text" id="title" name="title" maxlength="255" required placeholder="例：5月度 連絡資料">
          </div>

          <div class="form-row">
            <label for="target_scope">配信対象</label>
            <select id="target_scope" name="target_scope" required>
              <option value="all">全員</option>
              <option value="parttime">非常勤</option>
              <option value="fulltime">専任・社員</option>
            </select>
          </div>

          <label class="check-row">
            <input type="checkbox" name="requires_ack" value="1" checked>
            <span>確認ボタンを表示する</span>
          </label>

          <div class="form-row">
            <label for="pdf_file">PDFファイル</label>
            <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf,.pdf" required>
            <p class="hint">PDFのみ。内部保存名は自動生成され、元ファイル名は記録だけに使います。</p>
          </div>

          <button class="button" type="submit">資料を登録する</button>
        </form>
      </section>

      <section class="card">
        <h2>登録済み資料</h2>

        <?php if ($documents === array()): ?>
          <div class="empty">登録済み資料はまだありません。</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>資料</th>
                  <th>対象</th>
                  <th>確認</th>
                  <th>公開状態</th>
                  <th>確認済み数</th>
                  <th>登録日</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($documents as $doc): ?>
                  <?php
                    $id = (int)$doc['id'];
                    $isActive = (int)$doc['is_active'] === 1;
                    $requiresAck = (int)$doc['requires_ack'] === 1;
                    $nextActive = $isActive ? 0 : 1;
                  ?>
                  <tr>
                    <td class="title-cell">
                      <div class="title-main"><?= pdfAdminH($doc['title']) ?></div>
                      <div class="file-name"><?= pdfAdminH($doc['file_name']) ?></div>
                      <?php if (!empty($doc['original_name'])): ?>
                        <div class="file-name">元ファイル: <?= pdfAdminH($doc['original_name']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= pdfAdminH(pdfAdminScopeLabel((string)$doc['target_scope'])) ?></td>
                    <td>
                      <?php if ($requiresAck): ?>
                        <span class="badge warn">確認あり</span>
                      <?php else: ?>
                        <span class="badge off">確認不要</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($isActive): ?>
                        <span class="badge ok">公開中</span>
                      <?php else: ?>
                        <span class="badge off">非公開</span>
                      <?php endif; ?>
                    </td>
                    <td><?= (int)$doc['acknowledged_count'] ?> 件</td>
                    <td><?= pdfAdminH(pdfAdminFormatDate($doc['uploaded_at'])) ?></td>
                    <td>
                      <div class="actions">
                        <?php if ($isActive): ?>
                          <a class="mini-button" href="pdf-viewer.php?id=<?= $id ?>">開く</a>
                        <?php endif; ?>
                        <form method="post" action="pdf-admin-action.php" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= pdfAdminH($csrfToken) ?>">
                          <input type="hidden" name="action" value="toggle_active">
                          <input type="hidden" name="id" value="<?= $id ?>">
                          <input type="hidden" name="is_active" value="<?= $nextActive ?>">
                          <?php if ($isActive): ?>
                            <button class="mini-button danger" type="submit">非公開にする</button>
                          <?php else: ?>
                            <button class="mini-button" type="submit">公開する</button>
                          <?php endif; ?>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
