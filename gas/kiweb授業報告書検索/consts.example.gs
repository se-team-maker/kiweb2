// Copy this file to consts.gs for local use and replace the placeholder values.
const SHEET_SHEETS = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("SHEET_NAME");

const SHEET_IDS = SpreadsheetApp.openById("SPREADSHEET_ID").getSheetByName("USER_ID_LIST");
const BOT_TOKEN = "YOUR_SLACK_BOT_TOKEN";
const API_URL_POST_MESSAGE = "https://slack.com/api/chat.postMessage";
const API_URL_OPEN_DM = "https://slack.com/api/conversations.open";

const SHEET_IDS_HIJOKIN = SpreadsheetApp.openById("SPREADSHEET_ID").getSheetByName("PARTTIME_USER_ID_LIST");
const BOT_TOKEN_HIJOKIN = "YOUR_SLACK_BOT_TOKEN";
