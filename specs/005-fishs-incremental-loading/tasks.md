# 任務 — 魚類列表分批載入（Fishs Incremental Loading）

Feature：魚類列表分批載入（透過 Web 路由 + Inertia 的游標分頁）
Branch：005-fishs-incremental-loading
Feature 目錄：/Users/chungyueh/Herd/tao_among/specs/005-fishs-incremental-loading

## 第 1 階段 — 設定（Setup）

- [ ] T001 驗證列表頁 Web 路由是否存在於 /Users/chungyueh/Herd/tao_among/routes/web.php（Route::get('/fishs', [FishController::class, 'getFishs'])）
- [ ] T002 確認列表頁 Inertia 元件存在於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue

## 第 2 階段 — 基礎（Foundational，跨故事共用）

- [ ] T003 [P] 新增游標工具以編碼/解碼 base64url JSON（PaginationCursor）於 /Users/chungyueh/Herd/tao_among/app/Services/PaginationCursor.php
- [ ] T004 擴充搜尋服務新增 paginate(filters, cursor, perPage) 並回傳 { items, pageInfo } 於 /Users/chungyueh/Herd/tao_among/app/Services/FishSearchService.php
- [ ] T005 定義 perPage 預設與範圍限制（default=20, min=1, max=50）於 /Users/chungyueh/Herd/tao_among/app/Http/Controllers/FishController.php

## 第 3 階段 — 使用者故事 1（P1）：首屏快速可見

故事目標：首屏 1 秒內可見骨架與首批內容；穩定排序（id DESC）；回應 Inertia props：items[] + pageInfo。
獨立驗測：在乾淨瀏覽器開 /fishs，1 秒內出現骨架與首批卡片文字/縮圖；可立即滾動。

- [ ] T006 [US1] 控制器使用 paginate 服務回傳 items/pageInfo props 於 /Users/chungyueh/Herd/tao_among/app/Http/Controllers/FishController.php
- [ ] T007 [US1] 在頁面渲染 props.items 並呈現初始骨架狀態於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue
- [ ] T008 [P] [US1] 在 paginate 查詢中確保以 id DESC 穩定排序於 /Users/chungyueh/Herd/tao_among/app/Services/FishSearchService.php
- [ ] T009 [P] [US1] 保留既有 filters/searchOptions/searchStats props 不變以相容於 /Users/chungyueh/Herd/tao_among/app/Http/Controllers/FishController.php

## 第 4 階段 — 使用者故事 2（P2）：連續瀏覽不中斷（續載與重試）

故事目標：滾至底部自動續載；失敗可重試且不影響已載內容；避免併發重複請求。
獨立驗測：多次觸發續載觀察請求節流/上鎖與狀態提示；故意讓一次請求失敗後，點擊重試僅補該批次。

- [ ] T010 [US2] 控制器新增 cursor/perPage 查詢參數處理與驗證（非法游標回 422）於 /Users/chungyueh/Herd/tao_among/app/Http/Controllers/FishController.php
- [ ] T011 [US2] 在 paginate 計算 hasMore 與 nextCursor（以最後一筆 id）於 /Users/chungyueh/Herd/tao_among/app/Services/FishSearchService.php
- [ ] T012 [P] [US2] 前端實作無限滾動觸發（IntersectionObserver + sentinel）於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue
- [ ] T013 [P] [US2] 實作載入上鎖與 Inertia partial reload（only: ['items','pageInfo']、preserveState、replace）於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue
- [ ] T014 [P] [US2] 顯示錯誤狀態與重試按鈕；重試僅重新請求該批次於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue

## 第 5 階段 — 使用者故事 3（P3）：圖片平滑載入（CLS 控制）

故事目標：圖片懶載入、固定比例占位、失敗有後備處理；插入時不造成版面跳動。
獨立驗測：慢速網路下觀察占位、淡入與 CLS ≤ 0.1；失敗時顯示後備圖不破版。

- [ ] T015 [P] [US3] 卡片加入固定比例容器與骨架占位於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue
- [ ] T016 [P] [US3] 實作圖片懶載入 + 淡入（loading="lazy" + onload class）於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue
- [ ] T017 [P] [US3] 提供圖片載入失敗後備行為（placeholder 或中性背景）於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue

## 最終階段 — 打磨與橫切關注（Polish & Cross-Cutting）

- [ ] T018 [P] 搜尋 UI 開關：於 hasMore=false 前停用並顯示提示於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue
- [ ] T019 [P] 記住列表狀態與滾動位置（Inertia remember/本地狀態）於 /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fishs.vue
- [ ] T020 如實作期間調整 props 名稱/形狀則更新契約於 /Users/chungyueh/Herd/tao_among/specs/005-fishs-incremental-loading/contracts/inertia-fishs-pagination.yaml
- [ ] T021 更新 quickstart 增補偏差或調校指引於 /Users/chungyueh/Herd/tao_among/specs/005-fishs-incremental-loading/quickstart.md

---

## 依賴與順序（Dependencies & Order）

- 故事順序：US1 → US2 → US3
- 基礎任務（T003–T005）須先完成再進 US1
- US2 依賴 US1（控制器/props + 基本渲染）與基礎任務
- US3 可與 US2 後段的前端 UI 任務平行（純 UI 關注）

## 平行執行範例（Parallel Execution Examples）

- 第 2 階段：T003 與 T004 可平行（不同檔案）
- US1：T008 與 T009 可平行（Service 排序 vs Controller props 補充）
- US2：T012/T013/T014 三項前端可由不同人員平行處理（同檔案不同區塊，避免互相覆寫）
- US3：T015/T016/T017 皆為前端 UI 任務可平行

## MVP 建議範圍

- 僅涵蓋 US1：提供游標分頁的首批載入（items/pageInfo），穩定排序與骨架；維持既有 filters/searchOptions/searchStats props。

## 格式驗證（自查）

- 所有任務行皆符合格式：`- [ ] T### [P]? [US#]? 描述（含絕對路徑）`
- User Story 階段任務皆帶有 [US1]/[US2]/[US3] 標記
- Setup/Foundational/Polish 階段任務無故事標記

## 任務統計

- 總任務數：21
- 每個使用者故事任務數：
  - US1：4（T006–T009）
  - US2：5（T010–T014）
  - US3：3（T015–T017）
- 平行機會：T003、T004、T008、T009、T012、T013、T014、T015、T016、T017、T018、T019

## 參考資料

- 設計決策：/Users/chungyueh/Herd/tao_among/specs/005-fishs-incremental-loading/research.md
- 資料模型：/Users/chungyueh/Herd/tao_among/specs/005-fishs-incremental-loading/data-model.md
- 契約：/Users/chungyueh/Herd/tao_among/specs/005-fishs-incremental-loading/contracts/inertia-fishs-pagination.yaml
- 快速指南：/Users/chungyueh/Herd/tao_among/specs/005-fishs-incremental-loading/quickstart.md
