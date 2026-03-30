# 京都医塾 会議室予約システム & ログインシステム 統合ガイド

このドキュメントは、プロジェクトの構成と現在までの変更内容をまとめたものです。

## 1. プロジェクト概要
- **デプロイ先URL**: `https://system.kyotoijuku.com/kiweb/`
- **サーバー環境**: お名前.com レンタルサーバー（共有サーバー）
- **主要な仕組み**: PHPベースのログインシステムと、HTML/JSベースの会議室予約システムの統合。

## 2. ディレクトリ構成
```text
/kiweb/
  ├── index.html            # ルートアクセス時のログイン画面へのリダイレクト用
  ├── login/                # ログインシステム本体
  │   ├── api/              # PHP API (login.php 等)
  │   ├── public/           # 公開ディレクトリ (login.php, bootstrap.php 等)
  │   └── src/              # バックエンドロジック (Auth, Model 等)
  ├── room-booking/     # 会議室予約システム
  │   └── room-booking.php         # 予約システム本体（ログイン必須化済み）
  └── nginx-kiweb.conf      # (参考用) nginx環境用の設定ファイル
```

## 3. 実施した主要な修正内容

### A. パスとリダイレクトの最適化
- サブディレクトリ `/kiweb/` での動作に合わせ、アセット（CSS/JS/画像）およびAPIの呼び出しパスをすべて `/kiweb/login/...` に統一しました。
- `bootstrap.php` 内の `requireAuth` ロジックを修正し、未ログイン時は `/kiweb/login/public/login.php` へリダイレクトするように設定。

### B. ID（ijuku）によるログインへの変更
- **APIの工夫**: `login/api/login.php` を修正。入力されたIDに `@` が含まれない場合、内部的に `@internal` というサフィックスを付けてDBと照合するようにしました。
- **UIの変更**: `login/public/login.php` のラベルを「メールアドレス」から「ID」に変更。
- **バリデーション緩和**: `login.js` でのメールアドレス形式チェックを削除。

### C. 会議室予約システムの見栄えと保護
- `room-booking/room-booking.php` に変換。
- 冒頭に以下のコードを追加し、ログインしていないユーザーをログインページへ強制リダイレクトするようにしました。
  ```php
  require_once __DIR__ . '/../login/public/bootstrap.php';
  requireAuth();
  ```
- ログイン画面の「次へ」ボタンを右寄せに配置。

### D. 講師名自動入力機能（2026-01-30追加）
ログインユーザーの氏名を各フォームの入力欄に自動的に埋め込む機能を実装しました。

- **対象ファイル**:
  - `kiweb2.html` - iframeのURLに `teacher` パラメータを付加
  - `class-declaration.html` - 講師名を自動入力し自動検索を実行
  - `work-record.html` - 氏名欄に自動入力（`name` または `teacher` パラメータ）
  - `class-reschedule.html` - 申請者名・講師名の両方に自動入力し自動検索

- **動作の流れ**:
  1. ユーザーがログイン後、`kiweb2.html` がAPIから氏名を取得（`.user-name` に表示）
  2. 各ページをiframeで表示する際に `?teacher=ユーザー名` を付加
  3. 各フォームがURLパラメータから値を取得し入力欄にセット

- **前提条件**: ユーザーDBの `name` カラムに講師のフルネームが正しく登録されていること

## 4. 共通アカウント情報
- **ID**: `ijuku`
- **Password**: `ijuku1111`
- **備考**: `ijuku@internal` というメールアドレスとしてDBに登録されています。

## 5. 今後の開発・保守時の注意点
1. **パスの追加**: 新しいAPIやアセットを追加する場合は、必ず `/kiweb/` プレフィックスを考慮したパス設定にする必要があります。
2. **キャッシュの注意**: CSSやJSの変更が反映されない場合、サーバー側のキャッシュやブラウザキャッシュをクリアしてください。
3. **セキュリティ**: 現在は共通IDでの入力を優先していますが、将来的に個別ユーザーを増やす場合は、`signup.php` や `forgot-password.php` のコメントアウトを解除してください。
4. **講師名自動入力**: 新しいフォームを追加する場合は、URLパラメータ `teacher` から氏名を読み取る処理を追加してください。

## 6. 主要ファイルの役割

| ファイル | 役割 |
|---------|------|
| `kiweb2.html` | 講師向けポータル画面（メニュー・iframe切り替え） |
| `class-declaration.html` | 授業予定の閲覧・実施申告 |
| `work-record.html` | 授業以外の業務実施申告 |
| `class-reschedule.html` | 欠勤届・振替申請 |
| `login/src/Model/User.php` | ユーザーモデル（`name` プロパティ含む） |
| `login/api/me.php` | ログイン中ユーザー情報取得API |

---
このガイドにより、次回以降のAIアシスタントや開発者がスムーズに作業を再開できます。
