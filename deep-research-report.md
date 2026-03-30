# Android/iPhone向けWebアプリ最適化の包括的ガイド

## 結論

Android/iPhone最適化は「モバイルWeb・PWA・ハイブリッド（WebView）どれを選ぶか」で設計の前提が変わるため、最初に分岐点（インストール、オフライン、プッシュ、ストア配布、ネイティブAPI要件）を明文化し、その上で **レスポンシブ＋パフォーマンス（Core Web Vitals）＋キャッシュ＋セキュリティヘッダ** を一貫して実装するのが最短ルートです。 citeturn19search1turn19search2turn32search0turn32search1turn15search1turn16search0turn17view0turn3search3turn6search1

## 根拠

- PWAのインストール/UIや条件はプラットフォーム差があり（Chromeのinstall criteriaと、iOS Safariの「ホーム画面に追加」）、体験設計を分岐させる必要があります。citeturn19search2turn19search1turn19search0  
- ハイブリッドはOSネイティブのWebView（Android WebView / iOS WKWebView）上でWebを動かすため、「ブラウザ最適化」だけでは吸収できない制約・運用（アップデート配布、WebView差、ブリッジ）が発生します。citeturn32search0turn32search1turn32search3  
- モバイル最適化の基盤はviewport設定とメディアクエリ（端末幅・ユーザー嗜好・キーボードの影響など）で、誤るとレスポンシブが成立しません。citeturn35view0turn3search0  
- 改善優先度はCore Web Vitals（LCP/INP/CLS）のしきい値と測定手順に沿って決めるのが再現性が高いです。citeturn15search1turn16search0turn17view0turn1search0turn29search0  
- iOS/Androidの実勢（OS・ブラウザ・画面サイズ）に合わせたテスト優先順位は、（ここでは）日本のStatCounterデータで具体化できます。citeturn27view0turn28view0turn25view0turn26view0turn39view0  

## 不確実な点と前提

- iOSのバージョン判定は、ブラウザ側のアンチフィンガープリンティング等で誤判定が混在し得ます。StatCounter自体も「Safariの変更で誤計測が起きたためパッチを適用した」と明記しています。よって **OSバージョン別の厳密な割合は“目安”** として扱う前提です。citeturn25view0  
- iOS Safari固有の「入力フォーカス時の自動ズーム（font-sizeが小さいと発生）」や「キーボード表示後に固定要素がずれる」等は公式仕様として明文化されていない範囲があり、環境差が出ます（本稿では回避策を提示しますが、最終的には実機で要確認）。citeturn18search5turn35view0turn10search2  
- PWAの“バックグラウンド処理”はAPIごとに対応状況が異なり、特にPeriodic Background Syncは「インストール必須」など制約があります。iOS側の対応は、対象iOS/Safariの実機・対象機能で確認が必要です。citeturn13search7turn13search4  

## 次に何を確認すべきか

- まず「要件の分岐点」をチェックリスト化し、モバイルWeb / PWA / ハイブリッドのうち、**どれに“勝ち筋”があるか** を決めます（後述のフローチャート参照）。citeturn19search1turn32search0turn32search1  
- 現状のLCP/INP/CLSを、**ラボ（Lighthouse/WebPageTest）＋フィールド（RUM/CrUX）** の二系統で測り、最初の改善バックログ（上位10件程度）を作ります。citeturn1search1turn1search3turn29search0turn16search2turn31search4  
- ターゲットOS/ブラウザ/画面サイズを、アクセス解析（自社のGA等）と市場データ（StatCounter等）で突き合わせ、実機テスト台数を最小化します。citeturn28view0turn39view0  

## 最適化戦略の分岐点

モバイル最適化は「同じWeb技術」でも、配布形態と実行環境が違うため、最適化方針が分岐します。

### 方式の定義と違い

モバイルWeb（ブラウザ）は、Safari/Chrome等でURLアクセスする前提で、SEO・共有・即時更新に強い一方、インストールやプッシュ等の“アプリらしさ”は限定されがちです（ただしService Worker自体はPWA専用ではなくWebにも使えます）。citeturn29search1turn4search0  

PWAは、Web App ManifestとService Worker等により「インストール」「オフライン」「通知」などを実現しやすい方式です。インストール体験はOS・ブラウザごとに異なり、特にiOSでは「ホーム画面に追加」導線が中心になります。citeturn4search3turn19search1turn19search0turn10search3  

