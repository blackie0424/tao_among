<?php

namespace App\Services;

use \Carbon\Carbon;

use App\Models\Fish;
use App\Models\CaptureRecord;
use App\Http\Resources\FishResource;
use App\Contracts\StorageServiceInterface;
use App\Contracts\FishServiceInterface;
use Illuminate\Support\Collection;

class FishService implements FishServiceInterface
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
     * @return array{fish: Fish, tribalClassifications: mixed, captureRecords: mixed, fishNotes: array<string, array<int, array<string, mixed>>>}
     */
    public function getFishDetails(int $id): array
    {
        $fish = Fish::with([
            'tribalClassifications',
            'captureRecords',
            'notes' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'referenceKnowledge' => fn ($q) => $q
                ->with('reference')
                ->orderBy('reference_id')
                ->orderBy('page_start')
                ->orderBy('page_end')
                ->orderBy('id'),
            'audios',
            'displayCaptureRecord',
        ])->findOrFail($id);

        // 套用媒體 URL 規則
        $fish = $this->decorateFishMedia($fish);

        return [
            'fish' => $fish,
            'tribalClassifications' => $fish->tribalClassifications,
            'captureRecords' => $fish->captureRecords,
            'fishNotes' => $this->groupFishNotesByType($fish->notes),
            'referenceKnowledge' => $fish->referenceKnowledge,
        ];
    }

    /**
     * 將進階知識整理成以前端頁面直接可用的 keyed object。
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupFishNotesByType(Collection $notes): array
    {
        $noteTypeOrder = config('fish_options.note_types', []);

        return $notes
            ->groupBy(fn ($note) => $note->note_type ?: '未分類')
            ->sortBy(function ($items, $type) use ($noteTypeOrder) {
                $index = array_search($type, $noteTypeOrder, true);

                return $index === false ? PHP_INT_MAX : $index;
            })
            ->mapWithKeys(fn ($items, $type) => [$type => $items->values()->toArray()])
            ->toArray();
    }

    /**
     * 從 LINE Bot 建立魚類資料（不含捕獲紀錄），供後續填寫表單使用
     *
     * @param string|null $name 魚類名稱，null 時使用預設值「我不知道」
     * @param string[] $filenames 已上傳至 S3 的圖片檔名陣列（basename only）
     */
    public function createFishWithImages(?string $name, array $filenames): Fish
    {
        return Fish::create([
            'name'  => $name ?: '我不知道',
            'image' => $filenames[0],
        ]);
    }

    /**
     * 從 LINE Bot 建立魚類記錄（含批次捕獲記錄）
     *
     * @param string|null $name 魚類名稱，null 時使用預設值「我不知道」
     * @param string[] $filenames 已上傳至 S3 的圖片檔名陣列（basename only）
     */
    public function createFishFromLine(?string $name, array $filenames, array $captureData = []): Fish
    {
        $fishName       = $name ?: '我不知道';
        $tribe          = $captureData['tribe']          ?? 'iraraley';
        $location       = $captureData['location']       ?? 'LINE Bot';
        $captureMethod  = $captureData['capture_method'] ?? '未知';
        $captureDate    = $captureData['capture_date']   ?? now()->toDateString();
        $notes          = $captureData['notes']          ?? null;

        $fish = Fish::create([
            'name'  => $fishName,
            'image' => $filenames[0],
        ]);

        $firstRecordId = null;
        foreach ($filenames as $index => $filename) {
            $record = CaptureRecord::create([
                'fish_id'        => $fish->id,
                'image_path'     => $filename,
                'tribe'          => $tribe,
                'location'       => $location,
                'capture_method' => $captureMethod,
                'capture_date'   => $captureDate,
                'notes'          => $notes,
            ]);
            if ($index === 0) {
                $firstRecordId = $record->id;
            }
        }

        if ($firstRecordId) {
            $fish->update(['display_capture_record_id' => $firstRecordId]);
            $fish->display_capture_record_id = $firstRecordId;
        }

        return $fish;
    }
}
