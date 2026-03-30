/**
 * JUST.DBからレコードを取得してGoogleスプレッドシートに貼り付ける
 * パネル指定時の配列レスポンス形式に対応
 */

// 設定
const CONFIG = {
  API_KEY: 'tE3hq0tZnmDeW8FlDd68r6um90fBB7Ks',
  BASE_URL: 'https://kyotoijuku.just-db.com/sites/api/services/v1/tables/',
  TABLE_NAME: 'table_1698862183',
  PANEL_NAME: 'panel_1766129549',
  FILTER_NAME: 'filter_1766130719',
  LIMIT: 100  // 一度に取得するレコード数（最大100）
};

/**
 * JUST.DB APIからレコードを取得
 * @param {number} offset - 取得開始位置
 * @return {Array} レコード配列
 */
function fetchRecordsFromJustDB(offset = 0) {
  const url = `${CONFIG.BASE_URL}${CONFIG.TABLE_NAME}/records/` +
              `?panelName=${CONFIG.PANEL_NAME}` +
              `&filterName=${CONFIG.FILTER_NAME}` +
              `&offset=${offset}` +
              `&limit=${CONFIG.LIMIT}`;
  
  const options = {
    method: 'get',
    headers: {
      'Authorization': `Bearer ${CONFIG.API_KEY}`,
      'Content-Type': 'application/json'
    },
    muteHttpExceptions: true
  };
  
  try {
    Logger.log(`リクエストURL: ${url}`);
    const response = UrlFetchApp.fetch(url, options);
    const statusCode = response.getResponseCode();
    const responseText = response.getContentText();
    
    Logger.log(`ステータスコード: ${statusCode}`);
    
    // ステータスコードのチェック
    if (statusCode !== 200 && statusCode !== 201) {
      Logger.log(`エラー: ステータスコード ${statusCode}`);
      Logger.log(`レスポンス: ${responseText}`);
      return null;
    }
    
    const data = JSON.parse(responseText);
    
    // レスポンスが配列かオブジェクトか判定
    if (Array.isArray(data)) {
      // 配列の場合（パネル指定時の形式）
      Logger.log(`${data.length}件のレコードを取得しました（offset: ${offset}）`);
      return data;
    } else if (data.records) {
      // オブジェクト形式の場合（標準形式）
      Logger.log(`${data.records.length}件のレコードを取得しました（offset: ${offset}）`);
      return data.records;
    } else {
      Logger.log('警告: レスポンス形式が不明です');
      Logger.log(`レスポンス: ${responseText.substring(0, 500)}`);
      return null;
    }
    
  } catch (error) {
    Logger.log(`API呼び出しエラー: ${error.message}`);
    Logger.log(`エラー詳細: ${error.stack}`);
    return null;
  }
}

/**
 * ISO8601形式の日時をyyyy-MM-dd HH:mm形式に変換
 * @param {string} isoString - ISO8601形式の日時文字列
 * @return {string} フォーマット済みの日時文字列
 */
