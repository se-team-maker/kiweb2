# kiweb

京都医塾の社内向け Web ツール群をまとめた private リポジトリです。

このリポジトリは、ローカルで動作確認した内容を Git に反映し、`staging` ブランチへの push で GitHub Actions からテストサーバーへ FTPS デプロイする前提です。

**作成:** 真砂 大朗（SE）

## 現在の構成

| Path | 役割 |
| --- | --- |
| `index.html` | ルート入口。ログイン導線へリダイレクト |
| `kiweb2.html`, `kiweb2-fulltime.html`, `kiweb2-admin.html` | 講師ポータル画面 |
| `public/` | ブラウザ、GAS、外部サービスから直接アクセスされる画面・公開API・公開アセット |
| `app/` | PHPクラス、内部ライブラリ、業務処理、CLIツール |
| `config/` | DB schema、migration、設定サンプル、サーバー用設定 |
| `storage/` | セッション、キャッシュ、非公開PDFなどGitに入れない保存領域 |
| `gas/` | Google Apps Script 連携コード |
| `docs/` | 保守資料、仕様、移行メモ |
| `login/` | 既存ログイン機能 |
| `room-booking/` | 旧URL互換リンク。実体は `public/room-booking/`, `app/RoomBooking/`, `config/room-booking/` |
| `.github/workflows/deploy-staging.yml` | `staging` ブランチからテストサーバーへFTPS反映 |

基本ルールは `public = URL・画面・入口`、`app = 処理・機能・責務` です。詳細は [docs/path-migration-map.md](docs/path-migration-map.md) を参照してください。

## 機能別の入口

| 機能 | 主な場所 |
| --- | --- |
| 認証・講師ポータル | `public/auth/`, `public/auth/api/`, `app/Auth/` |
| 出退勤 | `public/check-in/` |
| 授業報告・申告・検索 | `public/class-report/`, `public/auth/api/` |
| 会議室予約・教室割 | `public/room-booking/`, `app/RoomBooking/`, `config/room-booking/` |
| 資料配信・PDF | `public/documents/`, `storage/auth/private-pdfs/` |
| ユーザー同期 | `public/user/api/teachers.php`, `gas/user-sync/` |

旧 `teacher-auth/` と旧 `teacher-sync/` は廃止済みです。新規修正で復活させないでください。

## ローカル準備

### 前提

- PHP 7.4 以上
- MySQL
- Composer
- Apacheなど、`/kiweb` として配信できるWebサーバー
- GAS / Slack / Google Sheets などの利用権限が必要な機能あり

### 初期セットアップ

```powershell
Copy-Item login/.env.example login/.env
Copy-Item app/Auth/.env.example app/Auth/.env
Copy-Item config/auth/user-sync.env.example config/auth/user-sync.env
Copy-Item config/room-booking/config.sample.php config/room-booking/config.local.php
Copy-Item config/room-booking/credentials/service-account.example.json config/room-booking/credentials/service-account.json
Copy-Item apis/justdb_teacher_bridge.example.php apis/justdb_teacher_bridge.php
Copy-Item "gas/kiweb授業報告書検索/consts.example.gs" "gas/kiweb授業報告書検索/consts.gs"
composer install --working-dir app/Auth
```

補足:

- `app/Auth/vendor/` はGit管理しません。clone後は `composer install --working-dir app/Auth` が必要です。テストサーバーへのFTPS反映時はGitHub Actions内でComposer installしてからアップロードします。
- `login/vendor/` は現状repoに含まれています。
- `.env`、ローカル設定、サービスアカウント、非公開PDF、キャッシュはGitに入れません。
- PHPやDBにつなげて試すときは、`file://` ではなく `http://localhost/kiweb/...` で確認してください。

## ローカル動作確認

最低限、push前に以下を確認します。

```bash
php -l public/auth/bootstrap.php
php -l public/auth/api/login.php
curl -i http://localhost/kiweb/public/auth/login.php
curl -i http://localhost/kiweb/public/auth/api/login.php
```

期待値:

- ログイン画面は `200 OK`
- `GET /kiweb/public/auth/api/login.php` は `405 Method Not Allowed` のJSON
- ログインPOSTは、DB設定とテストユーザーが正しければ成功または認証失敗のJSON
- `500` が出る場合は、まず Apache/PHP エラーログと `app/Auth/.env` のDB設定を確認

## テストサーバー反映

このリポジトリでは、`staging` ブランチへの push で `.github/workflows/deploy-staging.yml` が動き、FTPSでテストサーバーへ反映します。

```bash
git status --short
git diff
git add 変更したファイル
git commit -m "chore: reorganize kiweb directories"
git switch staging
git merge 作業ブランチ
git push origin staging
```

GitHub Actions 側の接続情報は repository secrets に置きます。

- `KIWEB_DEV_FTP_SERVER`
- `KIWEB_DEV_FTP_USERNAME`
- `KIWEB_DEV_FTP_PASSWORD`
- `KIWEB_DEV_FTP_PORT`
- `KIWEB_DEV_FTP_SERVER_DIR`

デプロイ後はテストサーバーで、ローカルと同じURLを確認します。

- `/kiweb/public/auth/login.php`
- `/kiweb/public/auth/api/login.php`
- `/kiweb/public/room-booking/index.php`
- `/kiweb/public/documents/index.php`
- `/kiweb/public/user/api/teachers.php`

## Gitに入れないもの

`.gitignore` とFTPデプロイ除外で、秘密情報や保存データはGit/テストサーバー反映から外します。

- `**/.env`
- `config/auth/user-sync.env`
- `config/room-booking/config.local.php`
- `config/room-booking/credentials/service-account.json`
- `apis/justdb_teacher_bridge.php`
- `gas/**/consts.gs`
- `storage/auth/runtime/**`
- `storage/auth/private-pdfs/**`
- `storage/room-booking/**`

`app/Auth/vendor/` はGitには入れませんが、`.github/workflows/deploy-staging.yml` がデプロイ前に生成し、テストサーバーへアップロードします。

`git add .` は意図しないローカルファイルを拾いやすいので、基本は `git add ファイル名` で確認しながら追加してください。

## 関連ドキュメント

- [docs/path-migration-map.md](docs/path-migration-map.md): フォルダ整理ルール
- [docs/directory-reorganization-summary.md](docs/directory-reorganization-summary.md): 今回のディレクトリ整理まとめ
- [docs/pdf-materials-system-summary.md](docs/pdf-materials-system-summary.md): 資料配信機能まとめ
- [docs/ict-manual-system-summary.md](docs/ict-manual-system-summary.md): ICTマニュアルまとめ

---

最終確認: 2026-07-08（ディレクトリ整理とstagingデプロイ前提を反映）
