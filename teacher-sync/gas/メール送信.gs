/**
 * フォーム送信時に実行されるトリガー関数。
 * e.values の列対応:
 * A列(index 0): タイムスタンプ
 * B列(index 1): メールアドレス
 * E列(index 4): 漢字氏名
 */
function onFormSubmit(e) {
  // イベントが不正な場合は何もしない
  if (!e || !e.values) {
    return;
  }

  // B列: 送信先メールアドレス
  var email = String(e.values[1] || '').trim();

  // E列: 氏名（空白除去前）
  var rawName = String(e.values[4] || '').trim();

  // 半角/全角スペースをすべて除去してURLパラメータ用に整形
  var normalizedName = rawName.replace(/[ \u3000]/g, '');

  // メールアドレスまたは氏名が空なら送信しない
  if (!email || !normalizedName) {
    return;
  }

  // アカウント作成URLを生成（nameはURLエンコード必須）
  var baseUrl = 'https://system.kyotoijuku.com/kiweb/teacher-auth/public/login.php';
  var accountUrl = baseUrl + '?name=' + encodeURIComponent(normalizedName);

  // 件名
  var subject = '【京都医塾】アカウント作成のご案内';

  // 本文（テキスト版 / HTML版）を生成
  var plainTextBody = buildPlainTextBody_(rawName, accountUrl);
  var htmlBody = buildHtmlBody_(rawName, accountUrl);

  // HTMLメール送信（第3引数はプレーンテキスト本文）
  GmailApp.sendEmail(email, subject, plainTextBody, {
    htmlBody: htmlBody,
    name: '京都医塾'
  });
}

/**
 * プレーンテキスト本文を作成。
 */
function buildPlainTextBody_(rawName, accountUrl) {
  return [
    rawName + ' 様',
    '',
    'お世話になっております。京都医塾です。',
    '以下のURLよりアカウント作成をお願いいたします。',
    '',
    accountUrl,
    '',
    '※このURLはお一人様専用です。',
    '※このメールは自動送信です。'
  ].join('\n');
}

/**
 * HTML本文を作成。
 * メールクライアント互換性のため、テーブル+インラインスタイルで構成。
 */
function buildHtmlBody_(rawName, accountUrl) {
  var escapedName = escapeHtml(rawName);
  var escapedUrl = escapeHtml(accountUrl);
  var logoUrl = 'https://system.kyotoijuku.com/kiweb/teacher-auth/public/京都医塾logo.png';

  return '' +
    '<!doctype html>' +
    '<html lang="ja">' +
    '<head>' +
      '<meta charset="UTF-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1.0">' +
      '<title>【京都医塾】アカウント作成のご案内</title>' +
    '</head>' +
    '<body style="margin:0; padding:0; background-color:#f5f7fb; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',\'Hiragino Kaku Gothic ProN\',\'Yu Gothic\',sans-serif; color:#1f2937;">' +
      '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f5f7fb; padding:24px 12px;">' +
        '<tr>' +
          '<td align="center">' +
            '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:640px;">' +
              '<tr>' +
                '<td align="center" style="padding:8px 0 18px 0;">' +
                  '<img src="' + logoUrl + '" alt="京都医塾" width="200" style="display:block; width:200px; height:auto; border:0; outline:none; text-decoration:none;">' +
                '</td>' +
              '</tr>' +
              '<tr>' +
                '<td style="background-color:#ffffff; border-radius:14px; box-shadow:0 6px 20px rgba(15,23,42,0.08); padding:30px 24px;">' +
                  '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">' +
                    '<tr>' +
                      '<td style="font-size:20px; line-height:1.6; font-weight:700; color:#111827; padding-bottom:16px;">' + escapedName + ' 様</td>' +
                    '</tr>' +
                    '<tr>' +
                      '<td style="font-size:15px; line-height:1.9; color:#374151; padding-bottom:18px;">' +
                        'お世話になっております。京都医塾です。<br>' +
                        '以下のボタンよりアカウント作成をお願いいたします。' +
                      '</td>' +
                    '</tr>' +
                    '<tr>' +
                      '<td align="center" style="padding:10px 0 20px 0;">' +
                        '<a href="' + escapedUrl + '" style="display:inline-block; background-color:#AF1E2B; color:#ffffff; text-decoration:none; font-size:16px; font-weight:700; line-height:1; padding:16px 28px; border-radius:10px;">アカウントを作成する</a>' +
                      '</td>' +
                    '</tr>' +
                    '<tr>' +
                      '<td style="font-size:13px; line-height:1.8; color:#4b5563; padding-bottom:12px;">' +
                        'ボタンが表示されない場合は、以下のURLをブラウザに貼り付けてアクセスしてください。' +
                      '</td>' +
                    '</tr>' +
                    '<tr>' +
                      '<td style="word-break:break-all; font-size:13px; line-height:1.8;">' +
                        '<a href="' + escapedUrl + '" style="color:#AF1E2B; text-decoration:underline;">' + escapedUrl + '</a>' +
                      '</td>' +
                    '</tr>' +
                    '<tr>' +
                      '<td style="font-size:12px; line-height:1.8; color:#6b7280; padding-top:22px; border-top:1px solid #e5e7eb;">' +
                        '※このURLはお一人様専用です。<br>' +
                        '※このメールは自動送信です。' +
                      '</td>' +
                    '</tr>' +
                  '</table>' +
                '</td>' +
              '</tr>' +
            '</table>' +
          '</td>' +
        '</tr>' +
      '</table>' +
    '</body>' +
    '</html>';
}

/**
 * HTMLエスケープ（XSS対策）。
 */
function escapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
