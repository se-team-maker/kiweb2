/**
 * 管理画面 JavaScript (Apple School Manager風)
 */

// API エンドポイント
const API = {
    users: '/kiweb/teacher-auth/api/admin/users.php',
    userRoles: '/kiweb/teacher-auth/api/admin/user-roles.php',
    userScopes: '/kiweb/teacher-auth/api/admin/user-scopes.php',
    roles: '/kiweb/teacher-auth/api/admin/roles.php',
    rolePermissions: '/kiweb/teacher-auth/api/admin/role-permissions.php',
    permissions: '/kiweb/teacher-auth/api/admin/permissions.php',
    scopes: '/kiweb/teacher-auth/api/admin/scopes.php',
    scopeTypes: '/kiweb/teacher-auth/api/admin/scope-types.php',
    accessLogs: '/kiweb/teacher-auth/api/admin/access-logs.php'
};

// 状態管理
let currentPage = 1;
let accessLogsCurrentPage = 1;
let allRoles = [];
let allPermissions = [];
let allScopes = [];
let allScopeTypes = [];
let allUsers = [];
let selectedUserId = null;
let selectedRoleId = null;
let selectedScopeId = null;
let currentSection = 'users';
const adminPermissions = window.adminPermissions || {};
const canManageUsers = Boolean(adminPermissions.manageUsers);
const canViewAuditLogs = Boolean(adminPermissions.viewAuditLogs || canManageUsers);
const sectionAccess = {
    users: canManageUsers,
    roles: canManageUsers,
    scopes: canManageUsers,
    'access-logs': canViewAuditLogs
};
const currentUserId = String(window.currentUserId || '');
const managedRoleNames = ['admin', 'full_time_teacher', 'part_time_teacher', 'part_time_staff'];
const managedRoleOrder = managedRoleNames.reduce((acc, roleName, index) => {
    acc[roleName] = index;
    return acc;
}, {});

// アイコンカラーのマッピング
const roleIcons = {
    admin: { icon: 'shield_person', color: 'red' },
    full_time_teacher: { icon: 'school', color: 'purple' },
    part_time_teacher: { icon: 'person', color: 'sky' },
    part_time_staff: { icon: 'badge', color: 'orange' },
    teacher: { icon: 'school', color: 'purple' },
    student: { icon: 'person', color: 'sky' },
    default: { icon: 'badge', color: 'orange' }
};

// 初期化
document.addEventListener('DOMContentLoaded', async () => {
    applySectionVisibility();

    // ナビゲーション
    document.querySelectorAll('.nav-item[data-section]').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const section = e.currentTarget.dataset.section;
            switchSection(section);
        });
    });

    // モーダル閉じるボタン
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            closeAllModals();
        });
    });

    const mobileBackBtn = document.getElementById('mobile-back-btn');
    if (mobileBackBtn) {
        mobileBackBtn.addEventListener('click', () => {
            closeMobileDetail();
        });
    }

    // モーダル外クリックで閉じる
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeAllModals();
            }
        });
    });

    // ユーザー関連
    const addUserBtn = document.getElementById('btn-add-user');
    if (addUserBtn) {
        addUserBtn.addEventListener('click', () => openUserModal());
    }
    const userSearch = document.getElementById('user-search');
    if (userSearch) {
        userSearch.addEventListener('input', debounce(() => loadUsers(), 300));
    }
    const userForm = document.getElementById('user-form');
    if (userForm) {
        userForm.addEventListener('submit', handleUserSubmit);
    }

    // 役職関連
    const addRoleBtn = document.getElementById('btn-add-role');
    if (addRoleBtn) {
        addRoleBtn.addEventListener('click', () => openRoleModal());
    }
    const roleForm = document.getElementById('role-form');
    if (roleForm) {
        roleForm.addEventListener('submit', handleRoleSubmit);
    }

    // 権限関連
    const addPermissionBtn = document.getElementById('btn-add-permission');
    if (addPermissionBtn) {
        addPermissionBtn.addEventListener('click', () => openPermissionModal());
    }
    const permissionForm = document.getElementById('permission-form');
    if (permissionForm) {
        permissionForm.addEventListener('submit', handlePermissionSubmit);
    }

    // スコープ関連
    const addScopeBtn = document.getElementById('btn-add-scope');
    if (addScopeBtn) {
        addScopeBtn.addEventListener('click', () => openScopeModal());
    }
    const scopeForm = document.getElementById('scope-form');
    if (scopeForm) {
        scopeForm.addEventListener('submit', handleScopeSubmit);
    }

    // アクセスログ関連
    const searchAccessLogsBtn = document.getElementById('btn-search-access-logs');
    const clearAccessLogsBtn = document.getElementById('btn-clear-access-logs');
    const accessLogFilterInputs = [
        document.getElementById('log-date-from'),
        document.getElementById('log-date-to'),
        document.getElementById('log-user-search'),
        document.getElementById('log-page-path')
    ];

    if (searchAccessLogsBtn) {
        searchAccessLogsBtn.addEventListener('click', () => loadAccessLogs(1));
    }
    if (clearAccessLogsBtn) {
        clearAccessLogsBtn.addEventListener('click', () => clearAccessLogFilters());
    }
    accessLogFilterInputs.forEach((input) => {
        if (!input) return;
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadAccessLogs(1);
            }
        });
    });

    // 初期データ読み込み
    await loadInitialData();

    // 初期セクションを設定
    switchSection(canManageUsers ? 'users' : 'access-logs');
});

