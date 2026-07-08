ICTマニュアル機能 システム内容まとめ


1. システムの目的

ICTマニュアル機能は、kiweb / kiweb2 上でICT関連の操作方法、よくあるトラブル対応、業務システムの使い方を確認できるようにするための機能です。

利用者が困ったときに、ICT担当者へ問い合わせる前に自分で確認できる場所を用意することを目的としています。

初期実装ではDBや管理画面は使わず、PHP、HTML、CSS、JavaScript、Markdown、JSONで構成されています。


2. 主な機能

ICTマニュアル機能には、主に以下の機能があります。

・ログイン済みユーザー向けのICTマニュアルページ表示
・マニュアル一覧表示
・カテゴリ別表示
・検索欄による絞り込み
・最近更新されたページの表示
・Markdown本文の表示
・Markdown内画像の表示
・対象ユーザー別の表示制御
・詳細ページ内の目次表示
・一覧に戻る操作
・問い合わせ前の確認導線表示


3. 全体構成

ICTマニュアルは、以下のような構成です。

・ict-manual.php が画面本体を表示する
・ict-manual-file.php が一覧JSON、Markdown本文、画像を配信する
・app/Documents/ict-manual/manuals/index.json がマニュアル一覧の定義を持つ
・app/Documents/ict-manual/manuals/ 配下に本文Markdownを置く
・app/Documents/ict-manual/assets/images/ 配下に画像を置く

DBにはマニュアル本文を保存しません。

マニュアルの追加・更新は、Markdownファイルと index.json を編集して行う想定です。


4. 利用者側の流れ

利用者は、kiweb / kiweb2 のメニューから「ICTマニュアル」を開きます。

画面本体は、以下のPHPです。

public/auth/ict-manual.php

ict-manual.php は、まずログイン状態と有効ユーザーかどうかを確認します。

未ログインの場合は、ログイン画面へ遷移します。

ログイン済みであれば、ICTマニュアル画面を表示します。

画面表示後、JavaScript が ict-manual-file.php にアクセスし、マニュアル一覧を取得します。

一覧には、ログインユーザーが閲覧できるマニュアルだけが表示されます。


5. マニュアル一覧の流れ

マニュアル一覧は、以下のJSONファイルで管理します。

app/Documents/ict-manual/manuals/index.json

index.json には、各マニュアルのタイトル、カテゴリ、本文ファイル名、説明文、更新日、対象ユーザー、タグ、並び順、表示可否などを定義します。

画面側では、ict-manual-file.php?type=index にアクセスして一覧を取得します。

ict-manual-file.php は、index.json を読み込み、ログインユーザーのロールに応じて表示可能なマニュアルだけを返します。

一覧は order の値を使って並び替えられます。

order が同じ場合は、タイトル順で並びます。


6. マニュアル詳細の流れ

利用者が一覧からマニュアルを選ぶと、JavaScript が ict-manual-file.php に本文取得リクエストを送ります。

本文取得時の形式は以下です。

ict-manual-file.php?type=markdown&id=マニュアルID

ict-manual-file.php は、指定された id が index.json に存在するか確認します。

さらに、そのマニュアルが visible であるか、ログインユーザーが target に含まれているかを確認します。

問題がなければ、対応するMarkdownファイルを読み込んで返します。

画面側のJavaScriptがMarkdownを簡易的にHTMLへ変換し、詳細画面に表示します。

Markdown内のHTMLはエスケープされるため、scriptなどは実行されません。


7. 画像表示の流れ

Markdown本文内では、画像を以下のような形で記述できます。

![ログイン画面](images/login/login-01.png)

画面側のJavaScriptは、この画像パスを直接表示せず、ict-manual-file.php 経由のURLに変換します。

画像取得時の形式は以下です。

ict-manual-file.php?type=image&path=画像パス

ict-manual-file.php は、画像パスの形式を確認し、app/Documents/ict-manual/assets/images/ 配下の画像だけを返します。

realpath 確認により、imagesフォルダの外にあるファイルを読まないようにしています。


8. 検索・カテゴリ表示

ICTマニュアル画面には検索欄があります。

初期実装で検索対象になるのは以下です。

・タイトル
・カテゴリ
・説明文
・タグ

本文全文検索は初期実装の対象外です。

カテゴリは、index.json に登録されている category の値から自動的に作られます。

カテゴリを選ぶと、そのカテゴリに属するマニュアルだけが表示されます。


9. 対象ユーザー制御

index.json の target によって、マニュアルごとの表示対象を制御します。

使える値は以下です。

all

全員向けです。

parttime

非常勤向けです。

fulltime

専任・社員向けです。

admin

管理者向けです。

target が all のマニュアルは、ログイン済みユーザー全員が閲覧できます。

target が admin のマニュアルは、管理者だけが閲覧できます。

一覧表示時だけでなく、本文を直接指定された場合も target を確認します。


10. 管理者判定

ICTマニュアル機能では、以下のいずれかに該当するユーザーを管理者として扱います。

・admin ロールを持つ
・administrator ロールを持つ
・manage_users 権限を持つ

