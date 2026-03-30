<?php
// メール送信テストスクリプト
$searchPaths = [
    __DIR__ . '/public/bootstrap.php', // loginフォルダ直下に置いた場合
    __DIR__ . '/bootstrap.php',        // publicフォルダ内に置いた場合
    dirname(__DIR__) . '/public/bootstrap.php' // さらに上の階層に置いた場合
];

$bootstrapLoaded = false;
foreach ($searchPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $bootstrapLoaded = true;
        break;
    }
}

if (!$bootstrapLoaded) {
    die("エラー: bootstrap.php が見つかりません。<br>現在のディレクトリ: " . __DIR__ . "<br>login/public/bootstrap.php が存在するか確認してください。");
}

$to = $_GET['to'] ?? '';

if (!$to) {
    echo "使用法: ?to=your-email@example.com";
    exit;
}

$subject = "メール送信テスト";
$body = "これはテストメールです。\n正しく受信できていれば設定はOKです。";

use App\Auth\EmailAuth;

$from = $_ENV['MAIL_FROM'] ?? 'noreply@example.com';
$fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Test System';

echo "設定情報:<br>";
echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? '未設定') . "<br>";
echo "From: {$from}<br>";
echo "To: {$to}<br>";

$headers = [
    'From' => "{$fromName} <{$from}>",
    'Reply-To' => $from,
    'Content-Type' => 'text/plain; charset=UTF-8',
    'X-Mailer' => 'PHP/' . phpversion()
];

if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
    echo "<h3>警告: APP_ENVが development です！メールは送信されずログに出力されます。</h3>";
}

$result = mail($to, $subject, $body, $headers, "-f" . $from);

if ($result) {
    echo "<h3>送信成功 (trueが返されました)</h3>";
    echo "受信トレイ（迷惑メール含む）を確認してください。";
} else {
    echo "<h3>送信失敗 (falseが返されました)</h3>";
    echo "サーバーのメール設定またはFromアドレスを確認してください。";
}
