# teacher-sync

`teacher-auth` の `users` を Google スプレッドシートへ同期するための Read-Only API と GAS テンプレートです。

## 構成

- `api/bootstrap.php`: `.env` 読み込みと DB 接続
- `api/teachers.php`: 講師一覧 API（`GET` 専用）
- `.env.example`: 環境変数テンプレート
- `.htaccess`: `.env` 直アクセス遮断
- `gas/Code.gs`: スプレッドシート同期用 GAS

## セットアップ

1. `teacher-sync/.env.example` をコピーして `teacher-sync/.env` を作成
2. `TEACHER_SYNC_SECRET` に 32 文字以上のランダム文字列を設定
3. `teacher-sync/.env` を置かない場合は `teacher-auth/.env` の DB 設定を流用
4. 共有サーバーで `teacher-auth/.env` を読めない場合は、`teacher-sync/.env` にも `DB_*` を設定
5. Web から `https://.../kiweb/teacher-sync/api/teachers.php` へアクセスできるよう配置

## 環境変数

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- `TEACHER_SYNC_SECRET`（必須、32文字以上）
- `TEACHER_SYNC_BEARER_TOKEN`（任意）
- `TEACHER_SYNC_DEFAULT_ROLE_FILTER`（`all` or `teacher`、既定 `all`）

## API 仕様

### Endpoint

`GET /kiweb/teacher-sync/api/teachers.php`

### Query

- `since` (任意): ISO8601。指定時は `updated_at >= since`
- `role` (任意): `teacher` or `all`
  - `teacher` は `full_time_teacher` と `part_time_teacher` を対象にします（互換のため `teacher` も含む）。

### 認証

以下どちらかを利用:

1. `X-Sync-Timestamp` + `X-Sync-Signature`  
署名対象は `timestamp` 文字列、署名は `HMAC-SHA256(secret)`（hex または base64）。
2. `Authorization: Bearer ...`  
`TEACHER_SYNC_BEARER_TOKEN` 一致、または `timestamp.signature`（5分有効）形式。

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "email": "teacher@example.com",
      "name": "Name",
      "status": "active",
      "roles": "講師",
      "scopes": "四条烏丸, 教務",
      "updated_at": "2026-02-18 10:30:00"
    }
  ]
}
```

## 疎通確認（PowerShell）

```powershell
$url = "https://system.kyotoijuku.com/kiweb/teacher-sync/api/teachers.php?role=all"
$secret = "YOUR_32_PLUS_CHAR_SECRET"
$ts = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds().ToString()
$hmac = [System.Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes($secret))
$sigBytes = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($ts))
$sig = -join ($sigBytes | ForEach-Object { $_.ToString("x2") })

Invoke-WebRequest -Uri $url -Headers @{
  "X-Sync-Timestamp" = $ts
  "X-Sync-Signature" = $sig
}
```

## GAS 設定

1. 同期先スプレッドシートに `gas/Code.gs` を貼り付け
2. `Code.gs` の固定値を必要に応じて編集
- `FIXED_API_URL`
- `FIXED_TEACHER_SYNC_SECRET`（PHP と同じ秘密鍵）
- `FIXED_SHEET_NAME`
3. `installSyncTrigger()` を1回実行（15分トリガー作成）
4. `syncTeachers()` を実行して初回同期

## 運用メモ

- 差分同期では「未返却 = 削除」とは限らないため、既定では物理削除しません。
- `status=deleted` の行は GAS 側で非表示にします。

## トラブルシューティング（HTTP 500 / DB_QUERY_FAILED）

1. `.env` に `TEACHER_SYNC_DEBUG=1` を追加
2. GAS で再度同期を実行
3. エラーレスポンスの `debug` フィールドに具体的なエラー内容が表示される
4. 例: `Table 'xxx.users' doesn't exist` → 接続先 DB に `users` テーブルがない（別 DB に接続している可能性）
5. 原因を特定・修正後、`TEACHER_SYNC_DEBUG` を削除またはコメントアウト
