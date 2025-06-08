<?php

namespace App\Http\Controllers;

use App\Services\UploadService;

class UploadController extends Controller
{
    public function uploadImage()
    {
        $request = request();
        try {
            $request->validate([
                'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:4403', 'min:1'],
            ], [
                'image.image' => '只能上傳單一圖片檔案。',
                'image.required' => '請選擇要上傳的圖片。',
                'image.mimes' => '圖片格式僅限 jpeg, png, jpg, gif, svg。',
                'image.max' => '圖片大小不可超過 4403 KB。',
                'image.min' => '圖片檔案不可為空。',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => '驗證失敗', 'errors' => $e->errors()], 400);
        } catch (Exception $e) {
            return response()->json(['message' => '伺服器內部錯誤，請稍後再試。', 'error' => app()->environment('production') ? null : $e->getMessage()], 500);
        }

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
    }
}
