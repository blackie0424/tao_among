<?php

namespace App\Http\Controllers;

use App\Models\FishSize;
use App\Http\Requests\FishSizeRequest;

class FishSizeController extends Controller
{
    /**
     * 取得指定魚種的尺寸資訊
     *
     * @OA\Get(
     *     path="/prefix/api/fishSize/{fish_id}",
     *     summary="取得指定 fish_id 的 fish_size",
     *     tags={"FishSize"},
     *     @OA\Parameter(
     *         name="fish_id",
     *         in="path",
     *         description="魚種 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="取得成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="fish_id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="parts",
     *                     type="array",
     *                     @OA\Items(type="string", example="手指1")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="找不到資料",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Not Found"),
     *             @OA\Property(property="data", type="string", example=null)
     *         )
     *     )
     * )
     */
    public function show($fish_id)
    {
        $fishSize = FishSize::where('fish_id', $fish_id)->first();

        if (!$fishSize) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not Found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => '取得成功',
            'data' => [
                'fish_id' => $fishSize->fish_id,
                'parts' => $fishSize->parts,
            ],
        ]);
    }

    /**
     * 新增一筆 fish size
     *
     * @OA\Post(
     *     path="/prefix/api/fishSize",
     *     summary="新增 fish_size",
     *     tags={"FishSize"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fish_id", "parts"},
     *             @OA\Property(property="fish_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="parts",
     *                 type="array",
     *                 @OA\Items(type="string", example="手指1")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="建立成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="建立成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="fish_id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="parts",
     *                     type="array",
     *                     @OA\Items(type="string", example="手指1")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function store(FishSizeRequest $request)
    {
        $validated = $request->validated();

        $fishSize = FishSize::create([
            'fish_id' => $validated['fish_id'],
            'parts' => $validated['parts'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => '建立成功',
            'data' => [
                'fish_id' => $fishSize->fish_id,
                'parts' => $fishSize->parts,
            ],
        ], 201);
    }
}
