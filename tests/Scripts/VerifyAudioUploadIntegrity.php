<?php

/**
 * 音檔上傳完整性驗證腳本
 *
 * 此腳本用於手動驗證音檔上傳到 S3 的完整性：
 * 1. 上傳測試音檔到 S3
 * 2. 下載並比較內容
 * 3. 驗證 Content-Type 是否正確
 * 4. 提供 URL 供手動播放測試
 *
 * 使用方式：
 * php tests/Scripts/VerifyAudioUploadIntegrity.php
 *
 * Requirements: 1.2, 2.2
 */

require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\LineUploadService;
use App\Services\S3StorageService;

// 啟動 Laravel 應用程式
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 音檔上傳完整性驗證腳本 ===\n\n";

// 建立測試音檔內容（模擬真實的 M4A 格式）
echo "1. 建立測試音檔內容...\n";
$testAudioContent = createTestM4AContent();
$originalSize = strlen($testAudioContent);
echo "   原始音檔大小: " . $originalSize . " bytes\n";
echo "   音檔格式: M4A (模擬)\n\n";

// 初始化服務
echo "2. 初始化上傳服務...\n";
$storageService = new S3StorageService();
$uploadService = new LineUploadService($storageService);
echo "   使用儲存服務: S3StorageService\n";
echo "   音檔目錄: " . $storageService->getAudioFolder() . "\n\n";

// 上傳音檔
echo "3. 上傳音檔到 S3...\n";
try {
    $filename = $uploadService->uploadLineAudio($testAudioContent);
    echo "   ✓ 上傳成功\n";
    echo "   檔案名稱: " . $filename . "\n";
    
    $fullPath = $storageService->getAudioFolder() . '/' . $filename;
    echo "   完整路徑: " . $fullPath . "\n\n";
} catch (\Exception $e) {
    echo "   ✗ 上傳失敗: " . $e->getMessage() . "\n";
    exit(1);
}

// 下載音檔並比較內容
echo "4. 下載音檔並驗證完整性...\n";
try {
    $downloadedContent = Storage::disk('s3')->get($fullPath);
    $downloadedSize = strlen($downloadedContent);
    
    echo "   下載音檔大小: " . $downloadedSize . " bytes\n";
    
    if ($downloadedContent === $testAudioContent) {
        echo "   ✓ 內容完整性驗證通過（round-trip 成功）\n";
    } else {
        echo "   ✗ 內容不一致！\n";
        echo "   原始大小: " . $originalSize . " bytes\n";
        echo "   下載大小: " . $downloadedSize . " bytes\n";
        exit(1);
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   ✗ 下載失敗: " . $e->getMessage() . "\n";
    exit(1);
}

// 驗證 Content-Type
echo "5. 驗證 Content-Type...\n";
try {
    // 使用 AWS SDK 檢查 metadata
    $s3Client = Storage::disk('s3')->getAdapter()->getClient();
    $bucket = config('filesystems.disks.s3.bucket');
    
    $result = $s3Client->headObject([
        'Bucket' => $bucket,
        'Key' => $fullPath,
    ]);
    
    $contentType = $result['ContentType'] ?? 'unknown';
    echo "   Content-Type: " . $contentType . "\n";
    
    if ($contentType === 'audio/mp4' || $contentType === 'audio/m4a') {
        echo "   ✓ Content-Type 正確\n";
    } else {
        echo "   ✗ Content-Type 不正確（預期: audio/mp4 或 audio/m4a）\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   ⚠ 無法檢查 Content-Type: " . $e->getMessage() . "\n\n";
}

// 生成可播放的 URL
echo "6. 生成音檔 URL...\n";
try {
    $url = Storage::disk('s3')->url($fullPath);
    echo "   URL: " . $url . "\n";
    echo "   ✓ 請在瀏覽器中開啟此 URL 測試播放功能\n\n";
} catch (\Exception $e) {
    echo "   ✗ 無法生成 URL: " . $e->getMessage() . "\n\n";
}

// 清理測試檔案（可選）
echo "7. 清理測試檔案...\n";
echo "   是否刪除測試檔案？(y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) === 'y') {
    try {
        Storage::disk('s3')->delete($fullPath);
        echo "   ✓ 測試檔案已刪除\n";
    } catch (\Exception $e) {
        echo "   ✗ 刪除失敗: " . $e->getMessage() . "\n";
    }
} else {
    echo "   保留測試檔案\n";
}
fclose($handle);

echo "\n=== 驗證完成 ===\n";

/**
 * 建立測試用的 M4A 音檔內容
 *
 * @return string
 */
function createTestM4AContent(): string
{
    // M4A 檔案的基本結構（簡化版）
    $content = '';
    
    // ftyp box (file type box)
    // 這是 M4A 檔案的標準開頭
    $ftypBox = 'ftypisom' . "\x00\x00\x02\x00" . 'isomiso2mp41';
    $ftypSize = strlen($ftypBox) + 4;
    $content .= pack('N', $ftypSize) . $ftypBox;
    
    // mdat box (media data box)
    // 包含實際的音訊資料（這裡使用假資料）
    $mdatData = str_repeat("\x00\x01\x02\x03\x04\x05\x06\x07", 1000); // 8KB 的假音訊資料
    $mdatBox = 'mdat' . $mdatData;
    $mdatSize = strlen($mdatBox) + 4;
    $content .= pack('N', $mdatSize) . $mdatBox;
    
    return $content;
}
