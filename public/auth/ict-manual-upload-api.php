<?php
/**
 * ICTマニュアル 外部アップロードAPI
 *
 * 外部アップローダーのPHPサーバーから、次のmultipart/form-dataを受け取る。
 *
 * - metadata: 1件分のメタ情報JSON
 * - markdown: 本文Markdown
 * - images[]: Markdownで使用する画像（任意・複数）
 *
 * kiwebのログインセッションは使用しない。外部サーバーだけが保持する共有トークンを
 * Authorizationヘッダーで照合し、検証・保存処理はIctManualPublisherへ委譲する。
 * このファイルの責任は、HTTPリクエストの受付、認証、アップロード形式の確認、
 * HTTPステータスを含むJSONレスポンスの返却まで。
 */

declare(strict_types=1);

define('KIWEB_ROOT', dirname(__DIR__, 2));
define('AUTH_APP_ROOT', KIWEB_ROOT . '/app/Auth');
define('AUTH_ICT_MANUAL_ROOT', KIWEB_ROOT . '/app/Documents/ict-manual');

require_once AUTH_APP_ROOT . '/vendor/autoload.php';

use App\Service\IctManualPublisher;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(AUTH_APP_ROOT);
$dotenv->safeLoad();

/**
 * APIレスポンスをJSONで返して処理を終了する。
 *
 * 認証情報や入力内容をブラウザ・中継キャッシュへ残さないようno-storeを指定する。
 */
function ictManualUploadJson(array $data, $status)
{
    http_response_code((int)$status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * リクエストからAPIトークンを取得する。
 *
 * 通常はAuthorization: Bearerを使う。共有サーバーなどでAuthorizationヘッダーが
 * PHPへ渡らない場合に備え、REDIRECT_HTTP_AUTHORIZATIONと
 * X-ICT-Manual-Tokenも受け付ける。
 */
function ictManualUploadBearerToken()
{
    $header = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = (string)$_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (preg_match('/\ABearer\s+(.+)\z/i', trim($header), $matches) === 1) {
        return trim((string)$matches[1]);
    }

    return isset($_SERVER['HTTP_X_ICT_MANUAL_TOKEN'])
        ? trim((string)$_SERVER['HTTP_X_ICT_MANUAL_TOKEN'])
        : '';
}

/**
 * HTTPS経由のリクエストか判定する。
 *
 * リバースプロキシ配下ではPHPからHTTPSが見えないことがあるため、
 * X-Forwarded-Protoも確認する。ヘッダーを信頼してよい構成かは本番サーバー側で担保する。
 */
function ictManualUploadIsHttps()
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https';
}

/**
 * true/false形式の環境変数を読み込む。
 *
 * 値が未設定または解釈不能な場合は、呼び出し側が指定した安全側の既定値を返す。
 */
function ictManualUploadBoolEnv($name, $default)
{
    $raw = ictManualUploadEnv($name);
    if ($raw === '') {
        return (bool)$default;
    }

    $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $value === null ? (bool)$default : $value;
}

/**
 * phpdotenvが設定する$_ENVと、OS・Webサーバーが設定するgetenv()の両方に対応する。
 */
function ictManualUploadEnv($name)
{
    if (array_key_exists($name, $_ENV)) {
        return trim((string)$_ENV[$name]);
    }

    $value = getenv((string)$name);
    return $value === false ? '' : trim((string)$value);
}

/**
 * metadataアップロードを読み、1件分の連想配列へ変換する。
 *
 * ここではHTTPアップロードとして最低限正しいかだけを確認する。
 * 各項目の必須・文字数・targetなどの業務ルールはPublisher側で検証する。
 */
function ictManualUploadReadMetadata()
{
    if (!isset($_FILES['metadata']) || !is_array($_FILES['metadata'])) {
        throw new RuntimeException('metadata JSONファイルを選択してください。');
    }

    $file = $_FILES['metadata'];
    $error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('metadata JSONのアップロードに失敗しました。');
    }

    $tmpPath = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
    $size = isset($file['size']) ? (int)$file['size'] : 0;
    $name = isset($file['name']) ? basename((string)$file['name']) : '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('metadata JSONを確認できません。');
    }
    if ($size < 1 || $size > 262144) {
        throw new RuntimeException('metadata JSONは256KB以下にしてください。');
    }
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
        throw new RuntimeException('metadataにはJSONファイルを指定してください。');
    }

    $raw = file_get_contents($tmpPath);
    if ($raw === false || preg_match('//u', $raw) !== 1) {
        throw new RuntimeException('metadata JSONはUTF-8で指定してください。');
    }

    $metadata = json_decode($raw, true);
    if (!is_array($metadata) || array_values($metadata) === $metadata || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('metadata JSONは1件分のオブジェクト形式で指定してください。');
    }

    return $metadata;
}

/**
 * Markdownアップロードを確認し、PHPが作成した一時ファイルのパスを返す。
 *
 * 元のファイル名は保存には使わない。最終的な保存名はPublisherがJSONのidから決定する。
 */
