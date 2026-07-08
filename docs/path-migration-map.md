# パス整理ルール

最終更新: 2026-07-06

この文書は、旧構成しか知らない人が保守に入ったときに「どこを見ればよいか」を判断するための整理ルールです。

細かい旧パス対新パスの暗記ではなく、置き場所の考え方を基準にしてください。

## 全体ルール

| フォルダ | 置くもの | 判断基準 |
| --- | --- | --- |
| `public/` | URL入口、画面、公開API、公開アセット | ブラウザ、GAS、外部サービスから直接アクセスされる |
| `app/` | PHPクラス、内部ライブラリ、業務処理、CLIツール | URLとして直接叩かせない処理本体 |
| `config/` | SQL、migration、設定サンプル、サーバー用設定 | 実行コードではないが運用・構築に必要 |
| `storage/` | キャッシュ、セッション、アップロード、非公開PDF | Webから直接見せない生成物・保存データ |
| `gas/` | Google Apps Script | GAS側へ貼り付ける、または同期するコード |
| `tests/` | テスト | 機能別のテストコード |
| `docs/` | 保守資料、仕様、移行メモ | 人が読む説明 |

基本は `public = URL・画面・入口`、`app = 処理・機能・責務` です。

## 機能別の見方

### 認証・講師ポータル

| 種別 | 現在の場所 |
| --- | --- |
| ログイン画面、管理画面、ポータルガード | `public/auth/` |
| ログイン、ログアウト、本人情報、管理系API | `public/auth/api/` |
| 認証クラス、セッション、DB接続、監査ログ | `app/Auth/src/` |
| Composer依存 | `app/Auth/vendor/` |
| 認証DB schema | `config/auth/database/` |
| 認証 `.env` | `app/Auth/.env` |
| セッション、キャッシュ等 | `storage/auth/runtime/` |

旧 `teacher-auth/` は削除済みです。復活させず、認証系は `public/auth/` と `app/Auth/` を見てください。

### 出退勤

| 種別 | 現在の場所 |
| --- | --- |
| 出退勤画面 | `public/check-in/` |
| 出退勤API | `public/check-in/api/` |
| 出退勤専用CSS/JS | `public/check-in/assets/` |

共通のログイン状態やユーザー情報が必要な場合は `public/auth/api/me.php` と `app/Auth/src/` 側も確認します。

### 授業報告・申告・検索

| 種別 | 現在の場所 |
| --- | --- |
| 授業報告、欠勤振替、検索画面 | `public/class-report/` |
| 関連API | 主に `public/auth/api/` |
| ポータル側のリンク定義 | `kiweb2.html`, `kiweb2-fulltime.html`, `kiweb2-admin.html` |

画面は `public/class-report/`、データ取得や同期処理は `public/auth/api/` を起点に追います。

### 会議室予約・教室割

| 種別 | 現在の場所 |
| --- | --- |
| 会議室予約画面 | `public/room-booking/index.php` |
| 会議室予約API | `public/room-booking/api/` |
| 内部ライブラリ | `app/RoomBooking/lib/` |
| CLI・保守ツール | `app/RoomBooking/tools/` |
| 設定、migration、認証情報サンプル | `config/room-booking/` |
| GAS | `gas/room-booking/` |

旧 `room-booking/` は互換リンクだけ残しています。修正は新しい実体側に入れてください。

### 資料配信・PDF

| 種別 | 現在の場所 |
| --- | --- |
| 資料一覧、ビューア、既読API、管理画面 | `public/documents/` |
| 既存の処理本体 | `public/auth/pdf-*.php` |
| 非公開PDF | `storage/auth/private-pdfs/` |
| PDF関連SQL | `config/auth/database/pdf_*.sql` |

`public/documents/` は資料配信としての入口です。処理本体が `public/auth/pdf-*.php` に残っている場合があります。

### ユーザー同期

