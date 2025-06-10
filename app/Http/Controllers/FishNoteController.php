<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FishService;
use App\Http\Requests\FishNoteRequest;

class FishNoteController extends Controller
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    /**
     * @OA\Post(
     *     path="/prefix/api/fish/{id}/note",
     *     summary="新增魚類筆記",
     *     tags={"FishNote"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"note", "locate"},
     *             @OA\Property(property="note", type="string"),
     *             @OA\Property(property="note_type", type="string"),
     *             @OA\Property(property="locate", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="建立成功")
     * )
     */
    public function store(FishNoteRequest $request, $id): JsonResponse
    {
        $fishNote = $this->fishService->addFishNote(
            $id,
            $request->note,
            $request->note_type,
            $request->locate
        );

        return response()->json([
            'message' => 'Note added successfully',
            'data' => $fishNote,
            'lastUpdateTime' => time()
        ], 201);
    }
}
