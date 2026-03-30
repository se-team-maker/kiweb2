/**
 * @fileoverview kiweb 連携：処理未定行 -> PHP キャッシュ更新
 *
 * スプレッドシートの「メイン」シートから「処理未定」となっている
 * データ行を抽出し、指定されたエンドポイント(PHP)へ同期する処理を担当します。
 * 要件定義書: 要件定義書_処理未定_kiweb連携.md
 *
 * 既存の「処理未定.gs」から責務を分離し、本スクリプトでは抽出と同期のみを行います。
 *
 * 同期の起動は時間主導トリガー（既定: 5分間隔）のみ。マルチ編集向けに onEdit トリガーは作成しません。
 */

/**
 * アプリケーションの基本設定
 * @constant {Object}
 */
var KIWEB_PENDING_CONFIG = {
  sheetName: 'メイン',
  statusHeader: '実施',
  pendingStatusValue: '処理未定',
  syncUrlProperty: 'KIWEB_PENDING_SYNC_URL',
  syncSecretProperty: 'KIWEB_PENDING_SYNC_SECRET',
  syncHeaderName: 'X-Kiweb-Pending-Sync-Secret',
  scriptTimeZone: 'Asia/Tokyo',
  dateTimeFormat: 'yyyy-MM-dd HH:mm:ss',
  debounceProperty: 'KIWEB_PENDING_LAST_EDIT_SYNC_MS',
  debounceMs: 30000,
  lockTimeoutMs: 30000,
  clockTriggerMinutes: 5,
  headerScanLimit: 20,
  triggerHandler: 'onPendingSheetEditedForKiwebSync'
};

/**
 * 読み取るべきカラムヘッダーの定義
 * @constant {Object}
 */
var KIWEB_PENDING_COLUMN_HEADERS = {
  status: KIWEB_PENDING_CONFIG.statusHeader,
  teacherName: '講師名',
  className: '授業名',
  startDateTime: '予定開始日時',
  endDateTime: '予定終了日時',
  businessNo: '予定業務No',
  originalStartDateTime: '当初開始日時',
  originalBusinessNo: '当初予定業務No',
  lessonDetail: '授業形態詳細',
  school: '授業実施校舎',
  studentName: '生徒名',
  studentInfo: '生徒情報(カンマ区切り)',
  changeReason: '変更理由',
  attendance: '出欠'
};

/**
 * 同期に必要な必須カラムのキー一覧
 * @constant {string[]}
 */
var KIWEB_PENDING_REQUIRED_COLUMN_KEYS = [
  'status',
  'originalStartDateTime',
  'originalBusinessNo',
  'startDateTime',
  'endDateTime',
  'businessNo',
  'teacherName',
  'lessonDetail',
  'className',
  'changeReason',
  'studentInfo'
];

/**
 * ヘッダ行判定に使用する重要カラムのキー一覧
 * @constant {string[]}
 */
var KIWEB_PENDING_HEADER_SCORE_KEYS = [
  'status',
  'teacherName',
  'className',
  'businessNo',
  'originalBusinessNo',
  'startDateTime',
  'endDateTime',
  'lessonDetail',
  'school'
];

/**
 * 各カラムのヘッダ名揺らぎ吸収用のエイリアス定義
 * @constant {Object<string, string[]>}
 */
var KIWEB_PENDING_HEADER_ALIASES = {
  status: ['実施', '対応状況', 'ステータス', '状況'],
  teacherName: ['予定講師名', '講師名', '担当講師', '担当者名'],
  className: ['授業名', '科目名', '講座名', 'クラス名'],
  startDateTime: ['予定開始日時', '開始日時', '開始', '授業開始日時'],
  endDateTime: ['予定終了日時', '終了日時', '終了', '授業終了日時'],
  businessNo: ['予定業務No', '予定業務No。', '業務No', '予定業務番号'],
  originalStartDateTime: ['当初開始日時', '元開始日時', '開始日時(当初)'],
  originalBusinessNo: ['当初予定業務No', '当初予定業務No。', '元業務No', '当初業務No'],
  lessonDetail: ['授業形態詳細', '授業形態', '詳細'],
  school: ['授業実施校舎', '校舎', '教室', '実施校舎'],
  studentName: ['生徒名', '受講生名', '氏名'],
  studentInfo: ['生徒情報(カンマ区切り)', '生徒情報', '生徒一覧'],
  changeReason: ['変更理由', '理由', '変更理由内容'],
  attendance: ['出欠', '出欠状況']
};

