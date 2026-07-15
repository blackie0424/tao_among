<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FishLabel extends Model
{
    protected $fillable = ['group', 'name'];

    public function tribalClassifications(): BelongsToMany
    {
        return $this->belongsToMany(
            TribalClassification::class,
            'tribal_classification_labels',
            'fish_label_id',
            'tribal_classification_id'
        );
    }
}
