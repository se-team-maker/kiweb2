<?php
/**
 * ICTマニュアル 外部アップローダー
 *
 * このディレクトリだけを、kiwebとは別のPHPサーバーへ配置して使用する。
 * ブラウザから受け取ったMarkdown・JSON・画像を、PHPのcURLでkiweb APIへ転送する。
 *
 * 認証情報は用途を分ける。
 *
 * - ICT_MANUAL_UPLOADER_ACCESS_KEY:
 *   登録担当者がフォームへ入力するキー。公開URLを知った第三者の利用を防ぐ。
 * - ICT_MANUAL_UPLOAD_TOKEN:
 *   このPHPサーバーがkiweb APIへ送る共有トークン。ブラウザへは絶対に出力しない。
 *
 * kiwebのログインセッションとは独立しており、このサイト自身もユーザー管理は行わない。
 */

declare(strict_types=1);

session_start();

/**
 * 画面へ値を出力する際の共通エスケープ。
 */
function uploaderH($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * API URLや秘密情報はコードへ書かず、外部サーバーの環境変数から取得する。
 */
function uploaderEnv($name)
{
    $value = getenv((string)$name);
    return $value === false ? '' : trim((string)$value);
}

/**
 * フォームの二重送信・別サイトからの意図しないPOSTを防ぐCSRFトークンを発行する。
 */
function uploaderCsrfToken()
{
    if (empty($_SESSION['ict_manual_uploader_csrf'])) {
        $_SESSION['ict_manual_uploader_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['ict_manual_uploader_csrf'];
}

/**
 * POSTされたCSRFトークンをセッション内の値と比較する。
 */
function uploaderVerifyCsrf()
{
    $posted = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    $stored = isset($_SESSION['ict_manual_uploader_csrf']) ? (string)$_SESSION['ict_manual_uploader_csrf'] : '';
    if ($posted === '' || $stored === '' || !hash_equals($stored, $posted)) {
        throw new RuntimeException('画面を再読み込みしてから、もう一度送信してください。');
    }
}

/**
 * 必須の単一アップロードファイルを確認する。
 *
 * 外部サイト側では、APIへ送る前の軽量なチェックだけを行う。内容に関する最終判断は
 * kiweb API側で必ずやり直すため、この検証だけを安全性の根拠にはしない。
 */
function uploaderRequireFile($key, $extension, $label, $maxBytes)
{
    if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
        throw new RuntimeException($label . 'を選択してください。');
    }

    $file = $_FILES[$key];
    $error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException($label . 'のアップロードに失敗しました。');
    }

    $tmpPath = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
    $name = isset($file['name']) ? basename((string)$file['name']) : '';
    $size = isset($file['size']) ? (int)$file['size'] : 0;
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException($label . 'を確認できません。');
    }
    if ($size < 1 || $size > (int)$maxBytes) {
        throw new RuntimeException($label . 'のファイルサイズが許容範囲外です。');
    }
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $extension) {
        throw new RuntimeException($label . 'は.' . $extension . 'ファイルを指定してください。');
    }

    return array(
        'tmp_path' => $tmpPath,
        'name' => $name,
        'mime' => isset($file['type']) ? (string)$file['type'] : 'application/octet-stream'
    );
}

/**
 * images[]の複数ファイル形式を、cURLへ渡しやすい配列へ変換する。
 */
function uploaderOptionalImages()
{
    if (!isset($_FILES['images']) || !is_array($_FILES['images'])) {
        return array();
    }

    $files = $_FILES['images'];
    if (!isset($files['name']) || !is_array($files['name'])) {
        return array();
    }

    $images = array();
    foreach ($files['name'] as $index => $name) {
        $error = isset($files['error'][$index]) ? (int)$files['error'][$index] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('画像のアップロードに失敗しました: ' . basename((string)$name));
        }

        $tmpPath = isset($files['tmp_name'][$index]) ? (string)$files['tmp_name'][$index] : '';
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('画像を確認できません: ' . basename((string)$name));
        }

        $images[] = array(
            'tmp_path' => $tmpPath,
            'name' => basename((string)$name),
            'mime' => isset($files['type'][$index])
                ? (string)$files['type'][$index]
                : 'application/octet-stream'
        );
    }

    return $images;
}

