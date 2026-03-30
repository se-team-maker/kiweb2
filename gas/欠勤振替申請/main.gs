function doPost(e) { // 8/31石上作成
  console.log("in function doPost");

  // ★step1：フォームの内容を読み込む（失敗した場合は分岐？）
  const DATA = e.parameter;

  // ★step2：担当講師と代講担当講師の名前が正しいか調べ、結果によって分岐する
  let validateNameResult = validateName(DATA.lecturer_name, DATA.substitute_lecturer);
      Logger.log("DATA.lecturer_name:", DATA.lecturer_name);
      Logger.log("DATA.substitute_lecturer:", DATA.substitute_lecturer);
      Logger.log("validateNameResult:", validateNameResult);

  let htmlOutput;
  // 氏名が正しかった場合、step3以降に進む
  
  if(validateNameResult[0][0]&&validateNameResult[1][0]){
    // ★step3：回答をスプシに記入
    writeToSheet(DATA);

    // ★step3-2：回答をJUST.DBに記入
    writeToJustDB(DATA);
    // genarallyPurpose_PostDM('中畠俊彦',DATA);     //中畠追加。postリクエストで受けるデータの形式を確認したい。。。

    // ★step4：担当講師と代講担当講師にDM送信し、DMの内容を記録
    let qastring = createQAstring(DATA);
    postDMtoTeachers(DATA, qastring);

    // ★step5：#22-烏丸-補講代講申請onlineにメッセージ送信
    sendSlackToChannel(DATA, qastring);

    // ★step6：画面表示
    htmlOutput = HtmlService.createHtmlOutput("<H1>申請は正常に送信されました。</H1>");
  }

  // 担当講師氏名が正しくない場合
  else if(!validateNameResult[0][0]){
    htmlOutput = HtmlService.createHtmlOutput("<H1>入力エラー</H1><H2>前のタブに戻り、再提出をお願いします。\nなにかご不明点がありましたら、業務推進部 石上柊史までSlackでご連絡ください。\n\n"+validateNameResult[0][1]+"</H2>");
  }
  // 代講担当講師氏名が正しくない場合
  else if(!validateNameResult[1][0]){
    htmlOutput = HtmlService.createHtmlOutput("<H1>入力エラー</H1><H3>前のタブに戻り、再提出をお願いします。なにかご不明点がありましたら、業務推進部 石上柊史までSlackでご連絡ください。</H3><H3>【エラー内容】"+validateNameResult[1][1]+"</H3>");
  }

  // 画面遷移
  return htmlOutput;
}

/**
 * 担当講師氏名と代講担当講師氏名がsleckメンバーIDリストにあるかどうかを同時に判別する関数
 * @param {string} name1 担当講師氏名
 * @param {string} name2 代講担当講師氏名（undefinedの場合もある）
 * @return {[bool, string], [bool, string]} name1とname2があるかどうかをそれぞれboolに記録、ない場合のメッセージをそれぞれstringに記録
 */
function validateName(name1, name2){
  console.log("★step2 : in function validateName");
  const SLACK_MEMBER_LIST = SpreadsheetApp.openById("1QXrN3ar21fSgz8U39fIQsvyvmbD6vOwGjKVBxsqkUls").getSheetByName("メンバーIDリスト").getRange("B2:B").getValues().flat();
  let exists1=true; // 存在するかを記録する変数
  let exists2=true;
  let message1="";
  let message2="";

  if(!SLACK_MEMBER_LIST.includes(name1)){ // slackメンバーリストに名前がない場合、返り値をfalseに設定
    exists1=false;
    message1="入力された「担当講師氏名」はslackのメンバーIDリストに載っていない名前です。\n担当講師氏名："+name1;
  }
  if(name2!=undefined && name2!="" && !SLACK_MEMBER_LIST.includes(name2)){ // 入力されたがslackメンバーリストに名前がない場合、返り値をfalseに設定
    exists2=false;
    message2="入力された「代講担当講師氏名」はslackのメンバーIDリストに載っていない名前です。\n代講担当講師氏名："+name2;
  }
  return [[exists1,message1],[exists2,message2]];
}


/**
 * フォームからsubmitされるデータをもとに、質問と回答をまとめた文字列を作る関数
 * @param {string} data フォームからsubmitされるデータ
 * @return {string} 質問と回答をまとめた文字列
 */
function createQAstring(data){
  console.log("in function createQAstring")
  let questionList = ["担当講師氏名","授業形態","所属科・指導科目","本来の日付","本来の時間帯",
                      "生徒の学年","授業名／生徒氏名","変更理由の類型","変更理由の内容","変更の類型",
                      "代講をする講師氏名","振替先日付／追加先日付","振替先時間帯／追加先時間帯","追加授業の許可状況","生徒への連絡",
                      "連絡未済の生徒の氏名"];
  let answerList = [data.lecturer_name, data.class_type, data.department, data.original_date,	data.original_time,
                    data.student_grade,	data.student_name, data.change_reason_type,	data.change_reason_detail, data.change_type,
                    data.substitute_lecturer,	data.rescheduled_date, data.rescheduled_time, data.permission, data.student_contact,
                    data.uncontacted_students]

  let useIndex; // 変更の類型によって使用する質問と回答が変わるが、そのインデックスを配列に格納
  if(data.change_type=="代講"){
    useIndex = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 14];
  }
  else if(data.change_type=="振替"||data.change_type=="未定振替の日時決定"){
    useIndex = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 14];
  }
  else if(data.change_type=="緊急欠勤"||data.change_type=="通常欠勤"||data.change_type=="未定振替"){
    useIndex = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 14];
  }
  else if(data.change_type=="振替＋代講"){
    useIndex = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14];
  }
  else if(data.change_type=="追加"){
    useIndex = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 13, 14];
  }
  if(data.student_contact=="未完了"){
    useIndex.push(15);
  }

  // 返り値となる文字列を生成
  let qastring = "";
  for(let i of useIndex){ // 16回ループ
    qastring += "*" + questionList[i] + "*\n" + answerList[i] + "\n";
  }
  return qastring;
}
