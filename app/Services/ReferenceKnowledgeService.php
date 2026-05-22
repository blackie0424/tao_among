<?php

namespace App\Services;

use App\Models\Fish;
use App\Models\ReferenceKnowledge;
use App\Models\User;
use App\Support\ReferenceKnowledgePageParser;

class ReferenceKnowledgeService
{
    public function createForFish(Fish $fish, array $attributes, User $user): ReferenceKnowledge
    {
        $pageRange = ReferenceKnowledgePageParser::parse($attributes['pages']);

        return ReferenceKnowledge::create([
            'fish_id' => $fish->id,
            'reference_id' => $attributes['reference_id'],
            'tribe' => $attributes['tribe'] ?? null,
            'content' => $attributes['content'],
            'pages' => $attributes['pages'],
            'page_start' => $pageRange['start'],
            'page_end' => $pageRange['end'],
            'note' => $attributes['note'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    public function update(ReferenceKnowledge $knowledge, array $attributes): ReferenceKnowledge
    {
        $pageRange = ReferenceKnowledgePageParser::parse($attributes['pages']);

        $knowledge->update([
            'reference_id' => $attributes['reference_id'],
            'tribe' => $attributes['tribe'] ?? null,
            'content' => $attributes['content'],
            'pages' => $attributes['pages'],
            'page_start' => $pageRange['start'],
            'page_end' => $pageRange['end'],
            'note' => $attributes['note'] ?? null,
        ]);

        return $knowledge->fresh();
    }
}
