# kiweb

京都医塾の社内向け Web ツール群をまとめた private リポジトリです。  
講師ポータル、講師認証、会議室予約、Google Apps Script 連携、各種 HTML フォームを同じ配下で管理しています。

> Last reviewed: 2026-03-30

## 概要

- ルートの `index.html` は `/kiweb/login/public/login.php` へリダイレクトします。
- 講師ポータル本体は `kiweb2*.html` と `teacher-auth/` を中心に動きます。
- 会議室予約は `room-booking/`、講師一覧の外部同期は `teacher-sync/`、各種連携スクリプトは `gas/` にあります。
- デプロイ前提の公開パスは `/kiweb/` です。

## 主な構成

| Path | 役割 | 補足 |
| --- | --- | --- |
| `login/` | 既存ログイン基盤 | ルートのリダイレクト先 |
| `teacher-auth/` | 講師ログイン、ロール制御、管理画面、検索 API | 講師ポータルの認証まわりの中心 |
| `room-booking/` | 会議室予約システム | PHP API と画面を含む |
| `teacher-sync/` | `teacher-auth` のユーザー情報を外部へ同期する read-only API | GAS 連携用 |
| `gas/` | Google Apps Script / 周辺 HTML | フォーム、通知、同期処理など |
| `apis/` | 補助 API | 現在は example ファイル中心 |
| `kiweb2*.html` / `class-*.html` / `work-record.html` | ポータル画面・申請画面 | 画面単位の HTML エントリ |

## 最初に読むドキュメント

- [TEACHER_PORTAL_HANDOVER.md](./TEACHER_PORTAL_HANDOVER.md)
- [HANDOVER.md](./HANDOVER.md)
- [teacher-auth/SETUP.md](./teacher-auth/SETUP.md)
- [teacher-auth/USAGE.md](./teacher-auth/USAGE.md)
- [teacher-sync/README.md](./teacher-sync/README.md)
- [room-booking/API_SPEC.md](./room-booking/API_SPEC.md)

## ローカル作業の入口

### 前提

- PHP 7.4 以上
- MySQL
- Apache または `.htaccess` 相当の rewrite が使える Web サーバー
- `teacher-auth/` を触る場合は Composer
- GAS / Slack / JUST.DB / Google Sheets を触る場合は各サービスの権限

### セットアップの基本

PowerShell 例:

```powershell
Copy-Item login/.env.example login/.env
Copy-Item teacher-auth/.env.example teacher-auth/.env
Copy-Item teacher-sync/.env.example teacher-sync/.env
Copy-Item room-booking/api/config.sample.php room-booking/api/config.local.php
Copy-Item room-booking/api/service-account.example.json room-booking/api/service-account.json
Copy-Item apis/justdb_teacher_bridge.example.php apis/justdb_teacher_bridge.php
Copy-Item "gas/kiweb授業報告書検索/consts.example.gs" "gas/kiweb授業報告書検索/consts.gs"
composer install --working-dir teacher-auth
```

補足:

- `teacher-auth/.env` と `teacher-sync/.env` の `DB_*` は同じ DB を使う想定です。
- `room-booking/api/config.local.php` はローカル専用設定です。
- `teacher-auth/vendor/` は Git 管理していないため、clone 後は `composer install --working-dir teacher-auth` が必要です。
- `login/vendor/` は現状 repo に含まれています。

## セキュリティ / Git 管理外ファイル

この repo は GitHub に上げるために repo-safe 化してあります。実運用の値は commit せず、example ファイルだけを管理します。

主に Git 管理外にしているもの:

- `**/.env`
- `room-booking/api/config.local.php`
- `room-booking/api/service-account.json`
- `apis/justdb_teacher_bridge.php`
- `gas/**/consts.gs`
- `teacher-auth/storage/*.json`
- `teacher-auth/vendor/`

また、Webhook を含む一部 GAS ファイルは現在 repo に含めていません。必要なら安全な保管元から取得してローカル専用で扱ってください。

- `gas/欠勤振替申請/専任講師への連絡.gs`
- `gas/kiweb授業報告書_記録シート/post.gs`

## 運用メモ

- ルート公開 URL からの導線は `index.html` → `login/` です。
- 講師ポータルの入口やロール別振り分けは `teacher-auth/public/portal-guard.php` と `kiweb2*.html` 側で管理しています。
- リバースプロキシや Nginx 調整が必要な場合は `nginx-kiweb.conf` を参照してください。
- 画面追加やルーティング変更をしたら、README よりも先に handover 系ドキュメントの更新有無を確認すると安全です。
