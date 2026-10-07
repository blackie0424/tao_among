<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceRequest;
use App\Models\Place;
use App\Services\PlaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Places/Index', [
            'places' => Place::withCount('captureSessions')->orderBy('name')->paginate(20),
        ]);
    }

    public function edit(Place $place): Response
    {
        return Inertia::render('Admin/Places/Edit', ['place' => $place]);
    }

    public function update(PlaceRequest $request, Place $place): RedirectResponse
    {
        $this->service->update($place, $request->validated());

        return redirect('/admin/places')->with('success', '地名已更新');
    }

    public function destroy(Place $place): RedirectResponse
    {
        $this->service->delete($place);

        return redirect('/admin/places')->with('success', '地名已刪除');
    }
}
