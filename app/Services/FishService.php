<?php

namespace App\Services;

use \Carbon\Carbon;

use App\Models\Fish;
use App\Http\Resources\FishResource;

class FishService
{
    protected $storageService;

    public function __construct(SupabaseStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function getAllFishes()
    {
        $fishes = Fish::with('tribalClassifications')->orderBy('id', 'desc')->get();
        return FishResource::collection($fishes);
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
        return $this->decorateFishMedia($fish);
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
                $fish->image =  $this->storageService->getUrl('images', 'default.png', $fish->has_webp ?? null);
            } else {
                $fish->image =  $this->storageService->getUrl('images', $fish->image, $fish->has_webp ?? null);
            }
            foreach ($fish->audios as $audio) {
                if ($audio && isset($audio->name) && $audio->name) {
                    $audio->url = $this->storageService->getUrl('audios', $audio->name);
                }
            }
        }

        return $fishes;
    }

    /**
     * 將單筆 fish 物件套用媒體 URL 規則（圖片 default、音檔 null-safe、audios.url）
     */
    public function decorateFishMedia(Fish $fish): Fish
    {
        // 圖片：沒有時給預設圖
        if (!empty($fish->image)) {
            $fish->image = $this->storageService->getUrl('images', $fish->image, $fish->has_webp ?? null);
        } else {
            $fish->image = $this->storageService->getUrl('images', 'default.png', $fish->has_webp ?? null);
        }

        // 音檔：只有在有檔名時才呼叫 getUrl，避免傳入 null
        if (!empty($fish->audio_filename)) {
            $fish->audio_filename = $this->storageService->getUrl('audios', $fish->audio_filename);
        } else {
            $fish->audio_filename = null;
        }

        // 針對 audios 關聯（若已載入）設定 url 欄位
        if ($fish->relationLoaded('audios')) {
            foreach ($fish->audios as $audio) {
                if ($audio && isset($audio->name) && $audio->name) {
                    $audio->url = $this->storageService->getUrl('audios', $audio->name);
                }
            }
        }

        return $fish;
    }

    /**
     * 載入魚類詳情（含關聯），並回傳分組後的 notes 與相關集合。
     * 目標：集中 eager loading 與分組，避免 N+1 與控制器重複。
     *
     * @return array{fish: Fish, tribalClassifications: mixed, captureRecords: mixed, fishNotes: array}
     */
    public function getFishDetails(int $id): array
    {
        $fish = Fish::with(['tribalClassifications', 'captureRecords', 'notes', 'audios'])
            ->findOrFail($id);

        // 套用媒體 URL 規則
        $fish = $this->decorateFishMedia($fish);

        // 分組 notes
        $groupedFishNotes = $fish->notes
            ->groupBy('note_type')
            ->map(function ($items) {
                return $items->values()->toArray();
            })->toArray();

        // Inertia 需要陣列，確保關聯輸出一致
        return [
            'fish' => $fish,
            'tribalClassifications' => $fish->tribalClassifications,
            'captureRecords' => $fish->captureRecords,
            'fishNotes' => $groupedFishNotes,
        ];
    }
}
