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
        $fishes = Fish::all();

        return $this->assignImageUrls($fishes);
    }

    public function getFishesBySince($since)
    {
        $sinceDate =Carbon::createFromTimestamp($since);
        $fishes = Fish::where('created_at', '>', $sinceDate)->get();

        return $this->assignImageUrls($fishes);
    }

    public function getFishById($id)
    {
        $fish = Fish::with('notes')->findOrFail($id);

        return $fish ? $this->assignImageUrls([$fish])[0] : null;
    }

    private function assignImageUrls($fishes)
    {
        if (empty($fishes)) {
            return [];
        }

        $assetUrl = env('ASSET_URL');
        $isLocal = in_array(env('APP_ENV'), ['local', 'testing']);

        foreach ($fishes as $fish) {
            if ($fish->image == null || $fish->image == '') {
                $fish->image = $isLocal ? $assetUrl.'/images/default.png' : $this->storageService->getUrl('default.png');
            } else {
                $fish->image = $isLocal ? $assetUrl.'/images/'.$fish->image : $this->storageService->getUrl($fish->image);
            }
        }

        return $fishes;
    }

    public function addFishNote($fishId, $note, $noteType)
    {
        return Fish::findOrFail($fishId)->notes()->create([
            'note' => $note,
            'note_type' => $noteType,
        ]);
    }
}
