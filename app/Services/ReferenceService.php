<?php

namespace App\Services;

use App\Models\Reference;

class ReferenceService
{
    public function create(array $attributes): Reference
    {
        return Reference::create($attributes);
    }

    public function update(Reference $reference, array $attributes): Reference
    {
        $reference->update($attributes);

        return $reference->fresh();
    }
}

