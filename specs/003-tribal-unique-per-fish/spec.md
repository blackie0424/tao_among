# Feature Specification: Tribal classification unique per fish

**Feature Branch**: `003-tribal-unique-per-fish`  
**Created**: 2025-11-02  
**Status**: Draft  
**Input**: User description: "在管理地方知識的介面，針對每個部落的魚的分類及處理方式需要修正，現在的管理方式是可以新增各部落的資料，但部落是固定的，只有六個，現在的做法可以新增重複的部落資料，會讓操作者搞混，我們需要設定每條魚就是只有這六個部落的資料，不能重複，若有相同部落但不同意見，可以在地方知識中的備註做紀錄"

## User Scenarios & Testing (mandatory)

### User Story 1 - 防止重複部落紀錄 (Priority: P1)

在地方知識管理頁，操作者針對單一魚種編輯「部落分類與處理方式」。系統應限制同一條魚的 6 個固定部落，每個部落最多一筆紀錄，避免重覆新增同一部落條目。

Why this priority: 直接解決當前最常見的混淆與資料錯置問題，降低後續清理與誤用成本。

Independent Test: 僅實作此限制也能提供清楚的資料結構；嘗試對同一魚新增相同部落第二筆，系統回報阻擋訊息且不新增。

Acceptance Scenarios:
1. Given 魚 A 尚無部落紀錄，When 新增「部落 X」條目，Then 建立成功。
2. Given 魚 A 已有「部落 X」條目，When 再次嘗試新增「部落 X」，Then 系統拒絕並提示「該部落已存在，請改用備註記錄不同意見」。
3. Given 魚 A 已有「部落 X」條目，When 編輯該條目，Then 允許更新內容（不影響唯一性）。

---

### User Story 2 - 部落清單固定且可見 (Priority: P1)

操作者在編輯頁看見固定六個部落的清單，清楚標示哪些部落已有紀錄、哪些尚未建立，避免誤以為可自訂任意部落名稱。

Why this priority: 明確引導正確操作，提升資料一致性與效率。

Independent Test: 僅顯示固定清單也能帶來改善；驗證 UI 只呈現六個部落選項，且已建立者標記為「已建立/不可重複」。

Acceptance Scenarios:
1. Given 進入魚 A 編輯頁，When 展示部落清單，Then 僅出現固定六個部落且標示各自狀態。
2. Given 魚 A 的「部落 Y」已有紀錄，When 使用者點選「新增部落 Y」，Then UI 禁用或提示不可重複。

---

### User Story 3 - 不同意見改記備註 (Priority: P2)

當同一部落對同一魚種有不同看法時，操作者以該部落既有條目的「備註」欄位補充，不再新增第二筆同部落紀錄。

Why this priority: 將分歧整合於單一來源，避免分散。

Independent Test: 僅提供備註欄也能滿足需求；驗證可在同一條目新增或累積備註內容。

Acceptance Scenarios:
1. Given 魚 A 的「部落 Z」已有條目，When 欲記錄不同意見，Then 於備註區新增文字並保存成功。
2. Given 備註過長或含特殊字元，When 提交，Then 依欄位規則驗證並給出清楚訊息。

### Edge Cases

- 既有資料已存在重複部落：需要資料遷移策略（見需求）。
- 操作者同時在兩個視窗嘗試新增同一部落：需有一致性保障（以後端唯一性規則為準）。
- 變更固定部落清單（未來需求）：需定義維護流程與相容策略。

## Requirements (mandatory)

### Functional Requirements

- FR-001: 系統必須在資料層強制「同一魚 × 同一部落」唯一，不得存在多筆重複紀錄。
- FR-002: 介面僅顯示固定六個部落可選，不允許新增任意新部落名稱。
- FR-003: 若使用者嘗試新增已存在的「同一魚 × 同一部落」紀錄，系統必須拒絕並顯示建議：「請在備註補充不同意見」。
- FR-004: 備註欄位支援多段敘述與基本長度限制；提交時進行驗證與錯誤提示。
- FR-005: 對既有資料的重複部落需提供遷移方案：合併到單一條目或標記重複待人工整理（以不丟失資訊為原則）。
- FR-006: 列表/編輯頁須標示各部落狀態（已建立/未建立），引導正確操作。
- FR-007: 任何透過 API 的寫入（新增/更新）都需套用相同唯一性與驗證規則，避免繞過 UI。

### Key Entities

- Entity: 魚（Fish）
  - 關聯：擁有多個部落知識條目（最多六個，對應固定部落清單）
- Entity: 部落知識條目（TribalClassification / FishNote）
  - 屬性：fish_id、tribe（枚舉：固定六個之一）、category/processing、notes（備註）
  - 約束：唯一鍵 (fish_id + tribe)

## Success Criteria (mandatory)

### Measurable Outcomes

- SC-001: 在資料層面，新增重複（魚 × 部落）紀錄的失敗率達到 100%（不可寫入）。
- SC-002: 操作員在編輯頁可於 10 秒內辨識六個部落的建立狀態（已建立/未建立）。
- SC-003: 相關誤用的回報或工單在上線後 4 週內降低 80% 以上（以現況為基準）。
- SC-004: 既有重複資料的遷移在一次維護作業內完成（≤ 1 天），且不丟失任何既有備註內容。
