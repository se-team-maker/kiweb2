// ============================================================================
// 実施申告 送信済み状態管理 (kiweb2)
// ============================================================================
//
// ■ 概要
//   実施申告画面（class-declaration.html）で「送信済みかどうか」を高速に判定する
//   ための、多段キャッシュ付きの送信済み状態管理モジュール。
//
// ■ キャッシュの階層（速い順）
//
//   [L1] ScriptCache（メモリキャッシュ、TTL 6時間）
//     ↑ ミスしたら
//   [L2] シートセル（I/J列 = キャッシュ）
//     ↑ ミスしたら
//   [再構築] シートの全行をスキャンして taskNo → status のマップを作り直す
//
// ■ キャッシュの中身
//   { "予定業務No": "ステータス", ... } のJSON文字列
//   例: { "12345": "A実施", "12346": "B実施せず" }
//
// ■ シート列の使い方（1行目に格納）
//   I列 (col 9)  : キャッシュ JSON（昨日以降の全送信済み）
//   J列 (col 10) : キャッシュの下限日（yyyy/MM/dd）
//   K列 (col 11) : キャッシュ生成ログ JSON（日時・結果・件数）
//
// ■ 日次バッチ（毎日 AM1時トリガー）
//   昨日以降のデータでキャッシュを再構築する。
//
// ■ POST（実施申告送信時）
//   キャッシュ（I/J列）に直接追記し、L1 も更新する。
//
// ■ データ行の構成（3行目〜）
//   D列 (col 4) : 日時
//   E列 (col 5) : 予定業務No
//   F列 (col 6) : ステータス
//   G列 (col 7) : 日付（yyyy-MM-dd）
//
// ■ 外部から呼ばれる関数
//   kiweb2DoGet_(e)  … Code.gs の doGet から source=kiweb2 のとき呼ばれる
//   kiweb2DoPost_(e) … Code.gs の doPost から source=kiweb2 のとき呼ばれる
//   kiweb2BatchBuildPreviousDayCache_() … 日次バッチトリガー
//   setupKiweb2BatchTrigger()           … トリガー登録（手動1回実行）
//   rebuildSubmittedCache()             … 手動キャッシュ再構築（GASエディタから実行）
//
// ============================================================================


// ============================================================================
// 定数
// ============================================================================

var KIWEB2_DECLARATION_SHEET_NAME_ = '実施申告records';

// シートキャッシュの列番号（1行目）
var KIWEB2_SUBMITTED_CACHE_COL_ = 9;       // I列: キャッシュ JSON
var KIWEB2_SUBMITTED_CACHE_META_COL_ = 10; // J列: キャッシュの下限日
var KIWEB2_CACHE_LOG_COL_ = 11;            // K列: キャッシュ生成ログ

// L1 キャッシュ（ScriptCache）
var KIWEB2_L1_CACHE_PREFIX_ = 'submitted_task_map:merged:';
var KIWEB2_L1_CACHE_TTL_SEC_ = 21600; // 6時間

// キャッシュ JSON が大きくなりすぎたときの警告閾値
var KIWEB2_CACHE_WARN_JSON_LENGTH_ = 45000;

// フロントからのリクエストを識別するソース名
var KIWEB2_SOURCE_ = 'kiweb2';


// ============================================================================
// ユーティリティ: 正規化・変換
// ============================================================================

/** 予定業務No を文字列に正規化する（null/undefined → 空文字、前後空白除去） */
function kiweb2NormalizeTaskNo_(value) {
  return String(value === undefined || value === null ? '' : value).trim();
}

/** source パラメータを小文字に正規化する */
function kiweb2NormalizeSource_(value) {
  return String(value === undefined || value === null ? '' : value).trim().toLowerCase();
}

/** クエリパラメータ → POSTボディの順で source を解決する */
function kiweb2ResolveSource_(e, data) {
  var sourceFromQuery = kiweb2NormalizeSource_(e && e.parameter ? e.parameter.source : '');
  if (sourceFromQuery) {
    return sourceFromQuery;
  }
  return kiweb2NormalizeSource_(data && typeof data === 'object' ? data.source : '');
}

