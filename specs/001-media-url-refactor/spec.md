# Feature Specification: Media URL Refactor

**Feature Branch**: `001-media-url-refactor`  
**Created**: 2025-10-30  
**Status**: Draft  
**Input**: User description: "重構媒體檔案的網址組合的功能，我們將檔案上傳到supabase後，資料庫都只會紀錄檔案名稱，最後在前端要呈現這些媒體資源時，才會組合成完整的路徑"

## User Scenarios & Testing (mandatory)

### User Story 1 - 前端顯示圖片連結（P1）

當使用者瀏覽魚類清單或詳情頁時，系統能根據資料庫中的圖片檔名，產生可存取的完整圖片網址以供前端顯示。

Why: 這是最常見的媒體需求，直接影響主要頁面可用性。

Independent Test: 呼叫 API 取得魚類資料，檢查回傳的 image 欄位為完整可用網址且能正確 fallback。

Acceptance Scenarios:

1. Given 魚類記錄只有 image 檔名, When 取得 fish 列表, Then 回傳的 image 是完整 URL（has_webp=true 時使用 webp）。
2. Given has_webp=false 或 null, When 取得 fish 列表, Then image 指向原始 images 目錄之檔名（不使用 webp）。
3. Given image 為空, When 取得 fish 列表, Then image 指向預設圖片 default.png 的完整 URL。

---

### User Story 2 - 前端顯示音訊連結（P1）

當使用者瀏覽發音列表或魚種詳情時，系統能根據資料庫的音訊檔名，產生完整音訊網址以供播放。

Why: 發音播放是核心情境之一。

Independent Test: 造訪 /fish/{id}/audio-list 頁面，驗證 audios[].url 皆為完整可用網址。

Acceptance Scenarios:

1. Given audio 檔名存在, When 取得列表, Then 每筆 audio.url 皆為完整 URL。
2. Given audio 檔名為空或 null, When 取得列表, Then 不產生 URL 欄位（音訊以缺席為設計）。

---

### User Story 3 - 簽名上傳 URL 取得（P2）

當前端要上傳圖片或音訊到物件儲存時，系統提供簽名上傳 URL 與應上傳的路徑與新檔名。

Why: 供前端直傳，降低後端負載。

Independent Test: 呼叫 /prefix/api/supabase/signed-upload-url 與 /prefix/api/fish/{id}/supabase/signed-upload-audio-url，回傳 200 並包含 url/path/filename。

Acceptance Scenarios:

1. Given 合法檔名, When 呼叫 image 簽名 API, Then 回傳 200 與完整 url/path/filename。
2. Given 合法檔名與魚種存在, When 呼叫 audio 簽名 API, Then 建立/更新 metadata 成功並回傳 200。
3. Given 檔名格式不符, When 呼叫, Then 回傳 400 與驗證錯誤。

### Edge Cases

- 圖片 has_webp 為 null 或 false 時，一律回退原圖網址（不進行 HEAD 檢查、也不使用 webp）。
- 已存完整 URL 的欄位（歷史資料）不應被再前置 base（需原樣回傳）。
- 音訊檔名為空/null 時不產生 URL 欄位。

## Requirements (mandatory)

### Functional Requirements

- FR-001: 系統必須集中化媒體網址組合邏輯於單一服務（Storage Service）。
- FR-002: 對圖片，若 has_webp=true，回傳 webp 版本完整 URL；若 has_webp=false 或 null，回傳原圖完整 URL（不做 HEAD 檢查）。
- FR-003: 對圖片，若傳入的檔名本身為完整 URL，必須原樣回傳，避免重複前置。
- FR-004: 對音訊，當提供檔名時，回傳完整 URL；檔名為空/null 時，不輸出該欄位（避免誤導）。
- FR-005: 提供簽名上傳 URL API，輸入原始檔名，輸出完整簽名 URL（絕對 URL）、path（含目錄）、filename（新檔名）。
- FR-006: 音訊簽名上傳 API 需於簽名成功後再落資料，若簽名失敗應回傳 500 並不異動資料。
- FR-007: 服務不得在正常流程中進行對外網路探測（如 HEAD），不再保留相容模式。
- FR-008: 呼叫端（Services/Controllers/Models Accessor）需統一呼叫集中服務，並在可得時傳入 has_webp。

### Key Entities

- MediaFile（概念）: { type: image|audio, filename, has_webp?, full_url }
- SignedUpload: { url, path, filename, expires_in }

## Success Criteria (mandatory)

### Measurable Outcomes

- SC-001: 魚類列表/詳情 API 回應中的圖片/音訊連結正確率達 100%（以自動化測試驗證）。
- SC-002: 圖片/音訊簽名上傳 API 成功率 >= 99%（以測試模擬成功回應驗證控制流程）。
- SC-003: 去除在 request 時期對外 HEAD 檢查（非相容模式）後，端到端 API 響應時間在平均情境下降低 ≥ 10%。
- SC-004: 回溯資料（已存完整 URL）呈現不再出現重複前置問題（測試包含此 case）。

## Assumptions

- A-001: 前端僅需最終完整 URL，資料庫僅存檔名。
- A-002: Supabase Storage public bucket 與路徑慣例固定：images/, images/webp/, audio/。
- A-003: has_webp 欄位為圖片層級（以 Fish 模型上的 has_webp 為準）。

## Clarifications

（已確認）

- Q1: has_webp=null 或 false 時，皆回原圖（不使用 webp、不做 HEAD）。
- Q2: 預設圖在 fish.image 為空白或 null 時啟用，檔名為 images/default.png。
- Q3: 當 fish.audio_name 為空白或 null 時，不輸出任何音訊欄位（圖片與音訊處理邏輯不同）。

### Session 2025-10-31

- Q: 本規格所稱的「HEAD」是什麼意思？ → A: 指 HTTP HEAD 方法，用於向遠端物件儲存（如 Supabase Storage）發出「僅取回回應標頭、不抓取內容」的請求；常用來以回應狀態碼（200/404）探測檔案是否存在。本規格已決定不在執行期間使用此方式做決策，以降低延遲與外部依賴。
