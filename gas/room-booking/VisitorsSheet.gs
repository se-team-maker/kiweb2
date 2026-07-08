/**
 * 来客シートを生成するGAS
 * 使い方: スプレッドシートにバインドして initializeVisitorsSheet() を実行
 */

const VISITORS_SHEET_NAME = '来客';
const RESERVATIONS_SHEET_NAME = 'Reservations';
const VISITORS_HEADERS = ['日付', '開始時刻', '所要時間', '部屋', '予約者', '会議名', '来客'];

function initializeVisitorsSheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName(VISITORS_SHEET_NAME);

  if (!sheet) {
    sheet = ss.insertSheet(VISITORS_SHEET_NAME);
  } else {
    sheet.clear();
  }

  sheet.getRange('B1').setValue('基準日付');
  sheet.getRange('C1').setFormula('=TODAY()');
  sheet.getRange('D1').setValue('');

  const baseLabel = sheet.getRange('B1');
  baseLabel
    .setBackground('#1a3dff')
    .setFontColor('#ffffff')
    .setFontWeight('bold')
    .setHorizontalAlignment('center');

  sheet.getRange('C1')
    .setNumberFormat('yyyy-mm-dd(ddd)')
    .setFontWeight('bold');

  sheet.getRange('D1').setBackground('#5f6368');

  const headerRange = sheet.getRange(3, 2, 1, VISITORS_HEADERS.length);
  headerRange.setValues([VISITORS_HEADERS]);
  headerRange
    .setFontWeight('bold')
    .setHorizontalAlignment('center')
    .setBorder(false, false, true, false, false, false, '#c4c7c5', SpreadsheetApp.BorderStyle.SOLID);

  sheet.getRange(4, 2).setFormula(buildVisitorsFormula_());
  sheet.setFrozenRows(3);

  sheet.getRange('B4:B').setNumberFormat('yy/mm/dd(ddd)');
  sheet.getRange('C4:D').setNumberFormat('hh:mm');
  sheet.getRange('B4:D').setHorizontalAlignment('center');
  sheet.getRange('E4:H').setHorizontalAlignment('left');

  Logger.log('Visitors sheet initialized');
}

function buildVisitorsFormula_() {
  // C=日付, D=開始時刻, E=所要時間(分), K=部屋名, G=予約者, F=会議名, H=来客
  return '=IFERROR(QUERY(' + RESERVATIONS_SHEET_NAME + '!A2:K, "select C, D, E, K, G, F, H where H <> \'\' order by C, D", 0), "")';
}
