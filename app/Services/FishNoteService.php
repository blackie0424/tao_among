<?php

namespace App\Services;

use App\Models\Fish;
use App\Models\FishNote;

class FishNoteService
{
    /**
     * @param  array{note: string, note_type: string, locate: string}  $attributes
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

    /**
     * @return array{
     *     notes: array<int, array{note: string, note_type: string, locate: string}>,
     *     total: int
     * }
     */
    public function getBrowseDataForFish(Fish $fish, int $limit = 6): array
    {
        $query = FishNote::query()
            ->where('fish_id', $fish->id)
            ->orderBy('note_type')
            ->orderBy('locate')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return [
            'notes' => (clone $query)
                ->limit($limit)
                ->get(['note', 'note_type', 'locate'])
                ->map(fn (FishNote $note) => [
                    'note' => $note->note,
                    'note_type' => $note->note_type ?? '',
                    'locate' => $note->locate ?? '',
                ])
                ->all(),
            'total' => (clone $query)->count(),
        ];
    }
}
