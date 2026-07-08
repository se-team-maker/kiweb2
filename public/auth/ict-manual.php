<?php
// ICTマニュアルページ

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ictManualPageRequireUser()
{
    if (!\App\Auth\Session::isLoggedIn()) {
        header('Location: /kiweb/public/auth/login.php');
        exit;
    }

    $userId = \App\Auth\Session::getUserId();
    $user = $userId ? \App\Model\User::findById($userId) : null;

    if (!$user || !method_exists($user, 'isActive') || !$user->isActive()) {
        \App\Auth\Session::destroy();
        header('Location: /kiweb/public/auth/login.php');
        exit;
    }
}

function ictManualPageH($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

ictManualPageRequireUser();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$version = '20260608';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>ICTマニュアル</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --primary: #0f3568;
      --accent: #247a6b;
      --background: #f4f7fb;
      --surface: #ffffff;
      --surface-soft: #f8fafc;
      --text: #172033;
      --muted: #64748b;
      --border: #cbd5e1;
      --warn: #9a3412;
      --warn-bg: #ffedd5;
    }

    * { box-sizing: border-box; }

    html,
    body {
      margin: 0;
      min-height: 100%;
      background: var(--background);
      color: var(--text);
      font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
      line-height: 1.65;
    }

    button,
    input {
      font: inherit;
    }

    .page {
      min-height: 100vh;
      padding: 22px;
    }

    .layout {
      display: grid;
      grid-template-columns: minmax(220px, 280px) 1fr;
      gap: 18px;
      max-width: 1180px;
      margin: 0 auto;
    }

    .side,
    .main {
      min-width: 0;
    }

    .topbar {
      grid-column: 1 / -1;
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      padding-bottom: 4px;
    }

    h1 {
      margin: 0;
      font-size: 1.65rem;
      line-height: 1.3;
      color: var(--primary);
    }

    .lead {
      margin: 4px 0 0;
      color: var(--muted);
      font-size: 0.94rem;
    }

    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
    }

    .search-panel {
      padding: 14px;
      position: sticky;
      top: 12px;
    }

    .search {
      width: 100%;
      min-height: 42px;
      padding: 8px 11px;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: #fff;
      color: var(--text);
    }

    .search:focus {
      border-color: var(--primary);
      outline: 2px solid rgba(15, 53, 104, 0.18);
    }

    .side-title {
      margin: 16px 0 8px;
      color: var(--muted);
      font-size: 0.82rem;
      font-weight: 700;
    }

    .category-list {
      display: grid;
      gap: 6px;
    }

    .category-button {
      width: 100%;
      min-height: 36px;
      border: 1px solid transparent;
      border-radius: 6px;
      background: transparent;
      color: var(--text);
      text-align: left;
      cursor: pointer;
      padding: 6px 8px;
    }

    .category-button:hover,
    .category-button:focus-visible,
    .category-button.active {
      border-color: var(--border);
      background: var(--surface-soft);
      outline: none;
    }

    .manual-grid {
      display: grid;
      gap: 10px;
    }

    .manual-card {
      width: 100%;
      padding: 15px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--surface);
      color: inherit;
      text-align: left;
      cursor: pointer;
    }

    .manual-card:hover,
    .manual-card:focus-visible {
      border-color: var(--primary);
      outline: none;
      box-shadow: 0 2px 10px rgba(15, 53, 104, 0.08);
    }

    .manual-title {
      margin: 0;
      font-size: 1rem;
      line-height: 1.4;
      color: var(--primary);
      overflow-wrap: anywhere;
    }

    .manual-description {
      margin: 5px 0 0;
      color: var(--muted);
      font-size: 0.9rem;
    }

    .meta {
      display: flex;
      flex-wrap: wrap;
      gap: 7px;
      margin-top: 9px;
      color: var(--muted);
      font-size: 0.8rem;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      min-height: 22px;
      padding: 2px 8px;
      border-radius: 999px;
      background: #e6f4f1;
      color: var(--accent);
      font-weight: 700;
    }

    .recent {
      margin-bottom: 14px;
      padding: 14px;
    }

    .recent-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 8px;
    }

    .recent-button,
    .back-button {
      min-height: 34px;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: #fff;
      color: var(--primary);
      cursor: pointer;
      padding: 5px 10px;
      font-weight: 700;
    }

    .recent-button:hover,
    .recent-button:focus-visible,
    .back-button:hover,
    .back-button:focus-visible {
      border-color: var(--primary);
      outline: none;
    }

    .detail {
      padding: 20px;
    }

    .detail-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 14px;
    }

    .detail-title {
      margin: 0;
      font-size: 1.42rem;
      line-height: 1.35;
      color: var(--primary);
      overflow-wrap: anywhere;
    }

    .toc {
      margin: 12px 0 18px;
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--surface-soft);
    }

    .toc-title {
      margin: 0 0 6px;
      color: var(--muted);
      font-size: 0.82rem;
      font-weight: 700;
    }

    .toc a {
      display: inline-block;
      margin: 2px 12px 2px 0;
      color: var(--primary);
      text-decoration: none;
      font-size: 0.9rem;
    }

    .markdown {
      overflow-wrap: anywhere;
    }

    .markdown h1 {
      display: none;
    }

    .markdown h2 {
      margin: 1.5em 0 0.45em;
      font-size: 1.18rem;
      color: var(--primary);
      border-bottom: 1px solid var(--border);
      padding-bottom: 0.18em;
    }

    .markdown h3 {
      margin: 1.25em 0 0.35em;
      font-size: 1.03rem;
      color: var(--text);
    }

    .markdown p,
    .markdown ul,
    .markdown ol {
      margin: 0.65em 0;
    }

    .markdown li + li {
      margin-top: 0.25em;
    }

    .markdown code {
      padding: 0.1em 0.32em;
      border-radius: 4px;
      background: #edf2f7;
      font-family: Consolas, Monaco, monospace;
      font-size: 0.92em;
    }

    .markdown pre {
      overflow: auto;
      padding: 12px;
      border-radius: 8px;
      background: #111827;
      color: #f8fafc;
    }

    .manual-image {
      display: block;
      max-width: 100%;
      height: auto;
      border: 1px solid var(--border);
      border-radius: 8px;
      margin: 10px 0;
      background: #fff;
    }

    .notice {
      margin-top: 18px;
      padding: 12px;
      border-radius: 8px;
      background: var(--warn-bg);
      color: var(--warn);
      font-weight: 700;
    }

    .empty,
    .error {
      padding: 18px;
      border: 1px dashed var(--border);
      border-radius: 8px;
      color: var(--muted);
      background: rgba(255, 255, 255, 0.7);
    }

    .hidden {
      display: none;
    }

    @media (max-width: 760px) {
      .page {
        padding: 14px;
      }

      .layout {
        grid-template-columns: 1fr;
      }

      .topbar {
        display: block;
      }

      .search-panel {
        position: static;
      }

      .detail-header {
        display: block;
      }

      .back-button {
        margin-top: 10px;
      }
    }
  </style>
