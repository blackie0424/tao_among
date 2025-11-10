# Quickstart — Fishs Incremental Loading

本指南協助你快速對接 /fishs 游標分頁於 Laravel + Inertia Vue 專案。

## 後端（Laravel）

1. 路由：`routes/web.php` 中確保存在 `GET /fishs` 對應 Controller。
2. 控制器：解析 `cursor`、`perPage`；查詢穩定排序（`id DESC`）；計算 `hasMore` 與 `nextCursor`；回傳 Inertia props：
   - `items: FishListItem[]`
   - `pageInfo: { hasMore: boolean, nextCursor?: string }`
3. 驗證：
   - `perPage` 超界回退 20；`cursor` 解碼失敗回 422（頁面可消化錯誤）。

## 前端（Vue + Inertia）

1. 狀態：以 `PaginationState` 管理 `cursor/hasMore/isLoading/error/perPage` 與 `items` 陣列。
2. 初始載入：伺服端渲染首批；前端接手狀態，設置骨架卡與圖片占位。
3. 續載：於接近底部時觸發 Inertia.visit 同一路由，帶入現有 `cursor/perPage`，設定：
   - `only: ['items', 'pageInfo']`
   - `preserveState: true`
   - `replace: true`
4. 失敗：顯示錯誤與重試按鈕；重試僅針對當前批次；成功後清空 `error`。
5. 搜尋：`hasMore=false` 後自動啟用；啟用前圖示停用並提供提示。

## 驗收（Acceptance）

- SC-001：首屏 1s 內可見骨架與首批內容。
- SC-002：續載中位數 ≤ 800ms（以瀏覽器 DevTools 測量）。
- SC-003：CLS ≤ 0.1（固定比例容器 + 骨架）。
- SC-004：重試單次操作完成，已載內容零回退。
- SC-005：首屏回應負載較現況下降 ≥ 30%（比對 Network）。
