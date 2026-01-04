<?php

namespace App\Services;

use \Carbon\Carbon;

use App\Models\Fish;
use App\Http\Resources\FishResource;
use App\Contracts\StorageServiceInterface;

class FishService
{
    protected $storageService;

    public function __construct(StorageServiceInterface $storageService)
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

    /**
     * 為魚類集合的 audios 關聯設定 url 欄位
     *
     * 注意：不要覆蓋 $fish->image，因為 Fish Model 的 accessor（image_url, display_image_url）
     * 會自動根據原始檔名產生完整 URL。
     */
    public function assignImageUrls($fishes)
    {
        if (empty($fishes)) {
            return [];
        }

        foreach ($fishes as $fish) {
            // 只處理 audios 關聯的 url 欄位
            foreach ($fish->audios as $audio) {
                if ($audio && isset($audio->name) && $audio->name) {
                    $audio->url = $this->storageService->getUrl('audios', $audio->name);
                }
            }
        }

        return $fishes;
    }

    /**
     * 將單筆 fish 物件套用媒體 URL 規則（音檔 audios.url）
     *
     * 注意：不要覆蓋 $fish->image 或 $fish->audio_filename，
     * 因為 Fish Model 的 accessor（image_url, audio_url, display_image_url）
     * 會自動根據原始檔名產生完整 URL。直接覆蓋會導致 accessor 收到
     * 已轉換的 URL 而產生錯誤的雙重路徑。
     */
    public function decorateFishMedia(Fish $fish): Fish
    {
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
