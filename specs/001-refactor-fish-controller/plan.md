# Implementation Plan: Refactor FishController (TDD, web.php routes)

**Branch**: `001-refactor-fish-controller` | **Date**: 2025-10-29 | **Spec**: `./spec.md`
**Input**: Feature specification from `/specs/001-refactor-fish-controller/spec.md`

**Note**: 本計畫僅聚焦 `routes/web.php` 的 SSR 路由；`/prefix/api` 端點與其測試已標示為汰除，暫不納入本計畫。

## Summary

以 TDD 方式重構 FishController，僅聚焦 `routes/web.php` 的 SSR 路由（`/`, `/fishs`, `/search`, `/fish/{id}` 與其子路由）。
目標：

- 控制器薄化，商業邏輯（媒體 URL 組裝、查詢條件）下沉至服務層（`App/Services/FishService`）。
- 媒體 URL 規則 null-safe：圖片空值使用 `default.png`、音檔空值回傳 null，且僅於檔名為非空字串時呼叫 `SupabaseStorageService::getUrl`。
- 避免 N+1：對常用關聯採用 eager loading 或在服務集中查詢。
- 生產環境使用 `errorlog`，避免唯讀檔案系統寫入失敗。

## Technical Context

**Language/Version**: PHP ^8.2, Laravel ^11.31, Inertia.js (Laravel) ^2.0  
**Primary Dependencies**: `laravel/framework`, `inertiajs/inertia-laravel`, `laravel/sanctum`, `darkaonline/l5-swagger`（本功能不使用 OpenAPI，但保留函式庫相依）  
**Storage**: DB 本地預設 SQLite；生產以 PostgreSQL（專案指引）為主；媒體檔案以 Supabase Storage 提供 URL ︱ Redis 供快取/佇列（如設定）  
**Testing**: 後端 Pest ^3（unit/feature）；前端 Vitest（不在本計畫範圍）  
**Target Platform**: Vercel（`vercel-php@0.7.4`），無狀態、唯讀檔案系統，允許 `/tmp` 快取  
**Project Type**: 全端 Web（Laravel SSR + Inertia SPA）  
**Performance Goals**: 避免 N+1 查詢；列表/詳情頁查詢數量可預期且穩定；產生媒體 URL O(1)  
**Constraints**: 生產環境日誌建議使用 `errorlog`；避免寫入 `storage/logs`；檔案系統唯讀（除 `/tmp`）  
**Scale/Scope**: 本次僅重構查詢流程（列表、詳情、搜尋）；建立/更新/刪除不在本迭代

## Constitution Check

_GATE: 必須通過後才可繼續 Phase 0/1。於 Phase 1 完成後再複檢。_

- TDD 原則：先測試（Red）→ 最小實作（Green）→ 重構（Refactor）。狀態：PASS（以 Pest 實作）。
- 範圍限制：僅 `routes/web.php` 查詢端點（SSR）。狀態：PASS（OpenAPI paths 已留空、routes.md 已建立）。
- 相容性：允許為改善而微調輸出結構，需同步前端。狀態：PASS（spec.md Q2=C）。
- Null-safe 媒體 URL：圖片空值→預設圖；音檔空值→null；僅在非空檔名時呼叫 `getUrl`。狀態：PASS（將以服務層落實並覆測邊界）。
- 效能：避免 N+1；必要關聯 eager loading。狀態：PASS（由服務封裝查詢）。
- 日誌：生產應使用 `errorlog`。注意：vercel.json 目前為 `daily`，需後續調整。狀態：NOTE。

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

<!--
  ACTION REQUIRED: Replace the placeholder tree below with the concrete layout
  for this feature. Delete unused options and expand the chosen structure with
  real paths (e.g., apps/admin, packages/something). The delivered plan must
  not include Option labels.
-->

```text
app/
├── Http/
│   └── Controllers/
│       ├── FishController.php               # 重構目標（SSR 查詢端點）
│       ├── FishNoteController.php           # 相關知識/筆記管理（部分查詢畫面）
│       ├── FishAudioController.php          # 發音列表管理（查詢畫面）
│       └── KnowledgeHubController.php       # 知識管理入口（查詢畫面）
├── Models/
│   ├── Fish.php
│   ├── FishAudio.php
│   ├── FishNote.php
│   ├── FishSize.php
│   ├── CaptureRecord.php
│   └── TribalClassification.php
└── Services/
  ├── FishService.php                      # 查詢/媒體 URL 邏輯（本次強化）
  ├── FishSearchService.php                # 清單/搜尋條件與選項
  └── SupabaseStorageService.php           # 產生媒體 URL、刪檔等 I/O

routes/
└── web.php                                  # 本計畫唯一路由面（SSR）

resources/js/Pages/                          # Inertia 頁面（僅渲染端，非本次重構焦點）

tests/
├── Feature/                                 # Feature 測試（FishController SSR）
└── Unit/                                    # Unit 測試（FishService 媒體 URL 與查詢）
```