管理者は、target に admin が設定されたマニュアルを閲覧できます。


11. 関係する主なファイル

画面本体

public/auth/ict-manual.php

ICTマニュアル画面を表示します。

ログイン状態を確認し、ログイン済みユーザーにだけ画面を表示します。

画面内のJavaScriptで、一覧取得、検索、カテゴリ絞り込み、Markdown表示、画像表示、目次生成を行います。


配信API

public/auth/ict-manual-file.php

一覧JSON、Markdown本文、画像を配信します。

ログイン状態、有効ユーザー、target、ファイルパスの安全性を確認します。


一覧定義

app/Documents/ict-manual/manuals/index.json

マニュアル一覧の定義ファイルです。

各マニュアルの id、title、category、file、description、updated、target、tags、priority、order、visible を管理します。


本文Markdown

app/Documents/ict-manual/manuals/login.md
app/Documents/ict-manual/manuals/cache-refresh.md
app/Documents/ict-manual/manuals/checkin.md
app/Documents/ict-manual/manuals/pdf-viewer.md
app/Documents/ict-manual/manuals/contact.md
app/Documents/ict-manual/manuals/_template.md

マニュアル本文です。

_template.md は新規作成時のひな形です。


画像保存先

app/Documents/ict-manual/assets/images/

Markdown本文内で使う画像の保存先です。


関連ドキュメント

docs/ict-manual-requirements.md

ICTマニュアル機能の要件定義です。

docs/ict-manual-authoring-guide.md

マニュアル本文を書く担当者向けの執筆ガイドです。


ポータル側

kiweb2.html
kiweb2-fulltime.html
kiweb2-admin.html

各ポータルからICTマニュアルを開く導線を持ちます。

iframe 表示先として /kiweb/public/auth/ict-manual.php を開く想定です。


12. index.json の主な項目

index.json では、各マニュアルを以下のような情報で管理します。

id

マニュアルを識別するIDです。

URLハッシュや本文取得時の指定に使います。


title

一覧と詳細画面に表示するタイトルです。


category

カテゴリ名です。

カテゴリ一覧の生成にも使われます。


file

本文Markdownファイル名です。


description

一覧に表示する短い説明文です。


updated

更新日です。


target

表示対象です。

all、parttime、fulltime、admin を使います。


tags

検索用のキーワードです。


priority

優先度です。

初期データでは high が使われています。


order

一覧表示順です。

小さい数字ほど上に表示されます。


visible

一覧や本文取得で表示対象にするかどうかです。

false の場合は表示されません。


13. 初期マニュアル

初期実装では、以下のマニュアルが用意されています。

ログインできないとき

カテゴリ: ログイン・アカウント

ファイル: login.md


画面が古いまま更新されないとき

カテゴリ: よくあるトラブル

ファイル: cache-refresh.md


出退勤の打刻方法

カテゴリ: 出退勤・勤務記録

ファイル: checkin.md


PDFビューアの使い方

カテゴリ: 資料配信・PDFビューア

ファイル: pdf-viewer.md


ICT担当への問い合わせ方法

カテゴリ: 問い合わせ方法

ファイル: contact.md


14. セキュリティ上のポイント

ICTマニュアル機能では、以下の点で認証・権限確認を行っています。

・未ログインでは ict-manual.php を閲覧できない
・未ログインでは一覧JSONを取得できない
・未ログインではMarkdown本文を取得できない
・未ログインでは画像を取得できない
・target に含まれないマニュアルは一覧に出ない
・target に含まれないマニュアルIDを直接指定しても本文を取得できない
・visible が false のマニュアルは表示されない
・Markdownファイル名は英数字、ドット、アンダースコア、ハイフンのみ許可する
・画像パスは画像拡張子のみ許可する
・.. を含むパスは拒否する
・realpath 確認で manuals / images の外に出ないようにする
・Markdown内のHTMLはエスケープして表示する
・本文に秘密情報を記載しない運用を前提にする


15. 初期実装で対象外のもの

初期実装では、以下は対象外です。

・DBによるマニュアル管理
・管理画面からのマニュアル編集
・既読管理
・コメント機能
・ファイルアップロード機能
・本文全文検索
・アクセスログ集計画面
・画像拡大表示


16. 現時点の注意点

現時点で注意が必要な点は以下です。

・現作業ツリーにはICTマニュアル関連ファイルが存在せず、origin/feature/ict-manual 上の実装を元に確認している
・DBテーブルは使わないため、本番DBへのSQL流し込みは不要
・マニュアル追加時は Markdown ファイルだけでなく index.json の更新も必要
・index.json の target 設定を間違えると、想定外のユーザーに表示される可能性がある
・管理者向けマニュアルには target: admin を設定する必要がある
・本文や画像にはパスワード、DB情報、GAS URL、Slack token、FTP情報などの秘密情報を書かない
・Markdown本文を更新してもブラウザキャッシュの影響で古い表示が残る可能性がある
・画面側のMarkdown変換は簡易実装のため、複雑なMarkdown表現には対応していない

