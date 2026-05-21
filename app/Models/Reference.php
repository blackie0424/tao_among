<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reference extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image_url',
        'external_url',
        'author',
        'status',
    ];

    public function knowledge(): HasMany
    {
        return $this->hasMany(ReferenceKnowledge::class, 'reference_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('status', 'enabled');
    }

    public function scopeDisabled($query)
    {
        return $query->where('status', 'disabled');
    }
}

