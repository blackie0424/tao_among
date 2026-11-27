<?php

namespace App\Models;

use App\Contracts\StorageServiceInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaptureRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fish_id',
        'session_id',
        'image_path',
        'tribe',
        'location',
        'capture_method',
        'capture_date',
        'notes',
        'image_focus_x',
        'image_focus_y',
        'image_zoom',
        'image_rotation',
    ];

    protected $casts = [
        'capture_date' => 'date',
        'image_focus_x' => 'float',
        'image_focus_y' => 'float',
        'image_zoom' => 'float',
        'image_rotation' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'image_url',
        'framing',
    ];

    protected static function booted()
    {
        static::deleting(function ($record) {
            // 立即刪除 Storage 中的圖片檔案
            if ($record->image_path) {
                $storage = app(StorageServiceInterface::class);
                $imageFolder = $storage->getImageFolder();
                $storage->delete($imageFolder.'/'.$record->image_path);
            }
        });
    }

    // 多對一關聯：一筆捕獲紀錄屬於一隻魚
    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class, 'fish_id');
    }

    public function captureSession(): BelongsTo
    {
        return $this->belongsTo(CaptureSession::class, 'session_id');
    }

    // 取得圖片 URL
    public function getImageUrlAttribute()
    {
        if (! $this->image_path) {
            return null;
        }

        $storage = app(\App\Contracts\StorageServiceInterface::class);
        $hasWebp = null;
        if ($this->relationLoaded('fish') && $this->fish) {
            $hasWebp = $this->fish->has_webp ?? null;
        }

        return $storage->getUrl('images', $this->image_path, $hasWebp);
    }

    // 照片調整值（魚身中心、放大、旋轉）：四者皆有值才回傳，否則 null＝沒調整
    public function getFramingAttribute(): ?array
    {
        $values = [
            'x' => $this->image_focus_x,
            'y' => $this->image_focus_y,
            'zoom' => $this->image_zoom,
            'rotation' => $this->image_rotation,
        ];

        return in_array(null, $values, true) ? null : $values;
    }
}
