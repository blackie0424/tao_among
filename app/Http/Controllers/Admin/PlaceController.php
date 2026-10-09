<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceRequest;
use App\Models\Place;
use App\Services\PlaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    public function index(Request $request): Response
    {
        $provisionalCount = Place::where('is_provisional', true)->count();
        $places = Place::withCount('captureSessions')
            ->when($request->boolean('provisional'), fn ($query) => $query->where('is_provisional', true))
            ->orderByDesc('is_provisional')->orderBy('tribe')->orderBy('name')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Places/Index', [
            'places' => $places,
            'provisionalCount' => $provisionalCount,
            'showProvisional' => $request->boolean('provisional'),
        ]);
    }

    public function edit(Place $place): Response
    {
        return Inertia::render('Admin/Places/Edit', [
            'place' => $place,
            'tribes' => config('fish_options.tribes'),
            'mergeTargets' => Place::whereKeyNot($place->id)->orderBy('name')->get(['id', 'tribe', 'name']),
        ]);
    }

    public function update(PlaceRequest $request, Place $place): RedirectResponse
    {
        $data = $request->validated();
        if ($place->is_provisional) {
            $data['is_provisional'] = false;
        }
        $this->service->update($place, $data);

        return redirect('/admin/places')->with('success', '地名已更新');
    }

    public function confirm(Place $place): RedirectResponse
    {
        $this->service->confirm($place);

        return redirect('/admin/places')->with('success', '地名已確認');
    }

    public function merge(Request $request, Place $place): RedirectResponse
    {
        $validated = $request->validate(['target_place_id' => ['required', 'integer', 'exists:places,id']]);
        $this->service->merge($place, Place::findOrFail($validated['target_place_id']));

        return redirect('/admin/places')->with('success', '地名已併入');
    }

    public function destroy(Place $place): RedirectResponse
    {
        $this->service->delete($place);

        return redirect('/admin/places')->with('success', '地名已刪除');
    }
}
