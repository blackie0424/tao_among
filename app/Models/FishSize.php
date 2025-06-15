<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FishSize extends Model
{
    use SoftDeletes;

    protected $table = 'fish_size';

    protected $fillable = [
        'fish_id',
        'parts',
    ];

    protected $casts = [
        'parts' => 'array',
    ];

    public function fish()
    {
        return $this->belongsTo(Fish::class, 'fish_id');
    }
}
