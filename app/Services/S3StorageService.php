<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Aws\S3\S3Client;

/**
 * AWS S3 Storage Service Implementation
 *
 * 使用 Laravel Storage Facade 與 Flysystem S3 adapter
 * 實作 StorageServiceInterface 定義的所有方法
 */
class S3StorageService implements StorageServiceInterface
{
    /**
     * 取得資料夾路徑設定
     */
    private function getFolderConfig(string $folderType): string
    {
        // @phpstan-ignore-next-line - 動態 config key
        return config("storage.drivers.s3.folders.{$folderType}", $folderType);
    }

    /**
     * 取得檔案的公開 URL
     *
     * @param string $type 檔案類型 ('image', 'audio', 'webp')
     * @param string $filename 檔案名稱
     * @param bool|null $hasWebp 是否有 webp 版本（僅用於 image）
     * @return string 完整的檔案 URL
     */
    public function getUrl(string $type, string $filename, ?bool $hasWebp = null): string
    {
        // 如果是 image 類型且有 webp，優先使用 webp
        if ($type === 'image' && $hasWebp) {
            $webpPath = $this->getWebpFolder() . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.webp';
            /** @var \Illuminate\Contracts\Filesystem\Cloud $disk */
            $disk = Storage::disk('s3');
            return $disk->url($webpPath);
        }

        $folder = match ($type) {
            'image', 'images' => $this->getImageFolder(),
            'audio', 'audios' => $this->getAudioFolder(),
            'webp' => $this->getWebpFolder(),
            default => throw new \InvalidArgumentException("Invalid type: {$type}")
        };

        /** @var \Illuminate\Contracts\Filesystem\Cloud $disk */
        $disk = Storage::disk('s3');
        return $disk->url($folder . '/' . $filename);
    }

    /**
     * 建立簽章上傳 URL（用於前端直傳）
     *
     * @param string $filePath 檔案路徑
     * @param int $expiresIn 有效秒數
     * @return string|null 簽章 URL
     */
    public function createSignedUploadUrl(string $filePath, int $expiresIn = 3600): ?string
    {
        try {
            // 取得 S3 設定
            $key = config('filesystems.disks.s3.key');
            $secret = config('filesystems.disks.s3.secret');
            $region = config('filesystems.disks.s3.region');
            $bucket = config('filesystems.disks.s3.bucket');
            
            // 驗證必要設定
            if (!$key || !$secret || !$region || !$bucket) {
                Log::error('Missing S3 configuration', [
                    'has_key' => !empty($key),
                    'has_secret' => !empty($secret),
                    'has_region' => !empty($region),
                    'has_bucket' => !empty($bucket),
                ]);
                return null;
            }
            
            // 建立 S3Client
            $client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);
            
            // 建立 PutObject 命令的 presigned URL
            $command = $client->getCommand('PutObject', [
                'Bucket' => $bucket,
                'Key' => $filePath,
            ]);
            
            $request = $client->createPresignedRequest($command, "+{$expiresIn} seconds");
            
            return (string) $request->getUri();
        } catch (\Exception $e) {
            Log::error('Failed to create signed upload URL', [
                'filePath' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * 為待審音檔建立簽章上傳 URL
     *
     * @param int $fishId 魚類 ID
     * @param string $ext 副檔名
     * @param int $expiresIn 有效秒數
     * @return array|null ['url' => string, 'path' => string, 'filename' => string]
     */
    public function createSignedUploadUrlForPendingAudio(int $fishId, string $ext = 'webm', int $expiresIn = 300): ?array
    {
        $filename = "audio_fish_{$fishId}_" . time() . ".{$ext}";
        $path = $this->getAudioFolder() . '/' . $filename;

        $url = $this->createSignedUploadUrl($path, $expiresIn);

        if (!$url) {
            return null;
        }

        return [
            'url' => $url,
            'path' => $path,
            'filename' => $filename,
        ];
    }

    /**
     * 移動物件到新位置
     *
     * @param string $sourcePath 來源路徑
     * @param string $destPath 目標路徑
     * @return string|null 成功返回新路徑，失敗返回 null
     */
    public function moveObject(string $sourcePath, string $destPath): ?string
    {
        try {
            // S3 使用 copy + delete 模擬 move
            if (Storage::disk('s3')->copy($sourcePath, $destPath)) {
                Storage::disk('s3')->delete($sourcePath);
                return $destPath;
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to move object in S3', [
                'source' => $sourcePath,
                'destination' => $destPath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 刪除檔案
     *
     * @param string $filePath 檔案路徑
     * @return bool 是否成功刪除
     */
    public function delete(string $filePath): bool
    {
        try {
            return Storage::disk('s3')->delete($filePath);
        } catch (\Exception $e) {
            Log::error('Failed to delete file from S3', [
                'filePath' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 刪除檔案前先驗證存在
     *
     * @param string $filePath 檔案路徑
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteWithValidation(string $filePath): array
    {
        if (!$this->fileExists($filePath)) {
            return [
                'success' => false,
                'message' => "File not found: {$filePath}"
            ];
        }

        $deleted = $this->delete($filePath);

        return [
            'success' => $deleted,
            'message' => $deleted ? 'File deleted successfully' : 'Failed to delete file'
        ];
    }

    /**
     * 檢查檔案是否存在
     *
     * @param string $filePath 檔案路徑
     * @return bool
     */
    private function fileExists(string $filePath): bool
    {
        try {
            return Storage::disk('s3')->exists($filePath);
        } catch (\Exception $e) {
            Log::error('Failed to check file existence in S3', [
                'filePath' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 取得圖片資料夾路徑
     *
     * @return string
     */
    public function getImageFolder(): string
    {
        return $this->getFolderConfig('image');
    }

    /**
     * 取得音檔資料夾路徑
     *
     * @return string
     */
    public function getAudioFolder(): string
    {
        return $this->getFolderConfig('audio');
    }

    /**
     * 取得 WebP 資料夾路徑
     *
     * @return string
     */
    public function getWebpFolder(): string
    {
        return $this->getFolderConfig('webp');
    }

    /**
     * 上傳檔案（舊版直接上傳方法，建議使用簽章 URL）
     *
     * @param mixed $file 上傳的檔案
     * @param string $path 目標路徑
     * @return string 儲存後的完整路徑
     */
    public function uploadFile($file, string $path): string
    {
        try {
            $storedPath = Storage::disk('s3')->putFileAs(
                dirname($path),
                $file,
                basename($path),
                'public'
            );

            if (!$storedPath) {
                throw new \RuntimeException('Failed to upload file to S3');
            }

            return $storedPath;
        } catch (\Exception $e) {
            Log::error('Failed to upload file to S3', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
