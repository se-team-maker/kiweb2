# AI引き継ぎメモ（kiweb / teacher-auth）

最終更新: 2026-01-30  
対象: `https://system.kyotoijuku.com/kiweb` 配下の「講師向けログイン基盤（teacher-auth）」  

---

## 0. 他AIへの入力（コピペ用）
```
あなたは既存Webサイトの改修担当です。既存システムは壊さずに、新規ログイン基盤（teacher-auth）だけを進めます。

【最重要制約（変更禁止）】
- kiweb/room-booking/**
- kiweb/login/**

【新規追加（変更OK）】
- kiweb/teacher-auth/**（新ログイン一式、RBAC、管理画面）
- kiweb2.html（ポータル、管理者リンク自動表示）
- ルート .htaccess（kiweb2.html のガード用ルール）

【本番URL】
- ログイン: https://system.kyotoijuku.com/kiweb/teacher-auth/public/login.php
- ポータル: https://system.kyotoijuku.com/kiweb/kiweb2.html
- 管理画面: https://system.kyotoijuku.com/kiweb/teacher-auth/public/admin.php (manage_users権限が必要)

【要件】
- 登録 / ログイン / ログアウト / セッション管理
- パスワード発行・再設定（メールでコード送信）
- RBAC（Role-Based Access Control）：役職（roles）と特定機能権限（permissions）
- スコープ（Scopes）：「どの校舎（campus）」「どの部署（department）」の担当かを管理
- 管理画面：ユーザー検索、役職変更、担当範囲変更、権限マスタ管理、削除
- デザイン：Apple School Manager風（3カラムレイアウト）

【技術スタック】
- PHP 8.x / MySQL
- フロントエンド: Vanilla JS + CSS (Variables活用)
- 認証: セッションベース（Cookie path: /kiweb/） + WebAuthn（パスキー登録・削除）

【注意】
- teacher-auth は新規DBを使い、既存DB（会議室予約用）は触らない
- 文字コードは UTF-8 (BOMなし) に統一済
```

---

## 1. 目的（何を作っているか）
構成の肥大化を防ぎつつ、以下の3要素を統合した「講師向け基盤」を構築しています。
1. **認証基盤**: 誰がログインしているか（Email/Pass, Passkey）
2. **認可基盤(RBAC)**: その人は何ができるか（admin, full_time_teacher, part_time_teacher, part_time_staff）
3. **担当範囲(Scopes)**: その人はどこに所属しているか（四条烏丸校, 経理課...）

---

## 2. 実装済み機能（現状の成果物）

### 2-1. 認証システム
- **ログイン**: メールアドレス + パスワード認証（`login.php`）
- **パスキー**: ダッシュボードでパスキーの登録・削除が可能（`dashboard.php`）
- **パスワードリセット**: メールでコード送信 → 新パスワード設定

### 2-2. RBAC & スコープシステム
- **DB構造**: `roles`, `permissions`, `role_permissions` に加え、`scope_types`, `scopes`, `user_scopes` を実装。
- **標準ロール**:
  - `admin`（システム管理者）
  - `full_time_teacher`（専任）
  - `part_time_teacher`（非常勤・講師）
  - `part_time_staff`（非常勤・講師以外）
- **Userモデル**: `User.php` に以下の判定ロジックを統合。
  - `$user->hasPermission('manage_users')`: 権限確認
  - `$user->hasScope('campus', '四条烏丸校')`: 担当範囲確認
- **API連携**: `api/me.php` がユーザー名・ロール・権限・スコープの全てを返す。

### 2-3. 管理画面（Apple School Manager風）
- **URL**: `teacher-auth/public/admin.php`
- **デザイン**: Apple School Manager風の3カラムレイアウト
  - 左: サイドバーナビゲーション
  - 中: リストパネル（ユーザー/役職/スコープ一覧）
  - 右: 詳細パネル
- **主要機能**:
  - **ユーザー管理**: 検索、作成、編集、ステータス変更、役職・スコープ割り当て、削除
  - **役職・権限管理**: 役職の作成・編集、権限の紐付け
  - **校舎・部署管理**: スコープの追加・編集・削除
