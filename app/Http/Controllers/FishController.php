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
use App\Models\FishSize;

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
    public function createAudio()
    {
        return Inertia::render('CreateFishAudio');
    }

    public function edit($id)
    {
        // 取得指定魚類資訊
        $fish = Fish::findOrFail($id);
        // 回傳編輯畫面，帶入魚類資訊
        return Inertia::render('EditFishName', [
            'fish' => $fish
        ]);
    }

    public function editSize($id)
    {
        // 用 fish_id 查詢 fish_size 物件
        $fishSize = FishSize::where('fish_id', $id)->firstOrFail();
        // 回傳編輯畫面，帶入魚類尺寸資訊
        return Inertia::render('EditFishSize', [
            'fishSize' => $fishSize
        ]);
    }

}
