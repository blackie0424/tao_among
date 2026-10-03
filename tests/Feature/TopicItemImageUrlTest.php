<?php

use App\Models\TopicItem;
use App\Models\TopicItemMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('returns the first ordered image URL with id as the tiebreaker', function () {
    $item = TopicItem::factory()->create();
    $first = $item->media()->create([
        'type' => TopicItemMedia::TYPE_IMAGE,
        'source' => 'topic-items/first.jpg',
        'sort_order' => 1,
    ]);
    $item->media()->create([
        'type' => TopicItemMedia::TYPE_IMAGE,
        'source' => 'topic-items/second.jpg',
        'sort_order' => 1,
    ]);

    expect($item->fresh()->image_url)->toContain($first->source);
});

it('returns null when an item only has YouTube media', function () {
    $item = TopicItem::factory()->create();
    $item->media()->create([
        'type' => TopicItemMedia::TYPE_YOUTUBE,
        'source' => 'dQw4w9WgXcQ',
        'sort_order' => 0,
    ]);

    expect($item->fresh()->image_url)->toBeNull();
});

it('returns null when an item has no media', function () {
    expect(TopicItem::factory()->create()->image_url)->toBeNull();
});
