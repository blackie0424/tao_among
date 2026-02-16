# 測試腳本說明

## VerifyAudioUploadIntegrity.php

此腳本用於驗證 LINE 音檔上傳到 S3 的完整性。

### 功能

1. **建立測試音檔**：生成模擬 M4A 格式的測試音檔
2. **上傳到 S3**：使用 LineUploadService 上傳音檔
3. **下載並比較**：下載上傳的音檔並驗證內容完整性（round-trip）
4. **驗證 Content-Type**：檢查 S3 上的檔案是否有正確的 Content-Type
5. **生成播放 URL**：提供可在瀏覽器中測試播放的 URL
6. **清理測試檔案**：可選擇是否刪除測試檔案

### 使用方式

```bash
php tests/Scripts/VerifyAudioUploadIntegrity.php
```

### 前置條件

1. 確保 `.env` 檔案中有正確的 S3 設定：

   ```
   AWS_ACCESS_KEY_ID=your-access-key
   AWS_SECRET_ACCESS_KEY=your-secret-key
   AWS_DEFAULT_REGION=your-region
   AWS_BUCKET=your-bucket
   ```

2. 確保 S3 bucket 有正確的權限設定

### 預期輸出

```
=== 音檔上傳完整性驗證腳本 ===

1. 建立測試音檔內容...
   原始音檔大小: 8048 bytes
   音檔格式: M4A (模擬)

2. 初始化上傳服務...
   使用儲存服務: S3StorageService
   音檔目錄: audio

3. 上傳音檔到 S3...
   ✓ 上傳成功
   檔案名稱: 12345678-1234-1234-1234-123456789abc.m4a
   完整路徑: audio/12345678-1234-1234-1234-123456789abc.m4a

4. 下載音檔並驗證完整性...
   下載音檔大小: 8048 bytes
   ✓ 內容完整性驗證通過（round-trip 成功）

5. 驗證 Content-Type...
   Content-Type: audio/mp4
   ✓ Content-Type 正確

6. 生成音檔 URL...
   URL: https://your-bucket.s3.amazonaws.com/audio/12345678-1234-1234-1234-123456789abc.m4a
   ✓ 請在瀏覽器中開啟此 URL 測試播放功能

7. 清理測試檔案...
   是否刪除測試檔案？(y/n): y
   ✓ 測試檔案已刪除

=== 驗證完成 ===
```

### 驗證項目

- ✓ 音檔上傳成功
- ✓ 檔案名稱格式正確（UUID.m4a）
- ✓ 內容完整性（round-trip 驗證）
- ✓ Content-Type 設定正確（audio/mp4）
- ✓ URL 可正常訪問
- ✓ 檔案可在瀏覽器中播放（需手動測試）

### 故障排除

**上傳失敗**

- 檢查 S3 憑證是否正確
- 檢查 bucket 權限設定
- 檢查網路連線

**Content-Type 不正確**

- 確認 LineUploadService 的 uploadLineAudio 方法有設定 ContentType 參數
- 檢查 Storage::disk('s3')->put() 的 options 參數

**無法播放**

- 檢查 S3 bucket 的 CORS 設定
- 檢查檔案的 ACL 權限（應為 public-read）
- 在瀏覽器開發者工具中檢查 Content-Type header

### 相關需求

- Requirements: 1.2, 2.2
- Task: 5. 驗證音檔上傳完整性
