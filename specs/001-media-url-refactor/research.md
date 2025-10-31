# Research: Media URL Refactor

## Decision 1: 不做 HEAD，相依 has_webp

- Rationale: 減少外部網路呼叫，簡化路徑決策，降低延遲與失敗點。
- Alternatives: HEAD 檢查（相容模式）、批次預先檢查（離線任務）。
- Outcome: has_webp=true → webp；false/null → 原圖。

## Decision 2: 完整 URL 早退

- Rationale: 回溯資料可能已存完整 URL，避免重複前置導致錯誤。
- Alternatives: 遷移資料清洗；風險較高且無立即效益。
- Outcome: 檔名若是 http/https，直接原樣回傳。

## Decision 3: 簽名上傳回絕對 URL

- Rationale: 前端直傳簡化、減少耦合；避免 controller 再組字串。
- Alternatives: 回傳相對 path 讓前端組合；增加前端認知負擔。
- Outcome: API 回傳 { url(絕對), path, filename }。

## Decision 4: 音訊空值不輸出欄位

- Rationale: 以欄位缺席代表「尚未有音訊」，避免 null 造成型別歧義。
- Alternatives: 回傳 null；需前端再判斷，意義不如缺席清楚。
- Outcome: 音訊欄位僅在有檔名時存在。

## Open Risks

- 舊測試期望可能與新行為不同（例如 controller 串接簽名 URL）。已調整 UploadController 使用服務回傳的絕對 URL。
- 個別呼叫端未傳入 has_webp 可能觸發回退邏輯；已逐步補上，持續巡檢。
