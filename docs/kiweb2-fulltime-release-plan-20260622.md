# ki web2社員版 リリース作業

作成日: 2026年6月22日

## 0. 現状とリリース方針

現在はテストサーバーにある実装が最新版である。本番サーバー全体をテスト版で上書きするのではなく、社員版のリリースに必要なファイルだけを選び、本番へ反映する。

最も安全な手順は次のとおり。

1. テストサーバーで社員、非常勤、管理者の動作を確認する。
2. 問題がなければ、対象ファイルをバックアップして本番サーバーへ反映する。
3. 本番サーバーでも同じ確認を行う。
4. 問題があれば、バックアップしたファイルへ戻す。

確認先:

- テストサーバー: <https://system-dev.kyotoijuku.com/kiweb/kiweb2.html>
- 本番サーバー: <https://system.kyotoijuku.com/kiweb/kiweb2.html>

`kiweb2.html` へのアクセスは `.htaccess` により `portal-guard.php` へ渡される。ログインユーザーのロールに応じて、社員には `kiweb2-fulltime.html`、管理者には `kiweb2-admin.html`、非常勤には `kiweb2.html` が返される。

本番サーバーには社員版を安全に確認できるテストアカウントがないため、本番反映前に `full_time_teacher` ロールを持つテストアカウントを用意する必要がある。確認後にアカウントを無効化または削除するか、継続利用する場合の管理方法も決めておく。

## 1. 今回リリースする機能

- 授業予定の閲覧・実施申告
- 欠勤・振替申請
- 会議室予約
- 出退勤記録・履歴閲覧
- 授業報告書の検索・閲覧
- 勤務記録の検索・閲覧
- 教室割サイネージ
- タスク通知

今回リリースしない機能:

- PDF資料一覧
- ICTマニュアル
- iframeアクセスログ

上記3機能は規模と確認範囲が大きいため、次の資料へ分けて整理する。

- [PDF資料一覧・資料配信 リリース作業](./kiweb-pdf-materials-release-plan-20260622.md)
- [ICTマニュアル リリース作業](./kiweb-ict-manual-release-plan-20260622.md)
- [iframeアクセスログ リリース作業](./kiweb-iframe-access-log-release-plan-20260622.md)

## 2. リリースまでに必要な作業

### 0. 本番確認用の社員テストアカウントを用意する

必要な状態:

- `full_time_teacher` ロールが設定されている
- 実在する社員のアカウントを借りずに確認できる
- 本番GAS、Slack、メールへ意図しない送信を行わない運用にする
- 確認後の無効化、削除または継続管理の担当を決める

### 0-1. 本番データベースのロールを確認する

今回の社員版リリースでは、新しいテーブルやカラムの追加は不要である。出退勤履歴と検索結果はGASから取得しており、今回更新する検索APIもkiwebのデータベースへ記録を書き込まない。

ただし、社員版の振り分けと他講師の検索許可には、データベース上の `full_time_teacher` ロールを使用する。本番反映前に次を確認する。

- `roles` テーブルに `full_time_teacher` が存在する
- 本番確認用ユーザーが `users` テーブルに存在し、有効状態になっている
- `user_roles` で確認用ユーザーに `full_time_teacher` が割り当てられている
- 確認用ユーザーに `admin` ロールが同時に割り当てられていない

確認用SQLの例:

```sql
SELECT id, name, description
FROM roles
WHERE name IN ('full_time_teacher', 'admin');

SELECT
    u.id,
    u.email,
    u.name,
    u.status,
    GROUP_CONCAT(r.name ORDER BY r.name) AS roles
FROM users u
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN roles r ON r.id = ur.role_id
WHERE u.email = '確認用アカウントのメールアドレス'
GROUP BY u.id, u.email, u.name, u.status;
```

`migration_roles_employment.sql` は `full_time_teacher` 等の作成だけでなく、旧 `teacher`、`student` ロールの付け替えと削除も行う。ロールの存在確認だけを目的として、本番DBへ安易に再実行しない。

### 0-2. ログイン後の導線を確認する

本番版とテスト版で、社員版へ移動する導線の機能的な違いはない。今回のリリースで `.htaccess` や `portal-guard.php` を変更・反映する必要はない。

現在の導線:

