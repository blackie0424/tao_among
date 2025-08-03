<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseStorageService;
use App\Services\UploadService;
use App\Http\Requests\UploadImageRequest;
use App\Http\Requests\UploadAudioRequest;
use App\Http\Requests\SupabaseSignedUploadUrlRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\FishAudio;

class UploadController extends Controller
{
    /**
     * 上傳音訊檔案
     *
     * @OA\Post(
     *     path="/prefix/api/upload-audio",
     *     summary="上傳音訊檔案",
     *     tags={"Upload"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"audio"},
     *                 @OA\Property(
     *                     property="audio",
     *                     type="string",
     *                     format="binary",
     *                     description="要上傳的音訊檔案（mp3, wav, ogg，最大 10MB，長度不超過 6 秒）"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="上傳成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="audio uploaded successfully"),
     *             @OA\Property(property="data", type="string", example="filename.mp3")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="audio",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example="音訊格式僅限 mp3, wav, ogg。|音訊大小不可超過 10MB。|音訊長度不可超過 6 秒。"
     *                     ),
     *                     description="可能的錯誤訊息：請選擇要上傳的音訊檔案。|只能上傳單一音訊檔案。|音訊格式僅限 mp3, wav, ogg。|音訊大小不可超過 10MB。|音訊長度不可超過 6 秒。"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="音訊儲存失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="音訊儲存失敗，請稍後再試。")
     *         )
     *     )
     * )
     */
    public function uploadAudio(UploadAudioRequest $request)
    {
        try {
            $uploadService = new UploadService;
            $audioName = $uploadService->uploadAudio($request);

            if ($audioName) {
                return response()->json([
                    'message' => 'audio uploaded successfully',
                    'data' => $audioName,
                ], 201);
            } else {
                return response()->json([
                    'message' => '音訊儲存失敗，請稍後再試。',
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '驗證失敗',
                'errors' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '伺服器內部錯誤，請稍後再試。',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 上傳魚類圖片
     *
     * @OA\Post(
     *     path="/prefix/api/upload",
     *     summary="上傳魚類圖片",
     *     tags={"Upload"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image"},
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="要上傳的圖片"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="上傳成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="image uploaded successfully"),
     *             @OA\Property(property="data", type="string", example="filename.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="image",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example="只能上傳單一圖片檔案。"
     *                     ),
     *                     description="可能的錯誤訊息：只能上傳單一圖片檔案。|請選擇要上傳的圖片。|圖片格式僅限 jpeg, png, jpg, gif, svg。|圖片大小不可超過 4403 KB。|圖片檔案不可為空。"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * 獲取 Supabase 簽名上傳 URL
     *
     * @OA\Post(
     *     path="/prefix/api/supabase/signed-upload-url",
     *     summary="取得 Supabase 簽名上傳 URL",
     *     tags={"Upload"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"filename"},
     *             @OA\Property(
     *                 property="filename",
     *                 type="string",
     *                 description="原始檔名"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得簽名上傳 URL",
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", example="https://your-project-id.supabase.co/storage/v1/object/upload/sign/bucket/images/uuid.jpg"),
     *             @OA\Property(property="path", type="string", example="images/uuid.jpg"),
     *             @OA\Property(property="filename", type="string", example="uuid.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="filename",
     *                     type="array",
     *                     @OA\Items(type="string", example="檔名格式不正確。")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="產生簽名上傳 URL 失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create signed upload URL")
     *         )
     *     )
     * )
     */
    public function getSignedUploadUrl(SupabaseSignedUploadUrlRequest $request)
    {
        $path = 'images'; // 寫死路徑
        $originalName = $request->input('filename');
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);

        $uniqueName = Str::uuid()->toString() . ($ext ? '.' . $ext : '');
        $filePath = $path . '/' . $uniqueName;

        $service = new SupabaseStorageService();
        $url = $service->createSignedUploadUrl($filePath);

        if ($url) {
            $storageBaseUrl = env('SUPABASE_STORAGE_URL');
            $fullUrl = $storageBaseUrl . $url;

            return response()->json([
                'url' => $fullUrl,
                'path' => $filePath,
                'filename' => $uniqueName,
            ]);
        }

        return response()->json(['message' => 'Failed to create signed upload URL'], 500);
    }

    /**
     * 獲取 Supabase 音訊檔案簽名上傳 URL
     *
     * @OA\Post(
     *     path="/prefix/api/supabase/signed-upload-audio-url",
     *     summary="取得 Supabase 音訊檔案簽名上傳 URL",
     *     tags={"Upload"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"filename"},
     *             @OA\Property(
     *                 property="filename",
     *                 type="string",
     *                 description="原始音訊檔名"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得簽名上傳 URL",
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", example="https://your-project-id.supabase.co/storage/v1/object/upload/sign/bucket/audio/uuid.mp3"),
     *             @OA\Property(property="path", type="string", example="audio/uuid.mp3"),
     *             @OA\Property(property="filename", type="string", example="uuid.mp3")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="filename",
     *                     type="array",
     *                     @OA\Items(type="string", example="音訊檔案格式僅限 mp3, wav。")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="產生簽名上傳 URL 失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create signed upload URL")
     *         )
     *     )
     * )
     */
    public function getSignedUploadAudioUrl(Request $request)
    {
        // 驗證 audio 檔案副檔名
        $request->validate([
            'filename' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['mp3', 'wav','webm'])) {
                        $fail('音訊檔案格式僅限 mp3, wav, webm。');
                    }
                }
            ],
        ], [
            'filename.required' => '請提供音訊檔案名稱。',
        ]);

        $path = 'audio';
        $originalName = $request->input('filename');
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);

        $uniqueName = \Illuminate\Support\Str::uuid()->toString() . ($ext ? '.' . $ext : '');
        $filePath = $path . '/' . $uniqueName;

        $service = new \App\Services\SupabaseStorageService();
        $url = $service->createSignedUploadUrl($filePath);

        try {
            $fishAudio = FishAudio::create([
                'fish_id' => $request->route('id'),
                'name' => $uniqueName,
                'locate' =>"iraraley",
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to save audio metadata'], 500);
        }

        if ($url) {
            $storageBaseUrl = env('SUPABASE_STORAGE_URL');
            $fullUrl = $storageBaseUrl . $url;

            return response()->json([
                'url' => $fullUrl,
                'path' => $filePath,
                'filename' => $uniqueName,
            ]);
        }

        return response()->json(['message' => 'Failed to create signed upload URL'], 500);
    }
}
