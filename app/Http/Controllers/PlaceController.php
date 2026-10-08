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
        $normalized = $this->service->normalize($request->string('name')->toString());
        if ($existing = Place::where('name_key', $normalized['name_key'])->first()) {
            return $this->duplicate($existing);
        }

        try {
            $place = Place::create([...$normalized, 'tao_name' => $request->input('tao_name'), 'notes' => $request->input('notes')]);
        } catch (QueryException $exception) {
            $existing = Place::where('name_key', $normalized['name_key'])->first();
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
        $escaped = addcslashes($query, '%_\\');
        $places = Place::query()
            ->where(fn ($builder) => $builder->where('name_key', 'like', "%{$escaped}%")->orWhere('tao_name', 'like', "%{$escaped}%"))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'tao_name']);

        return response()->json(['places' => $places]);
    }

    private function duplicate(Place $place): JsonResponse
    {
        return response()->json(['message' => '已有同名地名，要用既有的嗎？', 'existing_place' => $place], 422);
    }
}
