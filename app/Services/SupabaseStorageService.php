<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use Exception;
use InvalidArgumentException;
use Illuminate\Support\Facades\Http;

class SupabaseStorageService implements StorageServiceInterface
{
    protected string $storageUrl;
    protected string $apiKey;
    protected string $bucket;
    protected string $imageFolder;
    protected string $audioFolder;
    protected string $webpFolder;


    public function __construct()
    {
        $this->storageUrl = env('SUPABASE_STORAGE_URL');
        $this->apiKey = env('SUPABASE_SERVICE_ROLE_KEY');
        $this->bucket = env('SUPABASE_BUCKET');
        $this->imageFolder = env('SUPABASE_IMAGE_FOLDER', 'images');
        $this->audioFolder = env('SUPABASE_AUDIO_FOLDER', 'audio');
        $this->webpFolder = env('SUPABASE_WEBP_FOLDER', 'webp');

    }

    private function makeAbsoluteStorageUrl(?string $pathOrUrl): ?string
    {
        if (!$pathOrUrl) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $pathOrUrl) === 1) {
            return $pathOrUrl; // already absolute
        }
        $base = rtrim((string) $this->storageUrl, '/');
        if ($pathOrUrl[0] === '/') {
            return $base . $pathOrUrl;
        }
        return $base . '/' . $pathOrUrl;
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

    /**
     * Get the configured image folder path
     */
    public function getImageFolder(): string
    {
        return $this->imageFolder;
    }

    /**
     * Get the configured audio folder path
     */
    public function getAudioFolder(): string
    {
        return $this->audioFolder;
    }

    /**
     * Get the configured webp folder path
     */
    public function getWebpFolder(): string
    {
        return $this->webpFolder;
    }

    public function getUrl(string $type, string $filename, ?bool $hasWebp = null): string
    {
        // 若 filename 已是完整 URL（歷史資料），直接原樣回傳
        if (preg_match('/^https?:\/\//i', $filename) === 1) {
            return $filename;
        }

        // 音訊：一律走 audio 目錄
        if ($type === 'audios' || $type === 'audio') {
            return "{$this->storageUrl}/object/public/{$this->bucket}/{$this->audioFolder}/{$filename}";
        }

        // 圖片：依 hasWebp 決定 webp 或原圖，不進行任何 HEAD 探測
        if ($type === 'images') {
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            if ($hasWebp === true) {
                return "{$this->storageUrl}/object/public/{$this->bucket}/{$this->webpFolder}/{$baseName}.webp";
            }
            return "{$this->storageUrl}/object/public/{$this->bucket}/{$this->imageFolder}/{$filename}";
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
                $url = $response->json('url') ?? $response->json('signedUrl') ?? $response->json('signed_url');
                $absolute = $this->makeAbsoluteStorageUrl($url);
                return $absolute;
            }

        

            return null;
        } catch (Exception $e) {
            
            return null;
        }
    }

    /**
     * Create a signed upload URL in pending/audio/ for the given fish.
     */
    public function createSignedUploadUrlForPendingAudio(int $fishId, string $ext = 'm4a', int $expiresIn = 300): ?array
    {
        $date = date('Y/m/d');
        $uuid = bin2hex(random_bytes(8));
        $filePath = "pending/audio/{$date}/{$fishId}-{$uuid}.{$ext}";
        $url = $this->createSignedUploadUrl($filePath, $expiresIn);
        if (!$url) {
            return null;
        }
        return [
            'uploadUrl' => $this->makeAbsoluteStorageUrl($url),
            'filePath' => $filePath,
            'expiresIn' => $expiresIn,
        ];
    }

    /**
     * Move object within bucket (rename). Returns destination path on success.
     */
    public function moveObject(string $sourcePath, string $destPath): ?string
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withHeaders([
                    'apikey' => $this->apiKey,
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])->post("{$this->storageUrl}/object/move/{$this->bucket}", [
                    'source' => $sourcePath,
                    'destination' => $destPath,
                    'upsert' => false,
                ]);

            if ($response->successful()) {
                return $destPath;
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