function ictManualUploadReadMarkdownPath()
{
    if (!isset($_FILES['markdown']) || !is_array($_FILES['markdown'])) {
        throw new RuntimeException('Markdownファイルを選択してください。');
    }

    $file = $_FILES['markdown'];
    $error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Markdownファイルのアップロードに失敗しました。');
    }

    $tmpPath = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
    $name = isset($file['name']) ? basename((string)$file['name']) : '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Markdownファイルを確認できません。');
    }
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'md') {
        throw new RuntimeException('markdownには.mdファイルを指定してください。');
    }

    return $tmpPath;
}

/**
 * PHPの複数ファイルアップロード形式を、Publisherが扱いやすい配列へ変換する。
 *
 * MIMEタイプや画像実体の確認は、クライアント申告値を信用せずPublisher側で行う。
 */
function ictManualUploadReadImages()
{
    if (!isset($_FILES['images'])) {
        return array();
    }

    $files = $_FILES['images'];
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
        throw new RuntimeException('画像アップロード情報が不正です。');
    }

    $images = array();
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $error = isset($files['error'][$i]) ? (int)$files['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('画像のアップロードに失敗しました: ' . basename((string)$files['name'][$i]));
        }

        $tmpPath = isset($files['tmp_name'][$i]) ? (string)$files['tmp_name'][$i] : '';
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('画像ファイルを確認できません。');
        }

        $images[] = array(
            'tmp_path' => $tmpPath,
            'name' => basename((string)$files['name'][$i])
        );
    }

    return $images;
}

// request_idは利用者からの問い合わせとサーバーログを結び付けるために毎回発行する。
// トークンや本文などの秘密情報・内容そのものはログへ出力しない。
$requestId = bin2hex(random_bytes(8));
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : 'unknown';

try {
    // このAPIはファイル登録専用。GETなどは処理せず、呼び出し方法を明示して拒否する。
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        ictManualUploadJson(array('success' => false, 'error' => 'Method not allowed', 'request_id' => $requestId), 405);
    }

    if (ictManualUploadBoolEnv('ICT_MANUAL_UPLOAD_REQUIRE_HTTPS', true) && !ictManualUploadIsHttps()) {
        ictManualUploadJson(array('success' => false, 'error' => 'HTTPS is required', 'request_id' => $requestId), 400);
    }

    $expectedToken = ictManualUploadEnv('ICT_MANUAL_UPLOAD_TOKEN');
    $providedToken = ictManualUploadBearerToken();
    if (strlen($expectedToken) < 32 || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        // 応答時間からトークンの一致度を推測されにくくするため、比較にはhash_equals()を使う。
        // 短い待機は機械的な総当たりの速度も抑える。
        usleep(300000);
        error_log('[ict-manual-upload] request=' . $requestId . ' ip=' . $clientIp . ' result=unauthorized');
        ictManualUploadJson(array('success' => false, 'error' => 'Unauthorized', 'request_id' => $requestId), 401);
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 34603008) {
        throw new RuntimeException('アップロード全体は33MB以下にしてください。');
    }

    // HTTP層でファイルを取り出した後は、検証と保存をサービスクラスへまとめて任せる。
    $metadata = ictManualUploadReadMetadata();
    $markdownPath = ictManualUploadReadMarkdownPath();
    $images = ictManualUploadReadImages();

    $baseDir = AUTH_ICT_MANUAL_ROOT;
    $result = IctManualPublisher::publish($baseDir, $metadata, $markdownPath, $images);

    error_log(
        '[ict-manual-upload] request=' . $requestId
        . ' ip=' . $clientIp
        . ' result=success id=' . $result['id']
        . ' images=' . $result['image_count']
    );

    ictManualUploadJson(array(
        'success' => true,
        'manual' => $result,
        'view_url' => '/kiweb/public/auth/ict-manual.php#manual-' . rawurlencode($result['id']),
        'request_id' => $requestId
    ), 200);
} catch (RuntimeException $e) {
    // 入力不備や保存条件の不一致は、利用者が修正できるエラーとして422を返す。
    error_log(
        '[ict-manual-upload] request=' . $requestId
        . ' ip=' . $clientIp
        . ' result=validation_error message=' . str_replace(array("\r", "\n"), ' ', $e->getMessage())
    );
    ictManualUploadJson(array(
        'success' => false,
        'error' => $e->getMessage(),
        'request_id' => $requestId
    ), 422);
} catch (Throwable $e) {
    // 想定外の内部エラーの詳細はログだけに残し、API利用者には公開しない。
    error_log(
        '[ict-manual-upload] request=' . $requestId
        . ' ip=' . $clientIp
        . ' result=server_error message=' . str_replace(array("\r", "\n"), ' ', $e->getMessage())
    );
    ictManualUploadJson(array(
        'success' => false,
        'error' => 'Internal server error',
        'request_id' => $requestId
    ), 500);
}
