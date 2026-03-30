-- 管理者権限追加マイグレーション
-- 実行日: 2026-01-30

-- 管理者権限を追加
INSERT INTO permissions (name, description) VALUES
    ('manage_users', 'ユーザー管理'),
    ('manage_roles', 'ロール・権限管理')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 管理者ロールに新しい権限を付与
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.name = 'admin' AND p.name IN ('manage_users', 'manage_roles')
ON DUPLICATE KEY UPDATE role_id = role_id;
