# User Sheet Mirror

When `USER_SHEET_WEBHOOK_URL` is set, the app sends a `POST` request whenever a new user is created.

## Request

- Method: `POST`
- Content-Type: `application/json; charset=utf-8`
- Optional header: `X-User-Sheet-Secret`

## Payload

```json
{
  "event": "user_created",
  "occurred_at": "2026-03-23T12:34:56+09:00",
  "user": {
    "id": "uuid",
    "name": "Taro Yamada",
    "email": "taro@example.com",
    "status": "active",
    "email_verified_at": null,
    "created_at": "2026-03-23 12:34:56",
    "updated_at": "2026-03-23 12:34:56",
    "roles": ["part_time_teacher"],
    "scopes": {
      "campus": ["四条烏丸校"],
      "department": ["教務"]
    }
  }
}
```

## Notes

- If `USER_SHEET_WEBHOOK_URL` is empty, no request is sent.
- Mirror failures are logged with `error_log`, but user signup/admin creation continues.
- Current behavior mirrors user creation only. If you want update/delete sync too, extend the same service.

## Backfill existing users

You can backfill current users with this internal endpoint:

```text
POST /kiweb/teacher-auth/api/internal/backfill-users-to-sheet.php
Authorization: Bearer <USER_SHEET_BACKFILL_TOKEN>
```

It reuses the same webhook and sends all current DB users one by one.

You can also pass the token as a query parameter if your client cannot send the
`Authorization` header reliably:

```text
POST /kiweb/teacher-auth/api/internal/backfill-users-to-sheet.php?token=<USER_SHEET_BACKFILL_TOKEN>
```