**Structure Decision**: 本倉庫為單一 Laravel 專案（全端 Web）。本次重構專注於後端 `app/Http/Controllers/FishController.php` 與 `app/Services/*`，路由以 `routes/web.php` 為準；前端頁面與 API `/prefix/api` 端點不在本迭代範圍。

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation                  | Why Needed         | Simpler Alternative Rejected Because |
| -------------------------- | ------------------ | ------------------------------------ |
| [e.g., 4th project]        | [current need]     | [why 3 projects insufficient]        |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient]  |

## Phases and Deliverables

- Phase 0 — Research [DONE]
  - 已產出：`research.md`（雲端日誌、null-safe 規則、避免 N+1）
- Phase 1 — Contracts and Design [DONE]
  - 已產出：`data-model.md`、`contracts/routes.md`（聚焦 web.php）、`quickstart.md`（TDD 步驟）
  - 備註：`contracts/openapi.yaml` 規劃上留空 paths（本迭代不處理 /prefix/api）
- Phase 2 — Tasks
  - 以 TDD 切分為最小風險單位，產生 `tasks.md`（由 /speckit.tasks 生成）

## Execution Plan (TDD, web.php only)

1. Feature Tests（Red）

   - 新增/修正 Feature 測試（`tests/Feature/`）
     - 列表 `/fishs`：回傳 Inertia 頁面 `Fishs`，props 包含 `fishs`、`filters`、`searchOptions`、`searchStats`。
     - 詳情 `/fish/{id}`：回傳 Inertia 頁面 `Fish`，props 包含 `fish`（含 image/audio URL 規則）、`tribalClassifications`、`captureRecords`、`fishNotes`。
     - 首頁 `/` 與搜尋 `/search`：渲染對應頁面且 props 結構正確。
     - 404：無效 id 時應拋出 404。

2. Unit Tests（Red）

   - `App\Services\FishService`：
     - `assignImageUrls` 在 image 檔名為空時套用 `default.png`，非空時呼叫 `SupabaseStorageService::getUrl('images', name)`。
     - 音檔 URL 規則：`null|''` → `null`；非空字串才呼叫 `getUrl('audios', name)`。
     - 查詢方法（若存在）：應做必要 eager loading、排序，且不造成 N+1。

3. Implement Minimal Code（Green）

   - 移動或新增邏輯到 `FishService`，控制器改為組裝輸入/輸出與呼叫服務。
   - 為 `SupabaseStorageService::getUrl` 的呼叫加上檔名非空判斷；避免傳入 null。
   - 如需要，為 `Fish` 模型加上簡單 accessor 但避免藏複雜邏輯（維持服務層集中）。

4. Refactor

   - 確保控制器方法精簡且可讀（< ~40 行為佳）。
   - 去除重複查詢與重複 `assignImageUrls` 呼叫，集中於服務一次處理。
   - 檢視 `vercel.json` 中 `LOG_CHANNEL` 設為 `errorlog` 的調整計畫（另 PR）。

5. CI Gate
   - 所有 Pest 測試 PASS。
   - 無新 PHPStan/Pint 警告（如已啟用）。

- 測試避免外網呼叫：對 Supabase HEAD/HTTP 使用 Http::fake()。

## Risks and Mitigations

- 前端相容性：調整 props 命名可能影響頁面。緩解：以最小變更為原則，必要時同步前端 PR。
- 生產日誌：`vercel.json` 目前 `LOG_CHANNEL=daily`。緩解：另開小 PR 將其改為 `errorlog`。
- N+1 隱性發生：詳情頁帶多關聯。緩解：服務層集中查詢 `with()`，並加入最小效能測試或日誌觀測。
- 測試外部依賴：Guzzle/HTTP 對 mock-storage 測試網域 SSL。緩解：統一以 `Http::fake()` 隔離。
