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
        // tribe 未帶參數或空字串 → 預設 iraraley
        $selectedTribe = $request->query('tribe') ?: 'iraraley';

        return Inertia::render('Dashboard', [
            'tribes'        => $this->service->getTribes(),
            'selectedTribe' => $selectedTribe,
            'fishStats'     => $this->service->getFishStats($selectedTribe),
            'tribalStats'   => $this->service->getTribalStats($selectedTribe),
            'audioStats'    => $this->service->getAudioStats($selectedTribe),
            'noteStats'     => $this->service->getNoteStats($selectedTribe),
        ]);
    }
}
