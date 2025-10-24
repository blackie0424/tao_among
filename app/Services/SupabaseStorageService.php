<?php

namespace App\Services;

use Exception;
use InvalidArgumentException;
use Illuminate\Support\Facades\Http;

class SupabaseStorageService
{
    protected string $storageUrl;
    protected string $apiKey;
    protected string $bucket;

    public function __construct()
    {
        $this->storageUrl = env('SUPABASE_STORAGE_URL');
        $this->apiKey = env('SUPABASE_SERVICE_ROLE_KEY');
        $this->bucket = env('SUPABASE_BUCKET');
    }

    public function uploadFile($file, string $path): string
    {
        $fileName = time().'_'.$file->getClientOriginalName();
        $filePath = "{$path}/{$fileName}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->attach('file', file_get_contents($file->getRealPath()), $fileName)
            ->post("{$this->storageUrl}/object/{$this->bucket}/{$filePath}", [
                'access' => 'public',
            ]);

        if (! $response->successful()) {
            throw new Exception('Failed to upload file to Supabase'.$response->body());
        }

        return "{$this->storageUrl}/object/public/{$this->bucket}/{$filePath}";
    }

    public function getUrl(string $type, string $filename): string
    {
        if ($type === 'audios' || $type === 'audio') {
            return "{$this->storageUrl}/object/public/{$this->bucket}/audio/{$filename}";
        }

        if ($type !== 'images') {
            throw new InvalidArgumentException('Invalid type: ' . $type);
        }

        // 提取基名（去掉擴展名）
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        // WebP 路徑
        $webpPath = "webp/{$baseName}.webp";

        // 使用 HEAD 請求檢查 WebP 檔案是否存在
        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
            'Authorization' => "Bearer {$this->apiKey}",
        ])->head("{$this->storageUrl}/object/{$this->bucket}/{$webpPath}");

        if ($response->status() === 200) {
            // WebP 存在，返回 WebP 連結
            return "{$this->storageUrl}/object/public/{$this->bucket}/{$webpPath}";
        }

        // WebP 不存在，返回原檔連結
        return "{$this->storageUrl}/object/public/{$this->bucket}/images/{$filename}";
    }

    public function createSignedUploadUrl(string $filePath, int $expiresIn = 60): ?string
    {
        try {
            if (empty($filePath)) {
                throw new InvalidArgumentException('File path cannot be empty');
            }

            if ($expiresIn <= 0 || $expiresIn > 3600) {
                throw new InvalidArgumentException('Expires in must be between 1 and 3600 seconds');
            }

            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withHeaders([
                    'apikey' => $this->apiKey,
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])->post("{$this->storageUrl}/object/upload/sign/{$this->bucket}/{$filePath}", [
                    'expiresIn' => $expiresIn,
                ]);

            if ($response->successful()) {
                $url = $response->json('url');
                
                return $url;
            }

        

            return null;
        } catch (Exception $e) {
            
            return null;
        }
    }
    
    public function delete(string $filePath): bool
    {
        try {
            // Add timeout and retry logic
            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->withHeaders([
                    'apikey' => $this->apiKey,
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])->delete("{$this->storageUrl}/object/{$this->bucket}/{$filePath}");

            if ($response->successful()) {
              
                return true;
            }

            return false;
        } catch (Exception $e) {
           

            return false;
        }
    }

    /**
     * Delete file with enhanced error handling and validation
     */
    public function deleteWithValidation(string $filePath): array
    {
        if (empty($filePath)) {
            return [
                'success' => false,
                'error' => 'File path cannot be empty'
            ];
        }

        try {
            // Check if file exists before attempting deletion
            $exists = $this->fileExists($filePath);
            if (!$exists) {
                return [
                    'success' => true,
                    'message' => 'File does not exist'
                ];
            }

            $success = $this->delete($filePath);
            
            return [
                'success' => $success,
                'message' => $success ? 'File deleted successfully' : 'Failed to delete file'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if file exists in Supabase storage
     */
    public function fileExists(string $filePath): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'apikey' => $this->apiKey,
                    'Authorization' => "Bearer {$this->apiKey}",
                ])->head("{$this->storageUrl}/object/{$this->bucket}/{$filePath}");

            return $response->status() === 200;
        } catch (Exception $e) {
            
            return false;
        }
    }
}
