<!--
Sync Impact Report
- Version change: 1.0.0 → 1.1.0
- Modified principles: 新增 VIII. 文件與互動語言一致性
- Added sections: 無
- Removed sections: 檔案尾端第二套未填寫模板（[PROJECT_NAME] Constitution 等）
- Templates requiring updates (✅ updated / ⚠ pending):
  ⚠ .specify/templates/plan-template.md（Constitution Check 增列語言一致性）
  ⚠ .specify/templates/spec-template.md（新增「文件一律使用正體中文」註記）
  ✅ .specify/templates/tasks-template.md（無需變更）
- Follow-up TODOs:
  TODO(plan-template): 加入語言一致性檢查項
  TODO(spec-template): 加入正體中文要求註記
-->

# Tao Among Constitution

```
  驗證：PR Review 清單包含「語言一致性」；任何英文-only 區段需在 PR 描述註記理由。

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

**Version**: 1.1.0 | **Ratified**: 2025-11-09 | **Last Amended**: 2025-11-11

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
```
