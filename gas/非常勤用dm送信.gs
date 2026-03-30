/**
 * slack非常勤WS のDM送信用
 * POSTリクエストを送信し、メッセージを送信
 * @param {string} name 送信先の名前。講師台帳のslackID_listシートにあると送信ができる
 * @param {string} message 本文
 * @returns {[boolean, string]} 送信結果。エラーの場合は[false, "エラー： 〜〜"（エラーメッセージ）]、成功の場合は[true, "成功: 送信完了"]
 */
function genarallyPurpose_PostDM_HijokinWS(name, message) {
  let url = "https://script.google.com/macros/s/AKfycbw5oqgCbztKMngwSaXl8LC2HcZuDKeXGgn7hKg7vNcJ3nMISAZB_9p1bQSzYePIzNYnXw/exec";
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
