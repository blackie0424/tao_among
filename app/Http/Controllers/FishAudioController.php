<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\FishService;
use Inertia\Inertia;

class FishAudioController extends Controller
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    /**
     * Display the audio list page for a specific fish
     */
    public function audioList($fishId)
    {
        $fish = Fish::with('audios')->findOrFail($fishId);
        $fishWithUrls = $this->fishService->assignImageUrls([$fish])[0];

        return Inertia::render('FishAudioList', [
            'fish' => $fishWithUrls
        ]);
    }

    /**
     * Show the form for editing the specified audio
     */
    public function editAudio($fishId, $audioId)
    {
        $fish = Fish::findOrFail($fishId);
        $audio = FishAudio::where('fish_id', $fishId)->findOrFail($audioId);

        return Inertia::render('EditFishAudio', [
            'fish' => $this->fishService->assignImageUrls([$fish])[0],
            'audio' => $audio
        ]);
    }

    /**
     * Update the specified audio in storage
     */
    public function updateAudio(Request $request, $fishId, $audioId)
    {
        $audio = FishAudio::where('fish_id', $fishId)->findOrFail($audioId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'audio_filename' => 'nullable|string'
        ]);

        $updateData = ['name' => $validated['name']];

        // Handle audio file update if new file is provided
        if (!empty($validated['audio_filename'])) {
            $oldAudioPath = $audio->locate;
            $updateData['locate'] = $validated['audio_filename'];
            
            // Clean up old audio file if it exists and is different from the new one
            if ($oldAudioPath && $oldAudioPath !== $validated['audio_filename']) {
                try {
                    $supabaseStorage = new \App\Services\SupabaseStorageService();
                    $supabaseStorage->delete('audio/' . $oldAudioPath);
                } catch (\Exception $e) {
                    // Log error but don't fail the update
                    \Log::error('Failed to delete old audio file: ' . $e->getMessage());
                }
            }
        }

        $audio->update($updateData);

        return redirect()->route('fish.audio-list', $fishId)
            ->with('success', '發音資料已成功更新');
    }

    /**
     * Remove the specified audio from storage
     */
    public function destroyAudio($fishId, $audioId)
    {
        $audio = FishAudio::where('fish_id', $fishId)->findOrFail($audioId);
        
        // Store audio file path before deletion
        $audioFilePath = $audio->locate;
        
        // Perform soft delete on the database record
        $audio->delete();
        
        // Clean up the audio file from storage
        if ($audioFilePath) {
            try {
                $supabaseStorage = new \App\Services\SupabaseStorageService();
                $supabaseStorage->delete('audio/' . $audioFilePath);
            } catch (\Exception $e) {
                // Log error but don't fail the deletion
                \Log::error('Failed to delete audio file during record deletion: ' . $e->getMessage());
            }
        }

        return redirect()->route('fish.audio-list', $fishId)
            ->with('success', '發音資料已成功刪除');
    }
}
