<!--
Sync Impact Report
- Version change: N/A → 1.0.0
- Modified principles: [占位] → MVP 優先與避免過度設計；測試先行與可驗證性；合約先於實作；安全預設與資料保護；可觀測性與環境限制；版本策略與破壞性變更；品質關卡與 CI 門檻
- Added sections: 其他執行約束、開發流程與品質關卡
- Removed sections: 無
- Templates requiring updates (✅ updated / ⚠ pending):
	✅ .specify/templates/plan-template.md（Constitution Check 可沿用，無需修改）
	✅ .specify/templates/spec-template.md（必填章節不變，與原則一致）
	✅ .specify/templates/tasks-template.md（測試先行與分故事交付原則一致）
- Follow-up TODOs: 無（全部占位符已替換）
-->

# Tao Among Constitution

## Core Principles

### I. MVP 優先與避免過度設計（Non‑Negotiable）

本專案一律以可運作的最小可用增量（MVP）為先：

- MUST 先完成最高優先等級使用者故事（如 P1）即可交付；其餘功能延後。
- MUST 避免過早抽象與框架化（例如不預先引入複雜分層/Repository/DDD 模式），除非規格明文或效能/安全確有必要。
- SHOULD 優先採用簡單、可維護、易測試的解法（例如精簡 payload，盡量少載入非必要關聯）。
  驗證：規格與計畫需標示 MVP 範圍；任務以故事分批可獨立驗證；PR 僅納入達成 MVP 所需最小改動。

### II. 測試先行與可驗證性（Test‑First & Testability）

測試是交付品質的先決條件：

- MUST 在實作前撰寫（或至少同 PR 內先行提交）對應的測試：
  - 後端使用 Pest（單元 + 功能）。
  - 前端使用 Vitest（元件 + 頁面）。
- MUST 為每一項公開行為新增最小測試集合（至少：正常情境 + 1 個邊界/錯誤情境）。
- MUST 不得合併任何會導致測試失敗的變更；綠燈為門檻。
  驗證：CI 必須執行 `./vendor/bin/pest` 與 `npx vitest` 並通過；PR 描述需鏈接對應測試案例或檔案。

### III. 合約先於實作（Contract‑First APIs）

任何 API 的新增或修改，必須先有契約：

- MUST 在 `specs/<feature>/contracts/openapi.yaml` 定義或更新端點（路徑、參數、回應、錯誤格式）。
- MUST 實作遵循契約；控制器/請求驗證/服務需對齊 OpenAPI 定義。
- SHOULD 由前後端共同審閱契約後再開始實作，降低返工。
  驗證：合約檔存在且覆蓋本次變更；功能測試以契約為依據驗證 200/4xx。

### IV. 安全預設與資料保護（Security‑by‑Default）

安全與隱私預設為開啟狀態：

- MUST 預設以 Laravel Sanctum 驗證保護 API；若為公開端點，規格需明文標示並說明理由與範圍。
- MUST 進行伺服器端輸入驗證；對無效輸入以 4xx 回應，不得以模糊/寬鬆的 fallback 取代。
- SHOULD 避免在日誌/回應中洩漏敏感資訊（PII）。
  驗證：功能測試覆蓋授權/未授權情境；輸入驗證測試涵蓋非法參數與錯誤碼。

### V. 可觀測性與環境限制（Observability with Environment Constraints）

在受平台限制（如 Vercel 無持久化日誌）下仍需保持可觀測性：

- MUST 在規格中聲明效能目標與量測方式；將閾值集中於設定（例如 `config/*`）。
- MUST 僅在本地/測試環境啟用非持久的計時/偵聽（如 DB::listen/microtime），正式環境禁止寫入持久日誌（除非平台允許）。
- SHOULD 規劃未來導入 APM/集中化觀測時的銜接點（例如保留已註解的掛鉤）。
  驗證：規格含量測目標；程式碼中的觀測掛鉤以環境旗標保護；正式環境不產生持久化日誌。

### VI. 版本策略與破壞性變更（Versioning & Breaking Changes）

版本號僅在必要時引入，避免過度版本化：

- MUST 預設不在路徑加版本前綴（例如 `/fishs`）；
  當出現破壞性變更才採版本化或提供明確遷移方案/相容層。
- MUST 在規格/README/CHANGELOG 清楚記錄破壞性變更與升級步驟。
  驗證：OpenAPI 與文件同步更新；對舊行為的相容性或版本策略在 PR 中可追蹤。

### VII. 品質關卡與 CI 門檻（Quality Gates & CI）

交付必須經過可機械驗證的品質關卡：

