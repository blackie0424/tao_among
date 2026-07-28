<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IntroSlide extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'body',
        'media_type',
        'media_path',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
    ];

    protected $appends = ['media_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(IntroCategory::class, 'category_id');
    }

    public function getMediaUrlAttribute(): ?string
    {
        if (!$this->media_path) {
            return null;
        }

        if ($this->media_type === 'photo') {
            $disk = app()->environment('local', 'testing') ? 'public' : 's3';
            return Storage::disk($disk)->url($this->media_path);
        }

        return $this->media_path;
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
