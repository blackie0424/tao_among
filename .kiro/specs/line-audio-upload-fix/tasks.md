# Implementation Plan

- [x] 1. 修改 LineUploadService 加入 Content-Type 設定

  - 修改 `uploadLineAudio` 方法，在 `Storage::disk('s3')->put()` 調用時加入 options 參數
  - 設定 `ContentType` 為 `audio/mp4`
  - 設定 `visibility` 為 `public`
  - 設定 `CacheControl` 為 `max-age=31536000`
  - 加強錯誤處理，確保例外包含詳細資訊
  - _Requirements: 2.1, 2.2, 2.3_

- [x] 1.1 撰寫 LineUploadService 的單元測試

  - 測試 uploadLineAudio 方法能正確生成檔案名稱
  - 測試上傳時設定正確的 Content-Type
  - 測試上傳成功時回傳正確的檔案名稱
  - 測試上傳失敗時拋出例外
  - _Requirements: 2.1, 2.2, 2.4_

- [ ]\* 1.2 撰寫屬性測試：檔案名稱格式正確性

  - **Property 2: 檔案名稱格式正確性**
  - **Validates: Requirements 2.4**

- [ ]\* 1.3 撰寫屬性測試：Content-Type 正確性

  - **Property 6: Content-Type 正確性**
  - **Validates: Requirements 1.5, 2.2**

- [x] 2. 加強 LineBotController 的日誌記錄

  - 在 `handleAudioMessage` 方法中加入詳細的音檔資訊日誌
  - 記錄音檔的前 16 bytes（使用 bin2hex）
  - 記錄音檔大小、時長、messageId
  - 確保所有關鍵步驟都有日誌記錄
  - _Requirements: 3.1, 3.2, 3.4_

- [ ]\* 2.1 撰寫屬性測試：日誌記錄完整性

  - **Property 8: 日誌記錄完整性**
  - **Validates: Requirements 3.1, 3.2, 3.4**

- [x] 3. 新增音檔驗證功能

  - 在 LineBotController 中新增 `validateAudioBlob` 方法
  - 檢查音檔大小（至少 100 bytes）
  - 檢查 M4A 檔案簽名（magic bytes，尋找 "ftyp"）
  - 記錄驗證結果到日誌
  - 在 `handleAudioMessage` 中調用驗證方法
  - _Requirements: 1.1, 1.2_

- [x] 3.1 撰寫音檔驗證的單元測試

  - 測試 validateAudioBlob 能識別有效的 M4A 檔案
  - 測試能識別過小的檔案
  - 測試能記錄檔案簽名資訊
  - _Requirements: 1.1, 1.2_

- [x] 4. 更新 LineUploadService 的 uploadLineImage 方法

  - 同樣加入 Content-Type 設定（image/jpeg）
  - 保持與 uploadLineAudio 一致的錯誤處理
  - 加強日誌記錄
  - _Requirements: 2.1, 2.3_

- [x] 5. 驗證音檔上傳完整性

  - 建立測試腳本，上傳測試音檔到 S3
  - 下載並比較內容
  - 驗證 Content-Type 是否正確
  - 驗證檔案可以正常播放
  - _Requirements: 1.2, 2.2_

- [ ]\* 5.1 撰寫屬性測試：音檔上傳完整性

  - **Property 1: 音檔上傳完整性**
  - **Validates: Requirements 2.2**

- [x] 6. 測試時長驗證邏輯

  - 驗證 LineBotController 中的時長檢查邏輯
  - 確保 5100 毫秒的容差正確實作
  - 測試邊界條件（5000, 5100, 5101 毫秒）
  - _Requirements: 4.1, 4.2, 4.3_

- [ ]\* 6.1 撰寫屬性測試：時長驗證正確性

  - **Property 3: 時長驗證正確性**
  - **Validates: Requirements 4.1, 4.3**

- [x] 7. 驗證資料庫更新邏輯

  - 測試 saveFishAudio 方法的資料庫更新
  - 確保 audio_filename 和 audio_duration 正確儲存
  - 測試 Fish 不存在時的錯誤處理
  - _Requirements: 1.3, 4.4_

- [ ]\* 7.1 撰寫屬性測試：資料庫更新一致性

  - **Property 4: 資料庫更新一致性**
  - **Validates: Requirements 1.3, 4.4**

- [x] 8. 測試 URL 生成功能

  - 驗證 Fish model 的 audio_url 屬性
  - 確保 URL 包含正確的路徑和檔案名稱
  - 測試 URL 可以正常訪問
  - _Requirements: 1.4_

- [ ]\* 8.1 撰寫屬性測試：URL 生成正確性

  - **Property 5: URL 生成正確性**
  - **Validates: Requirements 1.4**

- [x] 9. 加強錯誤處理

  - 確保所有例外都有詳細的錯誤訊息
  - 確保所有錯誤都有完整的堆疊追蹤記錄
  - 測試各種錯誤情況（下載失敗、上傳失敗、資料庫失敗）
  - 確保使用者狀態在錯誤時被正確清除
  - _Requirements: 2.3, 3.3_

- [ ]\* 9.1 撰寫屬性測試：錯誤處理完整性

  - **Property 7: 錯誤處理完整性**
  - **Validates: Requirements 2.3, 3.3**

- [x] 10. 整合測試

  - 建立完整的 LINE webhook 模擬測試
  - 測試從接收訊息到儲存音檔的完整流程
  - 驗證回覆訊息正確
  - 驗證音檔可以正常播放
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [ ]\* 10.1 撰寫整合測試：完整流程測試

  - 模擬 LINE webhook 事件（包含音檔訊息）
  - 驗證系統成功下載音檔
  - 驗證音檔上傳到 S3 且 Content-Type 正確
  - 驗證資料庫更新
  - 驗證回覆訊息正確
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 11. 手動測試與驗證

  - 使用實際的 LINE 應用程式錄製語音
  - 傳送給 LINE Bot
  - 驗證收到成功訊息
  - 在 Web 介面查詢該魚類
  - 播放音檔，確認有聲音且清晰
  - 檢查 S3 上的檔案 metadata
  - _Requirements: 1.5_

- [x] 12. 文件更新

  - 更新 LineUploadService 的 PHPDoc 註解
  - 記錄 Content-Type 設定的重要性
  - 更新 README 或相關文件，說明 LINE 音檔上傳的特殊處理
  - _Requirements: 所有_

- [x] 13. Checkpoint - 確保所有測試通過
  - 確保所有測試通過，詢問使用者是否有問題
