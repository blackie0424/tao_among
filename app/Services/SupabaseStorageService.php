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

    public function getUrl(string $type, string $filename, ?bool $hasWebp = null): string
    {
        // 若 filename 已是完整 URL（歷史資料），直接原樣回傳
        if (preg_match('/^https?:\/\//i', $filename) === 1) {
            return $filename;
        }

        // 音訊：一律走 audio 目錄
        if ($type === 'audios' || $type === 'audio') {
            return "{$this->storageUrl}/object/public/{$this->bucket}/audio/{$filename}";
        }

        // 圖片：依 hasWebp 決定 webp 或原圖，不進行任何 HEAD 探測
        if ($type === 'images') {
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            if ($hasWebp === true) {
                return "{$this->storageUrl}/object/public/{$this->bucket}/webp/{$baseName}.webp";
            }
            return "{$this->storageUrl}/object/public/{$this->bucket}/images/{$filename}";
        }

        throw new InvalidArgumentException('Invalid type: ' . $type);
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