1. 利用者は `/kiweb/kiweb2.html` へアクセスする。
2. `.htaccess` が `teacher-auth/public/portal-guard.php` を実行する。
3. `portal-guard.php` がログインユーザーのロールを確認する。
4. `admin` なら `kiweb2-admin.html`、`full_time_teacher` なら `kiweb2-fulltime.html`、非常勤等なら `kiweb2.html` を返す。

`/kiweb/kiweb2-fulltime.html` や `/kiweb/kiweb2-admin.html` へ直接アクセスした場合も、`.htaccess` により同じ `portal-guard.php` を通る。そのため、URLを直接入力して社員版や管理者版の権限確認を回避することはできない。

本番版との実ファイル差分:

- `.htaccess`
  - テスト版には `teacher-auth/ict-manual/` への直接アクセスを禁止する設定が1行追加されている。
  - ICTマニュアルは今回リリースしないため、この差分は今回の本番反映対象にしない。
- `teacher-auth/public/portal-guard.php`
  - テスト版で説明コメントが追加されているだけで、ポータルの振り分け処理は本番版と同じ。
- `teacher-auth/public/index.php`
- `teacher-auth/public/login.php`
- `teacher-auth/public/verify-code.php`
- `teacher-auth/public/bootstrap.php`
  - 上記4ファイルは本番版とテスト版で同一。

### 1. 社員ポータルをリリース用に修正する

対象:

```text
kiweb2-fulltime.html
```

削除する:

- PC版の「資料一覧」
- PC版の「ICTマニュアル」
- モバイル版の「資料一覧」
- モバイル版の「ICTマニュアル」
- `PAGES` の `pdf-viewer`
- `PAGES` の `ict-manual`
- iframeアクセスログの送信処理

iframeアクセスログは今回のリリース対象外だが、送信処理を残してもポータルの主要機能には直接影響しない。削除する場合は、今回の本番反映ファイルとアクセスログAPI・DBの状態が食い違わないようにする。

残す:

- 教室割サイネージ
- タスク通知
- その他の社員向け業務メニュー

### 2. 出退勤画面を修正する

対象:

```text
check_in_out.html
```

社員版専用の出退勤HTMLがあるわけではなく、通常版、社員版、管理者版の3つのポータルが、同じ `check_in_out.html` を参照している。ポータルごとにURLパラメータを変え、表示テーマと講師名の変更可否を切り替えている。

参照元:

| 利用者 | 参照元 | iframeで開く画面 |
|---|---|---|
| 非常勤 | `kiweb2.html` | `check_in_out.html?teacher=ログイン名&theme=parttime` |
| 社員 | `kiweb2-fulltime.html` | `/kiweb/check_in_out.html?theme=fulltime&teacher=ログイン名` |
| 管理者 | `kiweb2-admin.html` | `check_in_out.html?teacher=ログイン名&allowNameEdit=1&theme=admin` |

本番版とテスト版の違い:

| 項目 | 本番版 | テスト版 |
|---|---|---|
| 社員向け青系テーマ | `theme=fulltime` 用のCSSがあり、URLのthemeを画面へ適用する | 青系テーマのCSSとthemeの適用処理が不足している |
| ログインユーザーのロール取得 | ロールを取得していない | `me.php` からロールを取得する |
| 社員による講師名変更 | できない | `full_time_teacher` の場合にできる |
| 管理者による講師名変更 | `manage_users` 権限と `allowNameEdit=1` があればできる | 同じ条件でできる |
| 非常勤による講師名変更 | できない | できない |
| 出退勤履歴の取得先と表示処理 | テスト版と同じ | 本番版と同じ |

修正内容:

- 本番版から、社員向け青系テーマのCSSと `theme=fulltime` を画面へ適用する処理を取り込む。
- テスト版にある、`me.php` からロールを取得して社員の講師名変更を許可する処理は残す。
- URLに `theme=fulltime` を手入力しただけでは変更できないよう、`full_time_teacher` ロールとの組み合わせで判定する。

修正後に確認する権限:

- 社員: 講師名を変更できる
- 管理者: 講師名を変更できる
- 非常勤: 講師名を変更できない

### 3. 社員が検索対象の講師を変更できるようにする

ここでいう「講師名変更」は、ログインユーザーの氏名や講師情報を変更することではない。検索画面の講師名欄を変更し、その講師の授業報告書または勤務記録を検索することを指す。