/**
 * kiweb APIへmultipart/form-dataをサーバー間送信する。
 *
 * APIトークンはPHP内でHTTPヘッダーへ付与するため、フォームHTMLやJavaScriptには
 * 一切含まれない。Authorizationが中継環境で欠落する場合に備え、互換ヘッダーも送る。
 */
function uploaderSend($apiUrl, $apiToken, array $metadata, array $markdown, array $images)
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHPのcURL拡張が必要です。');
    }

    // curl_file_createを使うと、PHPが各ファイルをmultipartのファイル項目として送信する。
    $postFields = array(
        'metadata' => curl_file_create($metadata['tmp_path'], 'application/json', $metadata['name']),
        'markdown' => curl_file_create($markdown['tmp_path'], 'text/markdown', $markdown['name'])
    );

    foreach ($images as $index => $image) {
        $postFields['images[' . $index . ']'] = curl_file_create(
            $image['tmp_path'],
            $image['mime'],
            $image['name']
        );
    }

    $curl = curl_init($apiUrl);
    if ($curl === false) {
        throw new RuntimeException('API接続を開始できません。');
    }

    curl_setopt_array($curl, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $apiToken,
            'X-ICT-Manual-Token: ' . $apiToken,
            'Accept: application/json'
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        // API URLの設定ミスや不正なリダイレクト先へトークンを転送しないよう追従しない。
        CURLOPT_FOLLOWLOCATION => false,
        // TLS証明書とホスト名を必ず検証する。falseへ変更しないこと。
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));

    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException('kiweb APIへ接続できません: ' . $curlError);
    }

    // APIは成功・失敗ともJSONを返す契約。HTMLなどが返れば接続先やサーバー設定の異常。
    $response = json_decode((string)$body, true);
    if (!is_array($response)) {
        throw new RuntimeException('kiweb APIから不正な応答が返されました。HTTP ' . $status);
    }

    if ($status < 200 || $status >= 300 || empty($response['success'])) {
        $error = isset($response['error']) ? (string)$response['error'] : '登録に失敗しました。';
        $requestId = isset($response['request_id']) ? ' request_id=' . (string)$response['request_id'] : '';
        throw new RuntimeException($error . $requestId);
    }

    return $response;
}

/**
 * APIが返すkiweb内の相対URLを、ブラウザで開ける絶対URLへ変換する。
 *
 * 外部アップローダーとkiwebは別オリジンのため、外部サイト基準の相対リンクにはしない。
 */
function uploaderViewUrl($apiUrl, $viewUrl)
{
    $viewUrl = trim((string)$viewUrl);
    if ($viewUrl === '' || strpos($viewUrl, '/') !== 0) {
        return $viewUrl;
    }

    $parts = parse_url((string)$apiUrl);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $origin .= ':' . (int)$parts['port'];
    }
    return $origin . $viewUrl;
}