function isMobileView() {
    return window.matchMedia('(max-width: 640px)').matches;
}

function openMobileDetail() {
    if (!isMobileView()) return;
    document.body.classList.add('mobile-detail-open');
}

function closeMobileDetail() {
    document.body.classList.remove('mobile-detail-open');
}

// デバウンス関数
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function resetFormWithMdComponents(form) {
    form.reset();

    form.querySelectorAll('md-outlined-text-field').forEach((field) => {
        field.value = '';
    });

    form.querySelectorAll('md-outlined-select').forEach((field) => {
        field.value = '';
    });
}

function applySectionVisibility() {
    Object.entries(sectionAccess).forEach(([section, canAccess]) => {
        const navItem = document.querySelector(`.nav-item[data-section="${section}"]`);
        const listSection = document.getElementById(`list-${section}`);
        const detailSection = document.getElementById(`detail-${section}`);

        if (navItem) {
            navItem.style.display = canAccess ? '' : 'none';
        }
        if (listSection) {
            listSection.style.display = canAccess ? '' : 'none';
        }
        if (detailSection) {
            detailSection.style.display = canAccess ? '' : 'none';
        }
    });
}

function getManagedRolesOnly(roles, source) {
    const roleList = Array.isArray(roles) ? roles : [];
    const filteredRoles = roleList.filter(role => managedRoleNames.includes(String(role.name || '').toLowerCase()));
    const unexpectedRoles = roleList.filter(role => !managedRoleNames.includes(String(role.name || '').toLowerCase()));

    if (unexpectedRoles.length > 0) {
        console.warn(`[admin] 想定外の役職を検出 (${source}):`, unexpectedRoles.map(role => role.name));
    }

    filteredRoles.sort((leftRole, rightRole) => {
        const leftOrder = managedRoleOrder[String(leftRole.name || '').toLowerCase()] ?? 99;
        const rightOrder = managedRoleOrder[String(rightRole.name || '').toLowerCase()] ?? 99;
        return leftOrder - rightOrder;
    });

    return filteredRoles;
}

// 初期データ読み込み
async function loadInitialData() {
    if (!canManageUsers) {
        return;
    }

    try {
        const [rolesFromApi, permissions, scopes, scopeTypes] = await Promise.all([
            fetchAPI(API.roles),
            fetchAPI(API.permissions),
            fetchAPI(API.scopes),
            fetchAPI(API.scopeTypes)
        ]);
        allRoles = getManagedRolesOnly(rolesFromApi, 'loadInitialData');
        allPermissions = permissions;
        allScopes = scopes;
        allScopeTypes = scopeTypes;
    } catch (error) {
        console.error('初期データの読み込みに失敗:', error);
    }
}

// セクション切り替え
function switchSection(section) {
    if (!sectionAccess[section]) {
        showAlert('このセクションを閲覧する権限がありません', 'error');
        return;
    }

    currentSection = section;
    closeMobileDetail();

    // ナビゲーションの更新
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.section === section);
    });

    // リストパネルの切り替え（クラスベース）
    document.querySelectorAll('.list-section').forEach(sec => {
        sec.classList.toggle('active', sec.id === `list-${section}`);
    });

    // 詳細パネルの切り替え（クラスベース）
    document.querySelectorAll('.detail-section-container').forEach(sec => {
        sec.classList.toggle('active', sec.id === `detail-${section}`);
    });

    // 権限セクションの表示切り替え
    const permissionsSection = document.getElementById('permissions-section');
    if (permissionsSection) {
        permissionsSection.style.display = section === 'roles' ? 'block' : 'none';
    }

    // 選択状態をリセット
    if (section === 'users') {
        selectedUserId = null;
        document.getElementById('user-detail').style.display = 'none';
        document.getElementById('user-empty-state').style.display = 'flex';
    } else if (section === 'roles') {
        selectedRoleId = null;
        document.getElementById('role-detail').style.display = 'none';
        document.getElementById('role-empty-state').style.display = 'flex';
    } else if (section === 'scopes') {
        selectedScopeId = null;
        document.getElementById('scope-detail').style.display = 'none';
        document.getElementById('scope-empty-state').style.display = 'flex';
    }

    // セクション固有の読み込み
    if (section === 'roles') {
        loadRoles();
        loadPermissions();
    } else if (section === 'scopes') {
        loadScopes();
    } else if (section === 'users') {
        loadUsers();
    } else if (section === 'access-logs') {
        loadAccessLogs(1);
    }
}

// === API ヘルパー ===
async function fetchAPI(url, options = {}) {
    // undefinedがURLに含まれている場合はエラー
    if (url.includes('undefined') || url.includes('null')) {
        throw new Error('無効なURLパラメータ');
    }

    const response = await fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        }
    });
    const contentType = response.headers.get('content-type') || '';
    const isJson = contentType.includes('application/json');

    if (!response.ok) {
        let errorMessage = 'APIエラー';
        if (isJson) {
            try {
                const error = await response.json();
                errorMessage = error.error || errorMessage;
            } catch (e) {
                errorMessage = `HTTPエラー: ${response.status}`;
            }
        } else {
            errorMessage = `HTTPエラー: ${response.status}`;
        }

        // 認証エラーの場合
        if (response.status === 401) {
            throw new Error('認証が必要です');
        }
        if (response.status === 403) {
            throw new Error('権限がありません');
        }

        throw new Error(errorMessage);
    }

    if (isJson) {
        return response.json();
    }

    const rawText = await response.text();
    try {
        return JSON.parse(rawText);
    } catch (e) {
        throw new Error('APIレスポンスのJSON解析に失敗しました');
    }
}

