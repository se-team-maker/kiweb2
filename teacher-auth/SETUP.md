# 講師ログイン（teacher-auth）セットアップ（お名前ドットコム超詳細版）

## 0. 目的と前提（ここは必ず守る）
- 対象フォルダ: `teacher-auth/**` のみ
- **触らないフォルダ**: `room-booking/**` と `login/**`
- 新規DBを作成し、既存DBは一切触らない

---

## 1. 事前に用意するもの（全部そろってから始める）
1. お名前ドットコムのコントロールパネルログイン情報  
   - サーバーID / パスワード
2. 公開ドメイン（例: `system.kyotoijuku.com`）
3. FTP/SFTP 接続情報  
   - ホスト名 / ユーザー名 / パスワード / ポート  
   - ※わからない場合は「FTPアカウント作成」で新規作成
4. このPC内のフォルダ  
   - `C:\Users\tarog\Downloads\kiweb\kiweb\teacher-auth`
5. DB用の情報（これから作る）  
   - DB名 / DBユーザー名 / DBパスワード / DBホスト名

---

## 2. どのファイルを「更新」するか（重要）

### 2-1. **必ず編集するファイル**
- `teacher-auth/.env`  
  ここに **新しいDB情報** と **セッション設定** を入れる

### 2-2. **そのままアップロードするファイル（編集不要）**
- `teacher-auth/.htaccess`（.env を守るため必須）
- `teacher-auth/database/schema.sql`（DB作成用）
- `teacher-auth/public/**`（ログイン画面）
- `teacher-auth/api/**`（API）
- `teacher-auth/src/**`（内部処理）
- `teacher-auth/vendor/**`（ライブラリ一式）

---

## 3. お名前ドットコム側でやること（超詳細）

### 3-1. PHPバージョンを確認する
1. お名前ドットコムの「コントロールパネル」にログイン  
2. 「PHP設定」や「PHPバージョン切替」を開く  
3. **PHP 7.4 以上**になっているか確認  
   - もし古い場合は 7.4 / 8.x に変更

### 3-2. 新しいDBを作成する
1. コントロールパネルの「データベース」→「MySQL」へ移動  
2. 「新規作成」ボタンを押す  
3. 以下を入力  
   - **DB名**: 例 `teacher_login`  
   - **文字コード**: `utf8mb4`  
4. 作成後、**DBホスト名** を必ずメモ  
   - 例: `mysqlXXXX.onamae.ne.jp`

### 3-3. DBユーザーを作成してDBに紐づける
1. 「DBユーザー追加」または「ユーザー作成」を選ぶ  
2. **ユーザー名・パスワード** を設定  
3. 作成したユーザーを **先ほどのDBに紐づける**  
   - 権限は **すべて許可（ALL）** を選択
4. 以下4点を必ずメモ  
   - `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS`

### 3-4. phpMyAdminでテーブルを作成（schema.sqlを入れる）
1. コントロールパネルの「phpMyAdmin」を開く  
2. 左側から **作成した新DB** を選択  
3. 画面上部の「インポート」タブを開く  
4. ファイル選択で **このPCの**  
   `teacher-auth/database/schema.sql` を選ぶ  
5. 文字コードは `utf8mb4` を選ぶ（または自動）  
6. 「実行」をクリック  
7. エラーがなければ成功

---

## 4. ファイルをアップロードする（どこに？何を？）
**アップロード先は「/kiweb/」の中**  
公開URLが `https://system.kyotoijuku.com/kiweb` なので、  
サーバー上の `kiweb` フォルダが公開対象です。

### 4-1. ブラウザの「ファイルマネージャ」からアップロードする場合
1. コントロールパネル → 「ファイルマネージャ」へ  
2. `public_html`（または `www`）を開く  
3. その中に `kiweb` フォルダがあるか確認  
4. `kiweb` フォルダを開く  
5. **このPCの** `teacher-auth` フォルダをまるごとアップロード  
   - 既存の `room-booking` や `login` は触らない  
6. アップロード後の構成が下記になることを確認  
```
public_html/
  kiweb/
    teacher-auth/
      .env
      .htaccess
      public/
      api/
      src/
      vendor/
      database/
```

