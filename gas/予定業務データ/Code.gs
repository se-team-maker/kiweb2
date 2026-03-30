const ss = SpreadsheetApp.openById('1QlYIcrLpbsi0uDar7sFKY9A3kCB5opKu3USt6x0nfCg');
const DEFAULT_DECLARATION_CACHE_REFRESH_URL_ = 'https://system.kyotoijuku.com/kiweb/teacher-auth/api/internal/refresh-declaration-schedule-cache.php';
const DECLARATION_CACHE_REFRESH_URL_PROPERTY_ = 'DECLARATION_CACHE_REFRESH_URL';
const DECLARATION_CACHE_REFRESH_TOKEN_PROPERTY_ = 'DECLARATION_CACHE_REFRESH_TOKEN';

function main() {
  const lapStart= new Date();
  const tableName = 'table_1698862183';

  refreshScheduleDataSheets_(tableName);
  var refreshResult = refreshDeclarationSchedulePhpCache_();

  // 現在の時刻とlapStartとの差をミリ秒で取得
  var elapsedMillis = new Date().getTime() - lapStart.getTime();
  var elapsedDate = new Date(elapsedMillis);
  Logger.log('PHP declaration cache refresh success: ' + JSON.stringify(refreshResult));
  Logger.log(Utilities.formatDate(elapsedDate, 'JST', 'mm:ss'));
  // Browser.msgBox(Utilities.formatDate(elapsedDate, 'JST', 'mm:ss'));
  // Logger.log(Utilities.formatDate(new Date()-lapStart),'JST',"mm:ss");
}

function refreshScheduleDataSheets_(tableName) {
  let records = getAllRecords(tableName);     // JUST.DBから取得の本体！

  Logger.log(`取得したレコード数: ${records.length}`);
  Logger.log(records);
  var arr = objectToSpecific2DArray(records);

  var sheet = ss.getSheetByName('JUSTDBからAPI取得'); // シート名を適切なものに変更してください
  sheet.getRange('A1').setValue(Utilities.formatDate(new Date(),'JST',"yyyy-MM-dd HH:mm"));
  sheet.getRange('B3:M').clearContent();
  sheet.getRange(2,2,arr.length,arr[0].length).setValues(arr);
  // sheet.getRange(1,1).setValue(records);
  sheet.getRange('G2').clearContent();

  // JSONを書き込み
  saveJsonInChunks(arr);

  // キャッシュ読み出し元のQ列を確実に反映させる
  SpreadsheetApp.flush();

  sortAndCopyData();        //「ウェブ用データ」シートへの貼り付け

  // PHP側連携の前に全シート更新を確定させる
  SpreadsheetApp.flush();
}

function getDeclarationCacheRefreshConfig_() {
  var properties = PropertiesService.getScriptProperties();
  var url = String(properties.getProperty(DECLARATION_CACHE_REFRESH_URL_PROPERTY_) || '').trim();
  var token = String(properties.getProperty(DECLARATION_CACHE_REFRESH_TOKEN_PROPERTY_) || '').trim();

  if (!url) {
    url = DEFAULT_DECLARATION_CACHE_REFRESH_URL_;
    Logger.log('Script Property "' + DECLARATION_CACHE_REFRESH_URL_PROPERTY_ + '" が未設定のため既定値を使用します: ' + url);
  }
  if (!token) {
    throw new Error('Script Property "' + DECLARATION_CACHE_REFRESH_TOKEN_PROPERTY_ + '" が未設定です。');
  }

  return {
    url: url,
    token: token
  };
}

function tryParseJson_(text) {
  try {
    return JSON.parse(text);
  } catch (error) {
    return null;
  }
}

function trimForLog_(text, maxLength) {
  var normalized = String(text === undefined || text === null ? '' : text);
  if (normalized.length <= maxLength) {
    return normalized;
  }
  return normalized.substring(0, maxLength) + '...';
}