ハイブリッドは、ネイティブアプリの“殻”の中でWebViewを起動し、WebでUIを作りつつ、ブリッジでネイティブ機能にアクセスします（Android WebView / iOS WKWebView）。配布はストア中心になり、WebViewの挙動差・アップデート運用が最適化計画に直結します。citeturn32search0turn32search1turn32search3turn32search2  

### 分岐点を明示した意思決定フロー

```mermaid
flowchart TD
  A[要件整理] --> B{ストア配布が必須? \n(課金/規約/MDM/社内配布など)}
  B -- はい --> C{ネイティブAPI連携が濃い? \n(センサー/バックグラウンド常駐/深い統合)}
  C -- はい --> N[ネイティブ中心\n(必要箇所だけWebView)]
  C -- いいえ --> H[ハイブリッド\n(WebView + ブリッジ)]
  B -- いいえ --> D{オフライン/インストール/通知が必要?}
  D -- いいえ --> W[モバイルWeb最適化]
  D -- はい --> E{iOS/Androidで\n同等機能が必要?}
  E -- はい --> P[PWA\n(差分は設計で吸収)]
  E -- いいえ --> P2[PWA\n(主要OSを優先し段階提供)]
```

意思決定の“現実的なコツ”は、まず **「iOSで（欲しい機能が）成立するか」** を確認することです。iOSはインストール導線（ホーム画面に追加）がOS流儀で、Web Pushも「ホーム画面に追加されたWebアプリ」に対して提供された機能である点が重要です。citeturn19search0turn10search3  

## 実装ガイド

### レスポンシブ設計とビューポート設定

#### 基本のviewport

モバイルWebのレスポンシブは、まずviewportメタで“仮想ビューポート縮小”を避けるのが定石です。citeturn35view0  

```html
<!-- 基本：レスポンシブを成立させる -->
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- ノッチ/角丸などを想定して全画面を使う場合（重要UIはsafe-areaで内側へ） -->
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
```

`viewport-fit=cover` を使う場合は、重要なコンテンツが欠けないよう `env(safe-area-inset-*)` の利用が推奨されています。citeturn35view0turn8search1  

> 注意: `user-scalable=no` でズームを殺すのはアクセシビリティ上の問題になり得る、とMDNが警告しています（少なくとも拡大が必要）。citeturn35view0  

#### キーボード表示時の挙動を制御するヒント

MDNは、仮想キーボード等がビューポートに与える影響を `interactive-widget` で指定できることを説明しています。citeturn35view0  

```html
<!-- キーボード表示時：視覚ビューポートだけが縮む（デフォルト） -->
<meta name="viewport"
      content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-visual">
```

iOS Safariでの“キーボード時レイアウト崩れ”は、最終的には実機で要チューニングですが、これを起点に「どこが縮むのか」を明示し、CSS/JS側の調整がしやすくなります。citeturn35view0turn10search2  

#### メディアクエリ

メディアクエリは、画面幅だけでなく、ユーザーの嗜好（動きの軽減等）も扱えます。citeturn3search0  

```css
/* モバイルファースト */
.page {
  padding: 16px;
}

/* 横幅が広がったら余白を増やす */
@media (min-width: 768px) {
  .page { padding: 24px; }
}

/* 動きを減らしたいユーザーへの配慮 */
@media (prefers-reduced-motion: reduce) {
  * { animation-duration: 0.001ms !important; transition-duration: 0.001ms !important; }
}
```

#### “100vh問題”の基本解決（svh/lvh/dvh）

モバイルではアドレスバー等でビューポートが変動します。CSS Valuesでは、small/large/dynamic viewportに対応する単位として `svh/lvh/dvh` を定義しています。citeturn11search5turn11search2  

```css
.hero {
  /* 画面の見えている高さに追随する */
  min-height: 100dvh;

  /* フォールバックを入れるなら順序に注意（古いUA向け） */
  min-height: 100vh;
}
```

#### セーフエリア

`env()` で `safe-area-inset-*` を参照できます。citeturn8search1turn35view0  

```css
:root {
  --safe-top: env(safe-area-inset-top);
  --safe-right: env(safe-area-inset-right);
  --safe-bottom: env(safe-area-inset-bottom);
  --safe-left: env(safe-area-inset-left);
}

.header {
  padding-top: calc(12px + var(--safe-top));
  padding-left: calc(16px + var(--safe-left));
  padding-right: calc(16px + var(--safe-right));
}
.footer {
  padding-bottom: calc(12px + var(--safe-bottom));
}
```

### タッチターゲット、ジェスチャー、フォーム

#### 最低限の数値基準

Web/モバイルで衝突しにくい基準は次のように整理できます。

