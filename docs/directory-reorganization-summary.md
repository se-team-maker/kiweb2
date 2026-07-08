# ディレクトリ整理まとめ

最終更新: 2026-07-08

今回の整理では、URL入口と内部処理を分離し、テストサーバーへGitHub ActionsからFTPS反映しやすい構成に寄せました。

## 目的

- URLで直接開くものを `public/` に集める。
- PHPクラスや業務処理を `app/` に集める。
- 設定、SQL、migrationを `config/` に集める。
- キャッシュ、セッション、非公開PDFなどを `storage/` に集める。
- GASコードを `gas/` に集める。
- 旧 `teacher-auth/` と旧 `teacher-sync/` を廃止し、保守時に迷う入口を減らす。

## 現在の主な配置

| 分類 | 配置 |
| --- | --- |
| 認証画面・認証API | `public/auth/`, `public/auth/api/` |
| 認証内部処理 | `app/Auth/src/` |
| 出退勤 | `public/check-in/` |
| 授業報告・申告画面 | `public/class-report/` |
| 会議室予約画面・API | `public/room-booking/` |
| 会議室予約内部処理 | `app/RoomBooking/` |
| 会議室予約設定 | `config/room-booking/` |
| 資料配信入口 | `public/documents/` |
| 非公開PDF | `storage/auth/private-pdfs/` |
| ユーザー同期API | `public/user/api/teachers.php` |
| ユーザー同期GAS | `gas/user-sync/` |
| 認証DB SQL | `config/auth/database/` |

## 廃止・互換

| 旧系統 | 現在 |
| --- | --- |
| `teacher-auth/public/` | `public/auth/` |
| `teacher-auth/api/` | `public/auth/api/` |
| `teacher-auth/src/` | `app/Auth/src/` |
| `teacher-auth/database/` | `config/auth/database/` |
| `teacher-auth/storage/` | `storage/auth/runtime/` |
| `teacher-auth/private-pdfs/` | `storage/auth/private-pdfs/` |
| `teacher-sync/api/teachers.php` | `public/user/api/teachers.php` |
| `teacher-sync/gas/` | `gas/user-sync/` |
| `room-booking/api/lib/` | `app/RoomBooking/lib/` |
| `room-booking/api/migrations/` | `config/room-booking/migrations/` |

旧URLは `.htaccess` と `nginx-kiweb.conf` で `302` リダイレクトします。旧ブックマーク、GAS、メール内リンク、ポータルキャッシュ対策です。

## Git・デプロイでの扱い

テストサーバーは `staging` ブランチへのpushをきっかけに、GitHub ActionsからFTPSで反映します。

Gitに入れるもの:

- `public/`, `app/`, `config/`, `gas/`, `docs/` の実装・サンプル・SQL
- `.htaccess`, `nginx-kiweb.conf`
- `.github/workflows/deploy-staging.yml`

Gitに入れないもの:

- `.env`
- `app/Auth/vendor/`
- `config/auth/user-sync.env`
- `config/room-booking/config.local.php`
- `config/room-booking/credentials/service-account.json`
- `apis/justdb_teacher_bridge.php`
- `gas/**/consts.gs`
- `storage/auth/runtime/**`
- `storage/auth/private-pdfs/**`
- `storage/room-booking/**`

## ローカル確認結果

2026-07-08時点の確認:

| 確認 | 結果 |
| --- | --- |
| `php -l public/auth/bootstrap.php` | OK |
| `php -l public/auth/api/login.php` | OK |
| `GET /kiweb/public/auth/login.php` | `200 OK` |
| `GET /kiweb/public/auth/api/login.php` | `405 Method Not Allowed` JSON |
| `POST /kiweb/public/auth/api/login.php` | API到達済み。ただしローカルDBユーザー認証で停止 |

ログインPOSTの停止理由は、`app/Auth/.env` のDBユーザーがローカルMySQLで拒否されているためです。テストサーバー反映前に、対象環境のDB名、ユーザー、パスワード、`users` テーブルの有無を確認してください。

## テストサーバー確認項目

FTPS反映後は、最低限以下を確認します。

- `/kiweb/public/auth/login.php` が開く。
- ログインPOSTが `500` にならない。
- `/kiweb/public/auth/api/me.php` がログイン状態に応じたJSONを返す。
- `/kiweb/public/check-in/` の画面が開く。
- `/kiweb/public/class-report/` の主要画面が開く。
- `/kiweb/public/room-booking/index.php` が開く。
- `/kiweb/public/documents/index.php` が権限に応じて開く。
- 旧URLから新URLへ `302` される。

## 保守時の基本

- 新規URLは `/kiweb/public/...` を使う。
- 内部処理を `public/` に増やしすぎない。
- `teacher-auth/` と `teacher-sync/` を復活させない。
- 旧URL互換が必要な場合は、`.htaccess` と `nginx-kiweb.conf` の両方を見る。
- 置き場所に迷ったら [path-migration-map.md](path-migration-map.md) のルールに従う。
