# ICTマニュアル 執筆ガイド

このガイドは、ICTマニュアルの本文を書く担当者向けの簡単な説明です。

## 1. 書く場所

本文は次のフォルダにMarkdownファイルとして追加します。

```text
teacher-auth/ict-manual/manuals/
```

例:

```text
teacher-auth/ict-manual/manuals/login.md
teacher-auth/ict-manual/manuals/pdf-viewer.md
```

新しいページを追加したら、必ず次の一覧ファイルにも情報を追加します。

```text
teacher-auth/ict-manual/manuals/index.json
```

## 2. ファイル名

ファイル名は英数字とハイフンだけにします。

よい例:

```text
login.md
cache-refresh.md
class-report-search.md
room-booking.md
```

避ける例:

```text
ログイン.md
PDF ビューア.md
manual_最新版.md
```

## 3. 基本フォーマット

本文は次の構成を基本にします。

```markdown
# タイトル

## 対象

誰向けのページかを書きます。

## 概要

このページで何を説明するかを短く書きます。

## 手順

1. 最初に行うことを書きます。
2. 次に行うことを書きます。
3. 最後に確認することを書きます。

## よくある原因

- 起こりやすい原因を書きます。
- 利用者が勘違いしやすい点を書きます。

## 注意点

- 操作前に知っておくべきことを書きます。

## 解決しない場合

問い合わせ時に伝えてほしい情報を書きます。
```

不要な見出しは削って構いません。ただし、`# タイトル`、`## 対象`、`## 手順`、`## 解決しない場合` はできるだけ残してください。

## 4. 書き方のルール

- 読む人がそのまま操作できるように、手順は番号付きリストで書きます。
- 1つの手順には、できるだけ1つの操作だけを書きます。
- 画面名やボタン名は、実際に表示されている名前に合わせます。
- 「ここ」「あれ」などの指示語だけで説明しないようにします。
- 専門用語を使う場合は、簡単な説明を添えます。
- 最後に、解決しない場合の連絡情報や確認事項を書きます。

## 5. 画像の入れ方

画像は次のフォルダ配下に置きます。

```text
teacher-auth/ict-manual/assets/images/
```

機能ごとにフォルダを分けます。

```text
teacher-auth/ict-manual/assets/images/login/
teacher-auth/ict-manual/assets/images/checkin/
teacher-auth/ict-manual/assets/images/pdf-viewer/
```

Markdownには次のように書きます。

```markdown
![ログイン画面](images/login/login-01.png)
```

画像ファイル名も英数字とハイフンを基本にしてください。

## 6. index.jsonへの追加例

新しいMarkdownファイルを追加したら、`index.json` に次のような情報を追加します。

```json
{
  "id": "room-booking",
  "title": "会議室予約の使い方",
  "category": "会議室予約",
  "file": "room-booking.md",
  "description": "会議室予約システムの基本操作を説明します。",
  "updated": "2026-06-08",
  "target": ["fulltime", "admin"],
  "tags": ["会議室", "予約", "専任"],
  "priority": "medium",
  "order": 80,
  "visible": true
}
```

各項目の意味:

- `id`: ページを識別する英数字IDです。ファイル名と近い名前にします。
- `title`: 一覧と詳細に表示するタイトルです。
- `category`: カテゴリ名です。既存カテゴリに合わせます。
- `file`: Markdownファイル名です。
- `description`: 一覧に表示する短い説明です。
- `updated`: 更新日です。`YYYY-MM-DD` 形式で書きます。
- `target`: 表示対象です。`all`、`parttime`、`fulltime`、`admin` が使えます。
- `tags`: 検索用の言葉です。
- `priority`: 優先度です。`high`、`medium`、`low` を使います。
- `order`: 一覧での並び順です。小さい数字ほど上に表示されます。
- `visible`: 一覧に表示する場合は `true` にします。

## 7. targetの選び方

- 全員向け: `"target": ["all"]`
- 非常勤向け: `"target": ["parttime"]`
- 専任向け: `"target": ["fulltime"]`
- 管理者向け: `"target": ["admin"]`
- 専任と管理者向け: `"target": ["fulltime", "admin"]`

管理者向けの内容は、必ず `admin` にしてください。