function refreshDeclarationSchedulePhpCache_() {
  var config = getDeclarationCacheRefreshConfig_();
  var response = UrlFetchApp.fetch(config.url, {
    method: 'post',
    contentType: 'application/json; charset=utf-8',
    headers: {
      Authorization: 'Bearer ' + config.token,
      Accept: 'application/json'
    },
    payload: JSON.stringify({
      trigger: 'gas_main',
      refreshedAt: new Date().toISOString()
    }),
    followRedirects: true,
    muteHttpExceptions: true
  });

  var statusCode = response.getResponseCode();
  var responseText = response.getContentText();
  var payload = tryParseJson_(responseText);

  if (statusCode < 200 || statusCode >= 300) {
    throw new Error('PHPキャッシュ更新APIがHTTP ' + statusCode + ' を返しました: ' + trimForLog_(responseText, 500));
  }
  if (!payload || payload.success !== true) {
    throw new Error('PHPキャッシュ更新APIのレスポンスが不正です: ' + trimForLog_(responseText, 500));
  }

  return payload;
}

function saveJsonInChunks(arr) {
  // const sheet = SpreadsheetApp.getActiveSheet();
  var sheet = ss.getSheetByName('JUSTDBからAPI取得'); // シート名を適切なものに変更してください

  const header = [
    "授業名",
    "担当講師",
    "開始日時",
    "終了日時",
    "予定業務No",
    "コマ符号",
    "生徒名",
    "STATUS",
    "授業形態詳細",
    "授業実施校舎"
  ];

  // 校舎変換 & 今日以降フィルタ（前回作ったロジックをここで使う）
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const filtered = arr.reduce(function (acc, row) {
    // 「会議」の場合はスキップ
    if (row[8] === '会議') return acc;

    const rawStart = row[2];
    let start;
    if (rawStart instanceof Date) {
      start = new Date(rawStart.getTime());
    } else {
      start = new Date(rawStart);
    }
    if (isNaN(start)) return acc;

    const startDate = new Date(start.getTime());
    startDate.setHours(0, 0, 0, 0);
    if (startDate < today) return acc;

    const newRow = row.slice();
    newRow[7] = newRow[7][0];
    newRow[9] = newRow[9][0];
    // if (campus === '四条烏丸') newRow[9] = 'SK';
    // else if (campus === '円町') newRow[9] = 'EM';
    // else if (campus === '京大前') newRow[9] = 'KD';

    acc.push(newRow);
    return acc;
  }, []);

  const arrWithHeader = [header].concat(filtered);
  const json = JSON.stringify(arrWithHeader);

  // ここがポイント：5万文字制限を避けるために分割
  const MAX_LEN = 49000; // 余裕をもたせておく
  const parts = [];
  for (let i = 0; i < json.length; i += MAX_LEN) {
    parts.push(json.substring(i, i + MAX_LEN));
  }

  // 既存の書き込み場所をクリア（Q列を一旦消すなど）
  const startRow = 2;
  const col = 17; // Q列 = 17
  sheet.getRange(startRow, col, sheet.getMaxRows() - startRow + 1, 1).clearContent();

  // Q2 以降に縦に書き込み
  sheet
    .getRange(startRow, col, parts.length, 1)
    .setValues(parts.map(p => [p]));

  // どれだけ分割されているかを別セルにメモ（例：P2）
  sheet.getRange('Q1').setValue(parts.length); // パーツ数
}


