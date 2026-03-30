# 会議室予約システム API 仕様書

**Base URL**: `https://system.kyotoijuku.com/kiweb/room-booking/api/`

> [!NOTE]
> 開発環境: `https://system-dev.kyotoijuku.com/room-booking/api/`

---

## 共通仕様

### リクエスト形式
- **GET**: クエリパラメータで `action` を指定
- **POST**: JSON形式で `action` を含むボディを送信

### レスポンス形式
```json
{
  "success": true,
  "data": { /* 結果データ */ }
}
```

### エラーレスポンス
```json
{
  "success": false,
  "error": "エラーメッセージ",
  "status": 400
}
```

---

## エンドポイント一覧

| action | メソッド | 説明 |
|--------|----------|------|
| `getRooms` | GET | 会議室一覧取得 |
| `getReservations` | GET | 予約一覧取得 |
| `getReservation` | GET | 予約詳細取得 |
| `createReservation` | POST | 予約作成 |
| `createReservations` | POST | 複数予約一括作成 |
| `createRecurringReservations` | POST | 繰り返し予約作成 |
| `updateReservation` | POST | 予約更新 |
| `deleteReservation` | POST | 予約削除 |
| `processSheetsQueue` | GET | Sheets同期キュー処理 |
| `getSheetsSyncStatus` | GET | Sheets同期ステータス取得 |
| `dedupeReservationsSheet` | POST | Sheetsの重複削除 |
| `rebuildSheet` | POST | DB→Sheets完全同期 ⭐ |

---

## 1. 会議室一覧取得

```
GET /api/?action=getRooms
```

### レスポンス例
```json
{
  "success": true,
  "data": [
    { "roomId": 292, "roomName": "面談B", "capacity": 4, "equipment": "モニター" },
    { "roomId": 294, "roomName": "階応接A", "capacity": 6, "equipment": "" }
  ]
}
```

---

## 2. 予約一覧取得

```
GET /api/?action=getReservations&date=2026-01-20
```

| パラメータ | 必須 | 説明 |
|------------|------|------|
| `date` | ○ | 日付 (YYYY-MM-DD形式) |

### レスポンス例
```json
{
  "success": true,
  "data": [
    {
      "reservationId": "abc123-...",
      "roomId": 292,
      "roomName": "面談B",
      "date": "2026-01-20",
      "startTime": "13:30",
      "durationMinutes": 60,
      "meetingName": "ウィークリーカウンセリング",
      "reserverName": "池原",
      "visitorName": "",
      "createdAt": "2026-01-20 11:40:00",
      "updatedAt": "2026-01-20 11:40:00"
    }
  ]
}
```

---

## 3. 予約作成 ⭐

```
POST /api/
Content-Type: application/json
```

### リクエストボディ
```json
{
  "action": "createReservation",
  "data": {
    "roomId": 292,
    "date": "2026-01-20",
    "startTime": "14:00",
    "durationMinutes": 60,
    "meetingName": "打ち合わせ",
    "reserverName": "山田太郎",
    "visitorName": "株式会社ABC 佐藤様"
  }
}
```

### パラメータ詳細

| パラメータ | 必須 | 型 | 説明 |
|------------|------|-----|------|
| `roomId` | ○ | int | 会議室ID |
| `date` | ○ | string | 日付 (YYYY-MM-DD) |
| `startTime` | ○ | string | 開始時刻 (HH:MM) |
| `durationMinutes` | △ | int | 所要時間（分）。デフォルト60 |
| `meetingName` | ○ | string | 会議名 |
| `reserverName` | ○ | string | 予約者名 |
| `visitorName` | - | string | 来客名（任意） |

### 成功レスポンス
```json
{
  "success": true,
  "data": {
    "reservationId": "abc123-def456-...",
    "roomId": 292,
    "roomName": "面談B",
    "date": "2026-01-20",
    "startTime": "14:00",
    "durationMinutes": 60,
    "meetingName": "打ち合わせ",
    "reserverName": "山田太郎",
    "visitorName": "株式会社ABC 佐藤様",
    "createdAt": "2026-01-20 15:00:00",
    "updatedAt": "2026-01-20 15:00:00"
  }
}
```

