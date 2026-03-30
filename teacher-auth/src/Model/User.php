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
    public static function createWithRole(string $email, string $password, string $role = 'part_time_teacher', ?string $name = null): ?self
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

    /**
     * ユーザーのスコープ（担当範囲）を全て取得
     * 結果例: ['campus' => ['四条烏丸'], 'department' => ['教務']]
     */
    public function getScopes(): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT st.name as type_name, s.display_name as scope_name
            FROM user_scopes us
            JOIN scopes s ON us.scope_id = s.id
            JOIN scope_types st ON s.scope_type_id = st.id
            WHERE us.user_id = ?
        ');
        $stmt->execute([$this->id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scopes = [];
        foreach ($rows as $row) {
            $type = $row['type_name'];
            if (!isset($scopes[$type])) {
                $scopes[$type] = [];
            }
            $scopes[$type][] = $row['scope_name'];
        }

        return $scopes;
    }

    /**
     * 特定タイプのスコープを取得
     * 例: getScopesByType('campus') → ['四条烏丸', '京都駅前']
     */
    public function getScopesByType(string $typeName): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT s.display_name
            FROM user_scopes us
            JOIN scopes s ON us.scope_id = s.id
            JOIN scope_types st ON s.scope_type_id = st.id
            WHERE us.user_id = ? AND st.name = ?
        ');
        $stmt->execute([$this->id, $typeName]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * スコープを割り当て
     * 例: assignScope('campus', 'shijo_karasuma')
     */
    public function assignScope(string $typeName, string $scopeName): bool
    {
        $db = Database::getConnection();

        // スコープIDを取得
        $stmt = $db->prepare('
            SELECT s.id
            FROM scopes s
            JOIN scope_types st ON s.scope_type_id = st.id
            WHERE st.name = ? AND s.name = ?
        ');
        $stmt->execute([$typeName, $scopeName]);
        $scope = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$scope) {
            return false;
        }

        try {
            $stmt = $db->prepare('
                INSERT INTO user_scopes (user_id, scope_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE user_id = user_id
            ');
            $stmt->execute([$this->id, $scope['id']]);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * 特定のスコープを持っているか確認
     * 例: hasScope('campus', '四条烏丸') → true/false
     */
    public function hasScope(string $typeName, string $scopeDisplayName): bool
    {
        $scopes = $this->getScopesByType($typeName);
        return in_array($scopeDisplayName, $scopes);
    }

    /**
     * ユーザーの全権限を取得（ロールから導出）
     */
    public function getPermissions(): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT DISTINCT p.name
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ?
        ');
        $stmt->execute([$this->id]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
