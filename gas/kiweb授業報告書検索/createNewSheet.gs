/**
 * 毎月25日に、来月用のシートを作成
 */
function createMonthSS() {
  // コピー元のスプレッドシート
  const file = DriveApp.getFileById("1JGYtOfYfi5Xr-9a64-5z8G-1QYNuJpXsD2ynINmi8Zo");

  // コピー先のフォルダ
  const targetFolder = DriveApp.getFolderById("1gZn0XJwX7eBytN2jYSiNayirOxfhPZMZ");

  // スプシ名を設定
  let date = new Date();
  date.setMonth(date.getMonth()+1);
  let yearMonthString = Utilities.formatDate(date, "JST", "yyyy-MM");
  let newSSName = "【kiweb検索用】" + yearMonthString;
  
  // 指定フォルダへコピー実行してリンクを取得
  let newSS = file.makeCopy(newSSName, targetFolder);
  genarallyPurpose_PostDM("石上柊史",newSSName+"作成済");

  // 「シート管理」シートに記録
  SHEET_SHEETS.getRange(SHEET_SHEETS.getLastRow()+1,1,1,3).setValues([[newSSName,newSS.getId(),newSS.getUrl()]]);
  genarallyPurpose_PostDM("石上柊史",newSSName+"シート管理シートに記載済");
}


/**
 * POSTリクエストを送信し、メッセージを送信
 * @param {string} name 送信先の名前。講師台帳のslackID_listシートにあると送信ができる
 * @param {string} message 本文
 * @returns {[boolean, string]} 送信結果。エラーの場合は[false, "エラー： 〜〜"（エラーメッセージ）]、成功の場合は[true, "成功: 送信完了"]
 */
function genarallyPurpose_PostDM(name, message) {
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
    Logger.log('成功: ' + jsonResponse.message);
    returnValue = [true, "成功: "+jsonResponse.message];
  } else {
    Logger.log('エラー: ' + jsonResponse.message);
    returnValue = [false, "エラー: "+jsonResponse.message];
  }
  return returnValue;
}
