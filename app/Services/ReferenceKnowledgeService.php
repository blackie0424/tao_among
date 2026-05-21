<?php

namespace App\Services;

use App\Models\Fish;
use App\Models\ReferenceKnowledge;
use App\Models\User;

class ReferenceKnowledgeService
{
    public function createForFish(Fish $fish, array $attributes, User $user): ReferenceKnowledge
    {
        return ReferenceKnowledge::create([
            'fish_id' => $fish->id,
            'reference_id' => $attributes['reference_id'],
            'content' => $attributes['content'],
            'pages' => $attributes['pages'],
            'note' => $attributes['note'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    public function update(ReferenceKnowledge $knowledge, array $attributes): ReferenceKnowledge
    {
        $knowledge->update([
            'reference_id' => $attributes['reference_id'],
            'content' => $attributes['content'],
            'pages' => $attributes['pages'],
            'note' => $attributes['note'] ?? null,
        ]);

        return $knowledge->fresh();
    }
}

