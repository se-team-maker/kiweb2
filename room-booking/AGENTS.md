# 会議室予約システム AGENTS.md

## 1. 現状構成（PHP移行後）

- フロント: `room-booking.php`（Googleサイト埋め込み or 同一サーバー配信、サーバーでは `/room-booking/room-booking.php` を公開）
- バック: PHP API `api/index.php`
- 正データ: MySQL（phpMyAdmin運用）
- ミラー: Google Sheets（管理・可視化用）
- GAS: 本番では使用しない（停止推奨）

## 2. データ定義

### 2.1 Rooms
| roomId | roomName | capacity | equipment |
|--------|----------|----------|-----------|
| 591 | 5階執務室 | 10 | - |
| 592 | 5階応接A | 6 | モニター |
| 294 | 2階応接B | 6 | モニター |
| 593 | 5階OL面 | 4 | - |
| 291 | 面談A | 4 | - |
| 292 | 面談B | 4 | - |
| 293 | CSL | 8 | プロジェクター |
| 301 | 3号館面3A | 4 | - |
| 302 | 3号館面3B | 4 | - |
| 601 | 六角5F | 10 | プロジェクター |

### 2.2 Reservations
| カラム | 型 | 説明 |
|--------|----|------|
| reservationId | String | 予約ID（UUID） |
| roomId | Number | 部屋ID |
| date | String | 予約日（YYYY-MM-DD） |
| startTime | String | 開始時間（HH:MM） |
| durationMinutes | Number | 所要時間（分） |
| meetingName | String | 会議名 |
| reserverName | String | 予約者名 |
| visitorName | String | 来客名 |
| createdAt | DateTime | 作成日時 |
| updatedAt | DateTime | 更新日時 |
| roomName | String | 部屋名（冗長化） |
| recurringEventId | String | 繰り返しID |
| recurrenceStartDate | String | 繰り返し開始日 |

## 3. API仕様（PHP）

**エンドポイント:** `api/index.php`  
**方式:** `action` パラメータで分岐  
**レスポンス:** `{ "success": true, "data": ... }` / エラー時 `{ "success": false, "error": "...", "status": 400 }`

**主要アクション**
- `getRooms`
- `getReservations`（`date` 必須）
- `getReservation`（`id` 必須）
- `createReservation`
- `updateReservation`
- `deleteReservation`
- `createReservations`（一括）
- `createRecurringReservations`（繰り返し）

## 4. Sheetsミラー運用ルール

- **正データはMySQL**。シートは**ミラー**（管理閲覧用）。
- シートを直接編集してもDBに反映されない。
- シート→DBの反映が必要な場合は、`api/tools/import_from_sheet.php` を手動実行。
- DB→シートの再生成は `api/tools/rebuild_sheet.php`。
- `sync_mode` が `async` の場合は同期がキュー経由（非同期）になる。

**同期対象シート**
- `Rooms`
- `Reservations`
- `日別JSON`（パフォーマンス用キャッシュ）

## 5. 設定

`api/config.local.php` にDB/Sheets設定を記載。
```
'service_account_json' => __DIR__ . '/service-account.json'
```
スプレッドシートはサービスアカウントのメールへ共有。

**非同期同期（推奨設定）**
- `sync_mode: async`
- 定期実行: `php api/tools/process_sheets_queue.php`
- `fastcgi_finish_request()` が使える環境では `queue_run_after_response: true` で「API応答後に少しだけキュー処理」も可能（レスポンスは待たない）
- `fastcgi_finish_request()` が使えない環境では、次の `get` リクエスト（ポーリング等）時に少しだけキュー処理して追随させる
- 手動でキューを回すAPI: `api/index.php?action=processSheetsQueue`（時間予算は `queue_run_seconds`）
- 重複修復（Reservationsシート）: `api/index.php?action=dedupeReservationsSheet`

## 6. 移行・運用ツール

- `api/migrations/schema.sql`: MySQLテーブル作成
- `api/tools/import_from_sheet.php`: シート → DB取り込み
- `api/tools/rebuild_sheet.php`: DB → シート再生成
- `api/tools/process_sheets_queue.php`: Sheets同期キュー処理

## 7. 画面・機能（維持）

- タイムライン: 9:00–21:00、15分刻み、10室、ヘッダー/時間軸固定
- 予約詳細: 編集/削除
- 新規/編集: 15分/30分単位、競合チェック
- フロントは既存のまま（UI/UX変更なし）

## 8. ログイン連携（暫定運用）

- ログインシステムは `ログインシステム/` 配下に配置（別プロジェクト）
- 共有ID/パスワード運用のため、ログイン画面は「次へ」以外を非表示（`ログインシステム/public/login.php` の `simple-login` と `ログインシステム/public/assets/css/style.css`）
- ログイン成功時の遷移先は `ログインシステム/.env` の `POST_LOGIN_REDIRECT_URL` で指定（現行: `/room-booking/room-booking.php`）
