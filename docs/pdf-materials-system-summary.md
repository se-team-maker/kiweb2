資料配信管理システム 内容まとめ


1. システムの目的

資料配信管理システムは、kiweb / kiweb2 上でPDF資料を配信し、必要に応じて利用者ごとの確認済み記録を残すための機能です。

管理者がPDF資料を登録し、利用者は自分に配信されている資料だけを資料一覧から確認できます。

確認が必要な資料では、PDFビューア上に「確かに見ました」ボタンが表示されます。利用者がボタンを押すと、確認済み記録がDBに保存されます。


2. 主な機能

資料配信管理システムには、主に以下の機能があります。

・管理者によるPDF資料登録
・資料タイトルの設定
・配信対象の設定
・確認ボタン表示有無の設定
・資料の公開 / 非公開切り替え
・利用者向け資料一覧表示
・PDFビューア表示
・PDF実ファイル配信
・確認済み記録の保存
・資料ごとの確認済み件数表示


3. 利用者側の流れ

利用者は、kiweb / kiweb2 のサイドバーから「資料一覧」を開きます。

資料一覧では、ログイン中ユーザーのロールに応じて、そのユーザーが閲覧できる資料だけが表示されます。

表示される資料は、公開中であり、かつ配信対象に含まれている資料のみです。

資料をクリックすると、PDFビューアが開きます。

PDFビューアでは、PDFのページ送り、拡大・縮小、幅に合わせる、全体表示などができます。

確認が必要な資料の場合は、PDFビューア上に「確かに見ました」ボタンが表示されます。


4. 確認済み記録の流れ

確認が必要な資料で「確かに見ました」ボタンを押すと、確認済み記録APIにPOSTされます。

確認済み記録APIでは、以下を確認します。

・ログイン済みか
・有効なユーザーか
・資料IDが正しいか
・資料が公開中か
・ログインユーザーが配信対象に含まれているか
・その資料が確認記録対象か

問題がなければ、pdf_acknowledgements テーブルに確認日時、ユーザーID、資料ID、IPアドレス、User-Agent を保存します。

同じユーザーが同じ資料に対して複数回ボタンを押した場合は、確認記録を重複登録せず、既存の記録を更新します。


5. 管理者側の流れ

管理者は、資料配信管理画面からPDF資料を登録・管理します。

資料登録時に設定する内容は以下です。

・資料タイトル
・配信対象
・確認ボタンを表示するかどうか
・PDFファイル

登録されたPDFファイルは、teacher-auth/private-pdfs/ に保存されます。

PDFの元ファイル名は記録として残しますが、実際の保存ファイル名はシステム側で自動生成されます。

管理者は、登録済み資料について以下を確認・操作できます。

・資料タイトル
・保存ファイル名
・元ファイル名
・配信対象
・確認ボタン有無
・公開状態
・確認済み件数
・登録日
・PDFを開く
・公開 / 非公開を切り替える


6. 配信対象

資料には配信対象を設定できます。

現在の配信対象は以下の3種類です。

all

全員向けの資料です。

parttime

非常勤向けの資料です。

fulltime

専任・社員向けの資料です。

ログインユーザーのロールを確認し、そのユーザーが該当する配信対象の資料だけを表示します。

管理者は、すべての配信対象の資料を確認できます。


7. 管理者判定

資料配信管理では、以下のいずれかに該当するユーザーを管理者として扱います。

・manage_users 権限を持つ
・manage_pdf_documents 権限を持つ
・admin ロールを持つ
・administrator ロールを持つ

管理者だけが、資料配信管理画面を開いたり、PDF登録・公開切替などの管理操作を実行できます。


8. PDF保存と配信の考え方

PDF実ファイルは、ブラウザから直接アクセスさせるのではなく、以下のフォルダに保存します。

teacher-auth/private-pdfs/

PDFを表示するときは、pdf-file.php 経由で配信します。

pdf-file.php では、PDFを返す前に以下を確認します。

・ログイン済みか
・有効なユーザーか
・資料IDが正しいか
・資料が公開中か
・ログインユーザーが配信対象に含まれているか
・DB上のファイル名が安全な形式か
・実ファイルが private-pdfs 配下に存在するか
・realpath 確認で private-pdfs の外に出ていないか

これにより、PDFファイルを直接公開せず、PHP側で認証・権限確認を行った上で配信します。


