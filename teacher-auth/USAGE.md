# teacher-auth / kiweb2 利用ガイド（利用者向け・運用者向け）

最終更新: 2026-01-31
対象: `https://system.kyotoijuku.com/kiweb` 配下（teacher-auth + kiweb2）

この資料は「どのURLを開くか」「配布用URLの作り方（パラメータ）」「管理画面で何を設定するか」をまとめた簡易マニュアルです。

対象読者
- 利用者（講師/生徒）：ログイン、ポータルの使い方、パスワード再設定
- 運用担当（管理者）：配布URLの作成、役職/担当範囲の付与、よくある問合せ対応

---

## Slack送信用（このブロックだけコピペ）

【kiweb2 / teacher-auth 共有（使い方・運用）】

■ URL（本番）
ログイン: https://system.kyotoijuku.com/kiweb/teacher-auth/public/login.php
アカウント作成: https://system.kyotoijuku.com/kiweb/teacher-auth/public/signup.php
ポータル: https://system.kyotoijuku.com/kiweb/kiweb2.html
管理画面: https://system.kyotoijuku.com/kiweb/teacher-auth/public/admin.php（manage_users 権限が必要）

■ 配布URLテンプレ（アカウント作成用）
講師: https://system.kyotoijuku.com/kiweb/teacher-auth/public/login.php?name=【氏名】&role=teacher
生徒: https://system.kyotoijuku.com/kiweb/teacher-auth/public/login.php?name=【氏名】&role=student
※ role は teacher / student のみ有効。それ以外は student 扱い
※ 氏名は「山田太郎」のように空白なし推奨（フォーム連携の都合）

■ 利用者（講師/生徒）
1) 配布URLを開く → メール/パスワード登録
2) メールの確認コードで認証
3) ログイン → ポータルから各フォームへ（授業申告/勤務記録/欠勤・振替）
4) パスワード再設定はログイン画面の「パスワードを忘れた場合」から

■ 運用担当（管理者）
1) 管理画面でユーザーを検索
2) 役職（Role）を付与（※単一選択）
   - 専任: `full_time_teacher`
   - 非常勤（講師）: `part_time_teacher`
   - 非常勤（講師以外）: `part_time_staff`
3) 部署スコープ（department）を付与
   → 勤務記録フォームの「所属」が自動で選択されます
4) 非常勤（講師以外）は、ポータル初期表示が「勤務記録」になります

---

## URL一覧（本番）

| 用途 | URL | 備考 |
|---|---|---|
| ログイン | `https://system.kyotoijuku.com/kiweb/teacher-auth/public/login.php` | |
| アカウント作成 | `https://system.kyotoijuku.com/kiweb/teacher-auth/public/signup.php` | |
| ポータル | `https://system.kyotoijuku.com/kiweb/kiweb2.html` | 授業申告/勤務記録/欠勤届をここから開きます |
| 管理画面 | `https://system.kyotoijuku.com/kiweb/teacher-auth/public/admin.php` | `manage_users` 権限が必要 |

---

## 1. パラメータ運用ルール（配布URLの作り方）

### 1-1. アカウント作成（配布用）
登録画面は**シンプル運用**です（校舎/部署を選ぶUIはありません）。
役職などの指定は **URLパラメータで渡す**ルールにしています。

| パラメータ | 必須 | 内容 | 例 |
|---|---:|---|---|
| `name` | 任意 | 氏名（画面表示/登録名） | `name=山田太郎` |
| `role` | 任意 | 役職（`teacher` / `student`） | `role=teacher` |

配布例（講師として作成させたい場合）
- `signup.php?name=山田太郎&role=teacher`
- `login.php?name=山田太郎&role=teacher`（アクセスすると作成画面へ誘導されます）

