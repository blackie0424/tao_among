<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use App\Models\Fish;
use App\Models\FishNote;
use App\Services\FishService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Inertia\Inertia;

class FishController extends Controller
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    public function index()
    {
        return Inertia::render('Index');
    }

    public function getFish($id, Request $request)
    {
        $locate = $request->query('locate') ? strtolower($request->query('locate')) : 'iraraley';
        $fish = $this->fishService->getFishByIdAndLocate($id, $locate);
        return Inertia::render('Fish', ['fish' => $fish]);
    }

    public function getFishs(Request $request)
    {
        $fishes = $this->fishService->getAllFishes();
        return Inertia::render('Fishs', [
            'fishes' => $fishes
        ]);
    }

    public function create()
    {
        return Inertia::render('CreateFish');
    }
}