### エラー例（競合時）
```json
{
  "success": false,
  "error": "すべての候補が競合しています。別の時間帯をお試しください。",
  "status": 409,
  "details": {
    "candidates": [
      {
        "candidate": { "roomId": 292, "date": "2026-01-20", "startTime": "14:00" },
        "reason": "conflict",
        "conflict": { "reservationId": "xxx", "meetingName": "既存の予約" }
      }
    ]
  }
}
```

---

## 4. 複数予約一括作成

```
POST /api/
```

### リクエストボディ
```json
{
  "action": "createReservations",
  "data": {
    "meetingName": "面接",
    "reserverName": "採用担当",
    "candidates": [
      { "roomId": 292, "date": "2026-01-20", "startTime": "10:00", "durationMinutes": 60 },
      { "roomId": 292, "date": "2026-01-20", "startTime": "11:00", "durationMinutes": 60 },
      { "roomId": 294, "date": "2026-01-20", "startTime": "14:00", "durationMinutes": 90 }
    ]
  }
}
```

---

## 5. 繰り返し予約作成

```
POST /api/
```

### リクエストボディ
```json
{
  "action": "createRecurringReservations",
  "data": {
    "roomId": 292,
    "date": "2026-01-20",
    "startTime": "10:00",
    "durationMinutes": 60,
    "meetingName": "週次MTG",
    "reserverName": "田中"
  },
  "recurrence": {
    "frequency": "weekly",
    "until": "2026-03-31"
  }
}
```

| recurrenceパラメータ | 説明 |
|----------------------|------|
| `frequency` | `daily` / `weekly` / `monthly` |
| `until` | 繰り返し終了日 (YYYY-MM-DD) |

---

## 6. 予約更新

```
POST /api/
```

### リクエストボディ
```json
{
  "action": "updateReservation",
  "id": "abc123-def456-...",
  "data": {
    "startTime": "15:00",
    "durationMinutes": 90,
    "meetingName": "更新後の会議名"
  }
}
```

---

## 7. 予約削除

```
POST /api/
```

### リクエストボディ
```json
{
  "action": "deleteReservation",
  "id": "abc123-def456-..."
}
```

### 繰り返し予約の削除オプション
```json
{
  "action": "deleteReservation",
  "id": "abc123-def456-...",
  "deleteMode": "all"
}
```

| deleteMode | 説明 |
|------------|------|
| `single` | この予約のみ削除（デフォルト） |
| `all` | 繰り返し予約すべてを削除 |
| `future` | この予約以降を削除 |

---

## cURL サンプル

### 予約作成
```bash
curl -X POST "https://system.kyotoijuku.com/kiweb/room-booking/api/" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "createReservation",
    "data": {
      "roomId": 292,
      "date": "2026-01-27",
      "startTime": "14:00",
      "durationMinutes": 60,
      "meetingName": "打ち合わせ",
      "reserverName": "山田太郎"
    }
  }'
```

### 予約一覧取得
```bash
curl "https://system.kyotoijuku.com/kiweb/room-booking/api/?action=getReservations&date=2026-01-27"
```

---

## GASからの利用例

```javascript
function createReservation() {
  const url = 'https://system.kyotoijuku.com/kiweb/room-booking/api/';
  const payload = {
    action: 'createReservation',
    data: {
      roomId: 292,
      date: '2026-01-20',
      startTime: '14:00',
      durationMinutes: 60,
      meetingName: '打ち合わせ',
      reserverName: '山田太郎',
      visitorName: ''
    }
  };
  
  const options = {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  };
  
  const response = UrlFetchApp.fetch(url, options);
  const result = JSON.parse(response.getContentText());
  
  if (result.success) {
    Logger.log('予約成功: ' + result.data.reservationId);
  } else {
    Logger.log('エラー: ' + result.error);
  }
}
```