/**
 * 手動実行用エントリーポイント: 処理未定行の同期処理を開始します。
 * @return {Object} 同期結果オブジェクト
 */
function syncPendingRowsToKiwebPhp() {
  return syncPendingRowsToKiwebPhp_();
}

/**
 * onEdit トリガー用エントリーポイント
 * @param {GoogleAppsScript.Events.SheetsOnEdit} e 編集イベントオブジェクト
 */
function onPendingSheetEditedForKiwebSync(e) {
  return handlePendingEdit_(e);
}

/**
 * 旧トリガー名が残っていても動くようにする互換エイリアス。
 * @param {GoogleAppsScript.Events.SheetsOnEdit} e 編集イベントオブジェクト
 */
function onPendingSheetEditedForKiwebSync_(e) {
  return handlePendingEdit_(e);
}

/**
 * 時間主導トリガー（syncPendingRowsToKiwebPhp）のみインストールします。
 * 既に同種トリガーがある場合はスキップします（間隔変更には reinstall を実行）。
 */
function installKiwebPendingSyncTriggers() {
  var created = [];

  if (!hasTrigger_('syncPendingRowsToKiwebPhp', ScriptApp.EventType.CLOCK)) {
    ScriptApp.newTrigger('syncPendingRowsToKiwebPhp')
      .timeBased()
      .everyMinutes(KIWEB_PENDING_CONFIG.clockTriggerMinutes)
      .create();
    created.push('time');
  }

  Logger.log('kiweb-pending-reschedule-sync: created triggers = %s', created.join(', ') || 'none');
}

/**
 * onEdit および既存の時限トリガーを削除し、KIWEB_PENDING_CONFIG.clockTriggerMinutes 間隔の
 * 時限トリガー1本だけを張り直します。10分→5分への移行や onEdit 廃止時に1回実行してください。
 */
function reinstallKiwebPendingSyncTriggers() {
  var triggers = ScriptApp.getProjectTriggers();
  var toRemove = [];
  var i;
  for (i = 0; i < triggers.length; i++) {
    var t = triggers[i];
    var h = t.getHandlerFunction();
    var ev = t.getEventType();
    if (h === KIWEB_PENDING_CONFIG.triggerHandler && ev === ScriptApp.EventType.ON_EDIT) {
      toRemove.push(t);
    } else if (h === 'syncPendingRowsToKiwebPhp' && ev === ScriptApp.EventType.CLOCK) {
      toRemove.push(t);
    }
  }
  for (i = 0; i < toRemove.length; i++) {
    ScriptApp.deleteTrigger(toRemove[i]);
  }

  ScriptApp.newTrigger('syncPendingRowsToKiwebPhp')
    .timeBased()
    .everyMinutes(KIWEB_PENDING_CONFIG.clockTriggerMinutes)
    .create();

  Logger.log(
    'kiweb-pending-reschedule-sync: reinstalled clock every %s min (onEdit removed if present)',
    KIWEB_PENDING_CONFIG.clockTriggerMinutes
  );
}

/**
 * トリガー作成処理のエイリアス関数 (インストールと同様)
 */
function createKiwebPendingSyncTriggers() {
  return installKiwebPendingSyncTriggers();
}

/**
 * 処理未定行の抽出と PHP への同期実行のメインロジック。
 * 複数実行による衝突回避のため LockService で排他制御を行っています。
 *
 * @private
 * @return {Object} 抽出結果とレスポンスを含む同期結果
 */
function syncPendingRowsToKiwebPhp_() {
  // スクリプトの排他制御を行い、同時実行を防ぐ
  var lock = LockService.getScriptLock();
  if (!lock.tryLock(KIWEB_PENDING_CONFIG.lockTimeoutMs)) {
    throw new Error('kiweb-pending-reschedule-sync: 同期処理が実行中です');
  }

  try {
    var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = getPendingSheetOrThrow_(spreadsheet);
    var extraction = extractPendingRowsFromSheet_(sheet);

    // 同期に必要なすべてのカラムが見つかっているか検証
    assertRequiredColumnsPresent_(extraction.missingHeaders);

    var payload = buildSyncPayload_(spreadsheet, sheet, extraction);
    var syncResponse = postPendingRowsToKiwebPhp_(payload);

    // 最後に同期した時刻を記録し、次回以降の連続実行をデバウンスさせる
    markPendingSyncTimestamp_();

    Logger.log('kiweb-pending-reschedule-sync: synced pending rows = %s', extraction.rows.length);
    return {
      success: true,
      pending_count: extraction.rows.length,
      total_rows: extraction.totalRows,
      header_row: extraction.headerRowNumber,
      response: syncResponse
    };
  } finally {
    // 確実にロックを解放する
    lock.releaseLock();
  }
}

