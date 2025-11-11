<!--
Sync Impact Report
- Version change: 1.0.0 → 1.1.0
- Modified principles: 新增 VIII. 文件與互動語言一致性
- Removed sections: 舊模板尾端未填寫佔位符（已清除）
- Follow-up TODOs: 無（模板已更新）
-->

# Tao Among Constitution

驗證：PR Review 清單 MUST 包含「語言一致性」；若出現僅英文敘述區塊，PR 描述 MUST 說明原因與時效。

## I. MVP-First & 避免過度設計

每次調整 MUST 先最小化可交付價值；拒絕未被使用者故事或測試覆蓋支撐的額外抽象；避免因預期未來而增加耦合。過度設計風險 SHOULD 在 Reviewer 留言中被標示並要求縮減。

## II. Contract-First API

所有後端新功能 MUST 先提交或更新 OpenAPI（`/api/documentation` 可驗證）；Controller / Service 編碼前 MUST 獲得規格（FR/SC）對齊。破壞性合約變更 MUST 於 README 或 Migrations 指令註記遷移步驟。

## III. Test-First & 可測性

功能程式碼提交前 MUST 具備：至少 1 個快樂路徑 + 1 個邊界案例（Pest / Vitest）。無法測試的結構（例如過深巢狀或靜態耦合） MUST 在重構後才允許合併。測試名稱 SHOULD 清晰描述行為與預期。

## IV. Quality Gates

CI 中 Build、Lint/型別、Pest、Vitest 必須全綠（MUST）才能合併。若因外部相依暫時失敗，需在 PR 描述提供臨時豁免理由與修復 ETA（SHOULD < 7 天）。

## V. Simplicity & Readability

程式碼 MUST 避免不必要層級與魔法字串；偏好明確資料流。命名 SHOULD 採語意而非技術實作細節。審查時若閱讀需要追蹤 >2 個檔案才能理解單一行為，需考慮拆分或提取函式。

## VI. Observability & 可追溯性

關鍵流程（查詢、上傳、交易） MUST 具備結構化紀錄（user id, duration, count）。效能或資料品質議題 SHOULD 能以日誌/指標（例如慢查詢 > 500ms）快速定位。N+1 查詢 MUST 於審查被攔截並以 eager loading 或批次查詢修正。

## VII. Performance & Data Integrity

批次作業 MUST 使用交易（若跨多張表）；大量讀取 SHOULD 使用分頁或串流。索引新增/調整 MUST 在遷移註記影響面。任何會放大 I/O 的迴圈（例如在 for 迴圈中執行 ORM save） MUST 提前審查重構。

## VIII. 文件與互動語言一致性

所有互動式文件（spec、plan、tasks、checklists） MUST 使用正體中文敘述；必要技術名詞（例如 Model、Controller、OpenAPI、Transaction）可保留英文。規範語氣 MUST 使用「MUST / SHOULD / MAY」。違反需在 PR 描述備註並獲得 Reviewer 認可。

## 執行約束（技術棧與命名慣例）

- 後端：PHP 8.x（Laravel + Eloquent + Pest）
- 前端：Vue 3 + Inertia.js + Tailwind + Vitest
- 資料庫：PostgreSQL（prod），SQLite（tests）
- 媒體：Supabase Storage（透過 Service 層封裝）
- Vue 元件命名：功能語意；頁面於 `resources/js/Pages/`；共用全域元件於 `resources/js/Components/Global/`

## 開發流程與品質關卡

1. Spec → Plan → Tasks：Spec MUST 具使用者故事/FR/SC；Plan MUST 明確架構與分層；Tasks 可獨立交付與測試。
2. Contract‑First：OpenAPI/合約先行，審核後才撰寫控制器。
3. Test‑First：測試先於實作；未覆蓋路徑不可合併。
4. 文件驗收：README / quickstart / 合約 同步更新；可重現步驟 MUST 被記錄。
5. CI Gates：所有 Gate 綠燈才可合併；不得以跳過測試形式加速。

## Governance

本憲章凌駕其他流程文件；任何衝突 MUST 以憲章為準。修改流程：PR 中列出動機、影響、遷移計畫；維運或技術負責人審核。版本：SemVer（MAJOR 刪改原則、MINOR 新增或擴充、PATCH 文字釐清）。PR 描述 MUST 對齊關鍵原則（II, III, IV, VIII）。

**Version**: 1.1.0 | **Ratified**: 2025-11-09 | **Last Amended**: 2025-11-11