function sortAndCopyData() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sourceSheet = ss.getSheetByName("JUSTDBからAPI取得");
  // var sourceSheet = ss.getActiveSheet();
  var targetSheet = ss.getSheetByName("ウェブ用データ");

  // B3:Kの範囲を取得
  var range = sourceSheet.getRange("B3:K" + sourceSheet.getLastRow());
  
  // D列（インデックスは3）で昇順に並べ替え
  range.sort({column: 4, ascending: true});
  
  // 並べ替えたデータをコピー
  var dataToCopy = range.getValues();
  
  // // B列（インデックスは0）の■を🟦に、●を🟠に置換
  // for (var i = 0; i < dataToCopy.length; i++) {
  //   dataToCopy[i][0] = dataToCopy[i][0].replace(/■/g, '🟦').replace(/●/g, '🟠');
  //   if (dataToCopy[i][7]==='生徒CNL'){
  //     dataToCopy[i][0]='🚫'+dataToCopy[i][0]+'_当日キャンセル';
  //   } else if (dataToCopy[i][7]==='緑線') {
  //     dataToCopy[i][0]='🚫'+dataToCopy[i][0]+'_授業なし';
  //   } else if (dataToCopy[i][7]==='体験') {
  //     dataToCopy[i][0]=dataToCopy[i][0]+'_体験';
  //   } else if (dataToCopy[i][7]==='面談') {
  //     dataToCopy[i][0]=dataToCopy[i][0]+'_面談';
  //   }
  //   if (dataToCopy[i][8]==='RPE'){
  //     dataToCopy[i][0]=dataToCopy[i][0]+'📶RPE';
  //   }
  // }

  // 【新方式】
  // B列（インデックスは0）の■、●を削除
  for (var i = 0; i < dataToCopy.length; i++) {
    dataToCopy[i][0] = dataToCopy[i][0].replace(/■/g, '').replace(/●/g, '').replace(/★/g, '');

    if (dataToCopy[i][9]==='四条烏丸'){
       var place='🟦';
    } else if (dataToCopy[i][9]==='円町'){
       var place='🟠';
    } else if (dataToCopy[i][9]==='京大前'){
       var place='⭐️';
    } else{
       var place='🟦';
    }  
  
    if (dataToCopy[i][7]==='生徒CNL'){
      var alert = '⚠️当日キャンセル';
    } else if (dataToCopy[i][7]==='緑線') {
      var alert = '⚠️授業なし';
    } else if (dataToCopy[i][7]==='体験') {
      var alert = '⚠️体験';
    } else if (dataToCopy[i][7]==='面談') {
      var alert = '⚠️面談';
    } else{
      var alert=''; 
    }

    if(dataToCopy[i][0]){
      dataToCopy[i][0] = place + dataToCopy[i][0]+alert;
      // dataToCopy[i][0] = place +dataToCopy[i][8] +'|'+ dataToCopy[i][0]+alert;
    } 
  }
  // 「ウェブ用データ」シートの指定位置にデータを貼り付け
  targetSheet.getRange('A1').setValue(Utilities.formatDate(new Date(),'JST',"yyyy-MM-dd HH:mm"));
  targetSheet.getRange('C3:J').clearContent();  
  targetSheet.getRange(3, 3, dataToCopy.length, dataToCopy[0].length).setValues(dataToCopy);
}



//
//新関数　main関数の処理いろいろ

function getAllRecords(tableName) {
  const API_KEY = 'tE3hq0tZnmDeW8FlDd68r6um90fBB7Ks';
  const BASE_URL = 'https://kyotoijuku.just-db.com/sites/api/services/v1/tables/';
  
  let allRecords = [];
  let offset = 0;
  let panelName = "panel_1698868461";
  let filterName = "filter_1729242803";         //
  const limit = 100; // 1回のリクエストで取得するレコード数

  // // 現在の日付を取得し、前日の日付を計算
  // const today = new Date();
  // today.setDate(today.getDate() - 1);
  // const yesterdayStr = Utilities.formatDate(today, 'Asia/Tokyo', 'yyyy-MM-dd');  

  // 現在の日付を取得し、前日の日付を計算
  const today = new Date();
  today.setDate(today.getDate() - 1);
  const yesterdayStr = Utilities.formatDate(today, 'Asia/Tokyo', 'yyyy-MM-dd\'T\'00:00:00+09:00');
  
  while (true) {
    // let url = `${BASE_URL}${tableName}/records/?offset=${offset}&limit=${limit}`;
    let url = `${BASE_URL}${tableName}/records/?panelName=${panelName}&filterName=${filterName}&offset=${offset}&limit=${limit}`; 

    let options = {
      'method': 'get',
      'headers': {
        'Authorization': 'Bearer ' + API_KEY,
        'Content-Type': 'application/json'
      }
    };
    
    let response = UrlFetchApp.fetch(url, options);
    let responseData = JSON.parse(response.getContentText());
    
    if (responseData.length === 0) {
      break; // 全てのレコードを取得したら終了
    }
    
    allRecords = allRecords.concat(responseData);
    offset += limit;
    Utilities.sleep(3000); // 3秒待機    
  }
  
  // フィルタリングを行う
  let filteredRecords = filterFutureRecords(allRecords);
  
  // ソートを行う
  return sortByStartDate(filteredRecords);
}

