# Design Document

## Overview

本設計文件旨在修復 LINE Bot 語音上傳功能的問題。使用者透過 LINE 錄製 5 秒語音並上傳後，收到的錄音檔案沒有聲音。

核心問題分析：

1. **音檔格式問題**：LINE 傳送的音檔是 M4A 格式（AAC 編碼），可能需要特殊處理
2. **資料流處理**：從 LINE API 下載的 binary stream 可能需要正確的 Content-Type 設定
3. **上傳方式**：`Storage::disk('s3')->put()` 上傳時可能缺少必要的 metadata
4. **架構限制**：LINE 不支援前端直傳 S3，必須透過後端中轉上傳

解決方案：

- 驗證音檔資料流的完整性和格式
- 確保 S3 上傳時設定正確的 Content-Type 和 metadata
- 加強錯誤處理和日誌記錄
- 測試音檔上傳後的可播放性
- 考慮使用 `putFileAs` 或設定額外的 options

## Architecture

### 現有架構

```
LINE Platform (儲存使用者錄音)
    ↓ (Webhook 通知)
LineBotController
    ↓ (handleAudioMessage)
LineBotService (getMessageContent)
    ↓ (下載 binary stream)
LineUploadService (uploadLineAudio)
    ↓ (Storage::disk('s3')->put())
S3 Storage
```

### 問題點

1. **LINE 音檔特性**：

   - 格式：M4A (MPEG-4 Audio)
   - 編碼：AAC
   - Content-Type：audio/mp4 或 audio/m4a

2. **當前上傳方式**：

   ```php
   Storage::disk('s3')->put($fullPath, $audioStream);
   ```

   - 可能缺少 Content-Type 設定
   - 可能缺少 visibility 設定
   - 可能缺少其他必要的 metadata

3. **Web 上傳對比**：
   - Web：前端直接上傳到 S3（使用 presigned URL，自動設定 Content-Type）
   - LINE：後端中轉上傳（需要手動設定 Content-Type）

## Components and Interfaces

### 1. LineUploadService

**當前實作問題：**

```php
// 當前方式：可能缺少必要的 options
$success = Storage::disk('s3')->put($fullPath, $audioStream);
```

**修正方案 1：使用 options 參數**

```php
$success = Storage::disk('s3')->put($fullPath, $audioStream, [
    'visibility' => 'public',
    'ContentType' => 'audio/mp4',  // 或 'audio/m4a'
    'CacheControl' => 'max-age=31536000',
]);
```

**修正方案 2：使用 putFileAs（如果 stream 可以轉換為 file）**

```php
// 將 stream 寫入臨時檔案
$tempFile = tmpfile();
fwrite($tempFile, $audioStream);
$tempPath = stream_get_meta_data($tempFile)['uri'];

// 使用 putFileAs 上傳
$storedPath = Storage::disk('s3')->putFileAs(
    $audioFolder,
    new \Illuminate\Http\File($tempPath),
    $filename,
    ['visibility' => 'public', 'ContentType' => 'audio/mp4']
);

fclose($tempFile);
```

**修正方案 3：使用 S3 Client 直接上傳**

```php
use Aws\S3\S3Client;

$client = new S3Client([
    'version' => 'latest',
    'region' => config('filesystems.disks.s3.region'),
    'credentials' => [
        'key' => config('filesystems.disks.s3.key'),
        'secret' => config('filesystems.disks.s3.secret'),
    ],
]);

$result = $client->putObject([
    'Bucket' => config('filesystems.disks.s3.bucket'),
    'Key' => $fullPath,
    'Body' => $audioStream,
    'ContentType' => 'audio/mp4',
    'ACL' => 'public-read',
]);
```

### 2. LineBotController

**加強日誌記錄：**

```php
protected function handleAudioMessage(MessageEvent $event, string $replyToken): void
{
    // ... 現有程式碼 ...

    // 下載語音內容
    $audioBlob = $this->lineBotService->getMessageContent($messageId);

    // 新增：記錄音檔的詳細資訊
    Log::info('LINE Bot audio details', [
        'userId' => $userId,
        'fishId' => $fishId,
        'messageId' => $messageId,
        'size' => strlen($audioBlob),
        'duration' => $duration,
        'first_bytes' => bin2hex(substr($audioBlob, 0, 16)), // 記錄前 16 bytes
    ]);

    // 儲存音檔
    $this->saveFishAudio($userId, $fishId, $audioBlob, $duration, $replyToken);
}
```

### 3. 音檔驗證功能

**新增音檔驗證方法：**

