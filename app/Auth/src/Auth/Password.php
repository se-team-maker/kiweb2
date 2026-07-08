<?php
/**
 * パスワード認証クラス
 */

namespace App\Auth;

use App\Config\Database;
use PDO;

class Password
{
    /**
     * パスワードをハッシュ化（Argon2id + ペッパー）
     */
    public static function hash(string $password): string
    {
        $pepper = $_ENV['PEPPER'] ?? '';
        $pepperedPassword = hash_hmac('sha256', $password, $pepper);

        return password_hash($pepperedPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,  // 64MB
            'time_cost' => 4,
            'threads' => 1
        ]);
    }

    /**
     * パスワードを検証
     */
    public static function verify(string $password, string $hash): bool
    {
        $pepper = $_ENV['PEPPER'] ?? '';
        $pepperedPassword = hash_hmac('sha256', $password, $pepper);

        return password_verify($pepperedPassword, $hash);
    }

    /**
     * パスワードでログイン
     * 
     * @return array|false ユーザー情報、エラー情報、または失敗時false
     *         成功時: ['id' => ..., 'email' => ..., 'status' => ...]
     *         未確認時: ['error' => 'email_not_verified', 'user_id' => ...]
     *         失敗時: false
     */
    public static function login(string $email, string $password): array|false
    {
        $db = Database::getConnection();

        // ユーザー取得（email_verified_atも取得）
        $stmt = $db->prepare('
            SELECT id, email, password_hash, status, email_verified_at
            FROM users
            WHERE email = ? AND status = "active"
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // 存在しないユーザーでもタイミング攻撃を防ぐ
            self::hash('dummy_password');
            return false;
        }

        if ($user['password_hash'] === null) {
            // パスワードが設定されていない
            return false;
        }

        if (!self::verify($password, $user['password_hash'])) {
            return false;
        }

        // メール未確認の場合はエラー情報を返す
        if ($user['email_verified_at'] === null) {
            return [
                'error' => 'email_not_verified',
                'user_id' => $user['id'],
                'email' => $user['email']
            ];
        }

        // 必要ならハッシュを再計算（将来のアルゴリズム変更対応）
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            self::updatePassword($user['id'], $password);
        }

        unset($user['password_hash']);
        unset($user['email_verified_at']);
        return $user;
    }

    /**
     * パスワードを更新
     */
    public static function updatePassword(string $userId, string $newPassword): bool
    {
        $db = Database::getConnection();
        $hash = self::hash($newPassword);

        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$hash, $userId]);
    }

    /**
     * パスワード強度をチェック
     * 
     * @return array エラーメッセージの配列（空なら問題なし）
     */
    public static function validateStrength(string $password): array
    {
        $errors = [];

        if (!preg_match('/^[\x21-\x7E]+$/', $password)) {
            $errors[] = 'パスワードは半角英数記号のみで入力してください';
        }

        if (strlen($password) < 8) {
            $errors[] = 'パスワードは8文字以上にしてください';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = '小文字を含めてください';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = '大文字を含めてください';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = '数字を含めてください';
        }

        return $errors;
    }
}
