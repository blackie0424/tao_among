# Requirements Document

## Introduction

本功能旨在修復 LINE Bot 語音上傳功能的問題。目前使用者透過 LINE 介面錄製 5 秒語音並上傳到 S3 後，收到的錄音檔案沒有聲音。根據日誌分析，問題出在 LineUploadService 使用了錯誤的 Storage 方法，導致音檔上傳失敗或損壞。

## Glossary

- **LINE Bot**：透過 LINE Messaging API 提供的聊天機器人服務
- **M4A**：MPEG-4 Audio 格式，LINE 語音訊息的預設格式
- **S3**：Amazon Simple Storage Service，雲端物件儲存服務
- **Storage Service**：應用程式中的儲存服務抽象層，支援 S3 和 Supabase
- **Audio Stream**：從 LINE API 下載的音檔二進位資料流
- **LineUploadService**：專門處理 LINE Bot 檔案上傳的服務類別
- **StorageServiceInterface**：定義儲存服務標準介面的契約

## Requirements

### Requirement 1

**User Story:** 作為使用者，我想要透過 LINE Bot 錄製並上傳語音，以便系統能正確儲存並播放我的錄音。

#### Acceptance Criteria

1. WHEN 使用者透過 LINE 錄製 5 秒以內的語音 THEN the system SHALL 成功接收並下載音檔資料流
2. WHEN the system 接收到 LINE 音檔資料流 THEN the system SHALL 正確上傳音檔到 S3 儲存空間
3. WHEN 音檔上傳完成 THEN the system SHALL 儲存正確的檔案名稱和音檔時長到資料庫
4. WHEN 使用者查詢該魚類資料 THEN the system SHALL 回傳可正常播放的音檔 URL
5. WHEN 音檔播放時 THEN the system SHALL 播放完整且清晰的錄音內容

### Requirement 2

**User Story:** 作為開發者，我想要 LineUploadService 使用正確的 Storage API，以確保音檔能正確上傳到雲端儲存。

#### Acceptance Criteria

1. WHEN LineUploadService 上傳音檔 THEN the service SHALL 使用 Laravel Storage facade 的正確方法
2. WHEN 上傳音檔到 S3 THEN the service SHALL 保持音檔的完整性和格式
3. WHEN 上傳過程發生錯誤 THEN the system SHALL 記錄詳細的錯誤日誌並拋出例外
4. WHEN 音檔上傳成功 THEN the service SHALL 回傳正確的檔案名稱

### Requirement 3

**User Story:** 作為系統管理員，我想要系統記錄完整的上傳流程日誌，以便追蹤和診斷問題。

#### Acceptance Criteria

1. WHEN 開始上傳音檔 THEN the system SHALL 記錄檔案名稱、路徑和資料大小
2. WHEN 上傳成功 THEN the system SHALL 記錄成功訊息和最終檔案路徑
3. WHEN 上傳失敗 THEN the system SHALL 記錄錯誤訊息和完整的堆疊追蹤
4. WHEN 處理 LINE 音檔 THEN the system SHALL 記錄音檔時長和格式資訊

### Requirement 4

**User Story:** 作為使用者，我想要系統驗證音檔時長，以確保只接受符合規定的錄音。

#### Acceptance Criteria

1. WHEN 使用者上傳音檔 THEN the system SHALL 驗證音檔時長不超過 5.1 秒
2. WHEN 音檔超過時長限制 THEN the system SHALL 拒絕上傳並回傳錯誤訊息
3. WHEN 音檔符合時長限制 THEN the system SHALL 繼續處理上傳流程
4. WHEN 儲存音檔資訊 THEN the system SHALL 記錄實際的音檔時長（毫秒）

### Requirement 5

**User Story:** 作為開發者，我想要確保 Storage 服務的一致性，以便在不同儲存後端（S3/Supabase）之間切換。

#### Acceptance Criteria

1. WHEN 使用 StorageServiceInterface THEN the implementation SHALL 提供一致的上傳方法
2. WHEN 切換儲存後端 THEN the system SHALL 不需要修改 LineUploadService 的程式碼
3. WHEN 上傳檔案 THEN the service SHALL 使用 StorageServiceInterface 定義的標準方法
4. WHEN 取得檔案 URL THEN the service SHALL 使用 StorageServiceInterface 的 getUrl 方法