### 4-2. FTPソフト（例: FileZilla）からアップロードする場合
1. FileZilla を起動  
2. ホスト / ユーザー / パスワード / ポートを入力し「接続」  
3. 右側（サーバー側）で `public_html/kiweb/` を開く  
4. 左側（PC側）で `C:\Users\tarog\Downloads\kiweb\kiweb\teacher-auth` を開く  
5. `teacher-auth` フォルダを右側の `kiweb` の中へドラッグ  
6. アップロード完了まで待つ

---

## 5. `.env` を編集する（超重要）
**方法は2つ**  
1) ローカルで `.env` を編集してからアップロード  
2) サーバー上の `.env` を直接編集

### 5-1. 編集内容（必須）
`teacher-auth/.env` を開いて以下を実際の値に書き換える
```
DB_HOST=mysqlXXXX.onamae.ne.jp
DB_NAME=teacher_login
DB_USER=teacher_login_user
DB_PASS=あなたが決めたパスワード

SESSION_NAME=teacher_auth_session
SESSION_COOKIE_PATH=/kiweb/teacher-auth/

PEPPER=長くてランダムな文字列（30文字以上推奨）

APP_ENV=production
APP_DEBUG=false
```

### 5-2. メールを使う場合（パスワード再設定に必要）
```
MAIL_HOST=SMTPサーバー
MAIL_PORT=587
MAIL_USER=送信用メールアドレス
MAIL_PASS=メールのパスワード
MAIL_FROM=送信用メールアドレス
MAIL_FROM_NAME=送信者名

※メールを使わない場合は空欄でも動作するが、  
パスワード再設定メールは送れない

### 5-3. Passkey（WebAuthn）を使う場合だけ変更

WEBAUTHN_RP_ID=system.kyotoijuku.com
WEBAUTHN_ORIGIN=https://system.kyotoijuku.com

### 5-4. Googleスプレッドシートへミラーリングする場合
以下を設定すると、ユーザー作成時にWebhookへ JSON をPOSTします。  
未設定なら何もしません。

```
USER_SHEET_WEBHOOK_URL=https://script.google.com/macros/s/xxxxx/exec
USER_SHEET_WEBHOOK_SECRET=
USER_SHEET_TIMEOUT=5
USER_SHEET_BACKFILL_TOKEN=generate-a-random-token
```


---

## 6. `.env` が外部から見えないか確認する（必須）
1. ブラウザで  
   `https://system.kyotoijuku.com/kiweb/teacher-auth/.env`  
   にアクセス  
2. **403** か **404** になればOK  
3. 中身が表示されたら危険  
   - `.htaccess` が効いていない可能性  
   - すぐに対応が必要

---

## 7. 動作確認（最低限ここまでやる）
1. ログイン画面を開く  
   - `https://system.kyotoijuku.com/kiweb/teacher-auth/public/login.php`
2. `?name=山田太郎` を付けて開く  
   - `.../login.php?name=山田太郎`
3. 「アカウント作成」へ切り替える  
   - 名前が自動入力されているか確認
4. アカウント登録 → ログイン → ログアウトができるか確認

---

## 8. よくあるエラーと確認ポイント

### 8-1. 画面が真っ白 / 500エラー
- PHPバージョンが古い可能性  
- `.env` の記述ミス  
- ファイルのアップロード漏れ（`vendor/` が無いなど）

### 8-2. DB接続エラー
- `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` の入力ミス  
- DBユーザーがDBに紐づいていない  
- 権限が不足している

### 8-3. `/.env` が見えてしまう
- `.htaccess` がアップロードされていない  
- サーバー側で `.htaccess` が無効になっている  
  - コントロールパネルの「.htaccess有効化」を確認

---

## 9. アクセスURL（確認用）
- ログイン画面: `/kiweb/teacher-auth/public/login.php`
- パラメータ例: `/kiweb/teacher-auth/public/login.php?name=山田太郎`
  - 「アカウント作成」押下で `signup.php` に名前が引き継がれます
