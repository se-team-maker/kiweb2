-- スコープデータ更新SQL
-- 実行日: 2026-01-30
-- 校舎と部署のマスターデータを更新

-- ========================================
-- 既存のスコープデータをクリア（user_scopesに紐づいていないもののみ）
-- ※注意: 実運用時はバックアップを取ってから実行してください
-- ========================================

-- 既存の校舎データを削除して再挿入
DELETE FROM scopes WHERE scope_type_id = (SELECT id FROM scope_types WHERE name = 'campus')
  AND id NOT IN (SELECT scope_id FROM user_scopes);

-- 既存の部署データを削除して再挿入  
DELETE FROM scopes WHERE scope_type_id = (SELECT id FROM scope_types WHERE name = 'department')
  AND id NOT IN (SELECT scope_id FROM user_scopes);

-- ========================================
-- 校舎データ（2校）
-- ========================================
INSERT INTO scopes (scope_type_id, name, display_name, description) VALUES
    ((SELECT id FROM scope_types WHERE name = 'campus'), 'shijo_karasuma', '四条烏丸校', '四条烏丸校'),
    ((SELECT id FROM scope_types WHERE name = 'campus'), 'enmachi', '円町校', '円町校')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description);

-- ========================================
-- 部署データ（18部署）
-- ========================================
INSERT INTO scopes (scope_type_id, name, display_name, description) VALUES
    -- 本部
    ((SELECT id FROM scope_types WHERE name = 'department'), 'kosotsu_honbu', '高卒本部', '高卒本部'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'geneki_honbu', '現役本部', '現役本部'),
    
    -- 管理部門
    ((SELECT id FROM scope_types WHERE name = 'department'), 'keiei_kikaku', '経営企画部', '経営企画部'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'gyomu_suishin', '業務推進部', '業務推進部'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'koho_eigyo', '広報営業課', '広報営業課'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'jinji_saiyo', '人事採用課', '人事採用課'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'sogo_uketsuke', '総合受付課', '総合受付課'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'somu', '総務課', '総務課'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'keiri', '経理課', '経理課'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'jimu_shuchu', '事務集中課', '事務集中課'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'unei_kanri', '運営管理室', '運営管理室'),
    
    -- 講師関連
    ((SELECT id FROM scope_types WHERE name = 'department'), 'koshi_shikko', '講師部執行室', '講師部執行室'),
    
    -- 教科
    ((SELECT id FROM scope_types WHERE name = 'department'), 'eigo_ka', '英語科', '英語科'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'sugaku_ka', '数学科', '数学科'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'kagaku_ka', '化学科', '化学科'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'seibutsu_ka', '生物科', '生物科'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'butsuri_ka', '物理科', '物理科'),
    ((SELECT id FROM scope_types WHERE name = 'department'), 'kokugo_shakai_ka', '国語・社会科', '国語・社会科')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description);

-- 確認用クエリ
-- SELECT st.display_name as type, s.display_name as scope_name 
-- FROM scopes s 
-- JOIN scope_types st ON s.scope_type_id = st.id 
-- ORDER BY st.name, s.id;
