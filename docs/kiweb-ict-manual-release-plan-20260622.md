# kiweb ICTマニュアル リリース作業

作成日: 2026年6月22日

## 0. 現在の状況

ICTマニュアルは、kiwebの操作方法やトラブル対応を、ログインユーザーが検索・閲覧する機能である。

閲覧機能はテストサーバーへ反映済みだが、2026年6月20日に取得した本番サーバーのファイル一式には、ICTマニュアル関連ファイルが存在しない。

本番にない主要ファイル:

```text
teacher-auth/public/ict-manual.php
teacher-auth/public/ict-manual-file.php
teacher-auth/ict-manual/manuals/
teacher-auth/ict-manual/assets/
```

そのため、ICTマニュアルは本番へ新規配置する機能になる。

また、機能は次の2段階に分けて考える必要がある。

1. ICTマニュアルの閲覧機能
2. 外部サイトからMarkdown・JSON・画像を登録するアップロード機能

閲覧機能だけでも運用できる。外部アップロードは認証トークン、書き込み権限、即時公開、ロールバック等の検討が必要なため、別リリースに分ける方が安全である。

## 1. 閲覧機能でできること

- ポータルの「ICTマニュアル」から開く
- カテゴリ別に一覧表示する
- タイトル、カテゴリ、説明、タグで検索する
- Markdown本文をHTMLとして表示する
- Markdown内の画像を表示する
- 更新日、対象者、目次を表示する
- 非常勤、社員、管理者ごとに表示対象を分ける

初期実装ではDBを使用しない。

## 2. 閲覧機能の仕組み

```text
kiweb2.html / kiweb2-fulltime.html / kiweb2-admin.html
  ↓ iframe
ict-manual.php
  ↓ ログイン確認
ict-manual-file.php?action=index
  ↓
manuals/index.json
  ↓ 選択したマニュアル
ict-manual-file.php?action=manual&id=...
  ↓
manuals/{id}.md
```

画像:

```text
Markdown内の images/... 参照
  ↓
ict-manual-file.php
  ↓
teacher-auth/ict-manual/assets/images/
```

`teacher-auth/ict-manual/`内のJSON、Markdown、画像は、Webサーバーから直接配信せず、必ずログイン確認を行うPHP経由で返す。

## 3. 対象ユーザー

`manuals/index.json`の`target`で表示対象を指定する。

| target | 表示対象 |
|---|---|
| `all` | 全ログインユーザー |
| `parttime` | 非常勤 |
| `fulltime` | 社員 |
| `admin` | 管理者 |

一覧から隠すだけでなく、本文や画像を直接要求された場合にも`ict-manual-file.php`で対象ロールを確認する。

## 4. 現在用意されている初期マニュアル

```text
login.md
cache-refresh.md
checkin.md
pdf-viewer.md
contact.md
```

現在の内容は初期サンプルを兼ねている。リリース前に、実際の本番画面、ボタン名、問い合わせ先、運用手順と一致しているか確認する。

## 5. 閲覧機能のリリース作業

### 1. 掲載内容を確定する

- ページ名を「ICTマニュアル」で確定する
- 初期公開するマニュアルを決める
- 問い合わせ先を決める
- 管理者向け情報の掲載範囲を決める
- 秘密情報や内部接続情報が含まれていないことを確認する

### 2. 本番へファイルを配置する

```text
teacher-auth/public/ict-manual.php
teacher-auth/public/ict-manual-file.php
teacher-auth/ict-manual/manuals/
teacher-auth/ict-manual/assets/
docs/ict-manual-authoring-guide.md
```

運用資料である`docs`はWebサーバーへ置かず、Gitだけで管理してもよい。

### 3. 直接アクセスを禁止する

`.htaccess`へ次の保護設定を反映する。

```apache
RewriteRule ^teacher-auth/ict-manual/ - [F,L]
```

この設定がない場合、Webサーバーの構成によってはMarkdown、JSON、画像へ直接アクセスされ、PHP側のロール判定を通らない可能性がある。

### 4. ポータルへ導線を追加する

対象:

```text
kiweb2.html
kiweb2-fulltime.html
kiweb2-admin.html
```

PC版・モバイル版のメニューへ「ICTマニュアル」を追加し、`PAGES`へ次を追加する。

```text
/kiweb/teacher-auth/public/ict-manual.php
```

### 5. ロール別にテストする

未ログイン、非常勤、社員、管理者で一覧・本文・画像のアクセス制御を確認する。

## 6. 外部アップロード機能

### 目的

kiwebとは別のPHPサイトから、Markdown、メタ情報JSON、画像を送信し、ICTマニュアルへ即時反映する。

構成:

```text
登録担当者
  ↓
外部アップローダーPHP
  ↓ HTTPS・Bearerトークン
ict-manual-upload-api.php
  ↓
IctManualPublisher.php
  ├─ manuals/{id}.md
  ├─ assets/images/{id}/
  └─ manuals/index.json
```