| 種別 | 現在の場所 |
| --- | --- |
| 同期API | `public/user/api/teachers.php` |
| 同期API bootstrap | `public/user/api/teachers-bootstrap.php` |
| GAS | `gas/user-sync/` |
| 同期用 `.env` サンプル | `config/auth/user-sync.env.example` |

旧 `teacher-sync/` は削除済みです。環境変数名は原則 `USER_SYNC_*` を使います。

### ICTマニュアル

| 種別 | 現在の場所 |
| --- | --- |
| マニュアル本文、画像、索引 | `app/Documents/ict-manual/` |
| 保守資料 | `docs/` |

ICTマニュアルは公開URLの入口と、Markdownなどの実データの置き場所を分けて考えます。

## 旧URL互換

URL変更直後は、古いブックマーク、GAS、メール内リンク、ポータルキャッシュが残ります。

そのため旧URLは `.htaccess` と `nginx-kiweb.conf` で新URLへ `302` リダイレクトします。

| 旧系統 | 新系統 |
| --- | --- |
| `/kiweb/teacher-auth/public/...` | `/kiweb/public/auth/...` |
| `/kiweb/teacher-auth/api/...` | `/kiweb/public/auth/api/...` |
| `/kiweb/teacher-sync/api/teachers.php` | `/kiweb/public/user/api/teachers.php` |
| `/kiweb/room-booking/room-booking.php` | `/kiweb/public/room-booking/index.php` |
| `/kiweb/room-booking/api/...` | `/kiweb/public/room-booking/api/...` |
| `/kiweb/*.html` の旧画面 | `/kiweb/public/<機能>/...` |

運用が落ち着いて、アクセスログ上も旧URLが十分減ったら `301` への変更を検討します。

## 不具合時の追い方

| 症状 | 見る順番 |
| --- | --- |
| 404 | `public/<機能>/` に入口があるか、`.htaccess` / `nginx-kiweb.conf` のリダイレクトが合っているか |
| 画面は出るがAPIが落ちる | ブラウザのリクエストURL、`public/<機能>/api/` または `public/auth/api/`、Apache/PHPエラーログ |
| ログインできない | `public/auth/login.php`、`public/auth/api/login.php`、`app/Auth/.env`、`app/Auth/src/Config/Database.php` |
| 権限やログイン状態がおかしい | `app/Auth/src/Auth/Session.php`、`public/auth/api/me.php`、管理系API |
| 授業報告・申告が動かない | `public/class-report/`、`public/auth/api/declaration-schedule.php`、`absence-schedule.php`、`pending-reschedule.php` |
| 会議室予約が動かない | `public/room-booking/`、`app/RoomBooking/`、`config/room-booking/config.php` |
| PDFが開かない | `public/documents/`、`public/auth/pdf-*.php`、`storage/auth/private-pdfs/` |
| GAS連携が落ちる | GAS内URLが `/kiweb/public/...` を向いているか、対象APIが `public/` 配下にあるか |
| キャッシュやセッションが怪しい | `storage/auth/runtime/` |

## 新規追加時の判断

- URLとして直接アクセスさせるなら `public/<機能>/` に置く。
- 画面専用のCSS/JSは `public/<機能>/assets/` に置く。
- PHPクラスや共通処理は `app/<Feature>/` または `app/Shared/` に置く。
- 秘密情報、DB schema、migration、設定サンプルは `config/<機能>/` に置く。
- ユーザーに直接見せない保存ファイルは `storage/<機能>/` に置く。
- GASコードは `gas/<機能>/` に置く。
- 旧ディレクトリ名を前提にした新規実装はしない。

## 注意

- `teacher-auth/` と `teacher-sync/` は廃止済みです。
- `app/Auth/src/Auth/Session.php` に旧パス文字列が残っている場合がありますが、古いCookieを消すための互換処理です。
- `room-booking/` は外部URL互換のためのリンクだけ残しています。
- `app/`, `config/`, `storage/` は直接URLで開かせない前提です。
- 新しくURLを書く場合は、原則 `/kiweb/public/...` を使ってください。
