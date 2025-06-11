<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fish extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fish';

    protected $fillable = ['name', 'image'];

    // 一對多關聯：一隻魚有多個筆記
    public function notes(): HasMany
    {
        return $this->hasMany(FishNote::class, 'fish_id');
    }
}
