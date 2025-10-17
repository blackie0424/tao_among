<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\FishService;

class KnowledgeHubController extends Controller
{
    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    public function index($id, Request $request)
    {

        $locate = $request->query('locate') ? strtolower($request->query('locate')) : 'iraraley';
        $fish = $this->fishService->getFishByIdAndLocate($id, $locate);
        return Inertia::render('knowledge', [
            'fish' => $fish
        ]);
    }
}
