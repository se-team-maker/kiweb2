<?php
/**
 * ユーザーモデル
 */

namespace App\Model;

use App\Config\Database;
use App\Auth\Password;
use PDO;

class User
{
    public string $id;
    public string $email;
    public ?string $name;
    public string $status;
    public ?string $emailVerifiedAt;
    public string $createdAt;
    public string $updatedAt;

    /**
     * IDでユーザーを取得
     */
    public static function findById(string $id): ?self
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT id, email, name, status, email_verified_at, created_at, updated_at
            FROM users
            WHERE id = ?
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return self::fromRow($row);
    }

    /**
     * メールアドレスでユーザーを取得
     */
    public static function findByEmail(string $email): ?self
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT id, email, name, status, email_verified_at, created_at, updated_at
            FROM users
            WHERE email = ?
        ');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return self::fromRow($row);
    }

    /**
     * 新規ユーザーを作成
     */
    public static function findByName(string $name): ?self
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT id, email, name, status, email_verified_at, created_at, updated_at
            FROM users
            WHERE name = ?
        ');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return self::fromRow($row);
    }

    public static function isNameTaken(string $name, ?string $excludeUserId = null): bool
    {
        $db = Database::getConnection();

        $sql = '
            SELECT COUNT(*)
            FROM users
            WHERE name = ?
        ';
        $params = [$name];

        if ($excludeUserId !== null && $excludeUserId !== '') {
            $sql .= ' AND id <> ?';
            $params[] = $excludeUserId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(string $email, ?string $password = null, ?string $name = null): ?self
    {
        $db = Database::getConnection();

        $id = Database::generateUUID();
        $passwordHash = $password ? Password::hash($password) : null;

        try {
            $stmt = $db->prepare('
                INSERT INTO users (id, email, name, password_hash)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$id, $email, $name, $passwordHash]);

            return self::findById($id);
        } catch (\PDOException $e) {
            // メールアドレス重複など
            return null;
        }
    }

    /**
     * 行データからインスタンスを生成
     */
    private static function fromRow(array $row): self
    {
        $user = new self();
        $user->id = $row['id'];
        $user->email = $row['email'];
        $user->name = $row['name'] ?? null;
        $user->status = $row['status'];
        $user->emailVerifiedAt = $row['email_verified_at'];
        $user->createdAt = $row['created_at'];
        $user->updatedAt = $row['updated_at'];

        return $user;
    }

    /**
     * ユーザーのロールを取得
     */
    public function getRoles(): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT r.name
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ');
        $stmt->execute([$this->id]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * ロールを割り当て
     */
    public function assignRole(string $roleName): bool
    {
        $db = Database::getConnection();

        // ロールIDを取得
        $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
        $stmt->execute([$roleName]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            return false;
        }

        try {
            $stmt = $db->prepare('
                INSERT INTO user_roles (user_id, role_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE user_id = user_id
            ');
            $stmt->execute([$this->id, $role['id']]);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * 権限を持っているか確認
     */
    public function hasPermission(string $permissionName): bool
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT COUNT(*) as cnt
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ? AND p.name = ?
        ');
        $stmt->execute([$this->id, $permissionName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['cnt'] > 0;
    }

    /**
     * アクティブかどうか
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * メール確認済みかどうか
     */
    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    /**
     * メールアドレスを確認済みにする
     */
    public function markEmailVerified(): bool
    {
        $db = Database::getConnection();

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?');
        $result = $stmt->execute([$now, $this->id]);

        if ($result) {
            $this->emailVerifiedAt = date('Y-m-d H:i:s');
        }

        return $result;
    }

    /**
     * ユーザーを作成してロールを割り当て
     */
    public static function createWithRole(string $email, string $password, string $role = 'student', ?string $name = null): ?self
    {
        $user = self::create($email, $password, $name);

        if (!$user) {
            return null;
        }

        $user->assignRole($role);

        return $user;
    }

    /**
     * IDでメール確認済みにする（静的メソッド）
     */
    public static function markEmailVerifiedById(string $userId): bool
    {
        $db = Database::getConnection();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?');
        return $stmt->execute([$now, $userId]);
    }
}
