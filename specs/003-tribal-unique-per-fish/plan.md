ios/ or android/
# Implementation Plan: Tribal classification unique per fish

**Branch**: `003-tribal-unique-per-fish` | **Date**: 2025-11-03 | **Spec**: specs/003-tribal-unique-per-fish/spec.md
**Input**: Feature specification from `/specs/003-tribal-unique-per-fish/spec.md`

## Summary

將「同一魚 × 同一部落」強制唯一：固定六個部落清單，禁止重複新增，分歧意見改記備註。以 TDD 驅動：先寫 Feature 測試（API 驗證唯一性與 UI 資訊），再加上資料層唯一索引與驗證，最後重構既有程式碼以統一使用枚舉清單與驗證規則。

## Technical Context

**Language/Version**: PHP 8.x（Laravel）  
**Primary Dependencies**: Laravel Eloquent、Validation  
**Storage**: PostgreSQL  
**Testing**: Pest（後端）  
**Target Platform**: Web API（Inertia SPA 前端）  
**Project Type**: 單一 Laravel 專案  
**Performance Goals**: 寫入路徑低頻，僅需保證 < 100ms 典型驗證回應  
**Constraints**: 必須在資料層保證唯一（防併發/繞過 UI）  
**Scale/Scope**: 單庫單表寫入，資料量中小型

Clarifications Applied
- 固定部落值→顯示：
    - iraraley→朗島、ivalino→野銀、iranmeilek→東清、yayo→椰油、iratay→漁人、imorod→紅頭
- 既有重複資料：僅標記重複待人工（不自動合併）
- DELETE 端點：先不提供，以 GET/POST/PUT 完成 MVP

## Constitution Check

Gate: TDD Mandatory → 計畫將在變更前先寫失敗測試（Red），再實作（Green），最後重構（Refactor）。
Gate: 無硬性技術棧變更、無多服務拆分 → PASS。

## Project Structure（本專案）

```text
specs/003-tribal-unique-per-fish/
├── plan.md              # 本檔
├── research.md          # Phase 0 產出
├── data-model.md        # Phase 1 產出
├── quickstart.md        # Phase 1 產出
└── contracts/
    └── tribal-classifications.openapi.yaml

app/
├── Models/
│   └── TribalClassification.php         # 既有
├── Http/Controllers/
│   └── TribalClassificationController.php   # 如無則新增
└── Http/Requests/
    └── UpsertTribalClassificationRequest.php

database/
└── migrations/
    └── [add unique index fish_id+tribe]

tests/
└── Feature/
    └── TribalClassification/
        ├── UpsertUniquePerTribeTest.php
        └── ListSixTribesStatusTest.php
```

**Structure Decision**: 延續 Laravel 標準分層：Model + Controller + Request 驗證 + Migration + Pest 測試 + OpenAPI 合約。

## Phase 0: Outline & Research（research.md）

未知事項將轉為研究決策：
- 決定六個部落的枚舉清單來源（config/tribes.php 或 Enum 類別），介面顯示文字與儲存值一致性。
- 既有重複資料遷移：保留最早一筆、備註合併至保留筆；其餘標記為重複並 Soft Delete。
- 刪除策略：提供 DELETE 端點或僅允許清空內容（保留 0/1 結構）。

輸出 research.md：列出 Decision / Rationale / Alternatives。

## Phase 1: Design & Contracts

1) data-model.md：
- 新增唯一索引 (fish_id, tribe)。
- tribe 欄位限定於 {iraraley, ivalino, iranmeilek, yayo, iratay, imorod}。
- notes 長度限制與字元集建議。

2) contracts/: tribal-classifications.openapi.yaml（無 DELETE）
- GET /prefix/api/fish/{fish_id}/tribal-classifications → 列出 6 部落狀態（已/未建立）。
- POST /prefix/api/fish/{fish_id}/tribal-classifications → 新增（若存在回 409 並提示用備註）。
- PUT /prefix/api/fish/{fish_id}/tribal-classifications/{tribe} → 更新內容/備註。
- DELETE（可選）：刪除該部落條目（若採 0/1 結構的空位策略則可省）。

3) quickstart.md：
- 說明以 TDD 方式開發、跑測試、合約端點使用方式與範例呼叫。

4) update-agent-context：
- `.specify/scripts/bash/update-agent-context.sh copilot` 更新代理上下文。

## Phase 2: 準備 /speckit.tasks（停止於此，待 tasks）

輸出：research.md、data-model.md、contracts/tribal-classifications.openapi.yaml、quickstart.md，並在下一步產生 tasks 與實作。

## Complexity Tracking

無需特殊架構調整，依標準 MVC 與 Migration 實作即可。
