// #################################
// ki webから受けた実施申告を記録する
// #################################
var DECLARATION_SHEET_NAME_ = '実施申告records';
var SUBMITTED_CACHE_CELL_ = 'I1'; // 送信済みtaskNo->statusのJSONキャッシュ
var SUBMITTED_CACHE_META_CELL_ = 'J1'; // キャッシュ下限日(yyyy/MM/dd)

function normalizeTaskNo_(value) {
  return String(value === undefined || value === null ? '' : value).trim();
}

function toMillis_(value) {
  if (!value) return -1;
  if (Object.prototype.toString.call(value) === '[object Date]') {
    return value.getTime();
  }
  var parsed = new Date(value);
  return isNaN(parsed.getTime()) ? -1 : parsed.getTime();
}

function jsonResponse_(payload) {
  return ContentService.createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

function normalizeSendtoName_(name) {
  return String(name === undefined || name === null ? '' : name)
    .replace(/[\s\u3000]+/g, '');
}

function parseSubmittedCache_(rawValue) {
  if (rawValue === '' || rawValue === null || rawValue === undefined) {
    return null;
  }

  try {
    var parsed = JSON.parse(String(rawValue));
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      return null;
    }

    var normalized = {};
    Object.keys(parsed).forEach(function(key) {
      var taskNo = normalizeTaskNo_(key);
      if (!taskNo) return;
      normalized[taskNo] = String(parsed[key] || '');
    });
    return normalized;
  } catch (error) {
    return null;
  }
}

function normalizeDateKey_(value) {
  if (value === null || value === undefined || value === '') return '';

  if (Object.prototype.toString.call(value) === '[object Date]') {
    return Utilities.formatDate(value, 'Asia/Tokyo', 'yyyy/MM/dd');
  }

  var matched = String(value).match(/(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/);
  if (!matched) return '';

  var yyyy = matched[1];
  var mm = ('0' + matched[2]).slice(-2);
  var dd = ('0' + matched[3]).slice(-2);
  return yyyy + '/' + mm + '/' + dd;
}

function getSubmittedLowerBoundKey_() {
  // 画面表示の下限に合わせる: JSTで「昨日」の日付キー
  var now = new Date();
  var yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);
  return Utilities.formatDate(yesterday, 'Asia/Tokyo', 'yyyy/MM/dd');
}

function buildSubmittedTaskMapFromSheet_(sheet, lowerBoundKey) {
  var rows = sheet.getDataRange().getValues().slice(2); // index2=行3からデータ
  var taskMap = {};

  rows.forEach(function(row) {
    // G列(日付)優先、空ならD列(日時)から推定
    var dateKey = normalizeDateKey_(row[6]) || normalizeDateKey_(row[3]);
    if (!dateKey || dateKey < lowerBoundKey) return;

    var taskNo = normalizeTaskNo_(row[4]); // E列: 予定業務No
    if (!taskNo) return;
    taskMap[taskNo] = String(row[5] || ''); // F列: ステータス
  });

  return taskMap;
}

function readSubmittedTaskMap_(sheet) {
  var raw = sheet.getRange(SUBMITTED_CACHE_CELL_).getValue();
  return parseSubmittedCache_(raw);
}

function readSubmittedCacheLowerBoundKey_(sheet) {
  return String(sheet.getRange(SUBMITTED_CACHE_META_CELL_).getValue() || '').trim();
}

function writeSubmittedTaskMap_(sheet, taskMap) {
  sheet.getRange(SUBMITTED_CACHE_CELL_).setValue(JSON.stringify(taskMap));
}

function writeSubmittedCacheLowerBoundKey_(sheet, lowerBoundKey) {
  sheet.getRange(SUBMITTED_CACHE_META_CELL_).setValue(lowerBoundKey);
}

// 手動/トリガー用: 送信済みキャッシュを再生成
function refreshSubmittedTaskCache_() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(DECLARATION_SHEET_NAME_);
  if (!sheet) return;
  var lowerBoundKey = getSubmittedLowerBoundKey_();
  var rebuiltMap = buildSubmittedTaskMapFromSheet_(sheet, lowerBoundKey);
  writeSubmittedTaskMap_(sheet, rebuiltMap);
  writeSubmittedCacheLowerBoundKey_(sheet, lowerBoundKey);
}

// 手動1回実行で時間主導トリガーを作成（重複作成はしない）
function installSubmittedTaskCacheTrigger_() {
  var functionName = 'refreshSubmittedTaskCache_';
  var exists = ScriptApp.getProjectTriggers().some(function(trigger) {
    return trigger.getHandlerFunction() === functionName;
  });
  if (exists) return;
  ScriptApp.newTrigger(functionName).timeBased().everyHours(1).create();
}

