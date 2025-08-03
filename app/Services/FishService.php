<?php

namespace App\Services;

use \Carbon\Carbon;

use App\Models\Fish;

class FishService
{
    protected $storageService;

    public function __construct(SupabaseStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function getAllFishes()
    {
        $fishes = Fish::orderBy('id', 'desc')->get();

        return $this->assignImageUrls($fishes);
    }

    public function getFishesBySince($since)
    {
        $sinceDate = Carbon::createFromTimestamp($since);
        $fishes = Fish::where('updated_at', '>', $sinceDate)->get();

        return $this->assignImageUrls($fishes);
    }

    public function getFishById($id)
    {
        $fish = Fish::with('notes')->findOrFail($id);

        return $fish ? $this->assignImageUrls([$fish])[0] : null;
    }

    public function getFishByIdAndLocate($id, $locate)
    {
        $fish = Fish::with([
            'notes' => function ($query) use ($locate) {
                $query->where('locate', $locate);
            },
            'audios' => function ($query) {
                $query->orderByDesc('id')->limit(1); // 只取最新一筆 audio 物件
            }
        ])->findOrFail($id);

        // 先處理圖片 url
        $result = $fish ? $this->assignImageUrls([$fish])[0] : null;

        // 處理 audio url，加上前綴
        if ($result && isset($result->audios)) {
            // audios 可能是單一物件或集合，統一處理為陣列
            $audios = is_array($result->audios) || $result->audios instanceof \Illuminate\Support\Collection
                ? $result->audios
                : [$result->audios];

            foreach ($audios as $audio) {
                if ($audio && isset($audio->url) && $audio->url) {
                    $audio->url = $this->storageService->getUrl('audios', $audio->url);
                }
            }
            // 若 audios 是單一物件，重新賦值為陣列
            $result->audios = $audios;
        }

        return $result;
    }

    private function assignImageUrls($fishes)
    {
        if (empty($fishes)) {
            return [];
        }

        $assetUrl = env('ASSET_URL');

        foreach ($fishes as $fish) {
            if ($fish->image == null || $fish->image == '') {
                $fish->image =  $this->storageService->getUrl('images', 'default.png');
            } else {
                $fish->image =  $this->storageService->getUrl('images', $fish->image);
            }
        }

        return $fishes;
    }
}
