# Implementation Plan: Media URL Refactor

**Branch**: `001-media-url-refactor` | **Date**: 2025-10-31 | **Spec**: specs/001-media-url-refactor/spec.md
**Input**: Feature specification from `/specs/001-media-url-refactor/spec.md`

## Summary

重構媒體完整網址組合：

- 集中於 SupabaseStorageService 組合完整 URL；資料庫僅存檔名。
- 圖片：has_webp=true → 使用 webp；has_webp=false 或 null → 使用原圖；不做 HEAD。
- 音訊：有檔名才產生 URL；空/NULL 不輸出該欄位。
- 簽名上傳 API 回傳絕對 URL，audio 簽名失敗不異動 DB。

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP 8.x（Laravel）  
**Primary Dependencies**: Laravel、Inertia.js（前端 SPA）、Pest（後端測試）  
**Storage**: PostgreSQL、Supabase Storage（public bucket）  
**Testing**: Pest（後端）、Vitest（前端，非本次範圍）  
**Target Platform**: Web 後端 API + SPA  
**Project Type**: Web 應用（後端 + SPA 前端）  
**Performance Goals**: API 回應時間較現況平均下降 ≥10%（移除 HEAD 探測）  
**Constraints**: 不進行外部 HEAD 探測；簽名上傳回絕對 URL；音訊欄位空時不輸出  
**Scale/Scope**: 媒體 URL 組合、魚種清單與詳情、音訊列表、簽名上傳 API

## Constitution Check

_GATE: Must pass before Phase 0 research. Re-check after Phase 1 design._

Constitution 未明確定義，採專案慣例品質門檻：

- 測試先行：更新或新增測試覆蓋新行為（Unit + Feature）。
- 契約穩定：API 回傳格式調整需在合約中明定（簽名 URL 為絕對 URL）。
- 不做外網探測：禁止 HEAD 做為執行期決策。

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
Laravel 專案（本倉庫）：
app/
  Http/Controllers/        # ApiFishController, UploadController, ...
  Models/                  # Fish, CaptureRecord, FishAudio, ...
  Services/                # SupabaseStorageService, FishService, ...
routes/                    # api.php, web.php
resources/js               # Vue 3 SPA（Inertia）
tests/                     # Pest 測試（Unit/Feature）
specs/001-media-url-refactor/  # 規格與計畫產物
```

**Structure Decision**: 延用現有 Laravel + Inertia 結構；本次變更集中於 Services 與 Controllers，並同步更新測試。

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation                  | Why Needed         | Simpler Alternative Rejected Because |
| -------------------------- | ------------------ | ------------------------------------ |
| [e.g., 4th project]        | [current need]     | [why 3 projects insufficient]        |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient]  |
