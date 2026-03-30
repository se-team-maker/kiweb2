# Kazasuステータス表示の実装案

実施申告画面（[class-declaration.html](file:///c:/Users/tarog/Downloads/%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E9%96%8B%E7%99%BA-ijuku/kiweb/kiweb/kiweb/class-declaration.html)）の授業名の横に、生徒の最新のKazasuステータスを表示するための実装案です。
ご要望通り、**実際のコード変更は行わず、実装方針のご提案のみ**となります。

## 課題とアプローチ
KazasuログはGoogleスプレッドシート（Kazasuログシート）に保存されているため、フロントエンド（HTML）から直接読み込むことはできません。
そのため、以下の3つのステップで実装を行うのが最適です。

1. **Google Apps Script (GAS) にAPIを追加する**
2. **HTML側で非同期にAPIからデータを取得する**
3. **テーブル描画時にステータスバッジを付与する**

---

## 具体的な実装のステップ

### 1. GAS（バックエンド）の改修
現在の `GAS_URL2` 等で利用されているGASスクリプトに、新たに `action=getLatestKazasuStatus` のような処理を追加します。
- **処理内容**: 
  - Kazasuログシートの当日分のデータを取得。
  - 生徒氏名（A列）をキーとして、最新の日付（C列）と時刻（D列）の「アクション（H列）」と「画像URL（I列）」を取得。
- **レスポンスの形（JSON）**:
  ```json
  {
    "山手智生": {"action": "入室", "time": "16:00", "imageUrl": "https://..."},
    "山田太郎": {"action": "退室", "time": "18:30", "imageUrl": "https://..."}
  }
  ```

### 2. [class-declaration.html](file:///c:/Users/tarog/Downloads/%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E9%96%8B%E7%99%BA-ijuku/kiweb/kiweb/kiweb/class-declaration.html) （JavaScript）のデータ取得処理の追加
ページの表示速度を落とさないよう、既存の予定データ取得（[fetchData](file:///c:/Users/tarog/Downloads/%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E9%96%8B%E7%99%BA-ijuku/kiweb/kiweb/kiweb/class-declaration.html#928-989)内）と**並列（非同期）**でKazasuのステータスを取得します。
- 新たに `let kazasuStatusMap = {};` というグローバル変数を用意。
- [fetchData()](file:///c:/Users/tarog/Downloads/%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E9%96%8B%E7%99%BA-ijuku/kiweb/kiweb/kiweb/class-declaration.html#928-989) にて、GASの新しいAPIエンドポイントを [fetch](file:///c:/Users/tarog/Downloads/%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E9%96%8B%E7%99%BA-ijuku/kiweb/kiweb/kiweb/class-declaration.html#928-989) で呼び出し、結果を `kazasuStatusMap` に格納します。

### 3. [class-declaration.html](file:///c:/Users/tarog/Downloads/%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E9%96%8B%E7%99%BA-ijuku/kiweb/kiweb/kiweb/class-declaration.html) （UI描画）へのバッジ追加
テーブルの行を描画している [displayResults()](file:///c:/Users/tarog/Downloads/%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E9%96%8B%E7%99%BA-ijuku/kiweb/kiweb/kiweb/class-declaration.html#1024-1134) 関数内で、授業名の横にステータスを追加します。
- **生徒名の抽出**: すでに実装されている [extractStudentNameFromClassName(record.授業名)](file:///c:/Users/tarog/Downloads/%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E9%96%8B%E7%99%BA-ijuku/kiweb/kiweb/kiweb/class-declaration.html#1135-1144) を使い、「山手智生_化学」から「山手智生」を抽出します。
- **ステータスの照合**: `kazasuStatusMap["山手智生"]` が存在するか確認します。
- **UIへの挿入**: 存在する場合、授業名を表示する `<td>` の中に、ステータスを示すHTMLを追加します。
  
  **表示イメージ（HTMLコード例）**:
  ```html
  <td>
    ${record.授業名}
    <span class="kazasu-badge kazasu-in">入室 (16:00)</span>
  </td>
  ```

### 4. （オプション）CSSでの装飾・画像URLの活用
- **バッジのデザイン**:
  ステータスが目立つようにCSSを追加します。例えば、「入室」は青系、「退室」はグレー系など色分けすると視認性が上がります。
  ```css
  .kazasu-badge {
      display: inline-block;
      margin-left: 8px;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: bold;
      background-color: #E3F2FD;
      color: #1976D2;
  }
  ```
- **画像URL（I列）の活用**:
  現状のUIを圧迫しないよう、ステータスバッジに**マウスを合わせた際（ホバー時）やクリック時に、ツールチップやポップアップで画像URLを表示する**といった拡張も可能です。

---

## まとめ
この案のメリットは、**既存のデータ取得・表示ロジックを大きく壊さずに拡張できる点**と、**API取得を並列化することでページのロード遅延を防げる点**です。
実装を進める場合は、まず「1. GAS側のAPI作成」から着手することをおすすめします。