- AppleのHIG（Buttons）は「一般的にボタンのヒット領域は少なくとも **44×44pt**」としています。citeturn23search0  
- Material Designはタッチターゲットを **48×48**（見た目が小さくてもpaddingで領域確保）としています。citeturn20search3turn1search10  
- WCAG 2.2（達成基準2.5.8）の理解文書では **24×24 CSS px** を“最低限”の基準として説明しています。citeturn20search0turn20search4  
- WCAG 2.1/2.2（達成基準2.5.5/Enhanced）は **44×44 CSS px** を示しています（適用レベルは文書の該当箇所に依存）。citeturn20search1turn20search5  

実務上は「主要操作は44〜48相当」「補助操作は最低24相当（ただし密集させない）」が事故りにくいです。citeturn23search0turn20search0turn20search3  

#### 実装例

```css
/* 主要ボタン：最低44px相当を確保 */
.btn {
  min-height: 44px;
  min-width: 44px;
  padding: 12px 16px;
  border-radius: 12px;
}

/* 小さなアイコンでも押しやすく（見た目24pxでもタップ領域は確保） */
.icon-btn {
  width: 24px;
  height: 24px;
  padding: 12px;          /* 24 + 12*2 = 48px 相当 */
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
```

#### ジェスチャーと `touch-action`

`touch-action` はブラウザ内蔵のパン/ズーム（ダブルタップズーム等）へ影響するため、使い所を誤るとアクセシビリティを損ねます。MDNは `touch-action` の目的と注意点を説明しています。citeturn33search3  

```css
/* クリック遅延対策として使われることが多い（ピンチズームは維持） */
.tap-optimized {
  touch-action: manipulation;
}

/* 地図/ゲーム等で独自ジェスチャー実装する時だけ（ズーム阻害に注意） */
.custom-gesture-area {
  touch-action: none;
}
```

#### キーボード表示時のレイアウト（固定フッター等）

Visual Viewport APIは「オンスクリーンキーボード等を除いた“見えている領域”」を扱えるため、固定UIのギャップ調整に使えます。citeturn10search2turn35view0  

```js
const footer = document.querySelector('.footer');
const vv = window.visualViewport;

function updateFooterOffset() {
  if (!vv || !footer) return;
  // レイアウト・ビューポートとの差分を下余白として足す例
  const bottomInset = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
  footer.style.paddingBottom = `calc(${bottomInset}px + env(safe-area-inset-bottom))`;
}

if (vv) {
  vv.addEventListener('resize', updateFooterOffset);
  vv.addEventListener('scroll', updateFooterOffset);
  updateFooterOffset();
}
```

#### iOS Safariのフォーム自動ズーム（要確認）

iOS Safariで「入力欄のフォントサイズが小さいと自動ズームする」挙動は広く報告されていますが、一次仕様として参照しづらい領域です。実務では **入力欄の`font-size: 16px`以上** で回避するケースが多いです（環境差があるため要実機確認）。citeturn18search5  

```css
input, textarea, select {
  font-size: 16px; /* iOSでの自動ズーム回避としてよく使われる */
}
```

### フォントとアイコンの最適化

#### フォント（FOIT/FOUTとCLSを意識）

`@font-face` の `font-display` は、フォントがまだダウンロードされていない場合の表示戦略を定義します。citeturn8search2  

```css
@font-face {
  font-family: "MySans";
  src: url("/fonts/mysans.woff2") format("woff2");
  font-display: swap; /* 体感速度を優先（FOUT許容） */
}
```

フォント差し替えによるレイアウト変化はCLSの原因になるため、後述のCLS対策（サイズ確保・フォールバック設計）とセットで扱います。citeturn17view0turn8search2  

#### アイコン（PWA/ホーム画面向け）

Web App Manifestの`icons`は、インストール時の見た目に直結します。MDNはiconsの指定を解説しています。citeturn4search2  
またAppleのBrowserEngineKit関連ドキュメントでも、Webアプリが `<link rel="manifest">` でmanifestを示すことが説明されています。citeturn12search2  

```html
<link rel="manifest" href="/manifest.webmanifest">
```

```json
{
  "name": "Example App",
  "short_name": "Example",
  "start_url": "/?source=pwa",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#111111",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

### 画像最適化（WebP/AVIF、srcset、lazy、LCP）

#### 形式選定（WebP/AVIF）

MDNは主要画像形式のサポート状況と選び方をまとめています。citeturn8search3  
WebP配信はLighthouse監査でも検出できるとweb.devが説明しています。citeturn30search2  

#### `<picture>`でAVIF→WebP→JPEGのフォールバック

```html
<picture>
  <source srcset="/img/hero.avif" type="image/avif">
  <source srcset="/img/hero.webp" type="image/webp">
  <img src="/img/hero.jpg"
       width="1200" height="800"
       alt="ヒーロー画像"
       fetchpriority="high">
