<?php
require_once __DIR__ . '/../login/public/bootstrap.php';
requireAuth();
?><!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
  <title>会議室予約</title>
  <meta content="Meeting Room Reservation System" name="description" />
  <!-- preconnect for faster font loading -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
  <link rel="preconnect" href="https://cdn.tailwindcss.com" />
  <!-- async font loading for non-blocking render -->
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet"
    media="print" onload="this.media='all'" />
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round&display=swap" rel="stylesheet" media="print"
    onload="this.media='all'" />
  <link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL@20..48,100..700,0..1&display=swap"
    rel="stylesheet" media="print" onload="this.media='all'" />
  <noscript>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round&display=swap" rel="stylesheet" />
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL@20..48,100..700,0..1&display=swap"
      rel="stylesheet" />
  </noscript>
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            primary: "#3949ab",
            "primary-light": "#137fec",
            "primary-container": "#e8eaf6",
            "on-primary-container": "#1a237e",
            "surface": "#fdfbff",
            "surface-container": "#f2f5f8",
            "surface-light": "#ffffff",
            "surface-variant": "#e1e4eb",
            "on-surface": "#1b1b1f",
            "on-surface-variant": "#44474f",
            "outline": "#757780",
            "outline-variant": "#c4c7c5",
            "border-light": "#e5e7eb",
          },
          fontFamily: {
            sans: ["'Google Sans'", "'Noto Sans JP'", "system-ui", "sans-serif"],
          },
          boxShadow: {
            'elevation-1': '0px 1px 3px 1px rgba(0, 0, 0, 0.15), 0px 1px 2px 0px rgba(0, 0, 0, 0.30)',
            'elevation-2': '0px 2px 6px 2px rgba(0, 0, 0, 0.15), 0px 1px 2px 0px rgba(0, 0, 0, 0.30)',
            'elevation-3': '0px 4px 8px 3px rgba(0, 0, 0, 0.15), 0px 1px 3px 0px rgba(0, 0, 0, 0.30)',
          }
        },
      },
    };
  </script>
  <style>
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }

    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    html {
      scroll-behavior: smooth;
    }

    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }

    .view {
      display: none;
    }

    .view.active {
      display: flex;
      flex-direction: column;
    }

    /* 予約ブロック - 来客あり（Pink） */
    .res-guest {
      background: #ffcccc;
      color: #8b0000;
    }

    /* 予約ブロック - 来客なし（Google Blue） */
    .res-no-guest {
      background: #d2e3fc;
      color: #174ea6;
    }

    .res-displaced {
      background: #e5e7eb;
      color: #4b5563;
    }

    .event-block {
      padding: 1px 0;
      box-sizing: border-box;
      pointer-events: none;
    }

    .event-card {
      border-radius: 8px;
      padding: 2px 3px;
      font-family: 'Google Sans', 'Roboto', 'Noto Sans JP', sans-serif;
      display: flex;
      flex-direction: column;
      gap: 0px;
      overflow: hidden;
      box-sizing: border-box;
      border: 1px solid rgba(0, 0, 0, 0.10);
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
      transition: all 0.15s ease-out;
      pointer-events: auto;
    }

    .event-card:hover {
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
      z-index: 50 !important;
    }

    .event-card-title {
      font-size: 11px;
      font-weight: 700;
      line-height: 1.2;
      word-break: break-all;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      line-clamp: 3;
      -webkit-box-orient: vertical;
    }

    .event-card-title-guest {
      word-break: break-all;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .event-card-time {
      font-size: 10px;
      font-weight: 500;
      line-height: 1.1;
      opacity: 0.85;
      font-variant-numeric: tabular-nums;
    }

    .event-card-meta {
      font-size: 10px;
      font-weight: 500;
      line-height: 1.1;
      opacity: 0.9;
      word-break: break-all;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 1;
      line-clamp: 1;
      -webkit-box-orient: vertical;
    }

    .event-card-guest {
      font-size: 10px;
      font-weight: 500;
      line-height: 1.1;
      opacity: 0.7;
      word-break: break-all;
      overflow-wrap: anywhere;
      white-space: normal;
      overflow: visible;
      display: block;
    }

    .timeline-cell {
      position: absolute;
      left: 0;
      right: 0;
      z-index: 5;
      transition: background-color 0.15s ease, box-shadow 0.15s ease;
      cursor: pointer;
      border-radius: 4px;
    }

    .timeline-cell:hover {
      background-color: rgba(57, 73, 171, 0.12);
      box-shadow: inset 0 0 0 1px rgba(57, 73, 171, 0.45),
        0 0 14px rgba(57, 73, 171, 0.35);
    }

    .timeline-cell:active {
      background-color: rgba(26, 115, 232, 0.16);
      box-shadow: inset 0 0 0 1px rgba(26, 115, 232, 0.35);
    }

    .timeline-cell-drop-target {
      background-color: rgba(57, 73, 171, 0.18) !important;
      box-shadow: inset 0 0 0 2px rgba(57, 73, 171, 0.65),
        0 0 16px rgba(57, 73, 171, 0.32) !important;
    }

    .drag-drop-preview {
      position: absolute;
      left: 1px;
      right: 1px;
      z-index: 16;
      pointer-events: none;
      border-radius: 8px;
      background: rgba(57, 73, 171, 0.22);
      box-shadow: inset 0 0 0 2px rgba(57, 73, 171, 0.58),
        0 0 12px rgba(57, 73, 171, 0.22);
    }

    .dragging-reservation {
      user-select: none;
      cursor: grabbing;
    }

    .dragging-reservation .event-card {
      pointer-events: none !important;
    }

    .event-card-drag-source {
      opacity: 0.45;
    }

    .event-card-ghost {
      position: fixed;
      top: 0;
      left: 0;
      z-index: 310;
      pointer-events: none;
      opacity: 0.96;
      transform: translate(-9999px, -9999px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
    }

    @media (max-width: 1023px) {

      #day-headers {
        display: none;
      }

      .day-divider-header-line {
        display: none;
      }

      #scroll-container .sticky,
      #day-headers,
      #room-headers,
      #header-time-spacer {
        background-color: #f2f5f8;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .timeline-cell {
        transition: none;
      }
    }

    @supports (padding-bottom: env(safe-area-inset-bottom)) {
      .safe-area-bottom {
        padding-bottom: calc(env(safe-area-inset-bottom) + 16px);
      }
    }

    /* FABボタン用のSafe Area対応 */
    .safe-area-fab {
      bottom: calc(24px + env(safe-area-inset-bottom, 0px));
    }

    :root {
      --md-primary: #1a73e8;
      --md-on-primary: #ffffff;
      --md-surface: #ffffff;
      --md-surface-variant: #f0f4f8;
      --md-outline: #c4c7c5;
      --md-outline-variant: #d7dee7;
      --md-shadow-1: 0 1px 2px rgba(0, 0, 0, 0.18);
      --md-shadow-2: 0 2px 8px rgba(0, 0, 0, 0.2);
      --form-action-bar-height: 0px;
      --keyboard-offset: 0px;
    }

    #form-view .flex-1.flex.flex-col.gap-6.p-4 {
      padding: 20px 18px calc(160px + var(--form-action-bar-height) + var(--keyboard-offset) + env(safe-area-inset-bottom, 0px));
      gap: 28px;
    }

    #form-view section.space-y-4>div.flex.flex-wrap.items-center.gap-4 {
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
      gap: 16px;
      align-items: flex-start;
    }

    #form-view section.space-y-4>div.flex.flex-wrap.items-center.gap-4>div {
      flex: 1 1 0%;
      min-width: 160px;
    }

    #form-view label {
      font-size: 12px;
      font-weight: 600;
      color: #5f6368;
      letter-spacing: 0.02em;
    }

    #form-view .md-field {
      position: relative;
      display: flex;
      align-items: center;
      width: 100%;
      border: 1px solid var(--md-outline);
      border-radius: 10px;
      background: var(--md-surface);
      box-sizing: border-box;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
      overflow: hidden;
      --ripple-color: rgba(26, 115, 232, 0.2);
    }

    #form-view .md-field:focus-within {
      border-color: var(--md-primary);
      border-width: 2px;
      box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.15);
    }

    #form-view .md-field:hover {
      border-color: #9aa0a6;
    }

    #form-view .md-field>* {
      width: 100%;
    }

    #form-view .md-readonly {
      background: var(--md-surface-variant);
      border-color: var(--md-outline-variant);
    }

    #form-view .md-readonly:focus-within {
      border-width: 1px;
      box-shadow: none;
    }

    #form-view .md-readonly>div {
      background: transparent;
      box-shadow: none;
      border: 0;
    }

    #form-view .md-field .relative {
      width: 100%;
    }

    #form-view .md-field input,
    #form-view .md-field select {
      border: 0;
      border-radius: 0;
      background: transparent;
      outline: none;
      box-shadow: none;
      font-size: 0.95rem;
      color: #1b1b1f;
    }

    #form-room-id,
    #form-date {
      font-weight: 600;
      color: #3c4043;
    }

    #form-room-id {
      padding: 10px 12px 10px 40px;
    }

    #form-date {
      padding: 10px 36px 10px 12px;
    }

    #form-meeting-name {
      padding: 10px 12px;
    }

    #form-reserver-name,
    #form-guest-name {
      padding: 10px 12px 10px 40px;
    }

    #form-start-time,
    #form-duration {
      padding: 10px 12px;
      text-align: center;
    }

    #form-view button {
      position: relative;
      overflow: hidden;
      --ripple-color: rgba(26, 115, 232, 0.22);
    }

    #form-view button[onclick="saveReservation()"] {
      border-radius: 9999px;
      background: var(--md-primary);
      box-shadow: var(--md-shadow-1);
      transition: box-shadow 0.2s ease, transform 0.2s ease;
      --ripple-color: rgba(255, 255, 255, 0.45);
    }

    #form-view button[onclick="saveReservation()"]:hover {
      box-shadow: var(--md-shadow-2);
    }

    #form-view button[onclick="saveReservation()"]:active {
      transform: translateY(1px);
    }

    #form-view button[onclick="saveReservation()"] span[aria-hidden="true"] {
      background-color: transparent;
    }

    #form-view button[onclick="saveReservation()"]:hover span[aria-hidden="true"] {
      background-color: rgba(255, 255, 255, 0.08);
    }

    #form-view button[onclick="saveReservation()"]:active span[aria-hidden="true"] {
      background-color: rgba(255, 255, 255, 0.12);
    }

    #form-view button[onclick="closeFormView()"] {
      width: 40px;
      height: 40px;
      padding: 0;
      border-radius: 9999px;
      background: #eef1f5;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    #form-view button[onclick="closeFormView()"]:hover {
      background: #e4e7eb;
    }

    #form-view .form-scroll {
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior: contain;
      scroll-padding-bottom: calc(var(--form-action-bar-height) + var(--keyboard-offset) + env(safe-area-inset-bottom, 0px) + 12px);
    }

    #form-action-bar {
      bottom: calc(env(safe-area-inset-bottom, 0px) + var(--keyboard-offset));
    }

    .recurrence-card {
      border: 1px solid #e0e3e7;
      border-radius: 12px;
      padding: 16px;
      background: #f8f9fa;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .recurrence-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 0;
      border-radius: 0;
      border: 0;
      background: transparent;
    }

    .recurrence-options {
      margin-top: 0;
      padding: 0;
      border-radius: 0;
      border: 0;
      background: transparent;
    }

    #recurrence-options>div.flex.flex-wrap.gap-4 {
      display: flex;
      flex-direction: row;
      flex-wrap: nowrap;
      align-items: flex-start;
      gap: 12px;
    }

    #recurrence-options>div.flex.flex-wrap.gap-4>div {
      flex: 1 1 0%;
      min-width: 0;
    }

    .dialog-scrim {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(2px);
    }

    .md-dialog {
      width: 100%;
      max-width: 360px;
      background: #eef1f5;
      border-radius: 28px;
      padding: 20px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .md-dialog-title {
      font-size: 1rem;
      font-weight: 700;
      color: #1f1f1f;
    }

    .md-dialog-body {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .md-dialog-option {
      display: flex;
      align-items: center;
      gap: 12px;
      min-height: 48px;
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid #e0e3e7;
      background: #ffffff;
      cursor: pointer;
      transition: background-color 0.2s ease, border-color 0.2s ease;
    }

    .md-dialog-option:hover {
      background: #f7f9fc;
      border-color: #d0d7e2;
    }

    .md-dialog-option input {
      width: 18px;
      height: 18px;
      accent-color: var(--md-primary);
      margin: 0;
    }

    .md-dialog-actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }

    .md-dialog-button {
      padding: 10px 16px;
      border-radius: 9999px;
      font-weight: 600;
      font-size: 0.9rem;
      color: #1a73e8;
      background: transparent;
    }

    .md-dialog-button-danger {
      color: #b3261e;
    }

    #recurrence-frequency,
    #recurrence-until {
      padding: 10px 12px;
    }

    .md-switch {
      position: relative;
      display: inline-flex;
      width: 52px;
      height: 32px;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border-radius: 9999px;
      --ripple-color: rgba(26, 115, 232, 0.18);
    }

    .md-switch input {
      position: absolute;
      inset: 0;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
      margin: 0;
    }

    .md-switch-track {
      position: absolute;
      inset: 0;
      border-radius: 9999px;
      background: #c4c7c5;
      transition: background-color 0.2s ease;
    }

    .md-switch-thumb {
      position: absolute;
      top: 4px;
      left: 4px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #ffffff;
      box-shadow: var(--md-shadow-1);
      transition: transform 0.2s ease;
    }

    .md-switch input:checked~.md-switch-track {
      background: #34c759;
    }

    .md-switch input:checked~.md-switch-thumb {
      transform: translateX(20px);
    }

    .md-switch input:focus-visible~.md-switch-track {
      box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.3);
    }

    .timeline-cell {
      overflow: hidden;
      --ripple-color: rgba(26, 115, 232, 0.2);
    }

    .ripple {
      position: absolute;
      border-radius: 50%;
      transform: scale(0);
      background: var(--ripple-color, rgba(26, 115, 232, 0.2));
      opacity: 0.9;
      pointer-events: none;
      animation: ripple 600ms ease-out;
    }

    @keyframes ripple {
      to {
        transform: scale(1);
        opacity: 0;
      }
    }

    @media (min-width: 1024px) {

      .room-header-item,
      .room-column {
        box-sizing: border-box;
      }

      .room-header-item {
        padding: 6px;
      }

      .room-header-label {
        font-size: 24px;
        line-height: 1.05;
      }

      #day-headers {
        font-size: 12px;
      }

      #day-headers .day-header-label {
        font-size: 20px;
        font-weight: 700;
      }

      #room-headers,
      #room-columns {
        gap: 0;
      }

      .day-divider {
        position: relative;
      }

      .day-divider-line {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #94a3b8;
        pointer-events: none;
      }

      .day-divider-header-line {
        background: #a7b2bf;
      }
        .pin-top-left{
          position: fixed;
          top: 0;
          left: 0;
          z-index: 2147483647; /* とにかく最前面にしたい場合 */
          width: 250px;        /* 任意 */
          height: auto;        /* 任意 */
          display: block;
          .pin-top-left{ pointer-events: none; }
        }

    }
  </style>
</head>

