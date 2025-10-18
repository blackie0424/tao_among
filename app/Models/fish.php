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

    protected $appends = ['image_url'];


    protected static function booted()
    {
        static::deleting(function ($fish) {
            // 刪除相關的尺寸資料
            $fish->size()->delete();
            
            // 刪除相關的知識條目
            $fish->notes()->delete();
            
            // 刪除相關的音頻文件
            $fish->audios()->delete();
            
            // 刪除相關的部落分類
            $fish->tribalClassifications()->delete();
            
            // 刪除相關的捕獲紀錄
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

    public function getImageUrlAttribute()
    {
        $storageUrl = env('SUPABASE_STORAGE_URL');
        $bucket = env('SUPABASE_BUCKET');
        if (!$this->image) {
            return env('ASSET_URL') . '/images/default.png';
        }
        return "{$storageUrl}/object/public/{$bucket}/images/{$this->image}";
    }
}
