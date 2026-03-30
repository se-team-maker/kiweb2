-- ログインシステム データベーススキーマ
-- お名前.com レンタルサーバー MySQL用

-- ユーザーテーブル
CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY,  -- UUID
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(100) NULL,           -- 氏名
    password_hash VARCHAR(255) DEFAULT NULL,  -- Argon2id ハッシュ（任意設定）
    status ENUM('active', 'locked', 'deleted') DEFAULT 'active',
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ロールテーブル
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 権限テーブル
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ユーザー・ロール紐付け
CREATE TABLE IF NOT EXISTS user_roles (
    user_id CHAR(36) NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ロール・権限紐付け
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WebAuthn クレデンシャル
CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id VARCHAR(255) PRIMARY KEY,  -- credential_id (base64)
    user_id CHAR(36) NOT NULL,
    public_key TEXT NOT NULL,
    counter INT UNSIGNED DEFAULT 0,
    device_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WebAuthn チャレンジ（一時保存）
CREATE TABLE IF NOT EXISTS webauthn_challenges (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36),
    challenge VARCHAR(255) NOT NULL,
    type ENUM('register', 'login') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- メールログイン用トークン
CREATE TABLE IF NOT EXISTS login_tokens (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    purpose ENUM('login', 'verify', 'reset') NOT NULL DEFAULT 'login',  -- 用途（追加）
    token_hash VARCHAR(255) NOT NULL,
    code CHAR(6) NOT NULL,  -- 6桁コード
    used BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,         -- 検証済み日時（追加）
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_purpose (purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- レート制限用
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,  -- IP, email, or token
    identifier_type ENUM('ip', 'email', 'token') NOT NULL, -- tokenを追加
    attempt_count INT DEFAULT 1,
    first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    INDEX idx_identifier (identifier, identifier_type),
    INDEX idx_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 監査ログ
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36),
    event_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- アクセスログ
CREATE TABLE IF NOT EXISTS access_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36),
    page_path VARCHAR(500) NOT NULL,
    request_method VARCHAR(10) NOT NULL DEFAULT 'GET',
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_page_path (page_path(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初期ロールの挿入
INSERT INTO roles (name, description) VALUES
    ('full_time_teacher', '専任'),
    ('part_time_teacher', '非常勤（講師）'),
    ('part_time_staff', '非常勤（講師以外）'),
    ('admin', 'システム管理者')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 初期権限の挿入
INSERT INTO permissions (name, description) VALUES
    ('view_dashboard', 'ダッシュボード閲覧'),
    ('manage_users', 'ユーザー管理'),
    ('manage_roles', 'ロール管理'),
    ('view_audit_logs', '監査ログ閲覧')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 管理者ロールに全権限を付与
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
