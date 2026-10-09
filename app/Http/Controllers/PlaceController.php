<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlaceRequest;
use App\Models\Place;
use App\Services\PlaceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    public function store(PlaceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $normalized = $this->service->normalize($data['name']);
        $scopeKey = PlaceService::scopeKey($data['tribe'] ?? null);
        if ($existing = Place::where('scope_key', $scopeKey)->where('name_key', $normalized['name_key'])->first()) {
            return $this->duplicate($existing);
        }

        try {
            $place = $this->service->create($data, $request->user()->role !== 'admin');
        } catch (QueryException $exception) {
            $existing = Place::where('scope_key', $scopeKey)->where('name_key', $normalized['name_key'])->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->duplicate($existing);
        }

        return response()->json(['place' => $place], 201);
    }

    public function suggest(Request $request): JsonResponse
    {
        $query = $this->service->normalize((string) $request->query('q', ''))['name_key'];
        $tribe = $request->query('tribe');
        $escaped = addcslashes($query, '%_\\');
        $places = Place::query()
            ->when($tribe !== null && $tribe !== '', fn ($builder) => $builder->whereIn('scope_key', [PlaceService::scopeKey((string) $tribe), '']))
            ->where(fn ($builder) => $builder->where('name_key', 'like', "%{$escaped}%")->orWhere('tao_name', 'like', "%{$escaped}%"))
            ->when($tribe !== null && $tribe !== '', fn ($builder) => $builder->orderByRaw('CASE WHEN scope_key = ? THEN 0 ELSE 1 END', [PlaceService::scopeKey((string) $tribe)]))
            ->orderBy('name')->orderBy('id')->limit(10)->get(['id', 'tribe', 'name', 'tao_name', 'is_provisional']);

        return response()->json(['places' => $places]);
    }

    private function duplicate(Place $place): JsonResponse
    {
        return response()->json(['message' => '已有同名地名，要用既有的嗎？', 'existing_place' => $place], 422);
    }
}
