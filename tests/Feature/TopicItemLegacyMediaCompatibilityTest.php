<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Topic;
use App\Models\TopicItem;
use App\Models\TopicItemMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->storageMock = $this->mock(StorageServiceInterface::class);
    $this->storageMock->shouldReceive('getImageFolder')->zeroOrMoreTimes()->andReturn('images');
    $this->storageMock->shouldReceive('getWebpFolder')->zeroOrMoreTimes()->andReturn('webp');
    $this->storageMock->shouldReceive('getUrl')->zeroOrMoreTimes()
        ->andReturnUsing(fn (string $folder, string $path) => "https://storage.test/{$folder}/{$path}");
    $this->admin = User::factory()->admin()->create();
    $this->topic = Topic::first();
});

it('keeps the previous single-image create form operational', function () {
    $this->actingAs($this->admin)->post('/admin/topic-items', [
        'topic_id' => $this->topic->id,
        'title' => '舊表單新增',
        'image_path' => 'topic-items/legacy-create.jpg',
    ])->assertRedirect();

    $item = TopicItem::where('title', '舊表單新增')->firstOrFail();
    expect($item->image_path)->toBe('topic-items/legacy-create.jpg')
        ->and($item->media()->value('type'))->toBe(TopicItemMedia::TYPE_IMAGE);
});

it('keeps existing media when the previous edit form sends no image fields', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->withImage('topic-items/original.jpg')->create();
    $this->storageMock->shouldNotReceive('delete');

    $this->actingAs($this->admin)->put("/admin/topic-items/{$item->id}", [
        'topic_id' => $this->topic->id,
        'title' => '只更新標題',
    ])->assertRedirect();

    expect($item->fresh()->image_path)->toBe('topic-items/original.jpg');
});

it('supports replacing and removing an image through the previous edit form', function () {
    $replaceItem = TopicItem::factory()->for($this->topic, 'topic')->withImage('topic-items/replace-old.jpg')->create();
    $removeItem = TopicItem::factory()->for($this->topic, 'topic')->withImage('topic-items/remove-old.jpg')->create();

    $this->actingAs($this->admin)->put("/admin/topic-items/{$replaceItem->id}", [
        'topic_id' => $this->topic->id,
        'title' => $replaceItem->title,
        'image_path' => 'topic-items/replace-new.jpg',
    ])->assertRedirect();

    $this->actingAs($this->admin)->put("/admin/topic-items/{$removeItem->id}", [
        'topic_id' => $this->topic->id,
        'title' => $removeItem->title,
        'remove_image' => true,
    ])->assertRedirect();

    expect($replaceItem->fresh()->image_path)->toBe('topic-items/replace-new.jpg')
        ->and($removeItem->fresh()->image_path)->toBeNull();
});
