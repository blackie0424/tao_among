<?php

namespace App\Http\Controllers;

use App\Contracts\StorageServiceInterface;
use App\Http\Requests\SignedUploadUrlRequest;
use App\Http\Requests\UploadAudioRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\AudioConfirmService;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UploadController extends Controller
{
    protected $storageService;

    public function __construct(StorageServiceInterface $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * POST /prefix/api/upload/audio/sign
     *
     * @OA\Post(
     *   path="/prefix/api/upload/audio/sign",
     *   summary="取得音訊暫存簽名上傳網址（pending/audio/...）",
     *   tags={"Upload"},
     *
     *   @OA\RequestBody(
     *     required=true,
     *
     *     @OA\JsonContent(
     *       required={"fish_id"},
     *
     *       @OA\Property(property="fish_id", type="integer", example=1, minimum=1),
     *       @OA\Property(property="ext", type="string", example="m4a", enum={"m4a","mp3","wav","mp4","aac"})
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="簽名網址產生成功（uploadUrl 為儲存服務的絕對 https 網址）",
     *
     *     @OA\JsonContent(
     *       required={"uploadUrl","filePath","expiresIn"},
     *
     *       @OA\Property(property="uploadUrl", type="string", format="uri", description="Supabase 絕對簽名網址", example="https://your-project-ref.supabase.co/storage/v1/object/upload/sign/your-bucket/pending/audio/2025/11/01/1-uuid.webm?token=eyJ..."),
     *       @OA\Property(property="filePath", type="string", description="暫存路徑，供 confirm 使用", example="pending/audio/2025/11/01/1-uuid.webm"),
     *       @OA\Property(property="expiresIn", type="integer", description="簽名有效秒數", example=300)
     *     )
     *   ),
     *
     *   @OA\Response(response=500, description="產生簽名失敗")
     * )
     */
    public function signPendingAudio(Request $request)
    {
        $validated = $request->validate([
            'fish_id' => 'required|integer|min:1',
            'ext' => 'nullable|string|in:webm,mp3,wav,m4a',
        ]);

        $ext = $validated['ext'] ?? 'webm';

        $service = app(StorageServiceInterface::class);
        $fishId = (int) $validated['fish_id'];
        $signed = $service->createSignedUploadUrlForPendingAudio($fishId, $ext);
        Log::info('[audio.sign] generated', [
            'fish_id' => $fishId,
            'filePath' => $signed['filePath'] ?? null,
            'expiresIn' => $signed['expiresIn'] ?? null,
        ]);
        if (! $signed) {
            Log::error('[audio.sign] failed to create signed URL', [
                'fish_id' => $fishId,
                'ext' => $ext,
            ]);

            return response()->json(['message' => 'Failed to create signed upload URL'], 500);
        }
        // sanity: ensure absolute
        if (! preg_match('/^https?:\/\//', (string) ($signed['uploadUrl'] ?? ''))) {
            Log::error('[audio.sign] misconfigured storage URL (not absolute)', [
                'fish_id' => $fishId,
                'uploadUrl' => $signed['uploadUrl'] ?? null,
            ]);

            return response()->json(['message' => 'Misconfigured storage URL'], 500);
        }
        Log::info('[audio.sign] success', [
            'fish_id' => $fishId,
            'filePath' => $signed['filePath'] ?? null,
        ]);

        return response()->json($signed, 200);
    }

    /**
     * 上傳音訊檔案
     *
     * @OA\Post(
     *     path="/prefix/api/upload-audio",
     *     summary="上傳音訊檔案",
     *     tags={"Upload"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"audio"},
     *
     *                 @OA\Property(
     *                     property="audio",
     *                     type="string",
     *                     format="binary",
     *                     description="要上傳的音訊檔案（mp3, wav, ogg，最大 10MB，長度不超過 6 秒）"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="上傳成功",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="audio uploaded successfully"),
     *             @OA\Property(property="data", type="string", example="filename.mp3")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="驗證失敗",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="audio",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="音訊格式僅限 mp3, wav, ogg。|音訊大小不可超過 10MB。|音訊長度不可超過 6 秒。"
     *                     ),
     *                     description="可能的錯誤訊息：請選擇要上傳的音訊檔案。|只能上傳單一音訊檔案。|音訊格式僅限 mp3, wav, ogg。|音訊大小不可超過 10MB。|音訊長度不可超過 6 秒。"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="音訊儲存失敗",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="音訊儲存失敗，請稍後再試。")
     *         )
     *     )
     * )
     */
    public function uploadAudio(UploadAudioRequest $request, UploadService $uploadService)
    {
        try {
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
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"image"},
     *
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="要上傳的圖片"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="上傳成功",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="image uploaded successfully"),
     *             @OA\Property(property="data", type="string", example="filename.jpg")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="驗證失敗",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="image",
     *                     type="array",
     *
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
    public function uploadImage(UploadImageRequest $request, UploadService $uploadService)
    {
        try {

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
     * 獲取簽名上傳 URL
     *
     * @OA\Post(
     *     path="/prefix/api/storage/signed-upload-url",
     *     summary="取得儲存服務簽名上傳 URL",
     *     tags={"Upload"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"filename"},
     *
     *             @OA\Property(
     *                 property="filename",
     *                 type="string",
     *                 description="原始檔名"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="成功取得簽名上傳 URL",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="url", type="string", example="https://your-project-id.supabase.co/storage/v1/object/upload/sign/bucket/images/uuid.jpg"),
     *             @OA\Property(property="path", type="string", example="images/uuid.jpg"),
     *             @OA\Property(property="filename", type="string", example="uuid.jpg")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="驗證失敗",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="filename",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="檔名格式不正確。")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="產生簽名上傳 URL 失敗",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Failed to create signed upload URL")
     *         )
     *     )
     * )
     */
    public function getSignedUploadUrl(SignedUploadUrlRequest $request)
    {
        $service = app(StorageServiceInterface::class);

        // 使用 folder 參數,預設為 images
        $folder = $request->input('folder', 'images');

        // 根據資料夾選擇路徑
        $path = match ($folder) {
            'topics' => 'topics',
            'topic-items' => 'topic-items',
            default => $service->getImageFolder(),
        };

        $originalName = $request->input('filename');
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);

        $uniqueName = Str::uuid()->toString().($ext ? '.'.$ext : '');
        $filePath = $path.'/'.$uniqueName;

        $url = $service->createSignedUploadUrl($filePath);

        if (! $url) {
            return response()->json(['message' => 'Failed to create signed upload URL'], 500);
        }

        // 服務已回傳絕對 URL，直接轉交
        return response()->json([
            'url' => $url,
            'path' => $filePath,
            'filename' => $uniqueName,
        ]);
    }

    /**
     * 獲取音訊檔案簽名上傳 URL
     *
     * @OA\Post(
     *     path="/prefix/api/fish/{id}/storage/signed-upload-audio-url",
     *     summary="取得音訊檔案簽名上傳 URL",
     *     tags={"Upload"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="魚類 ID",
     *
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"filename"},
     *
     *             @OA\Property(
     *                 property="filename",
     *                 type="string",
     *                 description="原始音訊檔名"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="成功取得簽名上傳 URL",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="url", type="string", example="https://your-project-id.supabase.co/storage/v1/object/upload/sign/bucket/audio/uuid.mp3"),
     *             @OA\Property(property="path", type="string", example="audio/uuid.mp3"),
     *             @OA\Property(property="filename", type="string", example="uuid.mp3")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="驗證失敗",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="filename",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="音訊檔案格式僅限 mp3, wav。")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="產生簽名上傳 URL 失敗",
     *
     *         @OA\JsonContent(
     *
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
                    if (! in_array($ext, FishAudio::EXTENSIONS, true)) {
                        $fail('音訊檔案格式僅限 '.implode(', ', FishAudio::EXTENSIONS).'。');
                    }
                },
            ],
        ], [
            'filename.required' => '請提供音訊檔案名稱。',
        ]);

        // 只簽名，不寫任何資料表；音檔簽到登入者專屬的暫存資料夾，確認（confirmAudio）時才複製到正式資料夾並寫入
        if (! Fish::find($request->route('id'))) {
            return response()->json(['message' => '魚類資料不存在'], 404);
        }

        $ext = strtolower(pathinfo($request->input('filename'), PATHINFO_EXTENSION));
        $uniqueName = Str::uuid()->toString().'.'.$ext;
        $filePath = FishAudio::pendingPath($request->user()->id, $uniqueName);

        $url = $this->storageService->createSignedUploadUrl($filePath, FishAudio::UPLOAD_TTL_SECONDS);
        if (! $url) {
            return response()->json(['message' => 'Failed to create signed upload URL'], 500);
        }

        return response()->json([
            'url' => $url,
            'path' => $filePath,
            'filename' => $uniqueName,
        ]);
    }

    /**
     * 確認網頁錄音：驗證暫存檔、複製到正式資料夾並寫入一筆新紀錄（同名稱同部落可並存多段）。
     * POST /prefix/api/fish/{id}/audio/confirm
     */
    public function confirmAudio(Request $request, AudioConfirmService $confirmService, int $id)
    {
        $fish = Fish::findOrFail($id);
        $data = $request->validate([
            'filename' => ['required', 'string', 'regex:'.FishAudio::filenamePattern()],
            'spoken_name' => ['required', 'string'],
            'tribe' => ['required', Rule::in(config('fish_options.tribes', []))],
            'duration_ms' => ['required', 'integer', 'min:1', 'max:'.FishAudio::MAX_DURATION_MS],
        ]);

        try {
            $audio = $confirmService->confirm($fish, $request->user()->id, $data['filename'], $data['spoken_name'], $data['tribe'], $data['duration_ms']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['spoken_name' => $e->getMessage()]);
        }

        return response()->json(['id' => $audio->id, 'filename' => $audio->name, 'url' => $audio->url], 201);
    }
}