function getAllSubmittedTaskNosObject_(options) {
  options = options || {};
  var shouldPersistCache = options.shouldPersistCache !== false;
  var preferCacheOnly = options.preferCacheOnly === true;

  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(DECLARATION_SHEET_NAME_);
  if (!sheet) {
    return {};
  }
  var cachedMap = readSubmittedTaskMap_(sheet);
  if (cachedMap && preferCacheOnly) {
    // GET用途: 高速応答優先でキャッシュをそのまま返す
    return cachedMap;
  }

  var lowerBoundKey = getSubmittedLowerBoundKey_();
  var cachedLowerBoundKey = readSubmittedCacheLowerBoundKey_(sheet);

  if (cachedMap && cachedLowerBoundKey === lowerBoundKey) {
    return cachedMap;
  }

  if (preferCacheOnly) {
    // キャッシュ未生成時でもGETを遅くしない
    return {};
  }

  // 初回・破損・日付切替時は再構築（表示対象期間のみ）
  var rebuiltMap = buildSubmittedTaskMapFromSheet_(sheet, lowerBoundKey);
  if (shouldPersistCache) {
    try {
      writeSubmittedTaskMap_(sheet, rebuiltMap);
      writeSubmittedCacheLowerBoundKey_(sheet, lowerBoundKey);
    } catch (error) {
      Logger.log('キャッシュ書き込みをスキップ: ' + error);
    }
  }
  return rebuiltMap;
}

function getAllSubmittedTaskNosResponse_() {
  try {
    // GETはキャッシュ即返し（高速・読み取り専用）
    return jsonResponse_(getAllSubmittedTaskNosObject_({
      shouldPersistCache: false,
      preferCacheOnly: true
    }));
  } catch (error) {
    Logger.log('getAllSubmittedTaskNosResponse_ error: ' + error);
    return jsonResponse_({});
  }
}

// 旧フロント互換: [{taskNo,status,...}] 形式
function getSubmittedListResponse_() {
  var taskMap = {};
  try {
    taskMap = getAllSubmittedTaskNosObject_({
      shouldPersistCache: false,
      preferCacheOnly: true
    });
  } catch (error) {
    Logger.log('getSubmittedListResponse_ error: ' + error);
  }
  var list = Object.keys(taskMap).map(function(taskNo) {
    return {
      taskNo: taskNo,
      status: String(taskMap[taskNo] || ''),
      className: '',
      dateTime: '',
      reportFormUrl: '',
      submittedAt: ''
    };
  });
  return jsonResponse_(list);
}

function doPost(e) {
  //このssは、実施申告だけ！　（予定業務の表示と切り分けた）
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(DECLARATION_SHEET_NAME_);
  if (!sheet) {
    return jsonResponse_({ result: 'error', message: 'Sheet not found' });
  }
  let data = {};

  try {
    data = JSON.parse(e.postData.contents);
  } catch (error) {
    return jsonResponse_({ result: 'error', message: 'Invalid JSON payload' });
  }

  if (data.action === 'updateStatus') {
    var teacherName = String(data.teacherName || '').trim();
    var className = String(data.className || '');
    var dateTime = String(data.dateTime || '');
    var taskNo = normalizeTaskNo_(data.taskNo);
    var status = String(data.status || '').trim();
    var reportFormUrl = String(data.reportFormUrl || '');

    if (!teacherName || !taskNo || !status) {
      return jsonResponse_({ result: 'error', message: 'Missing required fields' });
    }

    // 2602828 以下、中畠が停止しました。postのたびにキャッシュを生成してる？？
    // // 期間条件（昨日00:00以降）に沿った最新キャッシュを取得
    // var submittedTaskMap = getAllSubmittedTaskNosObject_();

    // if (Object.prototype.hasOwnProperty.call(submittedTaskMap, taskNo)) {
    //   return jsonResponse_({
    //     result: 'duplicate',
    //     taskNo: taskNo,
    //     reportFormUrl: ''
    //   });
    // }

    const timestamp = new Date();
    const rowData = [
      Utilities.formatDate(timestamp,'JST',"yyyy-MM-dd HH:mm:ss"),
      teacherName,
      className,
      dateTime,
      taskNo,
      status,
      dateTime.substring(0, 10),
      className.includes("_") ? "個別" : "集団",
      reportFormUrl
    ];
    sheet.appendRow(rowData);

    // 新規登録成功後にキャッシュJSONを更新
    var lowerBoundKey = getSubmittedLowerBoundKey_();
    var taskDateKey = normalizeDateKey_(dateTime);
    if (taskDateKey && taskDateKey >= lowerBoundKey) {
      submittedTaskMap[taskNo] = status;
    }
    writeSubmittedTaskMap_(sheet, submittedTaskMap);
    writeSubmittedCacheLowerBoundKey_(sheet, lowerBoundKey);
    
    return jsonResponse_({ result: 'success' });
  } else {
    return jsonResponse_({ result: 'error', message: 'Invalid action' });
  }
}


