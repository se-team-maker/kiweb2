

// function sortAndCopyData() {
//   var ss = SpreadsheetApp.getActiveSpreadsheet();
//   var sourceSheet = ss.getActiveSheet();
//   var targetSheet = ss.getSheetByName("ウェブ用データ");
  
//   // B3:Hの範囲を取得
//   var range = sourceSheet.getRange("B3:H" + sourceSheet.getLastRow());
  
//   // D列（インデックスは3）で昇順に並べ替え
//   range.sort({column: 4, ascending: true});
  
//   // 並べ替えたデータをコピー
//   var dataToCopy = range.getValues();
  
//   // // 貼り付け位置の行を計算
//   // var pasteRow = sourceSheet.getRange("J2").getValue() + 3;
  
//   // 「ウェブ用データ」シートの指定位置にデータを貼り付け
//   targetSheet.getRange('C3:J').clearContent();  
//   targetSheet.getRange(3, 3, dataToCopy.length, dataToCopy[0].length).setValues(dataToCopy);
// }

