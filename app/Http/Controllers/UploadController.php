<?php

namespace App\Http\Controllers;

use App\Services\UploadService;
use App\Http\Requests\UploadImageRequest;

class UploadController extends Controller
{
    public function uploadImage(UploadImageRequest $request)
    {
        try {
            $uploadService = new UploadService;
            $imageName = $uploadService->uploadImage($request);

            if ($imageName) {
                return response()->json([
                    'message' => 'image uploaded successfully',
                    'data' => $imageName,
                ], 201);
            } else {
                return response()->json([
                    'message' => '圖片儲存失敗，請稍後再試。',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => '伺服器內部錯誤，請稍後再試。',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }
}
