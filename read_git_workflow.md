# Gitブランチ・PR運用ルール

## 目的

このドキュメントは、kiweb などの開発で Git / GitHub を使うときの基本ルールをまとめたものです。

目的は、以下の3つです。

- `main` を常に安定した状態に保つ
- 作業内容を後から追いやすくする
- チーム人数が増えても、同じ流れで安全に開発できるようにする

- GitHubには多くの専門用語が存在します。わからない場合は都度調べてください。
  https://docs.github.com/ja

---
## 事前準備
- あなた個人のGitHubアカウントを作成する
- se-team-maker/kiweb2のsettingから「Collaborators」にあなたのGitHubアカウントを追加してもらう
- GitHub上で、se-team-maker/kiweb2のリポジトリをあなたのローカルの任意のフォルダにクローンする
- GitHubに慣れていない場合、GitHubDesktopの使用を推奨しますが、コマンドラインでの操作についても理解しておくと便利です。 

---
## 基本方針

原則として、直接 `main` ブランチで作業しません。

作業するときは、必ず作業用ブランチを作ります。

```text
main
  本番・安定版

test / dev
  テストサーバー反映用

feature/xxxx
  新機能追加用

fix/xxxx
  不具合修正用

hotfix/xxxx
  緊急修正用
```

基本の流れは以下です。

```text
作業ブランチ
  ↓
PR作成
  ↓
testブランチ、またはテストサーバーで動作確認
  ↓
問題なければ main にマージ
```

---

## ブランチ名のルール

ブランチを見たときに「何の作業か」が分かるようにするための命名ルールです。
`feature/` や `fix/` に特別な機能はありません。  

### 新機能追加

```bash
feature/作業内容
```

例：

```bash
feature/material-delivery
feature/signage-viewer
feature/fulltime-page
```

### 不具合修正

```bash
fix/修正内容
```

例：

```bash
fix/unread-badge
fix/fulltime-name-edit
fix/login-redirect
```

### 緊急修正

```bash
hotfix/修正内容
```

例：

```bash
hotfix/login-error
hotfix/pdf-viewer-crash
```

### 日付を入れたい場合

```bash
feature/260513-material-delivery
fix/260513-unread-badge
```

---

## ブランチを切る単位

基本は、**1つの目的につき1ブランチ**です。

### 良い例

```text
feature/material-delivery-admin
  資料配信管理画面を作る

fix/pdf-unread-badge
  未確認バッジが出ない問題を直す

fix/fulltime-name-edit
  専任ロールで氏名変更できない問題を直す

feature/signage-viewer
  サイネージPDF表示機能を追加する
```

### 悪い例

```text
feature/kiweb-update
feature/all-fixes
feature/260513-work
```

このような名前だと、後から見たときに何をしたブランチなのか分かりにくくなります。

---

## ブランチを分ける基準

### 1ブランチにしてよいもの

同じ目的・同じ画面・同じ不具合に関係する変更は、1つのブランチにまとめて大丈夫です。

例：

```text
資料配信の未確認バッジ修正
- 未確認件数を取得するAPI修正
- サイドメニューにバッジ表示
- 確認後にバッジを更新
```

これは同じ目的なので、1ブランチで問題ありません。

### 分けた方がよいもの

以下の場合は、ブランチを分けます。

```text
- 目的が違う
- 影響範囲が違う
- 片方だけ先に main に入れたい可能性がある
- 片方が壊れても、もう片方は入れたい
```

例：

```text
資料配信管理の追加
サイネージPDF表示の追加
ログイン処理の修正
専任ページの氏名編集修正
```

これらを1つのブランチにまとめると大きすぎます。  
それぞれ別ブランチに分けた方が安全です。

---

## コミットの単位

ブランチは「目的ごと」、コミットは「作業の区切りごと」にします。

例：

```text
branch: feature/material-delivery

commit 1: 資料一覧APIを追加
commit 2: 利用者側の資料一覧画面を追加
commit 3: 確認済み登録処理を追加
commit 4: 未確認資料を上に表示
```

1つのブランチの中に、複数のコミットがあっても問題ありません。

---

## 基本の作業手順

### 1. main を最新にする

```bash
git switch main
git pull origin main
```

### 2. 作業ブランチを作る

```bash
git switch -c feature/作業名
```

例：

```bash
git switch -c feature/material-delivery
```

### 3. 作業する

ファイルを編集します。

