# Phase 1 Data Model — Fishs Incremental Loading

## Entities

### FishListItem（單筆不變更）

- 描述：供前端卡片渲染的最小資料單位。
- 欄位（沿用現況，舉例）：
  - `id: number`
  - `name: string`
  - `image_url: string`（由後端計算屬性提供；現有 `has_webp` 等指標照舊）
  - 其他現有欄位（不新增/不刪除）

### PageInfo（分頁信封）

- 欄位：
  - `hasMore: boolean`
  - `nextCursor?: string`（不透明）

### PaginationState（前端狀態）

- 欄位：
  - `cursor: string|null`（目前位置）
  - `hasMore: boolean`
  - `isLoading: boolean`
  - `error?: string|null`
  - `perPage?: number`（預設 20）

## Validation Rules

- 請求參數：

  - `cursor`: 可為 `null`/未提供 或 base64url 字串；無法解碼則 422。
  - `perPage`: 整數；允許範圍 1–50；超界回退為預設 20。

- 回應：
  - `items`: 陣列；每筆符合 FishListItem 現況欄位。
  - `pageInfo.hasMore`: 布林；必填。
  - `pageInfo.nextCursor`: 當 `hasMore=true` 時必填，否則可省略。

## State Transitions

1. 初始：`cursor=null, hasMore=true, isLoading=true` → 請求首批。
2. 成功：附加 `items`，以回傳 `nextCursor` 更新 `cursor`；同步 `hasMore`。
3. 失敗：`error` 設定訊息；`isLoading=false`；允許重試。
4. 終止：`hasMore=false` → 停止自動續載；啟用搜尋 UI。
