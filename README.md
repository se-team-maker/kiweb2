# kiweb

京都医塾の社内向け Web ツール群をまとめた private リポジトリです。

`index.html` にアクセスすると `/kiweb/login/public/login.php` にリダイレクトされます。  
講師ポータル、講師認証、会議室予約、Google Apps Script 連携用コードを同じ配下で管理しています。

## 主な構成

| Path | 役割 |
| --- | --- |
| `index.html` | ルート。`/kiweb/login/public/login.php` へリダイレクト |
| `login/` | 既存ログイン機能 |
| `teacher-auth/` | 講師ログイン、ロール制御、管理画面、各種 API |
| `room-booking/` | 会議室予約機能 |
| `teacher-sync/` | `teacher-auth` のユーザー情報を外部へ同期する read-only API |
| `apis/` | JustDB 連携ブリッジ（`justdb_teacher_bridge.php` など） |
| `gas/` | Google Apps Script と連携用ファイル |
| `kiweb2*.html` | 講師ポータル画面 |
| `class-*.html`, `work-record.html` | 各種申請・入力画面 |

## ローカル準備

### 前提

- PHP 7.4 以上
- MySQL
- Composer
- GAS / Slack / Google Sheets などの利用権限が必要な機能あり

### 最低限のセットアップ

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

- `teacher-auth/vendor/` は Git 管理していないので、clone 後は `composer install --working-dir teacher-auth` が必要です。
- `login/vendor/` は現状 repo に含まれています。
- `room-booking/api/config.local.php` と各 `.env` はローカル専用です。
- `index.html` のリダイレクト先は `/kiweb/...` の絶対パスです。PHP や DB とつなげて試すときは、Web サーバー上で `/kiweb` 配下として配信してください（`file://` 直開きではログインへ飛びません）。

## 普段の Git 手順

### そのまま `main` で更新する場合

```powershell
git status --short
git diff
git add 変更したファイル
git commit -m "fix: 変更内容"
git push
```

### ブランチで作業してからマージする場合

```powershell
git switch main
git pull --rebase
git switch -c fix/something

# 編集
git add 変更したファイル
git commit -m "fix: 変更内容"

# テスト後
git switch main
git merge fix/something
git push
git branch -d fix/something
```

## Git に入れないもの

この repo では秘密情報やローカル専用設定を Git 管理しません。

- `**/.env`
- `room-booking/api/config.local.php`
- `room-booking/api/service-account.json`
- `apis/justdb_teacher_bridge.php`
- `gas/**/consts.gs`
- `teacher-auth/storage/*.json`
- `teacher-auth/vendor/`

`git add .` を使うと、未整理のローカルファイルまで拾いやすいので、基本は `git add ファイル名` を使うのがおすすめです。

## メモ

- いま一部の GAS ファイルはローカル専用のため Git 管理外です。
- `teacher-auth/public/portal-guard.php` と `kiweb2*.html` が講師ポータル導線の中心です。
- Nginx 側の調整が必要な場合は `nginx-kiweb.conf` を参照してください。

---

最終確認: 2026-03-30（リポジトリ構成・`.gitignore` と突き合わせ）
