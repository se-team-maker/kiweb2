# kiweb iframeアクセスログ リリース作業

作成日: 2026年6月22日

## 0. 現在の状況

アクセスログには、次の2種類がある。

1. ポータルやPHPページ自体を開いた記録
2. ポータル内でメニューを選び、iframeの表示先を切り替えた記録

通常のページアクセスログは本番サーバーにも存在する。一方、iframe遷移ログはテストサーバー側で追加された拡張機能である。

2026年6月20日に取得した本番サーバーのファイル一式には、次のiframeログ用ファイルが存在しない。

```text
teacher-auth/api/iframe-access-log.php
teacher-auth/database/migration_add_access_iframes_logs.sql
```

ただし、本番版の`kiweb2-fulltime.html`にはAPIを呼び出すコードが含まれている。そのため本番では、メニュー遷移時に送信だけが行われ、APIが404になる可能性がある。

ログ送信は画面側で失敗を無視するため、主要なポータル操作は継続する。DB書き込み失敗も`AccessLog::logIframeOpen()`内で捕捉されるため、通常はログだけが保存されず、サーバーのエラーログへ記録される。

## 1. この機能で記録する内容

iframeを開いたとき、次の情報を記録する。

- ログインユーザーID
- 種別 `iframe_open`
- ページキー
- メニュー名
- 表示先パス
- 記録日時
- IPアドレス
- User-Agent
- Referer

URLに付いている`teacher`、`name`、`department`等は、ログへ保存する前に削除する。

## 2. システムの流れ

```text
社員・管理者ポータルでメニューを選択
  ↓
loadPage()
  ├─ iframe.srcを変更
  └─ logIframeAccess()を実行
        ↓ POST JSON
teacher-auth/api/iframe-access-log.php
  ↓ セッション確認
AccessLog::logIframeOpen()
  ↓
access_logsテーブル
```

管理者による閲覧:

```text
teacher-auth/public/admin.php
  ↓
teacher-auth/public/assets/js/admin.js
  ↓ GET
teacher-auth/api/admin/access-logs.php
  ↓
AccessLog::getLogs()
  ↓
access_logsテーブル
```

## 3. 現在記録処理があるポータル

```text
kiweb2-fulltime.html
kiweb2-admin.html
```

現在の`kiweb2.html`には、同じiframeログ送信処理がない。全利用者のiframe遷移を記録する目的であれば、非常勤ポータルにも同じ処理を追加する必要がある。

この点は仕様として決める。

- 社員・管理者だけ記録する
- 非常勤を含む全ポータルで記録する

## 4. DB変更

既存の`access_logs`テーブルへ次のカラムとインデックスを追加する。

```text
event_type VARCHAR(30) NOT NULL DEFAULT 'page_view'
page_key VARCHAR(100) NULL
page_label VARCHAR(100) NULL
INDEX(event_type, created_at)
INDEX(page_key)
```

対象SQL:

```text
teacher-auth/database/migration_add_access_iframes_logs.sql
```

このSQLは`ADD COLUMN`をそのまま実行するため、すでに適用済みのDBへ再実行するとエラーになる。反映前に`SHOW COLUMNS`または`information_schema`で存在確認を行う。

確認SQLの例:

```sql
SHOW COLUMNS FROM access_logs;
SHOW INDEX FROM access_logs;
```

## 5. 本番へ反映するファイル

必須:

```text
teacher-auth/api/iframe-access-log.php
teacher-auth/src/Security/AccessLog.php
teacher-auth/database/migration_add_access_iframes_logs.sql
teacher-auth/api/admin/access-logs.php
teacher-auth/public/admin.php
teacher-auth/public/assets/js/admin.js
teacher-auth/public/assets/css/admin.css
```

送信元:

```text
kiweb2-fulltime.html
kiweb2-admin.html
```

非常勤も対象にする場合:

```text
kiweb2.html
```

これらは相互依存するため、単独で一部だけ反映しない。

## 6. リリースまでに必要な作業

### 1. 記録対象を決める

- 社員ポータル
- 管理者ポータル
- 非常勤ポータル
- PDF資料一覧
- サイネージ
- ICTマニュアル
- 管理画面

現在の実装は、ポータルのメニュー選択時に記録する方式である。iframe内でさらにリンクを操作した履歴や、本文内の細かな操作までは記録しない。

