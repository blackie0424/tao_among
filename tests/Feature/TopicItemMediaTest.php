<?php

use App\Models\TopicItem;
use App\Models\TopicItemMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('orders media deterministically and relates it to a topic item', function () {
    $item = TopicItem::factory()->create();
    $later = TopicItemMedia::create([
        'topic_item_id' => $item->id,
        'type' => TopicItemMedia::TYPE_YOUTUBE,
        'source' => 'dQw4w9WgXcQ',
        'sort_order' => 1,
    ]);
    $first = TopicItemMedia::create([
        'topic_item_id' => $item->id,
        'type' => TopicItemMedia::TYPE_IMAGE,
        'source' => 'topic-items/first.jpg',
        'sort_order' => 0,
    ]);

    expect($item->media()->pluck('topic_item_media.id')->all())->toBe([$first->id, $later->id])
        ->and($first->topicItem->is($item))->toBeTrue();
});

it('deletes media records when their topic item is deleted', function () {
    $item = TopicItem::factory()->create();
    $media = TopicItemMedia::create([
        'topic_item_id' => $item->id,
        'type' => TopicItemMedia::TYPE_IMAGE,
        'source' => 'topic-items/cascade.jpg',
        'sort_order' => 0,
    ]);

    $item->delete();

    expect(TopicItemMedia::find($media->id))->toBeNull();
});

it('exposes type-specific media URLs', function () {
    Storage::fake('public');
    $item = TopicItem::factory()->create();
    $image = TopicItemMedia::create([
        'topic_item_id' => $item->id,
        'type' => TopicItemMedia::TYPE_IMAGE,
        'source' => 'topic-items/photo.jpg',
        'sort_order' => 0,
    ]);
    $youtube = TopicItemMedia::create([
        'topic_item_id' => $item->id,
        'type' => TopicItemMedia::TYPE_YOUTUBE,
        'source' => 'dQw4w9WgXcQ',
        'sort_order' => 1,
    ]);

    expect($image->image_url)->toContain('topic-items/photo.jpg')
        ->and($image->youtube_url)->toBeNull()
        ->and($youtube->image_url)->toBeNull()
        ->and($youtube->youtube_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});
