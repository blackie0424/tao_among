<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use Illuminate\Http\Request;

class UploadService
{
    protected $imageName;

    protected $storageService;

    public function __construct(StorageServiceInterface $storageService)
    {
        $this->storageService = $storageService;
    }

    public function uploadImage(Request $request): string
    {

        if (env('APP_ENV') === 'local' || env('APP_ENV') === 'testing') {
            return basename($request->file('image')->store('images', 'public'));
        } else {
            $file = $request->file('image');
            $path = $this->storageService->getImageFolder();

            $filePath = $this->storageService->uploadFile($file, $path);

            return $filePath ? basename($filePath) : null;
        }

    }
    public function uploadAudio(Request $request): ?string
    {
        if (env('APP_ENV') === 'local' || env('APP_ENV') === 'testing') {
            return basename($request->file('audio')->store('audio', 'public'));
        } else {
            $file = $request->file('audio');
            $path = $this->storageService->getAudioFolder();

            $filePath = $this->storageService->uploadFile($file, $path);

            return $filePath ? basename($filePath) : null;
        }
    }
}
