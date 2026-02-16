<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LINE Bot 專用的檔案上傳服務
 *
 * 此服務處理從 LINE Messaging API 下載的媒體檔案（音檔和圖片），
 * 並將其上傳到 S3 儲存空間。
 *
 * ## 與 Web 版本的差異
 *
 * - **Web**: 前端直接上傳到 S3 (使用 presigned URL)
 *   - Content-Type 由前端瀏覽器自動設定
 *   - 不經過後端伺服器，節省頻寬
 *
 * - **LINE**: 後端從 LINE 下載後上傳到 S3 (使用 Storage facade)
 *   - 必須手動設定 Content-Type，否則瀏覽器無法正確播放/顯示
 *   - 需要經過後端中轉，因為 LINE API 不支援前端直傳
 *
 * ## Content-Type 的重要性
 *
 * **關鍵問題**: 如果上傳時未設定正確的 Content-Type，S3 會使用預設值
 * (application/octet-stream)，導致：
 * - 音檔無法在瀏覽器中播放（顯示為下載而非播放）
 * - 圖片無法正常顯示
 * - 媒體播放器無法識別檔案格式
 *
 * **解決方案**: 在 Storage::put() 的第三個參數中明確設定：
 * - 音檔: ContentType => 'audio/mp4' (LINE 使用 M4A 格式，MIME type 為 audio/mp4)
 * - 圖片: ContentType => 'image/jpeg'
 *
 * ## LINE 音檔格式說明
 *
 * LINE 語音訊息使用 M4A 格式：
 * - 容器格式: MPEG-4 Part 14 (MP4)
 * - 音訊編碼: AAC (Advanced Audio Coding)
 * - MIME type: audio/mp4 或 audio/m4a
 * - 檔案簽名: 包含 "ftyp" magic bytes
 * - 最大時長: 5 秒（系統容差 5.1 秒）
 *
 * ## 錯誤處理
 *
 * 所有方法都包含完整的錯誤處理：
 * - 詳細的日誌記錄（包含檔案資訊、錯誤訊息、堆疊追蹤）
 * - AWS S3 特定錯誤的捕獲和處理
 * - 上傳後驗證檔案是否真的存在於 S3
 * - 拋出包含詳細資訊的例外，便於除錯
 *
 * @package App\Services
 * @see \App\Services\LineBotService 用於從 LINE API 下載媒體內容
 * @see \App\Http\Controllers\LineBotController 使用此服務處理 LINE webhook
 */
class LineUploadService
{
    protected StorageServiceInterface $storageService;