- MUST Build、Lint/型別檢查與所有測試皆 PASS 才可合併。
- SHOULD 在 PR 中連結對應規格/計畫/任務，確保可追溯性。
- SHOULD 對公用介面變更附上最小文件更新（合約、quickstart、README 索引）。
  驗證：CI 工作流程執行三大關卡並回報；文件與合約檔更新納入審查清單。

## 其他執行約束（技術棧與命名慣例）

- 後端：PHP 8.x（Laravel + Eloquent + Pest）。
- 前端：Vue 3 + Inertia.js + Tailwind + Vitest。
- 資料庫：PostgreSQL（prod），SQLite（tests）。
- 上傳/媒體：Supabase Storage（依專案服務層封裝）。
- 命名：Vue 元件以功能命名；前端頁面採 `resources/js/Pages/*.vue`；全域元件於 `resources/js/Components/Global/`。

## 開發流程、審查與品質關卡

1. Spec → Plan → Tasks：
   - 規格含使用者故事、FR/SC、邊界案例與量測目標。
   - 計畫明確輸入/輸出、相依與專案結構。
   - 任務以使用者故事分組，可獨立交付與測試。
2. Contract‑First：先提 OpenAPI，再開始 Controller/Service。
3. Test‑First：先補測試（至少 1 個快樂路徑 + 1 個邊界），再實作。
4. 驗收與文件：quickstart/README/合約同步更新；確保可被外部復現。
5. CI Quality Gates：Build、Lint/型別、Pest、Vitest 全綠才可合併。

## Governance

本憲章凌駕其他流程文件，變更需經審議並遵循版本化：

- 憲章修改需於 PR 中：列出動機、影響面、遷移計畫；經維運/技術負責人審核。
- 版本規則：SemVer（MAJOR：刪改原則；MINOR：新增原則或大幅擴充；PATCH：文字釐清）。
- 合規審查：所有 PR 需在描述中對齊憲章原則（特別是 Test‑First、Contract‑First、Quality Gates）。

**Version**: 1.0.0 | **Ratified**: 2025-11-09 | **Last Amended**: 2025-11-09

# [PROJECT_NAME] Constitution

<!-- Example: Spec Constitution, TaskFlow Constitution, etc. -->

## Core Principles

### [PRINCIPLE_1_NAME]

<!-- Example: I. Library-First -->

[PRINCIPLE_1_DESCRIPTION]

<!-- Example: Every feature starts as a standalone library; Libraries must be self-contained, independently testable, documented; Clear purpose required - no organizational-only libraries -->

### [PRINCIPLE_2_NAME]

<!-- Example: II. CLI Interface -->

[PRINCIPLE_2_DESCRIPTION]

<!-- Example: Every library exposes functionality via CLI; Text in/out protocol: stdin/args → stdout, errors → stderr; Support JSON + human-readable formats -->

### [PRINCIPLE_3_NAME]

<!-- Example: III. Test-First (NON-NEGOTIABLE) -->

[PRINCIPLE_3_DESCRIPTION]

<!-- Example: TDD mandatory: Tests written → User approved → Tests fail → Then implement; Red-Green-Refactor cycle strictly enforced -->

### [PRINCIPLE_4_NAME]

<!-- Example: IV. Integration Testing -->

[PRINCIPLE_4_DESCRIPTION]

<!-- Example: Focus areas requiring integration tests: New library contract tests, Contract changes, Inter-service communication, Shared schemas -->

### [PRINCIPLE_5_NAME]

<!-- Example: V. Observability, VI. Versioning & Breaking Changes, VII. Simplicity -->

[PRINCIPLE_5_DESCRIPTION]

<!-- Example: Text I/O ensures debuggability; Structured logging required; Or: MAJOR.MINOR.BUILD format; Or: Start simple, YAGNI principles -->

## [SECTION_2_NAME]

<!-- Example: Additional Constraints, Security Requirements, Performance Standards, etc. -->

[SECTION_2_CONTENT]

<!-- Example: Technology stack requirements, compliance standards, deployment policies, etc. -->

## [SECTION_3_NAME]

<!-- Example: Development Workflow, Review Process, Quality Gates, etc. -->

[SECTION_3_CONTENT]

<!-- Example: Code review requirements, testing gates, deployment approval process, etc. -->

## Governance

<!-- Example: Constitution supersedes all other practices; Amendments require documentation, approval, migration plan -->

[GOVERNANCE_RULES]

<!-- Example: All PRs/reviews must verify compliance; Complexity must be justified; Use [GUIDANCE_FILE] for runtime development guidance -->

**Version**: [CONSTITUTION_VERSION] | **Ratified**: [RATIFICATION_DATE] | **Last Amended**: [LAST_AMENDED_DATE]

<!-- Example: Version: 2.1.1 | Ratified: 2025-06-13 | Last Amended: 2025-07-16 -->
