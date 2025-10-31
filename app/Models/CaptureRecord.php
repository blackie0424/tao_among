<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        
        $supabaseStorage = app(\App\Services\SupabaseStorageService::class);
        $hasWebp = null;
        if ($this->relationLoaded('fish') && $this->fish) {
            $hasWebp = $this->fish->has_webp ?? null;
        }
        return $supabaseStorage->getUrl('images', $this->image_path, $hasWebp);
    }
}
