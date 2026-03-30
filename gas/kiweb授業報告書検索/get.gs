/**
 * getリクエストを受けて授業報告書を検索する
 */
function doGet(e) {
  let teacherName = e.parameter.teacherName || '';
  let subject = e.parameter.subject;
  let yearMonth = e.parameter.yearMonth;
  let studentName = e.parameter.studentName || '';
  let className = e.parameter.className || '';
  let action = e.parameter.action; // どちらのボタンが押されたか
  let format = e.parameter.format || '';
  console.log("teacherName:", teacherName, "subject:",subject, "yearMonth:", yearMonth,
              "studentName:", studentName, "className:", className, "action:", action, "format:", format);
  
  try {
    let data = filterData(teacherName, subject, yearMonth, studentName, className);
    console.log("件数", data.length);

    // web表示ボタンが押されたとき
    if(action==='browser'){
      if(format === 'json'){
        return ContentService.createTextOutput(JSON.stringify({
                  result: 'success',
                  rows: buildResultRows(data, subject),
                  count: data.length,
                  source: 'gas_json'
                }))
                .setMimeType(ContentService.MimeType.JSON);
      }
      // 表示するHTMLを作成
      let htmlContent = createTable(data, teacherName, subject, yearMonth, studentName, className);
      return HtmlService.createHtmlOutput(htmlContent).setTitle("授業報告書 検索結果表示").setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
    }

    // slack送信ボタンが押されたとき
    else if(action==='slackDM'){
      // Slackを送信
      let blocksJson = createBlocksJson(data, teacherName, subject, yearMonth, studentName, className);
      let slackDMBoolean = postMessageForBlockKit(teacherName, blocksJson);
      if(slackDMBoolean[0]){
        return ContentService.createTextOutput(JSON.stringify({ result: 'success' }))
                .setMimeType(ContentService.MimeType.JSON);
      }
      else {
        return ContentService.createTextOutput(JSON.stringify({ result: 'error', message: 'Invalid action' }))
                .setMimeType(ContentService.MimeType.JSON);
      }
    }
  }

  catch (error) {
    return ContentService.createTextOutput('エラーが発生しました: ' + error.toString());
  }
}


/**
 * 指定した科目のシートから、フィルタリングしたデータを返す
 * @param {string} teacherName 講師氏名
 * @param {string} subject 科目
 * @param {string} yearMonth 検索対象とする年月
 * @param {string} studentName 生徒氏名
 * @param {string} className 授業名
 * @returns {string[][]} 検索結果が入った二次元配列
 */
function filterData(teacherName, subject, yearMonth, studentName, className){
  console.log("in function filterData");
  let sheet = SpreadsheetApp.openById(getSSID(yearMonth)).getSheetByName(subject);
  let data = sheet.getRange(3,1,sheet.getLastRow(),13).getValues();

  // 指定条件でフィルタリングして返す
  let filteredData = [];
  for(let i=0;i<data.length;i++){
    if(data[i][4].trim().includes(teacherName) && data[i][5].trim().includes(studentName) && data[i][6].trim().includes(className)){
      filteredData.push(data[i]);
    }
  }
  return filteredData;
}


/**
 * 検索結果をweb表示用のJSON形式に変換
 * @param {string[][]} data 検索結果が入った二次元配列
 * @param {string} subject 科目
 * @returns {Object[]} JSONレスポンス用の行配列
 */
function buildResultRows(data, subject){
  return data.map(function(row) {
    let date = new Date(row[1]);
    let studentName = '';
    let className = '';

    if (row[0] === "個別授業") {
      studentName = row[5];
      className = row[7];
    } else {
      className = row[6];
    }

    return {
      date: Utilities.formatDate(date, 'Asia/Tokyo', "MM/dd(EEE)"),
      timeSlot: row[2],
      branch: row[3],
      teacherName: row[4],
      studentName: studentName,
      className: className,
      subject: subject,
      textbook: row[8],
      chapter: row[9],
      homework: row[10],
      testContent: row[11],
      testScore: row[12]
    };
  });
}