</picture>
```

#### レスポンシブ画像（`srcset`/`sizes`）

MDNはレスポンシブ画像（`srcset`/`sizes`/`picture`）の意図と実装を解説しています。citeturn2search1  

```html
<img
  src="/img/card-800.webp"
  srcset="/img/card-480.webp 480w,
          /img/card-800.webp 800w,
          /img/card-1200.webp 1200w"
  sizes="(max-width: 600px) 92vw, 600px"
  width="600" height="400"
  loading="lazy"
  decoding="async"
  alt="カード画像">
```

`width`/`height` などで確保領域を明示することはCLS低減に直結します（CLSの定義と対策は後述）。citeturn17view0  

#### lazy loading（ただしLCP候補は避ける）

MDNは遅延読み込みの概念と、`loading="lazy"` 等の戦略を解説しています。citeturn2search2  
web.devは、`loading`属性の使い方と、**LCP画像をlazyにしない注意** を明記しています。citeturn30search3  

### CSS/JSの最小化と分割（コード分割、Brotli、レンダーブロック）

#### コード分割（dynamic import）

web.devは、起動時に送るJS量を減らすためのコード分割と`import()`を解説しています。citeturn5search2turn5search3  

```js
// 初回は最小限だけ読み込み、必要になったら機能をロードする例
document.querySelector('#openSettings')?.addEventListener('click', async () => {
  const { openSettingsModal } = await import('./settings-modal.js');
  openSettingsModal();
});
```

#### レンダーブロックの削減

リソース読み込み最適化（parser-blocking / render-blockingの理解）はweb.devが体系的に説明しています。citeturn30search1  
Lighthouseの「render-blocking resources」監査も、切り分けの起点になります。citeturn30search5  

```html
<!-- 可能ならmodule + defer/asyncでブロックを減らす -->
<script type="module" src="/assets/app.js"></script>
```

#### 圧縮（Brotli）

テキスト（HTML/CSS/JS/JSON）の圧縮は転送量削減に効きます。web.devはBrotli圧縮の効果と導入の考え方を説明しています。citeturn30search0  

### Service Workerとキャッシュ戦略

#### 何ができるか

Service Workerはネットワークリクエストを傍受し、キャッシュから返す等の制御ができます。MDNは`fetch`イベントの意味を説明しています。citeturn4search0  

#### 最小構成の登録

```js
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js');
}
```

#### 最小構成の`sw.js`（キャッシュファースト＋更新）

```js
const CACHE_NAME = 'app-static-v1';
const PRECACHE_URLS = ['/', '/styles.css', '/app.js', '/offline.html'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(names.map((n) => (n === CACHE_NAME ? null : caches.delete(n))))
    )
  );
});

// fetchイベントでネットワークを傍受できる citeturn4search0
self.addEventListener('fetch', (event) => {
  // ナビゲーションはネットワーク優先→失敗時オフライン
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // 静的リソースはキャッシュ優先
  event.respondWith(
    caches.match(event.request).then((cached) => cached || fetch(event.request))
  );
});
```

`CacheStorage`は複数キャッシュの管理・検索等を提供します。citeturn4search1  

#### 更新を素早く反映する（慎重に）

MDNは `skipWaiting()` と `Clients.claim()` で新SWの適用を早められることを説明しています。citeturn36search2  
ただし、実行中タブとの整合性（古いJSと新しいHTMLが混ざる等）を壊す可能性があるため、「更新通知→次回起動で適用」等のUX設計が必要です。citeturn36search2  

#### Workboxで“壊れにくい”キャッシュ戦略へ

Workboxは典型的キャッシュ戦略（Stale-While-Revalidate等）をモジュールとして提供します。citeturn3search2  
precache（ビルド成果物のリビジョニング更新）もサポートします。citeturn36search3  

```js
// sw.js (Workbox例：ビルドで注入される前提)
import { precacheAndRoute } from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';
import { StaleWhileRevalidate, NetworkFirst } from 'workbox-strategies';

precacheAndRoute(self.__WB_MANIFEST); // revision差分で更新 citeturn36search3

// CSS/JSはSW-Rで体感速度を優先 citeturn3search2
registerRoute(
  ({request}) => request.destination === 'script' || request.destination === 'style',
  new StaleWhileRevalidate({ cacheName: 'static-assets' })
);