9. 関係する主なファイル

利用者側

teacher-auth/public/pdf-list.php

資料一覧を表示します。

ログインユーザーのロールを確認し、公開中かつ配信対象内の資料だけを取得します。

pdf_acknowledgements と結合し、確認済み状態も表示します。


teacher-auth/public/pdf-viewer.php

PDFビューア画面です。

資料IDを受け取り、公開状態・配信対象・確認済み状態を確認した上でPDFを表示します。

確認が必要な資料では「確かに見ました」ボタンを表示します。


teacher-auth/public/pdf-file.php

PDF実ファイルを配信するエンドポイントです。

PDFを返す直前にも、ログイン状態、公開状態、配信対象、ファイルパスの安全性を確認します。


teacher-auth/public/pdf-ack.php

確認済み記録を保存するAPIです。

PDFビューアの「確かに見ました」ボタンからPOSTされます。


管理者側

teacher-auth/public/pdf-admin.php

資料配信管理画面です。

PDF登録フォーム、登録済み資料一覧、確認済み件数、公開状態切替ボタンを表示します。


teacher-auth/public/pdf-admin-action.php

資料配信管理画面からのPOST処理を担当します。

PDF登録、PDFファイル保存、pdf_documents への登録、公開 / 非公開切替を行います。


DB

teacher-auth/database/pdf_documents.sql

pdf_documents テーブル作成SQLです。


teacher-auth/database/pdf_acknowledgements.sql

pdf_acknowledgements テーブル作成SQLです。


保存先

teacher-auth/private-pdfs/

PDF実ファイルの保存先です。


10. DBテーブル

pdf_documents

PDF資料の管理情報を保存するテーブルです。

主なカラムは以下です。

・id
・title
・file_name
・original_name
・target_scope
・requires_ack
・is_active
・uploaded_by
・uploaded_at
・updated_by
・updated_at

target_scope には、all / parttime / fulltime のいずれかが入ります。

requires_ack が 1 の場合、その資料は確認ボタン表示対象です。

is_active が 1 の場合、公開中として扱われます。


pdf_acknowledgements

利用者ごとの確認済み記録を保存するテーブルです。

主なカラムは以下です。

・id
・document_id
・user_id
・acknowledged_at
・ip_address
・user_agent

document_id と user_id の組み合わせはユニークです。

これにより、同じユーザーが同じ資料に対して確認記録を重複登録しないようになっています。


11. SQLの流し込み順

資料配信用テーブルのSQLは、以下の順で流し込みます。

1. teacher-auth/database/pdf_documents.sql
2. teacher-auth/database/pdf_acknowledgements.sql

pdf_acknowledgements は pdf_documents.id を外部キー参照しているため、pdf_documents.sql を先に流す必要があります。

また、現在のSQLは CREATE TABLE IF NOT EXISTS ではありません。

そのため、すでに同名テーブルが存在するDBに再実行するとエラーになります。


12. セキュリティ上のポイント

資料配信管理では、以下の点で認証・権限確認を行っています。

・未ログインでは資料一覧を見られない
・未ログインではPDF本体を取得できない
・配信対象外の資料は一覧に表示されない
・配信対象外の資料IDを直接指定しても表示できない
・非公開資料のIDを直接指定しても表示できない
・管理者以外は資料配信管理画面を開けない
・管理者以外はPDF登録や公開切替を実行できない
・管理POST処理ではCSRFトークンを確認する
・PDF以外のファイル登録を防ぐ
・PDFヘッダーを確認する
・PDF実ファイルは private-pdfs 配下に保存する
・ファイル名チェックと realpath 確認で private-pdfs 外のファイルを読まないようにしている


13. 現時点の注意点

本番反映時に注意が必要な点は以下です。

・teacher-auth/private-pdfs/ にPHP実行ユーザーの書き込み権限が必要
・PDFアップロード上限は画面側では50MB想定だが、PHP / Webサーバー側の設定にも依存する
・SQLは pdf_documents.sql → pdf_acknowledgements.sql の順に流す必要がある
・SQLは CREATE TABLE IF NOT EXISTS ではないため、既存テーブルがある場合は再実行できない
・一部PDF系PHPにはPHP 8系構文が含まれている
・本番がPHP 7系の場合は、互換対応が必要

