# Quickstart — Backend Server-Side Search (006-backend-search)

## Goal

使用 `GET /fishs` 以後端多條件 AND 搜尋與游標式分頁取得精簡魚類列表（`id,name,image_url`）。

## Endpoint

`GET /fishs`

## Query Parameters

| Param             | Type    | Rule                        | Example                    |
| ----------------- | ------- | --------------------------- | -------------------------- |
| name              | string? | ILIKE '%term%'              | `name=ab`                  |
| tribe             | string? | Case-insensitive exact      | `tribe=iratay`             |
| capture_location  | string? | ILIKE '%term%'              | `capture_location=harbor`  |
| capture_method    | string? | ILIKE '%term%'              | `capture_method=net`       |
| processing_method | string? | ILIKE '%term%'              | `processing_method=去魚鱗` |
| food_category     | string? | Case-insensitive exact      | `food_category=raw`        |
| perPage           | int?    | 1–50, invalid→20 (no clamp) | `perPage=20`               |
| last_id           | int?    | >0; previous batch last id  | `last_id=1049`             |

## Response (200)

```
{
  "items": [
    {"id": 1050, "name": "Flying Fish", "image_url": "https://cdn/.../1050.jpg"},
    {"id": 1049, "name": "Tuna", "image_url": "https://cdn/.../1049.jpg"}
  ],
  "pageInfo": {"hasMore": true, "nextCursor": 1049}
}
```

Tail page:

```
{"items": [], "pageInfo": {"hasMore": false, "nextCursor": null}}
```

## Error (422 INVALID_CURSOR)

```
{"error": "INVALID_CURSOR", "message": "Invalid or stale last_id."}
```

Triggers: non-integer, <=0, direction error, stale after filter change.

## Basic Usage Flow (Frontend)

1. 初次載入：不帶 `last_id`，僅附加使用者輸入的搜尋條件；若 `perPage` 未提供或非法 → 後端正規化為 20。
2. 解析回應：渲染 `items`；儲存 `nextCursor`。
3. 續載：當使用者滾到底且 `hasMore=true`，以相同條件 + `last_id=<nextCursor>` 再請求。
4. 錯誤處理：若 422 → 顯示非阻斷錯誤 Banner「參數失效，重新開始？」；重設游標並重新首批查詢。
5. 條件改變：清除舊游標與列表，重新送出首批請求。

## Curl Examples

(僅供參考)

```
# First page fuzzy name
curl 'https://example.com/fishs?name=ab&perPage=20'

# Next page
curl 'https://example.com/fishs?name=ab&perPage=20&last_id=1049'

# Invalid cursor (non-numeric)
curl 'https://example.com/fishs?last_id=abc'
```

## Validation Tips (Testing)

- 斷言第一批 `count(items) <= perPage` 且若 `count == perPage` 則 `hasMore=true`。
- 續載斷言所有新 items 的 id < 之前的 `nextCursor`。
- 422 案例：`last_id=0`, `last_id=-3`, `last_id=abc`, 方向錯誤（用更大的 id）。
- 空集合：`last_id` 指向最小 id 或條件無符合 → `hasMore=false` + `nextCursor=null`。
- 精簡度：檢查是否僅包含三個白名單欄位。

## Performance Checklist

- 查詢時間（本地）測試複合條件 microtime 差值 < `slow_query_ms`。
- 避免載入不必要關聯（確認沒有 `with(...)`）。

## Future Extension Notes

- 若改為不透明游標：可 Base64 編碼 JSON `{"last_id":1049}`；前端仍原樣傳回。
- 若需熱門建議：新增 `/fishs/recommendations?term=...` 分離關注點。

## Done When

- Pest 功能測試 200/422/空集合/續載全綠。
- Payload 減少測試（與舊端點比較）≥30%。
- OpenAPI 與實作回應欄位一致。
