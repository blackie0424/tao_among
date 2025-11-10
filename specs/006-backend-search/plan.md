# Implementation Plan: Backend Server-Side Search (006-backend-search)

**Branch**: `006-backend-search` | **Date**: 2025-11-09 | **Spec**: `specs/006-backend-search/spec.md`
**Input**: Feature specification from `specs/006-backend-search/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

本功能（006-backend-search）將原前端本地搜尋改為後端游標式多條件搜尋，以減少行動裝置負擔並確保結果穩定不重複、無遺漏。核心包含：

1. 多條件 AND 組合（名稱模糊 ILIKE、部落/食物分類等值、捕獲地點/方式/處理方式模糊），空白輸入自動忽略避免 ILIKE '%%'。
2. 游標分頁：固定 `id DESC` 排序 + 明碼 `last_id` 邊界（`id < last_id`），以 `LIMIT perPage+1` 前瞻判斷 hasMore。減少 offset 引起的重複/跳漏風險。
3. 精簡 payload：僅回傳 `id,name,image_url`（白名單鎖定）；以此支撐首屏 payload 降幅 ≥30%（SC-004）。
4. 公開端點（FR-015）但最小欄位確保安全；422 對非法游標（非正整數/方向錯誤/條件變更過期），合法但空區段回 200 空集合。
5. 效能與量測：SC-001..SC-007（p50/p95 延遲門檻、payload 降幅、游標序列 200 筆抽樣無重複）。`slow_query_ms=1000` 集中於設定，正式環境不記錄持久 Log。
6. 技術取徑：`FishSearchRequest` 進行參數驗證與正規化；`FishSearchService` 建立動態 Query（AND 彙整 + 游標 lookahead）；Controller 僅調度與回傳契約結構。先撰寫 Pest 測試覆蓋 Happy Path + 邊界（非法游標/空集合/正規化）。

MVP 範圍：完成單欄位名稱搜尋 + 游標分頁（P1），再擴展多條件（P2）；不納入推薦詞、關鍵字字典解析、欄位擴充或版本前綴。避免過度設計（不引入 Repository/額外抽象層）。

## Technical Context

<!-- 已填寫；保留註解以示模板來源 -->

**Language/Version**: PHP 8.x（Laravel）  
**Primary Dependencies**: Laravel Framework (Routing, Eloquent, Validation), Inertia.js（前端整合）  
**Storage**: PostgreSQL（prod），SQLite（tests）  
**Testing**: Pest（後端）、Vitest（前端）  
**Target Platform**: Web（Vercel 部署限制：無持久 Log）  
**Project Type**: Web application (單一 Laravel + Vue SPA)  
**Performance Goals**: SC-001 p50 首批 ≤1s；SC-002 p95 ≥3條件 ≤1.8s；SC-003 p50 續載 ≤800ms；SC-006 95% 首批 <1000ms；SC-004 首屏 payload 減少 ≥30%  
**Constraints**: 不記錄正式環境慢查詢持久 Log；公開端點需保持最小資料面；不使用 offset；不引入全文索引（v1）  
**Scale/Scope**: 資料筆數中等（數千級假設）；使用者同時搜尋條件多數 ≤2；MVP 僅列表瀏覽與續載

NEEDS CLARIFICATION：無（前期 research 已解析所有技術未知數）

## Constitution Check

_GATE: Must pass before Phase 0 research. Re-check after Phase 1 design._

原則對照：

- MVP：分階段（名稱搜尋先行）→ 避免過度抽象（不引入 Repository/DDD）。
- Test‑First：計畫 Phase 2 會先撰寫 Pest 功能測試（游標、正規化、錯誤）。
- Contract‑First：OpenAPI 已建立並帶 FR/SC 註解（CHK036 完成）。
- Security‑by‑Default：公開端點理由記錄（FR-015），精簡欄位避免敏感資料；輸入驗證在 Request。若未來改保護可低風險調整。
- Observability：效能門檻集中設定；不持久記錄 slow query；提供測試量測策略。
- Versioning：不加 /v1，因為無破壞性變更；未來需要再版本化。
- Quality Gates：Pest & Vitest 綠燈才合併；CI 已有對應 Task。

初次評估：無違規；無需 Complexity Tracking 條目。

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

<!-- 已裁剪未使用選項，保留最終決策 -->

（省略未使用結構選項；採用既有 Laravel + Vue 單專案）

**Structure Decision**: 採用現有單一 Web 專案結構（Laravel + Vue SPA），僅新增最小檔案：

實作位置：

- 後端
  - `app/Http/Requests/FishSearchRequest.php`（新）：參數驗證與正規化（perPage、last_id、trim/忽略空白）。
  - `app/Services/FishSearchService.php`（新）：依 FR-003/FR-004 組合條件，`id DESC` + `id < last_id`，lookahead 實作與回傳 `items` + `pageInfo`。
  - `app/Http/Controllers/FishController.php`（調整）：注入 Request/Service，回傳契約格式。
  - `config/fish_search.php`（既有）：`per_page_default`、`per_page_max`、`slow_query_ms`。
  - `routes/web.php`：GET `/fishs` 維持；控制器方法內切換至服務查詢。
- 測試
  - `tests/Feature/FishSearchTest.php`（新）：
    - happy path（首批/續載）
    - 422 非法游標
    - 200 空區段
    - perPage 正規化
    - 多條件 AND 組合
    - payload 欄位白名單檢查

前端：維持 Inertia.js 呼叫，不變更檔案結構；後續視需要補 `resources/js/Tests` 的 Vitest 測試。

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

（無）
