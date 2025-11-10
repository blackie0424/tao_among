# Feature Specification: Fishs Incremental Loading

**Feature Branch**: `005-fishs-incremental-loading`  
**Created**: 2025-11-04  
**Status**: Draft  
**Input**: User description: "/fishs ，我要在頁面載入時，可以分批載入圖檔及文字資料，讓手持裝置的使用者不會因為瞬間的資料量導致載入過慢，體驗不佳"

## User Scenarios & Testing _(mandatory)_

### User Story 1 - 首屏快速可見 (Priority: P1)

使用者在行動裝置開啟「魚類列表」頁面時，能在1秒內看到首屏卡片骨架與首批資料的基本文字與縮圖，並可立即捲動瀏覽。

**Why this priority**: 直接影響初次體驗與跳出率，需優先保障。

**Independent Test**: 於乾淨瀏覽器開 /fishs，記錄首屏可見時間（FCP/TTI 代理值）；目測首批卡片是否在1秒內出現，並可立即捲動。

**Acceptance Scenarios**:

1. Given 首次載入 /fishs，When 網路為 4G/良好 Wi‑Fi，Then 使用者在1秒內看到骨架與首批卡片的文字與縮圖。
2. Given 首次載入 /fishs，When 首批資料尚未全部到齊，Then 骨架持續顯示且互動（捲動）不受阻礙。

---

### User Story 2 - 連續瀏覽不中斷 (Priority: P2)

使用者往下捲動時，列表自動載入下一批資料；若載入失敗，提供清楚的重試，不影響已載內容。

**Why this priority**: 支援長列表探索，避免一次載入全部造成延遲。

**Independent Test**: 於 /fishs 連續捲動觸發多次續載，觀察請求節流、狀態提示與重試行為。

**Acceptance Scenarios**:

1. Given 列表底部靠近視窗，When 觸發續載，Then 顯示載入中狀態並追加新卡片，無重排抖動。
2. Given 續載請求失敗，When 使用者點擊重試，Then 僅重新載入該批資料，既有內容不變。

---

### User Story 3 - 圖片平滑載入 (Priority: P3)

列表中的圖片以懶載入與後備格式供應，可先看到占位圖，下載完成後平滑顯示，不會造成版面跳動。

**Why this priority**: 降低行動網路的流量與首屏延遲，提升穩定性。

**Independent Test**: 以慢速網路模擬載入，觀察占位、淡入與 CLS 指標是否穩定。

**Acceptance Scenarios**:

1. Given 圖片尚未載入，When 卡片進入視窗，Then 顯示固定比例占位並開始載入，完成後淡入顯示。
2. Given 來源圖片不可用，When 載入失敗，Then 顯示後備圖片或通用占位不破版。

### Edge Cases

- 無資料：顯示空狀態與重新整理操作，且不反覆重試無效請求。
- 到達清單終點：顯示「已無更多」並停止續載請求。
- 慢網或離線：維持骨架與占位顯示，提供重試；恢復連線後可續載。
- 重覆觸發：續載請求節流或上鎖，避免並發重複請求造成插入順序錯亂。

## Requirements _(mandatory)_

### Functional Requirements

- **FR-001**: 系統必須提供魚類列表的「游標式分頁」資料取得，並遵循以下合約（不更動每筆 item 的欄位結構）：
- 路由：採用 Web 路由 `GET /fishs`（Inertia 頁面），以查詢參數承載分頁狀態
- 請求（Query）：`cursor`（字串；首批為 null/未提供）、`perPage`（預設 20）
- 回應（Props）：`items: [...]`、`pageInfo: { hasMore: boolean, nextCursor?: string }`（透過 Inertia 傳遞給頁面）
- 排序需穩定：建議 `id DESC` 或 `created_at DESC, id DESC` 作為 tie-breaker
- 備註：本里程碑僅新增分頁 envelope（pageInfo/cursor），`items` 內單筆資料欄位維持現況，不新增/刪除欄位；前端可透過 Inertia 的 partial reload（例如 `only`/`replace`）取得下一批資料並合併到既有列表狀態。
- **FR-002**: 首次回應必須支援「首批資料」與其餘資料的漸進載入，使用者可在首批就緒時即可瀏覽與捲動。
- **FR-003**: 列表捲動至底部時，系統必須能無縫載入下一批資料，且在載入中提供視覺狀態提示。
- **FR-004**: 續載失敗時，必須顯示清楚錯誤與可重試的操作，不影響已載入內容。
- **FR-005**: 圖片必須支援懶載入與固定容器比例，占位顯示，載入完成後平滑呈現，失敗時有後備圖。
- **FR-006**: 列表狀態（已載資料、滾動位置）在返回 /fishs 時必須可被還原，避免重新載入影響體驗。
- **FR-007**: 搜尋/過濾維持現行「後端為主」的行為；分頁與查詢可共用（如名稱關鍵字），但不為本里程碑擴充或新增可搜尋欄位（不增加隱藏欄位回傳）。
  **FR-007**: 搜尋維持前端過濾（不改後端回傳欄位），僅在列表「已載入全部」（`hasMore=false`）後啟用；在未載完前，搜尋圖示常駐但為停用狀態並顯示提示。
  - UI：
    - 未載完（hasMore=true）時：搜尋圖示停用；tooltip/點擊提示「尚未載入全部資料，載入完成後可使用搜尋」。
    - 載完（hasMore=false）時：自動啟用；出現一次性提示「已載入全部，現在可使用搜尋」。
  - 補充：若清單極大，允許提供「快速載入全部」按鈕（可選）以加速啟用搜尋，但不屬本里程碑必做。
