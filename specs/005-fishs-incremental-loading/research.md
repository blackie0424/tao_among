# Phase 0 Research — Fishs Incremental Loading

本研究彙整本里程碑的關鍵抉擇、理由與替代方案，並解決既有規格中的「NEEDS CLARIFICATION」與矛盾敘述。

## 決策一：路由與資料流（Web 路由 + Inertia partial reload）

- Decision: 使用 Web 路由 `GET /fishs`，以 Query 參數 `cursor`、`perPage` 承載分頁狀態；回應以 Inertia props 形式提供 `items` 與 `pageInfo`，後續載入採 Inertia partial reload（only/replace）。
- Rationale: 符合現有 SPA 結構與慣例；避免引入額外 API 路由；便於前端合併載入與狀態保存。
- Alternatives considered:
  - REST API 專用端點：需新增 API 路由與資料序列化層，帶來不必要的改動量（本期避免）。
  - WebSocket/Streaming：超出本期目標且複雜度過高。

## 決策二：排序與游標格式（id DESC + Opaque Cursor）

- Decision: 採用 `id DESC` 穩定排序；游標為「不透明字串」以 base64url(JSON) 表示，如 `{"last_id":123}`，便於日後擴充而不破壞相容性。
- Rationale: `id` 單欄位足以提供穩定遞減順序；以不透明游標可隱藏實作並維持未來彈性（例如日後改為 `created_at DESC, id DESC` 時，JSON 可攜帶複合鍵）。
- Alternatives considered:
  - 直接使用純數字 `last_id`：最簡單，但未來改動游標含義時會破壞相容性。
  - `created_at DESC, id DESC`：更嚴謹，但本期不需要跨節點時間序細節；可留作未來擴充。

## 決策三：perPage 預設與界線

- Decision: `perPage` 預設 20；允許範圍 1–50，超界回退至預設。
- Rationale: 20 具備良好首屏密度與載入時間平衡；限制上限避免回應過大。
- Alternatives considered:
  - 固定值（不接受參數）：較簡單但不利 A/B 與調校。
  - 更高上限（>100）：會拉高回應時間與行動流量成本。

## 決策四：回應 Envelope（Props 形狀）

- Decision: Inertia props 僅新增分頁 Envelope，保持 `items` 單筆欄位完全不動：
  - `items: FishListItem[]`
  - `pageInfo: { hasMore: boolean, nextCursor?: string }`
- Rationale: 嚴守「不更動單筆欄位」限制；降低風險；前端可直接 append。
- Alternatives considered:
  - 加入統計欄位或快取提示：非必要，延至後續里程碑。

## 決策五：錯誤處理與重試

- Decision:
  - 非法參數（如游標無法解碼、perPage 超界）回傳 422；頁面顯示錯誤提示與「重試」；重試僅針對當前批次，不影響已載內容。
  - 前端以 `isLoading` 上鎖避免併發請求；請求失敗時解除鎖，允許重試。
- Rationale: 維持零回退體驗與簡潔失敗處理路徑；保障狀態一致性。
- Alternatives considered:
  - 400 Bad Request：亦可；但 422 對「參數格式錯誤」語意更清楚。

## 決策六：狀態保存與返回還原

- Decision: 前端使用 Inertia 的 remember 機制（或等效手段）保存 `items`、`pageInfo`、滾動位置與搜尋啟用旗標；partial reload 時使用 `preserveState: true, replace: true`。
- Rationale: 返回列表時不重頭載入；replace 避免歷史堆疊；preserveState 保持已載列表與滾動。
- Alternatives considered:
  - 僅依賴瀏覽器 Back/Forward Cache：不可靠且不可預期。

## 決策七：搜尋啟用時機（UI 控制）

- Decision: 採「圖示常駐但停用直到 `hasMore=false`」；到達終點自動啟用並顯示一次性提示。
- Rationale: 與既有規格對齊，避免混淆使用者，且不牽涉後端欄位變更。
- Alternatives considered:
  - 立即可用的前端過濾：會導致「僅對已載資料生效」的誤解。
  - 後端搜尋擴充：超出本期範圍。

## 決策八：圖片策略（FR-010 Deferred）

- Decision: 本期不新增圖片雙 URL 或欄位；沿用現有欄位與 `has_webp` 指標；將「同時提供 WebP 與 JPEG」延至後續評估。
- Rationale: 避免改動單筆欄位與回應體積膨脹；聚焦分頁機制。
- Alternatives considered:
  - 同時提供 WebP + JPEG（原先 A 案）：增加體積與實作成本，與本期目標相違。
  - 僅一種格式：相容性或效能皆有折衷，本期不處理。

## 決策九：可及性與 CLS 控制

- Decision: 使用固定比例容器與骨架占位，圖片 onload 後淡入；列表插入時保留固定高度避免版面跳動。
- Rationale: 滿足 SC-003（CLS ≤ 0.1）。
- Alternatives considered:
  - 自動高度圖片：易產生版面跳動。

---

以上決策消除規格中的模糊點與矛盾：特別是將原先「同時提供 WebP + JPEG」改為本期延後（Deferred），並將排序、游標、perPage、回應形狀、錯誤處理與 UI 行為全部具體化。
