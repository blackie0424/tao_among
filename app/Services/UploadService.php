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
            $path = 'images';
            $storageService = new SupabaseStorageService;

            $filePath = $storageService->uploadFile($file, $path);

            return $filePath ? basename($filePath) : null;
        }

    }
}
