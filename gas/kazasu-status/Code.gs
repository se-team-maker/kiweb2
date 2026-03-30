var KAZASU_LOG_SHEET_NAME_ = 'Kazasuログシート';
var KAZASU_LOG_SPREADSHEET_ID_ = '';
var KAZASU_TIMEZONE_ = 'Asia/Tokyo';

function doGet(e) {
  var action = e && e.parameter ? String(e.parameter.action || '') : '';

  if (!action || action === 'getLatestKazasuStatus') {
    var requestedDateKey = normalizeDateKey_(e && e.parameter ? e.parameter.date : '');
    return jsonResponse_(getLatestKazasuStatus_(requestedDateKey));
  }

  return jsonResponse_({
    success: false,
    error: 'Invalid action'
  });
}

function jsonResponse_(payload) {
  return ContentService.createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

function getKazasuLogSheet_() {
  if (KAZASU_LOG_SPREADSHEET_ID_) {
    return SpreadsheetApp.openById(KAZASU_LOG_SPREADSHEET_ID_)
      .getSheetByName(KAZASU_LOG_SHEET_NAME_);
  }

  return SpreadsheetApp.getActiveSpreadsheet().getSheetByName(KAZASU_LOG_SHEET_NAME_);
}

function normalizeStudentKey_(value) {
  return String(value === undefined || value === null ? '' : value)
    .replace(/[\s\u3000]+/g, '')
    .trim();
}

function normalizeDateKey_(value) {
  if (value === null || value === undefined || value === '') return '';

  if (Object.prototype.toString.call(value) === '[object Date]') {
    return Utilities.formatDate(value, KAZASU_TIMEZONE_, 'yyyy/MM/dd');
  }

  var matched = String(value).match(/(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/);
  if (!matched) return '';

  return matched[1] + '/' + ('0' + matched[2]).slice(-2) + '/' + ('0' + matched[3]).slice(-2);
}

function toMillis_(value) {
  if (value === null || value === undefined || value === '') return -1;

  if (Object.prototype.toString.call(value) === '[object Date]') {
    return value.getTime();
  }

  var parsed = new Date(value);
  return isNaN(parsed.getTime()) ? -1 : parsed.getTime();
}

function resolveKazasuEventMillis_(timestampValue, dateValue, timeValue) {
  var timestampMillis = toMillis_(timestampValue);
  if (timestampMillis >= 0) {
    return timestampMillis;
  }

  var dateKey = normalizeDateKey_(dateValue);
  if (!dateKey) {
    return -1;
  }

  var timeMatch = String(timeValue === undefined || timeValue === null ? '' : timeValue)
    .match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
  if (!timeMatch) {
    return -1;
  }

  var combined = dateKey.replace(/\//g, '-') +
    ' ' +
    ('0' + timeMatch[1]).slice(-2) + ':' +
    ('0' + timeMatch[2]).slice(-2) + ':' +
    ('0' + (timeMatch[3] || '00')).slice(-2);

  return toMillis_(combined);
}

function formatKazasuTimeLabel_(timestampValue, timeValue, fallbackMillis) {
  if (Object.prototype.toString.call(timeValue) === '[object Date]') {
    return Utilities.formatDate(timeValue, KAZASU_TIMEZONE_, 'HH:mm');
  }

  var timeMatch = String(timeValue === undefined || timeValue === null ? '' : timeValue)
    .match(/(\d{1,2}):(\d{2})/);
  if (timeMatch) {
    return ('0' + timeMatch[1]).slice(-2) + ':' + ('0' + timeMatch[2]).slice(-2);
  }

  var timestampMillis = toMillis_(timestampValue);
  if (timestampMillis >= 0) {
    return Utilities.formatDate(new Date(timestampMillis), KAZASU_TIMEZONE_, 'HH:mm');
  }

  if (fallbackMillis >= 0) {
    return Utilities.formatDate(new Date(fallbackMillis), KAZASU_TIMEZONE_, 'HH:mm');
  }

  return '';
}

function getLatestKazasuStatus_(requestedDateKey) {
  var sheet = getKazasuLogSheet_();
  if (!sheet) {
    return {};
  }

  var lastRow = sheet.getLastRow();
  if (lastRow < 2) {
    return {};
  }

  var rows = sheet.getRange(2, 1, lastRow - 1, 9).getValues(); // A:I
  var targetDateKey = requestedDateKey || '';
  var latestByStudent = {};

  rows.forEach(function(row) {
    var studentKey = normalizeStudentKey_(row[0]); // A列: 生徒氏名
    var action = String(row[7] === undefined || row[7] === null ? '' : row[7]).trim(); // H列: アクション
    if (!studentKey || !action) {
      return;
    }

    var dateKey = normalizeDateKey_(row[2]) || normalizeDateKey_(row[1]); // C列優先、なければB列
    if (targetDateKey && dateKey !== targetDateKey) {
      return;
    }

    var eventMillis = resolveKazasuEventMillis_(row[1], row[2], row[3]); // B/C/D列
    if (eventMillis < 0) {
      return;
    }

    var current = latestByStudent[studentKey];
    if (current && current.sortMillis >= eventMillis) {
      return;
    }

    latestByStudent[studentKey] = {
      action: action,
      studentName: String(row[0] === undefined || row[0] === null ? '' : row[0]).trim(),
      time: formatKazasuTimeLabel_(row[1], row[3], eventMillis),
      imageUrl: String(row[8] === undefined || row[8] === null ? '' : row[8]).trim(), // I列: 画像URL
      date: dateKey,
      timestamp: Utilities.formatDate(new Date(eventMillis), KAZASU_TIMEZONE_, 'yyyy-MM-dd HH:mm:ss'),
      sortMillis: eventMillis
    };
  });

  var response = {};
  Object.keys(latestByStudent).forEach(function(studentKey) {
    var item = latestByStudent[studentKey];
    response[studentKey] = {
      action: item.action,
      studentName: item.studentName,
      time: item.time,
      imageUrl: item.imageUrl,
      date: item.date,
      timestamp: item.timestamp
    };
  });

  return response;
}