/**
 * 編集イベント (onEdit) のハンドリングを行います。
 *
 * @private
 * @param {GoogleAppsScript.Events.SheetsOnEdit} e
 */
function handlePendingEdit_(e) {
  // 対象シートの編集でない場合はスキップ
  if (!isPendingSheetEdit_(e)) {
    return;
  }

  // デバウンスによる短時間での連続実行を回避
  if (shouldDebouncePendingEdit_()) {
    return;
  }

  // タイムスタンプを即座に更新し、他の実行を防ぐ
  markPendingSyncTimestamp_();

  try {
    syncPendingRowsToKiwebPhp_();
  } catch (err) {
    // 失敗した場合は次回リトライできるようタイムスタンプをクリア
    clearPendingSyncTimestamp_();
    Logger.log('kiweb-pending-reschedule-sync: edit sync failed: %s', err && err.stack ? err.stack : err);
    throw err;
  }
}

/**
 * イベントが監視対象となるシートに対する編集かどうかを判定します。
 *
 * @private
 * @param {GoogleAppsScript.Events.SheetsOnEdit} e
 * @return {boolean} 監視対象であれば true
 */
function isPendingSheetEdit_(e) {
  if (!e || !e.range) {
    return false;
  }

  var sheet = e.range.getSheet();
  return !!sheet && sheet.getName() === KIWEB_PENDING_CONFIG.sheetName;
}

/**
 * 連続する編集イベントに対する実行を抑制 (デバウンス) すべきかを判定します。
 *
 * @private
 * @return {boolean} デバウンス対象であれば true
 */
function shouldDebouncePendingEdit_() {
  var lastSyncMs = getLastPendingSyncTimestamp_();
  if (!lastSyncMs) {
    return false;
  }

  return (Date.now() - lastSyncMs) < KIWEB_PENDING_CONFIG.debounceMs;
}

/**
 * 対象シートから「処理未定」行のデータを抽出しオブジェクトの配列に変換します。
 *
 * @private
 * @param {GoogleAppsScript.Spreadsheet.Sheet} sheet
 * @return {Object} 抽出結果(行データ、ヘッダ位置など) を含むオブジェクト
 */
function extractPendingRowsFromSheet_(sheet) {
  var values = sheet.getDataRange().getValues();
  if (!values || values.length === 0) {
    return createEmptyExtractionResult_();
  }

  // ヘッダ行の特定
  var headerInfo = detectHeaderRow_(values);
  var headerRowIndex = headerInfo.headerRowIndex;
  var headers = values[headerRowIndex] || [];
  var columnIndexes = buildColumnIndexes_(headers);

  return {
    headerRowNumber: headerRowIndex + 1,
    totalRows: Math.max(0, values.length - headerRowIndex - 1),
    headers: headers,
    rows: collectPendingRows_(sheet, values, headerRowIndex, columnIndexes),
    missingHeaders: collectMissingHeaders_(columnIndexes)
  };
}

/**
 * 何もデータが抽出できなかった場合に使用する空の抽出結果を生成します。
 *
 * @private
 * @return {Object} 空の抽出結果
 */
function createEmptyExtractionResult_() {
  return {
    headerRowNumber: 1,
    totalRows: 0,
    headers: [],
    rows: [],
    missingHeaders: [KIWEB_PENDING_COLUMN_HEADERS.status]
  };
}

/**
 * 各カラムが見つかったインデックス番号のマッピングを生成します。
 *
 * @private
 * @param {string[]} headers ヘッダ行の配列
 * @return {Object<string, number>} 各定義カラムのインデックス (未発見時は -1)
 */