    public function __construct(StorageServiceInterface $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * 上傳 LINE 音檔到 S3
     *
     * 此方法處理從 LINE Messaging API 下載的音檔 stream，並上傳到 S3。
     *
     * ## 重要：Content-Type 設定
     *
     * **必須**設定正確的 Content-Type (audio/mp4)，原因：
     * 1. LINE 語音訊息使用 M4A 格式（MPEG-4 Audio，AAC 編碼）
     * 2. 如果不設定，S3 會使用預設的 application/octet-stream
     * 3. 瀏覽器會將檔案視為下載而非媒體，導致無法播放
     * 4. HTML5 audio 元素需要正確的 MIME type 才能播放
     *
     * ## 上傳選項說明
     *
     * - **ContentType**: 'audio/mp4' - M4A 檔案的標準 MIME type
     * - **CacheControl**: 'max-age=31536000' - 快取一年，提升效能
     *
     * 注意：不使用 'visibility' => 'public' 參數，因為 S3 bucket 設定為
     * "Bucket owner enforced" 模式，不允許使用 ACL。公開訪問由 bucket policy 控制。
     *
     * ## 處理流程
     *
     * 1. 生成唯一的 UUID 檔案名稱
     * 2. 驗證 stream 不為空
     * 3. 記錄上傳開始日誌（包含檔案資訊）
     * 4. 使用 Storage::put() 上傳，設定完整的 metadata
     * 5. 捕獲並處理 AWS S3 特定錯誤
     * 6. 驗證檔案確實存在於 S3
     * 7. 記錄成功日誌
     *
     * ## 錯誤處理
     *
     * - 空 stream: 拋出例外
     * - S3 上傳失敗: 記錄 AWS 錯誤碼和訊息，拋出詳細例外
     * - 上傳後檔案不存在: 拋出例外
     * - 所有錯誤都會記錄完整的堆疊追蹤
     *
     * @param string|resource $audioStream LINE 音檔 binary stream
     *                                     可以是字串或 resource（從 LINE API 下載）
     * @param string|null $extension 檔案副檔名（預設 'm4a'）
     *                               LINE 語音訊息固定使用 M4A 格式
     *
     * @return string 儲存的檔案名稱（UUID.m4a 格式）
     *                例如: "550e8400-e29b-41d4-a716-446655440000.m4a"
     *
     * @throws \Exception 當上傳失敗時，包含詳細的錯誤資訊：
     *                    - 空 stream
     *                    - S3 上傳失敗（包含 AWS 錯誤碼）
     *                    - 上傳後驗證失敗
     *
     * @see \App\Services\LineBotService::getMessageContent() 取得音檔 stream
     * @see \App\Http\Controllers\LineBotController::handleAudioMessage() 使用此方法
     */
    public function uploadLineAudio($audioStream, ?string $extension = 'm4a'): string
    {
        $filename = null;
        $fullPath = null;
        
        try {
            // 生成唯一檔名
            $filename = Str::uuid() . '.' . $extension;
            
            // 取得音檔目錄
            $audioFolder = $this->storageService->getAudioFolder();
            $fullPath = $audioFolder . '/' . $filename;
            
            // 計算資料大小（用於日誌記錄）
            $dataSize = is_string($audioStream) ? strlen($audioStream) : 'unknown (stream)';
            
            // 驗證資料大小
            if (is_string($audioStream) && strlen($audioStream) === 0) {
                throw new \Exception('Audio stream is empty');
            }
            
            Log::info('LINE Upload: Starting audio upload', [
                'filename' => $filename,
                'path' => $fullPath,
                'data_size' => $dataSize,
                'extension' => $extension,
            ]);
            
            // 上傳 stream 到 S3，設定正確的 Content-Type 和其他 metadata
            // 這些設定對於確保音檔能在瀏覽器中正確播放至關重要
            // 注意：不使用 'visibility' => 'public'，因為 bucket 使用 Bucket owner enforced 模式
            // 公開訪問由 bucket policy 控制
            try {
                $success = Storage::disk('s3')->put($fullPath, $audioStream, [
                    'ContentType' => 'audio/mp4',
                    'CacheControl' => 'max-age=31536000',
                ]);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                Log::error('LINE Upload: S3 exception during upload', [
                    'filename' => $filename,
                    'path' => $fullPath,
                    'aws_error_code' => $e->getAwsErrorCode(),
                    'aws_error_message' => $e->getAwsErrorMessage(),
                    'status_code' => $e->getStatusCode(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                throw new \Exception(
                    'S3 upload failed: ' . $e->getAwsErrorMessage() . ' (Code: ' . $e->getAwsErrorCode() . ')',
                    $e->getStatusCode(),
                    $e
                );
            }
            
            if (!$success) {
                throw new \Exception('Failed to upload audio stream to S3: Storage::put returned false');
            }
            
            // 驗證檔案是否真的存在於 S3
            if (!Storage::disk('s3')->exists($fullPath)) {
                throw new \Exception('Audio file was not found in S3 after upload');
            }
            
            Log::info('LINE Upload: Audio uploaded successfully', [
                'filename' => $filename,
                'path' => $fullPath,
                'data_size' => $dataSize,
                'exists_in_s3' => true,
            ]);
            
            return $filename;
            
        } catch (\Exception $e) {
            Log::error('LINE Upload: Failed to upload audio', [
                'filename' => $filename,
                'path' => $fullPath,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception(
                'Failed to upload LINE audio to S3: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * 上傳 LINE 圖片到 S3
     *
     * 此方法處理從 LINE Messaging API 下載的圖片 stream，並上傳到 S3。
     *
     * ## 重要：Content-Type 設定
     *
     * **必須**設定正確的 Content-Type (image/jpeg)，原因：
     * 1. LINE 圖片訊息使用 JPEG 格式
     * 2. 如果不設定，S3 會使用預設的 application/octet-stream
     * 3. 瀏覽器會將檔案視為下載而非圖片，導致無法正常顯示
     * 4. HTML img 元素需要正確的 MIME type 才能渲染
     *
     * ## 上傳選項說明
     *
     * - **ContentType**: 'image/jpeg' - JPEG 圖片的標準 MIME type
     * - **CacheControl**: 'max-age=31536000' - 快取一年，提升效能
     *
     * 注意：不使用 'visibility' => 'public' 參數，因為 S3 bucket 設定為
     * "Bucket owner enforced" 模式，不允許使用 ACL。公開訪問由 bucket policy 控制。
     *
     * ## 處理流程
     *
     * 1. 生成唯一的 UUID 檔案名稱
     * 2. 驗證 stream 不為空
     * 3. 記錄上傳開始日誌（包含檔案資訊）
     * 4. 使用 Storage::put() 上傳，設定完整的 metadata
     * 5. 捕獲並處理 AWS S3 特定錯誤
     * 6. 驗證檔案確實存在於 S3
     * 7. 記錄成功日誌
     *
     * ## 錯誤處理
     *
     * - 空 stream: 拋出例外
     * - S3 上傳失敗: 記錄 AWS 錯誤碼和訊息，拋出詳細例外
     * - 上傳後檔案不存在: 拋出例外
     * - 所有錯誤都會記錄完整的堆疊追蹤
     *
     * @param string|resource $imageStream LINE 圖片 binary stream
     *                                     可以是字串或 resource（從 LINE API 下載）
     * @param string|null $extension 檔案副檔名（預設 'jpg'）
     *                               LINE 圖片訊息固定使用 JPEG 格式
     *
     * @return string 儲存的檔案名稱（UUID.jpg 格式）
     *                例如: "550e8400-e29b-41d4-a716-446655440000.jpg"
     *
     * @throws \Exception 當上傳失敗時，包含詳細的錯誤資訊：
     *                    - 空 stream
     *                    - S3 上傳失敗（包含 AWS 錯誤碼）
     *                    - 上傳後驗證失敗
     *
     * @see \App\Services\LineBotService::getMessageContent() 取得圖片 stream
     * @see \App\Http\Controllers\LineBotController::handleImageMessage() 使用此方法
     */
    public function uploadLineImage($imageStream, ?string $extension = 'jpg'): string
    {
        $filename = null;
        $fullPath = null;
        
        try {
            // 生成唯一檔名
            $filename = Str::uuid() . '.' . $extension;
            
            // 取得圖片目錄
            $imageFolder = $this->storageService->getImageFolder();
            $fullPath = $imageFolder . '/' . $filename;
            
            // 計算資料大小（用於日誌記錄）
            $dataSize = is_string($imageStream) ? strlen($imageStream) : 'unknown (stream)';
            
            // 驗證資料大小
            if (is_string($imageStream) && strlen($imageStream) === 0) {
                throw new \Exception('Image stream is empty');
            }
            
            Log::info('LINE Upload: Starting image upload', [
                'filename' => $filename,
                'path' => $fullPath,
                'data_size' => $dataSize,
                'extension' => $extension,
            ]);
            
            // 上傳 stream 到 S3，設定正確的 Content-Type 和其他 metadata
            // 這些設定對於確保圖片能在瀏覽器中正確顯示至關重要
            // 注意：不使用 'visibility' => 'public'，因為 bucket 使用 Bucket owner enforced 模式
            // 公開訪問由 bucket policy 控制
            try {
                $success = Storage::disk('s3')->put($fullPath, $imageStream, [
                    'ContentType' => 'image/jpeg',
                    'CacheControl' => 'max-age=31536000',
                ]);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                Log::error('LINE Upload: S3 exception during image upload', [
                    'filename' => $filename,
                    'path' => $fullPath,
                    'aws_error_code' => $e->getAwsErrorCode(),
                    'aws_error_message' => $e->getAwsErrorMessage(),
                    'status_code' => $e->getStatusCode(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                throw new \Exception(
                    'S3 upload failed: ' . $e->getAwsErrorMessage() . ' (Code: ' . $e->getAwsErrorCode() . ')',
                    $e->getStatusCode(),
                    $e
                );
            }
            
            if (!$success) {
                throw new \Exception('Failed to upload image stream to S3: Storage::put returned false');
            }
            
            // 驗證檔案是否真的存在於 S3
            if (!Storage::disk('s3')->exists($fullPath)) {
                throw new \Exception('Image file was not found in S3 after upload');
            }
            
            Log::info('LINE Upload: Image uploaded successfully', [
                'filename' => $filename,
                'path' => $fullPath,
                'data_size' => $dataSize,
                'exists_in_s3' => true,
            ]);
            
            return $filename;
            
        } catch (\Exception $e) {
            Log::error('LINE Upload: Failed to upload image', [
                'filename' => $filename,
                'path' => $fullPath,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception(
                'Failed to upload LINE image to S3: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
