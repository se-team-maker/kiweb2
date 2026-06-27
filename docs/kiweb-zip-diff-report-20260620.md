# 本番サーバーの kiweb.zip と staging の差分調査

## 調査概要

- 調査日: 2026年6月20日
- テストサーバー同期元: `/var/www/html/kiweb` の `staging` 相当
- 本番サーバーファイル: `/mnt/c/Users/fukuyama247/Downloads/kiweb.zip`
- 本番ZIP更新日時: 2026年6月20日15時48分
- 比較方向: 本番サーバーに存在し、stagingへ戻されていない変更・機能
- 調査方法: ZIPを一時領域へ展開し、ファイル構成と主要コードを読み取り専用で比較

`staging` はテストサーバーと同期しており、ZIPは本番サーバーから取得したファイル一式として扱う。

リポジトリ、ZIPのどちらにも変更を加えずに調査した。

## 結論

本番ZIPにはstagingへ戻されていない機能が複数含まれている。一方、stagingにもICTマニュアルやiframeアクセスログAPIなど、本番ZIPにない機能がある。

本番ZIPには実運用中の変更だけでなく、stagingより古い実装、検証用ファイル、秘密情報を含む設定ファイルも混在している。

そのため、本番ZIPをそのままstagingへ上書きするのではなく、必要な機能だけを現在のstaging実装へ個別に移植する必要がある。移植後はテストサーバーで確認し、改めて本番へ反映する。

## 本番にあり、stagingへ未反映の可能性が高い機能

### 1. 授業報告書入力画面

ZIPには次のファイルが存在する。

```text
lesson-report-form.html
```

staging側のリポジトリにはこのファイルが存在しない。

一方、現行の次のファイルからは `lesson-report-form.html` が参照されている。

```text
class-declaration.html
class-declaration-fulltime.html
```

該当する導線を利用した場合、現行環境では404になる可能性がある。

### 2. 非常勤向け欠勤・振替申請 ver2.2

ZIPの `class-reschedule-parttime.html` には、現行にない次の機能が含まれている。

- 緊急欠勤時の電話連絡モーダル
- 四条烏丸校への電話リンク
- 円町校への電話リンク
- 緊急欠勤時に「振替申請」を選択できなくする処理
- 振替先の日付を明日以降に制限する処理
- 振替設定禁止日時を外部から取得する処理
- 選択した日時と設定禁止日時の重複チェック
- 過去日への変更を防止する処理

外部GASへの接続が含まれるため、移植する場合は接続先とレスポンス仕様の確認が必要。

### 3. 非常勤向け実施申告の「Dその他」

ZIPの `class-declaration.html` には次の選択肢がある。

```text
Dその他_特別な実施
└ 高卒本科_欠席フォロー
```

主な処理は次のとおり。

- `Dその他_特別な実施` のラジオボタン
- `高卒本科_欠席フォロー` の詳細選択
- 詳細を選択していない場合の送信防止
- 送信値への詳細情報の付加

専任用の現行 `class-declaration-fulltime.html` には実装済みだが、非常勤用の現行 `class-declaration.html` には反映されていない。

### 4. メール認証状態のスプレッドシート同期

ZIPではメール認証完了後に、認証済みとなったユーザー情報をスプレッドシートへ再同期する。

関連ファイル:

```text
teacher-auth/api/verify-email.php
teacher-auth/src/Service/UserSpreadsheetMirror.php
```

ZIP側の追加内容:

- メール認証完了後にユーザー情報を再取得
- `UserSpreadsheetMirror::mirrorUpdatedUser()` を実行
- Webhookへ `user_updated` イベントとして送信

現行は主にユーザー作成時の `user_created` 同期であり、メール認証済み状態の更新同期は含まれていない。

### 5. 勤務記録の依頼部署選択肢

ZIPの `work-record.html` には次の変更がある。

- `現役本部-京大前校` の追加
- 各校舎を別々の値として送信

ZIP側:

```text
現役本部-四条烏丸校
現役本部-円町校
現役本部-京大前校
現役本部-オンライン校
```

現行では、複数の選択肢がすべて次の値になっている。

```html
value="現役本部"
```

表示名は異なっても、送信先では校舎を区別できない可能性がある。

### 6. 管理者ポータルのタスク通知

ZIPの `kiweb2-admin.html` には、現行管理者画面にないタスク通知機能が含まれている。

- 未完了タスク通知バー
- 深刻な遅延、締切超過、締切近しの件数表示
- 深刻な遅延、締切超過がある場合の警告モーダル
- タスク一覧画面への遷移
- iframe内で変更した講師名の検出
- 変更した講師名とタスク対象者の同期
- `postMessage` を使った講師名変更通知への対応

通常版と専任版の現行ポータルでは `kiweb-task-delivery.js` によるタスク通知が動作するが、現行の管理者版には同等の読み込みや初期化がない。

ただしZIPの管理者画面には、現行側のPDF、サイネージ、ICTマニュアル、iframeアクセスログなどが欠けているため、ファイル全体の置き換えはできない。

### 7. タスク一覧の表示改善

ZIPの `delivered-task-list.html` には、現行の `task-delivery-manager.html` にない次の改善がある。

