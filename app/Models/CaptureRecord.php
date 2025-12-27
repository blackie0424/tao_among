<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\StorageServiceInterface;

class CaptureRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fish_id',
        'image_path',
        'tribe',
        'location',
        'capture_method',
        'capture_date',
        'notes'
    ];

    protected $casts = [
        'capture_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'image_url'
    ];

    protected static function booted()
    {
        static::deleting(function ($record) {
            // 立即刪除 Storage 中的圖片檔案
            if ($record->image_path) {
                $storage = app(StorageServiceInterface::class);
                $imageFolder = $storage->getImageFolder();
                $storage->delete($imageFolder . '/' . $record->image_path);
            }
        });
    }

    // 多對一關聯：一筆捕獲紀錄屬於一隻魚
    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class, 'fish_id');
    }

    // 取得圖片 URL
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }
        
        $storage = app(\App\Contracts\StorageServiceInterface::class);
        $hasWebp = null;
        if ($this->relationLoaded('fish') && $this->fish) {
            $hasWebp = $this->fish->has_webp ?? null;
        }
        return $storage->getUrl('images', $this->image_path, $hasWebp);
    }
}
