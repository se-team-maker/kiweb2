/**
 * フォームからsubmitされるデータをもとに、スプシに記録
 * @param {string} data フォームからsubmitされるデータ
 */
function writeToSheet(data) {
  console.log("★step3 : in function writeToSheet");
  
  // 記入先のシートを取得
  const SHEET = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("DATA");
  
  // いったん全部配列に記録
  let rowData = [
    new Date(), // Timestamp
    data.lecturer_name,
    data.email,
    data.department,
    data.original_date,
    data.original_time,
    data.class_type,
    data.student_grade,
    data.student_name,
    data.change_reason_type,
    data.change_reason_detail,
    data.change_type,
    data.substitute_lecturer || "",
    data.rescheduled_date || "",
    data.rescheduled_time || "",
    data.permission || "",
    data.student_contact,
    data.uncontacted_students || ""
  ];

  let useData = [];
  let useIndexforSheet; // 変更の類型によって使用する質問と回答が変わるが、そのインデックスを配列に格納
  if(data.change_type=="代講"){
    useIndexforSheet = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 16];
    var flg="🔵";
  }
  else if(data.change_type=="未定振替の日時決定"||data.change_type=="振替"){
    useIndexforSheet = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14, 16];
    var flg="🔵";
  }
  else if(data.change_type=="緊急欠勤"||data.change_type=="通常欠勤"){
    useIndexforSheet = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 16];
    var flg="🔴";
  }
  else if(data.change_type=="未定振替"){
    useIndexforSheet = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 16];
    var flg="🔵";
  }
  else if(data.change_type=="振替＋代講"){
    useIndexforSheet = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 16];
    var flg="🔵";
  }
  else if(data.change_type=="追加"){
    useIndexforSheet = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14, 15, 16];
    var flg="🔵";
  }
  if(data.student_contact=="未完了"){
    useIndexforSheet.push(17);
  }

  // 変更の類型を考慮して、使用するものだけを配列に入れてシートに書き込む
  for(let i of useIndexforSheet){
    useData[i] = rowData[i];
  }
  SHEET.appendRow([''].concat(useData));      //JUST.DB書き込み処理の都合で、左に1列追加

  // 以下　中畠追加　「処理用」シート記入用
    // 「240909山田太郎-2010」　のような処理IDデータを生成
    // var hours = ('0' + data.original_time.getHours()).slice(-2);
    // var minutes = ('0' + data.original_time.getMinutes()).slice(-2);

    var processingID =
        Utilities.formatDate(new Date(data.original_date), 'JST', 'yyMMdd') +
        data.lecturer_name + '-' + 
        data.original_time.substring(0, 5).replace(":", "") +
        // hours + minutes +
        // Utilities.formatDate(data.original_time, 'JST', 'HHmm')+
        flg;

    if (typeof data.class_type === 'string') {
      if (data.class_type.includes('四条烏丸')) {
        var branch= 'SK';
      } else if (data.class_type.includes('円町')) {
        var branch=  'EM';
      } else if (data.class_type.includes('リモート')) {
        var branch=  'OL';
      }
    }

    SpreadsheetApp.getActiveSpreadsheet().getSheetByName("処理用_"+branch).appendRow(['','','','','',processingID].concat(useData));
}

function test(){
  var time_data= SpreadsheetApp.getActiveSpreadsheet().getSheetByName("DATA").getRange('G6').getValue();
  myStr = Utilities.formatDate(time_data,'JST',"HHmm");
  // myStr = time_data.substring(0, 5).replace(":", "");
  SpreadsheetApp.getActiveSpreadsheet().getSheetByName("テーブルサンプル").getRange('B75').setValue(myStr);
}