// === ユーザー管理 ===
async function loadUsers() {
    const search = getUserSearchValue();
    const container = document.getElementById('users-list');

    try {
        const params = new URLSearchParams({page: String(currentPage)});
        if (search) {
            params.set('search', search);
        }
        const data = await fetchAPI(`${API.users}?${params.toString()}`);

        // APIがエラーを返した場合（ステータス200でもerrorプロパティがある場合）
        if (data.error) {
            throw new Error(data.error);
        }

        // usersが配列でない場合は空配列として扱う
        allUsers = Array.isArray(data.users) ? data.users : [];

        console.log('loadUsers: ユーザー数 =', allUsers.length, 'ユーザー一覧:', allUsers);

        container.innerHTML = allUsers.map(user => {
            const roleInfo = getRoleIconInfo(user.roles?.[0]);
            return `
                <div class="list-item ${selectedUserId === user.id ? 'active' : ''}" 
                     onclick="selectUser('${user.id}')">
                    <div class="list-item-icon ${roleInfo.color}">
                        <span class="material-symbols-outlined">${roleInfo.icon}</span>
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title">${escapeHtml(user.name || user.email)}</div>
                        <div class="list-item-subtitle">${escapeHtml(user.email)}</div>
                    </div>
                </div>
            `;
        }).join('');

        renderPagination(data.pages, data.page);

        // 最初のユーザーを自動選択（idが有効な場合のみ）
        if (allUsers.length > 0 && !selectedUserId && allUsers[0].id && !isMobileView()) {
            selectUser(allUsers[0].id);
        }
    } catch (error) {
        console.error('loadUsers error:', error);
        // 認証エラーの場合はログインページへリダイレクト
        if (error.message && (error.message.includes('認証') || error.message.includes('401'))) {
            showAlert('セッションが切れました。再ログインしてください。', 'error');
            setTimeout(() => {
                window.location.href = '/kiweb/teacher-auth/public/login.php';
            }, 2000);
            return;
        }
        showAlert(`ユーザーの読み込みに失敗しました: ${error.message || '不明なエラー'}`, 'error');
    }
}

function getUserSearchValue() {
    const searchField = document.getElementById('user-search');
    return typeof searchField?.value === 'string' ? searchField.value.trim() : '';
}

function getRoleIconInfo(roleName) {
    if (!roleName) return roleIcons.default;
    const key = roleName.toLowerCase();
    return roleIcons[key] || roleIcons.default;
}

async function selectUser(userId) {
    // userIdが無効な場合は早期リターン
    if (!userId || userId === 'undefined' || userId === undefined) {
        console.error('selectUser: 無効なユーザーIDが渡されました:', userId);
        return;
    }

    selectedUserId = userId;

    // リストのアクティブ状態を更新
    document.querySelectorAll('#users-list .list-item').forEach(item => {
        const itemUserId = item.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
        item.classList.toggle('active', itemUserId === userId);
    });

    try {
        const user = await fetchAPI(`${API.users}?id=${userId}`);
        const detailContainer = document.getElementById('user-detail');
        const emptyState = document.getElementById('user-empty-state');

        emptyState.style.display = 'none';
        detailContainer.style.display = 'block';

        const roleInfo = getRoleIconInfo(user.roles?.[0]?.name);

        detailContainer.innerHTML = `
            <div class="detail-header">
                <div class="detail-icon ${roleInfo.color}" style="background: var(--icon-${roleInfo.color}); color: white;">
                    <span class="material-symbols-outlined">${roleInfo.icon}</span>
                </div>
                <h1 class="detail-title">${escapeHtml(user.name || '名前未設定')}</h1>
                <div class="detail-actions">
                    <button class="action-btn action-btn-edit" onclick="editUser('${user.id}')">
                        <div class="action-btn-icon">
                            <span class="material-symbols-outlined">edit</span>
                        </div>
                        <span class="action-btn-label">編集</span>
                    </button>
                    <button class="action-btn action-btn-delete" onclick="deleteUser('${user.id}', '${escapeHtml(user.email)}')">
                        <div class="action-btn-icon">
                            <span class="material-symbols-outlined">delete</span>
                        </div>
                        <span class="action-btn-label">削除</span>
                    </button>
                </div>
            </div>
            
            <div class="detail-divider"></div>
            
            <div class="detail-section">
                <div class="user-info">
                    <div class="user-info-row">
                        <span class="user-info-label">メールアドレス</span>
                        <span class="user-info-value">${escapeHtml(user.email)}</span>
                    </div>
                    <div class="user-info-row">
                        <span class="user-info-label">ステータス</span>
                        <span class="user-info-value">
                            <span class="status-badge status-${user.status}">${getStatusLabel(user.status)}</span>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="detail-divider"></div>
            
            <div class="detail-section">
                <div class="detail-section-label">役職</div>
                <div class="user-roles">
                    ${(user.roles || []).map(r => `<span class="role-tag">${escapeHtml(r.description || r.name)}</span>`).join('') || '<span style="color: var(--text-secondary);">なし</span>'}
                </div>
            </div>
            
            <div class="detail-divider"></div>
            
            <div class="detail-section">
                <div class="detail-section-label">担当範囲</div>
                <div class="user-roles">
                    ${(user.scopes || []).map(s => `<span class="role-tag">${escapeHtml(s.display_name)}</span>`).join('') || '<span style="color: var(--text-secondary);">なし</span>'}
                </div>
            </div>
        `;
        openMobileDetail();
    } catch (error) {
        showAlert('ユーザー情報の取得に失敗しました', 'error');
    }
}

