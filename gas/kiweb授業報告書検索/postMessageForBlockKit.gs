/**
 * Block Kitを使用したいとき、氏名とリクエストのJSONのblocks部分を指定してメッセージ送信
 * @param {string} name 氏名
 * @param {string} blocksJson リクエストのJSONのblocks部分
 */
function postMessageForBlockKit(name, blocksJson) {
  console.log("in function postMessageForBlockKit");
  try{
    // チャンネルIDを取得し、メッセージを送信
    let memberId = getMemberID(name);
    let channelID = getChannelID(memberId);
    postJson(channelID, blocksJson);
    return [true, "OK"];
  } catch (error) {
    // 何らかのエラーが発生した場合のレスポンス
    return [false, error.message];
  }
}


/**
 * 氏名からメンバーIDを取得
 * @param {string} name 氏名
 * @returns {string} メンバーID
 */
function getMemberID(name) {
  console.log("in function getMemberID");

  let rowLength = SHEET_IDS_HIJOKIN.getRange('B2:B').getValues().filter(String).length; // 列の値を全て取得し、空白の要素を除いた長さを取得
  // B2からCの最終行まで（名前とユーザーID）を取得
  let nameIDRange = SHEET_IDS_HIJOKIN.getRange(2, 2, rowLength, 2);
  let nameIDValues = nameIDRange.getValues();
  for(let i=0;i<rowLength;i++){
    // 名前が一致したものがあれば
    if(nameIDValues[i][0].replace(/　/g, "")==name){
      return nameIDValues[i][1];
    }
  }
  // 全部探しても見つからなかった場合
  throw new Error("スプシ内にメンバーIDが見つかりませんでした");
}


/**
 * メンバーIDからDMのチャンネルIDを取得
 * @param {string} memberID メンバーID
 * @returns {string} チャンネルID
 */
function getChannelID(memberId) {
  console.log("in function getChannelID");

  let getChannelIDOptions = {
    "method" : "post",
    "contentType": "application/x-www-form-urlencoded",
    "payload" : {
      "token": BOT_TOKEN_HIJOKIN,
      "users": memberId
    }
  }

  let response = UrlFetchApp.fetch(API_URL_OPEN_DM, getChannelIDOptions);
  // そもそもHTTPリクエスト失敗
  if (response.getResponseCode() >= 400) {
    throw new Error('HTTP error response: ' + response.getResponseCode());
  }

  let obj = JSON.parse(response);
  // SlackAPIを呼び出せたけどチャンネルIDの取得失敗時
  if(!obj.ok){
    throw new Error("メンバーIDからチャンネルIDを取得するのに失敗しました\n"+obj.error);
  }
  return obj.channel.id;
}


/**
 * Block Kitを使用したいときのメッセージ送信部品
 * @param {string} channelID チャンネルID
 * @param {string} blocksJson リクエストのJSONのblocks部分
 */
function postJson(channelID, blocksJson){
  console.log("in function postJson");
  console.log("blocksJson", JSON.stringify(blocksJson));

  let messageOptions = {
    "method": "post",
    "contentType": "application/x-www-form-urlencoded",
    "payload": {
      "token": BOT_TOKEN_HIJOKIN,
      "channel": channelID,
    }
  };
  // blocksJsonをシリアライズして追記
  messageOptions.payload.blocks = JSON.stringify(blocksJson.blocks);
  console.log("messageOptions", JSON.stringify(messageOptions));

  let response = UrlFetchApp.fetch(API_URL_POST_MESSAGE, messageOptions);
  // そもそもHTTPリクエスト失敗
  if (response.getResponseCode() >= 400) {
    throw new Error('HTTP error response: ' + response.getResponseCode());
  }

  let obj = JSON.parse(response);
  // SlackAPIを呼び出せたけどメッセージ送信失敗時
  if(!obj.ok){
    throw new Error("メッセージを送信するのに失敗しました\n"+obj.error);
  }
}
