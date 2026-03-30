# 動作確認チェックリスト（teacher-auth）

## 環境/設定
- [ ] `teacher-auth/.env` に本番DB情報・`PEPPER`・`SESSION_NAME`・`SESSION_COOKIE_PATH` を設定
- [ ] `.env` がWebから参照できない（`/kiweb/teacher-auth/.env` で403）

## ログイン/登録
- [ ] `/kiweb/teacher-auth/public/login.php` が表示される
- [ ] ログインできる（メール+パスワード）
- [ ] ログアウトでセッションが破棄される
- [ ] パスワード再設定が動作する

## kiweb2 連携
- [ ] `kiweb2.html` の「ログイン」からモーダルが開く
- [ ] `kiweb2.html?name=山田太郎` でモーダルを開くと、登録画面の「お名前」が自動入力済み
- [ ] 登録時に `users.name` に保存される

## セッション分離
- [ ] 既存 `login` とセッション名が衝突しない
- [ ] Cookie Path が `/kiweb/teacher-auth/` で分離されている
