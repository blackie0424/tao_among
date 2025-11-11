# Feature Specification: Fix search modal overlap on mobile

**Feature Branch**: `001-fix-search-modal`  
**Created**: 2025-11-11  
**Status**: Draft  
**Input**: User description: "我要修正ui顯示問題，在手機裝置上，使用者在/fishs頁面，按下右上角搜尋icon後，跳出的對話框會被底部新增魚類的控制項遮蓋，導致使用者不容易點擊搜尋及清除按鈕，這導致使用者在體驗上不順暢，我們需要解決這一個問題"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - 開啟搜尋對話框能完整操作 (Priority: P1)

使用者在手機裝置（窄螢幕）於 `/fishs` 頁面上，點擊右上角的搜尋圖示，期望彈出對話框（modal 或 panel）能完整顯示搜尋欄位與「搜尋」/「清除」按鈕，且不被底部的新增魚類控制項或其他固定浮動元件遮擋。

Why this priority: 這直接影響主要探索/搜尋流程的可用性，若無法可靠點擊「搜尋/清除」，使用者將無法完成常見任務，導致產品體驗重大受損。

Independent Test: 在真實手機或瀏覽器模擬手機尺寸（例如 360x800）下打開 `/fishs`，點擊右上角搜尋圖示，觀察並驗證：

Acceptance Scenarios:

1. Given 使用者在手機大小視窗，When 點擊右上角搜尋圖示，Then 搜尋對話框應完整顯示，且「搜尋」與「清除」按鈕可被觸控點擊（不被底部控制項遮擋）。
2. Given 搜尋對話框開啟，When 使用者向下滾動頁面（或對話框內滾動），Then 對話框的互動按鈕仍然可觸控，或視設計為固定於視窗可操作位置。

---

### User Story 2 - 快速清除條件並重新載入 (Priority: P2)

使用者開啟搜尋對話框後欲快速清除已選過濾條件並回到預設清單。

Why this priority: 清除功能是搜尋工作流程的常見需求；若無法使用將降低搜尋效率與使用率。

Independent Test: 開啟搜尋對話框、填入條件、點擊「清除」，確認對話框欄位被重置且主列表以不帶條件的首批結果重載。

Acceptance Scenarios:

1. Given 搜尋對話框開啟並有條件，When 點擊「清除」，Then 表單欄位清空並觸發回到 `/fishs` 的首批載入（或呼叫相同端點以載入首批）。

---

### User Story 3 - 例外情境：軟鍵盤顯示時的可觸控性 (Priority: P3)

在手機上使用文字輸入時，系統鍵盤將佔用畫面空間，必須確保搜尋/清除按鈕仍可被使用或自動滾動到可視區域。

Why this priority: 軟鍵盤為行動裝置常見行為，若遮蔽互動按鈕會導致無法提交/清除的情況。

Independent Test: 在手機尺寸下開啟搜尋對話框，點擊輸入欄位以喚出鍵盤，確認按鈕仍可見或畫面自動調整使按鈕可操作。

Acceptance Scenarios:

1. Given 搜尋輸入欄位 focus 且鍵盤打開，When 鍵盤顯示，Then 對話框內容（含按鈕）應自動調整到可視並可點擊，或按鈕固定在鍵盤上方區域並能點擊。

---

### Edge Cases

- 若頁面有其他固定浮動控制項（例如新增魚類按鈕、底部工具列），系統 MUST 自動調整 modal 的 z-index 與底部 safe-area spacing，避免遮擋互動按鈕。
- 若裝置使用奇特 aspect ratio 或非常低高度視窗（例如瀏覽器工具列顯示），則系統 MUST 將搜尋表單改為全螢幕覆蓋（full-screen modal）以確保可操作。
- 若鍵盤啟動時可視高度過小，系統 MUST 將表單欄位 scrollIntoView 目標按鈕，或將操作按鈕固定於視窗上方且可點擊。

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: 當使用者在手機寬度（viewport width <= 768px）開啟搜尋對話框時，搜尋對話框 MUST 顯示在最上層（較高 z-index）並且不會被頁面上的固定底部控制項（例如新增魚類按鈕）遮擋。
- **FR-002**: 搜尋對話框內的主要互動按鈕（「搜尋」、「清除」） MUST 永遠保持可觸控（在鍵盤顯示或未顯示的情況下），不得被其他元件遮蓋或移出可視區域。
- **FR-003**: 當可視高度不足以同時顯示所有搜尋欄位與按鈕時，系統 MUST 將對話框切換為 full-screen 模式或使按鈕固定在視窗底部（但是在 z-index 最高且高於任何浮動按鈕）。
- **FR-004**: 若頁面有固定底部按鈕，UI MUST 在開啟對話框時自動加入相等於底部按鈕高度的 safe-area spacing 或 margin，確保按鈕可見與可點擊。
- **FR-005**: 所有變更 MUST 遵守現有視覺主題（Tailwind CSS 設計系統），並通過基本可及性檢查（ARIA roles、focus 管理、tab order）以確保鍵盤/輔助技術可操作性。

### Key Entities *(include if feature involves data)*

