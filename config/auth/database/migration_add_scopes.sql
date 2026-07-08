-- スコープ（担当範囲）追加マイグレーション
-- 実行日: 2026-01-30

-- ========================================
-- スコープタイプ（種類）テーブル
-- 例: campus（校舎）, department（部署）
-- ========================================
CREATE TABLE IF NOT EXISTS scope_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,        -- 英語名（コード用）: campus, department
    display_name VARCHAR(100) NOT NULL,      -- 日本語名（表示用）: 校舎, 部署
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- スコープ値テーブル
-- 例: 四条烏丸, 京都駅前, 教務部, 総務部
-- ========================================
CREATE TABLE IF NOT EXISTS scopes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope_type_id INT NOT NULL,              -- どの種類のスコープか
    name VARCHAR(100) NOT NULL,              -- 英語名（コード用）: shijo_karasuma
    display_name VARCHAR(100) NOT NULL,      -- 日本語名（表示用）: 四条烏丸
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scope_type_id) REFERENCES scope_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_scope (scope_type_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- ユーザー・スコープ紐付けテーブル
-- 1人のユーザーが複数のスコープを持てる
-- 例: 田中さん → 四条烏丸校舎, 教務部
-- ========================================
CREATE TABLE IF NOT EXISTS user_scopes (
    user_id CHAR(36) NOT NULL,
    scope_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, scope_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (scope_id) REFERENCES scopes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 初期データ: スコープタイプ
-- ========================================
INSERT INTO scope_types (name, display_name, description) VALUES
    ('campus', '校舎', '所属する校舎'),
    ('department', '部署', '所属する部署')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- ========================================
-- 初期データ: スコープ値（校舎）
-- ※ 実際の校舎名に合わせて変更してください
-- ========================================
INSERT INTO scopes (scope_type_id, name, display_name, description) VALUES
    ((SELECT id FROM scope_types WHERE name = 'campus'), 'shijo_karasuma', '四条烏丸', '四条烏丸校舎'),
    ((SELECT id FROM scope_types WHERE name = 'campus'), 'kyoto_station', '京都駅前', '京都駅前校舎')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- ========================================
-- 初期データ: スコープ値（部署）
-- ※ 実際の部署名に合わせて変更してください
-- ========================================
INSERT INTO scopes (scope_type_id, name, display_name, description) VALUES
    ((SELECT id FROM scope_types WHERE name = 'department'), 'kyomu', '教務', '教務部'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'somu', '総務', '総務部'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'keiri', '経理', '経理部')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);
