<?php

namespace App\Http\Controllers;

use App\Http\Requests\TribalClassificationRequest;
use App\Models\Fish;
use App\Models\TribalClassification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribalClassificationController extends Controller
{
    /**
     * Display a listing of the resource for a specific fish.
     */
    public function index($fishId): JsonResponse
    {
        $fish = Fish::findOrFail($fishId);
        
        $classifications = $fish->tribalClassifications()
            ->orderBy('tribe')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Tribal classifications retrieved successfully',
            'data' => $classifications,
            'fish' => [
                'id' => $fish->id,
                'name' => $fish->name
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TribalClassificationRequest $request, $fishId): JsonResponse
    {
        $fish = Fish::findOrFail($fishId);

        $validated = $request->validated();

        // 檢查是否已存在相同部落的分類（允許同部落多筆記錄）
        $classification = TribalClassification::create([
            'fish_id' => $fish->id,
            'tribe' => $validated['tribe'],
            'food_category' => $validated['food_category'] ?? '',
            'processing_method' => $validated['processing_method'] ?? '',
            'notes' => $validated['notes']
        ]);

        return response()->json([
            'message' => 'Tribal classification created successfully',
            'data' => $classification
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $classification = TribalClassification::with('fish')->findOrFail($id);

        return response()->json([
            'message' => 'Tribal classification retrieved successfully',
            'data' => $classification
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TribalClassificationRequest $request, string $id): JsonResponse
    {
        $classification = TribalClassification::findOrFail($id);

        $validated = $request->validated();

        $classification->update([
            'tribe' => $validated['tribe'],
            'food_category' => $validated['food_category'] ?? '',
            'processing_method' => $validated['processing_method'] ?? '',
            'notes' => $validated['notes']
        ]);

        return response()->json([
            'message' => 'Tribal classification updated successfully',
            'data' => $classification->fresh()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $classification = TribalClassification::findOrFail($id);
        $classification->delete();

        return response()->json([
            'message' => 'Tribal classification deleted successfully'
        ]);
    }
}
