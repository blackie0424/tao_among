<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FishNote extends Model
{
    use HasFactory;
    protected $fillable = ['fish_id', 'note', 'note_type','locate'];

    // 多對一關聯：一筆筆記屬於一隻魚
    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class, 'fish_id');
    }
}