<body class="bg-surface text-on-surface font-sans h-[100dvh] flex flex-col overflow-hidden">

  <!-- ========================================
       タイムライン画面
       ======================================== -->
  <div id="timeline-view" class="view active h-full">
    <main class="flex-1 flex flex-col relative overflow-clip bg-surface-container h-full">
      <!-- モバイル上部の背景マスク（横スクロール時のはみ出し防止） -->
      <div class="absolute top-0 left-0 right-0 h-[72px] bg-surface-container z-[58] lg:hidden pointer-events-none">
      </div>
      <!-- 日付セレクター（モバイルのみ表示） -->
      <div class="absolute top-4 left-0 right-0 z-[60] flex justify-center pointer-events-none lg:hidden">
        <div
          class="flex items-center gap-1 bg-surface-container rounded-full shadow-elevation-3 p-1 pointer-events-auto">
          <button onclick="changeDate(-1)"
            class="px-2.5 py-1.5 rounded-full hover:bg-surface-variant text-on-surface-variant active:bg-surface-variant/80 transition-colors text-xs font-semibold lg:text-sm lg:px-4 lg:py-2">
            <span class="lg:hidden">前へ</span><span class="hidden lg:inline">前の日</span>
          </button>
          <button onclick="openDatePicker()"
            class="bg-primary text-white px-4 py-2 rounded-full flex items-center gap-1 active:scale-95 transition-transform">
            <span id="selected-date-display" class="text-sm font-medium tracking-wide">読み込み中...</span>
            <span class="material-icons-round text-base">expand_more</span>
          </button>
          <button onclick="changeDate(1)"
            class="px-2.5 py-1.5 rounded-full hover:bg-surface-variant text-on-surface-variant active:bg-surface-variant/80 transition-colors text-xs font-semibold lg:text-sm lg:px-4 lg:py-2">
            <span class="lg:hidden">次へ</span><span class="hidden lg:inline">次の日</span>
          </button>
          <button onclick="goToToday()"
            class="ml-1 px-3 py-1.5 bg-primary-container text-on-primary-container rounded-full text-xs font-bold">今日</button>
        </div>
      </div>

      <!-- 日付セレクター（デスクトップ表示） -->
      <div class="absolute top-4 left-0 right-0 z-[60] hidden lg:flex justify-center pointer-events-none">
        <img src="room-booking-qr.png" class="pin-top-left" alt="固定表示">
        <div
          class="flex items-center gap-1 bg-surface-container rounded-full shadow-elevation-3 p-1 pointer-events-auto">
          <button onclick="changeDatePage(-1)"
            class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-surface-variant text-on-surface-variant active:bg-surface-variant/80 transition-colors"
            aria-label="前のページ">
            <span class="material-icons-round text-[20px]">chevron_left</span>
          </button>
          <button onclick="openDatePicker()"
            class="px-4 h-9 rounded-full bg-primary text-white flex items-center gap-1 active:scale-95 transition-transform"
            aria-label="表示日を変更">
            <span id="selected-date-display-desktop" class="text-sm font-medium tracking-wide">読み込み中...</span>
            <span class="material-icons-round text-base">expand_more</span>
          </button>
          <button onclick="changeDatePage(1)"
            class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-surface-variant text-on-surface-variant active:bg-surface-variant/80 transition-colors"
            aria-label="次のページ">
            <span class="material-icons-round text-[20px]">chevron_right</span>
          </button>
          <button onclick="goToToday()"
            class="ml-1 px-3 py-2 bg-primary-container text-on-primary-container rounded-full text-sm font-bold">今日</button>
        </div>
      </div>

      <!-- タイムライングリッド -->
      <div class="overflow-auto flex-1 w-full relative no-scrollbar" id="scroll-container">
        <div class="sticky top-0 z-50 bg-surface-container pt-16 relative">
          <div class="absolute inset-0 bg-surface-container" aria-hidden="true"></div>
          <div class="relative z-10">
            <div id="day-headers"
              class="absolute top-0 left-0 right-0 flex min-w-max px-0.5 pt-1 pointer-events-none relative"></div>
            <div class="flex min-w-max pb-2 pt-4 border-b border-outline-variant bg-surface-container">
              <div id="header-time-spacer"
                class="shrink-0 sticky left-0 z-[55] bg-surface-container border-r border-outline-variant/70">
              </div>
              <div id="room-headers" class="flex gap-0.5 px-0.5 relative"></div>
            </div>
          </div>
        </div>
        <div class="relative min-w-max flex">
          <div id="time-labels"
            class="sticky left-0 z-40 bg-surface-container flex flex-col text-[14px] text-on-surface-variant font-medium text-right border-r border-outline-variant/70 shrink-0 select-none relative">
          </div>
          <div id="room-columns" class="relative flex px-0.5 gap-0.5 pt-0"></div>
        </div>
      </div>

      <!-- FAB -->
      <div class="absolute right-6 flex items-center gap-3 z-50 safe-area-fab"
        style="bottom: calc(24px + env(safe-area-inset-bottom, 0px));">
        <button onclick="openNewReservation()"
          class="w-14 h-14 bg-primary text-white rounded-[16px] shadow-elevation-3 flex items-center justify-center hover:bg-primary/90 active:scale-90 transition-all">
          <span class="material-icons-round text-3xl">add</span>
        </button>
      </div>
    </main>
  </div>

  <!-- ========================================
       ======================================== -->
  <div id="detail-popup" class="fixed inset-0 z-[100] hidden">
    <div onclick="closeDetailPopup()" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] transition-opacity"></div>
    <div
      class="fixed bottom-0 left-0 right-0 z-50 flex flex-col items-center justify-end w-full h-full pointer-events-none">
      <div
        class="w-full max-w-md lg:max-w-sm bg-white rounded-t-2xl shadow-[0_-4px_20px_-5px_rgba(0,0,0,0.1)] pointer-events-auto transform transition-transform duration-300 ease-out flex flex-col max-h-[90vh] lg:max-h-[80vh]">
        <!-- Drag Handle -->
        <div class="flex h-5 w-full items-center justify-center pt-3 pb-1">
          <div class="h-1.5 w-12 rounded-full bg-slate-300"></div>
        </div>
        <!-- Header -->
        <div class="px-5 py-3 pt-2 flex justify-between items-start">
          <div class="flex flex-col gap-2 pt-2 pr-10">
            <h2 id="detail-title" class="text-xl font-bold leading-tight tracking-[-0.015em] text-[#0d141b]"></h2>
            <div class="flex items-center">
              <div
                class="flex h-7 items-center justify-center gap-x-1.5 rounded-full bg-green-50 border border-green-100 pl-2 pr-3">
                <span class="material-symbols-outlined text-green-600 text-[18px]">check_circle</span>
                <p id="detail-status-text" class="text-green-700 text-xs font-semibold leading-normal">予約確定</p>
              </div>
            </div>
          </div>
          <button onclick="closeDetailPopup()"
            class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1.5 rounded-full hover:bg-slate-100 transition-colors">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <!-- Content -->
        <div class="overflow-y-auto overflow-x-hidden flex-1 px-5 py-2 space-y-6 no-scrollbar">
          <div class="flex flex-col gap-5 pt-2">
            <!-- 日時 -->
            <div class="flex gap-4 items-start">
              <div
                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/20">
                <span class="text-[20px]" aria-hidden="true">⏰️</span>
              </div>
              <div class="flex flex-col">
                <p class="text-xs font-medium text-slate-500 mb-0.5 uppercase tracking-wide">日時</p>
                <div class="flex flex-wrap items-baseline gap-2">
                  <p id="detail-time" class="text-base font-bold text-[#0d141b]"></p>
                  <span id="detail-duration" class="text-sm font-medium text-slate-500"></span>
                </div>
                <p id="detail-date" class="text-sm text-slate-700 mt-0.5"></p>
              </div>
            </div>
            <!-- 場所 -->
            <div class="flex gap-4 items-start">
              <div
                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#e7edf3] text-slate-600">
                <span class="text-[20px]" aria-hidden="true">📍</span>
              </div>
              <div class="flex flex-col">
                <p class="text-xs font-medium text-slate-500 mb-0.5 uppercase tracking-wide">場所</p>
                <p id="detail-room" class="text-base font-bold text-[#0d141b]"></p>
              </div>
            </div>
            <!-- 予約者 -->
            <div class="flex gap-4 items-start">
              <div
                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#e7edf3] text-slate-600">
                <span class="text-[20px]" aria-hidden="true">👤</span>
              </div>
              <div class="flex flex-col">
                <p class="text-xs font-medium text-slate-500 mb-0.5 uppercase tracking-wide">予約者</p>
                <p id="detail-reserver" class="text-base font-semibold text-[#0d141b]"></p>
              </div>
            </div>
            <!-- 来客 -->
            <div id="detail-guest-section" class="flex gap-4 items-start hidden">
              <div
                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#e7edf3] text-slate-600">
                <span class="text-[20px]" aria-hidden="true">🍵</span>
              </div>
              <div class="flex flex-col">
                <p class="text-xs font-medium text-slate-500 mb-0.5 uppercase tracking-wide">来客</p>
                <p id="detail-guest" class="text-base font-semibold text-[#0d141b]"></p>
              </div>
            </div>
          </div>
        </div>
        <!-- Actions -->
        <div class="p-5 pt-4 pb-8 border-t border-[#cfdbe7] bg-white">
          <div class="flex flex-col gap-3">
            <p id="detail-readonly-note" class="hidden rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600"></p>
            <button id="detail-edit-button" onclick="editReservation()"
              class="relative flex w-full items-center justify-center gap-2 rounded-lg bg-primary-light py-3.5 px-4 text-center text-sm font-bold text-white shadow-sm hover:bg-[#1170d2] active:bg-[#0f63ba] transition-all">
              <span class="material-symbols-outlined text-[20px]">edit</span>
              予約を編集
            </button>
            <button id="detail-delete-button" onclick="deleteCurrentReservation()"
              class="relative flex w-full items-center justify-center gap-2 rounded-lg bg-red-50 py-3.5 px-4 text-center text-sm font-bold text-red-600 hover:bg-red-100 active:bg-red-200 transition-all border border-transparent hover:border-red-200">
              <span class="material-symbols-outlined text-[20px]">delete</span>
              削除
            </button>
            <button onclick="closeDetailPopup()"
              class="mt-1 w-full text-center text-sm font-medium text-slate-500 hover:text-slate-800 py-2 transition-colors">
              閉じる
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 繰り返し削除ダイアログ -->
  <div id="recurrence-delete-dialog" class="fixed inset-0 z-[120] hidden">
    <div class="dialog-scrim" onclick="closeRecurrenceDeleteDialog()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
      <div class="md-dialog">
        <h3 class="md-dialog-title">繰り返し予定の削除</h3>
        <div class="md-dialog-body">
          <label class="md-dialog-option">
            <input type="radio" name="recurrence-delete-scope" value="single" checked />
            <div class="flex flex-col">
              <span class="text-sm font-semibold text-slate-800">この予定のみ</span>
              <span class="text-xs text-slate-500">選択した1件だけ削除します</span>
            </div>
          </label>
          <label class="md-dialog-option">
            <input type="radio" name="recurrence-delete-scope" value="following" />
            <div class="flex flex-col">
              <span class="text-sm font-semibold text-slate-800">これ以降すべての予定</span>
              <span class="text-xs text-slate-500">選択日以降の予定をまとめて削除します</span>
            </div>
          </label>
        </div>
        <div class="md-dialog-actions">
          <button class="md-dialog-button" onclick="closeRecurrenceDeleteDialog()">キャンセル</button>
          <button class="md-dialog-button md-dialog-button-danger" onclick="confirmRecurrenceDelete()">削除</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================
       新規予約・編集画面（全画面フォーム）
       ======================================== -->
  <!-- 予約移動確認ダイアログ -->
  <div id="drag-move-dialog" class="fixed inset-0 z-[130] hidden">
    <div class="dialog-scrim" onclick="closeDragMoveDialog()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
      <div class="md-dialog">
        <h3 class="md-dialog-title">予約を移動</h3>
        <div class="md-dialog-body">
          <div class="rounded-xl border border-slate-200 bg-white p-3">
            <p class="text-[11px] font-semibold text-slate-500">移動元</p>
            <p id="drag-move-from" class="mt-1 text-sm font-medium text-slate-800 break-all"></p>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white p-3">
            <p class="text-[11px] font-semibold text-slate-500">移動先</p>
            <p id="drag-move-to" class="mt-1 text-sm font-medium text-slate-800 break-all"></p>
          </div>
        </div>
        <div class="md-dialog-actions">
          <button class="md-dialog-button" onclick="closeDragMoveDialog()">キャンセル</button>
          <button class="md-dialog-button" onclick="confirmMoveReservation()">移動する</button>
        </div>
      </div>
    </div>
  </div>

  <div id="form-view" class="view h-full lg:items-center lg:justify-center">
    <div
      class="relative flex h-[100dvh] w-full flex-col overflow-hidden lg:h-[85vh] lg:max-w-[900px] lg:rounded-2xl lg:border lg:border-border-light lg:bg-white lg:shadow-elevation-3">
      <!-- コンパクトヘッダー -->
      <div
        class="sticky top-0 z-50 flex items-center justify-between bg-surface-light px-3 py-2 border-b border-border-light">
        <button onclick="closeFormView()" class="p-2 rounded-full text-slate-500 hover:bg-slate-100">
          <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
        <h2 id="form-title" class="text-base font-bold text-center flex-1">新規予約</h2>
        <button onclick="saveReservation()"
          class="group relative ml-2 hidden shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#1A73E8] px-6 py-2.5 text-sm font-semibold text-white tracking-[0.02em] shadow-sm transition-colors hover:bg-[#0B57D0] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1A73E8]/50 focus-visible:ring-offset-2 focus-visible:ring-offset-white lg:inline-flex">
          <span class="relative z-10">確定</span>
          <span aria-hidden="true"
            class="pointer-events-none absolute inset-0 rounded-full bg-black/[0.00] transition-colors group-hover:bg-black/[0.08] group-active:bg-black/[0.12]"></span>
        </button>
      </div>

      <div class="form-scroll flex-1 flex flex-col gap-6 p-4">
        <!-- 会議室選択 & 日付 & 時間 -->
        <section class="space-y-4">
          <div class="flex flex-wrap items-center gap-4">
            <!-- 会議室選択 -->
            <div class="flex flex-1 min-w-[220px] flex-col gap-2">
              <label class="text-sm font-bold text-slate-700">会議室</label>
              <div class="md-field">
                <div
                  class="relative flex w-full items-center rounded-xl bg-surface-variant/30 shadow-sm ring-1 ring-border-light bg-[#F0F4F8] ring-0">
                  <span class="absolute left-3.5 text-[20px]" aria-hidden="true">📍</span>
                  <select id="form-room-id"
                    class="flex-1 cursor-pointer border-none bg-transparent py-4 pl-11 pr-4 text-base font-bold text-slate-700 focus:ring-0">
                    <!-- JSで生成 -->
                  </select>
                </div>
              </div>
            </div>

            <!-- 日付選択 -->
            <div class="flex flex-1 min-w-[220px] flex-col gap-2">
              <label class="text-sm font-bold text-slate-700">日付</label>
              <div class="md-field md-readonly">
                <div
                  class="relative flex w-full items-center rounded-xl bg-surface-variant/30 shadow-sm ring-1 ring-border-light bg-[#F0F4F8] ring-0">
                  <input id="form-date" type="date" readonly tabindex="-1" aria-readonly="true"
                    class="flex-1 border-none bg-transparent py-4 pl-4 pr-12 text-base font-semibold text-slate-700 tracking-[0.01em] cursor-default focus:ring-0 pointer-events-none select-none" />
                  <div class="absolute right-4 flex text-slate-500 pointer-events-none">
                    <span class="text-[22px]" aria-hidden="true">📅</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 時間選択 -->
          <div class="flex gap-4">
            <div class="flex-1 flex flex-col gap-2">
              <label class="text-sm font-bold text-slate-700 text-center">開始時間</label>
              <div class="md-field">
                <select id="form-start-time"
                  class="w-full rounded-xl border-border-light bg-surface-light px-4 py-3.5 text-center text-base font-bold text-primary-light focus:border-primary focus:ring-1 focus:ring-primary shadow-sm">
                  <!-- JSで生成 -->
                </select>
              </div>
            </div>
            <div class="flex-1 flex flex-col gap-2">
              <label class="text-sm font-bold text-slate-700 text-center">所要時間</label>
              <div class="md-field">
                <select id="form-duration"
                  class="w-full rounded-xl border-border-light bg-surface-light px-4 py-3.5 text-center text-base font-bold text-primary-light focus:border-primary focus:ring-1 focus:ring-primary shadow-sm">
                  <option value="15">15分</option>
                  <option value="30">30分</option>
                  <option value="45">45分</option>
                  <option value="60" selected>1時間</option>
                  <option value="90">1時間30分</option>
                  <option value="120">2時間</option>
                  <option value="150">2時間30分</option>
                  <option value="180">3時間</option>
                  <option value="240">4時間</option>
                </select>
              </div>
            </div>
          </div>
        </section>

        <hr class="border-border-light opacity-50" />

        <!-- 会議詳細 -->
        <section class="space-y-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-bold text-slate-700">会議名 <span
                class="text-red-500 ml-1 text-xs font-normal">必須</span></label>
            <div class="md-field">
              <input id="form-meeting-name"
                class="w-full rounded-xl border-border-light bg-surface-light px-4 py-3.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary shadow-sm"
                placeholder="例：週次定例ミーティング" maxlength="20" />
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label class="text-sm font-bold text-slate-700">予約者名 <span
                class="text-red-500 ml-1 text-xs font-normal">必須</span></label>
            <div class="md-field">
              <div class="relative">
                <input id="form-reserver-name"
                  class="w-full rounded-xl border-border-light bg-surface-light px-4 py-3.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary shadow-sm pl-11"
                  placeholder="氏名を入力" />
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[20px]" aria-hidden="true">👤</span>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <div class="flex justify-between items-center">
              <label class="text-sm font-bold text-slate-700">来客名</label>
              <span class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded">任意</span>
            </div>
            <div class="md-field">
              <div class="relative">
                <input id="form-guest-name"
                  class="w-full rounded-xl border-border-light bg-surface-light px-4 py-3.5 text-base text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary shadow-sm pl-11"
                  placeholder="社名・氏名" />
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[20px]" aria-hidden="true">🍵</span>
              </div>
            </div>
          </div>

          <div class="recurrence-card">
            <div class="recurrence-row">
              <div class="flex items-center gap-2 text-slate-700">
                <span class="material-symbols-outlined text-[18px] text-primary">repeat</span>
                <span class="text-sm font-semibold">繰り返し設定</span>
              </div>
              <label class="md-switch">
                <input id="recurrence-toggle" type="checkbox" />
                <span class="md-switch-track"></span>
                <span class="md-switch-thumb"></span>
              </label>
            </div>

            <div id="recurrence-options" class="recurrence-options hidden">
              <div class="flex flex-wrap gap-4">
                <div class="flex flex-1 min-w-[160px] flex-col gap-2">
                  <label class="text-sm font-bold text-slate-700">頻度</label>
                  <div class="md-field">
                    <select id="recurrence-frequency"
                      class="w-full rounded-xl border-border-light bg-surface-light text-center text-base font-semibold text-primary-light focus:border-primary focus:ring-1 focus:ring-primary shadow-sm">
                      <option value="daily">毎日</option>
                      <option value="weekly" selected>毎週</option>
                      <option value="biweekly">隔週</option>
                      <option value="monthly">毎月</option>
                    </select>
                  </div>
                </div>
                <div class="flex flex-1 min-w-[160px] flex-col gap-2">
                  <label class="text-sm font-bold text-slate-700">終了日</label>
                  <div class="md-field">
                    <input id="recurrence-until" type="date"
                      class="w-full rounded-xl border-border-light bg-surface-light text-center text-base font-semibold text-slate-700 focus:border-primary focus:ring-1 focus:ring-primary shadow-sm" />
                  </div>
                </div>
              </div>
              <p class="text-xs text-slate-500 mt-3">終了日当日まで作成します</p>
            </div>
          </div>
        </section>
      </div>
      <!-- モバイル固定アクション -->
      <div id="form-action-bar"
        class="fixed left-0 right-0 z-[60] bg-white/95 backdrop-blur border-t border-border-light px-4 pt-3 pb-4 lg:hidden">
        <button onclick="saveReservation()"
          class="form-primary-action group relative inline-flex w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-[#1A73E8] px-6 py-3.5 text-base font-bold text-white shadow-elevation-2 transition-colors hover:bg-[#0B57D0]">
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">check_circle</span>
          <span class="relative z-10">確定して保存</span>
          <span aria-hidden="true"
            class="pointer-events-none absolute inset-0 rounded-2xl bg-black/[0.00] transition-colors group-hover:bg-black/[0.08] group-active:bg-black/[0.12]"></span>
        </button>
      </div>
    </div>
  </div>

  <!-- 日付ピッカーモーダル -->
  <div id="date-picker-modal" class="fixed inset-0 z-[100] hidden">
    <div onclick="closeDatePicker()" class="fixed inset-0 bg-black/60 backdrop-blur-[2px]"></div>
    <div class="fixed bottom-0 left-0 right-0 z-50 flex flex-col items-center justify-end w-full pointer-events-none">
      <div class="w-full max-w-md bg-white rounded-t-2xl shadow-lg pointer-events-auto p-6">
        <h3 class="text-lg font-bold mb-4 text-center">日付を選択</h3>
        <input type="date" id="date-picker-input"
          class="w-full rounded-xl border-outline-variant bg-surface px-4 py-4 text-lg font-medium text-center focus:border-primary focus:ring-2 focus:ring-primary shadow-sm" />
        <div class="flex gap-3 mt-6">
          <button onclick="closeDatePicker()"
            class="flex-1 py-3 rounded-xl border border-outline-variant text-on-surface-variant font-medium">キャンセル</button>
          <button onclick="applyDateSelection()"
            class="flex-1 py-3 rounded-xl bg-primary text-white font-bold">適用</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ローディング -->
  <div id="loading-overlay" class="fixed inset-0 z-[200] bg-white/80 flex items-center justify-center hidden">
    <div class="flex flex-col items-center gap-3">
      <div class="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      <p id="loading-message" class="text-sm text-on-surface-variant">読み込み中...</p>
    </div>
  </div>

  <!-- 初期読み込みスケルトン（外部リソース読み込み前に表示） -->
  <div id="initial-skeleton" class="fixed inset-0 z-[250] bg-[#f2f5f8] flex flex-col">
    <style>
      .skeleton-pulse {
        background: linear-gradient(90deg, #e1e4eb 25%, #f2f5f8 50%, #e1e4eb 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
      }

      @keyframes skeleton-shimmer {
        0% {
          background-position: 200% 0;
        }

        100% {
          background-position: -200% 0;
        }
      }
    </style>
    <!-- ヘッダースケルトン -->
    <div class="flex justify-center pt-4 pb-2">
      <div class="flex items-center gap-1 bg-white rounded-full shadow-lg p-1">
        <div class="skeleton-pulse w-10 h-8 rounded-full"></div>
        <div class="skeleton-pulse w-28 h-10 rounded-full bg-[#3949ab]/20"></div>
        <div class="skeleton-pulse w-10 h-8 rounded-full"></div>
        <div class="skeleton-pulse w-12 h-8 rounded-full ml-1"></div>
      </div>
    </div>
    <!-- グリッドスケルトン -->
    <div class="flex-1 px-2 pt-12 overflow-hidden">
      <!-- 部屋ヘッダー -->
      <div class="flex gap-1 mb-2 pl-12">
        <div class="skeleton-pulse w-10 h-8 rounded-md"></div>
        <div class="skeleton-pulse w-10 h-8 rounded-md"></div>
        <div class="skeleton-pulse w-10 h-8 rounded-md"></div>
        <div class="skeleton-pulse w-10 h-8 rounded-md"></div>
        <div class="skeleton-pulse w-10 h-8 rounded-md"></div>
      </div>
      <!-- タイムライングリッド -->
      <div class="flex">
        <div class="w-12 flex flex-col gap-4 pr-2">
          <div class="skeleton-pulse h-4 rounded"></div>
          <div class="skeleton-pulse h-4 rounded"></div>
          <div class="skeleton-pulse h-4 rounded"></div>
          <div class="skeleton-pulse h-4 rounded"></div>
          <div class="skeleton-pulse h-4 rounded"></div>
        </div>
        <div class="flex-1 flex gap-1">
          <div class="flex-1 flex flex-col gap-1">
            <div class="skeleton-pulse h-16 rounded-md"></div>
            <div class="skeleton-pulse h-12 rounded-md"></div>
            <div class="skeleton-pulse h-20 rounded-md"></div>
          </div>
          <div class="flex-1 flex flex-col gap-1">
            <div class="skeleton-pulse h-12 rounded-md"></div>
            <div class="skeleton-pulse h-24 rounded-md"></div>
          </div>
          <div class="flex-1 flex flex-col gap-1">
            <div class="skeleton-pulse h-20 rounded-md"></div>
            <div class="skeleton-pulse h-16 rounded-md"></div>
          </div>
        </div>
      </div>
    </div>
    <!-- ローディングテキスト -->
    <div class="absolute bottom-8 left-0 right-0 flex justify-center">
      <div class="flex items-center gap-2 text-[#44474f] text-sm">
        <div class="w-5 h-5 border-2 border-[#3949ab] border-t-transparent rounded-full animate-spin"></div>
        <span>読み込み中...</span>
      </div>
    </div>
  </div>

  <script>
    // ========================================
    // ========================================

    const DAY_NAMES = ['日', '月', '火', '水', '木', '金', '土'];
    const START_HOUR = 8;
    const END_HOUR = 24;
    const SLOT_HEIGHT = 22;
    const DESKTOP_MIN_WIDTH = 1024;
    const DESKTOP_DAY_COUNT = 2;
    const LONG_PRESS_MS = 250;
    const DRAG_CANCEL_DISTANCE_PX = 8;
    const DETAIL_CLICK_SUPPRESS_MS = 350;
    const STYLE_CONFIG = {
      MOBILE: {
        ROOM_WIDTH: 48,
        TIME_LABEL_WIDTH: 48,
        TIME_LABEL_CLASS: 'w-12',
        HEADER_HEIGHT_CLASS: 'min-h-[30px]',
        HEADER_FONT_SIZE: 'text-[11px]',
        COLUMN_WIDTH_CLASS: 'w-12',
        HEADER_WIDTH_CLASS: 'w-12'
      },
      DESKTOP: {
        ROOM_WIDTH: 100,
        TIME_LABEL_WIDTH: 64,
        TIME_LABEL_CLASS: 'w-16',
        HEADER_HEIGHT_CLASS: 'min-h-[40px]',
        HEADER_FONT_SIZE: 'text-[15px]',
        COLUMN_WIDTH_CLASS: 'w-[100px]',
        HEADER_WIDTH_CLASS: 'w-[100px]'
      }
    };

    const ROOM_HEADER_THEMES = [
      { bg: '#C7C7C7', text: '#000000', border: '#000000' }, // 591 - グレー
      { bg: '#FFA1A1', text: '#000000', border: '#000000' }, // 592 - ピンク
      { bg: '#FFA1A1', text: '#000000', border: '#000000' }, // 294 - ピンク
      { bg: '#FFE599', text: '#000000', border: '#000000' }, // 593 - 鮟・牡
      { bg: '#A4C2F4', text: '#000000', border: '#000000' }, // 291 - 髱・
      { bg: '#A4C2F4', text: '#000000', border: '#000000' }, // 292 - 髱・
      { bg: '#A4C2F4', text: '#000000', border: '#000000' }, // 293 - 髱・
      { bg: '#D9EAD3', text: '#000000', border: '#000000' }, // 301 - 邱・
      { bg: '#D9EAD3', text: '#000000', border: '#000000' }, // 302 - 邱・
      { bg: '#B4A7D6', text: '#000000', border: '#000000' }  // 601 - 紫
    ];

    const ROOM_HEADER_THEME_BY_ID = {
      '591': 0,
      '592': 1,
      '294': 2,
      '593': 3,
      '291': 4,
      '292': 5,
      '293': 6,
      '301': 7,
      '302': 8,
      '601': 9
    };

    function hashString_(value) {
      const str = String(value || '');
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
      }
      return Math.abs(hash);
    }

    function getRoomHeaderTheme_(roomId) {
      const mapped = ROOM_HEADER_THEME_BY_ID[String(roomId)];
      if (mapped !== undefined) return ROOM_HEADER_THEMES[mapped];
      const idx = hashString_(roomId) % ROOM_HEADER_THEMES.length;
      return ROOM_HEADER_THEMES[idx];
    }

    // ========================================
    // 状態
    // ========================================
    let currentDate = new Date();
    let rooms = [];
    let reservations = [];
    let reservationsByDate = {};
    let tteInterviewsByDate = {};
    let currentReservation = null;
    let editingReservationId = null;
    let lastIsDesktop = null;
    let dragState = null;
    let pendingMoveState = null;
    let suppressDetailClickUntil = 0;

    const TTE_PROXY_URL = './api/getTteInterviews.php';
    const TTE_MAX_RANGE_DAYS = 5;
    const TTE_ROOM_MAPPING = {
      INT_MAIN_5F_SHITSUMU: { roomId: 591, roomName: '5階執務室' },
      INT_MAIN_5F_OSETSU_A: { roomId: 592, roomName: '5階応接A' },
      INT_MAIN_2F_OSETSU_B: { roomId: 294, roomName: '2階応接B' },
      INT_BLDG3_1F_3A: { roomId: 301, roomName: '3号館面3A' },
      INT_BLDG3_1F_3B: { roomId: 302, roomName: '3号館面3B' }
    };
    const TTE_CONFLICT_ROOM_IDS = [591, 592, 294, 301, 302];
    const TTE_CONFLICT_ROOM_ID_SET = new Set(TTE_CONFLICT_ROOM_IDS.map(id => String(id)));

    const CACHE_KEYS = {
      rooms: 'meeting-rooms-cache-v2',
      reservations: 'meeting-reservations-cache-v2'
    };
    const CACHE_TTL_MS = {
      rooms: 24 * 60 * 60 * 1000,
      reservations: 5 * 60 * 1000
    };

    function readCache(key, ttlMs) {
      try {
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return null;
        if (!Object.prototype.hasOwnProperty.call(parsed, 'timestamp')) return null;
        if (!Object.prototype.hasOwnProperty.call(parsed, 'data')) return null;
        if (ttlMs && Date.now() - parsed.timestamp > ttlMs) return null;
        return parsed.data;
      } catch (e) {
        return null;
      }
    }

    function writeCache(key, data) {
      try {
        localStorage.setItem(key, JSON.stringify({ timestamp: Date.now(), data }));
      } catch (e) {
        // ignore cache errors
      }
    }

    function hydrateFromCache() {
      const cachedRooms = readCache(CACHE_KEYS.rooms, CACHE_TTL_MS.rooms);
      if (Array.isArray(cachedRooms) && cachedRooms.length) {
        rooms = cachedRooms;
      } else {
        rooms = FALLBACK_ROOMS;
      }

      const cachedReservations = readCache(CACHE_KEYS.reservations, CACHE_TTL_MS.reservations);
      if (cachedReservations && typeof cachedReservations === 'object') {
        reservationsByDate = cachedReservations;
        const todayKey = formatDateValue(currentDate);
        reservations = cachedReservations[todayKey] || [];
      } else {
        reservationsByDate = {};
        reservations = [];
      }
      tteInterviewsByDate = {};
    }

    const FALLBACK_ROOMS = [
      { roomId: 591, roomName: '5階執務室', capacity: 10 },
      { roomId: 592, roomName: '5階応接A', capacity: 6 },
      { roomId: 294, roomName: '2階応接B', capacity: 6 },
      { roomId: 593, roomName: '5階OL面', capacity: 4 },
      { roomId: 291, roomName: '面談A', capacity: 4 },
      { roomId: 292, roomName: '面談B', capacity: 4 },
      { roomId: 293, roomName: 'CSL', capacity: 8 },
      { roomId: 301, roomName: '3号館面3A', capacity: 4 },
      { roomId: 302, roomName: '3号館面3B', capacity: 4 },
      { roomId: 601, roomName: '六角5F', capacity: 10 }
    ];

    // ========================================
    // ポーリング
    // ========================================
    const POLLING_INTERVAL_MS = 30000; // 30秒ごとにポーリング
    let pollingTimerId = null;

    document.addEventListener('DOMContentLoaded', async () => {
      generateTimeOptions();
      attachRipples();
      initRecurrenceUI();
      hydrateFromCache();
      renderRoomOptions();
      renderTimeline();
      updateDateDisplay();
      scrollToCurrentTime();
      lastIsDesktop = isDesktopView();
      hideInitialSkeleton();
      // API呼び出しを並列化して高速化
      await Promise.all([loadRooms({ silent: true }), loadReservationsOnly(), loadTteInterviews()]);
      renderRoomOptions();
      renderTimeline();
      updateDateDisplay();
      scrollToCurrentTime();
      lastIsDesktop = isDesktopView();

      // 初期スケルトンを非表示（フェードアウト）
      hideInitialSkeleton();

      // リサイズ検知
      window.addEventListener('resize', () => {
        const currentIsDesktop = isDesktopView();
        // モード切替時 または デスクトップモードでのリサイズ時（幅再計算のため）
        if (currentIsDesktop !== lastIsDesktop || (currentIsDesktop && lastIsDesktop)) {
          lastIsDesktop = currentIsDesktop;
          updateDateDisplay();
          renderTimeline(); // 再描画
        }
      });

      // 自動ポーリング開始
      startPolling();

      // ページの表示/非表示でポーリングを制御
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          stopPolling();
        } else {
          // ページが再表示されたら即座にデータを更新してポーリング再開
          pollReservations();
          startPolling();
        }
      });

      window.addEventListener('resize', () => {
        const isDesktopNow = isDesktopView();
        if (isDesktopNow !== lastIsDesktop) {
          lastIsDesktop = isDesktopNow;
          Promise.all([loadReservationsOnly(), loadTteInterviews()]).then(() => renderTimeline());
        }
      });

      if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
          updateKeyboardOffset();
          scheduleActiveFieldVisibilityCheck();
        });
        window.visualViewport.addEventListener('scroll', updateKeyboardOffset);
      }
      window.addEventListener('resize', () => {
        updateKeyboardOffset();
        scheduleActiveFieldVisibilityCheck();
      });
      document.addEventListener('focusin', (event) => {
        updateKeyboardOffset();
        setTimeout(() => ensureActiveFieldVisible(event.target), 50);
        setTimeout(() => ensureActiveFieldVisible(document.activeElement), 180);
      });
      document.addEventListener('focusout', () => {
        updateKeyboardOffset();
        setTimeout(updateKeyboardOffset, 180);
      });
      updateFormActionBarHeight();
      updateKeyboardOffset();
    });

    // ポーリング開始
    function startPolling() {
      if (pollingTimerId) return; // 既に動いている場合は何もしない
      pollingTimerId = setInterval(pollReservations, POLLING_INTERVAL_MS);
      console.log('Polling started (every 30s)');
    }

    // ポーリング停止
    function stopPolling() {
      if (pollingTimerId) {
        clearInterval(pollingTimerId);
        pollingTimerId = null;
        console.log('Polling stopped');
      }
    }

    // 編集を確実に検知するため、毎回再描画する
    async function pollReservations() {
      try {
        await Promise.all([
          loadReservationsOnly({ cache: false }),
          loadTteInterviews()
        ]);
        renderTimeline();
        console.log('Polling: Timeline updated');
      } catch (error) {
        console.error('Polling error:', error);
      }
    }

    async function loadReservationsOnly(options = {}) {
      const { cache = true, datesTarget = null } = options;
      try {
        const dates = datesTarget || getDisplayDates();
        // console.log('Loading reservations for dates:', dates); // ログ抑制
        const results = await Promise.all(
          dates.map(dateStr => apiGet('getReservations', { date: dateStr }))
        );

        // キャッシュを初期化せず、マージする
        if (!reservationsByDate) reservationsByDate = {};

        dates.forEach((dateStr, idx) => {
          reservationsByDate[dateStr] = results[idx] || [];
        });

        // 現在表示中の日付ならreservationsを更新（プリフェッチ時は更新しない）
        if (!datesTarget) {
          reservations = reservationsByDate[getDisplayDates()[0]] || [];
        }

        if (cache) {
          writeCache(CACHE_KEYS.reservations, reservationsByDate);
        }
        // console.log('Reservations loaded:', reservationsByDate); // ログ抑制
      } catch (error) {
        console.error('Failed to load reservations:', error);
        if (!datesTarget && (!reservationsByDate || Object.keys(reservationsByDate).length === 0)) {
          reservations = [];
          reservationsByDate = {};
        }
      }
    }

    function getTteInterviewsForDate(dateStr) {
      if (tteInterviewsByDate && tteInterviewsByDate[dateStr]) {
        return tteInterviewsByDate[dateStr];
      }
      return [];
    }

    function enumerateDateRange_(fromDateStr, toDateStr) {
      const from = new Date(`${fromDateStr}T00:00:00`);
      const to = new Date(`${toDateStr}T00:00:00`);
      if (isNaN(from.getTime()) || isNaN(to.getTime()) || from > to) {
        return [];
      }
      const dates = [];
      const cursor = new Date(from);
      while (cursor <= to) {
        dates.push(formatDateValue(cursor));
        cursor.setDate(cursor.getDate() + 1);
      }
      return dates;
    }

    function parseIsoDateAndTime_(isoValue) {
      const text = String(isoValue || '');
      const matched = text.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/);
      if (!matched) return null;
      return { date: matched[1], time: matched[2] };
    }

    function getIsoDurationMinutes_(startAt, endAt) {
      const start = new Date(startAt);
      const end = new Date(endAt);
      if (isNaN(start.getTime()) || isNaN(end.getTime())) return null;
      const diff = Math.round((end.getTime() - start.getTime()) / 60000);
      if (diff <= 0) return null;
      return diff;
    }

    function mapTteInterviewItem_(item) {
      if (!item || typeof item !== 'object') return null;

      const placeCode = String(item.place_code || '').trim();
      if (!placeCode) return null;

      const mappedRoom = TTE_ROOM_MAPPING[placeCode];
      if (!mappedRoom) return null;

      const startParts = parseIsoDateAndTime_(item.start_at);
      const endParts = parseIsoDateAndTime_(item.end_at);
      const durationMinutes = getIsoDurationMinutes_(item.start_at, item.end_at);
      if (!startParts || !endParts || !durationMinutes) return null;

      const interviewerNames = Array.isArray(item.interviewer_names)
        ? item.interviewer_names
          .filter(name => name !== null && name !== undefined && String(name).trim() !== '')
          .map(name => String(name).trim())
        : [];

      const meetingName = String(item.title || '').trim();
      const studentName = String(item.student_name || '').trim();
      const bookingId = String(item.booking_id || '').trim();
      const reservationId = bookingId || `tte-${mappedRoom.roomId}-${startParts.date}-${startParts.time}-${durationMinutes}`;

      return {
        reservationId,
        roomId: mappedRoom.roomId,
        roomName: mappedRoom.roomName,
        date: startParts.date,
        startTime: startParts.time,
        duration: durationMinutes,
        durationMinutes,
        meetingName,
        reserverName: interviewerNames.join('、'),
        visitorName: studentName,
        guestName: studentName,
        source: 'tte',
        readOnly: true
      };
    }

    async function fetchTteInterviewsChunk_(from, to) {
      const params = new URLSearchParams({ from, to });
      const response = await fetch(`${TTE_PROXY_URL}?${params.toString()}`, {
        method: 'GET',
        cache: 'no-store'
      });
      const text = await response.text();
      let payload = {};

      if (text) {
        try {
          payload = JSON.parse(text);
        } catch (error) {
          if (!response.ok) {
            throw new Error(`TTE API ${response.status}`);
          }
          payload = {};
        }
      }

      if (!response.ok) {
        const message = (payload && payload.error) ? payload.error : `TTE API ${response.status}`;
        throw new Error(message);
      }

      return payload;
    }

    async function loadTteInterviews(options = {}) {
      const { datesTarget = null } = options;
      const dates = (datesTarget && datesTarget.length ? datesTarget : getDisplayDates())
        .filter(Boolean)
        .sort();
      if (!dates.length) return;

      try {
        const from = dates[0];
        const to = dates[dates.length - 1];
        const rangeDates = enumerateDateRange_(from, to);
        if (!rangeDates.length) return;

        const ranges = [];
        for (let i = 0; i < rangeDates.length; i += TTE_MAX_RANGE_DAYS) {
          ranges.push({
            from: rangeDates[i],
            to: rangeDates[Math.min(i + TTE_MAX_RANGE_DAYS - 1, rangeDates.length - 1)]
          });
        }

        const nextByDate = {};
        rangeDates.forEach(dateStr => {
          nextByDate[dateStr] = [];
        });

        const settled = await Promise.allSettled(
          ranges.map(range => fetchTteInterviewsChunk_(range.from, range.to))
        );

        settled.forEach(result => {
          if (result.status !== 'fulfilled') {
            console.error('Failed to load TTE interviews chunk:', result.reason);
            return;
          }
          const payload = result.value || {};
          const items = Array.isArray(payload.items) ? payload.items : [];
          items.forEach(item => {
            const mapped = mapTteInterviewItem_(item);
            if (!mapped) return;
            if (!nextByDate[mapped.date]) return;
            nextByDate[mapped.date].push(mapped);
          });
        });

        rangeDates.forEach(dateStr => {
          tteInterviewsByDate[dateStr] = nextByDate[dateStr] || [];
        });
      } catch (error) {
        console.error('Failed to load TTE interviews:', error);
        dates.forEach(dateStr => {
          tteInterviewsByDate[dateStr] = [];
        });
      }
    }

    // 前後の日付をプリフェッチ
    async function prefetchAdjacentDates() {
      const currentDates = getDisplayDates();
      if (currentDates.length === 0) return;

      const firstDate = new Date(currentDates[0]);
      const lastDate = new Date(currentDates[currentDates.length - 1]);
      const prefetchDays = 7; // 前後1週間分をプリフェッチ

      // 前の日程
      const prevDates = [];
      for (let i = 1; i <= prefetchDays; i++) {
        const d = new Date(firstDate);
        d.setDate(d.getDate() - i);
        prevDates.push(formatDateValue(d));
      }

      // 次の日程
      const nextDates = [];
      for (let i = 1; i <= prefetchDays; i++) {
        const d = new Date(lastDate);
        d.setDate(d.getDate() + i);
        nextDates.push(formatDateValue(d));
      }

      const targetDates = [...prevDates, ...nextDates];
      // すでにキャッシュにある日付は除外
      const neededDates = targetDates.filter(d => !reservationsByDate || !reservationsByDate[d]);

      if (neededDates.length > 0) {
        // console.log('Prefetching:', neededDates);
        await loadReservationsOnly({ cache: true, datesTarget: neededDates });
      }
    }

    /**
     * バックグラウンドでデータをフェッチして差分更新
     */
    function fetchAndRenderInBackground() {
      Promise.all([
        loadReservationsOnly({ cache: true }),
        loadTteInterviews()
      ]).then(() => {
        renderTimeline();
        // 表示更新後にプリフェッチを実行
        setTimeout(prefetchAdjacentDates, 500);
      }).catch(err => {
        console.error('Background fetch error:', err);
      });
    }

    // ローディング表示付きの予約読み込み（編集・削除後に使用）
    async function loadReservations() {
      showLoading();
      await Promise.all([loadReservationsOnly(), loadTteInterviews()]);
      hideLoading();
    }

    function generateTimeOptions() {
      const select = document.getElementById('form-start-time');
      select.innerHTML = '';
      for (let h = START_HOUR; h <= END_HOUR; h++) {
        for (let m = 0; m < 60; m += 15) {
          const val = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
          const opt = document.createElement('option');
          opt.value = val;
          opt.textContent = val;
          select.appendChild(opt);
        }
      }
    }

    function renderRoomOptions(selectedRoomId = null, fallbackRoomName = '') {
      const select = document.getElementById('form-room-id');
      if (!select) return;

      const preferredRoomId = (selectedRoomId !== null && selectedRoomId !== undefined && selectedRoomId !== '')
        ? String(selectedRoomId)
        : String(select.value || '');

      select.innerHTML = '';
      rooms.forEach(room => {
        const opt = document.createElement('option');
        opt.value = String(room.roomId);
        opt.textContent = room.roomName;
        select.appendChild(opt);
      });

      let hasPreferredRoom = Array.from(select.options).some(opt => opt.value === preferredRoomId);
      if (preferredRoomId && !hasPreferredRoom) {
        const fallback = document.createElement('option');
        fallback.value = preferredRoomId;
        fallback.textContent = fallbackRoomName || `不明な会議室 (${preferredRoomId})`;
        select.appendChild(fallback);
        hasPreferredRoom = true;
      }

      if (hasPreferredRoom) {
        select.value = preferredRoomId;
      } else if (select.options.length > 0) {
        select.selectedIndex = 0;
      }
    }

    function initRecurrenceUI() {
      const toggle = document.getElementById('recurrence-toggle');
      const options = document.getElementById('recurrence-options');
      if (!toggle || !options) return;
      toggle.addEventListener('change', () => {
        const enabled = toggle.checked;
        options.classList.toggle('hidden', !enabled);
        if (enabled) {
          applyRecurrenceDefaults();
        }
      });
    }

    function applyRecurrenceDefaults() {
      const dateInput = document.getElementById('form-date');
      const untilInput = document.getElementById('recurrence-until');
      const frequencySelect = document.getElementById('recurrence-frequency');
      if (frequencySelect && !frequencySelect.value) {
        frequencySelect.value = 'weekly';
      }
      if (dateInput && untilInput && dateInput.value && !untilInput.value) {
        untilInput.value = addDaysToDateString_(dateInput.value, 7);
      }
    }

    function resetRecurrenceUI() {
      const toggle = document.getElementById('recurrence-toggle');
      const options = document.getElementById('recurrence-options');
      const untilInput = document.getElementById('recurrence-until');
      const frequencySelect = document.getElementById('recurrence-frequency');
      if (toggle) {
        toggle.checked = false;
      }
      if (options) {
        options.classList.add('hidden');
      }
      if (untilInput) {
        untilInput.value = '';
      }
      if (frequencySelect) {
        frequencySelect.value = 'weekly';
      }
    }

    // ========================================
    // API
    // ========================================
    // ========================================
    // API (google.script.run)
    // ========================================

    // google.script.run が使えない環境では Web App にフォールバック
    const GAS_WEB_APP_URL = './api/index.php'; // 同一サーバー上のPHP APIエンドポイント
    function runGas(action, params = {}) {
      console.log('runGas called:', action, params); // デバッグ
      // Apps Script HTML Service 上で動いている場合
      if (typeof google !== 'undefined' && google.script && google.script.run) {
        return new Promise((resolve, reject) => {
          google.script.run
            .withSuccessHandler((resultStr) => {
              console.log('runGas raw result:', action, resultStr); // デバッグ
              // GASからはJSON文字列で返されるのでパースする
              try {
                const result = resultStr ? JSON.parse(resultStr) : null;
                console.log('runGas parsed result:', action, result); // デバッグ
                resolve(result);
              } catch (e) {
                console.error('runGas parse error:', e);
                resolve(resultStr); // パースできない場合はそのまま返す
              }
            })
            .withFailureHandler((error) => {
              console.error('runGas failure:', action, error); // デバッグ
              reject(error);
            })
            .processApi(action, params);
        });
      }

      // Google Sites埋め込み等で動いている場合
      if (!GAS_WEB_APP_URL) {
        return Promise.reject(new Error('GAS_WEB_APP_URL is not set'));
      }

      const payload = Object.assign({ action }, params);
      return fetch(GAS_WEB_APP_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain' },
        body: JSON.stringify(payload),
        redirect: 'follow'
      })
        .then(res => res.json())
        .then(json => {
          if (!json || !json.success) {
            throw new Error((json && json.error) || 'API error');
          }
          return json.data;
        });
    }

    async function apiGet(action, params = {}) {
      return runGas(action, params);
    }

    async function apiPost(action, payload) {
      return runGas(action, payload);
    }

    async function apiMutate(action, payload = {}) {
      return runGas(action, payload);
    }

    // ========================================
    // データ読み込み
    // ========================================
    const ROOM_ORDER = [591, 592, 294, 593, 291, 292, 293, 301, 302, 601];

    function sortRoomsByOrder(roomsArray) {
      if (!Array.isArray(roomsArray)) return [];
      const sorted = roomsArray.slice().sort((a, b) => {
        const idxA = ROOM_ORDER.indexOf(Number(a.roomId));
        const idxB = ROOM_ORDER.indexOf(Number(b.roomId));
        // 定義にないroomIdは末尾に
        const orderA = idxA === -1 ? 9999 : idxA;
        const orderB = idxB === -1 ? 9999 : idxB;
        return orderA - orderB;
      });
      // ROOM_ORDERに含まれる部屋のみを返す（不要な部屋を除外）
      return sorted.filter(r => ROOM_ORDER.includes(Number(r.roomId)));
    }

    function getRoomNameById_(roomId) {
      const room = rooms.find(r => String(r.roomId) === String(roomId));
      if (room && room.roomName) return room.roomName;
      const mapped = Object.values(TTE_ROOM_MAPPING).find(v => String(v.roomId) === String(roomId));
      if (mapped && mapped.roomName) return mapped.roomName;
      return String(roomId);
    }

    function getDisplayRoomColumns() {
      const mainColumns = rooms.map(room => ({
        type: 'main',
        roomId: Number(room.roomId),
        roomName: room.roomName
      }));
      const displacedColumns = TTE_CONFLICT_ROOM_IDS.map(roomId => ({
        type: 'displaced',
        roomId,
        roomName: `重複_${getRoomNameById_(roomId)}`
      }));
      return [...mainColumns, ...displacedColumns];
    }

    async function loadRooms(options = {}) {
      const { silent = false } = options;
      if (!silent) showLoading();
      try {
        let fetched = await apiGet('getRooms');
        rooms = sortRoomsByOrder(fetched);
        if (Array.isArray(rooms) && rooms.length) {
          writeCache(CACHE_KEYS.rooms, rooms);
        }
      } catch (error) {
        if (!Array.isArray(rooms) || rooms.length === 0) {
          rooms = FALLBACK_ROOMS;
        }
      }
      renderRoomOptions();
      if (!silent) hideLoading();
    }

    // ※ loadReservations は L1089-1094 に定義済みのため削除

    // ========================================
    // ユーティリティ
    // ========================================
    function formatDateValue(date) {
      const y = date.getFullYear();
      const m = String(date.getMonth() + 1).padStart(2, '0');
      const d = String(date.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    }

    function isDesktopView() {
      return window.matchMedia(`(min-width: ${DESKTOP_MIN_WIDTH}px)`).matches;
    }

    function getResponsiveRoomWidth() {
      if (!isDesktopView()) return 48; // STYLE_CONFIG.MOBILE.ROOM_WIDTH (48px)

      const container = document.getElementById('scroll-container');
      // コンテナが取得できない場合や幅が異常な場合はデフォルト値を返す
      if (!container || container.clientWidth === 0) return 100;

      const totalWidth = container.clientWidth;
      const config = STYLE_CONFIG.DESKTOP;
      const timeLabelWidth = config.TIME_LABEL_WIDTH;
      const dayCount = getDisplayDates().length;

      // 合計列数 = 部屋数 * 日数
      const totalColumns = getDisplayRoomColumns().length * dayCount;
      if (totalColumns === 0) return 100;

      // 利用可能幅 = 全体幅 - 時刻ラベル幅 - スクロールバー余白(10px) - 左オフセット(2px)
      const available = totalWidth - timeLabelWidth - 12;

      // 各カラムの幅 = 利用可能幅 / 列数 - gap(2px)
      // Math.floorで整数にして、隙間ができないようにする
      const width = Math.floor(available / totalColumns) - 2;

      // 極端に小さくならないよう制限
      return Math.max(width, 30);
    }

    function getDisplayDates() {
      const start = formatDateValue(currentDate);
      if (!isDesktopView()) return [start];
      return Array.from({ length: DESKTOP_DAY_COUNT }, (_, i) => addDaysToDateString_(start, i));
    }

    function getReservationsForDate(dateStr) {
      if (reservationsByDate && reservationsByDate[dateStr]) {
        return reservationsByDate[dateStr];
      }
      if (formatDateValue(currentDate) === dateStr) {
        return reservations;
      }
      return [];
    }

    function getAllDisplayReservations() {
      const byId = new Map();
      getDisplayDates().forEach(dateStr => {
        const allForDate = [
          ...getReservationsForDate(dateStr),
          ...getTteInterviewsForDate(dateStr)
        ];
        allForDate.forEach(item => {
          if (!item || !item.reservationId) return;
          byId.set(item.reservationId, item);
        });
      });
      return Array.from(byId.values());
    }

    function addDaysToDateString_(dateStr, days) {
      if (!dateStr) return '';
      const base = new Date(`${dateStr}T00:00:00`);
      if (isNaN(base.getTime())) return '';
      base.setDate(base.getDate() + Number(days || 0));
      return formatDateValue(base);
    }

    function formatDateShort(dateStr) {
      const d = new Date(dateStr + 'T00:00:00');
      if (isNaN(d.getTime())) return dateStr;
      const m = d.getMonth() + 1;
      const day = d.getDate();
      const dow = DAY_NAMES[d.getDay()];
      return `${m}/${day} (${dow})`;
    }

    function formatDuration(minutes) {
      if (!minutes) return '0分';
      const h = Math.floor(minutes / 60);
      const m = minutes % 60;
      if (h > 0) {
        if (m > 0) return `${h}時間${m}分`;
        return `${h}時間`;
      }
      return `${m}分`;
    }

    function formatDateDisplay(dateStr) {
      const d = new Date(dateStr + 'T00:00:00');
      const y = d.getFullYear();
      const m = d.getMonth() + 1;
      const day = d.getDate();
      const dow = DAY_NAMES[d.getDay()];
      return `${y}年${m}月${day}日 (${dow})`;
    }

    function formatRangeLabel(startDate) {
      const start = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
      const sm = start.getMonth() + 1;
      const sd = start.getDate();
      const sday = DAY_NAMES[start.getDay()];
      // デスクトップ版では単一日付表示
      return `${sm}月${sd}日 (${sday})`;
    }

    function updateDateDisplay() {
      const mobileDisplay = document.getElementById('selected-date-display');
      const desktopDisplay = document.getElementById('selected-date-display-desktop');
      const m = currentDate.getMonth() + 1;
      const d = currentDate.getDate();
      const day = DAY_NAMES[currentDate.getDay()];
      const mobileLabel = `${m}月${d}日 (${day})`;
      const desktopLabel = (isDesktopView() && DESKTOP_DAY_COUNT > 1)
        ? formatRangeLabel(currentDate)
        : mobileLabel;

      if (mobileDisplay) {
        mobileDisplay.textContent = mobileLabel;
      }
      if (desktopDisplay) {
        desktopDisplay.textContent = desktopLabel;
      }
    }

    function showLoading(message = '読み込み中...') {
      const msgEl = document.getElementById('loading-message');
      if (msgEl) msgEl.textContent = message;
      document.getElementById('loading-overlay').classList.remove('hidden');
    }
    function hideLoading() { document.getElementById('loading-overlay').classList.add('hidden'); }
    function hideInitialSkeleton() {
      const skeleton = document.getElementById('initial-skeleton');
      if (!skeleton) return;
      skeleton.style.transition = 'opacity 0.3s ease-out';
      skeleton.style.opacity = '0';
      setTimeout(() => skeleton.remove(), 300);
    }
    function showView(viewId) {
      document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
      document.getElementById(viewId).classList.add('active');
      updateFormActionBarHeight();
      updateKeyboardOffset();
      if (viewId === 'form-view') {
        scheduleActiveFieldVisibilityCheck();
      }
    }

    function updateFormActionBarHeight() {
      const formView = document.getElementById('form-view');
      const actionBar = document.getElementById('form-action-bar');
      if (!formView || !formView.classList.contains('active') || !actionBar) {
        document.documentElement.style.setProperty('--form-action-bar-height', '0px');
        return;
      }
      const actionBarHeight = Math.max(0, actionBar.offsetHeight || 0);
      document.documentElement.style.setProperty('--form-action-bar-height', `${actionBarHeight}px`);
    }

    function scheduleActiveFieldVisibilityCheck() {
      setTimeout(() => ensureActiveFieldVisible(document.activeElement), 50);
      setTimeout(() => ensureActiveFieldVisible(document.activeElement), 180);
    }

    function updateKeyboardOffset() {
      const formView = document.getElementById('form-view');
      if (!formView || !formView.classList.contains('active')) {
        updateFormActionBarHeight();
        document.documentElement.style.setProperty('--keyboard-offset', '0px');
        return;
      }
      updateFormActionBarHeight();
      if (!window.visualViewport) {
        document.documentElement.style.setProperty('--keyboard-offset', '0px');
        return;
      }
      const vv = window.visualViewport;
      const offset = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
      document.documentElement.style.setProperty('--keyboard-offset', `${offset}px`);
    }

    function ensureActiveFieldVisible(target) {
      if (!target) return;
      const formView = document.getElementById('form-view');
      if (!formView || !formView.classList.contains('active')) return;
      const container = formView.querySelector('.form-scroll');
      if (!container) return;
      if (!(target.matches('input, select, textarea') || target.closest('input, select, textarea'))) return;

      const vv = window.visualViewport;
      const actionBar = document.getElementById('form-action-bar');
      const actionBarHeight = actionBar ? actionBar.offsetHeight : 0;
      const viewportBottom = vv ? vv.height + vv.offsetTop : window.innerHeight;
      const visibleBottom = viewportBottom - actionBarHeight - 12;

      const rect = target.getBoundingClientRect();
      const delta = rect.bottom - visibleBottom;
      if (delta > 0) {
        container.scrollTop += delta + 8;
      } else if (rect.top < 12) {
        container.scrollTop += rect.top - 12;
      }
    }

    function scrollToCurrentTime() {
      const container = document.getElementById('scroll-container');
      const now = new Date();
      const currentHour = now.getHours();
      if (currentHour >= START_HOUR && currentHour <= END_HOUR) {
        const scrollTo = (currentHour - START_HOUR) * 4 * SLOT_HEIGHT;
        container.scrollTop = scrollTo - 100;
      }
    }

    function calculateEndTime(startTime, duration) {
      const [h, m] = startTime.split(':').map(Number);
      const endMinutes = h * 60 + m + duration;
      const endH = Math.floor(endMinutes / 60);
      const endM = endMinutes % 60;
      return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
    }

    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function normalizeGuestName(value) {
      if (value === null || value === undefined) return '';
      return String(value).trim();
    }

    function getReservationGuestName(reservation) {
      if (!reservation) return '';
      const guestName = normalizeGuestName(reservation.guestName);
      if (guestName) return guestName;
      return normalizeGuestName(reservation.visitorName);
    }

    function isFinePointerDevice_() {
      return !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);
    }

    function getDisplayReservationById_(reservationId) {
      const id = String(reservationId || '');
      if (!id) return null;
      const allReservations = getAllDisplayReservations();
      return allReservations.find(item => String(item && item.reservationId) === id) || null;
    }

    function getDragReservationDuration_(reservation) {
      return Number(reservation && reservation.duration)
        || Number(reservation && reservation.durationMinutes)
        || 60;
    }

    function isSameMoveTarget_(left, right) {
      if (!left || !right) return false;
      return String(left.roomId) === String(right.roomId)
        && String(left.date) === String(right.date)
        && normalizeTime(left.startTime) === normalizeTime(right.startTime);
    }

    function buildMoveTargetLabel_(target, duration) {
      if (!target) return '';
      const roomName = getRoomNameById_(target.roomId);
      const startTime = normalizeTime(target.startTime);
      const endTime = calculateEndTime(startTime, duration);
      return `${roomName} / ${formatDateDisplay(target.date)} / ${startTime} - ${endTime}`;
    }

    function getEventCardByReservationId_(reservationId) {
      const id = String(reservationId || '');
      if (!id) return null;
      const cards = document.querySelectorAll('.event-card[data-reservation-id]');
      for (const card of cards) {
        if ((card.dataset && card.dataset.reservationId) === id) {
          return card;
        }
      }
      return null;
    }

    function buildGhostReservationCard_(reservation, sourceCardEl) {
      const guestName = getReservationGuestName(reservation);
      const hasGuest = !!guestName;
      const colorClass = hasGuest ? 'res-guest' : 'res-no-guest';
      const meetingTitle = hasGuest ? truncateMeetingName(reservation.meetingName) : (reservation.meetingName || '');
      const titleClass = hasGuest ? 'event-card-title event-card-title-guest' : 'event-card-title';
      const guestDisplayName = truncateGuestName(guestName);
      const guestLine = guestDisplayName
        ? `<span class="event-card-guest">${escapeHtml(guestDisplayName)}</span>`
        : '';

      const ghost = document.createElement('div');
      ghost.className = `${colorClass} event-card event-card-ghost`;
      ghost.innerHTML = `
        <span class="${titleClass}">${escapeHtml(meetingTitle)}</span>
        <span class="event-card-meta">${escapeHtml(reservation.reserverName || '')}</span>
        ${guestLine}
      `;

      if (sourceCardEl) {
        const rect = sourceCardEl.getBoundingClientRect();
        if (rect.width > 0) ghost.style.width = `${Math.round(rect.width)}px`;
        if (rect.height > 0) ghost.style.height = `${Math.round(rect.height)}px`;
      } else {
        ghost.style.width = '160px';
      }

      return ghost;
    }

    function setGhostCardPosition_(clientX, clientY) {
      if (!dragState || !dragState.ghostEl) return;
      const ghostWidth = dragState.ghostEl.offsetWidth || 0;
      const ghostHeight = dragState.ghostEl.offsetHeight || 0;
      const offsetX = Number.isFinite(dragState.pointerOffsetX)
        ? dragState.pointerOffsetX
        : Math.round(ghostWidth / 2);
      const offsetY = Number.isFinite(dragState.pointerOffsetY)
        ? dragState.pointerOffsetY
        : Math.round(ghostHeight / 2);
      const x = Math.round(clientX - offsetX);
      const y = Math.round(clientY - offsetY);
      dragState.ghostEl.style.transform = `translate(${x}px, ${y}px)`;
    }

    function setHoveredTimelineCell_(nextCell) {
      if (!dragState) return;
      dragState.hoverCell = nextCell || null;
    }

    function removeDropPreview_() {
      if (!dragState) return;
      if (dragState.previewEl && dragState.previewEl.parentNode) {
        dragState.previewEl.parentNode.removeChild(dragState.previewEl);
      }
      dragState.previewEl = null;
      dragState.previewParent = null;
    }

    function setDropPreviewTarget_(moveTarget) {
      if (!dragState || !dragState.isActive) return;
      if (!moveTarget || !moveTarget.cell) {
        removeDropPreview_();
        return;
      }

      const columnEl = moveTarget.cell.closest('.room-column');
      if (!columnEl) {
        removeDropPreview_();
        return;
      }

      const duration = getDragReservationDuration_(dragState.reservation);
      const startMinutes = timeToMinutes(normalizeTime(moveTarget.startTime));
      const offsetMinutes = startMinutes - START_HOUR * 60;
      const top = (offsetMinutes / 15) * SLOT_HEIGHT;
      if (!Number.isFinite(top)) {
        removeDropPreview_();
        return;
      }

      const rawHeight = (duration / 15) * SLOT_HEIGHT;
      const maxHeight = Math.max(0, (columnEl.clientHeight || 0) - top);
      const height = Math.max(Math.min(rawHeight, maxHeight), SLOT_HEIGHT);

      if (!dragState.previewEl || dragState.previewParent !== columnEl) {
        removeDropPreview_();
        const preview = document.createElement('div');
        preview.className = 'drag-drop-preview';
        columnEl.appendChild(preview);
        dragState.previewEl = preview;
        dragState.previewParent = columnEl;
      }

      dragState.previewEl.style.top = `${Math.max(0, top)}px`;
      dragState.previewEl.style.height = `${height}px`;
    }

    function getMoveTargetFromCell_(cell) {
      if (!cell || !cell.dataset) return null;
      const roomId = cell.dataset.roomId;
      const date = cell.dataset.date;
      const startTime = cell.dataset.startTime;
      if (!roomId || !date || !startTime) return null;
      return {
        roomId: String(roomId),
        date: String(date),
        startTime: normalizeTime(startTime),
        cell
      };
    }

    function getMoveTargetFromPoint_(clientX, clientY) {
      const element = document.elementFromPoint(clientX, clientY);
      if (!element) return null;
      const cell = element.closest('.timeline-cell');
      if (!cell) return null;
      return getMoveTargetFromCell_(cell);
    }

    function getMoveTargetFromGhostTop_(clientX, clientY) {
      if (!dragState) return getMoveTargetFromPoint_(clientX, clientY);

      const ghostWidth = dragState.ghostEl ? (dragState.ghostEl.offsetWidth || 0) : 0;
      const ghostHeight = dragState.ghostEl ? (dragState.ghostEl.offsetHeight || 0) : 0;
      const offsetY = Number.isFinite(dragState.pointerOffsetY)
        ? dragState.pointerOffsetY
        : Math.round(ghostHeight / 2);

      const probeX = Math.max(0, Math.min(window.innerWidth - 1, Math.round(clientX)));
      const probeYRaw = clientY - offsetY + 2; // card top edge + 2px (inside the card)
      const probeY = Math.max(0, Math.min(window.innerHeight - 1, Math.round(probeYRaw)));

      return getMoveTargetFromPoint_(probeX, probeY);
    }

    function activateReservationDrag_() {
      if (!dragState || dragState.isActive) return;

      dragState.isActive = true;
      dragState.longPressTimer = null;

      const sourceCardEl = getEventCardByReservationId_(dragState.reservationId);
      dragState.sourceCardEl = sourceCardEl || null;
      if (dragState.sourceCardEl) {
        dragState.sourceCardEl.classList.add('event-card-drag-source');
      }

      const ghost = buildGhostReservationCard_(dragState.reservation, dragState.sourceCardEl);
      dragState.ghostEl = ghost;
      document.body.appendChild(ghost);
      document.body.classList.add('dragging-reservation');
      setGhostCardPosition_(dragState.lastClientX, dragState.lastClientY);

      const initialTarget = getMoveTargetFromGhostTop_(dragState.lastClientX, dragState.lastClientY);
      dragState.dropTarget = initialTarget
        ? { roomId: initialTarget.roomId, date: initialTarget.date, startTime: initialTarget.startTime }
        : null;
      setHoveredTimelineCell_(initialTarget ? initialTarget.cell : null);
      setDropPreviewTarget_(initialTarget);
      suppressDetailClickUntil = Date.now() + DETAIL_CLICK_SUPPRESS_MS;
    }

    function startReservationDrag(event, reservationId) {
      if (!isFinePointerDevice_()) return;
      if (!event || event.button !== 0) return;

      const reservationIdStr = String(reservationId || '');
      if (!reservationIdStr || reservationIdStr.startsWith('temp-')) return;

      const reservation = getDisplayReservationById_(reservationIdStr);
      if (!reservation || isReadOnlyReservation_(reservation)) return;

      const sourceCardEl = event.currentTarget && event.currentTarget.closest
        ? event.currentTarget.closest('.event-card')
        : null;
      const sourceRect = sourceCardEl ? sourceCardEl.getBoundingClientRect() : null;
      const pointerOffsetX = sourceRect
        ? Math.max(0, Math.min(sourceRect.width, event.clientX - sourceRect.left))
        : null;
      const pointerOffsetY = sourceRect
        ? Math.max(0, Math.min(sourceRect.height, event.clientY - sourceRect.top))
        : null;

      cleanupReservationDragState({ keepPendingMove: true });

      dragState = {
        reservationId: reservationIdStr,
        reservation,
        source: {
          roomId: String(reservation.roomId),
          date: String(reservation.date),
          startTime: normalizeTime(reservation.startTime)
        },
        isActive: false,
        longPressTimer: null,
        startClientX: event.clientX,
        startClientY: event.clientY,
        lastClientX: event.clientX,
        lastClientY: event.clientY,
        ghostEl: null,
        hoverCell: null,
        dropTarget: null,
        previewEl: null,
        previewParent: null,
        sourceCardEl: sourceCardEl || null,
        pointerOffsetX,
        pointerOffsetY
      };

      dragState.longPressTimer = setTimeout(() => {
        if (!dragState || dragState.reservationId !== reservationIdStr) return;
        activateReservationDrag_();
      }, LONG_PRESS_MS);

      document.addEventListener('mousemove', onReservationDragMouseMove_, { passive: false });
      document.addEventListener('mouseup', onReservationDragMouseUp_, { passive: false });
      document.addEventListener('keydown', onReservationDragKeyDown_);
      window.addEventListener('blur', onReservationDragWindowBlur_);
    }

    function onReservationDragMouseMove_(event) {
      if (!dragState) return;

      dragState.lastClientX = event.clientX;
      dragState.lastClientY = event.clientY;

      if (!dragState.isActive) {
        const moveX = Math.abs(event.clientX - dragState.startClientX);
        const moveY = Math.abs(event.clientY - dragState.startClientY);
        if (moveX > DRAG_CANCEL_DISTANCE_PX || moveY > DRAG_CANCEL_DISTANCE_PX) {
          cleanupReservationDragState({ keepPendingMove: true });
        }
        return;
      }

      event.preventDefault();
      setGhostCardPosition_(event.clientX, event.clientY);

      const moveTarget = getMoveTargetFromGhostTop_(event.clientX, event.clientY);
      if (moveTarget) {
        dragState.dropTarget = {
          roomId: moveTarget.roomId,
          date: moveTarget.date,
          startTime: moveTarget.startTime
        };
        setHoveredTimelineCell_(moveTarget.cell);
        setDropPreviewTarget_(moveTarget);
      } else {
        dragState.dropTarget = null;
        setHoveredTimelineCell_(null);
        setDropPreviewTarget_(null);
      }
    }

    function onReservationDragMouseUp_() {
      if (!dragState) return;

      const wasActive = !!dragState.isActive;
      const reservation = dragState.reservation;
      const source = dragState.source ? { ...dragState.source } : null;
      const dropTarget = dragState.dropTarget ? { ...dragState.dropTarget } : null;

      cleanupReservationDragState({ keepPendingMove: true });

      if (!wasActive || !reservation || !source || !dropTarget) {
        return;
      }
      if (isSameMoveTarget_(source, dropTarget)) {
        return;
      }

      suppressDetailClickUntil = Date.now() + DETAIL_CLICK_SUPPRESS_MS;
      pendingMoveState = {
        reservationId: String(reservation.reservationId || ''),
        reservation,
        from: source,
        to: dropTarget
      };
      openDragMoveDialog();
    }

    function onReservationDragKeyDown_(event) {
      if (event.key !== 'Escape' || !dragState) return;
      event.preventDefault();
      cleanupReservationDragState({ keepPendingMove: true });
    }

    function onReservationDragWindowBlur_() {
      cleanupReservationDragState({ keepPendingMove: true });
    }

    function cleanupReservationDragState(options = {}) {
      const keepPendingMove = !!options.keepPendingMove;

      document.removeEventListener('mousemove', onReservationDragMouseMove_);
      document.removeEventListener('mouseup', onReservationDragMouseUp_);
      document.removeEventListener('keydown', onReservationDragKeyDown_);
      window.removeEventListener('blur', onReservationDragWindowBlur_);

      if (dragState) {
        if (dragState.longPressTimer) {
          clearTimeout(dragState.longPressTimer);
        }
        if (dragState.hoverCell) {
          dragState.hoverCell.classList.remove('timeline-cell-drop-target');
        }
        removeDropPreview_();
        if (dragState.sourceCardEl) {
          dragState.sourceCardEl.classList.remove('event-card-drag-source');
        }
        if (dragState.ghostEl && dragState.ghostEl.parentNode) {
          dragState.ghostEl.parentNode.removeChild(dragState.ghostEl);
        }
      }

      document.body.classList.remove('dragging-reservation');
      dragState = null;

      if (!keepPendingMove) {
        pendingMoveState = null;
      }
    }

    function openDragMoveDialog() {
      if (!pendingMoveState) return;
      const dialog = document.getElementById('drag-move-dialog');
      if (!dialog) return;

      const duration = getDragReservationDuration_(pendingMoveState.reservation);
      const fromEl = document.getElementById('drag-move-from');
      const toEl = document.getElementById('drag-move-to');
      if (fromEl) {
        fromEl.textContent = buildMoveTargetLabel_(pendingMoveState.from, duration);
      }
      if (toEl) {
        toEl.textContent = buildMoveTargetLabel_(pendingMoveState.to, duration);
      }
      dialog.classList.remove('hidden');
    }

    function closeDragMoveDialog() {
      const dialog = document.getElementById('drag-move-dialog');
      if (dialog) {
        dialog.classList.add('hidden');
      }
      pendingMoveState = null;
    }

    async function confirmMoveReservation() {
      const move = pendingMoveState;
      if (!move) return;
      closeDragMoveDialog();

      const duration = getDragReservationDuration_(move.reservation);
      const guestName = getReservationGuestName(move.reservation);
      const startTime = normalizeTime(move.to.startTime);

      const conflict = checkLocalConflict(
        move.to.roomId,
        move.to.date,
        startTime,
        duration,
        move.reservationId
      );
      if (conflict) {
        alert('移動先の時間帯には既存予約があります。');
        return;
      }

      const data = {
        roomId: move.to.roomId,
        date: move.to.date,
        startTime,
        duration,
        durationMinutes: duration,
        meetingName: move.reservation.meetingName || '',
        reserverName: move.reservation.reserverName || '',
        guestName,
        visitorName: guestName,
        includeReservations: false
      };

      showLoading('予約を移動中...');
      try {
        await apiMutate('updateReservation', { id: move.reservationId, data });
        await loadReservationsOnly();
        renderTimeline();
      } catch (error) {
        await loadReservationsOnly();
        renderTimeline();
        alert(error.message || '予約の移動に失敗しました');
      } finally {
        hideLoading();
        cleanupReservationDragState({ keepPendingMove: true });
      }
    }

    function truncateMeetingName(value) {
      const meetingName = value === null || value === undefined ? '' : String(value).trim();
      if (!meetingName) return '';
      return meetingName.length > 8 ? `${meetingName.slice(0, 8)}...` : meetingName;
    }

    function truncateGuestName(value) {
      const guestName = normalizeGuestName(value);
      if (!guestName) return '';
      return guestName.length >= 15 ? `${guestName.slice(0, 15)}...` : guestName;
    }

    function ensureReservationsList_(dateStr) {
      if (!reservationsByDate || typeof reservationsByDate !== 'object') {
        reservationsByDate = {};
      }
      if (!reservationsByDate[dateStr]) {
        reservationsByDate[dateStr] = [];
      }
      return reservationsByDate[dateStr];
    }

    function findReservationInCache_(reservationId) {
      if (!reservationId || !reservationsByDate) return null;
      for (const [dateStr, list] of Object.entries(reservationsByDate)) {
        if (!Array.isArray(list)) continue;
        const idx = list.findIndex(r => r && r.reservationId === reservationId);
        if (idx !== -1) return { dateStr, list, idx };
      }
      return null;
    }

    function replaceReservationIdInCache_(oldId, newId) {
      const hit = findReservationInCache_(oldId);
      if (hit) {
        hit.list[hit.idx].reservationId = newId;
      }
    }

    function attachRipples() {
      document.addEventListener('pointerdown', (event) => {
        const target = event.target.closest('button, .md-field, .timeline-cell, .md-switch');
        if (!target || target.disabled) return;

        const computed = window.getComputedStyle(target);
        if (computed.position === 'static') {
          target.style.position = 'relative';
        }
        if (computed.overflow === 'visible') {
          target.style.overflow = 'hidden';
        }

        const rect = target.getBoundingClientRect();
        const ripple = document.createElement('span');
        ripple.className = 'ripple';
        const size = Math.max(rect.width, rect.height) * 2;
        ripple.style.width = ripple.style.height = `${size}px`;
        ripple.style.left = `${event.clientX - rect.left - size / 2}px`;
        ripple.style.top = `${event.clientY - rect.top - size / 2}px`;
        target.appendChild(ripple);
        ripple.addEventListener('animationend', () => ripple.remove());
      }, { passive: true });
    }

    // 時刻データを正規化（HH:MM形式に変換）
    function normalizeTime(timeValue) {
      if (!timeValue) return '00:00';
      // 既にHH:MM形式の場合
      if (typeof timeValue === 'string' && /^\d{1,2}:\d{2}$/.test(timeValue)) {
        const [h, m] = timeValue.split(':');
        return `${String(h).padStart(2, '0')}:${m}`;
      }
      // ISO形式やDate型の場合
      try {
        const d = new Date(timeValue);
        if (!isNaN(d.getTime())) {
          return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
        }
      } catch (e) { }
      return '00:00';
    }

    // フロントエンドでの重複チェック
    function checkLocalConflict(roomId, date, startTime, duration, excludeId = null) {
      const startMinutes = timeToMinutes(startTime);
      const endMinutes = startMinutes + duration;
      const targetReservations = getReservationsForDate(date);

      for (const r of targetReservations) {
        if (excludeId && r.reservationId === excludeId) continue;
        if (String(r.roomId) !== String(roomId)) continue;
        if (r.date !== date) continue;

        const rStart = timeToMinutes(normalizeTime(r.startTime));
        const rDuration = r.duration || r.durationMinutes || 60;
        const rEnd = rStart + rDuration;

        if (startMinutes < rEnd && endMinutes > rStart) {
          return r;
        }
      }
      return null;
    }

    // 時刻文字列を分に変換
    function timeToMinutes(timeStr) {
      if (!timeStr) return 0;
      const [h, m] = timeStr.split(':').map(Number);
      return h * 60 + m;
    }

    function getReservationRangeMinutes_(reservation) {
      const startMinutes = timeToMinutes(normalizeTime(reservation && reservation.startTime));
      const duration = Number(reservation && reservation.duration) || Number(reservation && reservation.durationMinutes) || 60;
      return {
        startMinutes,
        endMinutes: startMinutes + duration
      };
    }

    function isReservationOverlapping_(left, right) {
      const leftRange = getReservationRangeMinutes_(left);
      const rightRange = getReservationRangeMinutes_(right);
      return leftRange.startMinutes < rightRange.endMinutes && rightRange.startMinutes < leftRange.endMinutes;
    }

    function buildDayRoomEventBuckets_(dateStr) {
      const dayReservations = getReservationsForDate(dateStr) || [];
      const dayTteReservations = getTteInterviewsForDate(dateStr) || [];
      const buckets = {};

      rooms.forEach(room => {
        const roomIdKey = String(room.roomId);
        const roomReservations = dayReservations.filter(r => String(r.roomId) === roomIdKey);
        const roomTteReservations = dayTteReservations.filter(r => String(r.roomId) === roomIdKey);

        if (!TTE_CONFLICT_ROOM_ID_SET.has(roomIdKey)) {
          buckets[roomIdKey] = {
            mainEvents: [...roomReservations, ...roomTteReservations],
            displacedEvents: []
          };
          return;
        }

        if (!roomTteReservations.length) {
          buckets[roomIdKey] = {
            mainEvents: roomReservations,
            displacedEvents: []
          };
          return;
        }

        const displacedEvents = [];
        const regularEvents = [];

        roomReservations.forEach(reservation => {
          const overlapsWithTte = roomTteReservations.some(tte => isReservationOverlapping_(reservation, tte));
          if (overlapsWithTte) {
            displacedEvents.push(Object.assign({}, reservation, { displacedBy: 'tte' }));
          } else {
            regularEvents.push(reservation);
          }
        });

        buckets[roomIdKey] = {
          mainEvents: [...regularEvents, ...roomTteReservations],
          displacedEvents
        };
      });

      TTE_CONFLICT_ROOM_IDS.forEach(roomId => {
        const key = String(roomId);
        if (buckets[key]) return;
        const roomTteReservations = dayTteReservations.filter(r => String(r.roomId) === key);
        buckets[key] = {
          mainEvents: roomTteReservations,
          displacedEvents: []
        };
      });

      return buckets;
    }

    function getRenderedRoomEventsForColumn_(column, dayBuckets) {
      const roomKey = String(column.roomId);
      const bucket = dayBuckets[roomKey] || { mainEvents: [], displacedEvents: [] };
      return column.type === 'displaced' ? bucket.displacedEvents : bucket.mainEvents;
    }

    // ========================================
    // 日付ナビゲーション（楽観的UI更新）
    // ========================================

    /**
     * キャッシュからreservationsを同期的に更新してレンダリング
     * キャッシュがなければ空配列で描画（後でバックグラウンドフェッチで更新）
     */
    function renderWithCachedData() {
      const dates = getDisplayDates();
      // 表示対象の日付すべてについてキャッシュをチェック
      dates.forEach(dateStr => {
        if (!reservationsByDate[dateStr]) {
          reservationsByDate[dateStr] = []; // キャッシュがなければ空配列
        }
      });
      // 最初の日付の予約をreservationsにセット
      reservations = reservationsByDate[dates[0]] || [];
      renderTimeline();
    }

    /**
     * バックグラウンドでデータをフェッチして差分更新
     */


    async function changeDate(days) {
      // 1. 即座に日付を変更してUIを更新
      currentDate.setDate(currentDate.getDate() + days);
      updateDateDisplay();

      // 2. キャッシュがあれば即座に描画、なければ空で描画
      renderWithCachedData();

      // 3. バックグラウンドで最新データを取得して再描画
      fetchAndRenderInBackground();
    }

    function changeDatePage(direction) {
      const step = isDesktopView() ? DESKTOP_DAY_COUNT : 1;
      changeDate(direction * step);
    }

    async function goToToday() {
      // 1. 即座に今日に移動してUIを更新
      currentDate = new Date();
      updateDateDisplay();

      // 2. キャッシュがあれば即座に描画
      renderWithCachedData();

      // 3. バックグラウンドで最新データを取得
      fetchAndRenderInBackground();
    }

    function openDatePicker() {
      const input = document.getElementById('date-picker-input');
      input.value = formatDateValue(currentDate);
      document.getElementById('date-picker-modal').classList.remove('hidden');
    }

    function closeDatePicker() {
      document.getElementById('date-picker-modal').classList.add('hidden');
    }

    async function applyDateSelection() {
      const input = document.getElementById('date-picker-input');

      // 1. 即座に日付を変更してモーダルを閉じる
      currentDate = new Date(input.value + 'T00:00:00');
      closeDatePicker();
      updateDateDisplay();

      // 2. キャッシュがあれば即座に描画
      renderWithCachedData();

      // 3. バックグラウンドで最新データを取得
      fetchAndRenderInBackground();
    }

    // ========================================
    // タイムライン描画
    // ========================================
    function renderTimeline() {
      cleanupReservationDragState({ keepPendingMove: true });
      // レイアウト設定の適用（スペーサーと左カラムの幅）
      const config = isDesktopView() ? STYLE_CONFIG.DESKTOP : STYLE_CONFIG.MOBILE;
      const timeLabelWidth = config.TIME_LABEL_WIDTH;

      const headerSpacer = document.getElementById('header-time-spacer');
      if (headerSpacer) {
        headerSpacer.style.width = `${timeLabelWidth}px`;
      }

      const timeLabels = document.getElementById('time-labels');
      if (timeLabels) {
        timeLabels.style.width = `${timeLabelWidth}px`;
      }

      renderDayHeaders();
      renderRoomHeaders();
      renderTimeLabels();
      renderRoomColumns();
    }

    function renderDayHeaders() {
      const container = document.getElementById('day-headers');
      if (!container) return;
      const dates = getDisplayDates();
      if (dates.length <= 1) {
        container.innerHTML = '';
        return;
      }

      const config = isDesktopView() ? STYLE_CONFIG.DESKTOP : STYLE_CONFIG.MOBILE;
      const roomWidth = getResponsiveRoomWidth();
      const gapWidth = 2;
      const displayColumns = getDisplayRoomColumns();
      const dayWidth = displayColumns.length * roomWidth + Math.max(displayColumns.length - 1, 0) * gapWidth;
      const timeLabelWidth = config.TIME_LABEL_WIDTH;
      const leftOffset = timeLabelWidth + 2;
      let html = `<div class="${config.TIME_LABEL_CLASS} shrink-0"></div>`;
      dates.forEach((dateStr, idx) => {
        const dividerClass = idx > 0 ? ' day-divider day-divider-header' : '';
        html += `
        <div class="day-header-label flex items-center justify-center text-[10px] font-semibold text-on-surface-variant lg:text-[20px] lg:font-bold${dividerClass}" style="width: ${dayWidth}px;">
          ${formatDateShort(dateStr)}
        </div>`;
      });
      const lineHtml = dates.map((_, idx) => {
        if (idx === 0) return '';
        const left = leftOffset + idx * dayWidth;
        return `<div class="day-divider-line day-divider-header-line" style="left: ${left}px;"></div>`;
      }).join('');
      container.innerHTML = html + lineHtml;
    }

    function renderRoomHeaders() {
      const container = document.getElementById('room-headers');
      if (!container) return;
      const dates = getDisplayDates();
      const config = isDesktopView() ? STYLE_CONFIG.DESKTOP : STYLE_CONFIG.MOBILE;
      const roomWidth = getResponsiveRoomWidth();
      const gapWidth = 2;
      const displayColumns = getDisplayRoomColumns();
      const dayWidth = displayColumns.length * roomWidth + Math.max(displayColumns.length - 1, 0) * gapWidth;
      const leftOffset = 2;

      const headerHtml = dates.map((_, dateIdx) => {
        const dividerClass = dateIdx > 0 ? ' day-divider day-divider-header' : '';
        const roomsHtml = displayColumns.map(column => {
          const isDisplaced = column.type === 'displaced';
          const theme = isDisplaced
            ? { bg: 'rgba(170, 170, 170, 0.55)', text: '#374151', border: '#9ca3af' }
            : getRoomHeaderTheme_(column.roomId);
          const style = [
            `width: ${roomWidth}px`,
            theme && theme.bg ? `background: ${theme.bg}` : '',
            theme && theme.border ? `border-color: ${theme.border}` : ''
          ].filter(Boolean).join('; ');
          const labelStyle = theme && theme.text ? `color: ${theme.text};` : '';
          const titleText = isDisplaced ? column.roomName : `${column.roomName} (${column.roomId})`;
          return `
    <div class="room-header-item ${config.HEADER_HEIGHT_CLASS} p-0.5 rounded-md shadow-sm border flex items-center justify-center shrink-0" style="${style}" title="${titleText}">
      <span class="room-header-label font-bold ${config.HEADER_FONT_SIZE} w-full text-center leading-[1.1] break-words" style="${labelStyle}">${column.roomName}</span>
    </div>
  `;
        }).join('');
        return `
    <div class="day-room-header-group flex gap-0.5${dividerClass}">
      ${roomsHtml}
    </div>`;
      }).join('');
      const lineHtml = dates.map((_, idx) => {
        if (idx === 0) return '';
        const left = leftOffset + idx * dayWidth;
        return `<div class="day-divider-line day-divider-header-line" style="left: ${left}px;"></div>`;
      }).join('');
      container.innerHTML = headerHtml + lineHtml;
    }

    function renderTimeLabels() {
      const container = document.getElementById('time-labels');
      let html = '';
      for (let hour = START_HOUR; hour < END_HOUR; hour++) {
        for (let min = 0; min < 60; min += 15) {
          const isHour = min === 0;
          const isHalfHour = min === 30;
          let label = '';
          let fontClass = '';

          if (isHour) {
            label = `${hour}:00`;
            fontClass = 'font-bold text-primary';
          } else if (isHalfHour) {
            label = `${hour}:30`;
            fontClass = 'font-normal opacity-70';
          }

          html += `<div class="h-[${SLOT_HEIGHT}px] pr-2 flex items-start justify-end"><span class="${fontClass}">${label}</span></div>`;
        }
      }
      html += `<span class="absolute bottom-0 right-2 font-bold text-primary">${END_HOUR}:00</span>`;
      container.innerHTML = html;
    }

    function renderRoomColumns() {
      const container = document.getElementById('room-columns');
      if (!container) return;
      const totalSlots = (END_HOUR - START_HOUR) * 4;
      const totalHeight = totalSlots * SLOT_HEIGHT;
      const dates = getDisplayDates();
      const displayColumns = getDisplayRoomColumns();
      const roomWidth = getResponsiveRoomWidth();
      const gapWidth = 2;
      const dayWidth = displayColumns.length * roomWidth + Math.max(displayColumns.length - 1, 0) * gapWidth;
      const leftOffset = 2;

      let gridHtml = '<div class="absolute inset-0 z-0 pointer-events-none flex flex-col w-full">';
      for (let i = 0; i < totalSlots; i++) {
        // 1時間の終わり（xx:45-xx+1:00）のスロットの下に太線を引く
        const isHourEnd = (i % 4 === 3);
        // 30分の位置（xx:15-xx:30のスロットの下）
        const isHalfHour = (i % 4 === 1);

        let borderClass;
        if (isHourEnd) {
          // 1時間ごと: 太線(2px) + 青の薄い色
          borderClass = 'border-b-2 border-primary/30';
        } else if (isHalfHour) {
          // 30分ごと: 少し太め + グレー
          borderClass = 'border-b border-slate-300';
        } else {
          // 15分/45分: 細い線
          borderClass = 'border-b border-slate-200';
        }

        gridHtml += `<div class="${borderClass} h-[${SLOT_HEIGHT}px] w-full ${i === 0 ? 'border-t-2 border-primary/30' : ''}"></div>`;
      }
      const lineHtml = dates.map((_, idx) => {
        if (idx === 0) return '';
        const left = leftOffset + idx * dayWidth;
        return `<div class="day-divider-line" style="left: ${left}px;"></div>`;
      }).join('');
      gridHtml += lineHtml;
      gridHtml += '</div>';

      const now = new Date();
      const nowMinutes = now.getHours() * 60 + now.getMinutes();
      const startMinutes = START_HOUR * 60;
      const nowOffset = (nowMinutes - startMinutes) / 15 * SLOT_HEIGHT;
      const todayStr = formatDateValue(now);

      const dayGroupsHtml = dates.map((dateStr, dateIdx) => {
        const dividerClass = dateIdx > 0 ? ' day-divider' : '';
        let currentTimeHtml = '';
        if (nowMinutes >= startMinutes && nowMinutes <= END_HOUR * 60 + 60 && dateStr === todayStr) {
          currentTimeHtml = `
      <div class="absolute z-10 pointer-events-none flex items-center" style="top: ${nowOffset}px; left: -8px; width: calc(100% + 16px);">
        <div class="w-3 h-3 rounded-full border-2 border-white shadow-md bg-[#EDC0C1]"></div>
        <div class="h-[1.5px] flex-1 opacity-80 shadow-sm bg-[#EDC0C1]"></div>
      </div>
    `;
        }

        const dayBuckets = buildDayRoomEventBuckets_(dateStr);
        const roomColumns = displayColumns.map(column => {
          const slotCells = column.type === 'displaced'
            ? ''
            : Array.from({ length: totalSlots }, (_, i) => {
              const slotMinutes = START_HOUR * 60 + i * 15;
              const hour = Math.floor(slotMinutes / 60);
              const min = slotMinutes % 60;
              const timeStr = `${String(hour).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
              return `
        <div class="timeline-cell"
             data-room-id="${column.roomId}"
             data-date="${dateStr}"
             data-start-time="${timeStr}"
             style="top: ${i * SLOT_HEIGHT}px; height: ${SLOT_HEIGHT}px;"
             onclick="handleCellClick(event, ${column.roomId}, '${timeStr}', '${dateStr}')">
        </div>
      `;
            }).join('');
          const roomEvents = getRenderedRoomEventsForColumn_(column, dayBuckets);
          const laidOut = layoutRoomEvents(roomEvents);
          const reservationBlocks = laidOut.map(event => {
            const startTimeNorm = normalizeTime(event.startTime);
            const [h, m] = startTimeNorm.split(':').map(Number);
            const startMin = h * 60 + m;
            const offsetMin = startMin - START_HOUR * 60;
            const top = offsetMin / 15 * SLOT_HEIGHT;
            const height = (event.duration || event.durationMinutes || 60) / 15 * SLOT_HEIGHT;
            const widthPercent = 100 / event.columnCount;
            const leftPercent = event.columnIndex * widthPercent;
            const guestName = getReservationGuestName(event);
            const hasGuest = !!guestName;
            const isDisplacedEvent = column.type === 'displaced' || event.displacedBy === 'tte';
            const colorClass = isDisplacedEvent
              ? 'res-displaced'
              : (hasGuest ? 'res-guest' : 'res-no-guest');
            const isPending = event.reservationId && event.reservationId.startsWith('temp-');
            const pendingClass = isPending ? 'pending' : '';
            const meetingTitle = hasGuest ? truncateMeetingName(event.meetingName) : (event.meetingName || '');
            const titleClass = hasGuest ? 'event-card-title event-card-title-guest' : 'event-card-title';
            const guestDisplayName = truncateGuestName(guestName);
            const guestLine = guestName ? `<span class="event-card-guest">${escapeHtml(guestDisplayName)}</span>` : '';
            const reservationIdJson = JSON.stringify(String(event.reservationId || ''));
            const reservationIdAttr = escapeHtml(String(event.reservationId || ''));
            return `
        <div class="absolute z-10 event-block" style="top: ${top}px; height: ${height}px; left: calc(${leftPercent}% + 1px); width: calc(${widthPercent}% - 2px);">
          <div onclick='event.stopPropagation(); showReservationDetail(${reservationIdJson})' 
               onmousedown='startReservationDrag(event, ${reservationIdJson})'
               data-reservation-id="${reservationIdAttr}"
               class="${colorClass} event-card ${pendingClass} cursor-pointer active:scale-[0.98]" 
               style="height: 100%; width: 100%; z-index: ${20 + event.columnIndex}; position: relative;">
            <span class="${titleClass}">${escapeHtml(meetingTitle)}</span>
            <span class="event-card-meta">${escapeHtml(event.reserverName || '')}</span>
            ${guestLine}
          </div>
        </div>
      `;
          }).join('');
          const columnClass = column.type === 'displaced'
            ? 'border-r border-slate-300/80'
            : 'border-r border-slate-300/60';
          const columnBgStyle = column.type === 'displaced'
            ? 'background-color: rgba(170, 170, 170, 0.55);'
            : '';
          return `
      <div class="room-column relative shrink-0 ${columnClass}" style="height: ${totalHeight}px; width: ${roomWidth}px; ${columnBgStyle}">
        ${slotCells}
        ${reservationBlocks}
      </div>`;
        }).join('');

        const groupHtml = `
      <div class="day-group relative flex gap-0.5${dividerClass}" style="height: ${totalHeight}px;">
        ${currentTimeHtml}
        ${roomColumns}
      </div>`;
        return groupHtml;
      }).join('');

      container.innerHTML = gridHtml + dayGroupsHtml;
    }

    function layoutRoomEvents(events) {
      if (!events || !events.length) {
        return [];
      }
      const decorated = events.map(res => {
        const startTimeNorm = normalizeTime(res.startTime);
        const [h, m] = startTimeNorm.split(':').map(Number);
        const startMin = h * 60 + m;
        const duration = Number(res.duration) || Number(res.durationMinutes) || 60;
        return Object.assign({}, res, { startMin, endMin: startMin + duration, duration });
      }).sort((a, b) => a.startMin - b.startMin);

      const columns = [];
      decorated.forEach(event => {
        let placed = false;
        for (let i = 0; i < columns.length; i++) {
          const column = columns[i];
          const lastEvent = column[column.length - 1];
          if (!lastEvent || lastEvent.endMin <= event.startMin) {
            column.push(event);
            event.columnIndex = i;
            placed = true;
            break;
          }
        }
        if (!placed) {
          event.columnIndex = columns.length;
          columns.push([event]);
        }
      });

      const columnCount = Math.max(columns.length, 1);
      decorated.forEach(event => {
        event.columnCount = columnCount;
      });

      return decorated;
    }

    // ========================================
    // ========================================
    // ========================================
    function isReadOnlyReservation_(reservation) {
      return !!(reservation && (reservation.readOnly || reservation.source === 'tte'));
    }

    function showReservationDetail(reservationId) {
      if (Date.now() < suppressDetailClickUntil) return;
      if (dragState && dragState.isActive) return;
      const allReservations = getAllDisplayReservations();
      currentReservation = allReservations.find(r => String(r.reservationId) === String(reservationId));
      if (!currentReservation) return;
      const room = rooms.find(r => String(r.roomId) === String(currentReservation.roomId));
      const isReadOnly = isReadOnlyReservation_(currentReservation);

      document.getElementById('detail-title').textContent = currentReservation.meetingName;
      const startTimeNorm = normalizeTime(currentReservation.startTime);
      const duration = currentReservation.duration || currentReservation.durationMinutes || 60;
      document.getElementById('detail-time').textContent = `${startTimeNorm} - ${calculateEndTime(startTimeNorm, duration)}`;
      document.getElementById('detail-duration').textContent = `(${formatDuration(duration)})`;
      document.getElementById('detail-date').textContent = formatDateDisplay(currentReservation.date);
      document.getElementById('detail-room').textContent = room ? room.roomName : '不明';
      document.getElementById('detail-reserver').textContent = currentReservation.reserverName;

      const guestSection = document.getElementById('detail-guest-section');
      const guestName = getReservationGuestName(currentReservation);
      if (guestName) {
        document.getElementById('detail-guest').textContent = guestName;
        guestSection.classList.remove('hidden');
      } else {
        guestSection.classList.add('hidden');
      }

      const statusText = document.getElementById('detail-status-text');
      if (statusText) {
        statusText.textContent = isReadOnly ? '表示専用' : '予約確定';
      }

      const readOnlyNote = document.getElementById('detail-readonly-note');
      if (readOnlyNote) {
        if (isReadOnly) {
          readOnlyNote.textContent = 'TTE予約は表示専用です。編集・削除はできません。';
          readOnlyNote.classList.remove('hidden');
        } else {
          readOnlyNote.textContent = '';
          readOnlyNote.classList.add('hidden');
        }
      }

      const editButton = document.getElementById('detail-edit-button');
      if (editButton) {
        editButton.classList.toggle('hidden', isReadOnly);
      }
      const deleteButton = document.getElementById('detail-delete-button');
      if (deleteButton) {
        deleteButton.classList.toggle('hidden', isReadOnly);
      }

      document.getElementById('detail-popup').classList.remove('hidden');
    }

    function closeDetailPopup() {
      document.getElementById('detail-popup').classList.add('hidden');
      currentReservation = null;
    }

    function openRecurrenceDeleteDialog() {
      const dialog = document.getElementById('recurrence-delete-dialog');
      if (!dialog) return;
      const radio = dialog.querySelector('input[name="recurrence-delete-scope"][value="single"]');
      if (radio) {
        radio.checked = true;
      }
      dialog.classList.remove('hidden');
    }

    function closeRecurrenceDeleteDialog() {
      const dialog = document.getElementById('recurrence-delete-dialog');
      if (dialog) {
        dialog.classList.add('hidden');
      }
    }

    async function confirmRecurrenceDelete() {
      const dialog = document.getElementById('recurrence-delete-dialog');
      const selected = dialog
        ? dialog.querySelector('input[name="recurrence-delete-scope"]:checked')
        : null;
      const deleteType = selected ? selected.value : 'single';
      closeRecurrenceDeleteDialog();
      await executeDeleteReservation(deleteType);
    }

    // ========================================
    // 新規予約・編集フォーム
    // ========================================
    function handleCellClick(event, roomId, startTime, dateStr) {
      event.stopPropagation();
      openNewReservation(startTime, roomId, dateStr);
    }

    function openNewReservation(startTime = '10:00', roomId = null, dateStr = null) {
      editingReservationId = null;
      document.getElementById('form-title').textContent = '新規予約';

      const targetRoom = roomId ? rooms.find(r => String(r.roomId) === String(roomId)) : rooms[0];
      renderRoomOptions(targetRoom ? targetRoom.roomId : null);
      const selectedDate = dateStr || formatDateValue(currentDate);
      document.getElementById('form-date').value = selectedDate;
      document.getElementById('form-start-time').value = startTime;
      document.getElementById('form-duration').value = '60';
      document.getElementById('form-meeting-name').value = '';
      document.getElementById('form-reserver-name').value = '';
      document.getElementById('form-guest-name').value = '';
      resetRecurrenceUI();

      showView('form-view');
    }

    function editReservation() {
      if (!currentReservation) return;
      if (isReadOnlyReservation_(currentReservation)) {
        return;
      }
      editingReservationId = currentReservation.reservationId;
      document.getElementById('form-title').textContent = '予約を編集';

      const room = rooms.find(r => String(r.roomId) === String(currentReservation.roomId));
      renderRoomOptions(currentReservation.roomId, room ? room.roomName : (currentReservation.roomName || ''));
      document.getElementById('form-date').value = currentReservation.date;
      document.getElementById('form-start-time').value = normalizeTime(currentReservation.startTime);
      document.getElementById('form-duration').value = String(currentReservation.duration);
      document.getElementById('form-meeting-name').value = currentReservation.meetingName;
      document.getElementById('form-reserver-name').value = currentReservation.reserverName;
      document.getElementById('form-guest-name').value = getReservationGuestName(currentReservation);
      resetRecurrenceUI();

      closeDetailPopup();
      showView('form-view');
    }

    function closeFormView() {
      showView('timeline-view');
      editingReservationId = null;
    }

    // ========================================
    // 保存・削除
    // ========================================
    async function saveReservation() {
      const editingId = editingReservationId;
      const meetingName = document.getElementById('form-meeting-name').value.trim();
      const reserverName = document.getElementById('form-reserver-name').value.trim();
      const guestName = document.getElementById('form-guest-name').value.trim();
      const recurrenceToggle = document.getElementById('recurrence-toggle');
      const recurrenceEnabled = recurrenceToggle && recurrenceToggle.checked;

      if (!meetingName) { alert('会議名を入力してください'); return; }
      if (!reserverName) { alert('予約者名を入力してください'); return; }
      if (meetingName.length > 20) { alert('会議名は20文字以内で入力してください'); return; }
      if (recurrenceEnabled && editingId) {
        alert('繰り返し予約は新規作成時のみ対応しています');
        return;
      }

      const data = {
        roomId: document.getElementById('form-room-id').value,
        date: document.getElementById('form-date').value,
        startTime: document.getElementById('form-start-time').value,
        duration: parseInt(document.getElementById('form-duration').value),
        durationMinutes: parseInt(document.getElementById('form-duration').value),
        meetingName,
        reserverName,
        guestName,
        visitorName: guestName,
        includeReservations: false  // レスポンスに全予約を含めない（高速化）
      };

      console.log('Sending data:', data); // デバッグ用

      if (!data.roomId) { alert('会議室を選択してください'); return; }
      if (!data.date) { alert('日付が無効です。再入力してください。'); return; }

      // フロントエンドでの重複チェック（楽観的UI用）
      if (!editingId) {
        const conflict = checkLocalConflict(data.roomId, data.date, data.startTime, data.duration, null);
        if (conflict) {
          alert(`この時間帯には既に「${conflict.meetingName}」(${conflict.reserverName}) の予約があります。\n別の時間帯を選択してください。`);
          return;
        }
      } else {
        // 編集時は自分自身を除いてチェック
        const conflict = checkLocalConflict(data.roomId, data.date, data.startTime, data.duration, editingId);
        if (conflict) {
          alert(`この時間帯には既に「${conflict.meetingName}」(${conflict.reserverName}) の予約があります。\n別の時間帯を選択してください。`);
          return;
        }
      }

      let recurrence = null;
      if (recurrenceEnabled) {
        const frequency = document.getElementById('recurrence-frequency').value;
        const untilDate = document.getElementById('recurrence-until').value;
        if (!frequency) { alert('繰り返しを選択してください'); return; }
        if (!untilDate) { alert('終了日を入力してください'); return; }
        if (data.date && untilDate < data.date) { alert('終了日は開始日以降を指定してください'); return; }
        recurrence = { frequency, untilDate };
      }

      // 楽観的UI更新
      const isNewSingleReservation = !editingId && !recurrenceEnabled;
      const isEditSingleReservation = editingId && !recurrenceEnabled;
      let tempId = null;
      let originalReservation = null;
      let originalReservationInfo = null;

      if (isNewSingleReservation) {
        // 新規作成：仮予約を作成してローカルに追加
        tempId = 'temp-' + Date.now();
        const tempReservation = {
          reservationId: tempId,
          roomId: data.roomId,
          date: data.date,
          startTime: data.startTime,
          duration: data.duration,
          durationMinutes: data.duration,
          meetingName: data.meetingName,
          reserverName: data.reserverName,
          guestName: data.guestName,
          visitorName: data.guestName
        };

        const targetList = ensureReservationsList_(data.date);
        targetList.push(tempReservation);
        if (formatDateValue(currentDate) === data.date) {
          reservations = targetList;
        }

        // 即座にフォームを閉じてタイムラインに戻る
        closeFormView();
        renderTimeline();

      } else if (isEditSingleReservation) {
        // 編集：既存の予約を即座に更新
        const hit = findReservationInCache_(editingId);
        if (hit) {
          originalReservation = { ...hit.list[hit.idx] };  // 元の状態を保存
          originalReservationInfo = { dateStr: hit.dateStr, idx: hit.idx };
          hit.list[hit.idx] = {
            ...hit.list[hit.idx],
            roomId: data.roomId,
            date: data.date,
            startTime: data.startTime,
            duration: data.duration,
            durationMinutes: data.duration,
            meetingName: data.meetingName,
            reserverName: data.reserverName,
            guestName: data.guestName,
            visitorName: data.guestName
          };
          if (formatDateValue(currentDate) === data.date) {
            reservations = hit.list;
          }
        }

        // 即座にフォームを閉じてタイムラインに戻る
        closeFormView();
        renderTimeline();

      } else {
        // 繰り返し予約の場合は従来通りローディング表示
        showLoading('保存中...');
      }

      try {
        let result = null;
        if (editingId) {
          result = await apiMutate('updateReservation', { id: editingId, data });
        } else if (recurrenceEnabled) {
          result = await apiMutate('createRecurringReservations', { data, recurrence });
        } else {
          result = await apiMutate('createReservation', { data });
        }
        console.log('API result:', result); // デバッグログ

        if (isNewSingleReservation) {
          // 新規作成成功時：仮IDを本物のIDに置換
          if (result && result.reservationId) {
            replaceReservationIdInCache_(tempId, result.reservationId);
            // HTMLのonclickにも反映するために再描画
            renderTimeline();
          }
          // 新規作成成功後、サーバーから最新データを取得して確実に同期
          await loadReservationsOnly();
          renderTimeline();
        } else if (isEditSingleReservation) {
          // 編集成功後、サーバーから最新データを取得して確実に同期
          await loadReservationsOnly();
          renderTimeline();
        } else {
          // 繰り返し予約の場合は再読み込み
          await loadReservationsOnly();
          renderTimeline();
          closeFormView();
          hideLoading();
        }

        if (result && result.errors && result.errors.length) {
          const failedDates = result.errors.map(e => e.date).filter(Boolean);
          const failedMessage = failedDates.length > 0
            ? `\n失敗日: ${failedDates.join(', ')}`
            : '';
          alert(`一部の予約の保存に失敗しました。${result.errors.length}件。${failedMessage}`);
        }
      } catch (error) {
        console.error('Save error:', error); // デバッグログ

        if (isNewSingleReservation && tempId) {
          // エラー時：仮予約を削除してタイムラインを再描画
          const list = ensureReservationsList_(data.date);
          const idx = list.findIndex(r => r.reservationId === tempId);
          if (idx !== -1) {
            list.splice(idx, 1);
          }
          if (formatDateValue(currentDate) === data.date) {
            reservations = list;
          }
          renderTimeline();
        } else if (isEditSingleReservation && originalReservation && originalReservationInfo) {
          // エラー時：編集前の状態に戻す
          const list = ensureReservationsList_(originalReservationInfo.dateStr);
          list[originalReservationInfo.idx] = originalReservation;
          if (formatDateValue(currentDate) === originalReservation.date) {
            reservations = list;
          }
          renderTimeline();
        } else {
          hideLoading();
        }

        alert(error.message || '保存に失敗しました');
      }
    }

    async function executeDeleteReservation(deleteType) {
      if (!currentReservation) return;
      if (isReadOnlyReservation_(currentReservation)) return;

      // 仮ID（楽観的UI更新中）の場合は削除不可
      if (currentReservation.reservationId && currentReservation.reservationId.startsWith('temp-')) {
        alert('この予約はまだサーバーに保存されていません。しばらく待ってから再度お試しください。');
        return;
      }

      const reservationToDelete = { ...currentReservation };
      const reservationId = reservationToDelete.reservationId;
      const recurringEventId = reservationToDelete.recurringEventId || '';
      const targetDate = reservationToDelete.date;

      // 楽観的UI更新：削除対象を特定して即座に画面から削除
      let deletedReservations = [];
      const targetDateStr = formatDateValue(currentDate);

      if (deleteType === 'single') {
        // 単体削除
        deletedReservations = reservations.filter(r => r.reservationId === reservationId);
        reservations = reservations.filter(r => r.reservationId !== reservationId);
        // reservationsByDateキャッシュも更新
        if (reservationsByDate && reservationsByDate[targetDateStr]) {
          reservationsByDate[targetDateStr] = reservationsByDate[targetDateStr].filter(r => r.reservationId !== reservationId);
        }
      } else if (deleteType === 'all' && recurringEventId) {
        // すべて削除：同じrecurringEventIdを持つ予約をすべて削除
        deletedReservations = reservations.filter(r => r.recurringEventId === recurringEventId);
        reservations = reservations.filter(r => r.recurringEventId !== recurringEventId);
        // reservationsByDateキャッシュも更新
        for (const date in reservationsByDate) {
          reservationsByDate[date] = reservationsByDate[date].filter(r => r.recurringEventId !== recurringEventId);
        }
      } else if (deleteType === 'following' && recurringEventId && targetDate) {
        // 以降すべて削除：同じrecurringEventIdで、targetDate以降の予約を削除
        deletedReservations = reservations.filter(r => {
          if (r.recurringEventId !== recurringEventId) return false;
          return r.date >= targetDate;
        });
        reservations = reservations.filter(r => {
          if (r.recurringEventId !== recurringEventId) return true;
          return r.date < targetDate;
        });
        // reservationsByDateキャッシュも更新
        for (const date in reservationsByDate) {
          reservationsByDate[date] = reservationsByDate[date].filter(r => {
            if (r.recurringEventId !== recurringEventId) return true;
            return r.date < targetDate;
          });
        }
      }

      // 一括削除（all/following）の場合はローディング表示
      const isBulkDelete = deleteType === 'all' || deleteType === 'following';
      if (isBulkDelete) {
        showLoading('削除中...');
      }

      // 即座に画面を更新
      closeDetailPopup();
      renderTimeline();

      try {
        console.log('Deleting reservation:', reservationId, 'type:', deleteType); // デバッグログ
        const result = await apiMutate('deleteReservation', {
          id: reservationId,
          deleteType: deleteType,
          recurringEventId: recurringEventId,
          targetDate: targetDate,
          includeReservations: false  // 楽観的更新なので不要
        });

        // 成功後、サーバーから最新データを取得して確実に同期
        await loadReservationsOnly();
        renderTimeline();
        if (isBulkDelete) hideLoading();
        console.log('Delete success:', result);

      } catch (error) {
        console.error('Delete error:', error); // デバッグログ

        // 楽観的更新を元に戻す
        if (deletedReservations.length > 0) {
          reservations = [...reservations, ...deletedReservations];
          renderTimeline();
        }

        // Reservation not found の場合、キャッシュが古い可能性があるので再読み込み
        if (error.message && error.message.includes('not found')) {
          await loadReservationsOnly();
          renderTimeline();
          alert('予約を削除しました。画面を更新しました。');
        } else {
          alert(error.message || '削除に失敗しました');
        }
        if (isBulkDelete) hideLoading();
      }
    }

    async function deleteCurrentReservation() {
      if (!currentReservation) return;
      if (isReadOnlyReservation_(currentReservation)) return;
      // Google Sitesのiframeではconfirm()が使えないため、直接削除
      if (currentReservation.recurringEventId) {
        openRecurrenceDeleteDialog();
        return;
      }
      await executeDeleteReservation('single');
    }
  </script>

</body>

</html>