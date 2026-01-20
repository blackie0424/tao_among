<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\FishService;
use App\Traits\HasFishImageUrl;
use Inertia\Inertia;
use Exception;
use Illuminate\Support\Facades\Log;

class FishAudioController extends BaseController
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
     * Show the form for creating a new fish audio
     */
    public function create($fishId)
    {
        $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');
        $fishWithUrls = $this->assignFishImage($fish);

        return Inertia::render('CreateFishAudio', [
            'fish' => $fishWithUrls,
        ]);
    }

    /**
     * Display the audio list page for a specific fish
     */
    public function audioList($fishId)
    {
        try {
            $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');
            $fish->load('audios');
            
            $fishWithUrls = $this->assignFishImage($fish);

            $this->logOperation('Audio list viewed', [
                'fish_id' => $fishId,
                'audios_count' => $fish->audios->count()
            ]);

            return Inertia::render('FishAudioList', [
                'fish' => $fishWithUrls
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
                'id' => $audioId
            ], '發音資料');

            $this->logOperation('Audio edit form accessed', [
                'fish_id' => $fishId,
                'audio_id' => $audioId
            ]);

            return Inertia::render('EditFishAudio', [
                'fish' => $this->assignFishImage($fish),
                'audio' => $audio
            ]);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入編輯頁面');
        }
    }

    /**
     * Update the specified audio in storage
     */
    public function updateAudio(Request $request, $fishId, $audioId)
    {
        try {
            return $this->executeWithTransaction(function () use ($request, $fishId, $audioId) {
                // Verify fish exists
                $this->findResourceOrFail(Fish::class, $fishId, '魚類');
                
                // Find the audio record
                $audio = $this->findRelatedResourceOrFail(FishAudio::class, [
                    'fish_id' => $fishId,
                    'id' => $audioId
                ], '發音資料');

                // Validate request
                $validated = $this->validateRequest($request, [
                    'name' => 'required|string|max:255',
                    'audio_filename' => 'nullable|string|max:255'
                ]);

                $oldData = $audio->toArray();
                $updateData = ['name' => $validated['name']];

                // Handle audio file update if new file is provided
                if (!empty($validated['audio_filename'])) {
                    $oldAudioPath = $audio->locate;
                    $updateData['locate'] = $validated['audio_filename'];
                    
                    // Clean up old audio file if it exists and is different from the new one
                    if ($oldAudioPath && $oldAudioPath !== $validated['audio_filename']) {
                        $this->executeFileOperation(function () use ($oldAudioPath) {
                            $audioFolder = $this->storageService->getAudioFolder();
                            $result = $this->storageService->deleteWithValidation($audioFolder . '/' . $oldAudioPath);
                            
                            if (!$result['success']) {
                                Log::warning('Failed to delete old audio file', [
                                    'file_path' => $oldAudioPath,
                                    'error' => $result['error'] ?? 'Unknown error'
                                ]);
                            }
                            
                            return $result['success'];
                        }, 'delete old audio file', false);
                    }
                }

                $audio->update($updateData);

                $this->logOperation('Audio updated successfully', [
                    'fish_id' => $fishId,
                    'audio_id' => $audioId,
                    'old_data' => $oldData,
                    'new_data' => $audio->fresh()->toArray()
                ]);

                return redirect()->route('fish.media-manager', $fishId)
                    ->with('success', '發音資料已成功更新');
            }, 'audio update');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '更新發音資料失敗');
        }
    }

    /**
     * Remove the specified audio from storage
     */
    public function destroyAudio($fishId, $audioId)
    {
        try {
            return $this->executeWithTransaction(function () use ($fishId, $audioId) {
                // Verify fish exists
                $this->findResourceOrFail(Fish::class, $fishId, '魚類');
                
                // Find the audio record
                $audio = $this->findRelatedResourceOrFail(FishAudio::class, [
                    'fish_id' => $fishId,
                    'id' => $audioId
                ], '發音資料');
                
                // Store audio data before deletion
                $audioData = $audio->toArray();
                $audioFilePath = $audio->locate;
                
                // Perform soft delete on the database record
                $audio->delete();
                
                // Clean up the audio file from storage (non-blocking)
                if ($audioFilePath) {
                    $this->executeFileOperation(function () use ($audioFilePath) {
                        $audioFolder = $this->storageService->getAudioFolder();
                        $result = $this->storageService->deleteWithValidation($audioFolder . '/' . $audioFilePath);
                        
                        if (!$result['success']) {
                            Log::warning('Failed to delete audio file during record deletion', [
                                'file_path' => $audioFilePath,
                                'error' => $result['error'] ?? 'Unknown error'
                            ]);
                        }
                        
                        return $result['success'];
                    }, 'delete audio file', false);
                }

                $this->logOperation('Audio deleted successfully', [
                    'fish_id' => $fishId,
                    'audio_id' => $audioId,
                    'deleted_data' => $audioData,
                    'file_path' => $audioFilePath
                ]);

                return redirect()->route('fish.media-manager', $fishId)
                    ->with('success', '發音資料已成功刪除');
            }, 'audio deletion');
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
                    'audio_name' => $audio->name
                ]);

                // 重導回 MediaManager
                return redirect()->route('fish.media-manager', $fishId)
                    ->with('success', '魚類發音更新成功');
            }, 'audio filename update');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '更新魚類發音失敗');
        }
    }
}