/** JSON レスポンスを返す */
function kiweb2JsonResponse_(payload) {
  return ContentService.createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * 日付を yyyy/MM/dd 形式のキーに変換する。
 * Date オブジェクトでも文字列（"2025-03-06" や "2025/3/6"）でも受け付ける。
 */
function kiweb2NormalizeDateKey_(value) {
  if (value === null || value === undefined || value === '') return '';

  if (Object.prototype.toString.call(value) === '[object Date]') {
    return Utilities.formatDate(value, 'Asia/Tokyo', 'yyyy/MM/dd');
  }

  var matched = String(value).match(/(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/);
  if (!matched) return '';

  var yyyy = matched[1];
  var mm = ('0' + matched[2]).slice(-2);
  var dd = ('0' + matched[3]).slice(-2);
  return yyyy + '/' + mm + '/' + dd;
}

/** 画面表示の下限日を返す（JSTの「昨日」） */
function kiweb2GetSubmittedLowerBoundKey_() {
  var now = new Date();
  var yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);
  return Utilities.formatDate(yesterday, 'Asia/Tokyo', 'yyyy/MM/dd');
}

/**
 * J1 セルの値を yyyy/MM/dd 形式の lowerBoundKey に正規化する。
 * 日次バッチが書く "yyyy/MM/dd" と、simple path（古いフロント）が書く "yyyy-MM-dd HH:mm" の両形式に対応する。
 */
function kiweb2NormalizeLowerBoundFromJ1_(rawValue) {
  if (rawValue === '' || rawValue === null || rawValue === undefined) return '';
  var s = String(rawValue).trim();
  if (!s) return '';
  var m = s.match(/(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/);
  if (!m) return '';
  return m[1] + '/' + ('0' + m[2]).slice(-2) + '/' + ('0' + m[3]).slice(-2);
}


// ============================================================================
// キャッシュ JSON のパース
// ============================================================================

/**
 * JSON 文字列を { taskNo: status } のオブジェクトにパースする。
 * 不正な値・配列・空文字の場合は null を返す。
 */
function kiweb2ParseSubmittedCache_(rawValue) {
  if (rawValue === '' || rawValue === null || rawValue === undefined) {
    return null;
  }

  try {
    var parsed = JSON.parse(String(rawValue));

    // オブジェクトだけを受け付ける（配列や null は不可）
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      return null;
    }

    // 全キーを正規化してコピー
    var normalized = {};
    Object.keys(parsed).forEach(function(key) {
      var taskNo = kiweb2NormalizeTaskNo_(key);
      if (!taskNo) return;
      normalized[taskNo] = String(parsed[key] || '');
    });
    return normalized;
  } catch (error) {
    return null;
  }
}


// ============================================================================
// L2 キャッシュ: シートセルの読み書き
// ============================================================================

/** キャッシュ（I/J列）を読む → { taskMap, lowerBoundKey } */
function kiweb2ReadSubmittedCacheCells_(sheet) {
  var values = sheet.getRange(1, KIWEB2_SUBMITTED_CACHE_COL_, 1, 2).getValues()[0];
  var rawTaskMap = values[0];
  return {
    rawTaskMap: rawTaskMap,
    taskMap: kiweb2ParseSubmittedCache_(rawTaskMap),
    lowerBoundKey: kiweb2NormalizeLowerBoundFromJ1_(values[1])
  };
}

/** キャッシュ（I/J列）を書く */
function kiweb2WriteSubmittedCacheCells_(sheet, taskMap, lowerBoundKey) {
  var cacheJson = JSON.stringify(taskMap || {});
  sheet.getRange(1, KIWEB2_SUBMITTED_CACHE_COL_, 1, 2).setValues([[cacheJson, lowerBoundKey]]);
  return {
    cacheJson: cacheJson,
    cacheJsonLength: cacheJson.length
  };
}

// ============================================================================
// キャッシュ生成ログ: K列
// ============================================================================

/** K列にキャッシュ生成の記録を書く */
function kiweb2WriteCacheLog_(sheet, result) {
  try {
    var log = {
      timestamp: Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy/MM/dd HH:mm:ss'),
      success: result.success === true,
      trigger: result.trigger || 'unknown',
      count: result.count || 0,
      message: result.message || ''
    };
    sheet.getRange(1, KIWEB2_CACHE_LOG_COL_).setValue(JSON.stringify(log));
  } catch (e) {
    Logger.log('kiweb2 writeCacheLog failed: ' + e);
  }
}


// ============================================================================
// L1 キャッシュ: ScriptCache の読み書き
// ============================================================================

/** L1 キャッシュのキーを組み立てる（プレフィックス + 下限日） */
function kiweb2GetL1CacheKey_(lowerBoundKey) {
  return KIWEB2_L1_CACHE_PREFIX_ + lowerBoundKey;
}

/** L1 キャッシュを読む。ヒットすれば { taskMap, rawJson }、なければ null */
function kiweb2ReadL1Cache_(lowerBoundKey) {
  try {
    var cache = CacheService.getScriptCache();
    var key = kiweb2GetL1CacheKey_(lowerBoundKey);
    var raw = cache.get(key);
    if (!raw) {
      return null;
    }
    var taskMap = kiweb2ParseSubmittedCache_(raw);
    if (!taskMap) {
      return null;
    }
    return {
      taskMap: taskMap,
      rawJson: raw
    };
  } catch (error) {
    Logger.log('kiweb2 L1 cache read failed: ' + error);
    return null;
  }
}

/** L1 キャッシュに書く */
function kiweb2WriteL1Cache_(lowerBoundKey, taskMap, rawJson) {
  try {
    var cache = CacheService.getScriptCache();
    var key = kiweb2GetL1CacheKey_(lowerBoundKey);
    var json = rawJson || JSON.stringify(taskMap || {});
    cache.put(key, json, KIWEB2_L1_CACHE_TTL_SEC_);
  } catch (error) {
    Logger.log('kiweb2 L1 cache write skipped: ' + error);
  }
}


// ============================================================================
// シート全行スキャン: キャッシュの再構築
// ============================================================================

/**
 * シートの全行をスキャンし、lowerBoundKey 以降の送信済みタスクマップを作る。
 * キャッシュがすべてミスしたときのフォールバック。
 *
 * 対象列: D列(日時), E列(予定業務No), F列(ステータス), G列(日付)
 * 範囲: getRange(3, 4, 行数, 4) → D〜G列の4列分
 *   row[0] = D列(日時), row[1] = E列(予定業務No),
 *   row[2] = F列(ステータス), row[3] = G列(日付)
 */
function kiweb2BuildSubmittedTaskMapFromSheet_(sheet, lowerBoundKey) {
  var lastRow = sheet.getLastRow();
  if (lastRow < 3) return {};

  var rows = sheet.getRange(3, 4, lastRow - 2, 4).getValues(); // D〜G列
  var taskMap = {};

  rows.forEach(function(row) {
    // G列(日付)を優先、空ならD列(日時)から日付を抽出
    var dateKey = kiweb2NormalizeDateKey_(row[3]) || kiweb2NormalizeDateKey_(row[0]);
    if (!dateKey || dateKey < lowerBoundKey) return;

    var taskNo = kiweb2NormalizeTaskNo_(row[1]); // E列: 予定業務No
    if (!taskNo) return;
    taskMap[taskNo] = String(row[2] || '');       // F列: ステータス
  });

  return taskMap;
}

/**
 * 特定の日付だけを対象にタスクマップを作る（日次バッチ用）。
 * 列の意味は kiweb2BuildSubmittedTaskMapFromSheet_ と同じ。
 */
function kiweb2BuildSubmittedTaskMapForDate_(sheet, targetDateKey) {
  var lastRow = sheet.getLastRow();
  if (lastRow < 3) return {};

  var rows = sheet.getRange(3, 4, lastRow - 2, 4).getValues(); // D〜G列
  var taskMap = {};

  rows.forEach(function(row) {
    var dateKey = kiweb2NormalizeDateKey_(row[3]) || kiweb2NormalizeDateKey_(row[0]);
    if (dateKey !== targetDateKey) return;

    var taskNo = kiweb2NormalizeTaskNo_(row[1]);
    if (!taskNo) return;
    taskMap[taskNo] = String(row[2] || '');
  });

  return taskMap;
}


// ============================================================================
// キャッシュ取得: POST 用（重複チェックに使うため、確実に最新を返す）
// ============================================================================

/**
 * POST（送信）時に使う送信済みタスクマップを取得する。
 * L1 → L2(I/J列) → 再構築 の順で試す。
 * 計測情報付きのオブジェクトを返す（ログ出力用）。
 */
function kiweb2GetSubmittedTaskMapForPost_(sheet, lowerBoundKey) {
  var cacheReadStartedMs = Date.now();

  // --- L1: ScriptCache を試す ---
  var l1Cache = kiweb2ReadL1Cache_(lowerBoundKey);
  if (l1Cache) {
    return {
      taskMap: l1Cache.taskMap,
      rebuilt: false,
      rebuildReason: 'none',
      cacheSource: 'l1',
      cacheReadMs: Date.now() - cacheReadStartedMs,
      cacheRebuildMs: 0,
      cacheWriteMs: 0,
      cacheJsonLength: l1Cache.rawJson.length
    };
  }

  // --- L2: シートの I/J列を読む ---
  var batchCells = kiweb2ReadSubmittedCacheCells_(sheet);
  var cacheReadMs = Date.now() - cacheReadStartedMs;

  if (batchCells.taskMap && batchCells.lowerBoundKey === lowerBoundKey
      && Object.keys(batchCells.taskMap).length > 0) {
    // L2 ヒット（データあり）→ L1 に昇格させる
    var mergedJson = JSON.stringify(batchCells.taskMap);
    kiweb2WriteL1Cache_(lowerBoundKey, batchCells.taskMap, mergedJson);
    return {
      taskMap: batchCells.taskMap,
      rebuilt: false,
      rebuildReason: 'none',
      cacheSource: 'sheet',
      cacheReadMs: cacheReadMs,
      cacheRebuildMs: 0,
      cacheWriteMs: 0,
      cacheJsonLength: mergedJson.length
    };
  }
  // L2 が空 {} の場合は重複チェック精度のため再構築へ進む

  // --- 再構築: シート全行をスキャンして作り直す ---
  var rebuildStartedMs = Date.now();
  var rebuiltMap = kiweb2BuildSubmittedTaskMapFromSheet_(sheet, lowerBoundKey);
  var cacheWriteStartedMs = Date.now();
  kiweb2WriteSubmittedCacheCells_(sheet, rebuiltMap, lowerBoundKey);
  var cacheWriteMs = Date.now() - cacheWriteStartedMs;
  var cacheRebuildMs = Date.now() - rebuildStartedMs;

  // 再構築結果を L1 にも書く
  var rebuiltJson = JSON.stringify(rebuiltMap);
  kiweb2WriteL1Cache_(lowerBoundKey, rebuiltMap, rebuiltJson);

  kiweb2WriteCacheLog_(sheet, {
    success: true,
    trigger: 'post_fallback',
    count: Object.keys(rebuiltMap).length
  });

  Logger.log(
    'kiweb2 cache rebuilt: reason=fallback_no_batch' +
    ', size=' + Object.keys(rebuiltMap).length +
    ', rebuildMs=' + cacheRebuildMs +
    ', writeMs=' + cacheWriteMs
  );

  return {
    taskMap: rebuiltMap,
    rebuilt: true,
    rebuildReason: 'fallback_no_batch',
    cacheSource: 'rebuild',
    cacheReadMs: cacheReadMs,
    cacheRebuildMs: cacheRebuildMs,
    cacheWriteMs: cacheWriteMs,
    cacheJsonLength: rebuiltJson.length
  };
}


// ============================================================================
// キャッシュ取得: GET 用（毎回 I1 を参照）
// ============================================================================

/**
 * GET 用の送信済みタスクマップを取得する。
 * preferCacheOnly=true のとき、キャッシュがなければ空を返す（再構築しない）。
 * 新フロントの送信済み判定は毎回 I1 を基準にしたいので、L1 は参照しない。
 */
function kiweb2GetAllSubmittedTaskNosObject_(options) {
  options = options || {};
  var preferCacheOnly = options.preferCacheOnly === true;

  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(KIWEB2_DECLARATION_SHEET_NAME_);
  if (!sheet) {
    return {};
  }

  var lowerBoundKey = kiweb2GetSubmittedLowerBoundKey_();

  // GET は毎回 I1 を見る
  var batchCells = kiweb2ReadSubmittedCacheCells_(sheet);
  if (batchCells.taskMap && Object.keys(batchCells.taskMap).length > 0) {
    return batchCells.taskMap;
  }

  // キャッシュ優先モードなら、再構築せずに空を返す
  if (preferCacheOnly) {
    return {};
  }

  // フォールバック: 再構築
  var rebuiltMap = kiweb2BuildSubmittedTaskMapFromSheet_(sheet, lowerBoundKey);
  kiweb2WriteSubmittedCacheCells_(sheet, rebuiltMap, lowerBoundKey);
  kiweb2WriteCacheLog_(sheet, {
    success: true,
    trigger: 'get_fallback',
    count: Object.keys(rebuiltMap).length
  });
  return rebuiltMap;
}


// ============================================================================
// GET レスポンス生成
// ============================================================================

/** action=getAllSubmittedTaskNos のレスポンス（{ taskNo: status } 形式） */
function kiweb2GetAllSubmittedTaskNosResponse_() {
  try {
    return kiweb2JsonResponse_(kiweb2GetAllSubmittedTaskNosObject_({
      preferCacheOnly: false
    }));
  } catch (error) {
    Logger.log('kiweb2 getAllSubmittedTaskNos error: ' + error);
    return kiweb2JsonResponse_({});
  }
}

/** action=getSubmittedList のレスポンス（旧フロント互換の配列形式） */
function kiweb2GetSubmittedListResponse_() {
  var taskMap = {};
  try {
    taskMap = kiweb2GetAllSubmittedTaskNosObject_({
      preferCacheOnly: false
    });
  } catch (error) {
    Logger.log('kiweb2 getSubmittedList error: ' + error);
  }

  var list = Object.keys(taskMap).map(function(taskNo) {
    return {
      taskNo: taskNo,
      status: String(taskMap[taskNo] || ''),
      className: '',
      dateTime: '',
      reportFormUrl: '',
      submittedAt: ''
    };
  });
  return kiweb2JsonResponse_(list);
}


// ============================================================================
// POST: 実施申告の送信処理
// ============================================================================

/**
 * 実施申告の送信を処理する。
 *
 * 1. ロックを取得（同時送信の排他制御）
 * 2. キャッシュから送信済みマップを取得
 * 3. 重複チェック（すでに送信済みなら duplicate を返す）
 * 4. シートに新しい行を追記
 * 5. キャッシュ（I/J列）と L1 に直接追記
 */
function kiweb2HandleUpdateStatusCommon_(sheet, data, startedMs, source) {
  // --- リクエストデータの取り出し ---
  var teacherName = String(data.teacherName || '').trim();
  var className = String(data.className || '');
  var dateTime = String(data.dateTime || '');
  var taskNo = kiweb2NormalizeTaskNo_(data.taskNo);
  var status = String(data.status || '').trim();
  var reportFormUrl = String(data.reportFormUrl || '');

  if (!teacherName || !taskNo || !status) {
    return kiweb2JsonResponse_({ result: 'error', message: 'Missing required fields' });
  }

  var lowerBoundKey = kiweb2GetSubmittedLowerBoundKey_();
  var lock = LockService.getScriptLock();
  var hasLock = false;

  try {
    // --- ロック取得（最大30秒待ち） ---
    var lockWaitStartedMs = Date.now();
    lock.waitLock(30000);
    hasLock = true;
    var lockWaitMs = Date.now() - lockWaitStartedMs;

    // --- 重複チェック ---
    var cacheState = kiweb2GetSubmittedTaskMapForPost_(sheet, lowerBoundKey);
    var submittedTaskMap = cacheState.taskMap;

    if (Object.prototype.hasOwnProperty.call(submittedTaskMap, taskNo)) {
      Logger.log(
        'kiweb2 doPost duplicate: source=' + source +
        ', taskNo=' + taskNo +
        ', cacheSource=' + cacheState.cacheSource +
        ', cacheJsonLength=' + cacheState.cacheJsonLength +
        ', rebuildReason=' + cacheState.rebuildReason +
        ', lockWaitMs=' + lockWaitMs +
        ', cacheReadMs=' + cacheState.cacheReadMs +
        ', cacheRebuildMs=' + cacheState.cacheRebuildMs +
        ', cacheWriteMs=' + cacheState.cacheWriteMs +
        ', totalMs=' + (Date.now() - startedMs)
      );
      return kiweb2JsonResponse_({ result: 'duplicate', taskNo: taskNo, reportFormUrl: '' });
    }

    // --- シートに行を追記 ---
    //   A列: タイムスタンプ, B列: 講師名, C列: 授業名,
    //   D列: 日時, E列: 予定業務No, F列: ステータス,
    //   G列: 日付部分, H列: 個別/集団, I列: 報告書URL
    var timestamp = new Date();
    var rowData = [
      Utilities.formatDate(timestamp, 'JST', 'yyyy-MM-dd HH:mm:ss'),
      teacherName,
      className,
      dateTime,
      taskNo,
      status,
      dateTime.substring(0, 10),
      className.includes('_') ? '個別' : '集団',
      reportFormUrl
    ];

    var appendStartedMs = Date.now();
    var nextRow = sheet.getLastRow() + 1;
    sheet.getRange(nextRow, 1, 1, rowData.length).setValues([rowData]);
    var appendMs = Date.now() - appendStartedMs;

    // --- キャッシュを更新（I/J列に直接追記） ---
    var cacheUpdateMs = 0;
    var cacheUpdateSkipped = false;
    var cacheJsonLength = cacheState.cacheJsonLength || 0;
    var taskDateKey = kiweb2NormalizeDateKey_(dateTime);

    if (taskDateKey && taskDateKey >= lowerBoundKey) {
      submittedTaskMap[taskNo] = status;
      var cacheUpdateStartedMs = Date.now();
      var writeResult = kiweb2WriteSubmittedCacheCells_(sheet, submittedTaskMap, lowerBoundKey);
      cacheUpdateMs = Date.now() - cacheUpdateStartedMs;
      cacheJsonLength = writeResult.cacheJsonLength;

      // L1 も更新
      kiweb2WriteL1Cache_(lowerBoundKey, submittedTaskMap, writeResult.cacheJson);
    } else {
      cacheUpdateSkipped = true;
    }

    // キャッシュ JSON が大きすぎたら警告ログ
    if (cacheJsonLength >= KIWEB2_CACHE_WARN_JSON_LENGTH_) {
      Logger.log('kiweb2 cacheJsonLength warning: length=' + cacheJsonLength + ', threshold=' + KIWEB2_CACHE_WARN_JSON_LENGTH_);
    }

    Logger.log(
      'kiweb2 doPost success: source=' + source +
      ', taskNo=' + taskNo +
      ', cacheSource=' + cacheState.cacheSource +
      ', cacheJsonLength=' + cacheJsonLength +
      ', rebuilt=' + String(cacheState.rebuilt) +
      ', rebuildReason=' + cacheState.rebuildReason +
      ', cacheUpdateSkipped=' + String(cacheUpdateSkipped) +
      ', lockWaitMs=' + lockWaitMs +
      ', cacheReadMs=' + cacheState.cacheReadMs +
      ', cacheRebuildMs=' + cacheState.cacheRebuildMs +
      ', cacheRebuildWriteMs=' + cacheState.cacheWriteMs +
      ', appendMs=' + appendMs +
      ', cacheUpdateMs=' + cacheUpdateMs +
      ', totalMs=' + (Date.now() - startedMs)
    );

    return kiweb2JsonResponse_({ result: 'success' });
  } catch (error) {
    Logger.log(
      'kiweb2 doPost updateStatus error: source=' + source +
      ', error=' + error +
      ', totalMs=' + (Date.now() - startedMs)
    );
    return kiweb2JsonResponse_({ result: 'error', message: 'Internal error' });
  } finally {
    if (hasLock) {
      try {
        SpreadsheetApp.flush();
      } catch (flushError) {
        Logger.log('kiweb2 flush error: ' + flushError);
      }
      lock.releaseLock();
    }
  }
}


// ============================================================================
// エントリーポイント: doPost / doGet（Code.gs から呼ばれる）
// ============================================================================

/** POST リクエストの入口。Code.gs から source=kiweb2 のとき呼ばれる。 */
function kiweb2DoPost_(e) {
  var startedMs = Date.now();
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(KIWEB2_DECLARATION_SHEET_NAME_);
  if (!sheet) {
    return kiweb2JsonResponse_({ result: 'error', message: 'Sheet not found' });
  }

  var data = {};
  try {
    data = JSON.parse(e.postData.contents);
  } catch (error) {
    return kiweb2JsonResponse_({ result: 'error', message: 'Invalid JSON payload' });
  }

  var source = kiweb2ResolveSource_(e, data) || KIWEB2_SOURCE_;
  if (data.action === 'updateStatus') {
    return kiweb2HandleUpdateStatusCommon_(sheet, data, startedMs, source);
  }

  return kiweb2JsonResponse_({ result: 'error', message: 'Invalid action' });
}

/**
 * GET リクエストの入口。Code.gs から source=kiweb2 のとき呼ばれる。
 *
 * 対応する action:
 *   - getAllSubmittedTaskNos : { taskNo: status } のオブジェクトを返す
 *   - getSubmittedList      : [{ taskNo, status, ... }] の配列を返す（旧互換）
 *   - それ以外              : Code.gs の doGet にフォールバック
 */
function kiweb2DoGet_(e) {
  var action = e && e.parameter ? e.parameter.action : '';

  if (action === 'getAllSubmittedTaskNos') {
    return kiweb2GetAllSubmittedTaskNosResponse_();
  }
  if (action === 'getSubmittedList') {
    return kiweb2GetSubmittedListResponse_();
  }

  // kiweb2 が処理しない action は Code.gs の doGet に委譲
  if (typeof doGet === 'function') {
    var fallbackParams = {};
    if (e && e.parameter) {
      Object.keys(e.parameter).forEach(function(key) {
        if (key !== 'source') {
          fallbackParams[key] = e.parameter[key];
        }
      });
    }
    return doGet({ parameter: fallbackParams });
  }

  return kiweb2JsonResponse_({ result: 'error', message: 'Invalid action' });
}


// ============================================================================
// 日次バッチ: キャッシュの再構築（毎日 AM1:00-2:00 トリガー実行）
// ============================================================================

/**
 * 前日分のデータでキャッシュを再構築する。
 * トリガーで毎日1回実行される。
 */
function kiweb2BatchBuildPreviousDayCache_() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(KIWEB2_DECLARATION_SHEET_NAME_);
  if (!sheet) {
    Logger.log('kiweb2 batch: sheet not found');
    return;
  }

  var lock = LockService.getScriptLock();
  try {
    lock.waitLock(60000);
  } catch (e) {
    Logger.log('kiweb2 batch: could not acquire lock');
    return;
  }

  try {
    var yesterday = kiweb2GetSubmittedLowerBoundKey_();
    var startMs = Date.now();

    // 下限日（昨日）以降の全データでマップを構築
    // ※ ForDate_（完全一致）ではなく FromSheet_（>= 下限日）を使う。
    //   完全一致だと当日分やバッチ実行後に追加されたデータがキャッシュから漏れる。
    var batchMap = kiweb2BuildSubmittedTaskMapFromSheet_(sheet, yesterday);
    var buildMs = Date.now() - startMs;

    // キャッシュ（I/J列）を上書き
    kiweb2WriteSubmittedCacheCells_(sheet, batchMap, yesterday);

    // L1 にも書く
    kiweb2WriteL1Cache_(yesterday, batchMap);

    // K列にログを記録
    kiweb2WriteCacheLog_(sheet, {
      success: true,
      trigger: 'batch',
      count: Object.keys(batchMap).length
    });

    SpreadsheetApp.flush();

    Logger.log(
      'kiweb2 batch complete: date=' + yesterday +
      ', size=' + Object.keys(batchMap).length +
      ', buildMs=' + buildMs +
      ', totalMs=' + (Date.now() - startMs)
    );
  } catch (error) {
    kiweb2WriteCacheLog_(sheet, {
      success: false,
      trigger: 'batch',
      message: String(error)
    });
    throw error;
  } finally {
    lock.releaseLock();
  }
}