// HTMLナビゲーションはNetworkFirst（オフライン時にキャッシュ）という設計も多い
registerRoute(
  ({request}) => request.mode === 'navigate',
  new NetworkFirst({ cacheName: 'pages' })
);
```

### HTTP/2/HTTP/3の前提整理

HTTP/2は多重化（同一接続で並列）やヘッダ圧縮などでレイテンシ削減を狙う改訂版、とMDNが説明しています。citeturn5search0  
HTTP/3はQUIC（UDP上のプロトコル）を利用するのが最も大きな違い、とMDNが説明しています。citeturn6search0turn5search5  

実務では「まずCDN/ホスティングのHTTP/2/3対応をONにする」「画像・JSなど“総リクエスト数”を減らす最適化も並行する」が堅実です（HTTP/2/3があっても無駄な転送が多ければCWVは改善しません）。citeturn5search0turn6search0turn15search1  

### CSPとセキュリティヘッダ（推奨セットと設定例）

#### CSPの基本

CSPの`default-src`は他ディレクティブが無い場合のフォールバックとして働く、とMDNが説明しています。citeturn3search3  
JSソース制御は`script-src`で指定します。citeturn34search0  

#### まずはReport-Onlyで導入

CSPをいきなり強制すると本番障害になりやすいので、`Content-Security-Policy-Report-Only` で違反を収集し、段階的に本番強制へ移行するのが典型です。citeturn34search2  

#### セキュリティヘッダ一式（例：Nginx）

以下は「モバイルWeb/PWAでも基本となる」例です。実際の許可ドメインは要件に合わせて調整してください。

```nginx
# 例: Nginx（抜粋） - 過度に厳しいと動かなくなるので段階導入推奨

add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always; # HSTS citeturn6search1
add_header X-Content-Type-Options "nosniff" always;                                         # MIME sniffing抑止 citeturn7search0
add_header Referrer-Policy "strict-origin-when-cross-origin" always;                         # 参照元制御 citeturn6search3
add_header Permissions-Policy "geolocation=(self), camera=(), microphone=()" always;         # 機能制御 citeturn6search2turn34search3

# クリックジャッキングはCSP frame-ancestorsが第一候補（XFOは補助） citeturn34search1turn7search1
add_header X-Frame-Options "DENY" always;                                                    # 互換用 citeturn7search1

# COOP/COEPは高度機能（crossOriginIsolated等）に効くが、外部リソース依存があると破壊的になり得る citeturn7search2turn7search3
# add_header Cross-Origin-Opener-Policy "same-origin" always;
# add_header Cross-Origin-Embedder-Policy "require-corp" always;

# CSP（例：まずReport-Onlyを推奨） citeturn34search2turn3search3turn34search0
add_header Content-Security-Policy-Report-Only
  "default-src 'self';
   script-src 'self';
   style-src 'self' 'unsafe-inline';
   img-src 'self' data:;
   connect-src 'self';
   frame-ancestors 'none';" always;