### 2. 本番DBを確認する

- `access_logs`テーブルが存在する
- 通常ログが保存されている
- iframe用3カラムが未追加か、すでに存在するか確認する
- マイグレーション前にテーブルをバックアップする

### 3. テストサーバーで一連の動作を確認する

ポータルでメニューを開き、Network、DB、管理画面の3か所を確認する。

### 4. 管理画面の権限を確認する

ログ一覧APIは、次のいずれかを持つユーザーだけが利用できる。

```text
manage_users
view_audit_logs
```

一般利用者がAPI URLを直接指定しても403になることを確認する。

### 5. 個人情報の運用を決める

IPアドレス、User-Agent、閲覧時刻、ユーザー情報を保存するため、次を決める。

- 利用目的
- 閲覧できる担当者
- 保存期間
- 問い合わせ時の利用方法
- 退職者・削除ユーザーのログの扱い

## 7. テストサーバー確認表

### ログ送信

- [ ] 社員ポータルでメニューを開くとPOSTされる
- [ ] 管理者ポータルでメニューを開くとPOSTされる
- [ ] 非常勤を対象にする場合、通常ポータルでもPOSTされる
- [ ] APIはGETを405で拒否する
- [ ] 未ログインのPOSTを401で拒否する
- [ ] `page_key`または`page_path`が空の場合は400になる
- [ ] 正常時にJSONを返す

### DB保存

- [ ] `event_type`へ`iframe_open`が保存される
- [ ] `page_key`へメニュー識別子が保存される
- [ ] `page_label`へ画面上のメニュー名が保存される
- [ ] `page_path`へ表示先パスが保存される
- [ ] `teacher`、`name`、`department`が保存されていない
- [ ] IPとUser-Agentが保存される
- [ ] 通常の`page_view`ログも引き続き保存される

### 管理画面

- [ ] 日付で検索できる
- [ ] ユーザー名・メールで検索できる
- [ ] `page_view`と`iframe_open`を切り替えられる
- [ ] ページキーで検索できる
- [ ] メニュー名・ページパスで検索できる
- [ ] ページングが動作する
- [ ] `manage_users`または`view_audit_logs`を持つ利用者だけ閲覧できる

### 障害時

- [ ] ログAPIが404でもiframe遷移は継続する
- [ ] DB書き込みに失敗してもポータル操作は継続する
- [ ] DBエラーがサーバーログへ記録される
- [ ] ログ機能の障害で業務画面が使用不能にならない

## 8. 本番リリース手順

1. 本番の`access_logs`をバックアップする。
2. カラムとインデックスの現在状態を確認する。
3. 未適用の場合だけDBマイグレーションを実行する。
4. `AccessLog.php`と2つのAPIを反映する。
5. 管理画面のPHP、JavaScript、CSSをセットで反映する。
6. 社員・管理者ポータルの送信処理を反映する。
7. 非常勤も対象にする場合は通常ポータルへ追加する。
8. NetworkでPOST結果を確認する。
9. DBへ1件保存されたことを確認する。
10. 管理画面へ表示されることを確認する。
11. 非管理者がログ一覧APIを利用できないことを確認する。

## 9. 注意点

### APIの成功応答だけでは保存成功を判断できない

`AccessLog::logIframeOpen()`はDB例外を内部で捕捉する。現在のAPIはその後`success: true`を返すため、HTTP応答だけではDB保存成功を断定できない。

必ずDBまたは管理画面で実データを確認する。

### マイグレーションの再実行

現在のSQLは冪等ではない。カラムが存在するDBへ再実行しない。

### 本番ファイルの部分置換

本番の管理画面には通常アクセスログ機能が既にある。テスト版の`admin.php`や`admin.js`だけを単独で上書きすると、APIやDBと表示項目が一致しなくなる。

### 保存期間

`AccessLog::cleanup()`は`bootstrap.php`から呼ばれ、各リクエストの約1%の確率で90日より古いログを削除する。アクセスが少ない環境では削除時期が一定にならないため、正式な保存期間を保証する場合はcron等で明示的に管理する。

### ログの範囲

記録されるのは「どのメニューを開いたか」であり、PDFを何ページ読んだか、マニュアル本文をどこまで読んだか、各業務画面で何を送信したかまでは記録しない。