例えば、社員Aがログインした状態で講師名欄を「講師B」に変更すると、講師Bの記録が検索結果へ表示される。社員Aのログイン情報や登録氏名は変更しない。

講師名欄の変更権限と検索機能には、次のつながりがある。

1. HTML画面が、ログインユーザーのロール・権限を確認する。
2. 社員または管理者の場合だけ、講師名欄を編集可能にする。
3. 検索時に、入力された講師名を検索対象としてPHP APIへ送る。
4. PHP APIでも、社員または管理者が他講師を検索できるか再確認する。
5. 権限があれば指定された講師を検索し、権限がなければログイン本人の名前へ固定する。

したがって、画面側だけで講師名欄を編集可能にしても、API側が指定された講師名を許可しなければ他講師の検索はできない。反対に、API側だけで許可しても、画面の講師名欄が読み取り専用のままでは利用者が検索対象を変更できない。

対象:

```text
ClassReportSearchForm.html
declaration_of_non_class_work_search_and_view.html
teacher-auth/api/class-report-search.php
teacher-auth/api/work-record-search.php
```

4ファイルとも本番サーバーのファイル一式に存在するため、新規ファイルの追加ではなく、テスト版の内容で本番版を更新する作業になる。

本番版とテスト版の違い:

| ファイル | 本番版の状態 | テスト版で追加・変更されている内容 |
|---|---|---|
| `ClassReportSearchForm.html` | 講師名を変更できるのは、`allowNameEdit=1` が指定され、かつ `manage_users` 権限を持つ管理者のみ | `theme=fulltime` かつ `full_time_teacher` ロールの場合も講師名を変更できる |
| `declaration_of_non_class_work_search_and_view.html` | 管理者または社員で講師名を変更できる処理はあるが、判定条件がポータルの表示モードと明確に分離されていない。また、変更した名前を `sessionStorage` へ保存する | 社員モードと管理者モードを分けてロール・権限を確認する。変更した検索対象名をログイン本人名として保存しない |
| `teacher-auth/api/class-report-search.php` | 他講師を検索できるのは `manage_users` 権限を持つ管理者のみ | `full_time_teacher` ロールの社員にも他講師の検索を許可する。非常勤はリクエストされた名前を使わず、ログイン本人名へ固定する |
| `teacher-auth/api/work-record-search.php` | 管理者または `full_time_teacher` の社員が他講師を検索できる処理はすでにある | 同じ権限制御を整理し、認証中ユーザーと検索対象講師を明確に分ける |

重要な点:

- 授業報告書検索は、画面とAPIの両方を更新しないと社員による講師名変更が動作しない。
- 勤務記録検索は、本番APIにも社員の代理検索処理がある。主な修正対象は画面側の名前の扱いと、API側の処理の明確化である。
- HTMLで入力欄を編集可能にするだけでは権限保護にならないため、PHP API側のロール・権限判定も必ず本番へ反映する。

必要な状態:

- 社員が別講師名で検索できる
- 指定した講師の結果が表示される
- 非常勤は別講師を検索できない
- 検索対象講師名でログイン本人名を上書きしない
- 講師名が空の場合は検索を実行しない

## 3. テストサーバー確認表

### ログイン

- [ ] 社員は社員版へ進む
- [ ] 非常勤は通常版へ進む
- [ ] 管理者は管理者版へ進む
- [ ] 未ログインではログイン画面へ進む

### 社員ポータル

- [ ] 資料一覧が表示されない
- [ ] ICTマニュアルが表示されない
- [ ] iframeアクセスログを送信していない
- [ ] PC版とモバイル版のメニューが一致する
- [ ] 各メニューが正しい画面を開く

### 講師名変更

- [ ] 社員は別講師へ変更できる
- [ ] 指定した講師の結果が表示される
- [ ] 非常勤は別講師へ変更できない
- [ ] 欠勤振替の申請者名はログイン社員名で固定される
- [ ] タスク通知の対象者はログイン社員本人のまま

### 各機能