```

## パフォーマンス計測とCore Web Vitals

### 計測ツールと手順

#### Lighthouse（ラボ計測）

LighthouseはDevTools内、CLI、Node module等で実行でき、Performance/Accessibility/PWA/SEOなどを監査します。citeturn1search1turn1search13  
運用上は「モバイル設定」「CPU/ネットワークスロットリング」で実施し、改善前後を比較します。citeturn1search1  

#### WebPageTest（ラボ計測）

WebPageTestは「テスト投入→結果理解」の導線をドキュメントで案内しています。citeturn1search3  
滝（waterfall）で **どのリクエストがブロックしているか** を特定しやすいのが強みです。citeturn1search3  

#### Chrome DevTools（ローカル解析）

パフォーマンスパネルはCPUプロファイルを記録・分析でき、Core Web Vitals（LCP/CLS/INP）のローカル指標も表示できます。citeturn29search0  
ネットワークパネルはキャッシュ無効化やオフラインシミュレーション等の手段を提供します。citeturn29search1  

#### Safari Web Inspector（iOS実機）

AppleはWeb Inspectorがネットワークリクエストやレイアウト/JSイベント等を確認できると説明しています。citeturn29search2  
iOS/iPadOSのSafariをMacのSafariから検査する手順（Developメニュー等）もAppleが文書化しています。citeturn2search0  

### Core Web Vitalsのしきい値と優先順位

#### 指標と目標（p75基準）

- LCP: 2.5秒以下を目標（フィールドデータは75パーセンタイル推奨）。citeturn15search1  
- CLS: 0.1未満を目標（同じくp75）。citeturn17view0  
- INP: 200ms以下が「良好」とされ、p75で評価することが示されています。citeturn16search0  
- 2024年3月12日からINPがCore Web VitalsとしてFIDを置き換えた、とweb.devが告知しています。citeturn1search0  

#### 実務の優先順位の付け方（再現性重視）

1. まずは **“悪い”領域を脱出**（LCPが極端に遅い、CLSが大きい、INPが重い）  
2. 次に **LCPのボトルネック除去**（TTFB/レンダーブロック/LCP要素）  
3. 次に **INPの主スレッド負荷低減**（JS削減、Long Task分割、重要操作のイベント最適化）  
4. 最後に **CLSの磨き込み**（画像・広告・フォント・遅延表示の安定化）  

この順序は、各指標の定義と「良好」しきい値を前提に、改善インパクトが出やすい箇所から着手するための実務的な並べ方です。citeturn15search1turn16search0turn17view0turn30search5turn5search3  

### CWV別の具体策

#### LCP対策

LCPは「最大コンテンツが描画されるまで」の指標で、2.5秒以下が目標です。citeturn15search1  
具体策は次の粒度で切り分けます。

- サーバー/配信: CDN、キャッシュ（Cache-Control/ETag）、圧縮（Brotli）でTTFB〜転送を詰める。citeturn31search3turn36search0turn36search1turn30search0  
- クリティカルリソース: レンダーブロックCSS/JSを減らす（critical CSS、分割、defer/async、不要コード削除）。citeturn30search1turn30search5turn5search3  
- LCP要素の最適化: ヒーロー画像ならWebP/AVIF＋適切なサイズ配信、**LCP候補画像はlazyを避ける**。citeturn30search2turn30search3turn2search1  

#### INP対策

INPは操作応答性で、200ms以下が良好とされています。citeturn16search0  
改善の中心は「メインスレッドの仕事を減らす」ことです。

- 初期JSを削る: コード分割（dynamic import）で起動時負荷を下げる。citeturn5search2turn5search3  
- 長いタスクを潰す: DevTools（Performance）でINP/インタラクションを確認し、重いハンドラや同期処理を分割する。citeturn29search0  

#### CLS対策

CLSは予期しないレイアウト変化で、0.1未満が目標です。citeturn17view0  
典型施策は次の通りです。

- 画像/動画/広告枠のサイズを予約する（width/height、aspect-ratio等）。citeturn17view0turn2search1  
- フォント差し替えの影響を抑える（`font-display`、フォールバック設計）。citeturn8search2turn17view0  
- 既存コンテンツの上に後からDOMを挿入しない（必要ならプレースホルダで予約）。citeturn17view0  

### フィールド計測（RUM）で“現実の遅さ”を捕まえる

web.dev/Googleは`web-vitals`ライブラリで指標測定できることを示しています。citeturn31search4turn31search0turn16search1  

```js
import { onLCP, onINP, onCLS } from 'web-vitals';

function sendToRUM(metric) {
  // 値/ID/アトリビューション等を収集し、サーバへ送る
  navigator.sendBeacon('/rum', JSON.stringify(metric));
}

