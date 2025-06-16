<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;




class FishSize extends Model
{
    use SoftDeletes,HasFactory;

    protected $table = 'fish_size';

    protected $fillable = [
        'fish_id',
        'parts',
    ];

    protected $casts = [
        'parts' => 'array',
    ];

    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class, 'fish_id');
    }
}
