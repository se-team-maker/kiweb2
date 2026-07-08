<?php
/**
 * メール認証クラス
 * 
 * purpose対応:
 * - login: 既存のメールコードログイン
 * - verify: サインアップ後のメール確認
 * - reset: パスワード再設定
 */

namespace App\Auth;

use App\Config\Database;
use PDO;

class EmailAuth
{
    // トークンの用途
    const PURPOSE_LOGIN = 'login';
    const PURPOSE_VERIFY = 'verify';
    const PURPOSE_RESET = 'reset';

    /**
     * トークンを生成（purpose対応）
     * 
     * @param string $email メールアドレス
     * @param string $purpose 用途（login/verify/reset）
     * @param bool $requireVerified trueの場合、verified状態のユーザーのみ対象
     * @return array|null トークン情報、ユーザー不存在時はnull
     */
    public static function createToken(string $email, string $purpose = self::PURPOSE_LOGIN, bool $requireVerified = true): ?array
    {
        $db = Database::getConnection();

        // ユーザー取得クエリ（purposeによって条件を変える）
        if ($purpose === self::PURPOSE_VERIFY) {
            // verify: 未確認ユーザーも対象
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND status = "active"');
        } elseif ($purpose === self::PURPOSE_RESET) {
            // reset: 全ユーザー対象（確認・未確認問わず）
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND status = "active"');
        } else {
            // login: 確認済みユーザーのみ
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND status = "active" AND email_verified_at IS NOT NULL');
        }

        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        // トークンと6桁コードを生成
        $tokenId = Database::generateUUID();
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $code = sprintf('%06d', random_int(0, 999999));

        $expiresAt = date('Y-m-d H:i:s', time() + (int) ($_ENV['LOGIN_TOKEN_EXPIRY'] ?? 600));

        // 同一ユーザー・同一purposeの古いトークンを削除
        $stmt = $db->prepare('DELETE FROM login_tokens WHERE user_id = ? AND purpose = ?');
        $stmt->execute([$user['id'], $purpose]);

        // 新しいトークンを保存（purpose列を含む）
        $stmt = $db->prepare('
            INSERT INTO login_tokens (id, user_id, purpose, token_hash, code, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$tokenId, $user['id'], $purpose, $tokenHash, $code, $expiresAt]);

        return [
            'token_id' => $tokenId,
            'token' => $token,
            'code' => $code,
            'user_id' => $user['id'],
            'email' => $email,
            'purpose' => $purpose
        ];
    }

    /**
     * 指定されたユーザーIDでトークンを生成（ユーザー検索をスキップ）
     * サインアップ直後など、ユーザーIDが既知の場合に使用
     */
    public static function createTokenForUser(string $userId, string $email, string $purpose): array
    {
        $db = Database::getConnection();

        // トークンと6桁コードを生成
        $tokenId = Database::generateUUID();
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $code = sprintf('%06d', random_int(0, 999999));

        $expiresAt = date('Y-m-d H:i:s', time() + (int) ($_ENV['LOGIN_TOKEN_EXPIRY'] ?? 600));

        // 同一ユーザー・同一purposeの古いトークンを削除
        $stmt = $db->prepare('DELETE FROM login_tokens WHERE user_id = ? AND purpose = ?');
        $stmt->execute([$userId, $purpose]);

        // 新しいトークンを保存
        $stmt = $db->prepare('
            INSERT INTO login_tokens (id, user_id, purpose, token_hash, code, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$tokenId, $userId, $purpose, $tokenHash, $code, $expiresAt]);

        return [
            'token_id' => $tokenId,
            'token' => $token,
            'code' => $code,
            'user_id' => $userId,
            'email' => $email,
            'purpose' => $purpose
        ];
    }

    /**
     * ログインリンクのメールを送信（既存互換）
     */
    public static function sendLoginEmail(string $email, string $tokenId, string $code): bool
    {
        $loginUrl = ($_ENV['WEBAUTHN_ORIGIN'] ?? 'http://localhost:8080') . '/verify-code.php?id=' . $tokenId;

        $subject = '【京都医塾】ログイン認証コード';
        $body = <<<EOT
京都医塾ログインシステムへのログインリクエストを受け付けました。

以下のリンクをクリックし、認証コードを入力してください：
{$loginUrl}

認証コード: {$code}

※このコードは10分間有効です。
※心当たりがない場合は、このメールを無視してください。

---
京都医塾
EOT;

        return self::sendMail($email, $subject, $body);
    }

    /**
     * メール確認用のメールを送信
     */
    public static function sendVerifyEmail(string $email, string $tokenId, string $code): bool
    {
        $subject = '【京都医塾】メールアドレス確認コード';
        $body = <<<EOT
京都医塾アカウントへのご登録ありがとうございます。

確認コード: {$code}

※このコードは10分間有効です。
※心当たりがない場合は、このメールを無視してください。

---
京都医塾
EOT;

        return self::sendMail($email, $subject, $body);
    }

