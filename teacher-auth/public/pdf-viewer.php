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

function resolvePrivatePdfPath(string $fileName): ?string
{
    if (!isSafePdfFileName($fileName)) {
        return null;
    }

    $baseDir = realpath(privatePdfDir());
    if ($baseDir === false) {
        return null;
    }

    $path = realpath($baseDir . DIRECTORY_SEPARATOR . $fileName);
    if ($path === false || !is_file($path)) {
        return null;
    }

    $basePrefix = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($path, $basePrefix) !== 0) {
        return null;
    }

    return $path;
}

function pdfDisplayName(string $fileName): string
{
    $name = pathinfo($fileName, PATHINFO_FILENAME);
    return str_replace(['_', '-'], ' ', $name);
}

requirePdfViewerUser();

$fileName = (string) ($_GET['file'] ?? '');
$filePath = resolvePrivatePdfPath($fileName);

if ($filePath === null) {
    http_response_code(404);
    exit('PDF not found');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$title = pdfDisplayName($fileName);
$pdfUrl = 'pdf-file.php?file=' . rawurlencode($fileName);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --primary: #0f3568;
      --surface: #ffffff;
      --background: #f3f7ff;
      --stage: #eef2f7;
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
      padding: 0;
      width: 100%;
      height: 100%;
      background: var(--background);
      color: var(--text);
      overflow: hidden;
      font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
    }

    .viewer {
      width: 100vw;
      height: 100vh;
      display: grid;
      grid-template-rows: 48px 1fr;
      background: var(--background);
    }

    .toolbar {
      min-width: 0;
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
    }

    .title {
      min-width: 0;
      margin-right: auto;
      font-weight: 700;
      color: var(--primary);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .button {
      border: 1px solid var(--border);
      background: var(--surface);
      color: var(--text);
      border-radius: 8px;
      padding: 6px 10px;
      cursor: pointer;
      font-size: 14px;
      line-height: 1;
      white-space: nowrap;
    }

    .button:hover:not(:disabled),
    .button:focus-visible:not(:disabled) {
      border-color: var(--primary);
      outline: none;
    }

    .zoom-input {
      width: 72px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 6px 8px;
      font-size: 14px;
      text-align: right;
      box-sizing: border-box;
    }

    .zoom-unit {
      font-size: 14px;
      color: #334155;
      margin-left: -4px;
    }    

    .button:disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }

    .page-info {
      min-width: 68px;
      text-align: center;
      color: var(--muted);
      font-size: 14px;
      white-space: nowrap;
    }

    .stage {
      position: relative;
      width: 100%;
      height: 100%;
      overflow: auto;
      background: var(--stage);
      padding: 12px;
      cursor : grab;
      user-select: none;
    }
    
    stage.dragging {
      cursor: grabbing;
    }

    .canvas-wrap {
      min-width: 100%;
      width: max-content;
      margin: 0 auto;
    }

    canvas {
      display: block;
      background: #fff;
      box-shadow: 0 4px 16px rgba(15, 53, 104, 0.18);
    }

    .message {
      position: absolute;
      top: 16px;
      left: 16px;
      right: 16px;
      padding: 14px 16px;
      border-radius: 12px;
      background: #fff;
      border: 1px solid var(--border);
      color: var(--muted);
      box-shadow: 0 2px 8px rgba(15, 53, 104, 0.08);
    }

    .message.error {
      color: #b42318;
      border-color: #f1b7b0;
    }

    .mobile-label {
      display: none;
    }

    @media (max-width: 720px) {
      .viewer {
        grid-template-rows: 44px 1fr;
      }

      .toolbar {
        gap: 4px;
        padding: 5px 6px;
      }

      .title {
        display: none;
      }

      .button {
        padding: 6px 8px;
        font-size: 13px;
      }

      .wide-label {
        display: none;
      }

      .mobile-label {
        display: inline;
      }

      .stage {
        padding: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="viewer">
    <div class="toolbar">
      <div class="title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>

      <button class="button" type="button" id="prevBtn">前へ</button>
      <div class="page-info"><span id="pageNum">-</span> / <span id="pageCount">-</span></div>
      <button class="button" type="button" id="nextBtn">次へ</button>

      <button class="button" type="button" id="zoomOutBtn">－</button>
      <button class="button" type="button" id="zoomInBtn">＋</button>
      <input
        type="number"
        id="zoomInput"
        class="zoom-input"
        min="30"
        max="400"
        step="10"
        value="100"
        aria-label="ズーム倍率"
      >
      <span class="zoom-unit">%</span>
      <button class="button" type="button" id="applyZoomBtn">適用</button>
      <button class="button" type="button" id="fitWidthBtn"><span class="wide-label">幅に合わせる</span><span class="mobile-label">幅</span></button>
      <button class="button" type="button" id="fitPageBtn"><span class="wide-label">全体表示</span><span class="mobile-label">全体</span></button>
    </div>

    <div class="stage" id="stage">
      <div class="canvas-wrap">
        <canvas id="pdfCanvas"></canvas>
      </div>
      <div class="message" id="messageBox">PDFを読み込んでいます…</div>
    </div>
  </div>

  <script type="module">
    import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist/build/pdf.mjs';

    pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdn.jsdelivr.net/npm/pdfjs-dist/build/pdf.worker.mjs';

    const PDF_URL = <?= json_encode($pdfUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    const stage = document.getElementById('stage');
    const canvas = document.getElementById('pdfCanvas');
    const ctx = canvas.getContext('2d');
    const messageBox = document.getElementById('messageBox');

    const pageNumEl = document.getElementById('pageNum');
    const pageCountEl = document.getElementById('pageCount');

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomInput = document.getElementById('zoomInput');
    const applyZoomBtn = document.getElementById('applyZoomBtn');
    const fitWidthBtn = document.getElementById('fitWidthBtn');
    const fitPageBtn = document.getElementById('fitPageBtn');


    let isDraggingStage = false;
    let dragStartX = 0;
    let dragStartY = 0;
    let dragStartScrollLeft = 0;
    let dragStartScrollTop = 0;

    let pdfDoc = null;
    let pageNum = 1;
    let scale = 1;
    let rendering = false;
    let pendingRender = false;

    function showMessage(text, isError = false) {
      messageBox.textContent = text;
      messageBox.hidden = false;
      messageBox.classList.toggle('error', isError);
    }

    function hideMessage() {
      messageBox.hidden = true;
    }

    function setButtonsDisabled(disabled) {
      [prevBtn, nextBtn, zoomOutBtn, zoomInBtn, fitWidthBtn, fitPageBtn].forEach(btn => {
        btn.disabled = disabled;
      });
    }

    async function getCurrentPage() {
      if (!pdfDoc) return null;
      return await pdfDoc.getPage(pageNum);
    }

    async function calculateFitWidthScale() {
      const page = await getCurrentPage();
      if (!page) return 1;
      const viewport = page.getViewport({ scale: 1 });
      const availableWidth = Math.max(240, stage.clientWidth - 24);
      return availableWidth / viewport.width;
    }

    async function calculateFitPageScale() {
      const page = await getCurrentPage();
      if (!page) return 1;
      const viewport = page.getViewport({ scale: 1 });
      const availableWidth = Math.max(240, stage.clientWidth - 24);
      const availableHeight = Math.max(240, stage.clientHeight - 24);
      return Math.min(availableWidth / viewport.width, availableHeight / viewport.height);
    }

    async function renderPage() {
      if (!pdfDoc || rendering) {
        pendingRender = true;
        return;
      }

      rendering = true;
      pendingRender = false;
      setButtonsDisabled(true);

      try {
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale });
        const outputScale = window.devicePixelRatio || 1;

        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        const transform = outputScale !== 1
          ? [outputScale, 0, 0, outputScale, 0, 0]
          : null;

        await page.render({
          canvasContext: ctx,
          viewport,
          transform
        }).promise;

        pageNumEl.textContent = String(pageNum);
        pageCountEl.textContent = String(pdfDoc.numPages);

        prevBtn.disabled = pageNum <= 1;
        nextBtn.disabled = pageNum >= pdfDoc.numPages;
        zoomOutBtn.disabled = false;
        zoomInBtn.disabled = false;
        fitWidthBtn.disabled = false;
        fitPageBtn.disabled = false;
        hideMessage();
      } catch (error) {
        console.error(error);
        showMessage('PDFの表示に失敗しました。', true);
      } finally {
        rendering = false;
        if (pendingRender) {
          renderPage();
        }
      }
    }

    function applyZoomInput() {
      const value = Number(zoomInput.value);

      if (!Number.isFinite(value)) {
        updateZoomInput();
        return;
      }

      const clamped = Math.min(400, Math.max(30, value));
      scale = clamped / 100;
      zoomInput.value = String(clamped);
      renderPage();
    }

    async function fitWidth() {
      scale = await calculateFitWidthScale();
      updateZoomInput();
      renderPage();
    }

    function updateZoomInput() {
      zoomInput.value = String(Math.round(scale * 100));
    }

    async function fitPage() {
      scale = await calculateFitPageScale();
      updateZoomInput();
      renderPage();
    }

    prevBtn.addEventListener('click', () => {
      if (!pdfDoc || pageNum <= 1) return;
      pageNum -= 1;
      renderPage();
    });

    nextBtn.addEventListener('click', () => {
      if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
      pageNum += 1;
      renderPage();
    });

    zoomOutBtn.addEventListener('click', () => {
      scale = Math.max(0.25, scale - 0.1);
      updateZoomInput();
      renderPage();
    });

    zoomInBtn.addEventListener('click', () => {
      scale = Math.min(5, scale + 0.1);
      updateZoomInput();
      renderPage();
    });

    fitWidthBtn.addEventListener('click', fitWidth);

    applyZoomBtn.addEventListener('click', applyZoomInput);

    zoomInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        applyZoomInput();
      }
    });

    fitPageBtn.addEventListener('click', fitPage);


    stage.addEventListener('mousedown', (event) => {
      if (event.button !== 0) return;

      isDraggingStage = true;
      stage.classList.add('dragging');

      dragStartX = event.clientX;
      dragStartY = event.clientY;
      dragStartScrollLeft = stage.scrollLeft;
      dragStartScrollTop = stage.scrollTop;

      event.preventDefault();
    });

    window.addEventListener('mousemove', (event) => {
      if (!isDraggingStage) return;

      const dx = event.clientX - dragStartX;
      const dy = event.clientY - dragStartY;

      stage.scrollLeft = dragStartScrollLeft - dx;
      stage.scrollTop = dragStartScrollTop - dy;
    });

    window.addEventListener('mouseup', () => {
      if (!isDraggingStage) return;

      isDraggingStage = false;
      stage.classList.remove('dragging');
    });

        window.addEventListener('resize', () => {
          clearTimeout(window.__pdfViewerResizeTimer);
          window.__pdfViewerResizeTimer = setTimeout(fitWidth, 250);
        });

    async function init() {
      try {
        setButtonsDisabled(true);
        pdfDoc = await pdfjsLib.getDocument({ url: PDF_URL }).promise;
        pageCountEl.textContent = String(pdfDoc.numPages);
        await fitWidth();
      } catch (error) {
        console.error(error);
        setButtonsDisabled(true);
        canvas.hidden = true;
        showMessage('PDFの読み込みに失敗しました。', true);
      }
    }

    init();
  </script>
</body>
</html>
