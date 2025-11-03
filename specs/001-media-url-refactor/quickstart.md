# Quickstart: Media URL Refactor

## What changes

- 集中組合媒體完整 URL：使用 `SupabaseStorageService::getUrl(type, filename, hasWebp)`
- 圖片：`hasWebp=true→webp`，`false/null→原圖`，空值給 `images/default.png`
- 音訊：有檔名才產出 URL 欄位
- 簽名上傳 API：回傳絕對 URL，不要在 controller 再組 base URL

## Callers to update

- FishService：傳入 `has_webp`、音訊空值缺席
- CaptureRecord accessor：帶入相關 Fish 的 `has_webp`
- FishNoteController create：傳入 `has_webp`
- UploadController：使用服務回傳的絕對 URL，audio 簽名失敗不得異動 DB

## Tests

- 後端：`./vendor/bin/pest`（單元 + 功能）
- 調整 Feature 測試對簽名上傳回傳格式的期望（已為絕對 URL）