対象ファイル:

```text
teacher-auth/public/ict-manual-upload-api.php
teacher-auth/src/Service/IctManualPublisher.php
tools/ict-manual-uploader/
teacher-auth/.env.example
.htaccess
```

### 現在の状態

- Git履歴にはアップロードAPIとPublisherのコミットがある。
- 外部アップローダー本体の`tools/ict-manual-uploader/index.php`はGit管理されている。
- 外部アップローダー用の`.htaccess`と`README.md`は現在未追跡であり、リリース対象として確定・コミットされていない。
- 本番サーバーには未配置。
- 実際のAPIトークン設定と外部サーバーからの疎通確認は未実施。
- 保存先の書き込み権限は未確認。
- 新規登録、上書き、画像差し替え、異常系の統合テストは未実施。
- 履歴管理、承認、公開予約、未使用画像削除はない。

### リリース前に必要な作業

1. 閲覧機能を先に安定稼働させる。
2. 外部アップロード専用のリリースとして分ける。
3. kiweb側と外部サーバーへ同じ32文字以上のAPIトークンを設定する。
4. 外部画面用のアクセスキーはAPIトークンと別にする。
5. HTTPS通信を確認する。
6. `manuals/`と`assets/images/`を書き込み可能にする。
7. `index.json`、Markdown、画像を事前バックアップする。
8. 新規IDと既存ID上書きの両方をテストする。
9. 失敗時の復元手順を確認する。

## 7. テストサーバー確認表

### 閲覧

- [ ] 非常勤・社員・管理者ポータルから開ける
- [ ] PC版とモバイル版の両方に導線がある
- [ ] カテゴリ一覧が表示される
- [ ] タイトル、カテゴリ、説明、タグで検索できる
- [ ] Markdown本文が正しく表示される
- [ ] 目次と一覧へ戻るボタンが動作する
- [ ] 画像が表示される
- [ ] PC・スマートフォン・iPadで大きな崩れがない

### 認証・権限

- [ ] 未ログインで`ict-manual.php`を開くとログインへ進む
- [ ] 未ログインで`ict-manual-file.php`を呼ぶと401になる
- [ ] `teacher-auth/ict-manual/`へ直接アクセスすると403になる
- [ ] 非常勤が社員向け本文を取得できない
- [ ] 非常勤・社員が管理者向け本文を取得できない
- [ ] 管理者が管理者向け本文を取得できる
- [ ] 非表示設定の項目が一覧へ出ない

### 安全性

- [ ] Markdown内のHTMLやscriptが実行されない
- [ ] `../`等で別ファイルを取得できない
- [ ] SVG等の想定外ファイルを画像として実行できない
- [ ] 本文にパスワード、トークン、GAS URL、FTP情報がない

### 外部アップロード

- [ ] トークンなし・不一致を401で拒否する
- [ ] HTTP通信を拒否する
- [ ] 不正JSONを拒否する
- [ ] 不正なtargetを拒否する
- [ ] 不正拡張子・偽装画像・SVGを拒否する
- [ ] 新規マニュアルを登録できる
- [ ] 同一IDを上書きできる
- [ ] 同時更新で`index.json`の内容が消えない
- [ ] アップロード後に閲覧画面へ即時反映される

## 8. 本番リリース手順

### 第1段階: 閲覧機能

1. 初期マニュアルの内容と対象ロールを確定する。
2. 本番反映対象をバックアップする。
3. PHP、Markdown、JSON、画像を本番へ配置する。
4. `.htaccess`の直接アクセス禁止を反映する。
5. 3ポータルへPC・モバイル導線を追加する。
6. 未ログイン、非常勤、社員、管理者で確認する。

### 第2段階: 外部アップロード

1. 閲覧機能の安定稼働を確認する。
2. API、Publisher、外部アップローダーを配置する。
3. 本番環境変数へトークンを設定する。
4. 保存先を書き込み可能にする。
5. テスト用IDで新規登録と上書きを確認する。
6. バックアップから復元できることを確認する。

## 9. 注意点

### 即時公開

外部アップロードに承認待ち状態はなく、成功すると即時公開される。誤った本文や対象ロール設定もすぐ利用者へ表示されるため、公開前レビュー方法を決める。

### 履歴とロールバック

同じIDは上書きされるが、旧版の自動保存はない。Gitまたはサーバーバックアップで履歴を残す。

### ロール設定

`target`を誤ると、管理者向け情報を一般ユーザーへ公開する可能性がある。特に`all`と`admin`を取り違えない。

### `.env`とローカル設定

APIトークンをGitへコミットしない。現在存在する`.env.save`等のローカル設定ファイルもコミットしない。

外部アップローダー用の`.htaccess`と`README.md`も、内容を確認して必要なファイルだけを明示的にGitへ追加する。

### DBは不要

初期閲覧機能と外部アップロード機能は、どちらもDBテーブル追加を必要としない。データの正本は`index.json`、Markdown、画像ファイルである。
