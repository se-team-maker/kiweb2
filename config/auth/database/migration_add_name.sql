-- 名前カラムを追加
-- 実行: mysql -u [user] -p [database] < migration_add_name.sql

ALTER TABLE users ADD COLUMN name VARCHAR(100) NULL AFTER email;