- **FR-008**: 必須避免一次發出多個續載請求（佇列/上鎖/節流）。
- **FR-009**: 回應內容大小需受控（僅必要欄位），以降低首屏載入時間與行動流量。
- **FR-010 [Deferred]**: 本里程碑不更動圖片欄位，沿用現行回傳（包含既有 `has_webp` 等既有指標）。「同時提供 WebP 與 JPEG 兩種 URL」延至下個里程碑評估與實作。

3. Given 尚未載入全部（hasMore=true），When 使用者嘗試點擊搜尋，Then 顯示提示「尚未載入全部資料，載入完成後可使用搜尋」，搜尋保持停用。
4. Given 已載入全部（hasMore=false），When 使用者展開搜尋，Then 搜尋功能可用，並顯示一次性提示「已載入全部，現在可使用搜尋」。

- Q: 搜尋功能何時可用？ → A: 採用「圖示常駐但未載完停用」方案；當 hasMore=false（已載完）即自動啟用並顯示提示。

- **FishListItem**：列表中一筆魚的最小可見資料單位（供前端渲染卡片）。

  - 欄位：維持現行後端列表 API 的單筆欄位形狀（本里程碑不新增/移除欄位）。常見包含 `id`, `name`, `image` 或等價欄位與既有指標（如 `has_webp`）。
  - 說明：圖片格式優化（同時提供 WebP + JPEG URL）延後，前端沿用既有欄位與策略。

- **PaginationState**：前端列表分頁與 UI 狀態的單一真相（Single Source of Truth）。
  - 欄位：
    - `cursor: string|null`（目前頁的游標；首批為 `null`）
    - `hasMore: boolean`（是否仍有下一頁）
    - `isLoading: boolean`（是否有進行中的請求，避免並發重複）
    - `error?: string|null`（最近一次載入錯誤訊息；成功後清空）
    - `perPage?: number`（可選；預設 20，用於還原與續載）
  - 說明：與 FR-001 回應 `pageInfo: { hasMore, nextCursor }` 對齊；成功載入後應以回傳的 `nextCursor` 更新本狀態。

## Success Criteria _(mandatory)_

### Measurable Outcomes

- **SC-001**: 在行動裝置良好網路環境下，/fishs 首屏於 1 秒內出現骨架與首批可見內容。
- **SC-002**: 滑至底部觸發續載後，新資料出現的等待時間中位數 ≤ 800ms。
- **SC-003**: 頁面累計版面位移（CLS）≤ 0.1（以占位與固定比例控制）。
- **SC-004**: 續載失敗時，使用者可於 1 次操作內完成重試，且已載內容不受影響（零回退）。
- **SC-005**: 首屏回應負載大小較現況下降 ≥ 30%（以必要欄位裁減與圖片懶載入）。

### Assumptions

- 列表現有篩選/搜尋將保留，並與分頁/游標相容。
- 首批資料量採用保守值以保證首屏體驗（例如 12–20 筆）。

### Clarifications Needed（最多 1–3 項）

1. 影像格式供應策略（關聯 FR-010）
   - A: 一律同時提供 webp 與 jpg 後備連結（可靠但回應體積略增）。
   - B: 僅提供一個主連結，前端自行協商格式（實作簡單，可能降低相容性）。
   - C: 依客戶端能力回傳單一最適格式（需判斷 UA，後端較複雜）。
   - [NEEDS CLARIFICATION: 請擇一]

## Clarifications

### Session 2025-11-04

- Q: /fishs 分頁策略採用哪一種？ → A: 游標分頁（cursor）
- Q: 圖片格式策略採何者？ → A: A（同時提供 WebP + JPEG 後備）
- Q: 關鍵實體（FishListItem、PaginationState）是在做什麼？ → A: FishListItem 是列表展示的最小資料單位（id/name/圖片/標籤等）；PaginationState 是前端分頁與 UI 控制的單一狀態來源（cursor/hasMore/isLoading/error/perPage）。
- Q: 是否修改後端回傳的單筆欄位以支援更多可搜尋或圖片欄位？ → A: 不修改；本里程碑僅導入游標分頁，維持現有 item 欄位，圖片雙 URL 與隱藏搜尋欄位延後。

已決策事項的對應更新：

- FR-001 改為明確採用游標式分頁：請求以 `cursor`（或 `null` 代表首批）與 `perPage`，回應 `items` 與 `pageInfo: { hasMore, nextCursor }`；排序需穩定（建議 `id DESC` 或 `created_at DESC, id DESC`）。
- FR-010 調整為 Deferred：本里程碑不新增圖片欄位或行為，延至後續里程碑考量。
- FR-007 重申：搜尋維持後端為主，不新增隱藏可搜尋欄位；前端不做跨批次本地搜尋擴充。
- Key Entities 補充：`FishListItem` 不更動欄位形狀；`PaginationState` 照舊。
- 補充 Key Entities：具體定義 `FishListItem` 與 `PaginationState` 欄位/型別，並與 FR-001/FR-010 合約對齊。