function sortByStartDate(records) {
  return records.sort((a, b) => {
    const dateA = new Date(a.record.field_1698862300);
    const dateB = new Date(b.record.field_1698862300);
    return dateA - dateB;
  });
}

function filterFutureRecords(records) {
  const today = new Date();
    today.setDate(today.getDate() - 1); // 1日引いて前日の日付にする #########################
  today.setHours(0, 0, 0, 0);
  
  return records.filter(record => {
    const startDate = new Date(record.record.field_1698862300);
    const status = record.record.field_1698900331;

    return startDate >= today && 
           status !== '振替' && 
           status !== '処理未定';

  });
}
   

function objectToSpecific2DArray(objArray) {
  // 単一オブジェクトの場合は配列に変換
  if (!Array.isArray(objArray)) {
    objArray = [objArray];
  }

  // 日時をフォーマットする関数
  function formatDateTime(dateString) {
    const date = new Date(dateString);
    return Utilities.formatDate(date, 'Asia/Tokyo', 'yyyy-MM-dd HH:mm');
  }

  function getSecondElementOrEmpty(arr) {
    return Array.isArray(arr) && arr.length > 1 ? arr[1] : '';
  }

  // 抽出したいカラムの定義
  const columns = [
     { name: '授業名', field: 'field_1697693253',},
   // { name: '授業名', field: 'field_1697693253', process: (value) => processField1697693253(value).授業名 },
    { name: '担当講師', field: 'field_1697693160' },
    { name: '開始日時', field: 'field_1698862300', process: formatDateTime },
    { name: '終了日時', field: 'field_1698862327', process: formatDateTime },
    { name: '予定業務No', field: 'field_1698864130', process: getSecondElementOrEmpty },
    { name: '時限区分', field: 'field_1697694089' },
    { name: '生徒名', field: 'field_1724801182'},
    { name: 'STATUS', field: 'field_1698900331' },       // 新しく追加
    { name: '授業形態詳細', field: 'field_1724795187' },       // 新しく追加
    { name: '授業実施校舎', field: 'field_1724669124' }       // 新しく追加 250712中畠 
  ];

  // 結果の2次元配列を初期化（ヘッダー行を含む）
  const result = [columns.map(col => col.name)];

  // 各レコードに対してデータ行を作成
  objArray.forEach(obj => {
    const record = obj.record || {};
    const dataRow = columns.map(column => {
      let value = record[column.field] || '';
      // 処理関数が定義されている場合は適用
      if (column.process && typeof column.process === 'function') {
        value = column.process(value);
      }
      return value;
    });
    result.push(dataRow);
  });

  return result;
}



// ##############################################################################################
// ki web に表示するデータを渡すための処理
// ##############################################################################################

