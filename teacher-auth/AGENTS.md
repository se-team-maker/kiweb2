# Kyoto Ijuku Login System - AGENTS.md

このドキュメントは、AIコーディングエージェント（Codexなど）がこのプロジェクトを理解し、効率的に作業するためのガイドです。

## プロジェクト概要

京都医塾向けの認証システム。パスワードレス認証（WebAuthn）とメールコード認証を主軸としたセキュアなログイン基盤。

### 技術スタック
- **バックエンド**: PHP 7.4+
- **フロントエンド（ポータル）**: React + TypeScript + Vite + TailwindCSS
- **データベース**: MySQL 5.7+ (InnoDB, utf8mb4)
- **認証方式**:
  - WebAuthn（生体認証・セキュリティキー）
  - メールコード認証（6桁コード）
  - パスワード認証（任意設定）
- **ホスティング**: お名前.com レンタルサーバー

---

## サーバー構成

```
system-dev.kyotoijuku.com/
├── .htaccess
├── error/
├── login/                  ← ログインシステム本体
│   ├── .env
│   ├── api/
│   ├── public/
│   │   └── portal/         ← Reactポータル（ビルド後）
│   ├── src/
│   ├── database/
│   └── vendor/
├── masago/
└── meet-Schedule/
```

---

## ディレクトリ構成（login/）

```
login/
├── .env                    # 環境設定（DB接続、メール設定など）
├── .htaccess               # URL書き換え・セキュリティ設定
├── send_test_email.php     # メール送信テスト用スクリプト
├── api/                    # REST API エンドポイント
│   ├── register.php        # サインアップ
│   ├── login.php           # パスワードログイン
│   ├── email-login.php     # メールコード認証リクエスト
│   ├── verify-code.php     # コード検証
│   ├── verify-email.php    # メール確認完了
│   ├── resend-verify.php   # 確認コード再送
│   ├── password-reset-*.php # パスワードリセットフロー
│   ├── logout.php          # ログアウト
│   ├── me.php              # 現在のユーザー情報取得
│   └── webauthn/           # WebAuthn関連
│       ├── register-options.php
│       ├── register-verify.php
│       ├── login-options.php
│       ├── login-verify.php
│       └── delete.php
├── public/                 # フロントエンド（HTML/CSS/JS）
│   ├── bootstrap.php       # 共通初期化（autoload, .env読込, 関数定義）
│   ├── login.php           # ログイン画面
│   ├── signup.php          # サインアップ画面
│   ├── verify-email.php    # メール確認画面
│   ├── verify-code.php     # コード入力画面
│   ├── forgot-password.php # パスワード忘れ画面
│   ├── reset-password.php  # パスワード再設定画面
│   ├── dashboard.php       # ログイン後ダッシュボード
│   ├── choose-login.php    # ログイン方式選択（別の方法を試す）
│   ├── portal/             # Reactポータル（ビルド成果物）
│   └── assets/             # 静的ファイル (CSS/JS)
├── src/                    # ビジネスロジック（PSR-4 autoload）
│   ├── Auth/
│   │   ├── EmailAuth.php   # メール認証（トークン生成・検証・送信）
│   │   ├── Password.php    # パスワードハッシュ・検証・強度チェック
│   │   └── Session.php     # セッション管理
│   ├── Config/
│   │   └── Database.php    # PDO接続・UUID生成
│   ├── Model/
│   │   └── User.php        # ユーザーモデル（CRUD、ロール管理）
│   └── Security/
│       ├── RateLimiter.php # レート制限
│       └── AuditLog.php    # 監査ログ
├── database/
│   ├── schema.sql          # 初期スキーマ
│   └── migration_*.sql     # マイグレーション
└── vendor/                 # Composer依存（phpdotenv, webauthn, phpmailer）
```

---

## Reactポータル（kyoto-ijyuku-portal/）

ログイン後のユーザーダッシュボード。Vite + React + TypeScriptで構築。

```
kyoto-ijyuku-portal/
├── index.html
├── App.tsx
├── types.ts                # 型定義（NavigationItem enum含む）
├── hooks/
│   └── useAuth.tsx         # 認証フック（/api/me.php呼び出し）
├── components/
│   └── Layout.tsx          # 共通レイアウト（ナビ、フッター）
└── pages/
    ├── DashboardPage.tsx   # ダッシュボード
    ├── SettingsPage.tsx    # アカウント設定
    ├── ChangePasswordPage.tsx
    └── LoginPage.tsx
```

### ポータルのビルド・デプロイ
```bash
cd kyoto-ijyuku-portal
npm install
npm run build
# dist/ の内容を login/public/portal/ にコピー
```

---

## データベース構造

| テーブル名 | 用途 |
|-----------|------|
| `users` | ユーザー情報（UUID主キー、メール確認状態含む） |
| `roles` | ロール定義（student, teacher, admin） |
| `user_roles` | ユーザー・ロール紐付け |
| `permissions` | 権限定義 |
| `role_permissions` | ロール・権限紐付け |
| `webauthn_credentials` | WebAuthn公開鍵 |
| `webauthn_challenges` | 一時チャレンジ保存 |
| `login_tokens` | メールコード認証用トークン（purpose: login/verify/reset） |
| `login_attempts` | レート制限用 |
| `audit_logs` | 監査ログ |

