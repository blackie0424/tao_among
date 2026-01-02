<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaptureRecordRequest;
use App\Models\Fish;
use App\Models\CaptureRecord;
use App\Services\FishService;
use App\Contracts\StorageServiceInterface;
use App\Traits\HasFishImageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CaptureRecordController extends Controller
{
    use HasFishImageUrl;

    protected $fishService;
    protected $storageService;

    public function __construct(FishService $fishService, StorageServiceInterface $storageService)
    {
        $this->fishService = $fishService;
        $this->storageService = $storageService;
    }

    /**
     * Display a listing of capture records for a specific fish.
     */
    public function index($fishId)
    {
        // 取得指定魚類資訊和捕獲紀錄
        $fish = Fish::with('captureRecords')->findOrFail($fishId);
        
        // 使用 Trait 處理魚類圖片 URL
        $fishWithImage = $this->assignFishImage($fish);
        
        // 確保 captureRecords 以正確的鍵名傳遞
        $fishData = $fishWithImage->toArray();
        $fishData['captureRecords'] = $fishWithImage->captureRecords->toArray();
        
        // 定義部落選項
        $tribes = config('fish_options.tribes');
        
        return Inertia::render('CaptureRecords', [
            'fish' => $fishData,
            'tribes' => $tribes
        ]);
    }

    /**
     * Show the form for creating a new capture record.
     */
    public function create(Request $request, $fishId)
    {
        $fish = Fish::findOrFail($fishId);
        
        // 使用 Trait 處理圖片 URL
        $fishWithImage = $this->assignFishImage($fish);
        
        // 定義部落選項
        $tribes = config('fish_options.tribes');
        
        return Inertia::render('CreateCaptureRecord', [
            'fish' => $fishWithImage,
            'tribes' => $tribes,
            'prefill_image' => $request->query('prefill_image', '')
        ]);
    }

    /**
     * Store a newly created capture record in storage.
     */
    public function store(CaptureRecordRequest $request, $fishId)
    {
        $fish = Fish::findOrFail($fishId);

        $validated = $request->validated();

        // 檢查是否有圖片檔案名稱（前端已經上傳完成）
        if (empty($validated['image_filename'])) {
            return redirect()->back()->withErrors(['image' => '請上傳捕獲照片'])->withInput();
        }

        CaptureRecord::create([
            'fish_id' => $fish->id,
            'image_path' => $validated['image_filename'],
            'tribe' => $validated['tribe'],
            'location' => $validated['location'],
            'capture_method' => $validated['capture_method'],
            'capture_date' => $validated['capture_date'],
            'notes' => $validated['notes']
        ]);

        return redirect()->route('fish.capture-records', $fishId)->with('success', '捕獲紀錄新增成功');
    }

    /**
     * Show the form for editing the specified capture record.
     */
    public function edit($fishId, $recordId)
    {
        $fish = Fish::findOrFail($fishId);
        $record = CaptureRecord::where('fish_id', $fishId)
            ->where('id', $recordId)
            ->firstOrFail();
        
        // 使用 Trait 處理圖片 URL
        $fishWithImage = $this->assignFishImage($fish);
        
        // 定義部落選項
        $tribes = config('fish_options.tribes');
        
        return Inertia::render('EditCaptureRecord', [
            'fish' => $fishWithImage,
            'record' => $record,
            'tribes' => $tribes
        ]);
       
    }

    /**
     * Update the specified capture record in storage.
     */
    public function update(CaptureRecordRequest $request, $fishId, $recordId)
    {
        $record = CaptureRecord::where('fish_id', $fishId)
            ->where('id', $recordId)
            ->firstOrFail();

        $validated = $request->validated();

        $updateData = [
            'tribe' => $validated['tribe'],
            'location' => $validated['location'],
            'capture_method' => $validated['capture_method'],
            'capture_date' => $validated['capture_date'],
            'notes' => $validated['notes']
        ];

        // 處理圖片更新（如果有新圖片檔名）
        if (!empty($validated['image_filename'])) {
            // 刪除舊圖片
            if ($record->image_path) {
                try {
                    $this->storageService->delete($record->image_path);
                } catch (\Exception $e) {
                    // 記錄錯誤但不阻止更新操作
                    Log::error('Failed to delete old capture record image: ' . $e->getMessage());
                }
            }
            
            $updateData['image_path'] = $validated['image_filename'];
        }

        $record->update($updateData);

        // 重新載入捕獲紀錄頁面
        return redirect("/fish/{$fishId}/capture-records")
               ->with('success', "資料更新成功！");
    }

    /**
     * Remove the specified capture record from storage.
     */
    public function destroy($fishId, $recordId)
    {
        Log::info("Delete request received for fish: {$fishId}, record: {$recordId}");
        
        $record = CaptureRecord::where('fish_id', $fishId)
            ->where('id', $recordId)
            ->firstOrFail();
            
        // 執行軟刪除
        $record->delete();
        
        Log::info("Record deleted successfully, redirecting to capture records");

        return redirect()->route('fish.capture-records', $fishId)->with('success', '捕獲紀錄刪除成功');
    }
}
