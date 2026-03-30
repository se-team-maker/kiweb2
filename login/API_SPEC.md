# 認証システム API 仕様書

**Base URL**: `https://system.kyotoijuku.com/kiweb/login/api/`

> [!NOTE]
> 開発環境: `https://system-dev.kyotoijuku.com/login/api/`

---

## 共通仕様

### リクエスト形式
- **POST**: `application/x-www-form-urlencoded` または `application/json`
- **GET**: クエリパラメータ

### 認証
- CSRFトークン必須（`csrf_token` パラメータ または `X-CSRF-TOKEN` ヘッダー）
- セッションベース認証

### レスポンス形式
```json
{
  "success": true,
  "message": "メッセージ",
  "redirect": "/リダイレクト先"
}
```

### エラーレスポンス
```json
{
  "success": false,
  "error": "エラーメッセージ",
  "error_code": "ERROR_CODE"
}
```

### 共通エラーコード

| コード | HTTPステータス | 説明 |
|--------|----------------|------|
| `MISSING_FIELDS` | 400 | 必須項目未入力 |
| `INVALID_EMAIL` | 400 | メールアドレス形式エラー |
| `RATE_LIMITED` | 429 | レート制限超過 |
| `UNAUTHORIZED` | 401 | 認証が必要 |

---

## エンドポイント一覧

| エンドポイント | メソッド | 説明 |
|----------------|----------|------|
| `/login.php` | POST | パスワードログイン |
| `/logout.php` | GET/POST | ログアウト |
| `/register.php` | POST | アカウント新規作成 |
| `/me.php` | GET | ログインユーザー情報取得 |
| `/verify-email.php` | POST | メールアドレス確認 |
| `/verify-code.php` | POST | メール認証コード検証 |
| `/email-login.php` | POST | メールリンクログイン送信 |
| `/resend-verify.php` | POST | 確認コード再送信 |
| `/password-reset-request.php` | POST | パスワードリセット要求 |
| `/password-reset-verify.php` | POST | リセットコード検証 |
| `/password-reset-complete.php` | POST | パスワードリセット完了 |

---

## 1. パスワードログイン

```
POST /api/login.php
```

### リクエストパラメータ

| パラメータ | 必須 | 型 | 説明 |
|------------|------|-----|------|
| `email` | ○ | string | メールアドレス（または内部ID） |
| `password` | ○ | string | パスワード |
| `csrf_token` | ○ | string | CSRFトークン |

> [!TIP]
> `email` に `@` が含まれない場合、自動的に `@internal` が付加されます。

### 成功レスポンス
```json
{
  "success": true,
  "redirect": "/kiweb/room-booking/room-booking.php"
}
```

### エラーレスポンス

#### 認証失敗
```json
{
  "success": false,
  "error": "メールアドレスまたはパスワードが正しくありません"
}
```

#### メール未確認
```json
{
  "success": false,
  "error": "メール確認が完了していません。確認コードを再送しました。",
  "error_code": "EMAIL_NOT_VERIFIED",
  "redirect": "/kiweb/login/public/verify-email.php?id=xxxxx"
}
```

---

## 2. ログアウト

```
GET /api/logout.php
POST /api/logout.php
```

### 成功レスポンス（POST時）
```json
{
  "success": true,
  "redirect": "/login.php"
}
```

### GETリクエスト時
`/login.php` へリダイレクト

---

## 3. アカウント新規作成

```
POST /api/register.php
```

### リクエストパラメータ

| パラメータ | 必須 | 型 | 説明 |
|------------|------|-----|------|
| `email` | ○ | string | メールアドレス |
| `password` | ○ | string | パスワード |
| `password_confirm` | ○ | string | パスワード（確認） |
| `name` | - | string | 表示名（任意） |
| `csrf_token` | ○ | string | CSRFトークン |

### パスワード要件
- 制約なし（空欄不可・確認用パスワード一致のみ）

### 成功レスポンス
```json
{
  "success": true,
  "message": "アカウントを作成しました。メールをご確認ください。",
  "redirect": "/verify-email.php?id=xxxxx"
}
```

### エラーコード

| コード | 説明 |
|--------|------|
| `MISSING_FIELDS` | 必須項目未入力 |
| `INVALID_EMAIL` | メールアドレス形式エラー |
| `PASSWORD_MISMATCH` | パスワード不一致 |
| `EMAIL_EXISTS` | メールアドレス登録済み |
| `CREATE_FAILED` | 作成失敗 |

---

## 4. ログインユーザー情報取得

```
GET /api/me.php
```

### 成功レスポンス
```json
{
  "success": true,
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "ユーザー名",
    "status": "active",
    "email_verified": true,
    "roles": ["student"]
  }
}
```

### エラーレスポンス

#### 未認証
```json
{
  "success": false,
  "error": "認証が必要です",
  "error_code": "UNAUTHORIZED"
}
```

#### ユーザー無効
```json
{
  "success": false,
  "error": "アカウントが無効です",
  "error_code": "ACCOUNT_INACTIVE"
}
```

---

## 5. メールアドレス確認

```
POST /api/verify-email.php
```

### リクエストパラメータ

| パラメータ | 必須 | 型 | 説明 |
|------------|------|-----|------|
| `token_id` | ○ | string | トークンID |
| `code` | ○ | string | 6桁の確認コード |
| `csrf_token` | ○ | string | CSRFトークン |

### 成功レスポンス
```json
{
  "success": true,
  "message": "メールアドレスの確認が完了しました",
  "redirect": "/kiweb/room-booking/room-booking.php"
}
```

### エラーコード

| コード | 説明 |
|--------|------|
| `MISSING_FIELDS` | 必須項目未入力 |
| `INVALID_CODE_FORMAT` | コード形式エラー |
| `INVALID_CODE` | コードが無効または期限切れ |
| `RATE_LIMITED` | 入力回数上限 |

---

## 6. パスワードリセット要求

```
POST /api/password-reset-request.php
```

### リクエストパラメータ

| パラメータ | 必須 | 型 | 説明 |
|------------|------|-----|------|
| `email` | ○ | string | メールアドレス |
| `csrf_token` | ○ | string | CSRFトークン |

### 成功レスポンス
```json
{
  "success": true,
  "message": "パスワード再設定のメールを送信しました",
  "redirect": "/reset-password.php?id=xxxxx"
}
```

> [!IMPORTANT]
> セキュリティのため、ユーザーが存在しない場合も同じレスポンスを返します（列挙攻撃防止）

---

## cURL サンプル

### ログイン
```bash
curl -X POST "https://system.kyotoijuku.com/kiweb/login/api/login.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=user@example.com&password=password123&csrf_token=TOKEN"
```

### ユーザー情報取得
```bash
curl "https://system.kyotoijuku.com/kiweb/login/api/me.php" \
  -b "PHPSESSID=your_session_id"
```

---

## セキュリティ機能

### レート制限
- IP単位・メールアドレス単位で制限
- ブロック時は `429` レスポンスと再試行可能時間を返却

### CSRF保護
- 全POSTリクエストにCSRFトークン必須
- `X-CSRF-TOKEN` ヘッダーまたは `csrf_token` パラメータ

### 監査ログ
- ログイン成功・失敗
- アカウント作成
- パスワードリセット
- メール確認
