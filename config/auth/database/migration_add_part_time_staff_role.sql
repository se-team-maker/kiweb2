-- 非常勤属性分割: 講師 / 講師以外
-- 実行日: 2026-03-03

-- 1) 新ロール追加（存在時は説明を更新）
INSERT INTO roles (name, description) VALUES
    ('part_time_staff', '非常勤（講師以外）')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 2) 既存ロール説明の明確化
UPDATE roles
SET description = '非常勤（講師）'
WHERE name = 'part_time_teacher';

-- 3) part_time_teacher の権限を part_time_staff にコピー
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT target_role.id, rp.permission_id
FROM role_permissions rp
JOIN roles source_role ON source_role.id = rp.role_id
JOIN roles target_role ON target_role.name = 'part_time_staff'
WHERE source_role.name = 'part_time_teacher';
