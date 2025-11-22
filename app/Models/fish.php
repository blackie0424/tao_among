<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 1. 引入 Attribute
use Illuminate\Database\Eloquent\Casts\Attribute;

// 2. 引入 Service
use App\Services\SupabaseStorageService;

class Fish extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fish';

    protected $fillable = ['name', 'image', 'audio_filename'];

    protected $appends = ['image_url','audio_url'];

    protected static function booted()
    {
        static::creating(function ($fish) {
            // 測試有時只給 name，避免因 image NOT NULL 造成例外，預設補上 default.png
            if (empty($fish->image)) {
                $fish->image = 'default.png';
            }
            // has_webp 預設 false（若欄位存在）
            if (property_exists($fish, 'has_webp') && $fish->has_webp === null) {
                $fish->has_webp = false;
            }
        });
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

    /**
     * 取得圖片的完整 URL
     * 呼叫方式: $fish->image_url
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                // 處理空值：如果資料庫是 null，就給預設圖檔名
                $filename = $attributes['image'] ?? 'default.png';
                $hasWebp = isset($attributes['has_webp']) ? (bool)$attributes['has_webp'] : false;

                // 呼叫 Service 轉換
                return app(SupabaseStorageService::class)->getUrl('images', $filename, $hasWebp);
            }
        );
    }

    /**
     * 取得聲音檔案的完整 URL
     * 呼叫方式: $fish->audio_url
     */
    protected function audioUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                // 如果是 null, 直接回傳 null。否則，呼叫 Service 轉換。
                return $attributes['audio_filename'] === null
                    ? null
                    : app(SupabaseStorageService::class)->getUrl('audios', $attributes['audio_filename'], null);
            }
        );
    }


}
