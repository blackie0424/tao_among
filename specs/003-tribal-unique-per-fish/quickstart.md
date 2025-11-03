# Quickstart — Tribal classification unique per fish

## Run tests (backend)

- ./vendor/bin/pest --colors=always

## Endpoints

- GET /prefix/api/fish/{fish_id}/tribal-classifications
- POST /prefix/api/fish/{fish_id}/tribal-classifications
- PUT /prefix/api/fish/{fish_id}/tribal-classifications/{tribe}
   - tribe 允許值：iraraley, ivalino, iranmeilek, yayo, iratay, imorod
   - MVP 不提供 DELETE

## TDD Flow

1. 寫 Feature 測試：
   - 重複部落新增 → 409
   - 列出六個部落狀態
2. Migration：新增唯一索引 (fish_id, tribe)
3. Request 驗證：限制 tribe 於固定清單
4. Controller 實作：POST/PUT 邏輯 + 例外處理
5. 重構：舊有建立/更新路徑統一走同一驗證規則
