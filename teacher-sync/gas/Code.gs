var HEADER = ['id', 'email', 'name', 'status', 'roles', 'scopes', 'updated_at'];
var DELETE_MISSING_ON_FULL_SYNC = false;

// API URL is fixed (hard-coded).
var FIXED_API_URL = 'https://system.kyotoijuku.com/kiweb/teacher-sync/api/teachers.php';
var FIXED_TEACHER_SYNC_SECRET = 'rloCfUjjkDtM9UGBh54f4Dd5MtOoYzFmsK78mX20q9pow56Hnd117ne6X3kV8bXI';
var FIXED_SHEET_NAME = '講師一覧';

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('講師一覧を同期')
    .addItem('今すぐ同期', 'syncTeachers')
    .addSeparator()
    .addItem('15分トリガーを再作成', 'installSyncTrigger')
    .addToUi();
}

function syncTeachers() {
  var config = getConfig_();
  var url = config.apiUrl + (config.apiUrl.indexOf('?') === -1 ? '?' : '&') + 'role=all';
  var responseText = fetchTeachersWithRetry_(url, config.secret);
  var payload = JSON.parse(responseText);
  if (!payload.success || !Array.isArray(payload.data)) {
    throw new Error('Invalid API payload');
  }

  var sheet = ensureSheet_(config.sheetName);
  var upserted = upsertTeachers_(sheet, payload.data, true);

  Logger.log('Synced ' + upserted + ' teachers at ' + new Date().toISOString());
}

function fetchTeachersWithRetry_(url, secret) {
  for (var attempt = 0; attempt < 2; attempt++) {
    try {
      var timestamp = String(Math.floor(Date.now() / 1000));
      var signature = hmacHex_(timestamp, secret);
      var response = UrlFetchApp.fetch(url, {
        method: 'get',
        muteHttpExceptions: true,
        headers: {
          'X-Sync-Timestamp': timestamp,
          'X-Sync-Signature': signature
        }
      });

      var code = response.getResponseCode();
      if (code === 401) {
        Logger.log('Sync failed: Auth failed (401)');
        throw new Error('Auth failed (401)');
      }
      if (code < 200 || code >= 300) {
        throw new Error('HTTP ' + code + ': ' + response.getContentText());
      }

      return response.getContentText();
    } catch (error) {
      if (attempt === 1) {
        Logger.log('Sync failed: ' + error.message);
        throw error;
      }
      Utilities.sleep(1000);
    }
  }

  throw new Error('API call failed');
}

function upsertTeachers_(sheet, teachers, isFullSync) {
  var values = sheet.getDataRange().getValues();
  if (values.length === 0) {
    sheet.getRange(1, 1, 1, HEADER.length).setValues([HEADER]);
    values = [HEADER];
  }

  var rowById = {};
  for (var i = 1; i < values.length; i++) {
    var rowId = String(values[i][0] || '');
    if (rowId) {
      rowById[rowId] = i + 1;
    }
  }

  var appendRows = [];
  var seenIds = {};
  var upserted = 0;

  teachers.forEach(function(teacher) {
    var id = String(teacher.id || '');
    if (!id) {
      return;
    }

    var rowValues = [
      id,
      String(teacher.email || ''),
      String(teacher.name || ''),
      String(teacher.status || ''),
      String(teacher.roles || ''),
      String(teacher.scopes || ''),
      String(teacher.updated_at || '')
    ];
    seenIds[id] = true;
    upserted += 1;

    if (rowById[id]) {
      var rowIndex = rowById[id];
      sheet.getRange(rowIndex, 1, 1, HEADER.length).setValues([rowValues]);
      updateRowVisibility_(sheet, rowIndex, rowValues[3]); // status
      return;
    }

    appendRows.push(rowValues);
  });

  if (appendRows.length > 0) {
    var startRow = sheet.getLastRow() + 1;
    sheet.getRange(startRow, 1, appendRows.length, HEADER.length).setValues(appendRows);
    for (var j = 0; j < appendRows.length; j++) {
      updateRowVisibility_(sheet, startRow + j, appendRows[j][3]); // status
    }
  }

  // 方針: 差分同期では「未返却=削除済み」とは限らないため、基本は物理削除しない。
  // 必要なら full sync 時のみ stale 行を削除できるようにスイッチを残している。
  if (isFullSync && DELETE_MISSING_ON_FULL_SYNC) {
    removeMissingRows_(sheet, seenIds);
  }

  return upserted;
}

function removeMissingRows_(sheet, seenIds) {
  var values = sheet.getDataRange().getValues();
  for (var i = values.length - 1; i >= 1; i--) {
    var id = String(values[i][0] || '');
    if (id && !seenIds[id]) {
      sheet.deleteRow(i + 1);
    }
  }
}

function updateRowVisibility_(sheet, rowIndex, status) {
  if (String(status) === 'deleted') {
    sheet.hideRows(rowIndex);
  } else {
    sheet.showRows(rowIndex);
  }
}

function ensureSheet_(sheetName) {
  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = spreadsheet.getSheetByName(sheetName);
  if (!sheet) {
    sheet = spreadsheet.insertSheet(sheetName);
  }

  var headerRange = sheet.getRange(1, 1, 1, HEADER.length);
  var currentHeader = headerRange.getValues()[0];
  if (currentHeader.join('|') !== HEADER.join('|')) {
    headerRange.setValues([HEADER]);
  }
  return sheet;
}

function installSyncTrigger() {
  var handler = 'syncTeachers';
  var triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(function(trigger) {
    if (trigger.getHandlerFunction() === handler) {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger(handler)
    .timeBased()
    .everyMinutes(15)
    .create();
}

function getConfig_() {
  if (!FIXED_TEACHER_SYNC_SECRET || FIXED_TEACHER_SYNC_SECRET.length < 32) {
    throw new Error('FIXED_TEACHER_SYNC_SECRET must be 32+ chars');
  }

  return {
    apiUrl: FIXED_API_URL,
    secret: FIXED_TEACHER_SYNC_SECRET,
    sheetName: FIXED_SHEET_NAME
  };
}

function hmacHex_(message, secret) {
  var signature = Utilities.computeHmacSha256Signature(message, secret);
  return signature
    .map(function(byte) {
      var value = (byte < 0 ? byte + 256 : byte).toString(16);
      return value.length === 1 ? '0' + value : value;
    })
    .join('');
}