// ############################################
// Get開始　講師のリクエストに対し、実施申告を返す
// ############################################
function doGet(e) {
  var action = e.parameter.action;
  if (action === 'getAllSubmittedTaskNos') {
    return getAllSubmittedTaskNosResponse_();
  }
  if (action === 'getSubmittedList') {
    return getSubmittedListResponse_();
  }

  var name = e.parameter.name;
  var yearMonth = e.parameter.yearMonth;
  
  if (!name || !yearMonth) {
    return ContentService.createTextOutput("エラー: 名前と年月を指定してください。");
  }
  
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("実施申告records");
  var data = sheet.getDataRange().getValues();
  
  var filteredData = data.filter(function(row) {
    var rowDate = new Date(row[3].substring(0, 10)); // D列の日時
    // var rowDate = new Date(row[0]); // A列のTS
    var rowYearMonth = Utilities.formatDate(rowDate, "JST", "yyyy-MM");
    return row[1] === name && rowYearMonth === yearMonth;
  });
  
  if (filteredData.length === 0) {
    return ContentService.createTextOutput("指定された条件に一致するデータがありません。");
  }
  
  var message = name + "先生の" + yearMonth + "の実施申告:\n\n";
  filteredData.forEach(function(row) {
    message += "申告送信日時: " + Utilities.formatDate(new Date(row[0]), "JST", "yyyy-MM-dd HH:mm:ss") + "\n";
    message += "日時: " + row[3] + "\n";
    message += "業務: " + row[2] + "\n";
    message += "STATUS: " + row[5] + "\n";
    message += "- - - - - - -\n";

    // message += "申告送信日時: " + row[0] + "\n";
  });


  // 講師へのシステムDM一覧
  // スプレッドシートのIDを設定・スプレッドシートを開く
  var spreadsheetId = '1TwigbggH4LfAzUrHtsu0IQfNGKEokjkQ-IJHRLGqyPo';
  var DMsheet = SpreadsheetApp.openById(spreadsheetId).getSheetByName('シート1');
  // シートに行を追加
  var ts=Utilities.formatDate(new Date(), "JST", "yyyy-MM-dd HH:mm:ss")
  DMsheet.appendRow([ts,name,message]);

  try {
    genarallyPurpose_PostDM_HijokinWS(name, message);
    return ContentService.createTextOutput("実施申告の履歴をSlackのDMで送信しました。");
  } catch (error) {
    return ContentService.createTextOutput("エラー: " + error.toString());
  }

}

/**
 * POSTリクエストを送信し、メッセージを送信
 * @param {string} name 送信先の名前。講師台帳のslackID_listシートにあると送信ができる
 * @param {string} message 本文
 * @returns {[boolean, string]} 送信結果。エラーの場合は[false, "エラー： 〜〜"（エラーメッセージ）]、成功の場合は[true, "成功: 送信完了"]
 */
         
function generallyPurpose_PostDM(name, message) {
  var sendto = normalizeSendtoName_(name);
  if (!sendto) {
    Logger.log("エラー: sendto が空です");
    return [false, "エラー: 送信先名が空です"];
  }

  let url = "https://script.google.com/macros/s/AKfycbxdCP5mXqI-pK11r823K5doMgvR1lfJBZ2DgIz11shLTGDz-vHwj8t0ypGDMeIchAit/exec";
  let options = {
    "method": "post",
    "payload": JSON.stringify({
      "PW":"Kyotoijuku110akiraSlackDM",
      "sendto":sendto,
      "msg" :message
    })
  }
  let response = UrlFetchApp.fetch(url,options);
  let jsonResponse = JSON.parse(response.getContentText());
  let returnValue;
  if (jsonResponse.success) {
    Logger.log("成功: " + jsonResponse.message);
    returnValue = [true, "成功: "+jsonResponse.message];
  } else {
    Logger.log("エラー: " + jsonResponse.message);
    returnValue = [false, "エラー: "+jsonResponse.message];
  }
  return returnValue;
}