- **API**: `api/admin/` 配下に管理用エンドポイント群を実装
- **UI補足**:
  - 役職は**単一選択**（複数チェック不可）
  - モーダルの「キャンセル」ボタンは通常ボタン表示に調整済

### 2-4. サインアップ（アカウント作成）
- **role固定**: `signup.php?role=teacher|student` を受け取り、作成時にロールを付与
  - `login.php` / `index.php` / `portal-guard.php` が `role` を `signup.php` に引き継ぐ
- **画面はシンプル**: 校舎/部署の選択UIは**無し**（パラメータ付与のみ）

### 2-5. ポータル連携（kiweb2.html）
- `api/me.php` の `scopes.department` を読み取り、勤務記録フォーム (`work-record.html`) の `department` を自動選択
- 初期表示（ハッシュ未指定）を役職で分岐
  - `part_time_teacher`（非常勤・講師）: `#lessons`（授業予定・実施申告）
  - `part_time_staff`（非常勤・講師以外）: `#work`（勤務記録）

### 2-6. スコープマスターデータ
**校舎（campus）**: 2校
- 四条烏丸校
- 円町校

**部署（department）**: 18部署
- 高卒本部、現役本部
- 経営企画部、業務推進部、広報営業課、人事採用課、総合受付課、総務課、経理課、事務集中課、運営管理室
- 講師部執行室
- 英語科、数学科、化学科、生物科、物理科、国語・社会科

※ データ投入SQL: `database/update_scopes_data.sql`

---

## 3. 重要：導入・設定手順

作業を再開する際は、必ず以下のSQLを順に実行してください。

1. **スコープ基盤**: `database/migration_add_scopes.sql`
2. **管理者権限**: `database/migration_add_admin_permissions.sql`
3. **非常勤属性分割**: `database/migration_add_part_time_staff_role.sql`
4. **スコープデータ**: `database/update_scopes_data.sql`（校舎・部署の実データ）

### 最初の管理者の作り方
1. `signup.php` でユーザーを作成
2. phpMyAdminで `roles` テーブルの `admin` の `id` を確認
3. `user_roles` テーブルに `(user_id, role_id)` をインサート
4. 以降は管理画面から他ユーザーを編集可能

---

## 4. 文字コードの重要事項

> [!IMPORTANT]
> **すべてのファイルは UTF-8 (BOMなし) で保存してください。**

### エディタ設定の確認
- エンコーディング: **UTF-8**
- BOM: **なし** (UTF-8 without BOM)
- 改行コード: **LF** または **CRLF**

### 文字化けを防ぐために
- JSONレスポンスには `header('Content-Type: application/json; charset=utf-8')` を明記
- `json_encode()` には `JSON_UNESCAPED_UNICODE` フラグを使用

---

## 5. 削除済みファイル

以下のファイルは不要となり削除されました：
- `public/choose-login.php` - ログイン方法選択画面（削除）
- `api/email-login.php` - メール認証ログインAPI（削除）

ログインはパスワード認証のみに簡素化されました。

---

## 6. 次にやること（TODO）
1) **スコープデータ投入**: `database/update_scopes_data.sql` を本番DBで実行
2) **権限の具体化**: 具体的なPermissionを作成し、Roleに紐付け
3) **データアクセス制限**: 各APIで `User::hasScope()` を使い、担当範囲外のデータを制限
4) **メール送信確認**: パスワード再設定メールが本番で正常送信されるか確認

---

## 7. 主要ファイル構成

| ディレクトリ/ファイル | 役割 |
|-------------------|------|
| `public/login.php` | ログインページ（パスワード認証） |
| `public/dashboard.php` | ユーザーダッシュボード（パスキー管理） |
| `public/admin.php` | 管理者画面 (3カラムUI) |
| `public/assets/css/admin.css` | 管理画面スタイル（Apple風） |
| `public/assets/js/admin.js` | 管理者画面の全ロジック |
| `public/assets/js/login.js` | ログインページJS |
| `kiweb2.html` | ポータル本体（iframe切替・ログイン連携・勤務記録の所属自動選択） |
| `api/admin/` | 管理用API群 |
| `api/me.php` | ログインユーザー情報取得 |
| `src/Model/User.php` | RBAC/スコープのビジネスロジック |
| `database/update_scopes_data.sql` | 校舎・部署マスターデータ |
