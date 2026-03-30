# タスク：会議室予約システムに合格診断 TTE（面談表示API）連携を実装する

## 1. 概要

会議室予約システム（room-booking）に、合格診断 TimeTableEditor（TTE）の面談予定を表示する機能を追加する。
TTE の予約は「表示専用」で、本体の会議室予約と重複した場合は TTE を優先する。
重複した本体予約は「重複_部屋名」列に灰色で表示する。

---

## 2. TTE API 仕様

### 接続先
- **Base URL**: `https://integration-gateway-286380150747.asia-northeast1.run.app`
- **エンドポイント**: `GET /integrations/room-display/interviews`

### クエリパラメータ
| パラメータ | 型 | 必須 | 説明 |
|------------|----|------|------|
| from | YYYY-MM-DD | 必須 | 取得開始日 |
| to | YYYY-MM-DD | 必須 | 取得終了日 |

### 制約
- from <= to
- 最大5日間（inclusive）。6日以上は400エラー。

### 認証
- **Header**: `X-Integration-Key: <READ_KEY>`
- READ_KEY は環境変数または config で管理（ハードコード禁止）

### レスポンス例（HTTP 200）
```json
{
  "from": "2026-02-15",
  "to": "2026-02-16",
  "generated_at": "2026-02-16T16:44:21+09:00",
  "items": [
    {
      "booking_id": "tpm_event:UUID",
      "title": "加藤駿一｜入塾説明＋分析結果報告面談",
      "place_code": "INT_MAIN_5F_OSETSU_A",
      "place_name": "応接A",
      "start_at": "2026-02-15T12:50:00+09:00",
      "end_at": "2026-02-15T17:20:00+09:00",
      "student_name": "加藤駿一",
      "interviewer_names": ["曽根岡玲"]
    }
  ]
}
```

### エラー
- 400: 日付不正
- 401: キー未指定
- 403: キー不正
- 502: 内部APIエラー

---

## 3. place_code と会議室の対応

**重複対象は以下の5室のみ。** これらで TTE と本体予約が時間帯で重なった場合、TTE を本体列に表示し、本体予約を「重複_部屋名」列へ移動する。

| TTE place_code | roomId | roomName（会議室予約側） |
|----------------|--------|--------------------------|
| INT_MAIN_5F_SHITSUMU | 591 | 5階執務室 |
| INT_MAIN_5F_OSETSU_A | 592 | 5階応接A |
| INT_MAIN_2F_OSETSU_B | 294 | 2階応接B |
| INT_BLDG3_1F_3A | 301 | 3号館面3A |
| INT_BLDG3_1F_3B | 302 | 3号館面3B |

- place_code が上記マッピングにない TTE アイテムは表示しない。
- place_code が空のアイテムも表示しない。

---

## 4. 実装要件

### 4.1 バックエンド（PHP）

- **新規ファイル**: `room-booking/api/getTteInterviews.php`
- クエリ `from`, `to` を受け取り、TTE API を cURL で呼び出して JSON をそのまま返す。
- Integration Key は `api/config.local.php` または環境変数 `TTE_INTEGRATION_KEY` で管理。
- CORS 対策のため、ブラウザからはこの PHP を経由して TTE API を呼ぶ（Cloud Run への直接アクセスは避ける）。

### 4.2 フロントエンド（room-booking.php）

1. **TTE データ取得**
   - `loadTteInterviews()` を追加。
   - `getDisplayDates()` の日付範囲で TTE API（PHP プロキシ経由）を呼ぶ。
   - TTE API は最大5日制限のため、5日を超える場合は分割リクエスト。

2. **重複判定と表示**
   - 上記5室で、TTE と本体予約の時間帯が重なる場合：
     - 本体列 → TTE を表示（`source: 'tte'` で識別）
     - 重複列 → 本体予約を灰色で表示
   - 右端に5列追加：`重複_5階執務室`, `重複_5階応接A`, `重複_2階応接B`, `重複_3号館面3A`, `重複_3号館面3B`

3. **描画の拡張**
   - `renderRoomHeaders()` に重複列を追加。
   - `renderRoomColumns()` で TTE 優先表示と重複列表示を実装。
   - 重複列の予約ブロックは灰色スタイル（例: `res-displaced` クラス）。

4. **ポーリング**
   - 既存の `pollReservations()` に TTE 取得を組み込み、30秒ごとに本体予約と TTE の両方を更新する。
   - TTE はキャッシュしない（常に新規取得）。

5. **TTE 予約の扱い**
   - TTE の予約は read-only。詳細ポップアップで編集・削除ボタンを非表示にする。
   - クリック時は表示のみ（編集・削除不可であることを示す）。

### 4.3 設定

`api/config.local.php` に以下を追加（サンプル）:
```php
'tte' => [
    'api_base_url' => 'https://integration-gateway-286380150747.asia-northeast1.run.app',
    'integration_key' => getenv('TTE_INTEGRATION_KEY') ?: '',
],
```

Integration Key の実際の値は `.env` や環境変数で設定し、リポジトリにはコミットしない。

---

## 5. 既存コードの参照

- 部屋一覧・表示順: `ROOM_ORDER`, `rooms`, `renderRoomHeaders()`, `renderRoomColumns()`
- 予約取得: `loadReservationsOnly()`, `getReservationsForDate()`, `reservationsByDate`
- ポーリング: `pollReservations()`, `startPolling()`
- イベントレイアウト: `layoutRoomEvents()`, 予約ブロックの HTML 生成（`res-guest`, `res-no-guest` クラス）
- API 呼び出し: `apiGet()`, `./api/index.php` のパターン

---

## 6. 注意事項

- TTE API がエラーでも本体予約の表示は維持する（TTE 取得失敗時は本体のみ表示）。
- `interviewer_names` が空配列や未定義の場合もエラーにしない。
- `start_at` / `end_at` は ISO8601（JST）形式。`durationMinutes` は end - start から計算する。
- TTE の `title` を `meetingName`、`interviewer_names` を `reserverName`、`student_name` を `visitorName` として表示用にマッピングする。