### 4. 変更内容を確認する

```bash
git status
git diff
```

### 5. コミットする

```bash
git add .
git commit -m "資料配信の未確認バッジを追加"
```

### 6. GitHubへpushする

```bash
git push -u origin feature/作業名
```

例：

```bash
git push -u origin feature/material-delivery
```

### 7. GitHubでPRを作る

GitHub上で `Compare & pull request` を押します。

```text
base: main
compare: feature/作業名
```

意味としては、

```text
feature/作業名 の変更を main に入れたい
```

という申請です。

---

## PRを作る意味

1人作業でも PR を作ることはできます。

PRを作るメリットは以下です。

```text
- GitHub上で差分を確認できる
- 作業内容の説明を残せる
- テスト結果を記録できる
- 後から「何を入れたか」を追いやすい
- mainへの直接pushを避けられる
```

特に、ログイン処理・DB変更・管理画面追加・PDF表示など、本番影響がある作業ではPRを作ることを推奨します。

---

## PR本文テンプレート

PRを作るときは、以下のように書くと分かりやすいです。

```md
## やったこと

- 
- 
- 

## 確認したこと

- 
- 
- 

## 影響範囲

- 

## 注意点

- 
```

記入例：

```md
## やったこと

- 資料配信の未確認件数バッジを追加
- 未確認資料を上に表示
- 確認ボタン押下後にバッジを更新

## 確認したこと

- 非常勤アカウントで資料一覧を表示できること
- 未確認資料が上に表示されること
- 確認ボタン押下後に未確認件数が減ること
- 非公開資料が一覧に出ないこと

## 影響範囲

- kiweb2 のサイドメニュー
- 資料配信一覧画面
- 資料確認API

## 注意点

- DBの read_status テーブルを参照する
```

---

## テストサーバーで確認する流れ

テストサーバー用ブランチを `test` とします。

```text
feature/xxxx
  ↓
test にマージ
  ↓
テストサーバーにアップロードして確認
  ↓
OKなら main にマージ
```

### testブランチに作業内容を入れる

```bash
git switch test
git pull origin test
git merge origin/feature/作業名
git push origin test
```

例：

```bash
git switch test
git pull origin test
git merge origin/feature/material-delivery
git push origin test
```

その後、テストサーバー上で動作確認します。

---

## main にマージする流れ

テストが問題なければ、GitHub上のPRを確認して `main` にマージします。

```text
feature/xxxx
  ↓
main
```

マージ後は、ローカルの `main` も最新にします。

```bash
git switch main
git pull origin main
```

---

## PRなしで進める場合

小さい修正や自分だけの作業では、PRなしでも運用できます。

ただし、その場合でも `main` で直接作業せず、作業ブランチを作ります。

```bash
git switch main
git pull origin main

git switch -c fix/small-bug
# 作業する

git add .
git commit -m "小さな不具合を修正"

git switch main
git pull origin main
git merge fix/small-bug
git push origin main
```

ただし、チーム開発ではPRを作る運用を基本とします。

---

## やってはいけないこと

### mainで直接作業する

```bash
git switch main
# そのまま編集してcommit
```

これは避けます。

### mainに直接pushする

```bash
git push origin main
```

確認なしで本番用ブランチに入る可能性があるため、原則避けます。

### 何でも1つのブランチにまとめる

```text
feature/all-update
```

このようなブランチは、後から確認しづらくなります。

---

## よく使うコマンド

### 今いるブランチを確認

```bash
git branch
```

### 状態を確認

```bash
git status
```

### 変更差分を見る

```bash
git diff
```

### コミット履歴を見る

```bash
git log --oneline
```

### mainとの差分を見る

```bash
git diff main..feature/作業名
```

### mainに入っていないコミットを見る

```bash
git log main..feature/作業名 --oneline
```

### リモートの情報を取得

```bash
git fetch origin
```

---

## 運用まとめ

基本ルールは以下です。

```text
直接 main で作業しない
作業ごとにブランチを作る
ブランチ名で作業内容が分かるようにする
PRを作って差分と確認内容を残す
テストサーバーで確認してから main に入れる
```

特にチーム開発では、以下の流れを標準とします。

```text
main を最新化
  ↓
作業ブランチ作成
  ↓
作業・コミット
  ↓
GitHubへpush
  ↓
PR作成
  ↓
testブランチ / テストサーバーで確認
  ↓
mainへマージ
```