- **SearchDialog**: 前端 UI component，屬性包括 `isOpen: boolean`, `mode: 'modal'|'fullscreen'`, `fields: object`, `actions: ['search','clear']`。
- **BottomActionBar**: 既有的底部新增魚類控制項，具有 `height` 與 `zIndex` 屬性，必須被用作 safe-area spacing 參考。

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 在手機視窗（典型 360x800 與 414x896 測試尺寸）上，開啟搜尋對話框時「搜尋」與「清除」按鈕在 3 個不同裝置/模擬器上皆可直接觸控（手動驗證），不含遮擋情況。
- **SC-002**: 在鍵盤顯示情境下，按鈕可在 1 次自動畫面調整（scrollIntoView 或 layout switch）後可見並可點擊；若未可見，視同失敗。
- **SC-003**: UI 變更被覆蓋之相關跨瀏覽器/跨裝置回歸測試（至少 3 種行動瀏覽器）通過，且無視覺樣式偏差超過 5%（主觀判定）。

## Assumptions
 
## Assumptions

- The project uses a consistent design system and has reusable dialog and button components.
- The `BottomActionBar` (the bottom add-fish control) exposes a stable height (via CSS class or design token) that can be determined at runtime or agreed in the visual spec.
- The front-end is able to perform runtime layout checks (measure element bounds) if needed to compute safe-area spacing or to decide a full-screen fallback.

## Implementation Notes (non-normative)

- Preferred approach: when the search dialog opens on narrow viewports, detect whether any fixed bottom controls exist and whether the visible viewport height is sufficient. If a conflict is detected, add a bottom safe-area spacing to the dialog and elevate its stacking context so primary actions remain interactive.
- If available viewport height falls below a practical threshold, present the dialog in a full-screen mode where primary actions are kept in a visible action area that does not overlap with other controls.
- Ensure focus management and input behavior are handled so that when on-screen keyboards appear, the action area remains reachable (for example by using an internal scroll region or layout switch). These are implementation details for the front-end team to choose an accessible technique.

<!-- removed duplicate template tail -->
# Feature Specification: [FEATURE NAME]

**Feature Branch**: `[###-feature-name]`  
**Created**: [DATE]  
**Status**: Draft  
**Input**: User description: "$ARGUMENTS"

## User Scenarios & Testing *(mandatory)*

<!--
  IMPORTANT: User stories should be PRIORITIZED as user journeys ordered by importance.
  Each user story/journey must be INDEPENDENTLY TESTABLE - meaning if you implement just ONE of them,
  you should still have a viable MVP (Minimum Viable Product) that delivers value.
  
  Assign priorities (P1, P2, P3, etc.) to each story, where P1 is the most critical.
  Think of each story as a standalone slice of functionality that can be:
  - Developed independently
  - Tested independently
  - Deployed independently
  - Demonstrated to users independently
-->

### User Story 1 - [Brief Title] (Priority: P1)

[Describe this user journey in plain language]

**Why this priority**: [Explain the value and why it has this priority level]

**Independent Test**: [Describe how this can be tested independently - e.g., "Can be fully tested by [specific action] and delivers [specific value]"]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action], **Then** [expected outcome]
2. **Given** [initial state], **When** [action], **Then** [expected outcome]

---

### User Story 2 - [Brief Title] (Priority: P2)

[Describe this user journey in plain language]

**Why this priority**: [Explain the value and why it has this priority level]

**Independent Test**: [Describe how this can be tested independently]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action], **Then** [expected outcome]

---

### User Story 3 - [Brief Title] (Priority: P3)

[Describe this user journey in plain language]

**Why this priority**: [Explain the value and why it has this priority level]

**Independent Test**: [Describe how this can be tested independently]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action], **Then** [expected outcome]

---

[Add more user stories as needed, each with an assigned priority]

### Edge Cases

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right edge cases.
-->

- What happens when [boundary condition]?
- How does system handle [error scenario]?

## Requirements *(mandatory)*

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right functional requirements.
-->

### Functional Requirements

- **FR-001**: System MUST [specific capability, e.g., "allow users to create accounts"]
- **FR-002**: System MUST [specific capability, e.g., "validate email addresses"]  
- **FR-003**: Users MUST be able to [key interaction, e.g., "reset their password"]
- **FR-004**: System MUST [data requirement, e.g., "persist user preferences"]
- **FR-005**: System MUST [behavior, e.g., "log all security events"]

*Example of marking unclear requirements:*

- **FR-006**: System MUST authenticate users via [NEEDS CLARIFICATION: auth method not specified - email/password, SSO, OAuth?]
- **FR-007**: System MUST retain user data for [NEEDS CLARIFICATION: retention period not specified]

### Key Entities *(include if feature involves data)*

- **[Entity 1]**: [What it represents, key attributes without implementation]
- **[Entity 2]**: [What it represents, relationships to other entities]

## Success Criteria *(mandatory)*

<!--
  ACTION REQUIRED: Define measurable success criteria.
  These must be technology-agnostic and measurable.
-->

### Measurable Outcomes

- **SC-001**: [Measurable metric, e.g., "Users can complete account creation in under 2 minutes"]
- **SC-002**: [Measurable metric, e.g., "System handles 1000 concurrent users without degradation"]
- **SC-003**: [User satisfaction metric, e.g., "90% of users successfully complete primary task on first attempt"]
- **SC-004**: [Business metric, e.g., "Reduce support tickets related to [X] by 50%"]
