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
use App\Services\FishService;
use Exception;

class FishNoteController extends BaseController
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    /**
     * Display the knowledge list page for a specific fish
     */
    public function knowledgeList($fishId)
    {
        try {
            $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');
            $fish->load('notes');
            
            $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];
            $groupedNotes = $this->groupNotesByType($fish->notes);
            $stats = $this->getNoteTypeStats($fish->notes);

            $this->logOperation('Knowledge list viewed', [
                'fish_id' => $fishId,
                'notes_count' => $fish->notes->count()
            ]);

            return Inertia::render('FishKnowledgeList', [
                'fish' => $fishWithImage,
                'groupedNotes' => $groupedNotes,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入進階知識列表');
        }
    }

    /**
     * Show the form for editing the specified knowledge note
     */
    public function editKnowledge($fishId, $noteId)
    {
        try {
            $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');
            $note = $this->findRelatedResourceOrFail(FishNote::class, [
                'fish_id' => $fishId,
                'id' => $noteId
            ], '進階知識');

            $this->logOperation('Knowledge edit form accessed', [
                'fish_id' => $fishId,
                'note_id' => $noteId
            ]);

            return Inertia::render('EditFishNote', [
                'fish' => $this->fishService->assignImageUrls([$fish])[0],
                'note' => $note,
                'noteTypes' => $this->getNoteTypes()
            ]);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入編輯頁面');
        }
    }

    /**
     * Update the specified knowledge note in storage
     */
    public function updateKnowledge(UpdateFishNoteRequest $request, $fishId, $noteId)
    {
        try {
            return $this->executeWithTransaction(function () use ($request, $fishId, $noteId) {
                // Verify fish exists
                $this->findResourceOrFail(Fish::class, $fishId, '魚類');
                
                // Find and update the note
                $note = $this->findRelatedResourceOrFail(FishNote::class, [
                    'fish_id' => $fishId,
                    'id' => $noteId
                ], '進階知識');

                $oldData = $note->toArray();
                $note->update($request->validated());

                $this->logOperation('Knowledge updated successfully', [
                    'fish_id' => $fishId,
                    'note_id' => $noteId,
                    'old_data' => $oldData,
                    'new_data' => $note->fresh()->toArray()
                ]);

                return redirect()->route('fish.knowledge-list', $fishId)
                    ->with('success', '進階知識已成功更新');
            }, 'knowledge update');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '更新進階知識失敗');
        }
    }

    /**
     * Remove the specified knowledge note from storage
     */
    public function destroyKnowledge($fishId, $noteId)
    {
        try {
            return $this->executeWithTransaction(function () use ($fishId, $noteId) {
                // Verify fish exists
                $this->findResourceOrFail(Fish::class, $fishId, '魚類');
                
                // Find and delete the note
                $note = $this->findRelatedResourceOrFail(FishNote::class, [
                    'fish_id' => $fishId,
                    'id' => $noteId
                ], '進階知識');

                $noteData = $note->toArray();
                $note->delete();

                $this->logOperation('Knowledge deleted successfully', [
                    'fish_id' => $fishId,
                    'note_id' => $noteId,
                    'deleted_data' => $noteData
                ]);

                return redirect()->route('fish.knowledge-list', $fishId)
                    ->with('success', '進階知識已成功刪除');
            }, 'knowledge deletion');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '刪除進階知識失敗');
        }
    }

    /**
     * Group notes by their type with proper sorting and default handling
     */
    private function groupNotesByType($notes)
    {
        // Define the preferred order for note types
        $typeOrder = [
            '一般知識' => 1,
            '生態習性' => 2,
            '營養價值' => 3,
            '烹飪方法' => 4,
            '文化意義' => 5,
            '其他' => 6,
            '未分類' => 7
        ];

        // Group notes by type, handling null/empty types
        $grouped = $notes->groupBy(function ($note) {
            return $note->note_type ?: '未分類';
        });

        // Transform and sort the groups
        return $grouped->map(function ($groupedNotes, $type) {
            return [
                'name' => $type,
                'count' => $groupedNotes->count(),
                'notes' => $groupedNotes->sortByDesc('created_at')->values()
            ];
        })->sortBy(function ($group) use ($typeOrder) {
            // Sort by predefined order, unknown types go to the end
            return $typeOrder[$group['name']] ?? 999;
        })->values();
    }

    /**
     * Get available note types in preferred order
     */
    private function getNoteTypes()
    {
        return [
            '一般知識',
            '生態習性',
            '營養價值',
            '烹飪方法',
            '文化意義',
            '其他'
        ];
    }

    /**
     * Get note type statistics for a fish
     */
    private function getNoteTypeStats($notes)
    {
        $stats = [
            'total' => $notes->count(),
            'by_type' => []
        ];

        $grouped = $notes->groupBy(function ($note) {
            return $note->note_type ?: '未分類';
        });

        foreach ($grouped as $type => $typeNotes) {
            $stats['by_type'][$type] = $typeNotes->count();
        }

        return $stats;
    }

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