---

## 環境変数（.env）

```env
# データベース
DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET

# セッション
SESSION_NAME, SESSION_LIFETIME, SESSION_ABSOLUTE_LIFETIME

# セキュリティ
PEPPER                     # パスワードハッシュ用ペッパー

# WebAuthn
WEBAUTHN_RP_NAME           # "京都医塾"
WEBAUTHN_RP_ID             # "system-dev.kyotoijuku.com"
WEBAUTHN_ORIGIN            # "https://system-dev.kyotoijuku.com"

# メール
MAIL_FROM                  # 送信元アドレス（例: system-dev@system-dev.kyotoijuku.com）
MAIL_FROM_NAME             # 送信者名（例: 京都医塾）

# 動作モード
APP_ENV                    # "development" or "production"
                           # development時はメール送信をスキップしログ出力のみ
```

---

## 重要な実装パターン

### 1. API レスポンス
すべてのAPIはJSON形式で応答。`jsonResponse()` ヘルパー関数を使用。
```php
jsonResponse(['success' => true, 'data' => $data]);
jsonResponse(['success' => false, 'error' => 'エラーメッセージ', 'error_code' => 'CODE']);
```

### 2. CSRF保護
すべてのPOSTリクエストで `validateCsrf()` を呼び出し。
フォームには `<?= csrfField() ?>` でトークンを埋め込む。

### 3. メール送信
`EmailAuth` クラスの静的メソッドを使用。APP_ENV=productionで実送信。
- `sendVerifyEmail()` - メール確認
- `sendLoginEmail()` - ログインコード
- `sendResetEmail()` - パスワードリセット

### 4. セッション管理
`Session::start()`, `Session::login($userId)`, `Session::logout()`, `Session::isLoggedIn()`

### 5. ポータルからの認証確認
Reactポータルは `useAuth` フックで `/api/me.php` を呼び出し、セッションを確認。
未認証の場合は `/login.php` へリダイレクト。

---

## UI/UX実装メモ（ログイン系）

- **「別の方法を試す」**は `public/choose-login.php` に集約。
  - パスキー: `/login.php?mode=passkey`
  - メールコード: `/login.php?mode=email`
- **ページ遷移エフェクト**は `public/assets/js/progress.js` で統一。
  - ナビゲーション時のみ表示（リンク/JS遷移）。
  - 遅延は 500ms に設定。
  - 上部プログレスバーとスクリーン（`.kPY6ve`）を併用。
- **カラー指定**
  - 主要ボタン: `#0F3568`
  - 上部ロードバー: `#AF1E2B`
  - 文字リンクも `#0F3568`
- **入力制限（半角英数記号のみ）**
  - フロント: 入力時に非ASCII文字を除去（エラーメッセージは出さない）
  - バックエンド: `public/bootstrap.php` の `isHalfWidthAscii()` で検証

---

## 開発時の注意事項

> [!IMPORTANT]
> **すべてのファイルは UTF-8 (BOMなし) で保存してください。**

1. **文字エンコーディング（最重要）**:
   - **すべての `.php`, `.js`, `.css`, `.html` ファイル**は **UTF-8 (BOMなし)** で保存
   - エディタ設定を確認:
     - エンコーディング: **UTF-8**
     - BOM: **なし** (UTF-8 without BOM)
     - 改行コード: **LF** (`\n`) または **CRLF** (`\r\n`)
   - 2026年1月30日の修正により、以前Shift_JISだったファイルもすべてUTF-8に統一済み
   - 特に以下のファイルは日本語エラーメッセージを含むため注意:
     - `public/bootstrap.php` - JSONレスポンスに `charset=utf-8` を明示
     - `src/Auth/Session.php` - セッション管理クラス
     - `api/login.php` - ログインAPI
     - `api/logout.php` - ログアウトAPI
   - JSONレスポンスには必ず `header('Content-Type: application/json; charset=utf-8')` を明記
   - `json_encode()` には `JSON_UNESCAPED_UNICODE` フラグを使用

2. **APP_ENV**: 本番環境では必ず `production` に設定（メール送信有効化）
3. **パス**: サーバー上では `/login/public/` がフロントエンドのベースパス
4. **リダイレクト**: 相対パスではなく `/login/...` 形式を使用
5. **ポータル更新時**: `npm run build` 後、`dist/` を `login/public/portal/` にアップロード
6. **UI言語**: ポータルは日本語化済み（2026-01-15更新）
7. **ロゴパス**: サーバー上では `/login/public/京都医塾logo.png` を使用

---

## UIデザイン参照

- **identity-verification/**: 確認コード入力画面のデザインモック（React + TailwindCSS）
  - `verify-email.php` のデザインはこのモックを参考に実装
  - コンポーネント: `VerificationCard.tsx`, `VerificationInput.tsx`
  - ※モックのみ、API連携なし
