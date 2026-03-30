/**
 * フォームからsubmitされるデータをもとに、担当講師と代講担当講師にDM送信
 * @param {string} data フォームからsubmitされるデータ
 * @param {string} qastring 質問と回答をまとめた文字列
 */

//講師へのシステムDM一覧　スプレッドシート
const allMsgsSheet = SpreadsheetApp.openById("1TwigbggH4LfAzUrHtsu0IQfNGKEokjkQ-IJHRLGqyPo").getSheetByName("シート1");

function postDMtoTeachers(data, qastring) {
  console.log("★step4 : in function postDMtoTeachers");
  
  // 特別使う質問だけ名前つけて保存しておく
  let rescheduledDate = data.rescheduled_date || "";
  let rescheduledTime = data.rescheduled_time || "";
  let substituteLecturer = data.substitute_lecturer || "";

  // // 授業の名前を編集、集団のときは「高2医京数学」、個別のときは「高2石上柊史」のようにする
  // if(data.class_type=="対面 四条烏丸校 個別"||data.class_type=="対面 円町校 個別"||data.class_type=="リモート 個別"){
  //   data.student_name = data.student_grade + data.student_name;
  // }

  // Slackの本文
  let message = "*【授業変更申請】 "+ data.class_name +"*\nお世話になっております。以下内容で、授業変更申請を受け付けました。\n\n"+ qastring;
  if(data.change_type=="振替"||data.change_type=="未定振替の日時決定"||data.change_type=="振替＋代講"||data.change_type=="追加"){
    message += "*次のリンクをタップ（クリック）すると、Googleカレンダーに予定を追加する画面に移動します*\n"+createGCalenderURL(data.class_name, rescheduledDate, rescheduledTime);
  }
  else if(data.change_type=="代講"){
    message += "*次のリンクをタップ（クリック）すると、Googleカレンダーに予定を追加する画面に移動します*\n"+createGCalenderURL(data.class_name, data.original_date, data.original_time);
  }
  console.log(data.lecturer_name,message);
  var ts = Utilities.formatDate(new Date(), 'JST', 'yyyy-MM-dd HH:mm:ss');

  // 汎用APIを利用し、DM送信
  let postDMRes = genarallyPurpose_PostDM_HijokinWS(data.lecturer_name, message); // 各先生に送信
  //中畠追加　「講師へのシステムDM一覧」シートに書き込み
  allMsgsSheet.appendRow([ts,data.lecturer_name,message]);

  if(!postDMRes[0]){
    genarallyPurpose_PostDM_HijokinWS("石上柊史", postDMRes[0]+postDMRes[1]+"\n補講代講\n teacher: "+data.lecturer_name+"\n\n"+message); // 送信できなかった場合、俺にコピーを送信しておく
  }
  // 代講の場合は代講を担当する講師にも送信
    if(data.change_type=="代講"||data.change_type=="振替＋代講"||data.change_type=="合同実施"){
      let postDMRes2 = genarallyPurpose_PostDM_HijokinWS(substituteLecturer, message);
      //中畠追加　「講師へのシステムDM一覧」シートに書き込み
      allMsgsSheet.appendRow([ts,substituteLecturer,message]);
      if(!postDMRes2[0]){
      genarallyPurpose_PostDM_HijokinWS("石上柊史", postDMRes2[0]+postDMRes2[1]+"\n補講代講\n teacher: "+substituteLecturer+"\n\n"+message); // 送信できなかった場合、俺にコピーを送信しておく
    }
  }

}

/**
 * 授業名と日時を入力とし、googleカレンダーのリンクを返す関数
 * @param {string} lectureName 授業名
 * @param {string} date 授業予定日
 * @param {string} time 授業予定時間帯
 * @return {string} googleカレンダーのリンク
 */
function createGCalenderURL(lectureName, date, time){
  // timeが空の場合はデフォルト値を設定
  if (!time || time === "") {
    time = "00:00～";
  }
  // timeから開始時間を取り出し、日付形式に変換
  let startTime = time.split("～")[0].replace(":","");
  // 8:20と9:00など、3桁の回答は桁数が違ってURLがエラーになるので処理しておく
  if(startTime.length==3){
    startTime = "0" + startTime;
  }
  // 開始時間から90分後を終了時間とする
  let endTime = String(Moment.moment(startTime, "HHmm").add(90, "minutes").format("HHmm"));
  // newDateとnewTimeから期待する形式の文字列を作成
  let gCalenderURL = "http://www.google.com/calendar/event?action=TEMPLATE&text=【京都医塾%20授業】%20" + lectureName + "&dates=" + date.replace(/-/g, "") + "T" + startTime + "0000/" + date.replace(/-/g, "") + "T" + endTime + "0000";
  // 作成したリンク込みのメッセージを返す
  return gCalenderURL;
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
    Logger.log("成功: " + jsonResponse.message);
    returnValue = [true, "成功: "+jsonResponse.message];
  } else {
    Logger.log("エラー: " + jsonResponse.message);
    returnValue = [false, "エラー: "+jsonResponse.message];
  }
  return returnValue;
}