function renderPagination(totalPages, current) {
    const container = document.getElementById('users-pagination');
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = `<button class="page-btn" ${current === 1 ? 'disabled' : ''} onclick="goToPage(${current - 1})">前へ</button>`;

    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
    }

    html += `<button class="page-btn" ${current === totalPages ? 'disabled' : ''} onclick="goToPage(${current + 1})">次へ</button>`;

    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    selectedUserId = null;
    loadUsers();
}

// === アクセスログ ===
function getAccessLogFilters() {
    return {
        date_from: (document.getElementById('log-date-from')?.value || '').trim(),
        date_to: (document.getElementById('log-date-to')?.value || '').trim(),
        user_search: (document.getElementById('log-user-search')?.value || '').trim(),
        page_path: (document.getElementById('log-page-path')?.value || '').trim()
    };
}

async function loadAccessLogs(page = 1) {
    accessLogsCurrentPage = Math.max(1, Number(page) || 1);
    const filters = getAccessLogFilters();
    const params = new URLSearchParams({
        page: String(accessLogsCurrentPage),
        per_page: '50'
    });

    if (filters.date_from) params.set('date_from', filters.date_from);
    if (filters.date_to) params.set('date_to', filters.date_to);
    if (filters.user_search) params.set('user_search', filters.user_search);
    if (filters.page_path) params.set('page_path', filters.page_path);

    try {
        const data = await fetchAPI(`${API.accessLogs}?${params.toString()}`);
        renderAccessLogRows(data.items || []);
        renderAccessLogPagination(data.pages || 0, data.page || 1);
        const totalLabel = document.getElementById('access-logs-total-label');
        if (totalLabel) {
            totalLabel.textContent = `全 ${Number(data.total || 0).toLocaleString()} 件`;
        }
        if (currentSection === 'access-logs') {
            openMobileDetail();
        }
    } catch (error) {
        showAlert(`アクセスログの読み込みに失敗しました: ${error.message || '不明なエラー'}`, 'error');
    }
}

function clearAccessLogFilters() {
    const ids = ['log-date-from', 'log-date-to', 'log-user-search', 'log-page-path'];
    ids.forEach((id) => {
        const field = document.getElementById(id);
        if (field) {
            field.value = '';
        }
    });
    loadAccessLogs(1);
}