- [ ] 授業予定を取得できる
- [ ] 実施申告を送信できる
- [ ] 授業報告書画面へ進める
- [ ] 授業報告書の項目が自動入力される
- [ ] 欠勤振替の予定を取得できる
- [ ] 出退勤画面が青系テーマで表示される
- [ ] 授業報告を検索できる
- [ ] 勤務記録を検索できる
- [ ] 会議室予約を利用できる
- [ ] SK、EM、ブースのサイネージを表示できる
- [ ] タスク通知とタスク一覧を表示できる

### エラー

- [ ] 本番GASやSlackへテスト送信していない
- [ ] ブラウザにJavaScriptエラーがない
- [ ] 想定外の404、401、403、500がない
- [ ] `iframe-access-log.php` への通信がない

## 4. 本番へ反映するファイル

予定:

```text
kiweb2-fulltime.html
check_in_out.html
ClassReportSearchForm.html
declaration_of_non_class_work_search_and_view.html
teacher-auth/api/class-report-search.php
teacher-auth/api/work-record-search.php
lesson-report-form.html
```

タスク通知ファイルに変更がある場合:

```text
kiweb-task-delivery.js
```

## 5. 本番リリース手順

1. 本番確認用の社員テストアカウントを用意する。
2. テストサーバーで確認表の項目を確認する。
3. 本番の変更対象ファイルをバックアップする。
4. stagingと本番の最終差分を確認する。
5. `lesson-report-form.html` を反映する。
6. PHPの検索APIを反映する。
7. 各業務画面のHTMLを反映する。
8. 必要な場合は `kiweb-task-delivery.js` を反映する。
9. 最後に `kiweb2-fulltime.html` を反映する。
10. ブラウザとiframeをハードリロードする。
11. 社員、非常勤、管理者でログイン確認する。

## 6. 本番リリース直後の確認

- [ ] 社員が社員版へ入れる
- [ ] 非常勤と管理者の画面に影響がない
- [ ] 対象外メニューが表示されない
- [ ] 社員だけが講師名を変更できる
- [ ] 出退勤画面が青系テーマで表示される
- [ ] 各業務画面を開ける
- [ ] タスク通知が社員本人を対象に表示される
- [ ] 404、500、JavaScriptエラーがない
- [ ] GAS、Slack、メールの異常送信がない

## 7. 注意点

### 本番確認用の社員テストアカウント

社員版の確認には `full_time_teacher` ロールを持つアカウントを使う。`portal-guard.php` は「管理者、社員、通常」の順でポータルを選ぶため、`admin` ロールも同時に設定すると管理者版が表示され、社員版の確認にならない。

テストアカウントには実在する社員や講師と混同しない名前を設定し、GAS、Slack、メール等の外部連携へ送信しない。確認後の無効化または削除も忘れない。

### データベースマイグレーション

今回リリースしないiframeアクセスログ用の `migration_add_access_iframes_logs.sql` は、本リリースのためには適用しない。社員版の主要機能、出退勤画面、授業報告書検索、勤務記録検索にはこのカラム追加は不要である。

### `.htaccess` とログイン導線

今回のリリースでは `.htaccess`、`portal-guard.php`、ログイン関連PHPを本番へ上書きしない。社員版の表示に必要なロール振り分けは本番版にもすでに存在する。

### 本番GASへの送信

授業予定、欠勤振替、授業報告書等は本番GASへ接続する。テスト送信方法が決まるまでは送信ボタンを操作しない。

### 授業報告書のリンク

社員用授業予定画面は本番URLを直接指定している。テスト中は必ずテスト用URLへ変更する。

### 講師名

ログイン本人名と検索対象講師名を混同しない。検索対象を変更しても、タスク通知はログイン本人を対象にする。

### キャッシュ

更新したiframe URLのバージョンクエリを変更し、PC、スマートフォン、iPadでハードリロードする。

### Git

`.env`、本番PDF、秘密情報、個人情報、バックアップをコミットしない。変更ファイルだけを明示して扱う。

## 8. 問題発生時

最初に `kiweb2-fulltime.html` を変更前へ戻し、問題のある導線を停止する。

その後、変更したAPIと各業務画面を変更前へ戻す。

ロールバック対象:

```text
kiweb2-fulltime.html
check_in_out.html
ClassReportSearchForm.html
declaration_of_non_class_work_search_and_view.html
teacher-auth/api/class-report-search.php
teacher-auth/api/work-record-search.php
lesson-report-form.html
```
