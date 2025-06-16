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

    protected static function booted()
    {
        static::deleting(function ($fish) {        
            $fish->size()->delete();
        });
    }

    // 一對多關聯：一隻魚有多個筆記
    public function notes(): HasMany
    {
        return $this->hasMany(FishNote::class, 'fish_id');
    }

     // 一對多關聯：一隻魚有多個筆記
    public function size()
    {
        return $this->hasOne(FishSize::class, 'fish_id');
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($fish) {
            // 如果是軟刪除，才 cascade
            if (! $fish->isForceDeleting()) {
                $fish->notes()->delete();
            }
        });
    }
}
