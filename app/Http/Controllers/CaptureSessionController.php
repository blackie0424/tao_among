<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaptureSessionRequest;
use App\Models\CaptureSession;
use App\Models\Place;
use App\Services\CaptureSessionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CaptureSessionController extends Controller
{
    public function __construct(private readonly CaptureSessionService $service) {}

    public function index(): Response
    {
        $sessions = CaptureSession::with('place')
            ->withCount('captureRecords')
            ->withCount(['captureRecords as all_records_count' => fn ($query) => $query->withTrashed()])
            ->orderByDesc('capture_date')->orderByDesc('id')->paginate(20)
            ->through(fn (CaptureSession $session) => [
                'id' => $session->id,
                'capture_date' => $session->capture_date->format('Y-m-d'),
                'tribe' => $session->tribe,
                'capture_method' => $session->capture_method,
                'place' => $session->place,
                'location_hint' => $session->location_hint,
                'record_count' => $session->capture_records_count,
                'can_delete' => $session->all_records_count === 0,
            ]);

        return Inertia::render('CaptureSessions/Index', ['sessions' => $sessions]);
    }

    public function create(): Response
    {
        return Inertia::render('CaptureSessions/Create', $this->formProps());
    }

    public function store(CaptureSessionRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $session = $this->service->create($request->validated());
        if ($request->expectsJson()) {
            return response()->json(['session' => $session->load('place')], 201);
        }

        return redirect('/capture-sessions')->with('success', '情境已建立');
    }

    public function edit(CaptureSession $session): Response
    {
        return Inertia::render('CaptureSessions/Edit', [...$this->formProps(), 'session' => $session->load('place')]);
    }

    public function update(CaptureSessionRequest $request, CaptureSession $session): RedirectResponse
    {
        $this->service->update($session, $request->validated());

        return redirect('/capture-sessions')->with('success', '情境已更新');
    }

    public function destroy(CaptureSession $session): RedirectResponse
    {
        $this->service->delete($session);

        return redirect('/capture-sessions')->with('success', '情境已刪除');
    }

    private function formProps(): array
    {
        return ['tribes' => config('fish_options.tribes'), 'captureMethods' => config('fish_options.capture_methods'), 'places' => Place::orderBy('name')->get(['id', 'name', 'tao_name'])];
    }
}
