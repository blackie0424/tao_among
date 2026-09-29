<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TopicItem extends Model
{
    use HasFactory;

    protected $table = 'topic_items';

    protected $fillable = [
        'topic_id',
        'title',
        'image_path',
        'description',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        // 如果是 http(s) 開頭,直接返回
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }

        // 根據環境選擇 disk
        $disk = app()->environment('local', 'testing') ? 'public' : 's3';
        return Storage::disk($disk)->url($this->image_path);
    }
}