/**
 * 年月をもらって該当のスプシのIDを返す
 * @param {string} yearMonth 年月
 * @return {string} 該当のスプシのID
 */
function getSSID(yearMonth){
  let valuesList = SHEET_SHEETS.getRange(2,1,SHEET_SHEETS.getLastRow(),1).getDisplayValues().flat();
  let rowNum = valuesList.indexOf("【kiweb検索用】" + yearMonth)+2; // 見出し行数＋0から数える差分
  return SHEET_SHEETS.getRange(rowNum,2).getValue();
}


/**
 * 検索結果表示時のHTMLを作成
 * @param {string[][]} data 検索結果が入った二次元配列
 * @param {string} teacherName 講師氏名
 * @param {string} subject 科目
 * @param {string} yearMonth 検索対象とする年日
 * @param {string} studentName 生徒氏名
 * @param {string} className 授業名
 * @returns {string} h1タグとh2タグのみのHTML文字列
 */
function createTable(data, teacherName, subject, yearMonth, studentName, className){
  console.log("in function createTable");
  let tableHtml = 
    '<h1>授業報告書 検索結果　ver.1.1</h1>' +
    '<h2>講師名： ' + teacherName + ' / 科目： ' + subject + ' / 対象期間： ' + yearMonth + '\n' +
        ' / 生徒氏名： ' + studentName + ' / 授業名： ' + className + '</h2>';
  
  if (data.length === 0) {
    return tableHtml + '<p>該当するデータがありません。</p>';
  }
  
  tableHtml += '<table border="1" style="border-collapse: collapse; width: 100%;">';
  tableHtml += '<tr><th>授業日時</th><th>実施校舎</th><th>授業タイプ</th><th><nobr>講師氏名</nobr></th><th><nobr>生徒氏名/</nobr>授業名</th><th>使用テキスト名</th><th>進んだ内容</th><th>次回の宿題内容</th><th>小テスト内容</th><th>同左得点</th></tr>';
  
  data.forEach(function(row) {
    let date = new Date(row[1]);
    let formattedDate = Utilities.formatDate(date, 'Asia/Tokyo', "MM/dd(EEE)");
    let studentOrClass = row[0] === "個別授業" ? row[5] + '<br>' + row[7] : row[6];
    
    tableHtml += '<tr>';
    tableHtml += '<td>' + formattedDate + '<br>' + row[2] + '</td>';
    tableHtml += '<td>' + row[3] + '</td>';
    tableHtml += '<td>' + row[0].replace("授業", "") + '</td>';
    tableHtml += '<td>' + row[4] + '</td>';
    tableHtml += '<td>' + studentOrClass + '</td>';
    tableHtml += '<td>' + row[8] + '</td>';
    tableHtml += '<td>' + row[9] + '</td>';
    tableHtml += '<td>' + row[10] + '</td>';
    tableHtml += '<td>' + row[11] + '</td>';
    tableHtml += '<td>' + row[12] + '</td>';
    tableHtml += '</tr>';
  });
  
  tableHtml += '</table>';
  return tableHtml;
}


/**
 * Slack送信完了時のHTMLを作成
 * @param {string} teacherName 講師氏名
 * @param {string} subject 科目
 * @param {string} yearMonth 検索対象とする年日
 * @param {string} studentName 生徒氏名
 * @param {string} className 授業名
 * @returns {string} h1タグとh2タグのみのHTML文字列
 */
function createHtmlForSlackDM(teacherName, subject, yearMonth, studentName, className){
  console.log("in function createHtmlForSlackDM");
  let htmlForSlackDM =
    '<h1>授業報告書の検索結果が正常に送信されました</h1>' +
    '<h2>講師名： ' + teacherName + ' / 科目： ' + subject + ' / 対象期間： ' + yearMonth + '\n' +
        ' / 生徒氏名： ' + studentName + ' / 授業名： ' + className + '</h2>';
  return htmlForSlackDM;
}