function renderAccessLogRows(items) {
    const tbody = document.getElementById('access-logs-tbody');
    if (!tbody) {
        return;
    }

    if (!Array.isArray(items) || items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="access-log-empty-cell">対象データがありません。</td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = items.map((item) => {
        const userName = item.user_name || '-';
        const userEmail = item.user_email || '-';
        const pagePath = item.page_path || '-';
        const ipAddress = item.ip_address || '-';
        const userAgent = item.user_agent || '-';

        return `
            <tr>
                <td>${escapeHtml(item.created_at || '-')}</td>
                <td>${escapeHtml(userName)}</td>
                <td>${escapeHtml(userEmail)}</td>
                <td title="${escapeHtml(pagePath)}">${escapeHtml(pagePath)}</td>
                <td>${escapeHtml(ipAddress)}</td>
                <td title="${escapeHtml(userAgent)}">${escapeHtml(truncateText(userAgent, 100))}</td>
            </tr>
        `;
    }).join('');
}

function renderAccessLogPagination(totalPages, current) {
    const container = document.getElementById('access-logs-pagination');
    if (!container) {
        return;
    }

    if (!totalPages || totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = `<button class="page-btn" ${current <= 1 ? 'disabled' : ''} onclick="goToAccessLogPage(${current - 1})">前へ</button>`;
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="goToAccessLogPage(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" ${current >= totalPages ? 'disabled' : ''} onclick="goToAccessLogPage(${current + 1})">次へ</button>`;
    container.innerHTML = html;
}

function goToAccessLogPage(page) {
    loadAccessLogs(page);
}

async function openUserModal(userId = null) {
    const modal = document.getElementById('user-modal');
    const title = document.getElementById('user-modal-title');
    const form = document.getElementById('user-form');
    const passwordGroup = document.getElementById('user-password-group');
    const roleLockNotice = document.getElementById('user-role-lock-notice');

    resetFormWithMdComponents(form);
    document.getElementById('user-id').value = userId || '';
    document.getElementById('user-status').value = 'active';

    if (userId) {
        title.textContent = 'ユーザー編集';
        passwordGroup.style.display = 'none';

        try {
            const user = await fetchAPI(`${API.users}?id=${userId}`);
            document.getElementById('user-name').value = user.name || '';
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-status').value = user.status;

            const isSelfEdit = String(userId) === currentUserId;
            renderUserRoleRadios(user.roles.map(r => r.id), isSelfEdit);
            if (roleLockNotice) {
                roleLockNotice.style.display = isSelfEdit ? 'block' : 'none';
            }
            renderUserScopesCheckboxes(user.scopes.map(s => s.id));
        } catch (error) {
            showAlert('ユーザー情報の取得に失敗しました', 'error');
            return;
        }
    } else {
        title.textContent = '新規ユーザー';
        passwordGroup.style.display = 'block';
        renderUserRoleRadios([]);
        if (roleLockNotice) {
            roleLockNotice.style.display = 'none';
        }
        renderUserScopesCheckboxes([]);
    }

    modal.classList.add('active');
}

function renderUserRoleRadios(selectedIds, disabled = false) {
    const container = document.getElementById('user-roles-checkboxes');
    const selectedId = Array.isArray(selectedIds) && selectedIds.length > 0 ? selectedIds[0] : null;
    const selectedIdString = selectedId === null ? '' : String(selectedId);

    container.innerHTML = allRoles.map(role => `
        <div class="checkbox-item">
            <input type="radio" id="role-${role.id}" name="role" value="${role.id}" 
                ${String(role.id) === selectedIdString ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
            <label for="role-${role.id}">${escapeHtml(role.description || role.name)}</label>
        </div>
    `).join('');
}

function renderUserScopesCheckboxes(selectedIds) {
    const container = document.getElementById('user-scopes-checkboxes');
    const groupedScopes = {};

    allScopes.forEach(scope => {
        if (!groupedScopes[scope.type_display_name]) {
            groupedScopes[scope.type_display_name] = [];
        }
        groupedScopes[scope.type_display_name].push(scope);
    });

    container.innerHTML = Object.entries(groupedScopes).map(([typeName, scopes]) => `
        <div style="width: 100%; margin-bottom: 12px;">
            <strong style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(typeName)}</strong>
            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px;">
                ${scopes.map(scope => `
                    <div class="checkbox-item">
                        <input type="checkbox" id="scope-${scope.id}" name="scopes" value="${scope.id}"
                            ${selectedIds.includes(scope.id) ? 'checked' : ''}>
                        <label for="scope-${scope.id}">${escapeHtml(scope.display_name)}</label>
                    </div>
                `).join('')}
            </div>
        </div>
    `).join('');
}

async function handleUserSubmit(e) {
    e.preventDefault();

    const userId = document.getElementById('user-id').value;
    const isNew = !userId;
    const isSelfEdit = !isNew && String(userId) === currentUserId;
    const selectedRole = document.querySelector('input[name="role"]:checked');

    const userData = {
        name: document.getElementById('user-name').value,
        email: document.getElementById('user-email').value,
        status: document.getElementById('user-status').value
    };

    if (isNew) {
        userData.password = document.getElementById('user-password').value;
    }

    if (!isSelfEdit && !selectedRole) {
        showAlert('役職を1つ選択してください', 'error');
        return;
    }

    try {
        if (isNew) {
            const result = await fetchAPI(API.users, {
                method: 'POST',
                body: JSON.stringify(userData)
            });

            await updateUserRolesAndScopes(result.id);
            showAlert('ユーザーを作成しました', 'success');
        } else {
            await fetchAPI(`${API.users}?id=${userId}`, {
                method: 'PUT',
                body: JSON.stringify(userData)
            });
            await updateUserRolesAndScopes(userId);
            showAlert('ユーザーを更新しました', 'success');
        }

        closeAllModals();
        loadUsers();
        if (selectedUserId) {
            selectUser(selectedUserId);
        }
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

async function updateUserRolesAndScopes(userId) {
    const isSelfEdit = String(userId) === currentUserId;
    const currentScopes = await fetchAPI(`${API.userScopes}?user_id=${userId}`);

    const selectedRole = document.querySelector('input[name="role"]:checked');
    const selectedScopes = Array.from(document.querySelectorAll('input[name="scopes"]:checked')).map(cb => cb.value);

    if (!isSelfEdit) {
        if (!selectedRole) {
            throw new Error('役職を1つ選択してください');
        }

        await fetchAPI(`${API.userRoles}?user_id=${userId}`, {
            method: 'PUT',
            body: JSON.stringify({ role_id: selectedRole.value })
        });
    }

    // スコープの更新
    const currentScopeIds = currentScopes.map(s => String(s.id));
    for (const scopeId of selectedScopes) {
        if (!currentScopeIds.includes(scopeId)) {
            await fetchAPI(`${API.userScopes}?user_id=${userId}`, {
                method: 'POST',
                body: JSON.stringify({ scope_id: scopeId })
            });
        }
    }
    for (const scope of currentScopes) {
        if (!selectedScopes.includes(String(scope.id))) {
            await fetchAPI(`${API.userScopes}?user_id=${userId}&scope_id=${scope.id}`, {
                method: 'DELETE'
            });
        }
    }
}

function editUser(userId) {
    openUserModal(userId);
}

function deleteUser(userId, email) {
    showConfirm(`「${email}」を削除しますか？`, async () => {
        try {
            await fetchAPI(`${API.users}?id=${userId}`, { method: 'DELETE' });
            showAlert('ユーザーを削除しました', 'success');
            selectedUserId = null;
            document.getElementById('user-detail').style.display = 'none';
            document.getElementById('user-empty-state').style.display = 'flex';
            loadUsers();
        } catch (error) {
            showAlert(error.message, 'error');
        }
    });
}

// === 役職管理 ===
async function loadRoles() {
    try {
        const rolesFromApi = await fetchAPI(API.roles);
        allRoles = getManagedRolesOnly(rolesFromApi, 'loadRoles');
        const container = document.getElementById('roles-list');

        container.innerHTML = allRoles.map(role => {
            const iconInfo = getRoleIconInfo(role.name);
            return `
                <div class="list-item ${selectedRoleId === role.id ? 'active' : ''}" 
                     onclick="selectRole(${role.id})">
                    <div class="list-item-icon ${iconInfo.color}">
                        <span class="material-symbols-outlined">${iconInfo.icon}</span>
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title">${escapeHtml(role.description || role.name)}</div>
                        <div class="list-item-subtitle">${role.user_count || 0}人</div>
                    </div>
                </div>
            `;
        }).join('');

        // 最初の役職を自動選択
        if (allRoles.length > 0 && !selectedRoleId && !isMobileView()) {
            selectRole(allRoles[0].id);
        }
    } catch (error) {
        showAlert('役職の読み込みに失敗しました', 'error');
    }
}

async function selectRole(roleId) {
    selectedRoleId = roleId;

    // リストのアクティブ状態を更新
    document.querySelectorAll('#roles-list .list-item').forEach(item => {
        const itemRoleId = item.getAttribute('onclick')?.match(/\((\d+)\)/)?.[1];
        item.classList.toggle('active', itemRoleId === String(roleId));
    });

    try {
        const role = await fetchAPI(`${API.roles}?id=${roleId}`);
        const detailContainer = document.getElementById('role-detail');
        const emptyState = document.getElementById('role-empty-state');

        emptyState.style.display = 'none';
        detailContainer.style.display = 'block';

        const iconInfo = getRoleIconInfo(role.name);
        const rolePermissionIds = role.permissions.map(p => p.id);

        detailContainer.innerHTML = `
            <div class="detail-header">
                <div class="detail-icon" style="background: var(--icon-${iconInfo.color}); color: white;">
                    <span class="material-symbols-outlined">${iconInfo.icon}</span>
                </div>
                <h1 class="detail-title">${escapeHtml(role.description || role.name)}</h1>
                <div class="detail-actions">
                    <button class="action-btn action-btn-edit" onclick="openRoleModal(${role.id})">
                        <div class="action-btn-icon">
                            <span class="material-symbols-outlined">edit</span>
                        </div>
                        <span class="action-btn-label">編集</span>
                    </button>
                    <button class="action-btn action-btn-delete" onclick="deleteRole(${role.id}, '${escapeHtml(role.name)}')">
                        <div class="action-btn-icon">
                            <span class="material-symbols-outlined">delete</span>
                        </div>
                        <span class="action-btn-label">削除</span>
                    </button>
                </div>
            </div>
            
            <div class="detail-divider"></div>
            
            <div class="detail-section">
                <div class="detail-section-label">コード名</div>
                <div class="detail-section-content" style="font-family: monospace; color: var(--primary);">
                    ${escapeHtml(role.name)}
                </div>
            </div>
            
            <div class="detail-divider"></div>
            
            <div class="detail-section">
                <div class="detail-section-label">この役職に付与された権限</div>
                <ul class="permission-list">
                    ${allPermissions.map(perm => `
                        <li class="permission-item">
                            <div class="permission-checkbox ${rolePermissionIds.includes(perm.id) ? 'checked' : ''}"
                                 onclick="toggleRolePermission(${roleId}, ${perm.id}, ${!rolePermissionIds.includes(perm.id)})">
                            </div>
                            <span class="permission-label" 
                                  onclick="toggleRolePermission(${roleId}, ${perm.id}, ${!rolePermissionIds.includes(perm.id)})">
                                ${escapeHtml(perm.description || perm.name)}
                            </span>
                        </li>
                    `).join('')}
                </ul>
            </div>
        `;
        openMobileDetail();
    } catch (error) {
        showAlert('役職情報の取得に失敗しました', 'error');
    }
}

async function toggleRolePermission(roleId, permissionId, checked) {
    try {
        if (checked) {
            await fetchAPI(`${API.rolePermissions}?role_id=${roleId}`, {
                method: 'POST',
                body: JSON.stringify({ permission_id: permissionId })
            });
        } else {
            await fetchAPI(`${API.rolePermissions}?role_id=${roleId}&permission_id=${permissionId}`, {
                method: 'DELETE'
            });
        }
        // 詳細を再読み込み
        selectRole(roleId);
    } catch (error) {
        showAlert('権限の更新に失敗しました', 'error');
    }
}

function openRoleModal(roleId = null) {
    const modal = document.getElementById('role-modal');
    const title = document.getElementById('role-modal-title');
    const form = document.getElementById('role-form');

    resetFormWithMdComponents(form);
    document.getElementById('role-id').value = roleId || '';

    if (roleId) {
        title.textContent = '役職編集';
        const role = allRoles.find(r => r.id === roleId);
        if (role) {
            document.getElementById('role-name').value = role.name;
            document.getElementById('role-description').value = role.description || '';
        }
    } else {
        title.textContent = '新規役職';
    }

    modal.classList.add('active');
}

async function handleRoleSubmit(e) {
    e.preventDefault();

    const roleId = document.getElementById('role-id').value;
    const roleData = {
        name: document.getElementById('role-name').value,
        description: document.getElementById('role-description').value
    };

    try {
        if (roleId) {
            await fetchAPI(`${API.roles}?id=${roleId}`, {
                method: 'PUT',
                body: JSON.stringify(roleData)
            });
            showAlert('役職を更新しました', 'success');
        } else {
            await fetchAPI(API.roles, {
                method: 'POST',
                body: JSON.stringify(roleData)
            });
            showAlert('役職を作成しました', 'success');
        }

        closeAllModals();
        loadRoles();
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

function deleteRole(roleId, name) {
    if (['admin', 'full_time_teacher', 'part_time_teacher', 'part_time_staff', 'student', 'teacher'].includes(name)) {
        showAlert('システム役職は削除できません', 'error');
        return;
    }

    showConfirm(`役職「${name}」を削除しますか？`, async () => {
        try {
            await fetchAPI(`${API.roles}?id=${roleId}`, { method: 'DELETE' });
            showAlert('役職を削除しました', 'success');
            selectedRoleId = null;
            document.getElementById('role-detail').style.display = 'none';
            document.getElementById('role-empty-state').style.display = 'flex';
            loadRoles();
        } catch (error) {
            showAlert(error.message, 'error');
        }
    });
}

// === 権限管理 ===
async function loadPermissions() {
    try {
        allPermissions = await fetchAPI(API.permissions);
        const container = document.getElementById('permissions-list');

        container.innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px;">
                ${allPermissions.map(perm => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--sidebar-bg); border-radius: 8px;">
                        <div>
                            <div style="font-size: 13px; font-weight: 500; font-family: monospace; color: var(--primary);">${escapeHtml(perm.name)}</div>
                            <div style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(perm.description || '')}</div>
                        </div>
                        <div style="display: flex; gap: 4px;">
                            <button class="btn btn-icon icon-action-btn icon-action-edit" onclick="openPermissionModal(${perm.id})" aria-label="編集">
                                <span class="material-symbols-outlined" style="font-size: 16px;">edit</span>
                            </button>
                            <button class="btn btn-icon icon-action-btn icon-action-delete" onclick="deletePermission(${perm.id}, '${escapeHtml(perm.name)}')" aria-label="削除">
                                <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    } catch (error) {
        showAlert('権限の読み込みに失敗しました', 'error');
    }
}

function openPermissionModal(permissionId = null) {
    const modal = document.getElementById('permission-modal');
    const title = document.getElementById('permission-modal-title');
    const form = document.getElementById('permission-form');

    resetFormWithMdComponents(form);
    document.getElementById('permission-id').value = permissionId || '';

    if (permissionId) {
        title.textContent = '権限編集';
        const perm = allPermissions.find(p => p.id === permissionId);
        if (perm) {
            document.getElementById('permission-name').value = perm.name;
            document.getElementById('permission-description').value = perm.description || '';
        }
    } else {
        title.textContent = '新規権限';
    }

    modal.classList.add('active');
}

async function handlePermissionSubmit(e) {
    e.preventDefault();

    const permissionId = document.getElementById('permission-id').value;
    const permData = {
        name: document.getElementById('permission-name').value,
        description: document.getElementById('permission-description').value
    };

    try {
        if (permissionId) {
            await fetchAPI(`${API.permissions}?id=${permissionId}`, {
                method: 'PUT',
                body: JSON.stringify(permData)
            });
            showAlert('権限を更新しました', 'success');
        } else {
            await fetchAPI(API.permissions, {
                method: 'POST',
                body: JSON.stringify(permData)
            });
            showAlert('権限を作成しました', 'success');
        }

        closeAllModals();
        loadPermissions();
        if (selectedRoleId) {
            selectRole(selectedRoleId);
        }
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

function deletePermission(permissionId, name) {
    showConfirm(`権限「${name}」を削除しますか？`, async () => {
        try {
            await fetchAPI(`${API.permissions}?id=${permissionId}`, { method: 'DELETE' });
            showAlert('権限を削除しました', 'success');
            loadPermissions();
        } catch (error) {
            showAlert(error.message, 'error');
        }
    });
}

// === スコープ管理 ===
async function loadScopes() {
    try {
        [allScopes, allScopeTypes] = await Promise.all([
            fetchAPI(API.scopes),
            fetchAPI(API.scopeTypes)
        ]);

        const container = document.getElementById('scopes-list');

        // スコープタイプでグループ化
        const grouped = {};
        allScopeTypes.forEach(type => {
            grouped[type.name] = {
                type,
                scopes: allScopes.filter(s => s.type_name === type.name)
            };
        });

        container.innerHTML = Object.values(grouped).flatMap(({ type, scopes }) =>
            scopes.map(scope => `
                <div class="list-item ${selectedScopeId === scope.id ? 'active' : ''}" 
                     onclick="selectScope(${scope.id})">
                    <div class="list-item-icon ${type.name === 'campus' ? 'green' : 'purple'}">
                        <span class="material-symbols-outlined">${type.name === 'campus' ? 'location_on' : 'apartment'}</span>
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title">${escapeHtml(scope.display_name)}</div>
                        <div class="list-item-subtitle">${escapeHtml(type.display_name)}</div>
                    </div>
                </div>
            `)
        ).join('');

        // スコープタイプのセレクトボックスを更新
        const typeSelect = document.getElementById('scope-type');
        typeSelect.innerHTML = allScopeTypes.map(type =>
            `<option value="${type.id}">${escapeHtml(type.display_name)}</option>`
        ).join('');
        if (!typeSelect.value && allScopeTypes.length > 0) {
            typeSelect.value = String(allScopeTypes[0].id);
        }

        // 最初のスコープを自動選択
        if (allScopes.length > 0 && !selectedScopeId && !isMobileView()) {
            selectScope(allScopes[0].id);
        }
    } catch (error) {
        showAlert('スコープの読み込みに失敗しました', 'error');
    }
}

async function selectScope(scopeId) {
    selectedScopeId = scopeId;

    // リストのアクティブ状態を更新
    document.querySelectorAll('#scopes-list .list-item').forEach(item => {
        const itemScopeId = item.getAttribute('onclick')?.match(/\((\d+)\)/)?.[1];
        item.classList.toggle('active', itemScopeId === String(scopeId));
    });

    const scope = allScopes.find(s => s.id === scopeId);
    if (!scope) return;

    const detailContainer = document.getElementById('scope-detail');
    const emptyState = document.getElementById('scope-empty-state');

    emptyState.style.display = 'none';
    detailContainer.style.display = 'block';

    const scopeType = allScopeTypes.find(t => t.id === scope.scope_type_id);
    const isLocation = scopeType?.name === 'campus';

    detailContainer.innerHTML = `
        <div class="detail-header">
            <div class="detail-icon" style="background: var(--icon-${isLocation ? 'green' : 'purple'}); color: white;">
                <span class="material-symbols-outlined">${isLocation ? 'location_on' : 'apartment'}</span>
            </div>
            <h1 class="detail-title">${escapeHtml(scope.display_name)}</h1>
            <div class="detail-actions">
                <button class="action-btn action-btn-edit" onclick="openScopeModal(${scope.id})">
                    <div class="action-btn-icon">
                        <span class="material-symbols-outlined">edit</span>
                    </div>
                    <span class="action-btn-label">編集</span>
                </button>
                <button class="action-btn action-btn-delete" onclick="deleteScope(${scope.id}, '${escapeHtml(scope.display_name)}')">
                    <div class="action-btn-icon">
                        <span class="material-symbols-outlined">delete</span>
                    </div>
                    <span class="action-btn-label">削除</span>
                </button>
            </div>
        </div>
        
        <div class="detail-divider"></div>
        
        <div class="detail-section">
            <div class="user-info">
                <div class="user-info-row">
                    <span class="user-info-label">種類</span>
                    <span class="user-info-value">${escapeHtml(scope.type_display_name)}</span>
                </div>
                <div class="user-info-row">
                    <span class="user-info-label">コード名</span>
                    <span class="user-info-value" style="font-family: monospace; color: var(--primary);">${escapeHtml(scope.name)}</span>
                </div>
                ${scope.description ? `
                <div class="user-info-row">
                    <span class="user-info-label">説明</span>
                    <span class="user-info-value">${escapeHtml(scope.description)}</span>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    openMobileDetail();
}

function openScopeModal(scopeId = null) {
    const modal = document.getElementById('scope-modal');
    const title = document.getElementById('scope-modal-title');
    const form = document.getElementById('scope-form');

    resetFormWithMdComponents(form);
    document.getElementById('scope-id').value = scopeId || '';

    if (scopeId) {
        title.textContent = 'スコープ編集';
        const scope = allScopes.find(s => s.id === scopeId);
        if (scope) {
            document.getElementById('scope-type').value = scope.scope_type_id;
            document.getElementById('scope-name').value = scope.name;
            document.getElementById('scope-display-name').value = scope.display_name;
            document.getElementById('scope-description').value = scope.description || '';
        }
    } else {
        title.textContent = '新規スコープ';
        const scopeTypeField = document.getElementById('scope-type');
        if (scopeTypeField && allScopeTypes.length > 0) {
            scopeTypeField.value = String(allScopeTypes[0].id);
        }
    }

    modal.classList.add('active');
}

async function handleScopeSubmit(e) {
    e.preventDefault();

    const scopeId = document.getElementById('scope-id').value;
    const scopeData = {
        scope_type_id: document.getElementById('scope-type').value,
        name: document.getElementById('scope-name').value,
        display_name: document.getElementById('scope-display-name').value,
        description: document.getElementById('scope-description').value
    };

    try {
        if (scopeId) {
            await fetchAPI(`${API.scopes}?id=${scopeId}`, {
                method: 'PUT',
                body: JSON.stringify(scopeData)
            });
            showAlert('スコープを更新しました', 'success');
        } else {
            await fetchAPI(API.scopes, {
                method: 'POST',
                body: JSON.stringify(scopeData)
            });
            showAlert('スコープを作成しました', 'success');
        }

        closeAllModals();
        loadScopes();
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

function deleteScope(scopeId, name) {
    showConfirm(`「${name}」を削除しますか？`, async () => {
        try {
            await fetchAPI(`${API.scopes}?id=${scopeId}`, { method: 'DELETE' });
            showAlert('スコープを削除しました', 'success');
            selectedScopeId = null;
            document.getElementById('scope-detail').style.display = 'none';
            document.getElementById('scope-empty-state').style.display = 'flex';
            loadScopes();
        } catch (error) {
            showAlert(error.message, 'error');
        }
    });
}

// === ヘルパー関数 ===
function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });
}

function showAlert(message, type = 'success') {
    let alert = document.querySelector('.admin-alert');
    if (!alert) {
        alert = document.createElement('div');
        alert.className = 'admin-alert';
        document.body.appendChild(alert);
    }

    alert.textContent = message;
    alert.className = `admin-alert show ${type}`;

    setTimeout(() => {
        alert.classList.remove('show');
    }, 3000);
}

function showConfirm(message, onConfirm) {
    const modal = document.getElementById('confirm-modal');
    document.getElementById('confirm-message').textContent = message;

    const confirmBtn = document.getElementById('confirm-btn');
    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

    newBtn.addEventListener('click', () => {
        closeAllModals();
        onConfirm();
    });

    modal.classList.add('active');
}

function truncateText(text, maxLength) {
    const stringValue = String(text || '');
    if (stringValue.length <= maxLength) {
        return stringValue;
    }
    return `${stringValue.slice(0, maxLength)}...`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getStatusLabel(status) {
    const labels = {
        active: '有効',
        locked: 'ロック',
        deleted: '削除済み'
    };
    return labels[status] || status;
}
