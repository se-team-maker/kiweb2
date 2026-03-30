// 日付をフォーマットする補助関数
function formatDate(date) {
  return Utilities.formatDate(date, 'Asia/Tokyo', "yyyy/MM/dd");
}

// 時間をフォーマットする補助関数
function formatTime(time) {
  if (!time) return "";
  return time + "～";
}

// // 日付をフォーマットする関数
// function formatDate(date) {
//   return Utilities.formatDate(date, 'Asia/Tokyo', "yyyy-MM-dd'T'HH:mm:ssXXX");
// }

/**
 * kRwcPzXpQQ7uraLU3tbSjAqB3RHVKPr7
 * https://kyotoijuku.just-db.com/sites/api/services/v1/tables/table_1717981766/records/
 * 
 * {
    "records": [
        {
            "record": {
                "field_1719472780": "",
                "field_1719472798": "落合晟",
                "field_1719472850": "2025-12-30T00:00+09:00",
                "field_1719472867": "14:40～",
                "field_1719472899": "個別",
                "field_1719472915": "高卒",
                "field_1719472923": "薗田淳之介",
                "field_1719472940": "生徒理由",
                "field_1719473186": "体調不良",
                "field_1719473201": "生徒_未定振替",
                "field_1719473225": "",
                "field_1719473259": "",
                "field_1719473272": "",
                "field_1719473627": "",
                "field_1719473645": "",
                "field_1719474468": "",
                "field_1767084486": ""
            }
        }
    ]
}
 */

function writeToJustDB(data) {
  // console.log(JSON.stringify(data));

  const apiKey = 'kRwcPzXpQQ7uraLU3tbSjAqB3RHVKPr7';
  const url = 'https://kyotoijuku.just-db.com/sites/api/services/v1/tables/table_1717981766/records/';
  
  // var rescheduled_date2 = data.rescheduled_date
  //   ? Utilities.formatDate(new Date(data.rescheduled_date), 'Asia/Tokyo', 'yyyy-MM-dd') + 'T00:00+09:00'
  //   : '';

  // var rescheduled_time2 = data.rescheduled_time
  //   ? data.rescheduled_time + '～'
  //   : '';

  // recordsの構造を正しい階層に修正
  const payload = { 
    "records": [{
        "record": {
          "field_1719472780": "",//メールアドレス	
          "field_1719472798": data.lecturer_name,  //担当講師氏名	
          "field_1719472850": Utilities.formatDate(new Date(data.original_date),'JST','yyyy-MM-dd')+'T00:00+09:00',  //本来の日付	
          "field_1719472867": data.original_time,  //本来の時間帯	
          "field_1719472899": data.class_type+'：'+data.lesson_school,  //授業形態	
          "field_1719472915": data.student_grade,  //生徒の学年	data.class_name
          "field_1719472923": data.class_name,  //授業・指導名	
          "field_1719472940": data.change_reason_type,  //変更理由の類型	
          "field_1719473186": data.change_reason_detail,  //変更理由の内容	
          "field_1719473201": data.change_type,  //変更の類型	
          "field_1719473225": data.substitute_lecturer || "",  //代行講師名
          // "field_1719473259": Utilities.formatDate(new Date(data.rescheduled_date),'JST','yyyy-MM-dd')+'T00:00+09:00'|| "",  //	rescheduled_date,  //振替先日程	
          // "field_1719473272": data.rescheduled_time|| "",  //振替先時間帯。260109 	data.rescheduled_time が rescheduled_time になってて止まってた。。。
          "field_1719473627": data.permission|| "",  //追加授業料確認
          "field_1719473645": data.student_contact|| "",  //生徒への連絡	
          "field_1719474468": data.uncontacted_students || "",  //連絡未済の生徒がいる場合、その氏名
          "field_1767084486": data.business_no //予定業務No
      }
    }]
  };

  // data.rescheduled_dateが空でない場合のみ追加
  const record = payload.records[0].record;
  if (data.rescheduled_date && data.rescheduled_date !== "") {
    record["field_1719473259"] = Utilities.formatDate(new Date(data.rescheduled_date),'JST','yyyy-MM-dd')+'T00:00+09:00';
  }
  // data.rescheduled_timeも同様に処理したい場合
  if (data.rescheduled_time && data.rescheduled_time !== "") {
    record["field_1719473272"] = data.rescheduled_time;
  }

  const options = {
    'method': 'post',
    'contentType': 'application/json',
    'headers': {
      'Authorization': 'Bearer ' + apiKey
    },
    'payload': JSON.stringify(payload)  // 既にrecordsの構造が正しいので、そのままStringify
  };

  try {
    const response = UrlFetchApp.fetch(url, options);
    const responseCode = response.getResponseCode();
    if (responseCode === 200 || responseCode === 201) {
      Logger.log("Record successfully sent.");
      return {
        success: true,
        message: "Record successfully sent",
        response: JSON.parse(response.getContentText())
      };
    } else {
      Logger.log("Failed to send record. Response code: " + responseCode);
      return {
        success: false,
        message: "Failed to send record",
        error: response.getContentText()
      };
    }
  } catch (error) {
    Logger.log("Error sending record: " + error.toString());
    throw error;
  }
}


