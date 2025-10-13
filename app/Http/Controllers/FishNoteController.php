<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\FishNoteRequest;
use App\Http\Requests\UpdateFishNoteRequest;
use App\Models\FishNote;
use App\Models\Fish;
use Inertia\Inertia;
use App\Services\SupabaseStorageService;

class FishNoteController extends Controller
{

    public function create($id)
    {
        $fish = Fish::findOrFail($id);

        $supabase = app(SupabaseStorageService::class);
        $imageUrl = $supabase->getUrl('images', $fish->image);

        // 假設 image 欄位已經是完整路徑，否則請補上 storage 路徑
        return inertia('CreateFishNote', [
            'fish' => [
                'id' => $fish->id,
                'name' => $fish->name,
                'image' => $imageUrl,
            ],
        ]);
        
    }

    public function edit($fishId, $noteId)
    {
        $fish = Fish::findOrFail($fishId);
        $fishNote = FishNote::where('fish_id', $fishId)->where('id', $noteId)->firstOrFail();

        $supabase = app(SupabaseStorageService::class);
        $imageUrl = $supabase->getUrl('images', $fish->image);

        return inertia('EditFishNote', [
            'fish' => [
                'id' => $fish->id,
                'name' => $fish->name,
                'image' => $imageUrl,
            ],
            'fishNote' => [
                'id' => $fishNote->id,
                'note' => $fishNote->note,
                'note_type' => $fishNote->note_type,
                'locate' => $fishNote->locate,
            ],
        ]);
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
        $fish = $this->findFishOrFail($id);

        $fishNote = $this->createFishNote($fish, $request);

        return response()->json([
            'message' => 'Note added successfully',
            'data' => $fishNote,
            'lastUpdateTime' => time()
        ], 201);
    }

    private function findFishOrFail($id)
    {
        $fish = Fish::find($id);
        if (!$fish) {
            abort(response()->json([
                'message' => 'fish not found',
                'data' => null,
            ], 404));
        }
        return $fish;
    }

    private function createFishNote(Fish $fish, FishNoteRequest $request)
    {
        return FishNote::create([
            'fish_id' => $fish->id,
            'note' => $request->note,
            'note_type' => $request->note_type,
            'locate' => $request->locate,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/prefix/api/fish/note/{id}",
     *     summary="更新魚類筆記",
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
     *             @OA\Property(property="note", type="string"),
     *             @OA\Property(property="note_type", type="string"),
     *             @OA\Property(property="locate", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="更新成功"),
     *     @OA\Response(response=404, description="找不到該筆記")
     * )
     */
    public function update(UpdateFishNoteRequest $request, $fishId, $noteId)
    {
        $fishNote = FishNote::where('fish_id', $fishId)->where('id', $noteId)->first();

        if (!$fishNote) {
            return response()->json([
                'message' => 'fish note not found',
                'data' => null,
            ], 404);
        }

        $fishNote->update($request->validated());

        return response()->json([
            'message' => 'Fish note updated successfully',
            'data' => $fishNote,
        ]);
    }
    
    /**
     * @OA\Delete(
     *     path="/prefix/api/fish/{id}/note/{note_id}",
     *     summary="刪除魚類筆記",
     *     tags={"FishNote"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="魚類 ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="note_id",
     *         in="path",
     *         required=true,
     *         description="筆記 ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="刪除成功"),
     *     @OA\Response(response=404, description="找不到該筆記")
     * )
     */
    public function destroy($fishId, $noteId)
    {
        $fishNote = FishNote::where('fish_id', $fishId)->where('id', $noteId)->first();

        if (!$fishNote) {
            return response()->json([
                'message' => 'fish note not found',
                'data' => null,
            ], 404);
        }

        $fishNote->delete();

        return response()->json([
            'message' => 'Fish note deleted successfully',
        ]);
    }
}
