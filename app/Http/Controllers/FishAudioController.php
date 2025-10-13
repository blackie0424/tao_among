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
        $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];

        return Inertia::render('FishAudioList', [
            'fish' => $fishWithImage
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

        if (!empty($validated['audio_filename'])) {
            // Handle audio file update
            $updateData['locate'] = $validated['audio_filename'];
        }

        $audio->update($updateData);

        return redirect()->route('fish.audio-list', $fishId);
    }

    /**
     * Remove the specified audio from storage
     */
    public function destroyAudio($fishId, $audioId)
    {
        $audio = FishAudio::where('fish_id', $fishId)->findOrFail($audioId);
        $audio->delete();

        return redirect()->route('fish.audio-list', $fishId);
    }
}