/** トリガー登録（手動で1回実行すれば、毎日AM1時にバッチが走るようになる） */
function setupKiweb2BatchTrigger() {
  // 既存のトリガーがあれば削除
  var triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(function(t) {
    if (t.getHandlerFunction() === 'kiweb2BatchBuildPreviousDayCache_') {
      ScriptApp.deleteTrigger(t);
    }
  });

  // 新しいトリガーを登録
  ScriptApp.newTrigger('kiweb2BatchBuildPreviousDayCache_')
    .timeBased()
    .everyDays(1)
    .atHour(1)
    .create();
  Logger.log('kiweb2 batch trigger registered: daily at 1:00-2:00');
}


// ============================================================================
// 手動キャッシュ再構築（GAS エディタのドロップダウンから実行可能）
// ============================================================================

/** 昨日以降の全データでキャッシュを再構築する。GAS エディタから手動実行用。 */
function rebuildSubmittedCache() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(KIWEB2_DECLARATION_SHEET_NAME_);
  if (!sheet) {
    Logger.log('rebuildSubmittedCache: シートが見つかりません');
    return;
  }

  var startMs = Date.now();
  var lowerBoundKey = kiweb2GetSubmittedLowerBoundKey_();

  try {
    var rebuiltMap = kiweb2BuildSubmittedTaskMapFromSheet_(sheet, lowerBoundKey);
    var count = Object.keys(rebuiltMap).length;

    kiweb2WriteSubmittedCacheCells_(sheet, rebuiltMap, lowerBoundKey);
    kiweb2WriteL1Cache_(lowerBoundKey, rebuiltMap);
    kiweb2WriteCacheLog_(sheet, {
      success: true,
      trigger: 'manual',
      count: count
    });

    SpreadsheetApp.flush();
    Logger.log('rebuildSubmittedCache 完了: ' + count + '件, ' + (Date.now() - startMs) + 'ms');
  } catch (error) {
    kiweb2WriteCacheLog_(sheet, {
      success: false,
      trigger: 'manual',
      message: String(error)
    });
    Logger.log('rebuildSubmittedCache 失敗: ' + error);
  }
}
