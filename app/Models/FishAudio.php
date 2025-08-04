<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FishAudio extends Model
{

    use HasFactory, SoftDeletes;
    protected $table = 'fish_audios';

    protected $fillable = ['fish_id', 'name', 'locate'];

    // 多對一關聯：一筆筆記屬於一隻魚
    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class, 'fish_id');
    }
}
