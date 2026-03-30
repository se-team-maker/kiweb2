-- =====================================================
-- 京都医塾アカウント機能拡張 マイグレーション
-- 実行前に必ずバックアップを取得してください
-- =====================================================

-- 1. login_tokens に purpose 列を追加
-- 用途: login（既存）, verify（メール確認）, reset（パスワード再設定）
ALTER TABLE login_tokens
  ADD COLUMN purpose ENUM('login','verify','reset') NOT NULL DEFAULT 'login' AFTER user_id;

-- 2. login_tokens に verified_at 列を追加
-- 用途: パスワード再設定時のステップ管理（コード検証成功時に設定）
ALTER TABLE login_tokens
  ADD COLUMN verified_at TIMESTAMP NULL AFTER used;

-- 3. login_attempts.identifier_type に token を追加
-- 用途: トークン単位でのレート制限（コード検証のブルートフォース防止）
ALTER TABLE login_attempts
  MODIFY COLUMN identifier_type ENUM('ip','email','token') NOT NULL;

-- =====================================================
-- 確認用クエリ
-- =====================================================
-- DESCRIBE login_tokens;
-- DESCRIBE login_attempts;
