
エディタ
get.gs
templatesForGet.gs
postMessageForBlockKit.gs
createNewSheet.gs
consts.gs
.

12345678910111213141516171819202122232425262728293031323334
/**
 * Slack送信のblocksJsonを作成
 * @param {string[][]} data 検索結果が入った二次元配列
 * @param {string} teacherName 講師氏名
 * @param {string} subject 科目
 * @param {string} yearMonth 検索対象とする年日
 * @param {string} studentName 生徒氏名
 * @param {string} className 授業名
 * @returns {string} h1タグとh2タグのみのHTML文字列
 */

