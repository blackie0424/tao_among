<?php

namespace App\Services;

use Exception;
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

    public function getUrl(string $filename): string
    {
        return "{$this->storageUrl}/object/public/{$this->bucket}/images/{$filename}";
    }
}
