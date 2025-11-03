# Feature Specification: Audio Upload Confirm

**Feature Branch**: `001-audio-upload-confirm`  
**Created**: 2025-11-01  
**Status**: Draft  
**Input**: User description: "當使用者上傳發音音訊時，只有在 Supabase 簽名上傳成功且前端實際 PUT 成功後才永久寫入資料庫，否則任何中途失敗都不應留下資料殘留；需要交易化流程與端到端重構測試。"

## User Scenarios & Testing (mandatory)

### User Story 1 - 成功上傳並保存（P1）

使用者錄製語音並上傳，系統在確認雲端實際檔案已成功寫入後，才會在後端建立/更新對應的音訊紀錄與魚種的 `audio_filename`。

Why: 避免資料殘留與壞鏈結，是最核心體驗。

Independent Test: 端到端測試模擬簽名成功、前端 PUT 成功，檢查資料庫與 API 回傳皆一致且 `audios[].url` 可用。

Acceptance Scenarios:

1. Given 簽名上傳成功且前端 PUT 成功, When 呼叫 API 完成流程, Then DB 產生FishAudio且 Fish.audio_filename 更新，API 回傳可用的音訊 URL。
2. Given 多筆上傳在短時間內, When 並行/連續操作, Then 每筆紀錄一致且無覆蓋或殘留。

---

### User Story 2 - 任何一步失敗時不落資料（P1）

當簽名上傳或前端 PUT 任一步驟失敗（逾時/4xx/5xx），系統不應保留任何資料變更，不產生孤兒紀錄或空指標。

Why: 解決現有痛點（看到檔名但 Supabase 無檔案）。

Independent Test: 模擬簽名成功但 PUT 失敗；或簽名失敗。驗證資料庫無異動、API 回錯並指示重試。

Acceptance Scenarios:

1. Given 簽名成功但 PUT 失敗, When 流程結束, Then FishAudio 與 Fish.audio_filename 皆不更新，API 回傳 502/500 並附錯誤訊息。
2. Given 簽名失敗, When 呼叫 API, Then 立即回傳 500 不異動 DB。

---

### User Story 3 - 前端確認回傳（P2）

前端上傳成功後，需以「確認呼叫」告知後端實際 PUT 成功，後端於此時才進行交易式落資料。

Why: 將「簽名」與「持久化」解耦，避免半途資料殘留。

Independent Test: 確認呼叫成功 → DB 異動；確認呼叫缺席或失敗 → DB 無異動。

Acceptance Scenarios:

1. Given 前端已完成 PUT, When 呼叫 confirm API, Then DB 交易成功且回傳 200 與音訊 URL。
2. Given 前端未完成 PUT 或檔案不存在, When 呼叫 confirm API, Then 回傳 409/404 並不異動 DB。

### Edge Cases

- 使用者取消或網路中斷（未呼叫 confirm）：DB 不應有任何變更。
- 重複 confirm 同一路徑：應冪等處理（回 200 並返回現況）。
- 競態條件：多視窗/多次錄音快速操作應不互相覆寫。
- 檔名格式不符/副檔名不支援：回 400 並拒絕流程。
- 已取得簽名網址但未實際上傳：confirm 時應檢查物件不存在並回傳錯誤（例如 409/404），暫存檔案清理機制不應受影響。
- 上傳成功但未呼叫 confirm：暫存區（pending/）中將殘留物件，需由排程清理 TTL 逾期檔案。

## Requirements (mandatory)

### Functional Requirements

- FR-001: 分離「取得簽名上傳 URL」與「確認上傳成功」兩步驟 API。
- FR-002: 只有在確認呼叫（confirm）時，才以交易式落資料（建立 FishAudio、更新 Fish.audio_filename）。
- FR-003: 確認呼叫前需驗證 Supabase 物件存在（可用 HEAD/metadata），若不存在則回 404/409 並不異動 DB。
- FR-004: 簽名失敗回 500；PUT 失敗（由前端偵測）時不呼叫 confirm，自然不產生資料殘留。
- FR-005: 成功完成確認後，API 回傳完整可用的音訊 URL 與 metadata（path、filename）。
- FR-006: confirm API 應冪等，針對相同 filePath 重複呼叫不得造成重複資料或錯誤狀態。
- FR-006: confirm API 應冪等：對相同「fish_id + filePath」之重複呼叫，一律回 200 並返回現況（不產生任何額外異動或錯誤狀態）。
- FR-007: 簽名上傳一律指向暫存路徑（例如 `pending/audio/...`），confirm 成功後才移動至正式路徑（例如 `audio/...`）。
- FR-008: 若 confirm 失敗或未被呼叫，暫存路徑中的孤兒檔案需有清理策略：系統提供排程清理 TTL（例如 1 小時）以前的暫存物件。
- FR-009: confirm 流程中若資料庫交易失敗或任一步驟失敗，需進行補償行為（best-effort）：例如已移動至正式路徑則嘗試刪除該物件，以避免產生孤兒檔案。
- FR-007: 維持現有圖片/音訊 URL 組合規則（已集中於 Storage Service），音訊路徑為 `audio/`。
- FR-008: 端到端測試覆蓋三種情境：成功、簽名失敗、PUT 失敗（未 confirm）。

### Key Entities

- PendingAudio（概念）: { filePath, expiresIn, requestedAt }
- ConfirmUpload: { filePath, filename, fish_id }

## Success Criteria (mandatory)

### Measurable Outcomes

- SC-001: 上傳流程在成功情境下，端到端完成率 99%+（以測試模擬成功回應驗證控制流程）。
- SC-002: 失敗情境（簽名失敗/PUT 失敗）不產生任何資料殘留（測試驗證 DB 無異動且 API 回應正確）。
- SC-003: confirm API 冪等測試通過（重複呼叫無重複異動）。
- SC-004: API 回應中音訊 URL 正確率 100%（已用既有 URL 組合規則）。
# Clarifications

### Session 2025-11-01

- Q: 重複 confirm 的冪等回應策略為何？ → A: 重複 confirm 同一「fish_id + filePath」時一律回 200 並返回現況（idempotent read-after-write）。

## Object Lifecycle & Cleanup

- 暫存區（pending/）：所有簽名上傳一律放置於 `pending/audio/{date}/{uuid}.{ext}`，避免與正式檔案命名或權限混淆。
- 正式區（audio/）：僅在 confirm 成功後才移動至 `audio/{finalName}`（正式可見路徑）。
- 清理策略：
	- 定期背景排程清理 `pending/` 中「最後更新時間」超過 TTL（預設 1 小時）的檔案。
	- confirm 流程失敗時的補償：若已完成移動到正式區但 DB 交易失敗，系統應嘗試刪除正式檔案（best-effort），以降低孤兒檔案風險。
	- 若上傳成功但使用者未呼叫 confirm，檔案將停留於 `pending/`，並由排程自動清理。