    /**
     * パスワード再設定用のメールを送信
     */
    public static function sendResetEmail(string $email, string $tokenId, string $code): bool
    {
        $subject = '【京都医塾】パスワード再設定コード';
        $body = <<<EOT
京都医塾アカウントのパスワード再設定リクエストを受け付けました。

再設定コード: {$code}

※このコードは10分間有効です。
※心当たりがない場合は、このメールを無視し、パスワードを変更しないでください。

---
京都医塾
EOT;

        return self::sendMail($email, $subject, $body);
    }

    /**
     * メール送信（SMTP / mail関数）
     */
    private static function sendMail(string $to, string $subject, string $body): bool
    {
        $from = $_ENV['MAIL_FROM'] ?? '';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? '';

        // 開発環境では送信をスキップしてログに記録
        if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
            error_log("Email to: {$to}\nSubject: {$subject}\nBody:\n{$body}");
            return true;
        }

        // PHPのmail関数を使用（お名前.comサーバー）
        $headers = [
            'From' => "{$fromName} <{$from}>",
            'Reply-To' => $from,
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Mailer' => 'PHP/' . phpversion()
        ];

        return mail($to, $subject, $body, $headers, "-f" . $from);
    }

    /**
     * コードを検証（ログインはしない）
     * 
     * @param string $tokenId トークンID
     * @param string $code 6桁コード
     * @param string $purpose 用途
     * @return array|false トークン情報、失敗時false
     */
    public static function verifyCode(string $tokenId, string $code, string $purpose): array|false
    {
        $db = Database::getConnection();

        // トークン取得（purpose指定）
        // PHPのタイムゾーンを使用（MySQLのNOW()はタイムゾーンがずれる可能性がある）
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('
            SELECT lt.*, u.email, u.status as user_status, u.email_verified_at
            FROM login_tokens lt
            JOIN users u ON lt.user_id = u.id
            WHERE lt.id = ?
              AND lt.purpose = ?
              AND lt.used = FALSE
              AND lt.expires_at > ?
        ');
        $stmt->execute([$tokenId, $purpose, $now]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$token) {
            return false;
        }

        if ($token['user_status'] !== 'active') {
            return false;
        }

        // コードを検証
        if (!hash_equals($token['code'], $code)) {
            return false;
        }

        return [
            'token_id' => $token['id'],
            'user_id' => $token['user_id'],
            'email' => $token['email'],
            'email_verified_at' => $token['email_verified_at'],
            'purpose' => $token['purpose']
        ];
    }

    /**
     * トークンを検証済みとしてマーク（reset時のステップ管理用）
     */
    public static function markTokenVerified(string $tokenId): bool
    {
        $db = Database::getConnection();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE login_tokens SET verified_at = ? WHERE id = ? AND used = FALSE');
        return $stmt->execute([$now, $tokenId]);
    }

    /**
     * トークンを使用済みにする
     */
    public static function markTokenUsed(string $tokenId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE login_tokens SET used = TRUE WHERE id = ?');
        return $stmt->execute([$tokenId]);
    }

    /**
     * 検証済みトークンを取得（reset完了時に使用）
     */
    public static function getVerifiedToken(string $tokenId, string $purpose): array|false
    {
        $db = Database::getConnection();

        // PHPのタイムゾーンを使用（MySQLのNOW()はタイムゾーンがずれる可能性がある）
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('
            SELECT lt.*, u.email, u.email_verified_at
            FROM login_tokens lt
            JOIN users u ON lt.user_id = u.id
            WHERE lt.id = ?
              AND lt.purpose = ?
              AND lt.used = FALSE
              AND lt.verified_at IS NOT NULL
              AND lt.expires_at > ?
        ');
        $stmt->execute([$tokenId, $purpose, $now]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * トークン情報を取得（再送時にuser_id取得用）
     */
    public static function getToken(string $tokenId): array|false
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT lt.*, u.email
            FROM login_tokens lt
            JOIN users u ON lt.user_id = u.id
            WHERE lt.id = ?
        ');
        $stmt->execute([$tokenId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * トークンとコードを検証してログイン（既存互換）
     * 
     * @return array|false ユーザー情報または失敗時false
     */
    public static function verifyAndLogin(string $tokenId, string $code): array|false
    {
        $result = self::verifyCode($tokenId, $code, self::PURPOSE_LOGIN);

        if (!$result) {
            return false;
        }

        // メール未確認の場合は拒否
        if ($result['email_verified_at'] === null) {
            return false;
        }

        // トークンを使用済みにマーク
        self::markTokenUsed($tokenId);

        return [
            'id' => $result['user_id'],
            'email' => $result['email']
        ];
    }

    /**
     * 期限切れトークンを削除（疑似cron）
     */
    public static function cleanupExpiredTokens(): void
    {
        // 1%の確率で実行
        if (random_int(1, 100) !== 1) {
            return;
        }

        $db = Database::getConnection();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('DELETE FROM login_tokens WHERE expires_at < ?');
        $stmt->execute([$now]);
    }
}
