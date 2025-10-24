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

        return $fishes;
    }

    public function getFishesBySince($since)
    {
        $sinceDate = Carbon::createFromTimestamp($since);
        $fishes = Fish::where('updated_at', '>', $sinceDate)->get();

        return $this->assignImageUrls($fishes);
    }

    public function getFishById($id)
    {
        $fish = Fish::findOrFail($id);
        // 圖片：沒有時給預設圖
        if (!empty($fish->image)) {
            $fish->image = $this->storageService->getUrl('images', $fish->image);
        } else {
            $fish->image = $this->storageService->getUrl('images', 'default.png');
        }

        // 音檔：只有在有檔名時才呼叫 getUrl，避免傳入 null
        if (!empty($fish->audio_filename)) {
            $fish->audio_filename = $this->storageService->getUrl('audios', $fish->audio_filename);
        } else {
            $fish->audio_filename = null;
        }

        return $fish;
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
        // 先處理 url
        
        $result = $fish ? $this->assignImageUrls([$fish])[0] : null;
        return $result;
    }

    public function assignImageUrls($fishes)
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
            foreach ($fish->audios as $audio) {
                if ($audio && isset($audio->name) && $audio->name) {
                    $audio->url = $this->storageService->getUrl('audios', $audio->name);
                }
            }
        }

        return $fishes;
    }
}
