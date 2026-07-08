/**
 * スプレッドシート強制同期メニュー
 * 使い方: スプレッドシートにバインドして配置
 */

const API_BASE_URL = 'https://system.kyotoijuku.com/kiweb/room-booking/api/index.php';

/**
 * スプレッドシートを開いた時にカスタムメニューを追加
 */
function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('🔄 DB同期')
    .addItem('📥 DB→シート完全同期', 'rebuildFromDatabase')
    .addSeparator()
    .addItem('🔍 重複削除', 'dedupeReservations')
    .addItem('📊 同期ステータス確認', 'checkSyncStatus')
    .addToUi();
}

/**
 * DBからスプレッドシートを完全再構築
 */
function rebuildFromDatabase() {
  const ui = SpreadsheetApp.getUi();
  
  // 確認ダイアログ
  const response = ui.alert(
    'DB同期確認',
    'データベースからスプレッドシートを完全に上書き同期します。\n\n' +
    '※ シート上の手動編集内容は失われます。\n' +
    '実行しますか？',
    ui.ButtonSet.YES_NO
  );
  
  if (response !== ui.Button.YES) {
    ui.alert('キャンセルしました');
    return;
  }
  
  try {
    ui.alert('同期中', '処理を開始します。完了まで数秒お待ちください...', ui.ButtonSet.OK);
    
    const result = callApi('rebuildSheet');
    
    if (result.success) {
      const data = result.data;
      ui.alert(
        '✅ 同期完了',
        `同期が完了しました！\n\n` +
        `• 会議室: ${data.rooms}件\n` +
        `• 予約: ${data.reservations}件\n` +
        `• 日別JSON: ${data.dateJsons}件\n` +
        `• 処理時間: ${data.executionTimeMs}ms`,
        ui.ButtonSet.OK
      );
    } else {
      ui.alert('❌ エラー', 'エラーが発生しました:\n' + result.error, ui.ButtonSet.OK);
    }
  } catch (e) {
    ui.alert('❌ エラー', 'APIの呼び出しに失敗しました:\n' + e.message, ui.ButtonSet.OK);
  }
}

/**
 * Reservationsシートの重複を削除
 */
function dedupeReservations() {
  const ui = SpreadsheetApp.getUi();
  
  try {
    const result = callApi('dedupeReservationsSheet');
    
    if (result.success) {
      const data = result.data;
      if (data.deletedRows > 0) {
        ui.alert('✅ 完了', `重複行を ${data.deletedRows}件 削除しました`, ui.ButtonSet.OK);
      } else {
        ui.alert('✅ 完了', '重複行はありませんでした', ui.ButtonSet.OK);
      }
    } else {
      ui.alert('❌ エラー', 'エラーが発生しました:\n' + result.error, ui.ButtonSet.OK);
    }
  } catch (e) {
    ui.alert('❌ エラー', 'APIの呼び出しに失敗しました:\n' + e.message, ui.ButtonSet.OK);
  }
}

/**
 * 同期ステータスを確認
 */
function checkSyncStatus() {
  const ui = SpreadsheetApp.getUi();
  
  try {
    const response = UrlFetchApp.fetch(API_BASE_URL + '?action=getSheetsSyncStatus', {
      method: 'get',
      muteHttpExceptions: true
    });
    
    const result = JSON.parse(response.getContentText());
    
    if (result.success) {
      const data = result.data;
      let message = `同期ステータス\n\n`;
      message += `• 有効: ${data.enabled ? 'はい' : 'いいえ'}\n`;
      message += `• 同期モード: ${data.syncMode}\n`;
      
      if (data.status) {
        message += `• キュー待ち: ${data.status.pending}件\n`;
        message += `• 処理中: ${data.status.processing}件\n`;
        message += `• 失敗: ${data.status.failed}件\n`;
      }
      
      ui.alert('📊 同期ステータス', message, ui.ButtonSet.OK);
    } else {
      ui.alert('❌ エラー', 'エラーが発生しました:\n' + result.error, ui.ButtonSet.OK);
    }
  } catch (e) {
    ui.alert('❌ エラー', 'APIの呼び出しに失敗しました:\n' + e.message, ui.ButtonSet.OK);
  }
}

/**
 * APIを呼び出すヘルパー関数
 */
function callApi(action, data) {
  const payload = { action: action };
  if (data) {
    payload.data = data;
  }
  
  const options = {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  };
  
  const response = UrlFetchApp.fetch(API_BASE_URL, options);
  const text = response.getContentText();
  
  try {
    return JSON.parse(text);
  } catch (e) {
    throw new Error(
      'APIがJSONを返しませんでした。\n' +
      'HTTP: ' + response.getResponseCode() + '\n' +
      '先頭: ' + text.slice(0, 200)
    );
  }
}