// function writeToJustDB(data) {
//   console.log(JSON.stringify(data));

//   const apiKey = 'N9lviyP0XVeAK5Rh8UhcDF0F1nopI0CD';
//   const url = 'https://kyotoijuku.just-db.com/sites/api/services/v1/tables/table_1717981766/records/';
  
//   var rescheduled_date2 = data.rescheduled_date
//     ? Utilities.formatDate(new Date(data.rescheduled_date), 'Asia/Tokyo', 'yyyy-MM-dd') + 'T00:00+09:00'
//     : '';

//   var rescheduled_time2 = data.rescheduled_time
//     ? data.rescheduled_time + '～'
//     : '';

//   // recordsの構造を正しい階層に修正
//   const payload = { 
//     records: [{
//       record: {
//         field_1719472780: data.email,  //メールアドレス	
//         field_1767084486: data.business_no, //予定業務No
//         field_1719472798: data.lecturer_name,  //担当講師氏名	
//         field_1719472850: Utilities.formatDate(new Date(data.original_date),'JST','yyyy-MM-dd')+'T00:00+09:00',  //本来の日付	
//         field_1719472867: data.original_time +'～',  //本来の時間帯	
//         field_1719472899: data.class_type+'：'+data.lesson_school,  //授業形態	
//         field_1719472915: data.student_grade,  //生徒の学年	data.class_name
//         field_1719472923: data.class_name,  //授業・指導名	
//         field_1719472940: data.change_reason_type,  //変更理由の類型	
//         field_1719473186: data.change_reason_detail,  //変更理由の内容	
//         field_1719473201: data.change_type,  //変更の類型	
//         field_1719473225: data.substitute_lecturer || "",  //代行講師名
//         field_1719473259: rescheduled_date2,  //振替先日程	
//         field_1719473272: rescheduled_time2,  //振替先時間帯	
//         field_1719473627: data.permission,  //追加授業料確認	
//         field_1719473645: data.student_contact,  //生徒への連絡	
//         field_1719474468: data.uncontacted_students || "",  //連絡未済の生徒がいる場合、その氏名
//         field_1740902212: checkIfRequired(data.student_name) ,             //特定の生徒氏名のときに「連絡必須」という文字列を送る
//         field_1740826773: data.department
//       }
//     }]
//   };
//   const options = {
//     'method': 'post',
//     'contentType': 'application/json',
//     'headers': {
//       'Authorization': 'Bearer ' + apiKey
//     },
//     'payload': JSON.stringify(payload)  // 既にrecordsの構造が正しいので、そのままStringify
//   };

//   try {
//     const response = UrlFetchApp.fetch(url, options);
//     const responseCode = response.getResponseCode();
//     if (responseCode === 200 || responseCode === 201) {
//       Logger.log("Record successfully sent.");
//       return {
//         success: true,
//         message: "Record successfully sent",
//         response: JSON.parse(response.getContentText())
//       };
//     } else {
//       Logger.log("Failed to send record. Response code: " + responseCode);
//       return {
//         success: false,
//         message: "Failed to send record",
//         error: response.getContentText()
//       };
//     }
//   } catch (error) {
//     Logger.log("Error sending record: " + error.toString());
//     throw error;
//   }
// }


