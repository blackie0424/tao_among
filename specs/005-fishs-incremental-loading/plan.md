# Implementation Plan: Fishs Incremental Loading

**Branch**: `005-fishs-incremental-loading` | **Date**: 2025-11-04 | **Spec**: ../spec.md  
**Input**: Feature specification from `/specs/005-fishs-incremental-loading/spec.md`

## Summary

本里程碑聚焦「僅導入游標式分頁」以降低首屏負載與提升行動裝置體驗，不調整單筆 item 欄位結構。後端提供 `cursor/perPage` 請求與 `items/pageInfo{hasMore,nextCursor}` 回應，排序穩定（`id DESC`）。前端接入分批載入、續載節流/上鎖、重試不回退、返回狀態還原。搜尋功能採「圖示常駐但未載完停用」方案，於 hasMore=false 後啟用（不改後端）。

## Technical Context

**Language/Version**: PHP 8.x（Laravel）, JavaScript/TypeScript（Vue 3 + Inertia.js）  
**Primary Dependencies**: Laravel Eloquent、Inertia.js、Tailwind CSS、Pest（後端測試）、Vitest（前端測試）  
**Storage**: PostgreSQL（prod），SQLite（tests）  
**Testing**: Pest（Laravel）、Vitest（前端）  
**Target Platform**: Web（SPA，行動優先）  
**Project Type**: Web（Laravel 後端 + Inertia Vue 前端）  
**Performance Goals**: 首屏 1s 可見（SC-001）；續載中位數 ≤ 800ms（SC-002）；CLS ≤ 0.1（SC-003）  
**Constraints**: 本里程碑不得變更單筆 item 欄位；僅新增分頁 envelope；搜尋不改後端  
**Scale/Scope**: 清單項目數不定，需以游標分頁支撐長清單；perPage 預設 20，可配置

## Constitution Check

Gates（以專案慣例與 CI 期待為準）：

- 不變更既有資料 schema 與單筆欄位（PASS：本計畫不調整欄位）
- 保持測試可通過（Pest/Vitest）（PENDING：實作後驗證）
- API 契約變更需有文件（PENDING：待 Phase 1 產出 contracts/ 與 quickstart.md）
- 效能目標需可測（PASS：以 Success Criteria 指標）

備註：規格 `FR-007` 既有「後端為主」敘述與新版「前端啟用時機」並存，已於 Phase 0 研究明確化為「到達 hasMore=false 後啟用搜尋」。

## Project Structure

### Documentation (this feature)

```text
specs/005-fishs-incremental-loading/
├── plan.md              # 本文件
├── research.md          # Phase 0：研究與決策彙整
├── data-model.md        # Phase 1：資料結構與狀態模型
├── quickstart.md        # Phase 1：如何對接與驗收
└── contracts/           # Phase 1：API 契約（OpenAPI 片段）
```

### Source Code（實作將影響的檔案/區域）

```text
routes/web.php                        # 於 GET /fishs 注入 cursor 分頁（Inertia）
app/Http/Controllers/                 # 更新列表頁 Controller 回傳 props（items/pageInfo）
app/Services/FishSearchService.php    # 若既有，擴充提供 cursor 分頁查詢能力
resources/js/Pages/                   # Fishs 列表頁面：partial reload（only/replace）、續載、搜尋啟用時機
resources/js/Components/              # 進度/骨架/狀態提示元件（如需）
tests/Feature/                        # 後端契約測試（Pest）
resources/js/Tests/                   # 前端列表行為測試（Vitest）
```

**Structure Decision**: 依現有 Laravel + Inertia 單一倉庫結構；僅增補端點與頁面邏輯，無需新增子專案。

## Complexity Tracking

目前無需偏離既有流程或引入額外複雜度；若後續引入「快速載入全部」或「後端搜尋」將另行評估。

## Constitution Check — 設計後複核（Phase 1 Re-check）

- 不變更既有資料 schema 與單筆欄位（PASS）
- 保持測試可通過（Pest/Vitest）（PENDING：待實作與執行）
- API 契約變更需有文件（PASS：contracts/inertia-fishs-pagination.yaml + quickstart.md）
- 效能目標需可測（PASS：quickstart.md 列出量測方式）

本階段已產出：

- research.md（Phase 0）
- data-model.md（Phase 1）
- contracts/inertia-fishs-pagination.yaml（Phase 1）
- quickstart.md（Phase 1）
