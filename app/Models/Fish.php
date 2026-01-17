<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 1. 引入 Attribute
use Illuminate\Database\Eloquent\Casts\Attribute;

// 2. 引入 Service
use App\Contracts\StorageServiceInterface;

class Fish extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fish';

    protected $fillable = ['name', 'image', 'audio_filename', 'display_capture_record_id'];

    protected $appends = ['image_url','audio_url', 'display_image_url'];

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
            $storage = app(StorageServiceInterface::class);
            
            // 只處理 Fish 自己的圖片檔案
            if ($fish->image && $fish->image !== 'default.png') {
                $imageFolder = $storage->getImageFolder();
                $storage->delete($imageFolder . '/' . $fish->image);
                
                // 如果有 WebP 版本也刪除
                if ($fish->has_webp) {
                    $imageWithoutExt = pathinfo($fish->image, PATHINFO_FILENAME);
                    $webpFolder = $storage->getWebpFolder();
                    $storage->delete($webpFolder . '/' . $imageWithoutExt . '.webp');
                }
            }
            
            // 軟刪除關聯資料
            // 使用 each()->delete() 來觸發每個子模型的 deleting 事件
            // 這樣 FishAudio 和 CaptureRecord 會自動刪除各自的檔案
            $fish->notes()->delete();
            $fish->audios->each->delete();
            $fish->tribalClassifications()->delete();
            $fish->captureRecords->each->delete();
        });
    }

    // 一對多關聯：一隻魚有多個筆記
    public function notes(): HasMany
    {
        return $this->hasMany(FishNote::class, 'fish_id');
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

    // 多對一關聯：使用者選擇的圖鑑展示圖片（參考捕獲紀錄）
    public function displayCaptureRecord()
    {
        return $this->belongsTo(CaptureRecord::class, 'display_capture_record_id');
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
                return app(StorageServiceInterface::class)->getUrl('images', $filename, $hasWebp);
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
                // 檢查 audio_filename 是否存在且不為 null
                if (!isset($attributes['audio_filename']) || $attributes['audio_filename'] === null) {
                    return null;
                }
                
                return app(StorageServiceInterface::class)->getUrl('audios', $attributes['audio_filename'], null);
            }
        );
    }

    /**
     * 取得圖鑑要顯示的圖片 URL
     * 優先顯示使用者選擇的捕獲紀錄圖片，否則顯示 Fish 自己的圖片
     * 呼叫方式: $fish->display_image_url
     */
    protected function displayImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 優先：使用者選擇的捕獲紀錄圖片
                if ($this->display_capture_record_id) {
                    // 只在關聯已預載時才使用，避免 N+1 查詢
                    if ($this->relationLoaded('displayCaptureRecord')) {
                        $record = $this->displayCaptureRecord;
                        
                        // 確保紀錄存在且未被軟刪除
                        if ($record && !$record->trashed()) {
                            return $record->image_url;
                        }
                    }
                    // 若未預載，回退使用 Fish 自己的圖片（避免 N+1）
                }
                
                // 回退：使用 Fish 自己的圖片
                return $this->image_url;
            }
        );
    }


}
