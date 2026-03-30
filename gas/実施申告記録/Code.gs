var ROUTER_KIWEB2_SOURCE_ = 'kiweb2';

function normalizeRouterSource_(value) {
  return String(value === undefined || value === null ? '' : value).trim().toLowerCase();
}

function parseRouterPostJson_(e) {
  try {
    var raw = e && e.postData ? e.postData.contents : '';
    if (!raw) return null;
    return JSON.parse(raw);
  } catch (error) {
    return null;
  }
}

function resolveRouterSourceForPost_(e) {
  var sourceFromQuery = normalizeRouterSource_(e && e.parameter ? e.parameter.source : '');
  if (sourceFromQuery) {
    return sourceFromQuery;
  }
  var body = parseRouterPostJson_(e);
  return normalizeRouterSource_(body && typeof body === 'object' ? body.source : '');
}

function resolveRouterSourceForGet_(e) {
  return normalizeRouterSource_(e && e.parameter ? e.parameter.source : '');
}


/**
 * function writeStatusJson() 　★260312 中畠作成
 * 「当日前日実施申告records」シートからバッチ処理でキャッシュjsonを作成する。
 * 	buildTodayPrevDeclarationMapToI1 が消えていたので作成した
 *  開始rowは3
 */
function writeStatusJson() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const src = ss.getSheetByName('当日前日実施申告records');
  const dst = ss.getSheetByName('実施申告records');

  const data = src.getDataRange().getValues();
  const obj = {};

  for (let i = 2; i < data.length; i++) {       // 3行目からがデータ
    const no = data[i][4];     // E列
    const status = data[i][5]; // F列
    if (no !== '' && no != null) {
      obj[no] = status;
    }
  }

  dst.getRange('I1').setValue(JSON.stringify(obj));
  dst.getRange('J1').setValue(Utilities.formatDate(new Date(),'JST','yyyy-MM-dd HH:mm'));
}



// #################################
// ki webから受けた実施申告を記録する
// #################################
function doPost(e) {
  // var source = resolveRouterSourceForPost_(e);
  // if (source === ROUTER_KIWEB2_SOURCE_) {
  //   if (typeof kiweb2DoPost_ === 'function') {
  //     return kiweb2DoPost_(e);
  //   }
  //   return ContentService.createTextOutput(JSON.stringify({ result: 'error', message: 'kiweb2 logic not found' }))
  //     .setMimeType(ContentService.MimeType.JSON);
  // }

  //このssは、実施申告だけ！　（予定業務の表示と切り分けた）
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('実施申告records');
  const data = JSON.parse(e.postData.contents);
  const CELL_A1 = 'I1';

  if (data.action === 'updateStatus') {
    const timestamp = new Date();
    const rowData = [
      Utilities.formatDate(timestamp,'JST',"yyyy-MM-dd HH:mm:ss"),
      data.teacherName,
      data.className,
      data.dateTime,
      data.taskNo,
      data.status,
      data.dateTime.substring(0, 10),
      data.className.includes("_") ? "個別" : "集団"
    ];
    
    sheet.appendRow(rowData);

    // このあと、
    // I1 の現在値を取得
    const currentText = sheet.getRange(CELL_A1).getValue();
    // 空なら空オブジェクト、値があればJSONとしてparse
    const obj = currentText ? JSON.parse(currentText) : {};
    // { taskNo: status } を追加・更新
    obj[data.taskNo] = data.status;
    // stringifyしてI1へ保存
    sheet.getRange(CELL_A1).setValue(JSON.stringify(obj));
    sheet.getRange(CELL_A1).offset(0,1).setValue(Utilities.formatDate(new Date(),'JST','yyyy-MM-dd HH:mm'));


    return ContentService.createTextOutput(JSON.stringify({ result: 'success' }))
      .setMimeType(ContentService.MimeType.JSON);
  } else {
    return ContentService.createTextOutput(JSON.stringify({ result: 'error', message: 'Invalid action' }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}


// ############################################
// Get開始　講師のリクエストに対し、実施申告を返す
// ############################################
function doGet(e) {
  var source = resolveRouterSourceForGet_(e);
  if (source === ROUTER_KIWEB2_SOURCE_) {
    if (typeof kiweb2DoGet_ === 'function') {
      return kiweb2DoGet_(e);
    }
    return ContentService.createTextOutput(JSON.stringify({ result: 'error', message: 'kiweb2 logic not found' }))
      .setMimeType(ContentService.MimeType.JSON);
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
    generallyPurpose_PostDM(name, message);
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
  let url = "https://script.google.com/macros/s/AKfycbxdCP5mXqI-pK11r823K5doMgvR1lfJBZ2DgIz11shLTGDz-vHwj8t0ypGDMeIchAit/exec";
  let options = {
    "method": "post",
    "payload": JSON.stringify({
      "PW":"Kyotoijuku110akiraSlackDM",
      "sendto":name.replace(" ","").replace("　",""),
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