onLCP(sendToRUM);
onINP(sendToRUM);
onCLS(sendToRUM);
```

フィールドデータの俯瞰にはCrUX系ツールも有用です。CrUXの可視化ページは「URL/Originがユーザーにどう経験されているか」を確認できると説明しています。citeturn16search2  

## ブラウザ/OS差異、UX、テスト、運用、チェックリスト

### iOS Safari・Android Chrome・PWA差異の比較表

| 論点 | iOS Safari / iOS PWA | Android Chrome / Android PWA | 実例・回避策 |
|---|---|---|---|
| インストール導線 | iOSは「ホーム画面に追加」が中心（ユーザー操作）。citeturn19search0turn19search1 | Chromeはインストール条件を満たすと`beforeinstallprompt`等をトリガーできる。citeturn19search2turn4search3 | iOS向けには「共有→ホーム画面に追加」案内をUI/ヘルプで提供。citeturn19search0 |
| プッシュ通知 | iOS/iPadOS 16.4で「ホーム画面Webアプリ」にWeb Push対応を追加。citeturn10search3 | 一般にChromium系PWAでPush API/Service Workerを利用。iOS同様、権限/UI設計が重要。citeturn10search3turn4search0 | 「インストール後に通知許可を促す」など段階UXにする（iOSは特に“ホーム画面追加”が前提になりやすい）。citeturn10search3turn19search0 |
| バックグラウンド同期 | 対応状況は機能ごとに差があるため要実機確認。citeturn12search1turn13search4 | Periodic Background Syncは「アプリをインストール必須」など条件あり。citeturn13search7turn13search4 | “あれば使う”設計：権限確認→失敗時は起動時更新にフォールバック。citeturn13search4turn13search7 |
| viewport/ノッチ | `viewport-fit=cover`＋`env(safe-area-inset-*)`が推奨。citeturn35view0turn8search1 | 同様にセーフエリアや動的UIを考慮（端末により異なる）。citeturn35view0turn11search5 | 固定ヘッダ/フッタはセーフエリア分のpaddingを追加。citeturn8search1turn35view0 |
| キーボード時レイアウト | `interactive-widget` やVisualViewportで「縮む領域」を明確化できる。citeturn35view0turn10search2 | VisualViewportは同様に使える。citeturn10search2 | 固定フッタ等はVisualViewport差分でpadding調整（実装例は前述）。citeturn10search2 |
| pull-to-refresh/スクロール連鎖 | `overscroll-behavior`はSafari/iOS含め広く利用可能（Safari 16+等の互換性表）。citeturn33search0 | 同様。citeturn33search0 | モーダル内スクロールは`overscroll-behavior: contain;`等で連鎖抑止。citeturn33search0 |
| ハイブリッド（WebView） | WKWebViewはアプリUIにWebを組み込むネイティブビュー。citeturn32search1 | WebViewはブラウザ機能（アドレスバー等）を含まないView。citeturn32search0 | “ブラウザで動く前提”に加え、「WebView設定/更新」「ブリッジ」「ストア配布」を運用計画に入れる。citeturn32search0turn32search1turn32search3 |

### UI/UX設計の要点

タッチ操作の基本は「十分なターゲット」と「誤タップを招かない間隔」です。Appleは44×44pt、Materialは48×48を推奨し、WCAG 2.2の理解文書では最低24×24 CSS pxを扱っています。citeturn23search0turn20search3turn20search0  
また、`touch-action`は利便性を上げる一方、ズーム等のアクセシビリティ機能を阻害し得るため、必要範囲に限定します。citeturn33search3  

キーボード表示時は、VisualViewportや`interactive-widget`で“見えている領域”の変化を扱うのが再現性の高いアプローチです。citeturn10search2turn35view0  

### テストとデバイス対応

#### 実機テストとエミュレータ/シミュレータの使い分け

- iOS: Appleは、実機（iOS/iPadOS）をMacのSafari「Develop」メニューから検査できることを説明しています。citeturn2search0  
- Safari Web Inspector自体はネットワーク・JS・レンダリングなどの分析に使えます。citeturn29search2  
- Chrome/Android: DevTools（Network/Performance）でローカル指標（LCP/CLS/INP）やネットワーク条件を切り替えられます。citeturn29search0turn29search1  

#### 市場シェアに基づく優先リスト（日本、Jan 2026）

OSシェア（モバイル、日本、2026年1月）は iOS 60.45%、Android 39.36% です。citeturn27view0  
ブラウザは Chrome 51.07%、Safari 43.23% が支配的です。citeturn28view0  
ベンダーは Apple 60.45% が最大で、Android側はGoogle/Samsung/Xiaomi/Sony等が続きます。citeturn38view0  
画面解像度（CSS px換算の代表値）は 414×896、375×812、390×844、393×852、375×667、412×915 が上位です。citeturn39view0  

iOSバージョンは Jan 2026 時点で iOS 26.2 / 26.1 と iOS 18.6 / 18.7 が上位に見えますが、誤計測補正の注記があるため“実機での現実”と突き合わせが必要です。citeturn25view0  
Androidは 16.0、15.0、14.0、13.0、12.0、8.0 Oreo が上位です。citeturn26view0  

この前提での「最小の実機セット（例）」は、次のように組むと再現性が出ます（あくまで例、要件により増減）。

- iOS（Safari/WebKit）: 画面サイズ上位（例：390×844系）＋小型（375×667系）＋最新版系（26.x）＋前世代系（18.x相当）citeturn39view0turn25view0  
- Android（Chrome/Android System WebView）: Android 16/15/14 いずれかの中級機＋旧め（12〜13相当）＋可能なら低速端末枠（8.0相当）citeturn26view0  

### デプロイと運用

#### CDNとキャッシュ制御

HTTPキャッシュの基本は `Cache-Control` や `ETag` で制御できます。citeturn36search0turn36search1  
CDNを使う場合、Cloudflareは`CDN-Cache-Control`を「中間キャッシュ（CDN）だけ別制御する」ヘッダとして説明しています。citeturn31search3  

例（静的アセット：ハッシュ付きファイル名想定）:

```http
Cache-Control: public, max-age=31536000, immutable
ETag: "..."
```

例（HTML：常に最新を取りたい）:

```http
Cache-Control: no-cache
```

#### バージョン管理（Service Workerを含む）

Workboxのprecacheは、リビジョニング差分で更新対象を決められると説明しています。citeturn36search3  
また、Service Workerのライフサイクル制御（`skipWaiting()`/`Clients.claim()`）はMDNが説明しています。citeturn36search2  

実運用の定石は「静的資産はハッシュ名で長期キャッシュ」「HTMLは短期or検証」「SW更新はユーザー影響を制御」です。citeturn36search0turn36search3turn36search2  

#### 監視（RUM＋ログ/トレース）とエラートラッキング

- RUM: `web-vitals`でLCP/INP/CLS等を送る実装がGoogleのcodelabで解説されています。citeturn31search4turn31search0  
- エラー/パフォーマンス監視: SentryはWeb Vitals計測の文脈を提示しています（ツール選定の一例）。citeturn31search1  
- トレース: OpenTelemetryはブラウザ計測が「experimental」と警告しつつ導入ガイドを提供しています（採用時は成熟度に注意）。citeturn31search2  

### 開発・QA・リリース用チェックリスト

| フェーズ | 領域 | チェック項目 | 検証方法（例） |
|---|---|---|---|
| 開発 | viewport | `width=device-width, initial-scale=1` を設定 | 実機Safari/Chromeで縮小表示が起きないか citeturn35view0 |
| 開発 | セーフエリア | `viewport-fit=cover`を使うなら`env(safe-area-inset-*)`で余白 | ノッチ/ホームインジケータでUIが欠けない citeturn35view0turn8search1 |
| 開発 | メディアクエリ | 画面幅＋`prefers-reduced-motion`等の嗜好に対応 | CSS切替を検証 citeturn3search0 |
| 開発 | タッチターゲット | 主要操作は44×44相当以上（目安） | 実機で誤タップ率/押しやすさ確認 citeturn23search0turn20search3 |
| 開発 | アクセシビリティ | 最低24×24 CSS pxを下回る操作は密集させない | WCAG観点でレビュー citeturn20search0turn20search4 |
| 開発 | 画像 | `picture`でAVIF/WebP等を提供 | Networkでフォーマット確認 citeturn8search3turn30search2 |
| 開発 | 画像 | `srcset/sizes`で端末に適切なサイズ配信 | Lighthouse/DevToolsで転送量確認 citeturn2search1 |
| 開発 | 画像 | LCP候補はlazyにしない | LCP要素特定→実装確認 citeturn30search3turn15search1 |
| 開発 | CLS | 画像/埋め込み要素はサイズ予約（width/height等） | CLSログ・Web Vitalsで確認 citeturn17view0 |
| 開発 | JS | 初期JSを削減（コード分割） | bundle分析/INP改善 citeturn5search2turn16search0 |
| 開発 | 圧縮 | Brotli/gzip等でテキスト圧縮 | レスポンスヘッダ確認 citeturn30search0 |
| 開発 | Service Worker | fetch傍受＆キャッシュ戦略を設計 | Offline/更新の検証 citeturn4search0turn3search2 |
| 開発 | PWA | `manifest`/アイコン/起動URL | インストール後の体験確認 citeturn4search3turn4search2turn19search1 |
| QA | 計測 | Lighthouse（Mobile）で改善前後比較 | スコア＋指摘事項を記録 citeturn1search1 |
| QA | 計測 | WebPageTestでwaterfall解析 | 重いリクエストを特定 citeturn1search3 |
| QA | iOS実機 | Safari Web Inspectorでネットワーク/JS/レンダリング確認 | Macから接続して検証 citeturn2search0turn29search2 |
| QA | Android実機 | DevToolsでNetwork/Performance確認 | キャッシュ無効/回線制限 citeturn29search1turn29search0 |
| リリース | セキュリティ | HSTS/CSP等のヘッダを段階導入 | Report-Only→強制へ citeturn6search1turn34search2turn3search3 |
| リリース | キャッシュ | 静的資産は長期キャッシュ、HTMLは検証 | Cache-Control/ETag確認 citeturn36search0turn36search1 |
| 運用 | RUM | web-vitalsでLCP/INP/CLSを収集 | ダッシュボード化 citeturn31search4turn31search0 |
| 運用 | 市場追随 | OS/ブラウザ/画面の分布を定期更新 | StatCounter等で見直し citeturn27view0turn28view0turn39view0 |

confidence: 中