function buildColumnIndexes_(headers) {
  var headerMap = createHeaderMap_(headers);
  var columnIndexes = {};
  var columnKeys = Object.keys(KIWEB_PENDING_COLUMN_HEADERS);

  for (var i = 0; i < columnKeys.length; i++) {
    var key = columnKeys[i];
    columnIndexes[key] = resolveColumnIndex_(
      headerMap,
      KIWEB_PENDING_COLUMN_HEADERS[key],
      KIWEB_PENDING_HEADER_ALIASES[key]
    );
  }

  // 最低限「ステータス(実施)」列が見つからなければ例外をスロー
  if (columnIndexes.status === -1) {
    throw new Error('kiweb-pending-reschedule-sync: 「' + KIWEB_PENDING_COLUMN_HEADERS.status + '」列が見つかりません');
  }

  return columnIndexes;
}

/**
 * ヘッダ行以降のレコードを走査し、「処理未定」行のみを収集して成形します。
 *
 * @private
 * @param {GoogleAppsScript.Spreadsheet.Sheet} sheet
 * @param {Object[][]} values シートの全データ
 * @param {number} headerRowIndex ヘッダ行のインデックス
 * @param {Object<string, number>} columnIndexes 各カラムのインデックス情報
 * @return {Object[]} 送信用に成形された行データの配列
 */
function collectPendingRows_(sheet, values, headerRowIndex, columnIndexes) {
  var pendingRows = [];

  for (var rowIndex = headerRowIndex + 1; rowIndex < values.length; rowIndex++) {
    var row = values[rowIndex];
    if (!row || row.length === 0) {
      continue;
    }

    // 「処理未定」ステータスでなければスキップ
    if (!isPendingStatus_(getCellText_(row, columnIndexes.status))) {
      continue;
    }

    pendingRows.push(buildPendingRow_(sheet, rowIndex + 1, row, columnIndexes));
  }

  return pendingRows;
}

/**
 * 同期先 API (PHP) に送信する JSON ペイロードを組み立てます。
 *
 * @private
 * @param {GoogleAppsScript.Spreadsheet.Spreadsheet} spreadsheet
 * @param {GoogleAppsScript.Spreadsheet.Sheet} sheet
 * @param {Object} extraction 抽出結果オブジェクト
 * @return {Object} POST ペイロード
 */
function buildSyncPayload_(spreadsheet, sheet, extraction) {
  var timestamp = formatCellValue_(new Date());

  return {
    schema_version: 'kiweb-pending-reschedule-sync-v1',
    sheet_name: sheet.getName(),
    spreadsheet_id: spreadsheet.getId(),
    updated_at: timestamp,
    generated_at: timestamp,
    header_row: extraction.headerRowNumber,
    total_rows: extraction.totalRows,
    pending_count: extraction.rows.length,
    rows: extraction.rows
  };
}

/**
 * UrlFetchApp を用いて構成された PHP サーバーの API へ POST 送信を行います。
 *
 * @private
 * @param {Object} payload 送信するペイロード
 * @return {Object} API レスポンス結果
 */