</head>
<body>
  <main class="page">
    <div class="layout">
      <header class="topbar">
        <div>
          <h1>ICTマニュアル</h1>
          <p class="lead">kiweb2の操作方法と、よくあるトラブルの確認ページです。</p>
        </div>
      </header>

      <aside class="side">
        <div class="panel search-panel">
          <input id="searchInput" class="search" type="search" placeholder="検索" autocomplete="off">
          <div class="side-title">カテゴリ</div>
          <div id="categoryList" class="category-list"></div>
        </div>
      </aside>

      <section class="main">
        <section id="listView">
          <div id="recentPanel" class="panel recent hidden">
            <div class="side-title" style="margin:0;">最近更新されたページ</div>
            <div id="recentList" class="recent-list"></div>
          </div>
          <div id="manualList" class="manual-grid"></div>
        </section>

        <section id="detailView" class="panel detail hidden">
          <div class="detail-header">
            <div>
              <h2 id="detailTitle" class="detail-title"></h2>
              <div id="detailMeta" class="meta"></div>
            </div>
            <button id="backButton" class="back-button" type="button">一覧に戻る</button>
          </div>
          <nav id="toc" class="toc hidden" aria-label="目次">
            <div class="toc-title">目次</div>
            <div id="tocLinks"></div>
          </nav>
          <article id="manualBody" class="markdown"></article>
          <div class="notice">このページで解決しない場合は、ICT担当者までお問い合わせください。</div>
        </section>
      </section>
    </div>
  </main>

  <script>
    const API_URL = '/kiweb/public/auth/ict-manual-file.php';
    const VERSION = '<?= ictManualPageH($version) ?>';
    const state = {
      manuals: [],
      category: 'all',
      query: '',
      current: null
    };

    const searchInput = document.getElementById('searchInput');
    const categoryList = document.getElementById('categoryList');
    const manualList = document.getElementById('manualList');
    const recentPanel = document.getElementById('recentPanel');
    const recentList = document.getElementById('recentList');
    const listView = document.getElementById('listView');
    const detailView = document.getElementById('detailView');
    const detailTitle = document.getElementById('detailTitle');
    const detailMeta = document.getElementById('detailMeta');
    const manualBody = document.getElementById('manualBody');
    const toc = document.getElementById('toc');
    const tocLinks = document.getElementById('tocLinks');
    const backButton = document.getElementById('backButton');

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function imageUrl(path) {
      const cleanPath = String(path || '').replace(/^images\//, '');
      return API_URL + '?type=image&path=' + encodeURIComponent(cleanPath) + '&v=' + encodeURIComponent(VERSION);
    }

    function parseInline(raw) {
      let output = '';
      const imageRe = /!\[([^\]]*)\]\(([^)]+)\)/g;
      let lastIndex = 0;
      let match;

      while ((match = imageRe.exec(raw)) !== null) {
        output += escapeInlineText(raw.slice(lastIndex, match.index));
        const alt = escapeHtml(match[1] || '');
        const src = String(match[2] || '').trim();
        if (/^images\/[A-Za-z0-9][A-Za-z0-9._\/-]*\.(png|jpe?g|gif|webp|svg)$/i.test(src) && src.indexOf('..') === -1) {
          output += '<img class="manual-image" src="' + escapeHtml(imageUrl(src)) + '" alt="' + alt + '">';
        } else {
          output += alt;
        }
        lastIndex = imageRe.lastIndex;
      }

      output += escapeInlineText(raw.slice(lastIndex));
      return output;
    }

    function escapeInlineText(raw) {
      return escapeHtml(raw)
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    }

    function slugify(text, index) {
      return 'section-' + index + '-' + String(text).replace(/\s+/g, '-').replace(/[^\w\-一-龠ぁ-んァ-ヶー]/g, '').slice(0, 40);
    }

    function renderMarkdown(markdown) {
      const lines = String(markdown || '').replace(/\r\n/g, '\n').split('\n');
      const html = [];
      const headings = [];
      let listType = null;
      let inCode = false;
      let codeLines = [];
      let headingIndex = 0;

      function closeList() {
        if (listType) {
          html.push('</' + listType + '>');
          listType = null;
        }
      }

      function closeCode() {
        if (inCode) {
          html.push('<pre><code>' + escapeHtml(codeLines.join('\n')) + '</code></pre>');
          inCode = false;
          codeLines = [];
        }
      }

      lines.forEach((line) => {
        if (/^```/.test(line)) {
          if (inCode) {
            closeCode();
          } else {
            closeList();
            inCode = true;
            codeLines = [];
          }
          return;
        }

        if (inCode) {
          codeLines.push(line);
          return;
        }

        if (/^\s*$/.test(line)) {
          closeList();
          return;
        }

        const heading = line.match(/^(#{1,3})\s+(.+)$/);
        if (heading) {
          closeList();
          const level = heading[1].length;
          const text = heading[2].trim();
          const id = slugify(text, headingIndex++);
          if (level >= 2) {
            headings.push({ id, level, text });
          }
          html.push('<h' + level + ' id="' + escapeHtml(id) + '">' + parseInline(text) + '</h' + level + '>');
          return;
        }

        const ordered = line.match(/^\s*\d+\.\s+(.+)$/);
        if (ordered) {
          if (listType !== 'ol') {
            closeList();
            listType = 'ol';
            html.push('<ol>');
          }
          html.push('<li>' + parseInline(ordered[1]) + '</li>');
          return;
        }

        const unordered = line.match(/^\s*[-*]\s+(.+)$/);
        if (unordered) {
          if (listType !== 'ul') {
            closeList();
            listType = 'ul';
            html.push('<ul>');
          }
          html.push('<li>' + parseInline(unordered[1]) + '</li>');
          return;
        }

        closeList();
        html.push('<p>' + parseInline(line.trim()) + '</p>');
      });

      closeCode();
      closeList();

      return { html: html.join('\n'), headings };
    }

    function targetLabel(targets) {
      const labels = {
        all: '全員',
        parttime: '非常勤',
        fulltime: '専任',
        admin: '管理者'
      };
      return (targets || []).map((target) => labels[target] || target).join(' / ');
    }

    function manualMatches(manual) {
      const query = state.query.trim().toLowerCase();
      const categoryOk = state.category === 'all' || manual.category === state.category;
      if (!categoryOk) {
        return false;
      }
      if (!query) {
        return true;
      }
      const haystack = [
        manual.title,
        manual.category,
        manual.description,
        (manual.tags || []).join(' ')
      ].join(' ').toLowerCase();
      return haystack.indexOf(query) !== -1;
    }

    function renderCategories() {
      const categories = Array.from(new Set(state.manuals.map((manual) => manual.category))).filter(Boolean);
      const buttons = [{ key: 'all', label: 'すべて' }].concat(categories.map((category) => ({ key: category, label: category })));
      categoryList.innerHTML = buttons.map((item) => {
        const active = state.category === item.key ? ' active' : '';
        return '<button class="category-button' + active + '" type="button" data-category="' + escapeHtml(item.key) + '">' + escapeHtml(item.label) + '</button>';
      }).join('');
    }

    function renderRecent() {
      const recent = state.manuals.slice().sort((a, b) => String(b.updated || '').localeCompare(String(a.updated || ''))).slice(0, 3);
      if (recent.length === 0) {
        recentPanel.classList.add('hidden');
        return;
      }
      recentPanel.classList.remove('hidden');
      recentList.innerHTML = recent.map((manual) => {
        return '<button class="recent-button" type="button" data-manual-id="' + escapeHtml(manual.id) + '">' + escapeHtml(manual.title) + '</button>';
      }).join('');
    }

    function renderList() {
      const manuals = state.manuals.filter(manualMatches);
      if (manuals.length === 0) {
        manualList.innerHTML = '<div class="empty">該当するマニュアルはありません。</div>';
        return;
      }

      manualList.innerHTML = manuals.map((manual) => {
        return [
          '<button class="manual-card" type="button" data-manual-id="' + escapeHtml(manual.id) + '">',
          '<h2 class="manual-title">' + escapeHtml(manual.title) + '</h2>',
          '<p class="manual-description">' + escapeHtml(manual.description || '') + '</p>',
          '<div class="meta">',
          '<span class="badge">' + escapeHtml(manual.category || '') + '</span>',
          '<span>更新: ' + escapeHtml(manual.updated || '-') + '</span>',
          '<span>対象: ' + escapeHtml(targetLabel(manual.target)) + '</span>',
          '</div>',
          '</button>'
        ].join('');
      }).join('');
    }

    function showList() {
      state.current = null;
      detailView.classList.add('hidden');
      listView.classList.remove('hidden');
      renderList();
      if (location.hash) {
        history.replaceState(null, '', location.pathname + location.search);
      }
    }

    async function showManual(id, updateHash = true) {
      const manual = state.manuals.find((item) => item.id === id);
      if (!manual) {
        return;
      }

      state.current = manual;
      detailTitle.textContent = manual.title;
      detailMeta.innerHTML = [
        '<span class="badge">' + escapeHtml(manual.category || '') + '</span>',
        '<span>更新: ' + escapeHtml(manual.updated || '-') + '</span>',
        '<span>対象: ' + escapeHtml(targetLabel(manual.target)) + '</span>'
      ].join('');
      manualBody.innerHTML = '<div class="empty">読み込み中です。</div>';
      toc.classList.add('hidden');
      tocLinks.innerHTML = '';
      listView.classList.add('hidden');
      detailView.classList.remove('hidden');

      if (updateHash) {
        history.replaceState(null, '', '#manual-' + encodeURIComponent(id));
      }

      const response = await fetch(API_URL + '?type=markdown&id=' + encodeURIComponent(id) + '&v=' + encodeURIComponent(VERSION), {
        credentials: 'same-origin'
      });
      if (!response.ok) {
        manualBody.innerHTML = '<div class="error">マニュアルを読み込めませんでした。</div>';
        return;
      }

      const markdown = await response.text();
      const rendered = renderMarkdown(markdown);
      manualBody.innerHTML = rendered.html;

      if (rendered.headings.length > 0) {
        toc.classList.remove('hidden');
        tocLinks.innerHTML = rendered.headings.map((heading) => {
          return '<a href="#' + escapeHtml(heading.id) + '">' + escapeHtml(heading.text) + '</a>';
        }).join('');
      }
    }

    async function loadManuals() {
      manualList.innerHTML = '<div class="empty">読み込み中です。</div>';
      const response = await fetch(API_URL + '?type=index&v=' + encodeURIComponent(VERSION), {
        credentials: 'same-origin'
      });
      if (!response.ok) {
        manualList.innerHTML = '<div class="error">マニュアル一覧を読み込めませんでした。</div>';
        return;
      }
      const data = await response.json();
      state.manuals = Array.isArray(data.manuals) ? data.manuals : [];
      renderCategories();
      renderRecent();
      renderList();

      const hashMatch = location.hash.match(/^#manual-(.+)$/);
      if (hashMatch) {
        showManual(decodeURIComponent(hashMatch[1]), false);
      }
    }

    searchInput.addEventListener('input', () => {
      state.query = searchInput.value;
      renderList();
    });

    categoryList.addEventListener('click', (event) => {
      const button = event.target.closest('[data-category]');
      if (!button) {
        return;
      }
      state.category = button.getAttribute('data-category') || 'all';
      renderCategories();
      renderList();
    });

    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-manual-id]');
      if (!button) {
        return;
      }
      showManual(button.getAttribute('data-manual-id'));
    });

    backButton.addEventListener('click', showList);

    loadManuals().catch(() => {
      manualList.innerHTML = '<div class="error">マニュアル一覧を読み込めませんでした。</div>';
    });
  </script>
</body>
</html>
