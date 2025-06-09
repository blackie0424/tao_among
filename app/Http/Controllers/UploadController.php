<?php

namespace App\Http\Controllers;

use App\Services\UploadService;
use App\Http\Requests\UploadImageRequest;

class UploadController extends Controller
{

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
}