/**
 * Config シートの B8:B 範囲内で完全一致する文字列を検索し、
 * 一致した場合に「必要」という文字列を返す関数
 * 
 * @param {string} searchText - 検索する文字列
 * @return {string} - 一致した場合は「必要」、それ以外は空文字列
 */
function checkIfRequired(searchText) {
  // Config シートを取得
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("Config");
  
  if (!configSheet) {
    Logger.log("Config シートが見つかりません");
    return "";
  }
  
  // B8 から B 列の最終行までのデータを取得
  const lastRow = configSheet.getLastRow();
  const rangeB8toB = configSheet.getRange(8, 2, lastRow - 7, 1);
  const values = rangeB8toB.getValues();
  
  // 完全一致する値を検索
  for (let i = 0; i < values.length; i++) {
    if (values[i][0] === searchText) {
      return "連絡必須";
    }
  }
  
  // 一致しなかった場合は空文字列を返す
  return "";
}



function writeToJustDB2() {
  // // スプレッドシートを取得
  // var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('DATA');

  // // データを取得
  // var data = sheet.getDataRange().getValues();

  // // 現在時刻を取得
  // var now = new Date();
  // var oneHourAgo = new Date(now.getTime() - 16 * (60 * 1000)); // 16分前

  // // ヘッダー行を除いたデータを処理し、16分以内のレコードのみフィルタリング
  // var records = data.slice(2)
  //   .filter(function(row) {
  //     var timestamp = new Date(row[1]); // タイムスタンプを日付オブジェクトに変換
  //     return timestamp >= oneHourAgo && timestamp <= now;
  //   })
  //   .map(function(row) {
  //     if (row[15]){
  //       var rescheduled_time= Utilities.formatDate(new Date(row[15]), 'Asia/Tokyo', "HH:mm～");
  //     }
  //     return {
  //       record: {
  //         field_1701316803: formatDate(new Date(row[1])),
  //         field_1719472798: row[2],
  //         field_1719472780: row[3],
  //         field_1719472850: row[5],   //本来の日付
  //         field_1719472867: Utilities.formatDate(new Date(row[6]), 'Asia/Tokyo', "HH:mm～"),         //本来の時間帯
  //         field_1719472899: row[7],
  //         field_1719472915: row[8],
  //         field_1719472923: row[9],
  //         field_1719472940: row[10],
  //         field_1719473186: row[11],
  //         field_1719473201: row[12],
  //         field_1719473225: row[13],
  //         field_1719473259: row[14],
  //         field_1719473272: rescheduled_time,
  //         field_1719473627: row[16],
  //         field_1719473645: row[17],
  //         field_1719474468: row[18],
  //       }
  //     };
  //   });
  
  // // レコードが存在しない場合は処理を終了
  // if (records.length === 0) {
  //   Logger.log("No new records within the last hour.");
  //   return;
  // }
  
  // // JUST.DBのAPI設定
  // var apiKey = 'N9lviyP0XVeAK5Rh8UhcDF0F1nopI0CD';
  // var url = 'https://kyotoijuku.just-db.com/sites/api/services/v1/tables/table_1717981766/records/';
  
  // // 各レコードを個別に送信
  // for (var i = 0; i < records.length; i++) {
  //   var record = records[i];
    
  //   // リクエストオプション
  //   var options = {
  //     'method': 'post',
  //     'contentType': 'application/json',
  //     'headers': {
  //       'Authorization': 'Bearer ' + apiKey
  //     },
  //     'payload': JSON.stringify({records: [record]})
  //   };
    
  //   try {
  //     // API呼び出し
  //     var response = UrlFetchApp.fetch(url, options);
  //     var responseCode = response.getResponseCode();
      
  //     if (responseCode === 200 || responseCode === 201) {
  //       Logger.log("Record " + (i + 1) + " successfully sent.");
  //     } else {
  //       Logger.log("Failed to send record " + (i + 1) + ". Response code: " + responseCode);
  //       Logger.log("Response content: " + response.getContentText());
  //     }
  //   } catch (error) {
  //     Logger.log("Error sending record " + (i + 1) + ": " + error.toString());
  //   }
    
  //   // 少し待機して、APIへの負荷を軽減
  //   Utilities.sleep(1000); // 1秒待機
  // }
  
  // Logger.log("All records processed.");
}