運用メモ
- `role` は `teacher` / `student` のみ有効です。それ以外は `student` 扱いになります。
- 氏名にスペースや記号が含まれる場合はURLエンコードしてください（通常はブラウザ/共有ツール側で自動処理されます）。
- `name` を付けない場合、登録画面で本人が氏名を入力します。
- フォーム連携の都合上、氏名は「山田太郎」のように **空白なし** を推奨します。

配布テンプレ（コピペ用）
- 講師: `login.php?name=【氏名】&role=teacher`
- 生徒: `login.php?name=【氏名】&role=student`

---

### 1-2. ポータル → 各フォーム（自動付与）
ポータル（kiweb2.html）からフォームを開く際、氏名などの値を **URLパラメータで自動付与**しています。

勤務記録フォーム（`work-record.html`）
- `teacher`：ログイン名（氏名）
- `department`：ユーザーに割り当てられた「部署スコープ」（管理画面で付与）

例
- `work-record.html?teacher=山田太郎&department=経理課`

注意
- `department` は、部署スコープが複数ある場合「先頭の1つ」を使います。
- ポータル初期表示（ハッシュ未指定時）は役職で分岐します。
  - `part_time_teacher`（非常勤・講師）: `#lessons`（授業予定・実施申告）
  - `part_time_staff`（非常勤・講師以外）: `#work`（勤務記録）

---

## 2. 利用者向け（講師/生徒）

### 2-1. 初回（アカウント作成）
1. 配布されたURLを開く（`signup.php?...`）
2. メールアドレス/パスワードを登録
3. メールに届く確認コードで認証
4. ログインしてポータルへ

### 2-2. ふだんの使い方
1. ポータル（kiweb2.html）を開く
2. 左メニューから「授業予定・実施申告」「勤務記録フォーム」「欠勤・振替申請」を選ぶ
3. 勤務記録フォームは、所属（部署）が自動で選択されます（運用者が部署スコープを付与している場合）

### 2-3. パスワードを忘れた場合
1. ログイン画面の「パスワードを忘れた場合」から申請
2. メールに届くコードを入力して再設定

---

## 3. 運用者向け（管理画面）

### 3-1. 主な機能
- ユーザーの検索/作成/編集/削除
- 役職（Role）の付与（※単一選択）
- 担当範囲（Scope：校舎/部署）の付与
- 役職/権限/スコープのマスタ管理
- 役職の標準運用:
  - `admin`（システム管理者）
  - `full_time_teacher`（専任）
  - `part_time_teacher`（非常勤・講師）
  - `part_time_staff`（非常勤・講師以外）

### 3-2. 最低限の運用フロー
1. 利用者アカウントを作成してもらう（配布URL）
2. 管理画面で該当ユーザーを検索
3. 役職を付与（専任 / 非常勤（講師） / 非常勤（講師以外））
4. 部署スコープを付与（勤務記録フォームの所属自動選択に必要）

### 3-3. 初回だけ（管理者を1人作る）
管理画面に入るには `manage_users` 権限が必要です。
最初の1人だけは、以下の手順で「管理者ロール」を付与してから運用してください。

1. いったん `signup.php` でユーザーを作成
2. DBの `roles` テーブルで `admin` の `id` を確認
3. DBの `user_roles` に `(user_id, role_id)` を追加
4. 以降は管理画面から他ユーザーを編集できます

---

## 4. よくあるトラブル

### 4-1. 「勤務記録フォーム」で所属が自動で入らない
管理画面で、そのユーザーに **部署スコープ（department）** が割り当てられているか確認してください。

### 4-2. ポータルで名前が変になる/空になる
ポータルはログインユーザー情報（`api/me.php`）の値を元に表示します。
ユーザーの `name` が未設定の場合は、メールアドレス表示になることがあります。

---

## 5. 技術メモ（開発/保守）
- 文字コード: UTF-8（BOMなし）
- 認証: セッションベース（Cookie path: `/kiweb/`）
- `api/me.php` がユーザー情報（roles/permissions/scopes）を返します