- 「締切近し」のタスクを薄い黄色の背景で表示
- 未完了タスクと完了済みタスクの間に区切り線を表示
- 「完了済みタスク」の見出しを表示
- スマートフォンでカードを一列表示に変更
- 長いタスク名や説明の折り返し改善
- 操作ボタンのモバイル配置改善
- 依頼シリーズ名の色を強調

### 8. 出退勤記録画面の専任テーマ

ZIPの `check_in_out.html` には、`theme=fulltime` の場合に青系の専任テーマを適用するCSSがある。

主な変更:

- 専任用のプライマリーカラー
- 背景色、カード色、枠線色の変更
- URLの `theme` パラメータを `body` の `data-theme` に反映

現行 `check_in_out.html` には、この専任テーマ用スタイルがない。

### 9. 講師名変更時のセッション内同期

ZIPの次の画面では、変更した講師名を `sessionStorage` に保存する。

```text
work-record.html
declaration_of_non_class_work_search_and_view.html
```

保存先:

```text
kiweb_user_name
```

入力欄の `input` または `change` イベントで、変更後の講師名をポータル側と共有できるようにしている。

現行には、この保存処理がない。

## ZIPにだけ存在する独立ページ

次のファイルはZIPに存在するが、現行メニューや主要導線への接続は確認できなかった。

### 遅延実施申告

```text
delayed-declaration.html
```

授業予定を取得し、遅延した実施申告を行う画面と思われる。GASへの接続を含む。

### 集団授業報告書

```text
group-lesson-report-form.html
```

集団授業用の報告書入力画面。専用GASへの送信処理を含む。

### 研修資料一覧

```text
kenshu.html
```

研修資料を種類、対象者、部署などで絞り込む画面。ただし、登録内容にダミーURLやサンプルデータが含まれており、モック画面の可能性が高い。

### 講師情報検索サンプル

```text
justdb_teacher_sample.html
```

JustDB連携ブリッジを使った講師情報検索サンプル。

### その他の検証・旧版

```text
ClassReportSearchForm-test.html
check_in_out-test.html
class-declaration-fulltime-test.html
declaration_of_non_class_work_search_and_view-test.html
class-reschedule_v260401.html
class-reschedule-parttime-justdb.html
kiweb2_taskbar.html
kiweb2_with_task_notifications.html
```

これらはテスト版、検証版、過去版の可能性が高く、そのまま本番へ追加する対象ではない。

## 現行のほうが進んでいる機能

ZIP側のファイルをそのまま置き換えると、次の現行機能が失われる、または後退する可能性がある。

- ICTマニュアル
- ICTマニュアル外部アップロード
- iframe単位のアクセスログ
- アクセスログの種別、メニュー名、ページキー管理
- サイネージ表示
- PDF資料一覧
- PDF資料配信管理
- 非常勤、専任、管理者の最新権限制御
- 専任講師による講師名変更
- タスク配信処理の共通JS化
- 現行の認証、セッション、Cookie制御

## 取り込み非推奨・要注意ファイル

ZIPには秘密情報や本番接続情報を含む可能性のあるファイルが含まれている。

例:

```text
.env
room-booking/api/config.local.php
room-booking/api/service-account.json
apis/justdb_teacher_bridge.php
gas/kiweb2-アカウント認証/code.gs
```

また、次のような配備物や管理対象外ファイルも含まれている。

```text
private-pdfs配下のPDF
BUs配下のバックアップ
backups配下のファイル
vendor配下の依存ライブラリ
*.bak
*.save
Zone.Identifier
```

これらを現行へ無条件にコピーしたり、Gitへコミットしたりしてはいけない。

特にGASファイルには、スプレッドシートID、Webhook、トークン、Slack通知などの外部連携情報が含まれる可能性がある。

## 推奨する確認・移植順

### 優先度: 高

1. `lesson-report-form.html` の欠落確認
2. 非常勤用 `class-declaration.html` の「Dその他」対応
3. `work-record.html` の校舎別送信値修正
4. メール認証後のスプレッドシート同期

### 優先度: 中

1. 欠勤・振替申請 ver2.2 の入力制御
2. 管理者ポータルのタスク通知
3. タスク一覧の表示改善
4. 講師名変更時の `sessionStorage` 同期

### 優先度: 要件確認

1. 遅延実施申告
2. 集団授業報告書
3. 研修資料一覧
4. JustDB講師検索

## 移植前の注意点

- ZIPからファイル全体を上書きしない。
- 現行の認証、権限、iframeアクセスログ、ICTマニュアルを残す。
- PHP 7系との互換性を維持する。
- 非常勤、専任、管理者の権限差を維持する。
- GAS、Slack、メール、DBへの意図しない書き込みを発生させない。
- 外部URLやGASの接続先が現在も有効か、実装前に確認する。
- `.env` や認証情報を出力、コピー、コミットしない。
- 取り込みは機能単位の小さい差分として行う。

## 調査時のリポジトリ状態

調査開始時点で、staging側の作業リポジトリには既存の未コミット変更と未追跡ファイルが存在していた。

これらは利用者の作業として保持し、調査では変更していない。

調査後も、staging側の作業リポジトリの状態が調査前から変化していないことを確認した。
