/*
 * kiweb-task-delivery.js
 *
 * kiweb2 系トップページ共通のタスク通知バー / 緊急タスクポップアップ。
 * 参考元: kiweb2_taskbar (2).html のタスク配信部分。
 *
 * 使い方:
 *   <script src="kiweb-task-delivery.js"></script>
 *   <script>
 *     KiwebTaskDelivery.init({ getLoginName });
 *     // ログイン確認後に呼ぶのが安全。init の既定で1回 refresh します。
 *   </script>
 *
 * 処理の流れ:
 *   1. init() が通知バーと緊急モーダルをポータルへ組み込む。
 *   2. ログイン本人名でGASのsummary APIを呼ぶ。
 *   3. 締切状況に応じて通知を更新し、必要な場合だけ緊急モーダルを表示する。
 *   4. タスク一覧を開く際も同じログイン本人名を引き継ぐ。
 */
(function () {
  'use strict';

  const DEFAULTS = {
    apiUrl: 'https://script.google.com/macros/s/AKfycbzw1KVJreAodFe9ZbmT_2aDMbTSOYu49qJx2CjdP9m1p2IiAJLRFXbLMLpdAd_RLi-5gQ/exec',
    managerPageUrl: 'task-delivery-manager.html',
    nameStorageKey: 'kiweb_user_name',
    namePlaceholder: 'ログインしてください',
    headerSelector: '.header',
    contentAreaSelector: '.content-area',
    autoRefresh: true,
    barOpenMode: 'new-tab',     // 'new-tab' または 'same-tab'
    urgentOpenMode: 'same-tab', // 'new-tab' または 'same-tab'
    getLoginName: null
  };

  let options = { ...DEFAULTS };
  let initialized = false;
  let hasShownUrgentTaskPopup = false;
  let taskAlertBar = null;
  let taskUrgentModal = null;
  let taskUrgentMessage = null;
  let taskUrgentClose = null;
  let taskUrgentOpen = null;

  function init(userOptions = {}) {
    // ポータルごとの差はオプションで吸収し、DOM生成とイベント登録は初回だけ行う。
    options = { ...DEFAULTS, ...userOptions };

    if (!document.getElementById('kiwebTaskDeliveryStyle')) {
      const style = document.createElement('style');
      style.id = 'kiwebTaskDeliveryStyle';
      style.textContent = `
        .task-alert-bar {
          width: 100%;
          border: none;
          padding: 12px 24px;
          min-height: 48px;
          font: inherit;
          font-size: 0.98rem;
          font-weight: 800;
          text-align: center;
          letter-spacing: 0.01em;
          cursor: pointer;
          position: sticky;
          top: 64px;
          z-index: 9;
          transition: filter 0.18s ease, box-shadow 0.18s ease;
        }
        .task-alert-bar--urgent {
          background: #1f2937;
          color: #fde047;
          border-bottom: 3px solid #f97316;
          box-shadow: 0 3px 10px rgba(31, 41, 55, 0.25);
        }
        .task-alert-bar--open {
          background: linear-gradient(90deg, #f59e0b 0%, #f97316 100%);
          color: #3b1d00;
          border-bottom: 3px solid #dc2626;
          box-shadow: 0 3px 10px rgba(245, 158, 11, 0.25);
        }
        .task-alert-bar--complete {
          background: #fff1f2;
          color: #9f1239;
          border-top: 2px solid #fb7185;
          border-bottom: 2px solid #fb7185;
        }
        .task-alert-bar:hover { filter: brightness(0.97); }
        .task-alert-bar:focus-visible {
          outline: 3px solid #fbbf24;
          outline-offset: -3px;
        }
        .task-alert-bar[hidden] { display: none; }

        body.has-task-alert .content-area {
          height: calc(100vh - 140px - var(--kiweb-task-alert-height, 48px));
          display: flex;
          flex-direction: column;
        }
        body.has-task-alert iframe#mainFrame {
          flex: 1 1 auto;
          min-height: 0;
          height: auto;
        }

        .task-urgent-modal {
          position: fixed;
          inset: 0;
          display: none;
          align-items: center;
          justify-content: center;
          padding: 20px;
          background: rgba(0, 0, 0, 0.48);
          z-index: 1200;
        }
        .task-urgent-modal.show { display: flex; }
        .task-urgent-dialog {
          width: min(460px, 92vw);
          background: #fff;
          border-radius: 24px;
          padding: 28px 24px 22px;
          text-align: center;
          box-shadow: 0 16px 48px rgba(0, 0, 0, 0.28);
          border-top: 8px solid #dc2626;
        }
        .task-urgent-icon {
          font-size: 42px;
          line-height: 1;
          margin-bottom: 10px;
        }
        .task-urgent-dialog h2 {
          font-size: 1.25rem;
          margin: 0 0 10px;
          color: #991b1b;
        }
        .task-urgent-message {
          font-size: 0.98rem;
          font-weight: 700;
          color: #291819;
          margin: 0 0 20px;
          line-height: 1.6;
        }
        .task-urgent-actions {
          display: flex;
          justify-content: center;
          gap: 10px;
          flex-wrap: wrap;
        }
        .task-urgent-button {
          border: none;
          border-radius: 9999px;
          padding: 10px 18px;
          font: inherit;
          font-weight: 700;
          cursor: pointer;
        }
        .task-urgent-button-primary {
          background: #dc2626;
          color: #fff;
        }
        .task-urgent-button-secondary {
          background: #f3f4f6;
          color: #374151;
        }
        @media (max-width: 768px) {
          .task-alert-bar {
            top: 56px;
            min-height: 46px;
            padding: 10px 12px;
            font-size: 0.86rem;
            line-height: 1.35;
          }
          body.has-task-alert .content-area {
            height: calc(100vh - 56px - var(--kiweb-task-alert-height, 46px) - (80px + env(safe-area-inset-bottom)));
          }
        }
      `;
      document.head.appendChild(style);
    }

    taskAlertBar = document.getElementById('taskAlertBar');
    if (!taskAlertBar) {
      taskAlertBar = document.createElement('button');
      taskAlertBar.id = 'taskAlertBar';
      taskAlertBar.className = 'task-alert-bar';
      taskAlertBar.type = 'button';
      taskAlertBar.hidden = true;
      taskAlertBar.setAttribute('aria-live', 'polite');

      const header = document.querySelector(options.headerSelector);
      if (header && header.parentNode) {
        header.insertAdjacentElement('afterend', taskAlertBar);
      } else {
        document.body.insertAdjacentElement('afterbegin', taskAlertBar);
      }
    }

    taskUrgentModal = document.getElementById('taskUrgentModal');
    if (!taskUrgentModal) {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = `
        <div id="taskUrgentModal" class="task-urgent-modal" aria-hidden="true">
          <div class="task-urgent-dialog" role="dialog" aria-modal="true" aria-labelledby="taskUrgentTitle">
            <div class="task-urgent-icon">🚨</div>
            <h2 id="taskUrgentTitle">期限を過ぎたタスクがあります</h2>
            <p id="taskUrgentMessage" class="task-urgent-message"></p>
            <div class="task-urgent-actions">
              <button type="button" class="task-urgent-button task-urgent-button-secondary" id="taskUrgentClose">あとで確認</button>
              <button type="button" class="task-urgent-button task-urgent-button-primary" id="taskUrgentOpen">タスクを確認する</button>
            </div>
          </div>
        </div>
      `;
      taskUrgentModal = wrapper.firstElementChild;
      document.body.appendChild(taskUrgentModal);
    }

    taskUrgentMessage = document.getElementById('taskUrgentMessage');
    taskUrgentClose = document.getElementById('taskUrgentClose');
    taskUrgentOpen = document.getElementById('taskUrgentOpen');

    if (!initialized) {
      taskAlertBar.addEventListener('click', () => openTaskDeliveryManager(options.barOpenMode));
      if (taskUrgentClose) taskUrgentClose.addEventListener('click', closeUrgentTaskPopup);
      if (taskUrgentOpen) taskUrgentOpen.addEventListener('click', () => {
        closeUrgentTaskPopup();
        openTaskDeliveryManager(options.urgentOpenMode);
      });
      if (taskUrgentModal) {
        taskUrgentModal.addEventListener('click', (event) => {
          if (event.target === taskUrgentModal) closeUrgentTaskPopup();
        });
      }
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeUrgentTaskPopup();
      });
      initialized = true;
    }

    if (options.autoRefresh) {
      refreshTaskAlertBar();
    }

    return window.KiwebTaskDelivery;
  }

  function getCurrentLoginName() {
    // タスクの担当者は「現在検索している講師」ではなく、ポータルへログインしている本人。
    // 呼び出し元の getLoginName を最優先し、互換用の保存値や表示名はフォールバックとして使う。
    if (typeof options.getLoginName === 'function') {
      const value = (options.getLoginName() || '').trim();
      if (value) return value.replace(/(さん)+$/u, '').trim();
    }

    const urlName = new URLSearchParams(window.location.search).get('name');
    if (urlName) return urlName.trim().replace(/(さん)+$/u, '').trim();

    try {
      const storedName = (sessionStorage.getItem(options.nameStorageKey) || '').trim();
      if (storedName) return storedName.replace(/(さん)+$/u, '').trim();
    } catch (error) {
      // ignore
    }

    const nameEl = document.querySelector('.user-name');
    const text = nameEl ? nameEl.textContent.trim() : '';
    if (!text || text === options.namePlaceholder) return '';
    return text.replace(/(さん)+$/u, '').trim();
  }

  function setTaskAlertBar(summary) {
    // GASの区分別件数を、緊急・未完了・全件完了の表示状態へまとめる。
    if (!taskAlertBar || !summary) return;

    const critical = Number(summary['深刻な遅延'] || 0);
    const overdue = Number(summary['締切超過'] || 0);
    const nearDue = Number(summary['締切近し'] || 0);
    const otherUnfinished = Number(summary['その他未完了'] || 0);
    const completed = Number(summary['完了'] || 0);
    const urgentTotal = critical + overdue + nearDue;
    const unfinished = urgentTotal + otherUnfinished;
    const totalVisible = unfinished + completed;
    const parts = (items) => items
      .filter((item) => Number(item.count || 0) > 0)
      .map((item) => `${item.label}:${Number(item.count)}件`)
      .join('、');

    taskAlertBar.classList.remove('task-alert-bar--urgent', 'task-alert-bar--open', 'task-alert-bar--complete');

    if (!totalVisible) {
      hideTaskAlertBar();
      return;
    }

    if (urgentTotal > 0) {
      taskAlertBar.classList.add('task-alert-bar--urgent');
      taskAlertBar.textContent = `🚨未完了タスクがあります🚨　${parts([
        { label: '🔥深刻な遅延', count: critical },
        { label: '⚠️締切超過', count: overdue },
        { label: '締切近し', count: nearDue }
      ])}`;
    } else if (unfinished > 0) {
      taskAlertBar.classList.add('task-alert-bar--open');
      taskAlertBar.textContent = `⚠️未完了タスクがあります ${parts([
        { label: 'その他未完了', count: otherUnfinished }
      ])}`;
    } else {
      taskAlertBar.classList.add('task-alert-bar--complete');
      taskAlertBar.textContent = `✅完了タスクのみです ${parts([
        { label: '完了', count: completed }
      ])}`;
    }

    taskAlertBar.hidden = false;
    document.body.classList.add('has-task-alert');
    requestAnimationFrame(() => {
      document.documentElement.style.setProperty('--kiweb-task-alert-height', `${taskAlertBar.offsetHeight || 48}px`);
    });

    if (!hasShownUrgentTaskPopup && taskUrgentModal && taskUrgentMessage && taskUrgentOpen && (critical > 0 || overdue > 0)) {
      hasShownUrgentTaskPopup = true;
      taskUrgentMessage.textContent = critical > 0
        ? `${parts([{ label: '🔥深刻な遅延', count: critical }, { label: '⚠️締切超過', count: overdue }])}があります。すみやかに確実に実行してください。`
        : `${parts([{ label: '⚠️締切超過', count: overdue }])}があります。確実に実行してください。`;
      taskUrgentOpen.textContent = '今すぐタスクを確認する';
      if (taskUrgentClose) taskUrgentClose.style.display = critical > 0 ? 'none' : '';
      taskUrgentModal.classList.add('show');
      taskUrgentModal.setAttribute('aria-hidden', 'false');
    }
  }

  function hideTaskAlertBar() {
    if (!taskAlertBar) return;
    taskAlertBar.hidden = true;
    taskAlertBar.textContent = '';
    taskAlertBar.classList.remove('task-alert-bar--urgent', 'task-alert-bar--open', 'task-alert-bar--complete');
    document.body.classList.remove('has-task-alert');
  }

  async function refreshTaskAlertBar() {
    if (!initialized) init({ ...options, autoRefresh: false });

    const name = getCurrentLoginName();
    if (!name) {
      hideTaskAlertBar();
      return false;
    }

    try {
      // summary APIは講師名ごとの集計を返す。単一結果の場合はキー名の揺れも許容する。
      const url = `${options.apiUrl}?mode=summary&assignees=${encodeURIComponent(name)}`;
      const response = await fetch(url);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const json = await response.json();
      if (json.error) throw new Error(json.error);

      const keys = json && typeof json === 'object' ? Object.keys(json) : [];
      const summary = json[name] || (keys.length === 1 ? json[keys[0]] : null);
      if (!summary) {
        hideTaskAlertBar();
        return false;
      }

      setTaskAlertBar(summary);
      return true;
    } catch (error) {
      console.warn('Task delivery unavailable:', error);
      hideTaskAlertBar();
      return false;
    }
  }

  function closeUrgentTaskPopup() {
    if (!taskUrgentModal) return;
    taskUrgentModal.classList.remove('show');
    taskUrgentModal.setAttribute('aria-hidden', 'true');
  }

  function openTaskDeliveryManager(mode = 'new-tab') {
    const name = getCurrentLoginName();
    if (!name) return;
    const url = `${options.managerPageUrl}?names=${encodeURIComponent(name)}`;
    if (mode === 'same-tab') {
      window.location.href = url;
    } else {
      window.open(url, '_blank', 'noopener');
    }
  }

  window.KiwebTaskDelivery = {
    init,
    refresh: refreshTaskAlertBar,
    hide: hideTaskAlertBar,
    closePopup: closeUrgentTaskPopup,
    openManager: openTaskDeliveryManager,
    getLoginName: getCurrentLoginName
  };
})();
