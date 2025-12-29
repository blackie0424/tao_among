<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergeFishRequest;
use App\Services\FishMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FishMergeController extends Controller
{
    protected $fishMergeService;

    public function __construct(FishMergeService $fishMergeService)
    {
        $this->fishMergeService = $fishMergeService;
    }

    /**
     * 預覽合併操作
     * 
     * @OA\Post(
     *     path="/prefix/api/fish/merge/preview",
     *     summary="預覽魚類合併操作",
     *     tags={"Fish Merge"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"target_fish_id", "source_fish_ids"},
     *             @OA\Property(property="target_fish_id", type="integer", example=123, description="主魚類 ID"),
     *             @OA\Property(
     *                 property="source_fish_ids",
     *                 type="array",
     *                 @OA\Items(type="integer", example=456),
     *                 description="被併入的魚類 ID 陣列"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="預覽成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="預覽成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="target", type="object", description="主魚類資料"),
     *                 @OA\Property(property="sources", type="array", @OA\Items(type="object"), description="被併入魚類陣列"),
     *                 @OA\Property(property="conflicts", type="object", description="衝突資訊"),
     *                 @OA\Property(property="summary", type="object", description="統計摘要")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="驗證失敗"),
     *     @OA\Response(response=404, description="魚類不存在")
     * )
     */
    public function preview(MergeFishRequest $request): JsonResponse
    {
        try {
            $targetFishId = $request->input('target_fish_id');
            $sourceFishIds = $request->input('source_fish_ids');

            // 額外驗證
            $validation = $this->fishMergeService->validateMerge($targetFishId, $sourceFishIds);
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => '驗證失敗',
                    'errors' => $validation['errors'],
                ], 422);
            }

            // 執行預覽
            $previewData = $this->fishMergeService->previewMerge($targetFishId, $sourceFishIds);

            return response()->json([
                'success' => true,
                'message' => '預覽成功',
                'data' => $previewData,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的魚類',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Fish merge preview error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '預覽失敗: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 執行合併操作
     * 
     * @OA\Post(
     *     path="/prefix/api/fish/merge",
     *     summary="執行魚類合併",
     *     tags={"Fish Merge"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"target_fish_id", "source_fish_ids"},
     *             @OA\Property(property="target_fish_id", type="integer", example=123, description="主魚類 ID"),
     *             @OA\Property(
     *                 property="source_fish_ids",
     *                 type="array",
     *                 @OA\Items(type="integer", example=456),
     *                 description="被併入的魚類 ID 陣列"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="合併成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="合併成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="target_fish_id", type="integer", example=123),
     *                 @OA\Property(property="merged_fish_ids", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="transferred", type="object"),
     *                 @OA\Property(property="conflicts_resolved", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="驗證失敗"),
     *     @OA\Response(response=404, description="魚類不存在"),
     *     @OA\Response(response=500, description="合併失敗")
     * )
     */
    public function merge(MergeFishRequest $request): JsonResponse
    {
        try {
            $targetFishId = $request->input('target_fish_id');
            $sourceFishIds = $request->input('source_fish_ids');

            // 額外驗證
            $validation = $this->fishMergeService->validateMerge($targetFishId, $sourceFishIds);
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => '驗證失敗',
                    'errors' => $validation['errors'],
                ], 422);
            }

            // 執行合併
            $result = $this->fishMergeService->mergeFish($targetFishId, $sourceFishIds);

            return response()->json([
                'success' => true,
                'message' => '合併成功',
                'data' => $result,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的魚類',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Fish merge error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '合併失敗: ' . $e->getMessage(),
            ], 500);
        }
    }
}
