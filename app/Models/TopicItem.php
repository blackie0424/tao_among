<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicItem extends Model
{
    use HasFactory;

    protected $table = 'topic_items';

    protected $fillable = [
        'topic_id',
        'title',
        'description',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_path', 'image_url'];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(TopicItemMedia::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getImagePathAttribute(): ?string
    {
        return $this->orderedMedia()->firstWhere('type', TopicItemMedia::TYPE_IMAGE)?->source;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->orderedMedia()->firstWhere('type', TopicItemMedia::TYPE_IMAGE)?->image_url;
    }

    private function orderedMedia()
    {
        return $this->relationLoaded('media')
            ? $this->media
            : $this->media()->get();
    }
}