// ############################
// JUST.DBに書き込む
// ############################

function writeToJustDB() {
  // スプレッドシートを取得
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('実施申告records');

  // データを取得
  var data = sheet.getDataRange().getValues();

  var records = {
    records: data.slice(2)
      .filter(function(row) {
        var timestamp = new Date(row[0]);
        
        // 前日の日付を取得
        var yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);

        // ローカルタイムで日付部分（YYYY-MM-DD）だけを比較
        return timestamp.toLocaleDateString() === yesterday.toLocaleDateString();        
        // // 日付部分（YYYY-MM-DD）だけを比較
        // return timestamp.toISOString().split('T')[0] === yesterday.toISOString().split('T')[0];
      })
      .map(function(row) {
        var ts = Utilities.formatDate(new Date(row[0]),'JST',"yyyy-MM-dd")+"T"+ 
                Utilities.formatDate(new Date(row[0]),'JST',"HH:mm")+"+09:00";
        
        return {
          record: {
            field_1720486013: ts,
            field_1720486028: row[1],    //講師名
            field_1720486035: row[2].replace("🟦","■").replace("[🔴🟠]","●"),    //授業名
            field_1720486064: row[3],    //日時
            field_1726666701: row[4]+"",    //予定業務No
            field_1720486091: row[5]    //ステータス
          }
        };
      })
  };

  Logger.log(records);

  
  // レコードが存在しない場合は処理を終了
  if (records.length === 0) {
    Logger.log("No new records.");
    return;
  }
 
  // JUST.DBのAPI設定
  var apiKey = 'hw2PdFaHjNtTVbudoMbml8KJbPB3avS1';       //   
  var url = 'https://kyotoijuku.just-db.com/sites/api/services/v1/tables/table_1717983237/records/';     //  


  // ## 2024/10/23　追加
  const BATCH_SIZE = 1; // 一度に送信するレコード数     実は使用上1ずつしか無理。。。
  const recordsArray = records.records; // 配列を取得

  // レコードを指定サイズのチャンクに分割
  for (let i = 0; i < recordsArray.length; i += BATCH_SIZE) {
    // recordsからBATCH_SIZE分のレコードを取得
    const chunk = recordsArray.slice(i, i + BATCH_SIZE);
    
    // リクエストオプション
    const options = {
      'method': 'post',
      'contentType': 'application/json',
      'headers': {
        'Authorization': 'Bearer ' + apiKey
      },
      'payload': JSON.stringify({records: chunk}),
      'muteHttpExceptions': true
    };

    try {
      Logger.log(`Sending batch ${Math.floor(i/BATCH_SIZE) + 1} of ${Math.ceil(recordsArray.length/BATCH_SIZE)} (${chunk.length} records)`);
      
      const response = UrlFetchApp.fetch(url, options);
      const responseCode = response.getResponseCode();
      const responseContent = response.getContentText();
      
      if (responseCode === 200 || responseCode === 201) {
        Logger.log(`Successfully sent batch ${Math.floor(i/BATCH_SIZE) + 1}. Records ${i+1} to ${i+chunk.length}`);
      } else {
        Logger.log(`Failed to send batch ${Math.floor(i/BATCH_SIZE) + 1}. Response code: ${responseCode}`);
        Logger.log(`Response content: ${responseContent}`);
        Logger.log(`Failed records index range: ${i+1} to ${i+chunk.length}`);
      }
      
      // API制限に引っかからないよう、バッチ間で待機
      if (i + BATCH_SIZE < recordsArray.length) {
        Logger.log("Waiting 2 seconds before sending next batch...");
        Utilities.sleep(2000); // 2秒待機
      }
      
    } catch (error) {
      Logger.log(`Error sending batch ${Math.floor(i/BATCH_SIZE) + 1}: ${error.toString()}`);
      if (error.message) {
        Logger.log(`Error message: ${error.message}`);
      }
      Logger.log(`Failed records index range: ${i+1} to ${i+chunk.length}`);
    }
  }

  Logger.log(`Completed sending all ${recordsArray.length} records in ${Math.ceil(recordsArray.length/BATCH_SIZE)} batches`);

 
  Logger.log("All records processed.");
}


// 日付をフォーマットする関数
function formatDate(date) {
  return Utilities.formatDate(date, 'Asia/Tokyo', "yyyy-MM-dd'T'HH:mm:ssXXX");
}