function formatDateTime(isoString) {
  const date = new Date(isoString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day} ${hours}:${minutes}`;
}

/**
 * レコードデータから値を抽出
 * フィールドタイプに応じた処理を行う
 * @param {*} fieldValue - フィールドの値
 * @return {string} 抽出された値
 */
function extractFieldValue(fieldValue) {
  if (fieldValue === null || fieldValue === undefined) {
    return '';
  }
  
  // 配列の場合（自動採番フィールドなど）
  if (Array.isArray(fieldValue)) {
    if (fieldValue.length === 0) {
      return '';
    }
    // 配列の最初の要素を取得
    return fieldValue[0] || '';
  }
  
  // オブジェクトの場合
  if (typeof fieldValue === 'object') {
    // 日付時刻フィールドなど、値が入っている可能性のあるキーをチェック
    if (fieldValue.value !== undefined) {
      return fieldValue.value;
    }
    return JSON.stringify(fieldValue);
  }
  
  // return String(fieldValue);
  // ISO8601形式の日時文字列をチェック（例: 2025-12-19T09:00+09:00）
  const stringValue = String(fieldValue);
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(stringValue)) {
    return formatDateTime(stringValue);
  }
  
  return stringValue;  
}

/**
 * レコードをスプレッドシートに貼り付け
 * @param {Array} records - レコード配列
 * @param {Sheet} sheet - 対象シート
 * @param {Array} fieldNames - フィールド識別名の配列（列順）
 */
function writeRecordsToSheet(records, sheet, fieldNames) {
  if (!records || records.length === 0) {
    Logger.log('書き込むレコードがありません');
    return;
  }
  
  const data = [];
  
  // レコードをループして行データを作成
  for (const recordObj of records) {
    // パネル指定時は直接レコードオブジェクトが入っている
    // 標準形式の場合は recordObj.record にアクセス
    const record = recordObj.record || recordObj;
    
    const row = [];
    
    // 各フィールドの値を順番に取得
    for (const fieldName of fieldNames) {
      const value = extractFieldValue(record[fieldName]);
      row.push(value);
    }
    
    data.push(row);
  }
  
  // シートに書き込み
  if (data.length > 0) {
    const startRow = sheet.getLastRow() + 1;
    sheet.getRange(startRow, 1, data.length, data[0].length).setValues(data);
    Logger.log(`${data.length}行をシートに書き込みました`);
  }
}

/**
 * APIテスト関数
 * この関数を最初に実行してAPIレスポンスを確認してください
 */
function testAPI() {
  Logger.log('=== APIテスト開始 ===');
  
  const records = fetchRecordsFromJustDB(0);
  
  if (records && records.length > 0) {
    Logger.log(`✓ API接続成功: ${records.length}件取得`);
    Logger.log(`✓ レスポンス形式: ${Array.isArray(records) ? '配列' : 'オブジェクト'}`);
    
    // 最初のレコードの構造を確認
    const firstRecord = records[0].record || records[0];
    const allKeys = Object.keys(records[0]);
    
    Logger.log('=== レコードオブジェクトのキー（全体） ===');
    Logger.log(allKeys.join(', '));
    
    Logger.log('=== フィールドデータのキー（field_で始まるもの） ===');
    const fieldKeys = Object.keys(firstRecord).filter(key => key.startsWith('field_'));
    
    if (fieldKeys.length > 0) {
      fieldKeys.forEach((fieldName, index) => {
        const value = firstRecord[fieldName];
        const preview = JSON.stringify(value).substring(0, 50);
        Logger.log(`${index + 1}. ${fieldName}: ${preview}`);
      });
    } else {
      Logger.log('field_で始まるキーが見つかりません');
      Logger.log('=== 全キーの値（最初の5個） ===');
      Object.keys(firstRecord).slice(0, 5).forEach((key, index) => {
        const value = firstRecord[key];
        const preview = JSON.stringify(value).substring(0, 50);
        Logger.log(`${index + 1}. ${key}: ${preview}`);
      });
    }
  } else {
    Logger.log('✗ API接続失敗');
  }
  
  Logger.log('=== テスト終了 ===');
}

/**
 * メイン処理
 * 全レコードを取得してスプレッドシートに貼り付け
 */
function main() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('rawData');
  
  // ヘッダー行の設定（必要に応じて変更）
  // 実際のフィールド識別名を指定してください
  const fieldNames = [
    'field_1698900331',      // 実施
    'field_1738483016',      // 当初開始日時
    'field_1766109609',      // 当初予定業務No
    'field_1698862300',      // 予定開始日時
    'field_1698862327',      // 予定終了日時
    'field_1698864130',      // 予定業務No
    'field_1697693160',      // 講師名
    'field_1724795187',      // 授業形態詳細
    'field_1697693253',      // 授業名
    'field_1767851507',      // 変更理由
    'field_1724801182',      // 生徒情報(カンマ区切り)
    'field_1724795908',      // E教室No
    'field_1697695649',      // イレギュラー教室名
    'field_1699080449',      // 出欠
    'field_1698899943',      // 受講形態
    'field_1698862499',      // 生徒No
    'field_1697693912',      // 生徒名
    'field_1697693952',      // 生徒種別
    'field_1698862199'       // 授業No
  ];
  
  sheet.clear();
  // ヘッダーを書き込み（初回のみ）
  if (sheet.getLastRow() === 0) {
    const headers = [
      '実施',
      '当初開始日時',
      '当初予定業務No',
      '予定開始日時',
      '予定終了日時',
      '予定業務No',
      '講師名',
      '授業形態詳細',
      '授業名',
      '変更理由',
      '生徒情報(カンマ区切り)',
      'E教室No',
      'イレギュラー教室名',
      '出欠',
      '受講形態',
      '生徒No',
      '生徒名',
      '生徒種別',
      '授業No'
    ];
    sheet.getRange(2, 1, 1, headers.length).setValues([headers]);
    sheet.getRange(2, 1, 1, headers.length).setFontWeight('bold');
  }
  
  let offset = 0;
  let hasMoreRecords = true;
  let totalRecords = 0;
  
  // 全レコードを取得するまでループ
  while (hasMoreRecords) {
    // レート制限対策: 2.1秒待機（30リクエスト/分以下に保つ）
    if (offset > 0) {
      Utilities.sleep(2100);
    }
    
    const records = fetchRecordsFromJustDB(offset);
    
    if (!records || records.length === 0) {
      Logger.log('これ以上レコードがありません');
      break;
    }
    
    // レコードをシートに書き込み
    writeRecordsToSheet(records, sheet, fieldNames);
    totalRecords += records.length;
    
    // 次のページがあるかチェック
    if (records.length < CONFIG.LIMIT) {
      hasMoreRecords = false;
    } else {
      offset += CONFIG.LIMIT;
    }
  }

  ts=Utilities.formatDate(new Date(),'JST','yyyy-MM-dd HH:mm');
  ss.getSheetByName('メイン').getRange('C4').setValue(ts);
  ss.getSheetByName('rawData').getRange('C1').setValue(ts);
}

/**
 * 特定のoffsetから指定件数だけ取得する簡易版
 * @param {number} offset - 取得開始位置（デフォルト: 0）
 * @param {number} limit - 取得件数（デフォルト: 10）
 */
function fetchAndWriteOnce(offset = 0, limit = 10) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getActiveSheet();
  
  // 設定を一時的に変更
  const originalLimit = CONFIG.LIMIT;
  CONFIG.LIMIT = limit;
  
  const records = fetchRecordsFromJustDB(offset);
  
  if (records && records.length > 0) {
    // フィールド識別名を指定
    const fieldNames = [
      'field_1698900331',      // 実施
      'field_1738483016',      // 当初開始日時
      'field_1766109609',      // 当初予定業務No
      'field_1698862300',      // 予定開始日時
      'field_1698862327',      // 予定終了日時
      'field_1698864130',      // 予定業務No
      'field_1697693160',      // 講師名
      'field_1724795187',      // 授業形態詳細
      'field_1697693253',      // 授業名
      'field_1767851507',      // 変更理由
      'field_1724801182',      // 生徒情報(カンマ区切り)
      'field_1724795908',      // E教室No
      'field_1697695649',      // イレギュラー教室名
      'field_1699080449',      // 出欠
      'field_1698899943',      // 受講形態
      'field_1698862499',      // 生徒No
      'field_1697693912',      // 生徒名
      'field_1697693952',      // 生徒種別
      'field_1698862199'       // 授業No
    ];
    
    writeRecordsToSheet(records, sheet, fieldNames);
    Logger.log(`${records.length}件のレコードを書き込みました`);
  }
  
  // 設定を元に戻す
  CONFIG.LIMIT = originalLimit;
}



/**
 * 各科シートに「メイン」シートから転記する !!
 * 
 */
function addNewDataFromMain() {
  // スプレッドシートとシートを取得
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const activeSheet = ss.getActiveSheet(); // アクティブシート
  const rawDataSheet = ss.getSheetByName('メイン');
  
  // データが存在しない場合のエラーチェック
  if (!activeSheet || !rawDataSheet) {
    throw new Error('アクティブシートまたは「メイン」シートが見つかりません');
  }
  
  // A2セルから教科名を取得
  const subjectName = activeSheet.getRange('A2').getValue();
  
  if (!subjectName) {
    throw new Error('A2セルに教科名が入力されていません');
  }
  
  // アクティブシートのデータを取得（C列：当初予定業務No）
  const activeSheetData = activeSheet.getDataRange().getValues();
  const businessMap = new Map(); // Set から Map に変更してA列のstatusも保存
  
  // ヘッダー行を除いて、既存の当初予定業務NoとA列のstatusを取得
  for (let i = 1; i < activeSheetData.length; i++) {
    if (activeSheetData[i][2]) { // C列（インデックス2）
      businessMap.set(activeSheetData[i][2].toString(), {
        row: i + 1, // 実際のシート行番号
        status: activeSheetData[i][0] // A列（インデックス0）のstatus
      });
    }
  }
  
  // 「rawDATA」シートのデータを取得
  const rawData = rawDataSheet.getDataRange().getValues();
  const newRows = [];
  let updateCount = 0;
  const timestamp = Utilities.formatDate(new Date(), 'JST', 'yyyy-MM-dd HH:mm');
  
  // ヘッダー行を除いて処理
  for (let i = 1; i < rawData.length; i++) {
    const businessNo = rawData[i][2]; // C列（インデックス2）
    const columnA = rawData[i][0]; // A列（インデックス0）
    const columnI = rawData[i][8]; // I列（インデックス8）
    
    // A列が「振替」のものは除外
    if (columnA && columnA.toString() === '振替') {
      continue;
    }
    
    // I列に教科名を含まない場合はスキップ
    if (!columnI || !columnI.toString().includes(subjectName)) {
      continue;
    }
    
    if (businessNo) {
      const businessNoStr = businessNo.toString();
      
      if (businessMap.has(businessNoStr)) {
        // 既存データの場合：A列のstatusが変わっているかチェック
        const existingData = businessMap.get(businessNoStr);
        const newStatus = columnA ? columnA.toString() : '';
        const oldStatus = existingData.status ? existingData.status.toString() : '';
        
        if (newStatus !== oldStatus) {
          // statusが変わっている場合、A列とL列を更新
          activeSheet.getRange(existingData.row, 1).setValue(newStatus); // A列更新
          activeSheet.getRange(existingData.row, 12).setValue(timestamp); // L列更新
          updateCount++;
        }
      } else {
        // 新規データの場合：追加対象に含める
        newRows.push(rawData[i]);
      }
    }
  }
  
  // 新しいデータがある場合のみ追加
  if (newRows.length > 0) {
    const lastRow = activeSheet.getLastRow();
    const m10Range = activeSheet.getRange('M10'); // M10セルを取得
    
    // データを追加
    for (let i = 0; i < newRows.length; i++) {
      const rowToAdd = newRows[i].slice(); // 配列のコピー
      rowToAdd[11] = timestamp; // L列（インデックス11）に追加日時を設定
      
      // まず行データを追加
      activeSheet.getRange(lastRow + 1 + i, 1, 1, rowToAdd.length)
        .setValues([rowToAdd]);
      
      // M10をM列にコピー
      m10Range.copyTo(activeSheet.getRange(lastRow + 1 + i, 13)); // 13列目=M列
    }
  }
  
  // 結果をログ出力
  Logger.log(`${subjectName}: ${newRows.length}件の新しいデータを追加しました`);
  Logger.log(`${subjectName}: ${updateCount}件の既存データを更新しました`);
  
  if (newRows.length === 0 && updateCount === 0) {
    Logger.log(`${subjectName}: 追加・更新するデータはありません`);
  }
  
  // G列→B列の昇順でソート
  const dataRange = activeSheet.getDataRange();
  const numRows = dataRange.getNumRows();
  
  if (numRows > 1) { // ヘッダー行がある場合
    const sortRange = activeSheet.getRange(11, 1, numRows - 10, dataRange.getNumColumns());
    sortRange.sort([
      {column: 7, ascending: true},  // G列で昇順
      {column: 2, ascending: true}   // B列で昇順
    ]);
    Logger.log(`${subjectName}: データをG列→B列の昇順でソートしました`);
  }
}





/**
 * 各科シートにrawDATAから転記する !!
 * 
 */
function addNewDataFromRawDATA() {
  // スプレッドシートとシートを取得
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const activeSheet = ss.getActiveSheet(); // アクティブシート
  const rawDataSheet = ss.getSheetByName('rawDATA');
  
  // データが存在しない場合のエラーチェック
  if (!activeSheet || !rawDataSheet) {
    throw new Error('アクティブシートまたは「rawDATA」シートが見つかりません');
  }
  
  // A2セルから教科名を取得
  const subjectName = activeSheet.getRange('A2').getValue();
  
  if (!subjectName) {
    throw new Error('A2セルに教科名が入力されていません');
  }
  
  // アクティブシートのデータを取得（C列：当初予定業務No）
  const activeSheetData = activeSheet.getDataRange().getValues();
  const businessMap = new Map(); // Set から Map に変更してA列のstatusも保存
  
  // ヘッダー行を除いて、既存の当初予定業務NoとA列のstatusを取得
  for (let i = 1; i < activeSheetData.length; i++) {
    if (activeSheetData[i][2]) { // C列（インデックス2）
      businessMap.set(activeSheetData[i][2].toString(), {
        row: i + 1, // 実際のシート行番号
        status: activeSheetData[i][0] // A列（インデックス0）のstatus
      });
    }
  }
  
  // 「rawDATA」シートのデータを取得
  const rawData = rawDataSheet.getDataRange().getValues();
  const newRows = [];
  let updateCount = 0;
  const timestamp = Utilities.formatDate(new Date(), 'JST', 'yyyy-MM-dd HH:mm');
  
  // ヘッダー行を除いて処理
  for (let i = 1; i < rawData.length; i++) {
    const businessNo = rawData[i][2]; // C列（インデックス2）
    const columnA = rawData[i][0]; // A列（インデックス0）
    const columnI = rawData[i][8]; // I列（インデックス8）
    
    // A列が「振替」のものは除外
    if (columnA && columnA.toString() === '振替') {
      continue;
    }
    
    // I列に教科名を含まない場合はスキップ
    if (!columnI || !columnI.toString().includes(subjectName)) {
      continue;
    }
    
    if (businessNo) {
      const businessNoStr = businessNo.toString();
      
      if (businessMap.has(businessNoStr)) {
        // 既存データの場合：A列のstatusが変わっているかチェック
        const existingData = businessMap.get(businessNoStr);
        const newStatus = columnA ? columnA.toString() : '';
        const oldStatus = existingData.status ? existingData.status.toString() : '';
        
        if (newStatus !== oldStatus) {
          // statusが変わっている場合、A列とL列を更新
          activeSheet.getRange(existingData.row, 1).setValue(newStatus); // A列更新
          activeSheet.getRange(existingData.row, 12).setValue(timestamp); // L列更新
          updateCount++;
        }
      } else {
        // 新規データの場合：追加対象に含める
        newRows.push(rawData[i]);
      }
    }
  }
  
  // 新しいデータがある場合のみ追加
  if (newRows.length > 0) {
    const lastRow = activeSheet.getLastRow();
    const m10Range = activeSheet.getRange('M10'); // M10セルを取得
    
    // データを追加
    for (let i = 0; i < newRows.length; i++) {
      const rowToAdd = newRows[i].slice(); // 配列のコピー
      rowToAdd[11] = timestamp; // L列（インデックス11）に追加日時を設定
      
      // まず行データを追加
      activeSheet.getRange(lastRow + 1 + i, 1, 1, rowToAdd.length)
        .setValues([rowToAdd]);
      
      // M10をM列にコピー
      m10Range.copyTo(activeSheet.getRange(lastRow + 1 + i, 13)); // 13列目=M列
    }
  }
  
  // 結果をログ出力
  Logger.log(`${subjectName}: ${newRows.length}件の新しいデータを追加しました`);
  Logger.log(`${subjectName}: ${updateCount}件の既存データを更新しました`);
  
  if (newRows.length === 0 && updateCount === 0) {
    Logger.log(`${subjectName}: 追加・更新するデータはありません`);
  }
  
  // G列→B列の昇順でソート
  const dataRange = activeSheet.getDataRange();
  const numRows = dataRange.getNumRows();
  
  if (numRows > 1) { // ヘッダー行がある場合
    const sortRange = activeSheet.getRange(11, 1, numRows - 10, dataRange.getNumColumns());
    sortRange.sort([
      {column: 7, ascending: true},  // G列で昇順
      {column: 2, ascending: true}   // B列で昇順
    ]);
    Logger.log(`${subjectName}: データをG列→B列の昇順でソートしました`);
  }
}
