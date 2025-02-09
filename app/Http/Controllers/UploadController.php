<?php

namespace App\Http\Controllers;

use App\Services\SupabaseStorageService;

class UploadController extends Controller
{
    public function uploadImage()
    {
        $request = request();
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'image upload failed', 'data' => $e->errors()], 400);
        } catch (Exception $e) {
            return response()->json(['message' => 'Upload failed', 'error' => $e->getMessage()], 500);
        }

        if (env('APP_ENV') === 'local' || env('APP_ENV') === 'testing') {
            $imagePath = $request->file('image')->store('images', 'public');
            $imageName = basename($imagePath);

            return response()->json(['message' => 'image uploaded successfully', 'data' => $imageName], 201);

        } else {
            $file = $request->file('image');
            $path = 'images';
            $storageService = new SupabaseStorageService;

            $filePath = $storageService->uploadFile($file, $path);
            if (! $filePath) {
                return response()->json(['message' => 'Upload failed'], 500);
            }
            $url = $storageService->getUrl($filePath);
            $imageName = basename($filePath);

            return response()->json(['message' => 'image uploaded successfully', 'data' => $imageName], 201);
        }
    }
}
