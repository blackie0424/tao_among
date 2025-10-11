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
            $fish->notes()->delete();
            $fish->tribalClassifications()->delete();
            $fish->captureRecords()->delete();
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

    // 一對多關聯：一隻魚有多個audio
    public function audios()
    {
        return $this->hasMany(FishAudio::class, 'fish_id');
    }

    // 一對多關聯：一隻魚有多個部落分類
    public function tribalClassifications(): HasMany
    {
        return $this->hasMany(TribalClassification::class, 'fish_id');
    }

    // 一對多關聯：一隻魚有多個捕獲紀錄
    public function captureRecords(): HasMany
    {
        return $this->hasMany(CaptureRecord::class, 'fish_id');
    }

    // 確保關聯在序列化時保持 camelCase 命名
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