function postPendingRowsToKiwebPhp_(payload) {
  var syncSettings = getPendingSyncSettings_();
  var response = UrlFetchApp.fetch(syncSettings.url, {
    method: 'post',
    contentType: 'application/json; charset=utf-8',
    headers: {
      Accept: 'application/json',
      [KIWEB_PENDING_CONFIG.syncHeaderName]: syncSettings.secret
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });

  var statusCode = response.getResponseCode();
  var responseText = response.getContentText('UTF-8');

  // エラーハンドリング
  assertSuccessfulPost_(statusCode, responseText);

  return {
    status_code: statusCode,
    body: parseJsonResponse_(responseText)
  };
}

/**
 * プロパティストアーから同期のための URL と Secret 認証キーを取得します。
 *
 * @private
 * @return {Object} 同期設定 { url, secret }
 */
function getPendingSyncSettings_() {
  var properties = PropertiesService.getScriptProperties();
  var url = (properties.getProperty(KIWEB_PENDING_CONFIG.syncUrlProperty) || '').trim();
  var secret = (properties.getProperty(KIWEB_PENDING_CONFIG.syncSecretProperty) || '').trim();

  if (!url) {
    throw new Error('kiweb-pending-reschedule-sync: スクリプトプロパティ ' + KIWEB_PENDING_CONFIG.syncUrlProperty + ' が未設定です');
  }

  if (!secret) {
    throw new Error('kiweb-pending-reschedule-sync: スクリプトプロパティ ' + KIWEB_PENDING_CONFIG.syncSecretProperty + ' が未設定です');
  }

  return {
    url: url,
    secret: secret
  };
}

/**
 * HTTP ステータスコードが 200 番台であるか確認し、失敗時は例外をスローします。
 *
 * @private
 * @param {number} statusCode
 * @param {string} responseText
 */
function assertSuccessfulPost_(statusCode, responseText) {
  if (statusCode >= 200 && statusCode < 300) {
    return;
  }

  throw new Error('kiweb-pending-reschedule-sync: PHP への POST に失敗しました (' + statusCode + '): ' + responseText);
}

/**
 * テキストを安全に JSON パースし、失敗時は生のテキストを格納したオブジェクトを返却します。
 *
 * @private
 * @param {string} text
 * @return {Object} パース結果
 */
function parseJsonResponse_(text) {
  try {
    return text ? JSON.parse(text) : null;
  } catch (parseError) {
    return {
      raw_text: text
    };
  }
}

/**
 * スプレッドシートの 1 行から対応する各カラムの情報を抽出し、送信形式のオブジェクトを構築します。
 *
 * @private
 * @param {GoogleAppsScript.Spreadsheet.Sheet} sheet
 * @param {number} rowNumber セル行番号 (1-based)
 * @param {Object[]} row 行の配列データ
 * @param {Object<string, number>} columnIndexes ヘッダ列のインデックス情報
 * @return {Object} 成形された 1 レコードのオブジェクト
 */
function buildPendingRow_(sheet, rowNumber, row, columnIndexes) {
  var statusText = getCellText_(row, columnIndexes.status);
  var teacherName = getCellText_(row, columnIndexes.teacherName);
  var className = getCellText_(row, columnIndexes.className);
  var startDateTime = getCellText_(row, columnIndexes.startDateTime);
  var endDateTime = getCellText_(row, columnIndexes.endDateTime);
  var businessNo = getCellText_(row, columnIndexes.businessNo);
  var originalStartDateTime = getCellText_(row, columnIndexes.originalStartDateTime);
  var originalBusinessNo = getCellText_(row, columnIndexes.originalBusinessNo);
  var lessonDetail = getCellText_(row, columnIndexes.lessonDetail);
  var school = getCellText_(row, columnIndexes.school);
  var studentInfo = getCellText_(row, columnIndexes.studentInfo);
  // `studentName` が空の場合フォールバックとして `studentInfo` を使用
  var studentName = getCellText_(row, columnIndexes.studentName) || studentInfo;
  var changeReason = getCellText_(row, columnIndexes.changeReason);
  var attendance = getCellText_(row, columnIndexes.attendance);

  return {
    行番号: rowNumber,
    実施: statusText,
    対応状況: statusText,
    講師名: teacherName,
    授業名: className,
    予定開始日時: startDateTime,
    予定終了日時: endDateTime,
    予定業務No: businessNo,
    当初開始日時: originalStartDateTime,
    当初予定業務No: originalBusinessNo,
    授業形態詳細: lessonDetail,
    授業実施校舎: school,
    生徒名: studentName,
    '生徒情報(カンマ区切り)': studentInfo,
    変更理由: changeReason,
    出欠: attendance,
    sheet_name: sheet.getName(),
    record_key: buildRecordKey_(businessNo, originalBusinessNo, startDateTime, originalStartDateTime, teacherName, className)
  };
}

/**
 * レコードを一意に識別するための複合キーを作成します。
 * 同一性を判定する際の比較処理を容易にする目的で使用されます。
 *
 * @private
 * @return {string} 一意なキー文字列
 */
function buildRecordKey_(businessNo, originalBusinessNo, startDateTime, originalStartDateTime, teacherName, className) {
  return [
    businessNo || originalBusinessNo || '',
    startDateTime || originalStartDateTime || '',
    teacherName || '',
    className || ''
  ].join('__');
}

/**
 * 指定のステータス文字列が「処理未定」に合致するか判定します。
 * 前方一致でチェックし、「処理未定 (理由など)」といった表記ゆれも許容します。
 *
 * @private
 * @param {string} status セルの値
 * @return {boolean} 処理未定の場合 true
 */
function isPendingStatus_(status) {
  return String(status || '').indexOf(KIWEB_PENDING_CONFIG.pendingStatusValue) === 0;
}

/**
 * ヘッダ行の存在する行番号をスコアリングを用いて自動で探索します。
 * データ上部に空白やメタデータが含まれる可能性を考慮し、最もヘッダらしい行を抽出します。
 *
 * @private
 * @param {Object[][]} values シート全体のデータ
 * @return {Object} 発見したヘッダ行のインデックスとスコア
 */
function detectHeaderRow_(values) {
  var scanRows = Math.min(values.length, KIWEB_PENDING_CONFIG.headerScanLimit);
  var bestIndex = 0;
  var bestScore = -1;

  // 上部の行からスコア計算を行い、最も高得点な行をヘッダーとする
  for (var rowIndex = 0; rowIndex < scanRows; rowIndex++) {
    var score = scoreHeaderRow_(values[rowIndex] || []);
    if (score > bestScore) {
      bestScore = score;
      bestIndex = rowIndex;
    }
  }

  return {
    headerRowIndex: bestIndex,
    score: bestScore
  };
}

/**
 * 行データがどれだけヘッダ行として妥当かのスコアを計算します。
 *
 * @private
 * @param {Object[]} row 行の配列データ
 * @return {number} 適格性判定スコア
 */
function scoreHeaderRow_(row) {
  var score = 0;

  for (var i = 0; i < KIWEB_PENDING_HEADER_SCORE_KEYS.length; i++) {
    var key = KIWEB_PENDING_HEADER_SCORE_KEYS[i];
    if (findHeaderIndex_(row, KIWEB_PENDING_COLUMN_HEADERS[key]) !== -1) {
      score++;
    }
  }

  return score;
}

/**
 * 高速探索のため、ヘッダ行の各要素を正規化したマップを作成します。
 *
 * @private
 * @param {string[]} headers
 * @return {Object<string, number>} 正規化ヘッダ名 -> インデックス
 */
function createHeaderMap_(headers) {
  var map = {};

  for (var i = 0; i < headers.length; i++) {
    var normalizedHeader = normalizeHeaderText_(headers[i]);
    if (normalizedHeader && map[normalizedHeader] === undefined) {
      map[normalizedHeader] = i;
    }
  }

  return map;
}

/**
 * 定義されたカラム名およびエイリアスに基づき、インデックスをマッピングから探索します。
 *
 * @private
 * @param {Object<string, number>} headerMap 正規化済みヘッダマップ
 * @param {string} primaryName 主たるカラム名
 * @param {string[]} aliases エイリアス一覧
 * @return {number} インデックス (-1: 見つからない)
 */
function resolveColumnIndex_(headerMap, primaryName, aliases) {
  var aliasList = [primaryName].concat(aliases || []);

  for (var i = 0; i < aliasList.length; i++) {
    var normalizedHeader = normalizeHeaderText_(aliasList[i]);
    if (normalizedHeader && Object.prototype.hasOwnProperty.call(headerMap, normalizedHeader)) {
      return headerMap[normalizedHeader];
    }
  }

  return -1;
}

/**
 * 必須カラムが不足していないか確認し、不足分を配列で返します。
 *
 * @private
 * @param {Object<string, number>} columnIndexes
 * @return {string[]} 不足しているカラム名のリスト
 */
function collectMissingHeaders_(columnIndexes) {
  var missingHeaders = [];

  for (var i = 0; i < KIWEB_PENDING_REQUIRED_COLUMN_KEYS.length; i++) {
    var key = KIWEB_PENDING_REQUIRED_COLUMN_KEYS[i];
    // -1 の場合はヘッダーが見つからなかったため警告対象
    if (columnIndexes[key] === -1) {
      missingHeaders.push(KIWEB_PENDING_COLUMN_HEADERS[key]);
    }
  }

  return missingHeaders;
}

/**
 * 欠損しているカラムが存在すれば例外をスローしてプロセスを中断させます。
 * 不正データの送信による後続プロセス被害を避けるフェールセーフ。
 *
 * @private
 * @param {string[]} missingHeaders
 */
function assertRequiredColumnsPresent_(missingHeaders) {
  if (!missingHeaders || missingHeaders.length === 0) {
    return;
  }

  throw new Error(
    'kiweb-pending-reschedule-sync: 必須列が不足しているため同期を中止しました: ' +
    missingHeaders.join(', ')
  );
}

/**
 * ヘッダを含む配列から、指定した名前(エイリアス考慮せず)のインデックスを探します。
 *
 * @private
 * @param {Object[]} row
 * @param {string} headerName
 * @return {number}
 */
function findHeaderIndex_(row, headerName) {
  var normalizedHeader = normalizeHeaderText_(headerName);

  for (var i = 0; i < row.length; i++) {
    if (normalizeHeaderText_(row[i]) === normalizedHeader) {
      return i;
    }
  }

  return -1;
}

/**
 * 検索処理の精度を上げるため、ヘッダ文字列を小文字・全角半角空白除去で正規化します。
 *
 * @private
 * @param {any} value
 * @return {string}
 */
function normalizeHeaderText_(value) {
  return String(value == null ? '' : value)
    .replace(/\u3000/g, ' ')
    .replace(/\s+/g, '')
    .trim();
}

/**
 * 安全にセルの値を取得します。配列の範囲外アクセスを防ぎます。
 *
 * @private
 * @param {Object[]} row
 * @param {number} columnIndex
 * @return {any}
 */
function getCellValue_(row, columnIndex) {
  if (columnIndex < 0 || columnIndex >= row.length) {
    return '';
  }

  return row[columnIndex];
}

/**
 * セル値をフォーマットし、安全に文字列として取得します。
 *
 * @private
 * @param {Object[]} row
 * @param {number} columnIndex
 * @return {string}
 */
function getCellText_(row, columnIndex) {
  return formatCellValue_(getCellValue_(row, columnIndex));
}

/**
 * 様々な型 (特に Date オブジェクト) の値を、同期用の一貫した文字列表現に変換します。
 *
 * @private
 * @param {any} value
 * @return {string}
 */
function formatCellValue_(value) {
  if (value === null || value === undefined) {
    return '';
  }

  // Dateオブジェクトの際は、指定のフォーマットを用いて文字列化する
  if (Object.prototype.toString.call(value) === '[object Date]') {
    if (isNaN(value.getTime())) {
      return '';
    }

    return Utilities.formatDate(
      value,
      KIWEB_PENDING_CONFIG.scriptTimeZone,
      KIWEB_PENDING_CONFIG.dateTimeFormat
    );
  }

  return String(value).trim();
}

/**
 * 監視対象であるシートオブジェクトを取得します。ない場合は例外をスローします。
 *
 * @private
 * @param {GoogleAppsScript.Spreadsheet.Spreadsheet} spreadsheet
 * @return {GoogleAppsScript.Spreadsheet.Sheet}
 */
function getPendingSheetOrThrow_(spreadsheet) {
  var sheet = spreadsheet.getSheetByName(KIWEB_PENDING_CONFIG.sheetName);
  if (sheet) {
    return sheet;
  }

  throw new Error('kiweb-pending-reschedule-sync: 「' + KIWEB_PENDING_CONFIG.sheetName + '」シートが見つかりません');
}

/**
 * デバウンス用の最終同期タイムスタンプを取得します。
 *
 * @private
 * @return {number} 1970年からの経過ミリ秒数
 */
function getLastPendingSyncTimestamp_() {
  var properties = PropertiesService.getScriptProperties();
  return Number(properties.getProperty(KIWEB_PENDING_CONFIG.debounceProperty) || 0);
}

/**
 * スクリプトプロパティに対して、同期が行われた最新のタイムスタンプを保存します。
 *
 * @private
 */
function markPendingSyncTimestamp_() {
  PropertiesService.getScriptProperties().setProperty(
    KIWEB_PENDING_CONFIG.debounceProperty,
    String(Date.now())
  );
}

/**
 * リトライ等のため、直近の同期タイムスタンプをクリアします。
 *
 * @private
 */
function clearPendingSyncTimestamp_() {
  PropertiesService.getScriptProperties().deleteProperty(KIWEB_PENDING_CONFIG.debounceProperty);
}

/**
 * 指定したハンドラー関数・イベントタイプのトリガーが既に存在するかを判定します。
 * これにより重複してトリガーが作成されることを防止します。
 *
 * @private
 * @param {string} handlerFunction 関数名
 * @param {GoogleAppsScript.Script.EventType} eventType イベント種別
 * @return {boolean} 存在すれば true
 */
function hasTrigger_(handlerFunction, eventType) {
  var triggers = ScriptApp.getProjectTriggers();

  for (var i = 0; i < triggers.length; i++) {
    var trigger = triggers[i];
    if (trigger.getHandlerFunction() === handlerFunction && trigger.getEventType() === eventType) {
      return true;
    }
  }

  return false;
}