function doGet(e) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('ウェブ用データ');
  const data = sheet.getDataRange().getValues();

  var sheet2 = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('getRequest_log');
  var stringifiedE = JSON.stringify(e);
  var stringifiedE2 = JSON.stringify(e.parameter);
  var stringifiedE3 = JSON.stringify(e.parameters);

  sheet2.appendRow(['',Utilities.formatDate(new Date(),'JST',"yyyy-MM-dd HH:mm:ss"),stringifiedE,stringifiedE2,stringifiedE3]);



  // ★ 追加：JUSTDBからAPI取得 シート Q列のJSONキャッシュを返す
  if (e.parameter.action === 'getCachedJson') {
    const cacheSheet = ss.getSheetByName('JUSTDBからAPI取得');
    if (!cacheSheet) {
      return ContentService.createTextOutput(
        JSON.stringify({ error: 'シート「JUSTDBからAPI取得」が見つかりません' })
      ).setMimeType(ContentService.MimeType.JSON);
    }

    const startRow = 2;
    const col = 17; // Q列
    const lastRow = cacheSheet.getLastRow();

    if (lastRow < startRow) {
      return ContentService.createTextOutput(
        JSON.stringify({ error: 'Q列にキャッシュされたJSONがありません' })
      ).setMimeType(ContentService.MimeType.JSON);
    }

    // Q2〜最終行を取得して、空でないセルを連結
    const values = cacheSheet.getRange(startRow, col, lastRow - startRow + 1, 1).getValues();
    const jsonStr = values
      .map(r => r[0])
      .filter(v => v)      // 空文字/null除外
      .join('');

    if (!jsonStr) {
      return ContentService.createTextOutput(
        JSON.stringify({ error: 'Q列にキャッシュされたJSONが空です' })
      ).setMimeType(ContentService.MimeType.JSON);
    }

    // すでに JSON 文字列なので、そのままレスポンスとして返す
    return ContentService.createTextOutput(jsonStr)
      .setMimeType(ContentService.MimeType.JSON);
  }
  // ★ 追加ここまで


  if (e.parameter.action === 'getStudentNames') {
    const taskNo = e.parameter.taskNo;
    
    for (let i = 1; i < data.length; i++) {
      if (data[i][6] === taskNo) { // G列は0から数えて6番目
        const studentNames = data[i][8].split(';'); // 生徒名=I列は0から数えて8番目
        return ContentService.createTextOutput(JSON.stringify({studentNames: studentNames}))
          .setMimeType(ContentService.MimeType.JSON);
      }
    }
    return ContentService.createTextOutput(JSON.stringify({error: '生徒が見つかりません'}))
      .setMimeType(ContentService.MimeType.JSON);

  } else if (e.parameter.action === 'getStudentSchedule') {
    // 新機能：生徒向けスケジュール取得
    const studentName = e.parameter.studentName;
    
    if (!studentName) {
      return ContentService.createTextOutput(JSON.stringify({error: '生徒名が指定されていません'}))
        .setMimeType(ContentService.MimeType.JSON);
    }
    
    // 該当する授業データを検索
    const studentLessons = [];
    const headers = data[0];
    
    for (let i = 1; i < data.length; i++) {
      const row = data[i];
      const studentsInLesson = row[8] ? row[8].split(';') : []; // 生徒名（I列）
      
      // 指定された生徒名が含まれているかチェック
      if (studentsInLesson.some(name => name.trim() === studentName.trim())) {
        studentLessons.push({
          授業名: row[0] || '',
          講師名: row[1] || '',
          予定開始日時: row[2] || '',
          予定終了日時: row[3] || '',
          予定業務No: row[6] || ''
        });
      }
    }
    
    if (studentLessons.length === 0) {
      return ContentService.createTextOutput(JSON.stringify({
        message: `${studentName}さんの授業が見つかりませんでした。`,
        schedule: ''
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // 日時順にソート
    studentLessons.sort((a, b) => new Date(a.予定開始日時) - new Date(b.予定開始日時));
    
    // 生徒向けの読みやすい形式に変換
    const scheduleText = formatStudentSchedule(studentName, studentLessons);
    
    return ContentService.createTextOutput(JSON.stringify({
      message: `${studentName}さんの授業スケジュール`,
      schedule: scheduleText,
      lessonCount: studentLessons.length
    })).setMimeType(ContentService.MimeType.JSON);

  } else if (e.parameter.action === 'searchTimetable') {
    // 時間割検索の処理
    const headers = data[0];
    const requiredColumns = ['授業名', '講師名', '予定開始日時', '予定終了日時', '予定業務No','生徒名','講師key'];
    // const requiredColumns = ['授業名', '講師名', '予定開始日時', '予定終了日時', '予定業務No'];
    
    const columnIndices = requiredColumns.map(column => headers.indexOf(column));
    
    let formattedData = data.slice(1).map(row => {
      let obj = {};
      columnIndices.forEach((index, i) => {
        if (index !== -1) {
          obj[requiredColumns[i]] = row[index];
        }
      });
      return obj;
    });

    // 検索条件のフィルタリング
    const lecturer = e.parameter.lecturer;
    const date = e.parameter.date;
    const startTime = e.parameter.startTime;

    formattedData = formattedData.filter(row => {
      const rowDate = new Date(row['予定開始日時']);
      return (!lecturer || row['講師名'].includes(lecturer)) &&
             (!date || rowDate.toDateString() === new Date(date).toDateString()) &&
             (!startTime || rowDate.getHours() === parseInt(startTime.split(':')[0]));
    });

    // ソート処理
    const sortColumn = e.parameter.sortColumn;
    const sortDirection = e.parameter.sortDirection;

    if (sortColumn && sortDirection) {
      formattedData.sort((a, b) => {
        if (sortColumn === '予定開始日時' || sortColumn === '予定終了日時') {
          return sortDirection === 'asc' 
            ? new Date(a[sortColumn]) - new Date(b[sortColumn])
            : new Date(b[sortColumn]) - new Date(a[sortColumn]);
        } else {
          return sortDirection === 'asc'
            ? a[sortColumn].localeCompare(b[sortColumn])
            : b[sortColumn].localeCompare(a[sortColumn]);
        }
      });
    }

    return ContentService.createTextOutput(JSON.stringify(formattedData))
      .setMimeType(ContentService.MimeType.JSON);
      
  } else {
    // デフォルトの処理（全データを返す）
    const headers = data[0];
    const requiredColumns = ['授業名', '講師名', '予定開始日時', '予定終了日時', '予定業務No', '生徒名' ,'講師key'];
    // const requiredColumns = ['授業名', '講師名', '予定開始日時', '予定終了日時', '予定業務No'];
    
    const columnIndices = requiredColumns.map(column => headers.indexOf(column));
    
    const formattedData = data.slice(1).map(row => {
      let obj = {};
      columnIndices.forEach((index, i) => {
        if (index !== -1) {
          obj[requiredColumns[i]] = row[index];
        }
      });
      return obj;
    });

    return ContentService.createTextOutput(JSON.stringify(formattedData))
      .setMimeType(ContentService.MimeType.JSON);

  }
}


function markDuplicateRows() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  const data = sheet.getDataRange().getValues();
  const header = data.shift();  // 1行目をヘッダーとして除外（必要に応じて）
  
  // 重複判定用オブジェクト
  //   key:   B列(記号除去) + C列 + D列 + E列 の連結文字列
  //   value: { index: シート上の行番号, scheduledNo: 最古(最小)の予定業務No }
  const duplicates = {};

  sheet.getRange(3,12,sheet.getLastRow()-2,1).clearContent();

  // データを1行ずつ処理
  data.forEach((row, i) => {
    // シート上の実際の行番号（ヘッダーを除いた分、+2 する）
    const rowIndex = i + 2;  
    
    // B,C,D,E,F列がいずれも空白ならスキップ
    if (row[1] === "" || row[2] === "" || row[3] === "" || row[4] === "" || row[5] === "") {
      return;
    }
    
    // B列の先頭にある「■」「●」を削除
    const normalizedB = row[1].replace(/^[■●]/, "");
    
    // B,C,D,E列を連結したキーを作成
    const key = normalizedB + row[2] + row[3] + row[4];
    
    // F列の予定業務Noは文字列の場合もあるので数値化
    const scheduledNo = parseInt(row[5], 10);
    
    // まだこのキーが登録されていない → 最初に見つかった(=最古)として登録
    if (!duplicates[key]) {
      duplicates[key] = {
        index: rowIndex,
        scheduledNo: scheduledNo
      };
    } else {
      // すでに同じキーが存在 → 予定業務Noを比較
      const existing = duplicates[key];
      
      if (scheduledNo < existing.scheduledNo) {
        // 新しく見つかった方(scheduledNo)がより古い(値が小さい)場合
        // → 既存の“より若い(値が大きい)”方を削除対象にするので、
        //   既存の行(L列)にその古くない予定業務No(= existing.scheduledNo)を書き込む
        sheet.getRange(existing.index, 12).setValue(existing.scheduledNo);

        // 新しい方を「最古」として上書き
        duplicates[key] = {
          index: rowIndex,
          scheduledNo: scheduledNo
        };
        
        // ここでは新しい方は「残す」ので L列は空白のまま（何もしない）
      } else {
        // 新しく見つかった方が若い(値が大きい) → こちらが削除対象
        // → 新しい行(L列)に予定業務Noを書き込む
        sheet.getRange(rowIndex, 12).setValue(scheduledNo);
        
        // 既存の方はそのまま“最古”として残す（何も更新しない）
      }
    }
  });
}


function deleteDuplicateRows() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var yoteiToDelStr=sheet.getRange('L2').getValue();

  // Yes, No のボタンがある確認ダイアログを表示
  var ui = SpreadsheetApp.getUi();
  var response = ui.alert(
    '確認',
    '本当に削除を実行しますか？',
    ui.ButtonSet.YES_NO
  );

  // ユーザーの選択に応じて処理を分岐
  if (response == ui.Button.YES) {
    // Yes を押された場合の処理
    Logger.log("Yes が押されました。処理を実行します。");

    // ## 重複した予定業務の削除
    var yoteiArray = yoteiToDelStr.split(",");
    for (var i=0; i<yoteiArray.length;i++){
      const data = {yoteiNo: yoteiArray[i]};
      sendPostRequest('del_YGT', data);
    }
  } else {
    // No を押された場合の処理
    Logger.log("No が押されました。処理をキャンセルします。");
  }

}



function sendPostRequest(type, data) {
  const WEB_APP_URL = 'https://script.google.com/macros/s/AKfycbz0CEDhJytsBForo7bytSV2XunNa98nvk10Xml2rZOrgV2mhAtRrromjNiA6KgsxKiOMg/exec';
  const options = {
    'method': 'post',
    'contentType': 'application/json',
    'payload': JSON.stringify({
      type: type,
      data: data
    }),
    'muteHttpExceptions': true
  };

  try {
    const response = UrlFetchApp.fetch(WEB_APP_URL, options);
    const responseCode = response.getResponseCode();
    const responseText = response.getContentText();
    
    if (responseCode === 200) {
      const result = JSON.parse(responseText);
      if (result.status === 'success') {
        return result.data;
      } else {
        throw new Error(result.message || '不明なエラーが発生しました');
      }
    } else {
      throw new Error(`HTTPエラー: ${responseCode}`);
    }
  } catch (error) {
    Logger.log(`エラーが発生しました: ${error.message}`);
    throw error;
  }
}

// 生徒向けスケジュール文字列をフォーマットする関数
function formatStudentSchedule(studentName, lessons) {
  let scheduleText = `${studentName}さんの授業スケジュール\n`;
  scheduleText += '='.repeat(30) + '\n\n';
  
  // 日付ごとにグループ化
  const lessonsByDate = {};
  lessons.forEach(lesson => {
    const startDate = new Date(lesson.予定開始日時);
    const dateKey = Utilities.formatDate(startDate, 'JST', 'yyyy年M月d日(E)');
    
    if (!lessonsByDate[dateKey]) {
      lessonsByDate[dateKey] = [];
    }
    lessonsByDate[dateKey].push(lesson);
  });
  
  // 日付順に表示
  Object.keys(lessonsByDate).sort((a, b) => {
    const dateA = new Date(lessonsByDate[a][0].予定開始日時);
    const dateB = new Date(lessonsByDate[b][0].予定開始日時);
    return dateA - dateB;
  }).forEach(dateKey => {
    scheduleText += `📅 ${dateKey}\n`;
    scheduleText += '-'.repeat(25) + '\n';
    
    lessonsByDate[dateKey].forEach((lesson, index) => {
      const startDate = new Date(lesson.予定開始日時);
      const endDate = new Date(lesson.予定終了日時);
      
      const startTime = Utilities.formatDate(startDate, 'JST', 'HH:mm');
      const endTime = Utilities.formatDate(endDate, 'JST', 'HH:mm');
      
      scheduleText += `${index + 1}. 【${lesson.授業名}】\n`;
      scheduleText += `   ⏰ 時間: ${startTime} - ${endTime}\n`;
      scheduleText += `   👨‍🏫 講師: ${lesson.講師名}\n`;
      if (lesson.予定業務No) {
        scheduleText += `   📝 業務No: ${lesson.予定業務No}\n`;
      }
      scheduleText += '\n';
    });
    
    scheduleText += '\n';
  });
  
  scheduleText += `📊 合計授業数: ${lessons.length}コマ\n`;
  scheduleText += '頑張って勉強しましょう！✨';
  
  return scheduleText;
}
