<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TopicItemMedia extends Model
{
    use HasFactory;

    public const TYPE_IMAGE = 'image';

    public const TYPE_YOUTUBE = 'youtube';

    public const TYPES = [self::TYPE_IMAGE, self::TYPE_YOUTUBE];

    protected $table = 'topic_item_media';

    protected $fillable = [
        'topic_item_id',
        'type',
        'source',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url', 'youtube_url'];

    public function topicItem(): BelongsTo
    {
        return $this->belongsTo(TopicItem::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->type !== self::TYPE_IMAGE) {
            return null;
        }

        if (str_starts_with($this->source, 'http')) {
            return $this->source;
        }

        $disk = app()->environment('local', 'testing') ? 'public' : 's3';

        return Storage::disk($disk)->url($this->source);
    }

    public function getYoutubeUrlAttribute(): ?string
    {
        return $this->type === self::TYPE_YOUTUBE
            ? "https://www.youtube.com/watch?v={$this->source}"
            : null;
    }
}
