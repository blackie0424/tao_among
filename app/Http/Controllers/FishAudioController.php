<?php

namespace App\Http\Controllers;

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\AudioConfirmService;
use App\Services\FishAudioRecordingService;
use App\Services\FishNameSplitter;
use App\Services\FishService;
use App\Traits\HasFishImageUrl;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class FishAudioController extends BaseController
{
    use HasFishImageUrl;

    protected $fishService;

    protected $storageService;

    public function __construct(
        FishService $fishService,
        StorageServiceInterface $storageService,
        private readonly FishAudioRecordingService $recordings,
        private readonly AudioConfirmService $confirmService,
        private readonly FishNameSplitter $splitter,
    ) {
        $this->fishService = $fishService;
        $this->storageService = $storageService;
    }

    /**
     * Show the form for creating a new fish audio
     */
    public function create(Request $request, $fishId)
    {
        $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');
        $fishWithUrls = $this->assignFishImage($fish);

        return Inertia::render('CreateFishAudio', [
            'fish' => $fishWithUrls,
            'names' => $this->splitter->split($fish->name),
            'spokenName' => $request->query('name'),
            'tribes' => config('fish_options.tribes', []),
            'maxSeconds' => FishAudio::MAX_DURATION_MS / 1000,
        ]);
    }

    /**
     * Display the audio list page for a specific fish
     */
    public function audioList($fishId)
    {
        try {
            $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');
            $fish->load(['audios' => fn ($q) => $q->with('recorder:id,name')]);
            $fish->audios->each->makeVisible('recorded_by');

            $fishWithUrls = $this->assignFishImage($fish);
            $grouped = $this->recordings->groupByName($fish);

            $this->logOperation('Audio list viewed', [
                'fish_id' => $fishId,
                'audios_count' => $fish->audios->count(),
            ]);

            return Inertia::render('FishAudioList', [
                'fish' => $fishWithUrls,
                'audioGroups' => collect($grouped['groups'])
                    ->map(fn ($audios, $name) => ['name' => $name, 'audios' => $audios->map(fn ($a) => $this->withRecorder($a))->values()])
                    ->values(),
                'unassignedAudios' => $grouped['unassigned']->map(fn ($a) => $this->withRecorder($a))->values(),
            ]);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入發音列表');
        }
    }

    /**
     * Show the form for editing the specified audio
     */
    public function editAudio($fishId, $audioId)
    {
        try {
            $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');
            $audio = $this->findRelatedResourceOrFail(FishAudio::class, [
                'fish_id' => $fishId,
                'id' => $audioId,
            ], '發音資料');
            $audio->load('recorder:id,name')->makeVisible('recorded_by');

            $this->logOperation('Audio edit form accessed', [
                'fish_id' => $fishId,
                'audio_id' => $audioId,
            ]);

            return Inertia::render('Fish/EditAudio', [
                'fish' => $this->assignFishImage($fish),
                'audio' => $this->withRecorder($audio),
                'tribes' => config('fish_options.tribes', []),
                'maxSeconds' => FishAudio::MAX_DURATION_MS / 1000,
            ]);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入編輯頁面');
        }
    }

    /**
     * 編輯一段錄音：只能改部落、換音檔（名稱不可改；name 一律是實際檔名）。
     * 換音檔走「暫存簽名 → PUT → 這裡確認」，pending_filename 為暫存檔名。
     */
    public function updateAudio(Request $request, $fishId, $audioId)
    {
        try {
            $this->findResourceOrFail(Fish::class, $fishId, '魚類');
            $audio = $this->findRelatedResourceOrFail(FishAudio::class, [
                'fish_id' => $fishId,
                'id' => $audioId,
            ], '發音資料');

            $validated = $this->validateRequest($request, [
                'tribe' => ['required', Rule::in(config('fish_options.tribes', []))],
                'pending_filename' => ['nullable', 'string', 'regex:'.FishAudio::filenamePattern()],
                'duration_ms' => ['required_with:pending_filename', 'nullable', 'integer', 'min:1', 'max:'.FishAudio::MAX_DURATION_MS],
            ]);

            if (! empty($validated['pending_filename'])) {
                // 換音檔與改部落在同一個交易內完成（失敗時一起回滾並刪掉已複製的檔）
                $this->confirmService->replace($audio, $request->user()->id, $validated['pending_filename'], $validated['duration_ms'], $validated['tribe']);
            } else {
                $this->recordings->updateTribe($audio, $validated['tribe']);
            }

            $this->logOperation('Audio updated successfully', ['fish_id' => $fishId, 'audio_id' => $audioId]);

            return redirect("/fish/{$fishId}/audio-list")->with('success', '發音資料已成功更新');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '更新發音資料失敗');
        }
    }

    /**
     * 把一段錄音（通常是舊資料的未對應名稱）指派到這隻魚目前的某個名稱。
     */
    public function assignName(Request $request, $fishId, $audioId)
    {
        try {
            $this->findResourceOrFail(Fish::class, $fishId, '魚類');
            $audio = $this->findRelatedResourceOrFail(FishAudio::class, [
                'fish_id' => $fishId,
                'id' => $audioId,
            ], '發音資料');
            $validated = $this->validateRequest($request, ['name' => ['required', 'string']]);

            try {
                $this->recordings->assignName($audio, $validated['name']);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['name' => $e->getMessage()]);
            }

            return redirect("/fish/{$fishId}/audio-list")->with('success', '已指派名稱');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '指派名稱失敗');
        }
    }

    /**
     * Remove the specified audio from storage
     */
    public function destroyAudio($fishId, $audioId)
    {
        try {
            $this->findResourceOrFail(Fish::class, $fishId, '魚類');
            $audio = $this->findRelatedResourceOrFail(FishAudio::class, [
                'fish_id' => $fishId,
                'id' => $audioId,
            ], '發音資料');

            // 軟刪除；音檔由 FishAudio 的 deleting 事件刪除；刪的是主要發音那段時指標一併調整
            $this->recordings->delete($audio);

            $this->logOperation('Audio deleted successfully', ['fish_id' => $fishId, 'audio_id' => $audioId]);

            return redirect("/fish/{$fishId}/audio-list")->with('success', '發音資料已成功刪除');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '刪除發音資料失敗');
        }
    }

    /**
     * Update the fish's main audio filename
     * Set the selected audio as the base/main audio for the fish
     */
    public function updateAudioFilename(Request $request, $fishId, $audioId)
    {
        try {
            return $this->executeWithTransaction(function () use ($fishId, $audioId) {
                // 取出 fish 與指定 audio
                $fish = Fish::with('audios')->findOrFail($fishId);
                $audio = $fish->audios()->where('id', $audioId)->firstOrFail();

                // 更新主檔案欄位
                $fish->update([
                    'audio_filename' => $audio->name,
                ]);

                // 使用 Trait 處理媒體 URL
                $fish = $this->assignFishImage($fish);

                $this->logOperation('Main audio filename updated', [
                    'fish_id' => $fishId,
                    'audio_id' => $audioId,
                    'audio_name' => $audio->name,
                ]);

                // 重導回 MediaManager
                return redirect("/fish/{$fishId}/audio-list")
                    ->with('success', '魚類發音更新成功');
            }, 'audio filename update');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '更新魚類發音失敗');
        }
    }

    /** 田調人員專用頁面的錄音資料：附錄音者名稱（空＝未記錄）。 */
    private function withRecorder(FishAudio $audio): array
    {
        return $audio->makeVisible('recorded_by')->toArray() + ['recorder_name' => $audio->recorder?->name];
    }
}
