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


class FishController extends Controller
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    public function index(): View
    {
        return view('welcome', ['fishes' => $this->fishService->getAllFishes()]);
    }

    public function getFish($id,Request $request): View
    {
        $locate = $request->query('locate') ? strtolower($request->query('locate')) : 'iraraley';
        
        return view('fish', ['fish' =>$this->fishService->getFishByIdAndLocate($id,$locate)]);
    }

    public function create(): View
    {
        return view('createFish');
    }

    
}
