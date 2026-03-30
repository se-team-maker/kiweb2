-- ロール再編マイグレーション
-- teacher/student を 専任(full_time_teacher)・非常勤(part_time_teacher)・システム管理者(admin) へ統一

-- 1) 新ロールを作成・更新
INSERT INTO roles (name, description) VALUES
    ('full_time_teacher', '専任'),
    ('part_time_teacher', '非常勤'),
    ('admin', 'システム管理者')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 2) 既存 teacher / student ユーザーを非常勤へ移行
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT ur.user_id, pr.id
FROM user_roles ur
JOIN roles legacy ON legacy.id = ur.role_id
JOIN roles pr ON pr.name = 'part_time_teacher'
WHERE legacy.name IN ('teacher', 'student');

-- 3) teacher の権限を専任/非常勤へコピー
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT nr.id, rp.permission_id
FROM roles tr
JOIN role_permissions rp ON rp.role_id = tr.id
JOIN roles nr ON nr.name IN ('full_time_teacher', 'part_time_teacher')
WHERE tr.name = 'teacher';

-- 4) 旧ロールの付与を削除
DELETE ur
FROM user_roles ur
JOIN roles legacy ON legacy.id = ur.role_id
WHERE legacy.name IN ('teacher', 'student');

-- 5) 旧ロールを削除
DELETE FROM roles
WHERE name IN ('teacher', 'student');