```php
/**
 * 驗證音檔格式和完整性
 */
protected function validateAudioBlob(string $audioBlob): bool
{
    // 檢查檔案大小
    $size = strlen($audioBlob);
    if ($size < 100) {
        Log::error('Audio blob too small', ['size' => $size]);
        return false;
    }

    // 檢查 M4A 檔案簽名（magic bytes）
    // M4A 檔案通常以 "ftyp" 開頭（在前 12 bytes 內）
    $header = substr($audioBlob, 0, 12);
    if (strpos($header, 'ftyp') === false) {
        Log::warning('Audio blob missing M4A signature', [
            'header' => bin2hex($header)
        ]);
        // 不一定要拒絕，因為有些 M4A 格式可能不同
    }

    return true;
}
```

## Data Models

### Fish Model

**相關欄位：**

- `audio_filename` (string, nullable): 音檔檔案名稱
- `audio_duration` (integer, nullable): 音檔時長（毫秒）

**取得音檔 URL：**

```php
// 確保 URL 包含正確的 Content-Type
$audioUrl = Storage::disk('s3')->url($audioFolder . '/' . $fish->audio_filename);
```

## Correctness Properties

_A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees._

### Property 1: 音檔上傳完整性

_For any_ 音檔資料流，上傳後下載的內容應該與原始內容相同（round-trip 屬性）
**Validates: Requirements 2.2**

### Property 2: 檔案名稱格式正確性

_For any_ 上傳的音檔，回傳的檔案名稱應該符合 UUID 格式並包含正確的副檔名
**Validates: Requirements 2.4**

### Property 3: 時長驗證正確性

_For any_ 音檔時長，當時長 ≤ 5100 毫秒時應該接受，當時長 > 5100 毫秒時應該拒絕
**Validates: Requirements 4.1, 4.3**

### Property 4: 資料庫更新一致性

_For any_ 成功上傳的音檔，資料庫中的 audio_filename 和 audio_duration 應該與上傳的檔案和時長一致
**Validates: Requirements 1.3, 4.4**

### Property 5: URL 生成正確性

_For any_ 有音檔的魚類，生成的 audio_url 應該包含正確的儲存路徑和檔案名稱，且可以正常訪問
**Validates: Requirements 1.4**

### Property 6: Content-Type 正確性

_For any_ 上傳的音檔，S3 上的檔案應該具有正確的 Content-Type (audio/mp4 或 audio/m4a)
**Validates: Requirements 1.5, 2.2**

### Property 7: 錯誤處理完整性

_For any_ 上傳失敗的情況，系統應該記錄錯誤日誌並拋出例外
**Validates: Requirements 2.3, 3.3**

### Property 8: 日誌記錄完整性

_For any_ 上傳操作，日誌應該包含檔案名稱、路徑、資料大小、音檔格式資訊和操作結果
**Validates: Requirements 3.1, 3.2, 3.4**

## Error Handling

### 1. 音檔下載失敗

**情況：** LINE API 無法下載音檔
**處理：**

- 記錄錯誤日誌（包含 messageId 和錯誤訊息）
- 回覆使用者錯誤訊息
- 不更新資料庫

### 2. 音檔時長超過限制

**情況：** 音檔時長 > 5.1 秒
**處理：**

- 記錄警告日誌
- 回覆使用者「錄音超過 5 秒，請重新錄製」
- 不進行上傳

### 3. 音檔格式驗證失敗

**情況：** 音檔資料流不符合預期格式
**處理：**

- 記錄警告日誌（包含檔案簽名）
- 嘗試繼續上傳（因為格式檢查可能不準確）
- 如果上傳失敗，回覆使用者錯誤訊息

### 4. 上傳失敗

**情況：** 無法上傳到 S3
**處理：**

- 記錄錯誤日誌（包含完整堆疊追蹤和音檔資訊）
- 拋出例外
- 回覆使用者「處理音檔時發生錯誤，請稍後再試」
- 清除使用者狀態

### 5. 資料庫更新失敗

**情況：** 無法更新 Fish 記錄
**處理：**

- 記錄錯誤日誌
- 考慮刪除已上傳的音檔（避免孤兒檔案）
- 回覆使用者錯誤訊息
- 清除使用者狀態

## Testing Strategy

### Unit Tests

**LineUploadService 測試：**

- 測試 uploadLineAudio 方法能正確生成檔案名稱
- 測試上傳時設定正確的 Content-Type
- 測試上傳成功時回傳正確的檔案名稱
- 測試上傳失敗時拋出例外
- 測試日誌記錄是否完整

**音檔驗證測試：**

- 測試 validateAudioBlob 能識別有效的 M4A 檔案
- 測試能識別過小的檔案
- 測試能記錄檔案簽名資訊

**LineBotController 測試：**

- 測試音檔時長驗證邏輯
- 測試成功上傳後資料庫更新
- 測試錯誤處理流程
- 測試日誌記錄完整性

### Property-Based Tests

