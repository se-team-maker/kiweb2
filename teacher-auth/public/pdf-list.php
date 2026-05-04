<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

function requirePdfViewerUser(): User
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

    return $user;
}

function privatePdfDir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private-pdfs';
}

function isSafePdfFileName(string $fileName): bool
{
    return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.pdf\z/i', $fileName) === 1
        && strpos($fileName, '..') === false;
}

function pdfDisplayName(string $fileName): string
{
    $name = pathinfo($fileName, PATHINFO_FILENAME);
    return str_replace(['_', '-'], ' ', $name);
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

requirePdfViewerUser();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdfDir = privatePdfDir();
$pdfs = [];

if (is_dir($pdfDir)) {
    foreach (scandir($pdfDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!isSafePdfFileName($entry)) {
            continue;
        }
        $path = $pdfDir . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($path)) {
            continue;
        }
        $pdfs[] = [
            'file' => $entry,
            'title' => pdfDisplayName($entry),
            'size' => formatBytes((int) filesize($path)),
            'updatedAt' => filemtime($path) ?: 0,
        ];
    }
}

usort($pdfs, static fn(array $a, array $b): int => $b['updatedAt'] <=> $a['updatedAt']);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>PDF資料一覧</title>
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
    }

    * {
      box-sizing: border-box;
    }

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

    .title {
      font-weight: 700;
      font-size: 1rem;
      overflow-wrap: anywhere;
    }

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
      .page {
        padding: 16px;
      }

      .header {
        display: block;
      }

      .count {
        margin-top: 8px;
      }

      .pdf-link {
        grid-template-columns: 40px 1fr;
      }

      .open {
        grid-column: 2;
      }
    }
  </style>
</head>
<body>
  <main class="page">
    <div class="header">
      <div>
        <h1>PDF資料一覧</h1>
        <p class="description">表示したいPDFを選択してください。</p>
      </div>
      <div class="count"><?= count($pdfs) ?> 件</div>
    </div>

    <?php if ($pdfs === []): ?>
      <div class="empty">
        表示できるPDFがありません。<br>
        <code>teacher-auth/private-pdfs/</code> に半角英数字のPDFファイルを配置してください。
      </div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($pdfs as $pdf): ?>
          <?php
          $file = $pdf['file'];
          $href = 'pdf-viewer.php?file=' . rawurlencode($file);
          $updated = $pdf['updatedAt'] > 0 ? date('Y/m/d H:i', $pdf['updatedAt']) : '-';
          ?>
          <a class="pdf-link" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
            <span class="icon">PDF</span>
            <span>
              <span class="title"><?= htmlspecialchars($pdf['title'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="meta">
                <?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($pdf['size'], ENT_QUOTES, 'UTF-8') ?> / 更新: <?= htmlspecialchars($updated, ENT_QUOTES, 'UTF-8') ?>
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