// 3項目が揃うまで送信ボタンを無効化する。秘密値そのものはHTMLへ出力しない。
$apiUrl = uploaderEnv('ICT_MANUAL_API_URL');
$apiToken = uploaderEnv('ICT_MANUAL_UPLOAD_TOKEN');
$accessKey = uploaderEnv('ICT_MANUAL_UPLOADER_ACCESS_KEY');
$configured = $apiUrl !== '' && strlen($apiToken) >= 32 && strlen($accessKey) >= 12;
$success = '';
$error = '';
$viewUrl = '';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$configured) {
            throw new RuntimeException('アップローダーの環境変数が設定されていません。');
        }

        // まずブラウザからこの外部サイトへのPOSTが正当か確認する。
        uploaderVerifyCsrf();

        $postedAccessKey = isset($_POST['access_key']) ? (string)$_POST['access_key'] : '';
        if ($postedAccessKey === '' || !hash_equals($accessKey, $postedAccessKey)) {
            // 比較は一定時間型のhash_equals()を使い、失敗時は総当たりを少し遅らせる。
            usleep(300000);
            throw new RuntimeException('アクセスキーが正しくありません。');
        }

        // 外部サイトで明らかな入力不備を弾き、詳細な検証はkiweb APIへ任せる。
        $metadata = uploaderRequireFile('metadata', 'json', 'メタ情報JSON', 262144);
        $markdown = uploaderRequireFile('markdown', 'md', 'Markdown', 1048576);
        $images = uploaderOptionalImages();
        if (count($images) > 20) {
            throw new RuntimeException('画像は20枚以下にしてください。');
        }

        // ここで初めて、ブラウザには見せていないAPIトークンを付けてkiwebへ転送する。
        $response = uploaderSend($apiUrl, $apiToken, $metadata, $markdown, $images);
        $manualId = isset($response['manual']['id']) ? (string)$response['manual']['id'] : '';
        $success = 'ICTマニュアルを公開しました。ID: ' . $manualId;
        $viewUrl = uploaderViewUrl(
            $apiUrl,
            isset($response['view_url']) ? (string)$response['view_url'] : ''
        );
        // 成功済みフォームの再送信に同じCSRFトークンを使わせない。
        $_SESSION['ict_manual_uploader_csrf'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$csrfToken = uploaderCsrfToken();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>ICTマニュアル公開</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --primary: #153f68;
      --accent: #1f725e;
      --background: #eef3f6;
      --surface: #fff;
      --text: #172033;
      --muted: #5d6878;
      --border: #c6d0da;
      --danger: #991b1b;
      --danger-bg: #fee2e2;
      --success: #166534;
      --success-bg: #dcfce7;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      background: var(--background);
      color: var(--text);
      font-family: Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
      line-height: 1.6;
    }

    button,
    input {
      font: inherit;
    }

    .page {
      width: min(760px, calc(100% - 32px));
      margin: 0 auto;
      padding: 28px 0;
    }

    h1 {
      margin: 0;
      color: var(--primary);
      font-size: 1.65rem;
    }

    .lead {
      margin: 5px 0 20px;
      color: var(--muted);
    }

    .panel {
      padding: 20px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--surface);
    }

    .field {
      display: grid;
      gap: 6px;
      margin-bottom: 16px;
    }

    label {
      font-size: 0.9rem;
      font-weight: 700;
    }

    input[type="password"],
    input[type="file"] {
      width: 100%;
      min-height: 42px;
      padding: 8px 10px;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: #fff;
      color: var(--text);
    }

    .hint {
      margin: 0;
      color: var(--muted);
      font-size: 0.84rem;
    }

    .message {
      margin-bottom: 16px;
      padding: 12px 14px;
      border-radius: 8px;
      overflow-wrap: anywhere;
    }

    .message.error {
      background: var(--danger-bg);
      color: var(--danger);
    }

    .message.success {
      background: var(--success-bg);
      color: var(--success);
    }

    .message a {
      color: inherit;
      font-weight: 700;
    }

    .actions {
      display: flex;
      justify-content: flex-end;
    }

    .submit-button {
      min-height: 42px;
      padding: 8px 18px;
      border: 0;
      border-radius: 6px;
      background: var(--primary);
      color: #fff;
      cursor: pointer;
      font-weight: 700;
    }

    .submit-button:disabled {
      cursor: not-allowed;
      opacity: 0.55;
    }
  </style>
</head>
<body>
  <main class="page">
    <h1>ICTマニュアル公開</h1>
    <p class="lead">Markdown、JSON、使用する画像を送信するとkiwebへ即時反映します。</p>

    <?php if (!$configured): ?>
      <div class="message error">サーバー設定が未完了です。環境変数を確認してください。</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="message error"><?= uploaderH($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
      <div class="message success">
        <?= uploaderH($success) ?>
        <?php if ($viewUrl !== ''): ?>
          <a href="<?= uploaderH($viewUrl) ?>" target="_blank" rel="noopener">表示を確認</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form class="panel" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= uploaderH($csrfToken) ?>">

      <div class="field">
        <label for="access_key">アクセスキー</label>
        <input id="access_key" name="access_key" type="password" autocomplete="current-password" required>
      </div>

      <div class="field">
        <label for="metadata">メタ情報JSON</label>
        <input id="metadata" name="metadata" type="file" accept=".json,application/json" required>
      </div>

      <div class="field">
        <label for="markdown">Markdown</label>
        <input id="markdown" name="markdown" type="file" accept=".md,text/markdown,text/plain" required>
      </div>

      <div class="field">
        <label for="images">画像</label>
        <input id="images" name="images[]" type="file" accept=".png,.jpg,.jpeg,.gif,.webp,image/png,image/jpeg,image/gif,image/webp" multiple>
        <p class="hint">必要な画像だけ選択してください。最大20枚、1枚5MBまでです。</p>
      </div>

      <div class="actions">
        <button class="submit-button" type="submit"<?= $configured ? '' : ' disabled' ?>>公開する</button>
      </div>
    </form>
  </main>
</body>
</html>