使用 **Pest PHP** 作為測試框架，搭配 **Pest Property Testing Plugin** 進行屬性測試。

**Property 1: 音檔上傳完整性測試**

- 生成隨機音檔內容（模擬 M4A 格式）
- 上傳到 S3
- 下載並比較內容
- 驗證內容一致性

**Property 2: 檔案名稱格式測試**

- 生成隨機音檔
- 調用 uploadLineAudio
- 驗證回傳的檔案名稱符合 UUID 格式
- 驗證副檔名為 m4a

**Property 3: 時長驗證測試**

- 生成不同時長的音檔訊息（0-10000 毫秒）
- 驗證 ≤ 5100 毫秒的被接受
- 驗證 > 5100 毫秒的被拒絕

**Property 4: 資料庫更新測試**

- 生成隨機音檔和時長
- 上傳並更新資料庫
- 驗證資料庫記錄與上傳資料一致

**Property 5: URL 生成測試**

- 生成隨機魚類和音檔
- 取得 audio_url
- 驗證 URL 格式正確且可訪問
- 驗證 Content-Type 正確

**Property 6: Content-Type 測試**

- 上傳音檔到 S3
- 使用 HEAD 請求檢查 Content-Type
- 驗證為 audio/mp4 或 audio/m4a

**Property 7: 錯誤處理測試**

- 模擬各種上傳失敗情況
- 驗證錯誤日誌記錄
- 驗證例外拋出

**Property 8: 日誌記錄測試**

- 執行上傳操作
- 驗證日誌包含所有必要資訊（檔案名稱、路徑、大小、格式、結果）

### Integration Tests

**完整流程測試：**

1. 模擬 LINE webhook 事件（包含音檔訊息）
2. 驗證系統成功下載音檔
3. 驗證音檔上傳到 S3 且 Content-Type 正確
4. 驗證資料庫更新
5. 驗證回覆訊息正確
6. 驗證音檔可以正常下載和播放

**錯誤情況測試：**

1. 測試音檔時長超過限制的情況
2. 測試上傳失敗的情況
3. 測試資料庫更新失敗的情況
4. 驗證錯誤訊息和日誌

### Manual Testing

**實際 LINE 測試：**

1. 使用 LINE 應用程式錄製 5 秒語音
2. 傳送給 LINE Bot
3. 驗證收到成功訊息
4. 在 Web 介面查詢該魚類
5. 播放音檔，確認有聲音且清晰

## Implementation Notes

### 關鍵修改點

1. **LineUploadService.php**

   - 修改 `uploadLineAudio` 方法
   - 加入 Content-Type 設定：
     ```php
     $success = Storage::disk('s3')->put($fullPath, $audioStream, [
         'visibility' => 'public',
         'ContentType' => 'audio/mp4',
         'CacheControl' => 'max-age=31536000',
     ]);
     ```
   - 加強錯誤處理和日誌記錄
   - 新增音檔驗證功能

2. **LineBotController.php**
   - 加強日誌記錄（記錄音檔的前幾個 bytes）
   - 加入音檔驗證調用

### 診斷步驟

1. **檢查上傳的檔案**：

   - 使用 AWS CLI 或 S3 Console 檢查上傳的檔案
   - 確認檔案大小是否正確
   - 確認 Content-Type 是否正確

2. **檢查日誌**：

   - 查看 `LINE Bot audio downloaded` 日誌，確認下載的檔案大小
   - 查看 `LINE Upload: Starting audio upload` 日誌，確認上傳的檔案資訊
   - 查看 `LINE Upload: Audio uploaded successfully` 日誌，確認上傳成功

3. **測試下載**：
   - 直接從 S3 下載檔案
   - 使用音訊播放器播放
   - 確認是否有聲音

### 可能的根本原因

1. **Content-Type 缺失**：S3 上的檔案沒有正確的 Content-Type，導致瀏覽器無法正確播放
2. **資料流損壞**：從 LINE 下載的資料流在傳遞過程中損壞
3. **編碼問題**：M4A 檔案的編碼方式不被瀏覽器支援
4. **檔案截斷**：上傳過程中檔案被截斷

### 向後相容性

- 不影響現有的 Web 上傳流程（使用 presigned URL）
- 不影響其他使用 LineUploadService 的功能
- 修改僅限於 LINE 音檔上傳流程

### 效能考量

- 音檔資料流直接上傳，不經過本地儲存，節省磁碟空間
- 使用 Laravel Storage 的內建優化
- 適當的錯誤處理避免資源洩漏

### 安全性

- 驗證音檔時長，防止過大檔案
- 使用 UUID 生成檔案名稱，避免路徑遍歷攻擊
- 完整的錯誤日誌記錄，便於追蹤問題
- 設定適當的 ACL 和 visibility
