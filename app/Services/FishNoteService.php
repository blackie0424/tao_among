<?php

namespace App\Services;

use App\Models\Fish;
use App\Models\FishNote;

class FishNoteService
{
    /**
     * @param array{note: string, note_type: string, locate: string} $attributes
     */
    public function createForFish(Fish $fish, array $attributes): FishNote
    {
        return FishNote::create([
            'fish_id' => $fish->id,
            'note' => $attributes['note'],
            'note_type' => $attributes['note_type'],
            'locate' => $attributes['locate'],
        ]);
    }
}
