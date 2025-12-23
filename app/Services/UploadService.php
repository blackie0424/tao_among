<?php

namespace App\Services;

use Illuminate\Http\Request;

class UploadService
{
    protected $imageName;

    protected $storageService;

    public function uploadImage(Request $request): string
    {

        if (env('APP_ENV') === 'local' || env('APP_ENV') === 'testing') {
            return basename($request->file('image')->store('images', 'public'));
        } else {
            $file = $request->file('image');
            $storageService = new SupabaseStorageService;
            $path = $storageService->getImageFolder();

            $filePath = $storageService->uploadFile($file, $path);

            return $filePath ? basename($filePath) : null;
        }

    }
    public function uploadAudio(Request $request): ?string
    {
        if (env('APP_ENV') === 'local' || env('APP_ENV') === 'testing') {
            return basename($request->file('audio')->store('audio', 'public'));
        } else {
            $file = $request->file('audio');
            $storageService = new SupabaseStorageService;
            $path = $storageService->getAudioFolder();

            $filePath = $storageService->uploadFile($file, $path);

            return $filePath ? basename($filePath) : null;
        }
    }
}
