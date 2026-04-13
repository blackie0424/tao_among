<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service)
    {
    }

    public function index(Request $request): Response
    {
        $selectedTribe = $request->query('tribe', 'iraraley');

        return Inertia::render('Dashboard', [
            'tribes'        => $this->service->getTribes(),
            'selectedTribe' => $selectedTribe,
            'fishStats'     => $this->service->getFishStats($selectedTribe),
            'captureStats'  => $this->service->getCaptureStats($selectedTribe),
            'tribalStats'   => $this->service->getTribalStats($selectedTribe),
            'audioStats'    => $this->service->getAudioStats($selectedTribe),
            'noteStats'     => $this->service->getNoteStats($selectedTribe),
        ]);
    }
}
